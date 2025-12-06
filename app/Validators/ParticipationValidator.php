<?php

namespace App\Validators;

use Exception;

class ParticipationValidator
{
    /**
     * Valide les données avant de créer une participation
     * @param int $userId - l'utilisateur
     * @param int $covoiturageId - le trajet
     * @param int $nbPlaces - nombre de places demandées
     * @param float|null $userCredit - crédit de l'utilisateur
     * @param float $tripPrice - prix par personne du trajet
     * @param int $tripAvailableSeats - places disponibles du trajet
     * @return array - données validées
     * @throws Exception si validation échoue
     */
    public static function validateParticipation(
        int $userId,
        int $covoiturageId,
        int $nbPlaces,
        ?float $userCredit,
        float $tripPrice,
        int $tripAvailableSeats
    ): array {
        // 1. Vérifier userId
        if ($userId <= 0) {
            throw new Exception('Invalid user ID');
        }

        // 2. Vérifier covoiturageId
        if ($covoiturageId <= 0) {
            throw new Exception('Invalid trip ID');
        }

        // 3. Vérifier nbPlaces
        if ($nbPlaces <= 0) {
            throw new Exception('Number of seats must be positive');
        }

        // 4. Vérifier crédit utilisateur
        if ($userCredit === null || $userCredit < $tripPrice) {
            throw new Exception('Insufficient credit');
        }

        // 5. Vérifier places disponibles
        if ($tripAvailableSeats <= 0) {
            throw new Exception('No available seats');
        }

        // 6. Vérifier si assez de places
        if ($nbPlaces > $tripAvailableSeats) {
            throw new Exception('Not enough seats available');
        }

        // Tout est OK, retourner les données validées
        return [
            'userId' => $userId,
            'covoiturageId' => $covoiturageId,
            'nbPlaces' => $nbPlaces,
            'userCredit' => $userCredit,
            'tripPrice' => $tripPrice,
            'tripAvailableSeats' => $tripAvailableSeats
        ];
    }

    /**
     * Valide les paramètres d'une demande de participation depuis le JSON
     * @param array $json - données JSON décodées
     * @return array ['covoiturage_id' => int, 'nb_places' => int]
     * @throws Exception si validation échoue
     */
    public static function validateParticipationRequest(array $json): array
    {
        $covoiturageId = $json['covoiturage_id'] ?? null;
        $nbPlaces = $json['nb_places'] ?? null;

        if (!is_int($covoiturageId) || $covoiturageId <= 0) {
            throw new Exception('covoiturage_id doit être un entier positif', 400);
        }

        if (!is_int($nbPlaces) || $nbPlaces <= 0) {
            throw new Exception('nb_places doit être un entier positif', 400);
        }

        return [
            'covoiturage_id' => $covoiturageId,
            'nb_places' => $nbPlaces
        ];
    }

    /**
     * Valide un ID de participation
     * @param mixed $participationId - ID à valider
     * @return int - ID validé
     * @throws Exception si l'ID est invalide
     */
    public static function validateParticipationId($participationId): int
    {
        if (!is_int($participationId) || $participationId <= 0) {
            throw new Exception('participation_id doit être un entier positif', 400);
        }
        return $participationId;
    }
}

