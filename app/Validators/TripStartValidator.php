<?php

namespace App\Validators;

use App\Exceptions\ValidationException;
use App\Repositories\TrajetRepository;

/**
 * Validator pour les règles métier de démarrage d'un trajet
 */
class TripStartValidator
{
    /**
     * Valide les conditions de démarrage du trajet
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

            // 3. Vérifier que le statut est 'planifie'
            if ($trajet['statut'] !== 'planifie') {
                $errors['statut'] = 'Le trajet ne peut être démarré que s\'il est planifié. Statut actuel: ' . $trajet['statut'];
            }

            // 4. Vérifier que la date/heure du trajet est arrivée ou proche
            $tripDateTime = $trajet['date_depart'] . ' ' . $trajet['heure_depart'];
            $tripTime = strtotime($tripDateTime);
            $now = time();
            
            // Autoriser démarrage 15 minutes avant
            if ($tripTime > $now + (15 * 60)) {
                $errors['datetime'] = 'Le trajet ne peut être démarré que 15 minutes avant l\'heure prévue';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
