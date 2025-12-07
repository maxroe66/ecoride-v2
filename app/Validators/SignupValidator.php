<?php

namespace App\Validators;

use App\DTO\SignupRequest;
use App\Exceptions\ValidationException;

/**
 * Validateur pour les règles métier d'inscription.
 * Applique les règles business complexes sur le DTO.
 */
class SignupValidator
{
    /**
     * Valide les règles métier pour l'inscription
     * 
     * @param SignupRequest $dto DTO à valider
     * @return void
     * @throws ValidationException si validation échoue
     */
    public static function validate(SignupRequest $dto): void
    {
        $errors = [];

        // Règle métier 1 : Pseudo doit faire au moins 3 caractères
        if (strlen($dto->pseudo) < 3) {
            $errors[] = 'Le pseudo doit contenir au moins 3 caractères';
        }

        // Règle métier 2 : Pseudo ne doit pas dépasser 50 caractères
        if (strlen($dto->pseudo) > 50) {
            $errors[] = 'Le pseudo ne peut pas dépasser 50 caractères';
        }

        // Règle métier 3 : Email doit être valide
        if (!filter_var($dto->email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format d\'email invalide';
        }

        // Règle métier 4 : Mot de passe doit faire au moins 8 caractères
        if (strlen($dto->password) < 8) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caractères';
        }

        // Règle métier 5 : Mot de passe doit contenir au moins une lettre et un chiffre
        if (!preg_match('/[a-zA-Z]/', $dto->password) || !preg_match('/[0-9]/', $dto->password)) {
            $errors[] = 'Le mot de passe doit contenir au moins une lettre et un chiffre';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
