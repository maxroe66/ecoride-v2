<?php

namespace App\Validators;

use App\Exceptions\ValidationException;

/**
 * Validateur pour les données de véhicule
 */
class VehicleValidator
{
    /**
     * Valide les données de création d'un véhicule
     * 
     * @param array $data Données du véhicule à valider
     * @return array Données validées
     * @throws ValidationException Si les données sont invalides
     */
    public static function validateVehicleCreation(array $data): array
    {
        $required = ['modele', 'marque', 'couleur', 'date_premiere_immatriculation', 'nb_places', 'energie', 'immatriculation'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Le champ '$field' est requis");
            }
        }

        // Validation du nombre de places
        if (!is_numeric($data['nb_places']) || (int)$data['nb_places'] < 1 || (int)$data['nb_places'] > 9) {
            throw new ValidationException("Le nombre de places doit être entre 1 et 9");
        }

        // Validation de l'énergie
        $energiesValides = ['essence', 'diesel', 'electrique', 'hybride', 'gpl'];
        if (!in_array(strtolower($data['energie']), $energiesValides, true)) {
            throw new ValidationException("Type d'énergie invalide");
        }

        return [
            'modele' => trim((string)$data['modele']),
            'marque' => trim((string)$data['marque']),
            'couleur' => trim((string)$data['couleur']),
            'date_premiere_immatriculation' => $data['date_premiere_immatriculation'],
            'nb_places' => (int)$data['nb_places'],
            'energie' => strtolower($data['energie']),
            'immatriculation' => trim((string)$data['immatriculation'])
        ];
    }

    /**
     * Valide un ID de véhicule
     * 
     * @param mixed $vehicleId ID à valider
     * @return int ID validé
     * @throws ValidationException Si l'ID est invalide
     */
    public static function validateVehicleId($vehicleId): int
    {
        if (!is_numeric($vehicleId) || (int)$vehicleId <= 0) {
            throw new ValidationException("Paramètre id manquant ou invalide");
        }

        return (int)$vehicleId;
    }
}
