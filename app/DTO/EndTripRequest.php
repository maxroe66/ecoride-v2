<?php

namespace App\DTO;

use App\Exceptions\ValidationException;

/**
 * DTO pour validation structurelle d'arrêt d'un trajet
 * PUT /api/trajets/{id}/end
 */
class EndTripRequest
{
    /**
     * Valide la présence et le type des champs requis
     * @throws ValidationException
     */
    public static function fromArray(array $data): void
    {
        $errors = [];

        // Note: trajet_id vient du pathParam, pas du body JSON
        // Cette DTO est minimaliste car pas de données dans le body
    }
}
