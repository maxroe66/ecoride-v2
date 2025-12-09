<?php

namespace App\DTO;

use App\Exceptions\ValidationException;

/**
 * DTO pour validation structurelle de signalement d'un problème de participation
 * POST /api/participations/{id}/problem
 */
class ReportProblemRequest
{
    /**
     * Valide la présence et le type des champs requis
     * @throws ValidationException
     */
    public static function fromArray(array $data): void
    {
        $errors = [];

        // Vérifier présence du champ 'reason'
        if (!isset($data['reason']) || trim($data['reason'] ?? '') === '') {
            $errors['reason'] = 'La raison du problème est requise';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
