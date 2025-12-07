<?php

namespace App\Validators;

use App\DTO\LoginRequest;
use App\Exceptions\ValidationException;

/**
 * Validateur pour les règles métier de connexion.
 * Applique les règles business complexes sur le DTO.
 */
class LoginValidator
{
    /**
     * Valide les règles métier pour la connexion
     * 
     * @param LoginRequest $dto DTO à valider
     * @return void
     * @throws ValidationException si validation échoue
     */
    public static function validate(LoginRequest $dto): void
    {
        $errors = [];

        // Règle métier 1 : Email/Pseudo ne peut pas être vide
        if (strlen($dto->emailOrPseudo) === 0) {
            $errors[] = 'Email ou pseudo requis';
        }

        // Règle métier 2 : Si c'est un email, il doit être valide
        if (filter_var($dto->emailOrPseudo, FILTER_VALIDATE_EMAIL) === false) {
            // Si ce n'est pas un email valide, vérifier que c'est un pseudo valide
            if (strlen($dto->emailOrPseudo) < 3) {
                $errors[] = 'Email invalide ou pseudo trop court (minimum 3 caractères)';
            }
        }

        // Règle métier 3 : Mot de passe ne peut pas être vide
        if (strlen($dto->password) === 0) {
            $errors[] = 'Mot de passe requis';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
