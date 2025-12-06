<?php

namespace App\Controllers;

use App\Factories\ServiceLocator as SL;
use App\Factories\DatabaseFactory;
use App\Validators\CancellationValidator;
use App\Helpers\ControllerHelper;
use App\Core\Response;
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
        // Récupérer l'ID de la participation depuis les paramètres dynamiques du routeur
        $participationId = ControllerHelper::getPathParam(0);
        $userId = ControllerHelper::getAuthUserId();

        try {
            // 1. Valider les paramètres
            try {
                $validated = CancellationValidator::validateParticipationCancellation((int)$participationId, $userId);
                $participationId = $validated['participation_id'];
            } catch (Exception $e) {
                Response::json(400, ['success' => false, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]]);
                return;
            }

            // Initialiser les services via ServiceLocator
            $cancellationService = SL::getCancellationService();
            $emailService = SL::getEmailService();
            $trajetRepo = SL::getTrajetRepository();
            $userRepo = SL::getUserRepository();

            // Récupérer la participation pour obtenir le covoiturage_id (requête PDO directe)
            $db = DatabaseFactory::getConnection();
            $stmt = $db->prepare('SELECT * FROM participation WHERE participation_id = ?');
            $stmt->execute([$participationId]);
            $participation = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$participation) {
                Response::json(404, ['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Participation introuvable']]);
                return;
            }

            $tripId = (int)$participation['covoiturage_id'];

            // Annuler la participation (transaction gérée par le service)
            $result = $cancellationService->cancelParticipationAsPassenger($tripId, $userId);

            // Récupérer les détails pour envoyer l'email au chauffeur
            $trajet = $trajetRepo->getTrajetDetail($tripId);
            $driver = $userRepo->getUserById((int)($trajet['utilisateur_id'] ?? 0));
            $passenger = $userRepo->getUserById($userId);

            if ($driver && $trajet && $passenger) {
                // Envoyer email au chauffeur
                $emailService->sendParticipantCancellationToDriver(
                    $driver,
                    $trajet,
                    $passenger['pseudo'] ?? ($passenger['prenom'] . ' ' . $passenger['nom'])
                );
            }

            // Retourner la réponse
            Response::json(200, [
                'success' => true,
                'message' => 'Participation annulée avec succès',
                'refund_amount' => $result['refunded_amount'] ?? 0
            ]);

        } catch (Exception $e) {
            error_log('[ParticipationController::cancelParticipation] Exception : ' . $e->getMessage());
            Response::json(500, ['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => $e->getMessage()]]);
        }
    }
}
