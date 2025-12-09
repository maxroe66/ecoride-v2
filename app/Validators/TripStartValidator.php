<?php

namespace App\Validators;

use App\Exceptions\ValidationException;
use App\Repositories\TrajetRepository;
use App\Factories\DatabaseFactory;

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
        $db = DatabaseFactory::getConnection();
        $repo = new TrajetRepository($db);
        $trajet = $repo->getTrajetDetail($trajetId);
        
        if (empty($trajet)) {
            $errors['trajet_id'] = 'Le trajet n\'existe pas';
        } else {
            // 2. Vérifier que l'utilisateur est le chauffeur
            if ((int)$trajet['utilisateur_id'] !== $userId) {
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
            
            // Autoriser démarrage jusqu'à 2 heures après l'heure prévue (retards acceptés)
            if ($tripTime < $now - (2 * 3600)) {
                $errors['datetime'] = 'Le trajet est trop ancien pour être démarré (plus de 2 heures après l\'heure prévue)';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
