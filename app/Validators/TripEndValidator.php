<?php

namespace App\Validators;

use App\Exceptions\ValidationException;
use App\Repositories\TrajetRepository;

/**
 * Validator pour les règles métier d'arrêt d'un trajet
 */
class TripEndValidator
{
    /**
     * Valide les conditions d'arrêt du trajet
     * @throws ValidationException
     */
    public static function validate(int $trajetId, int $userId): void
    {
        $errors = [];

        // 1. Vérifier que le trajet existe
        $repo = new TrajetRepository();
        $trajet = $repo->getTrajetDetail($trajetId);
        
        if (empty($trajet)) {
            $errors['trajet_id'] = 'Le trajet n\'existe pas';
        } else {
            // 2. Vérifier que l'utilisateur est le chauffeur
            if ((int)$trajet['conducteur_id'] !== $userId) {
                $errors['conducteur'] = 'Vous n\'êtes pas le chauffeur de ce trajet';
            }

            // 3. Vérifier que le statut est 'en_cours'
            if ($trajet['statut'] !== 'en_cours') {
                $errors['statut'] = 'Le trajet doit être en cours pour être arrêté. Statut actuel: ' . $trajet['statut'];
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
