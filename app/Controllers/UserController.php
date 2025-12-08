<?php

namespace App\Controllers;

use App\Factories\ServiceLocator as SL;
use App\Validators\UserProfileValidator;
use App\Validators\VehicleValidator;
use App\DTO\AddVehicleRequest;
use App\DTO\UpdateProfileRequest;
use App\Helpers\ControllerHelper;
use App\Core\Request;
use App\Core\Response;
use Exception;

/**
 * Contrôleur utilisateur : gère les endpoints de profil
 * Architecture: Request → DTO → Validator → Service → Response
 */
class UserController
{
    /**
     * Endpoint : GET /api/user/credit
     * Retourne le solde de crédits de l'utilisateur authentifié
     */
    public static function getCredit(Request $req): void
    {
        try {
            $userId = ControllerHelper::getAuthUserId();

            $userRepo = SL::getUserRepository();
            $credit = $userRepo->getCredit($userId);

            Response::json(200, ['success' => true, 'data' => ['credit' => $credit]]);
        } catch (Exception $e) {
            self::handleError($e);
        }
    }

    /**
     * Endpoint : GET /api/user/credit/operations
     * Retourne l'historique des opérations de crédit/débit et des totaux
     */
    public static function getCreditOperations(Request $req): void
    {
        try {
            $userId = ControllerHelper::getAuthUserId();

            // Repository : Récupérer les données
            $userRepo = SL::getUserRepository();
            $creditRepo = SL::getCreditOperationRepository();

            $operations = $creditRepo->findByUser($userId, null);
            $balance = $userRepo->getCredit($userId);

            // Service : Calculer les totaux (✅ déplacé du controller)
            $creditService = SL::getCreditOperationService();
            $totals = $creditService->calculateTotals($operations);

            Response::json(200, [
                'success' => true,
                'data' => [
                    'balance' => $balance,
                    'total_credit' => $totals['total_credit'],
                    'total_debit' => $totals['total_debit'],
                    'operations' => $operations
                ]
            ]);
        } catch (Exception $e) {
            self::handleError($e);
        }
    }
    /**
     * Endpoint : GET /api/user/preferences
     * Récupère les préférences de l'utilisateur authentifié
     */
    public static function getPreferences(Request $req): void
    {
        try {
            $userId = ControllerHelper::getAuthUserId();

            $userRepo = SL::getUserRepository();
            $prefs = $userRepo->getPreferences($userId);

            Response::json(200, ['success' => true, 'data' => $prefs]);
        } catch (Exception $e) {
            self::handleError($e);
        }
    }
    /**
     * Endpoint : POST /api/user/vehicles
     * Ajoute un véhicule pour l'utilisateur authentifié
     */
    public static function addVehicle(Request $req): void
    {
        try {
            $userId = ControllerHelper::getAuthUserId();

            // 1. DTO : Transformer tableau → objet typé
            $vehicleDto = AddVehicleRequest::fromArray($req->getJsonBody());
            
            // 2. Validation métier
            $validatedData = VehicleValidator::validateVehicleCreation([
                'marque' => $vehicleDto->marque,
                'modele' => $vehicleDto->modele,
                'immatriculation' => $vehicleDto->immatriculation,
                'energie' => $vehicleDto->energie,
                'nb_places' => $vehicleDto->nbPlaces,
                'couleur' => $vehicleDto->couleur,
                'date_premiere_immatriculation' => $vehicleDto->datePremiereImmatriculation
            ]);

            // 3. Service : Créer le véhicule
            $vehicleService = SL::getVehicleService();
            $vehicleData = $vehicleService->createVehicle($userId, $validatedData);

            Response::json(201, [
                'success' => true,
                'data' => array_merge($vehicleData, ['message' => 'Véhicule ajouté avec succès'])
            ]);
            
        } catch (\InvalidArgumentException $e) {
            // Erreur DTO
            Response::json(400, [
                'success' => false,
                'error' => ['code' => 'INVALID_JSON', 'message' => $e->getMessage()]
            ]);
        } catch (Exception $e) {
            // Erreur validation métier ou service
            Response::json(400, [
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]
            ]);
        }
    }

    /**
     * Endpoint : GET /api/user/vehicles
     * Récupère les véhicules de l'utilisateur authentifié
     */
    public static function getVehicles(Request $req): void
    {
        try {
            $userId = ControllerHelper::getAuthUserId();

            $vehicleRepo = SL::getVehicleRepository();
            $vehicles = $vehicleRepo->findByUserId($userId);

            $vehiclesArray = array_map(fn($v) => $v->toArray(), $vehicles);

            Response::json(200, ['success' => true, 'data' => $vehiclesArray]);
        } catch (Exception $e) {
            self::handleError($e);
        }
    }

    /**
     * Endpoint : DELETE /api/user/vehicles?id=123
     * Supprime un véhicule appartenant à l'utilisateur authentifié
     */
    public static function deleteVehicle(Request $req): void
    {
        $query = $req->getQueryParams();

        try {
            $userId = ControllerHelper::getAuthUserId();
            
            // Validation
            $vehicleId = VehicleValidator::validateVehicleId($query['id'] ?? 0);
            
            // Service/Repository : Supprimer
            $vehicleRepo = SL::getVehicleRepository();

            if ($vehicleRepo->isVehicleUsed($vehicleId)) {
                Response::json(409, [
                    'success' => false,
                    'error' => [
                        'code' => 'VEHICLE_IN_USE',
                        'message' => 'Ce véhicule est utilisé par au moins un covoiturage. Supprimez ou mettez à jour les trajets associés avant de le retirer.'
                    ]
                ]);
                return;
            }

            $deleted = $vehicleRepo->deleteByIdForUser($vehicleId, $userId);

            if (!$deleted) {
                Response::json(404, [
                    'success' => false,
                    'error' => ['code' => 'NOT_FOUND', 'message' => 'Véhicule introuvable ou ne vous appartient pas']
                ]);
                return;
            }

            Response::json(200, ['success' => true, 'data' => ['id' => $vehicleId, 'message' => 'Véhicule supprimé']]);
        } catch (Exception $e) {
            Response::json(400, [
                'success' => false,
                'error' => ['code' => 'INVALID_INPUT', 'message' => $e->getMessage()]
            ]);
        }
    }

    /**
     * Endpoint : PUT /api/user/profile
     * Met à jour le profil utilisateur (rôle, véhicules, préférences)
     */
    public static function updateProfile(Request $req): void
    {
        try {
            // 1. Récupérer l'utilisateur authentifié
            $userId = ControllerHelper::getAuthUserId();

            // 2. DTO : Transformer tableau → objet typé
            $profileDto = UpdateProfileRequest::fromArray($req->getJsonBody());
            
            // 3. Validation métier
            $validatedData = UserProfileValidator::validateUpdateProfile([
                'role' => $profileDto->role,
                'vehicules' => $profileDto->vehicules,
                'preferences' => $profileDto->preferences
            ]);
            
            // 4. Service : Mettre à jour le profil
            $userService = SL::getUserService();
            $userUpdated = $userService->updateProfile(
                $userId,
                $validatedData['role'],
                $validatedData['vehicules'],
                $validatedData['preferences']
            );
            
            // 5. Response : Succès
            Response::json(200, ['success' => true, 'data' => $userUpdated->toArray()]);
            
        } catch (\InvalidArgumentException $e) {
            // Erreur DTO
            Response::json(400, [
                'success' => false,
                'error' => ['code' => 'INVALID_JSON', 'message' => $e->getMessage()]
            ]);
        } catch (Exception $e) {
            // Erreur validation métier ou service
            $errorMsg = (strpos(get_class($e), 'PDO') !== false) ? 'Mise à jour impossible' : $e->getMessage();
            Response::json(422, [
                'success' => false,
                'error' => ['code' => 'UPDATE_FAILED', 'message' => $errorMsg]
            ]);
        }
    }

    /**
     * Gère les erreurs communes dans les endpoints
     * @param Exception $e L'exception levée
     */
    private static function handleError(Exception $e): void
    {
        $errorMsg = (strpos(get_class($e), 'PDO') !== false) ? 'Erreur serveur' : $e->getMessage();
        Response::json(500, ['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => $errorMsg]]);
    }
}