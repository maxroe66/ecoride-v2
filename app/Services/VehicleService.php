<?php

namespace App\Services;

use App\Factories\ServiceLocator as SL;
use App\Models\Vehicules;
use Exception;

/**
 * Service de gestion des véhicules
 */
class VehicleService
{
    /**
     * Crée un nouveau véhicule pour un utilisateur
     * 
     * @param int $userId ID de l'utilisateur propriétaire
     * @param array $validatedData Données validées du véhicule
     * @return array Informations du véhicule créé
     * @throws Exception Si la création échoue
     */
    public function createVehicle(int $userId, array $validatedData): array
    {
        $vehicleRepo = SL::getVehicleRepository();
        $marqueRepo = SL::getMarqueRepository();

        // Résoudre la marque à partir du libellé
        $marqueId = $marqueRepo->findOrCreateByName($validatedData['marque']);

        // Créer le véhicule
        $vehicle = new Vehicules(
            $validatedData['modele'],
            $marqueId,
            $validatedData['immatriculation'],
            $validatedData['energie'],
            $validatedData['nb_places'],
            $userId,
            $validatedData['couleur'],
            $validatedData['date_premiere_immatriculation'],
            $validatedData['energie'] === 'electrique' ? true : false
        );

        $vehicleId = $vehicleRepo->create($vehicle);

        return [
            'id' => $vehicleId,
            'modele' => $validatedData['modele'],
            'marque' => $validatedData['marque'],
            'couleur' => $validatedData['couleur'],
            'immatriculation' => $validatedData['immatriculation']
        ];
    }
}
