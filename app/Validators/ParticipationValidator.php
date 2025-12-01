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
}