<?php

namespace App\Validators;

use Exception;

class CancellationValidator
{
    /**
     * Valide les paramètres pour annuler un trajet en tant que chauffeur
     * @param int $tripId - ID du trajet
     * @param int $userId - ID de l'utilisateur authentifié
     * @param ?string $reason - Raison optionnelle d'annulation
     * @return array - données validées
     * @throws Exception si validation échoue
     */
    public static function validateTripCancellation(int $tripId, int $userId, ?string $reason = null): array
    {
        // Valider tripId
        if ($tripId <= 0) {
            throw new Exception('ID du trajet invalide');
        }

        // Valider userId
        if ($userId <= 0) {
            throw new Exception('ID de l\'utilisateur invalide');
        }

        // Valider la raison si fournie
        if ($reason !== null) {
            if (!is_string($reason) || strlen(trim($reason)) === 0) {
                throw new Exception('La raison doit être une chaîne non-vide');
            }
            // Limiter la longueur à 500 caractères
            if (strlen($reason) > 500) {
                throw new Exception('La raison ne doit pas dépasser 500 caractères');
            }
            $reason = trim($reason);
        }

        return [
            'trip_id' => $tripId,
            'user_id' => $userId,
            'reason' => $reason
        ];
    }

    /**
     * Valide les paramètres pour annuler une participation en tant que passager
     * @param int $participationId - ID de la participation
     * @param int $userId - ID de l'utilisateur authentifié
     * @return array - données validées
     * @throws Exception si validation échoue
     */
    public static function validateParticipationCancellation(int $participationId, int $userId): array
    {
        // Valider participationId
        if ($participationId <= 0) {
            throw new Exception('ID de participation invalide');
        }

        // Valider userId
        if ($userId <= 0) {
            throw new Exception('ID de l\'utilisateur invalide');
        }

        return [
            'participation_id' => $participationId,
            'user_id' => $userId
        ];
    }

    /**
     * Valide les paramètres de filtrage par statut
     * @param string $status - Statut à valider
     * @return string - statut validé
     * @throws Exception si validation échoue
     */
    public static function validateStatusFilter(string $status): string
    {
        $validStatuses = ['planifie', 'en_cours', 'termine', 'annule'];

        if (!in_array($status, $validStatuses)) {
            throw new Exception('Statut invalide. Statuts acceptés: ' . implode(', ', $validStatuses));
        }

        return $status;
    }
}
