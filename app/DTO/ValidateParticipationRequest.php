<?php

namespace App\DTO;

use App\Exceptions\ValidationException;

/**
 * DTO pour validation structurelle de validation de participation
 * POST /api/participations/{id}/validate
 */
class ValidateParticipationRequest
{
    /**
     * Valide la présence et le type des champs requis
     * @throws ValidationException
     */
    public static function fromArray(array $data): void
    {
        $errors = [];

        // Note: participation_id vient du pathParam, pas du body JSON
        // Cette DTO est minimaliste car pas de données obligatoires dans le body
        // Le commentaire optionnel sera traité dans le Validator
    }
}
