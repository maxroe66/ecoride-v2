<?php

namespace App\Controllers;

use App\Factories\DatabaseFactory;
use App\Repositories\ParticipationRepository;
use App\Repositories\TrajetRepository;
use App\Repositories\UserRepository;
use App\Repositories\CreditOperationRepository;
use App\Validators\CancellationValidator;
use App\Services\CancellationService;
use App\Services\EmailService;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use Exception;

/**
 * Contrôleur pour les participations aux covoiturages.
 * Routes: POST /api/participations/{id}/annuler
 */
class ParticipationController
{
    /**
     * Annule une participation en tant que passager
     * POST /api/participations/{id}/annuler
     * Paramètres: id (query) - ID de la participation
     */
    public static function cancelParticipation(): void
    {
        header('Content-Type: application/json');

        // Récupérer l'ID de la participation depuis les paramètres dynamiques du routeur
        $pathParams = $_REQUEST['_path_params'] ?? [];
        $participationId = $pathParams[0] ?? null;

        try {
            // Authentification requise
            $user = AuthMiddleware::getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Authentification requise']]);
                return;
            }

            $userId = (int)$user['id'];

            // Valider les paramètres
            try {
                $validated = CancellationValidator::validateParticipationCancellation((int)$participationId, $userId);
                $participationId = $validated['participation_id'];
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]]);
                return;
            }

            // Vérifier le token CSRF
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $csrf = new CsrfMiddleware();
                if (!$csrf->validateToken()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => ['code' => 'INVALID_CSRF', 'message' => 'Token CSRF invalide']]);
                    return;
                }
            }

            // Récupérer la base de données
            $db = DatabaseFactory::getConnection();

            // Initialiser les repositories et services
            $trajetRepo = new TrajetRepository($db);
            $participationRepo = new ParticipationRepository($db);
            $userRepo = new UserRepository($db);
            $creditOpRepo = new CreditOperationRepository($db);
            $emailService = new EmailService();

            $cancellationService = new CancellationService(
                $trajetRepo,
                $participationRepo,
                $userRepo,
                $creditOpRepo
            );

            // Récupérer la participation pour obtenir le covoiturage_id
            $stmt = $db->prepare('SELECT * FROM participation WHERE participation_id = ?');
            $stmt->execute([$participationId]);
            $participation = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$participation) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Participation introuvable']]);
                return;
            }

            $tripId = (int)$participation['covoiturage_id'];

            // Annuler la participation (utilise tripId et userId)
            $result = $cancellationService->cancelParticipationAsPassenger($tripId, $userId);

            // Récupérer les détails pour envoyer l'email au chauffeur
            $trajet = $trajetRepo->getTrajetDetail($tripId);
            $driver = $userRepo->getUserById((int)($trajet['utilisateur_id'] ?? 0));

            if ($driver && $trajet) {
                // Envoyer email au chauffeur
                $emailService->sendParticipantCancellationToDriver(
                    $driver,
                    $trajet,
                    $user['pseudo'] ?? ($user['prenom'] . ' ' . $user['nom'])
                );
            }

            // Retourner la réponse
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Participation annulée avec succès',
                'refund_amount' => $result['refunded_amount'] ?? 0
            ]);

        } catch (Exception $e) {
            error_log('[ParticipationController::cancelParticipation] Exception : ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => $e->getMessage()]]);
        }
    }
}
