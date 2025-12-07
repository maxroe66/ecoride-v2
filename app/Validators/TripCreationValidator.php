<?php

namespace App\Validators;

use App\DTO\CreateTripRequest;
use Exception;

/**
 * Validateur pour les règles métier de création de trajet.
 * Applique les règles business complexes sur le DTO.
 */
class TripCreationValidator
{
    /**
     * Valide les règles métier pour la création d'un trajet
     * 
     * @param CreateTripRequest $dto DTO à valider
     * @return void
     * @throws Exception si validation échoue
     */
    public static function validate(CreateTripRequest $dto): void
    {
        // Règle métier 1 : Format de date valide (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dto->dateDepart)) {
            throw new Exception('Format de date invalide (attendu: YYYY-MM-DD)');
        }

        // Règle métier 2 : Date ne peut pas être dans le passé
        if (strtotime($dto->dateDepart) < strtotime('today')) {
            throw new Exception('La date de départ ne peut pas être dans le passé');
        }

        // Règle métier 3 : Format d'heure valide (HH:MM)
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $dto->heureDepart)) {
            throw new Exception('Format d\'heure invalide (attendu: HH:MM)');
        }

        // Règle métier 4 : Nombre de places valide (1-8)
        if ($dto->nbPlaces <= 0) {
            throw new Exception('Le nombre de places doit être supérieur à 0');
        }
        if ($dto->nbPlaces > 8) {
            throw new Exception('Le nombre de places ne peut pas dépasser 8');
        }

        // Règle métier 5 : Prix minimum de 2 crédits (commission plateforme)
        if ($dto->prixPersonne < 2) {
            throw new Exception('Le prix minimum est de 2 crédits (commission plateforme)');
        }

        // Règle métier 6 : Prix maximum de 1000 crédits
        if ($dto->prixPersonne > 1000) {
            throw new Exception('Le prix ne peut pas dépasser 1000 crédits');
        }

        // Règle métier 7 : Durée estimée valide si présente
        if ($dto->dureeEstimee !== null && $dto->dureeEstimee <= 0) {
            throw new Exception('La durée estimée doit être supérieure à 0 minutes');
        }
    }
}
