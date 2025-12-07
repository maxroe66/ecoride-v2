<?php

namespace App\Validators;

use App\DTO\CreateAvisRequest;
use App\Exceptions\ValidationException;

/**
 * Validateur pour les règles métier des avis.
 * Applique les règles business complexes sur le DTO.
 */
class AvisValidator
{
    /**
     * Valide les règles métier pour la création d'un avis
     * 
     * @param CreateAvisRequest $dto DTO à valider
     * @return void
     * @throws ValidationException si validation échoue
     */
    public static function validate(CreateAvisRequest $dto): void
    {
        $errors = [];

        // Règle métier 1 : Note doit être entre 1 et 5
        if ($dto->note < 1 || $dto->note > 5) {
            $errors[] = 'La note doit être comprise entre 1 et 5';
        }

        // Règle métier 2 : Commentaire ne doit pas dépasser 1000 caractères
        if ($dto->commentaire !== null && strlen($dto->commentaire) > 1000) {
            $errors[] = 'Le commentaire ne peut pas dépasser 1000 caractères';
        }

        // Règle métier 3 : Commentaire ne doit pas contenir que des espaces
        if ($dto->commentaire !== null && trim($dto->commentaire) === '') {
            $errors[] = 'Le commentaire ne peut pas être vide';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
