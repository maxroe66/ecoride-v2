<?php

namespace App\DTO;

use App\Exceptions\ValidationException;

/**
 * DTO pour la création d'un employé (validation structurelle)
 */
class CreateEmployeeRequest
{
    public string $email;
    public string $pseudo;

    /**
     * Valide la structure de la requête
     * @throws ValidationException
     */
    public static function fromArray(array $data): void
    {
        $errors = [];

        // Email requis
        if (empty($data['email'])) {
            $errors['email'] = 'L\'email est requis';
        }

        // Pseudo requis
        if (empty($data['pseudo'])) {
            $errors['pseudo'] = 'Le pseudo est requis';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
