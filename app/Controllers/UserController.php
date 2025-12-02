<?php

namespace App\Controllers;

use App\Factories\DatabaseFactory;
use App\Repositories\UserRepository;
use App\Services\UserService;
use App\Validators\UserProfileValidator;
use App\Repositories\VehicleRepository;

/**
 * Contrôleur utilisateur : gère les endpoints de profil
 */
class UserController
{
    /**
     * Endpoint : PUT /api/user/profile
     * Met à jour le profil utilisateur (rôle, véhicules, préférences)
     */
    public static function updateProfile(): void
    {
        // Étape 1 : Récupérer le JSON du corps de la requête
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        
        // Étape 2 : Vérifier que c'est du JSON valide
        if (!is_array($json)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['code' => 'INVALID_JSON', 'message' => 'Corps JSON invalide']]);
            return;
        }
        
        // Étape 3 : Valider les données avec UserProfileValidator
        try {
            $validatedData = UserProfileValidator::validateUpdateProfile($json);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]]);
            return;
        }
        
        // Étape 4 : Appeler UserService pour mettre à jour le profil
        try {
            // Récupérer l'ID utilisateur (supposé venir du JWT ou de la session)
            $userId = $_SESSION['user_id'] ?? null; // À adapter selon votre authentification
            
            if (!$userId) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Utilisateur non authentifié']]);
                return;
            }
            
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
            
            // Étape 5 : Retourner une réponse JSON de succès
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $userUpdated->toArray()]);
        } catch (\Exception $e) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => ['code' => 'UPDATE_FAILED', 'message' => $e->getMessage()]]);
        }
    }
}