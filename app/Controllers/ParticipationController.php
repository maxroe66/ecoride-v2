<?php

namespace App\Controllers;

use App\Factories\ServiceLocator as SL;
use App\Validators\CancellationValidator;
use App\Validators\ParticipationValidator;
use App\DTO\RequestParticipationRequest;
use App\DTO\ParticipationActionRequest;
use App\Helpers\ControllerHelper;
use App\Core\Request;
use App\Core\Response;
use Exception;

/**
 * Contrôleur pour les participations aux covoiturages.
 * Routes: POST /api/participations/request, /validate, /confirm, /{id}/annuler
 * Architecture: Request → DTO → Validator → Service → Response
 */
class ParticipationController
{
    /**
     * Annule une participation en tant que passager
     * POST /api/participations/{id}/annuler
     * Paramètres: id (path param) - ID de la participation
     */
    public static function cancelParticipation(Request $req): void
    {
        try {
            // 1. Récupérer l'ID de la participation depuis les paramètres dynamiques
            $participationId = ControllerHelper::getPathParam(0);
            $userId = ControllerHelper::getAuthUserId();

            // 2. Validation
            try {
                $validated = CancellationValidator::validateParticipationCancellation((int)$participationId, $userId);
                $participationId = $validated['participation_id'];
            } catch (Exception $e) {
                Response::json(400, [
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => $e->getMessage()
                    ]
                ]);
                return;
            }

            // 3. Récupérer la participation via Repository (✅ FIXÉ : plus de SQL direct)
            $participationRepo = SL::getParticipationRepository();
            $participation = $participationRepo->findById($participationId);

            if (!$participation) {
                Response::json(404, [
                    'success' => false,
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Participation introuvable'
                    ]
                ]);
                return;
            }

            $tripId = (int)$participation['covoiturage_id'];

            // 4. Service : Annuler la participation (transaction gérée)
            $cancellationService = SL::getCancellationService();
            $result = $cancellationService->cancelParticipationAsPassenger($tripId, $userId);

            // 5. Récupérer les détails pour notification email
            $trajetRepo = SL::getTrajetRepository();
            $userRepo = SL::getUserRepository();
            $emailService = SL::getEmailService();

            $trajet = $trajetRepo->getTrajetDetail($tripId);
            $driver = $userRepo->getUserById((int)($trajet['utilisateur_id'] ?? 0));
            $passenger = $userRepo->getUserById($userId);

            if ($driver && $trajet && $passenger) {
                $emailService->sendParticipantCancellationToDriver(
                    $driver,
                    $trajet,
                    $passenger['pseudo'] ?? ($passenger['prenom'] . ' ' . $passenger['nom'])
                );
            }

            // 6. Response : Retourner succès
            Response::json(200, [
                'success' => true,
                'message' => 'Participation annulée avec succès',
                'refund_amount' => $result['refunded_amount'] ?? 0,
                'driver_notified' => true,
                'driver_name' => $driver ? ($driver['nom'] . ' ' . $driver['prenom']) : null
            ]);

        } catch (Exception $e) {
            error_log('[ParticipationController::cancelParticipation] Exception : ' . $e->getMessage());
            Response::json(500, [
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => 'Erreur lors de l\'annulation de la participation'
                ]
            ]);
        }
    }

    /**
     * Demander une participation à un covoiturage
     * POST /api/participations/request
     * Body: { covoiturage_id, nb_places }
     */
    public static function requestParticipation(Request $req): void
    {
        try {
            // 1. Récupérer utilisateur authentifié
            $userId = ControllerHelper::getAuthUserId();

            // 2. DTO : Transformer tableau → objet typé
            $participationDto = RequestParticipationRequest::fromArray($req->getJsonBody());
            
            // 3. Validator : Validation métier (si nécessaire - peut être ajouté plus tard)
            // ParticipationValidator::validateRequest($participationDto);
            
            // 4. Service : Logique métier
            $service = SL::getParticipationService();
            $result = $service->requestParticipation(
                $userId,
                $participationDto->covoiturageId,
                $participationDto->nbPlaces
            );
            
            // 5. Response : Succès
            Response::json(201, ['success' => true, 'data' => $result]);
            
        } catch (\InvalidArgumentException $e) {
            // Erreur DTO (champs manquants)
            Response::json(400, [
                'success' => false,
                'error' => [
                    'code' => 'INVALID_INPUT',
                    'message' => $e->getMessage()
                ]
            ]);
        } catch (Exception $e) {
            // Erreur service (places insuffisantes, déjà participant, etc.)
            Response::json(400, [
                'success' => false,
                'error' => [
                    'code' => 'OPERATION_FAILED',
                    'message' => $e->getMessage()
                ]
            ]);
        }
    }

    /**
     * Valider une participation (1ère confirmation)
     * POST /api/participations/validate
     * Body: { participation_id }
     */
    public static function validateParticipation(Request $req): void
    {
        try {
            // 1. DTO : Transformer tableau → objet typé
            $actionDto = ParticipationActionRequest::fromArray($req->getJsonBody());
            
            // 2. Service : Logique métier
            $service = SL::getParticipationService();
            $result = $service->validateParticipation($actionDto->participationId);
            
            // 3. Response : Succès
            Response::json(200, ['success' => true, 'data' => $result]);
            
        } catch (\InvalidArgumentException $e) {
            // Erreur DTO (champ manquant)
            Response::json(400, [
                'success' => false,
                'error' => [
                    'code' => 'INVALID_INPUT',
                    'message' => $e->getMessage()
                ]
            ]);
        } catch (Exception $e) {
            // Erreur service
            Response::json(400, [
                'success' => false,
                'error' => [
                    'code' => 'OPERATION_FAILED',
                    'message' => $e->getMessage()
                ]
            ]);
        }
    }

    /**
     * Confirmer une participation (2ème confirmation finale)
     * POST /api/participations/confirm
     * Body: { participation_id }
     */
    public static function confirmParticipation(Request $req): void
    {
        try {
            // 1. DTO : Transformer tableau → objet typé
            $actionDto = ParticipationActionRequest::fromArray($req->getJsonBody());
            
            // 2. Service : Logique métier
            $service = SL::getParticipationService();
            $result = $service->confirmParticipation($actionDto->participationId);
            
            // 3. Response : Succès
            Response::json(200, ['success' => true, 'data' => $result]);
            
        } catch (\InvalidArgumentException $e) {
            // Erreur DTO (champ manquant)
            Response::json(400, [
                'success' => false,
                'error' => [
                    'code' => 'INVALID_INPUT',
                    'message' => $e->getMessage()
                ]
            ]);
        } catch (Exception $e) {
            // Erreur service
            Response::json(400, [
                'success' => false,
                'error' => [
                    'code' => 'OPERATION_FAILED',
                    'message' => $e->getMessage()
                ]
            ]);
        }
    }
}
