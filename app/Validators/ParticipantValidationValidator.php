<?php

namespace App\Validators;

use App\Exceptions\ValidationException;
use App\Repositories\ParticipationRepository;
use App\Repositories\TrajetRepository;

/**
 * Validator pour les règles métier de validation de participation par un passager
 */
class ParticipantValidationValidator
{
    /**
     * Valide les conditions de validation d'une participation
     * @throws ValidationException
     */
    public static function validate(int $participationId, int $userId): void
    {
        $errors = [];

        // 1. Vérifier que la participation existe
        $participationRepo = new ParticipationRepository();
        $participation = $participationRepo->findById($participationId);
        
        if (empty($participation)) {
            $errors['participation_id'] = 'La participation n\'existe pas';
        } else {
            // 2. Vérifier que l'utilisateur est bien le participant
            if ((int)$participation['utilisateur_id'] !== $userId) {
                $errors['utilisateur'] = 'Vous n\'êtes pas participant à ce covoiturage';
            }

            // 3. Vérifier que le statut de la participation est 'confirmee'
            if ($participation['statut'] !== 'confirmee') {
                $errors['statut'] = 'La participation doit être confirmée pour être validée. Statut actuel: ' . $participation['statut'];
            }

            // 4. Vérifier que le trajet est terminé
            $trajetRepo = new TrajetRepository();
            $trajet = $trajetRepo->getTrajetDetail($participation['covoiturage_id']);
            
            if (empty($trajet)) {
                $errors['trajet'] = 'Le trajet n\'existe pas';
            } elseif ($trajet['statut'] !== 'termine') {
                $errors['trajet_statut'] = 'Le trajet doit être terminé pour valider la participation. Statut actuel: ' . $trajet['statut'];
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
