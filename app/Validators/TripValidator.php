<?php

namespace App\Validators;

use Exception;

class TripValidator
{
    /**
     * Valide un ID de trajet
     * @param mixed $id - ID à valider
     * @return int - ID validé
     * @throws Exception si l'ID est invalide
     */
    public static function validateTripId($id): int
    {
        if (!$id || !is_numeric($id) || (int)$id <= 0) {
            throw new Exception('ID de trajet invalide', 400);
        }
        return (int)$id;
    }

    /**
     * Valide que l'utilisateur a le rôle requis pour créer un trajet (chauffeur)
     * @param int $userId - ID de l'utilisateur
     * @throws Exception si l'utilisateur n'est pas chauffeur
     */
    public static function validateDriverRole(int $userId): void
    {
        $userRepo = \App\Factories\ServiceLocator::getUserRepository();
        $user = $userRepo->findById($userId);
        
        if (!$user || !in_array($user->role, ['chauffeur', 'chauffeur_passager'])) {
            throw new Exception('Seuls les chauffeurs peuvent créer des trajets');
        }
    }

    /**
     * Valide que le véhicule appartient bien à l'utilisateur
     * @param int $userId - ID de l'utilisateur
     * @param int|null $vehicleId - ID du véhicule (null si non fourni)
     * @throws Exception si le véhicule n'appartient pas à l'utilisateur
     */
    public static function validateVehicleOwnership(int $userId, ?int $vehicleId): void
    {
        if (empty($vehicleId)) {
            return; // Pas de véhicule spécifié, pas de validation nécessaire
        }

        $vehicleRepo = \App\Factories\ServiceLocator::getVehicleRepository();
        $vehicles = $vehicleRepo->findByUserId($userId);
        $vehicleIds = array_map(fn($v) => $v->id, $vehicles);
        
        if (!in_array($vehicleId, $vehicleIds)) {
            throw new Exception('Ce véhicule ne vous appartient pas');
        }
    }
    
}
