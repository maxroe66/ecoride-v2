<?php

namespace App\Controllers;

use App\Factories\ServiceLocator as SL;
use App\Validators\UserProfileValidator;
use App\Validators\QueryValidator;
use App\Validators\VehicleValidator;
use App\Helpers\ControllerHelper;
use App\Core\Response;
use Exception;

/**
 * Contrôleur utilisateur : gère les endpoints de profil
 */
class UserController
{
    /**
     * Endpoint : GET /api/user/credit
     * Retourne le solde de crédits de l'utilisateur authentifié
     */
    public static function getCredit(): void
    {
        $userId = ControllerHelper::getAuthUserId();

        try {
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
    public static function getCreditOperations(): void
    {
        $userId = ControllerHelper::getAuthUserId();

        try {
            $userRepo = SL::getUserRepository();
            $creditRepo = SL::getCreditOperationRepository();

            $operations = $creditRepo->findByUser($userId, null);
            $balance = $userRepo->getCredit($userId);

            // Calculer les totaux
            $totalCredit = 0.0;
            $totalDebit = 0.0;
            foreach ($operations as $op) {
                if (($op['type_operation'] ?? '') === 'credit') {
                    $totalCredit += (float)$op['montant'];
                } elseif (($op['type_operation'] ?? '') === 'debit') {
                    $totalDebit += (float)$op['montant'];
                }
            }

            Response::json(200, [
                'success' => true,
                'data' => [
                    'balance' => $balance,
                    'total_credit' => $totalCredit,
                    'total_debit' => $totalDebit,
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
    public static function getPreferences(): void
    {
        $userId = ControllerHelper::getAuthUserId();

        try {
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
    public static function addVehicle(): void
    {
        $userId = ControllerHelper::getAuthUserId();

        // Récupérer le JSON du corps
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        
        try {
            QueryValidator::validateJsonInput($json);
            $validatedData = VehicleValidator::validateVehicleCreation($json);
        } catch (Exception $e) {
            Response::json(400, ['success' => false, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]]);
            return;
        }

        try {
            $vehicleService = SL::getVehicleService();
            $vehicleData = $vehicleService->createVehicle($userId, $validatedData);

            Response::json(201, [
                'success' => true,
                'data' => array_merge($vehicleData, ['message' => 'Véhicule ajouté avec succès'])
            ]);
        } catch (Exception $e) {
            self::handleError($e);
        }
    }

    /**
     * Endpoint : GET /api/user/vehicles
     * Récupère les véhicules de l'utilisateur authentifié
     */
    public static function getVehicles(): void
    {
        $userId = ControllerHelper::getAuthUserId();

        try {
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
    public static function deleteVehicle(): void
    {
        $userId = ControllerHelper::getAuthUserId();

        // Extraire le paramètre id de la query string
        $queryParams = [];
        parse_str($_SERVER['QUERY_STRING'] ?? '', $queryParams);

        try {
            $vehicleId = VehicleValidator::validateVehicleId($queryParams['id'] ?? 0);
        } catch (Exception $e) {
            Response::json(400, ['success' => false, 'error' => ['code' => 'INVALID_INPUT', 'message' => $e->getMessage()]]);
            return;
        }

        try {
            $vehicleRepo = SL::getVehicleRepository();
            $deleted = $vehicleRepo->deleteByIdForUser($vehicleId, $userId);

            if (!$deleted) {
                Response::json(404, ['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Véhicule introuvable ou ne vous appartient pas']]);
                return;
            }

            Response::json(200, ['success' => true, 'data' => ['id' => $vehicleId, 'message' => 'Véhicule supprimé']]);
        } catch (Exception $e) {
            self::handleError($e);
        }
    }

    /**
     * Endpoint : PUT /api/user/profile
     * Met à jour le profil utilisateur (rôle, véhicules, préférences)
     */
    public static function updateProfile(): void
    {
        // RÉCUPERER L'UTILISATEUR AUTHENTIFIÉ
        $userId = ControllerHelper::getAuthUserId();

        // ÉTAPE 1 : Récupérer le JSON du corps de la requête
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        
        // ÉTAPE 2 : Vérifier que c'est du JSON valide
        try {
            QueryValidator::validateJsonInput($json);
        } catch (Exception $e) {
            Response::json(400, ['success' => false, 'error' => ['code' => 'INVALID_JSON', 'message' => $e->getMessage()]]);
            return;
        }
        
        // ÉTAPE 4 : Valider les données avec UserProfileValidator
        try {
            $validatedData = UserProfileValidator::validateUpdateProfile($json);
        } catch (Exception $e) {
            Response::json(400, ['success' => false, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]]);
            return;
        }
        
        // ÉTAPE 5 : Appeler UserService pour mettre à jour le profil
        try {
            $userService = SL::getUserService();
            
            $userUpdated = $userService->updateProfile(
                $userId,
                $validatedData['role'],
                $validatedData['vehicules'],
                $validatedData['preferences']
            );
            
            // ÉTAPE 6 : Retourner une réponse JSON de succès
            Response::json(200, ['success' => true, 'data' => $userUpdated->toArray()]);
        } catch (Exception $e) {
            $errorMsg = (strpos(get_class($e), 'PDO') !== false) ? 'Mise à jour impossible' : $e->getMessage();
            Response::json(422, ['success' => false, 'error' => ['code' => 'UPDATE_FAILED', 'message' => $errorMsg]]);
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