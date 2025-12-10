<?php

namespace App\Validators;

use App\Exceptions\ValidationException;
use App\Factories\DatabaseFactory;

/**
 * Validator pour la création d'employé (règles métier)
 */
class CreateEmployeeValidator
{
    /**
     * Valide les règles métier pour la création d'employé
     * @throws ValidationException
     */
    public static function validate(array $data): void
    {
        $errors = [];
        $db = DatabaseFactory::getConnection();

        // Email valide
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format email invalide';
        } else {
            // Vérifier unicité email
            $stmt = $db->prepare('SELECT utilisateur_id FROM utilisateur WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $data['email']]);
            if ($stmt->fetch(\PDO::FETCH_ASSOC)) {
                $errors['email'] = 'Cet email est déjà utilisé';
            }
        }

        // Pseudo longueur
        if (strlen($data['pseudo']) < 3 || strlen($data['pseudo']) > 30) {
            $errors['pseudo'] = 'Le pseudo doit avoir entre 3 et 30 caractères';
        } else {
            // Vérifier unicité pseudo
            $stmt = $db->prepare('SELECT utilisateur_id FROM utilisateur WHERE pseudo = :pseudo LIMIT 1');
            $stmt->execute([':pseudo' => $data['pseudo']]);
            if ($stmt->fetch(\PDO::FETCH_ASSOC)) {
                $errors['pseudo'] = 'Ce pseudo est déjà utilisé';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
