<?php

namespace App\Controllers;

use App\Factories\ServiceLocator as SL;
use App\Validators\UserProfileValidator;
use App\Validators\QueryValidator;
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
                        $errorMsg = (strpos(get_class($e), 'PDO') !== false) ? 'Erreur serveur' : $e->getMessage();

            Response::json(500, ['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => $errorMsg]]);
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
                        $errorMsg = (strpos(get_class($e), 'PDO') !== false) ? 'Erreur serveur' : $e->getMessage();

            Response::json(500, ['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => $errorMsg]]);
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
                        $errorMsg = (strpos(get_class($e), 'PDO') !== false) ? 'Erreur serveur' : $e->getMessage();

            Response::json(500, ['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => $errorMsg]]);
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
        } catch (Exception $e) {
            Response::json(400, ['success' => false, 'error' => ['code' => 'INVALID_JSON', 'message' => $e->getMessage()]]);
            return;
        }

        // Valider les champs requis
        $required = ['modele', 'marque', 'couleur', 'date_premiere_immatriculation', 'nb_places', 'energie', 'immatriculation'];
        foreach ($required as $field) {
            if (empty($json[$field])) {
                Response::json(400, ['success' => false, 'error' => ['code' => 'MISSING_FIELD', 'message' => "Le champ '$field' est requis"]]);
                return;
            }
        }

        try {
            $vehicleRepo = SL::getVehicleRepository();
            $marqueRepo = SL::getMarqueRepository();

            // Résoudre la marque à partir du libellé
            $marqueId = $marqueRepo->findOrCreateByName(trim((string)$json['marque']));

            // Créer le véhicule
            $vehicle = new \App\Models\Vehicules(
                $json['modele'],
                $marqueId,
                $json['immatriculation'],
                $json['energie'],
                (int)$json['nb_places'],
                $userId,
                $json['couleur'] ?? null,
                $json['date_premiere_immatriculation'] ?? null,
                $json['energie'] === 'electrique' ? true : false
            );

            $vehicleId = $vehicleRepo->create($vehicle);

            Response::json(201, [
                'success' => true,
                'data' => [
                    'id' => $vehicleId,
                    'modele' => $json['modele'],
                    'marque' => $json['marque'],
                    'couleur' => $json['couleur'],
                    'immatriculation' => $json['immatriculation'],
                    'message' => 'Véhicule ajouté avec succès'
                ]
            ]);
        } catch (Exception $e) {
                        $errorMsg = (strpos(get_class($e), 'PDO') !== false) ? 'Erreur serveur' : $e->getMessage();

            Response::json(500, ['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => $errorMsg]]);
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

            // Convertir les objets Vehicules en tableau pour JSON
            $vehiclesArray = array_map(function ($vehicle) {
                return [
                    'id' => $vehicle->id,
                    'marque_id' => $vehicle->marque_id,
                    'marque' => $vehicle->marque_libelle ?? null,
                    'modele' => $vehicle->modele,
                    'couleur' => $vehicle->couleur,
                    'immatriculation' => $vehicle->immatriculation,
                    'date_premiere_immatriculation' => $vehicle->date_premiere_immatriculation,
                    'nb_places' => $vehicle->nb_places,
                    'energie' => $vehicle->energie,
                    'est_ecologique' => (bool)$vehicle->est_ecologique
                ];
            }, $vehicles);

            Response::json(200, ['success' => true, 'data' => $vehiclesArray]);
        } catch (Exception $e) {
                        $errorMsg = (strpos(get_class($e), 'PDO') !== false) ? 'Erreur serveur' : $e->getMessage();

            Response::json(500, ['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => $errorMsg]]);
        }
    }

    /**
     * Endpoint : DELETE /api/user/vehicles?id=123
     * Supprime un véhicule appartenant à l'utilisateur authentifié
     */
    public static function deleteVehicle(): void
    {
        $userId = ControllerHelper::getAuthUserId();

        $vehicleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($vehicleId <= 0) {
            Response::json(400, ['success' => false, 'error' => ['code' => 'INVALID_INPUT', 'message' => 'Paramètre id manquant ou invalide']]);
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
                        $errorMsg = (strpos(get_class($e), 'PDO') !== false) ? 'Erreur serveur' : $e->getMessage();

            Response::json(500, ['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => $errorMsg]]);
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
}