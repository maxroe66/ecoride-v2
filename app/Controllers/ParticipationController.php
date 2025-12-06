<?php

namespace App\Controllers;

use App\Factories\ServiceLocator as SL;
use App\Factories\DatabaseFactory;
use App\Validators\CancellationValidator;
use App\Validators\QueryValidator;
use App\Validators\ParticipationValidator;
use App\Helpers\ControllerHelper;
use App\Core\Response;
use Exception;

/**
 * Contrôleur pour les participations aux covoiturages.
 * Routes: POST /api/participations/request, /validate, /confirm, /{id}/annuler
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

    /**
     * Demander une participation à un covoiturage
     * POST /api/participations/request
     * Body: { covoiturage_id, nb_places }
     */
    public static function requestParticipation(): void
    {
        $userId = ControllerHelper::getAuthUserId();
        $json = json_decode(file_get_contents('php://input'), true);
        
        try {
            QueryValidator::validateJsonInput($json);
            $validated = ParticipationValidator::validateParticipationRequest($json);
        } catch (Exception $e) {
            Response::json(400, ['success' => false, 'error' => ['code' => 'INVALID_INPUT', 'message' => $e->getMessage()]]);
            return;
        }
        
        try {
            $service = SL::getParticipationService();
            $result = $service->requestParticipation($userId, $validated['covoiturage_id'], $validated['nb_places']);
            Response::json(201, ['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            Response::json(400, ['success' => false, 'error' => ['code' => 'OPERATION_FAILED', 'message' => $e->getMessage()]]);
        }
    }

    /**
     * Valider une participation (1ère confirmation)
     * POST /api/participations/validate
     * Body: { participation_id }
     */
    public static function validateParticipation(): void
    {
        $userId = ControllerHelper::getAuthUserId();
        $json = json_decode(file_get_contents('php://input'), true);
        
        try {
            QueryValidator::validateJsonInput($json);
            $participationId = ParticipationValidator::validateParticipationId($json['participation_id'] ?? null);
        } catch (Exception $e) {
            Response::json(400, ['success' => false, 'error' => ['code' => 'INVALID_INPUT', 'message' => $e->getMessage()]]);
            return;
        }

        try {
            $service = SL::getParticipationService();
            $result = $service->validateParticipation($participationId);
            Response::json(200, ['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            Response::json(400, ['success' => false, 'error' => ['code' => 'OPERATION_FAILED', 'message' => $e->getMessage()]]);
        }
    }

    /**
     * Confirmer une participation (2ème confirmation finale)
     * POST /api/participations/confirm
     * Body: { participation_id }
     */
    public static function confirmParticipation(): void
    {
        $userId = ControllerHelper::getAuthUserId();
        $json = json_decode(file_get_contents('php://input'), true);
        
        try {
            QueryValidator::validateJsonInput($json);
            $participationId = ParticipationValidator::validateParticipationId($json['participation_id'] ?? null);
        } catch (Exception $e) {
            Response::json(400, ['success' => false, 'error' => ['code' => 'INVALID_INPUT', 'message' => $e->getMessage()]]);
            return;
        }

        try {
            $service = SL::getParticipationService();
            $result = $service->confirmParticipation($participationId);
            Response::json(200, ['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            Response::json(400, ['success' => false, 'error' => ['code' => 'OPERATION_FAILED', 'message' => $e->getMessage()]]);
        }
    }
}
