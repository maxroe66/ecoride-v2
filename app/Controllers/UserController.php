<?php

namespace App\Controllers;

use App\Factories\DatabaseFactory;
use App\Repositories\UserRepository;
use App\Services\UserService;
use App\Validators\UserProfileValidator;
use App\Repositories\VehicleRepository;
use App\Repositories\MarqueRepository;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
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
        header('Content-Type: application/json');

        try {
            $middleware = new AuthMiddleware();
            $userData = $middleware->authenticate();
            $userId = (int)$userData['user_id'];
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Authentification requise.']]);
            return;
        }

        try {
            $db = DatabaseFactory::getConnection();
            $userRepo = new UserRepository($db);
            $credit = $userRepo->getCredit($userId);

            http_response_code(200);
            echo json_encode(['success' => true, 'data' => ['credit' => $credit]]);
        } catch (Exception $e) {
            http_response_code(500);
            $errorMsg = (strpos(get_class($e), 'PDO') !== false) ? 'Erreur serveur' : $e->getMessage();
            echo json_encode(['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => $errorMsg]]);
        }
    }
    /**
     * Endpoint : GET /api/user/preferences
     * Récupère les préférences de l'utilisateur authentifié
     */
    public static function getPreferences(): void
    {
        header('Content-Type: application/json');

        try {
            $middleware = new AuthMiddleware();
            $userData = $middleware->authenticate();
            $userId = (int)$userData['user_id'];
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Authentification requise.']]);
            return;
        }

        try {
            $db = DatabaseFactory::getConnection();
            $userRepo = new UserRepository($db);
            $prefs = $userRepo->getPreferences($userId);

            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $prefs]);
        } catch (Exception $e) {
            http_response_code(500);
            $errorMsg = (strpos(get_class($e), 'PDO') !== false) ? 'Erreur serveur' : $e->getMessage();
            echo json_encode(['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => $errorMsg]]);
        }
    }
    /**
     * Endpoint : POST /api/user/vehicles
     * Ajoute un véhicule pour l'utilisateur authentifié
     */
    public static function addVehicle(): void
    {
        header('Content-Type: application/json');

        try {
            $middleware = new AuthMiddleware();
            $userData = $middleware->authenticate();
            $userId = (int)$userData['user_id'];
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Authentification requise.']]);
            return;
        }

        // CSRF
        (new CsrfMiddleware())->validate();

        // Récupérer le JSON du corps
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        
        if (!is_array($json)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['code' => 'INVALID_JSON', 'message' => 'Corps JSON invalide']]);
            return;
        }

        // Valider les champs requis
        $required = ['modele', 'marque', 'couleur', 'date_premiere_immatriculation', 'nb_places', 'energie', 'immatriculation'];
        foreach ($required as $field) {
            if (empty($json[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => ['code' => 'MISSING_FIELD', 'message' => "Le champ '$field' est requis"]]);
                return;
            }
        }

        try {
            $db = DatabaseFactory::getConnection();
            $vehicleRepo = new VehicleRepository($db);
            $marqueRepo = new MarqueRepository($db);

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

            http_response_code(201);
            echo json_encode([
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
            http_response_code(500);
            $errorMsg = (strpos(get_class($e), 'PDO') !== false) ? 'Erreur serveur' : $e->getMessage();
            echo json_encode(['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => $errorMsg]]);
        }
    }

    /**
     * Endpoint : GET /api/user/vehicles
     * Récupère les véhicules de l'utilisateur authentifié
     */
    public static function getVehicles(): void
    {
        header('Content-Type: application/json');

        try {
            $middleware = new AuthMiddleware();
            $userData = $middleware->authenticate();
            $userId = (int)$userData['user_id'];
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Authentification requise.']]);
            return;
        }

        try {
            $db = DatabaseFactory::getConnection();
            $vehicleRepo = new VehicleRepository($db);
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

            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $vehiclesArray]);
        } catch (Exception $e) {
            http_response_code(500);
            $errorMsg = (strpos(get_class($e), 'PDO') !== false) ? 'Erreur serveur' : $e->getMessage();
            echo json_encode(['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => $errorMsg]]);
        }
    }

    /**
     * Endpoint : DELETE /api/user/vehicles?id=123
     * Supprime un véhicule appartenant à l'utilisateur authentifié
     */
    public static function deleteVehicle(): void
    {
        header('Content-Type: application/json');

        try {
            $middleware = new AuthMiddleware();
            $userData = $middleware->authenticate();
            $userId = (int)$userData['user_id'];
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Authentification requise.']]);
            return;
        }

        // CSRF
        (new CsrfMiddleware())->validate();

        $vehicleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($vehicleId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['code' => 'INVALID_INPUT', 'message' => 'Paramètre id manquant ou invalide']]);
            return;
        }

        try {
            $db = DatabaseFactory::getConnection();
            $vehicleRepo = new VehicleRepository($db);
            $deleted = $vehicleRepo->deleteByIdForUser($vehicleId, $userId);

            if (!$deleted) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Véhicule introuvable ou ne vous appartient pas']]);
                return;
            }

            http_response_code(200);
            echo json_encode(['success' => true, 'data' => ['id' => $vehicleId, 'message' => 'Véhicule supprimé']]);
        } catch (Exception $e) {
            http_response_code(500);
            $errorMsg = (strpos(get_class($e), 'PDO') !== false) ? 'Erreur serveur' : $e->getMessage();
            echo json_encode(['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => $errorMsg]]);
        }
    }

    /**
     * Endpoint : PUT /api/user/profile
     * Met à jour le profil utilisateur (rôle, véhicules, préférences)
     */
    public static function updateProfile(): void
    {
        // Définir le header avant toute sortie
        header('Content-Type: application/json');

        // ÉTAPE 1 : AUTHENTIFICATION via AuthMiddleware
        try {
            $middleware = new AuthMiddleware();
            $userData = $middleware->authenticate();
            $userId = (int)$userData['user_id'];
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Authentification requise. Veuillez vous connecter.']]);
            return;
        }

        // CSRF
        (new CsrfMiddleware())->validate();

        // ÉTAPE 2 : Récupérer le JSON du corps de la requête
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        
        // ÉTAPE 3 : Vérifier que c'est du JSON valide
        if (!is_array($json)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['code' => 'INVALID_JSON', 'message' => 'Corps JSON invalide']]);
            return;
        }
        
        // ÉTAPE 4 : Valider les données avec UserProfileValidator
        try {
            $validatedData = UserProfileValidator::validateUpdateProfile($json);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]]);
            return;
        }
        
        // ÉTAPE 5 : Appeler UserService pour mettre à jour le profil
        try {
            $db = DatabaseFactory::getConnection();
            $userRepo = new UserRepository($db);
            $vehicleRepo = new VehicleRepository($db);
            $userService = new UserService($userRepo, $vehicleRepo);
            
            $userUpdated = $userService->updateProfile(
                $userId,
                $validatedData['role'],
                $validatedData['vehicules'],
                $validatedData['preferences']
            );
            
            // ÉTAPE 6 : Retourner une réponse JSON de succès
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $userUpdated->toArray()]);
        } catch (Exception $e) {
            http_response_code(422);
            $errorMsg = (strpos(get_class($e), 'PDO') !== false) ? 'Mise à jour impossible' : $e->getMessage();
            echo json_encode(['success' => false, 'error' => ['code' => 'UPDATE_FAILED', 'message' => $errorMsg]]);
        }
    }
}