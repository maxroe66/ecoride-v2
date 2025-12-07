<?php

namespace App\Validators;

use App\Exceptions\ValidationException;

class QueryValidator
{
    /**
     * Valide les paramètres de recherche de trajets
     * @return array ['departure' => string, 'arrival' => string, 'date' => string]
     * @throws \Exception
     */
    public static function validateTrajetSearch(array $query): array
    {
        $departure = trim((string)($query['departure'] ?? ''));
        $arrival = trim((string)($query['arrival'] ?? ''));
        $date = trim((string)($query['date'] ?? ''));

        if (!$departure || !$arrival || !$date) {
            throw new \Exception('Paramètres departure, arrival et date requis', 400);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \Exception('Format de date invalide (YYYY-MM-DD)', 400);
        }

        return compact('departure', 'arrival', 'date');
    }

    /**
     * Valide les paramètres de suggestions de dates
     * @return array ['departure' => string, 'arrival' => string]
     * @throws \Exception
     */
    public static function validateDateSuggestions(array $query): array
    {
        $departure = trim((string)($query['departure'] ?? ''));
        $arrival = trim((string)($query['arrival'] ?? ''));

        if (!$departure || !$arrival) {
            throw new \Exception('Paramètres departure et arrival requis', 400);
        }

        return compact('departure', 'arrival');
    }

    /**
     * Valide et extrait les filtres optionnels
     * @return array ['economique' => ?bool, 'maxPrice' => ?float, 'maxDuration' => ?int, 'minRating' => ?int]
     */
    public static function extractFilters(array $query): array
    {
        $economique = isset($query['economique']) && $query['economique'] === '1' ? true : null;

        $maxPrice = null;
        if (isset($query['maxPrice'])) {
            $price = (float)$query['maxPrice'];
            if ($price > 0 && $price <= 10000) {
                $maxPrice = $price;
            }
        }

        $maxDuration = null;
        if (isset($query['maxDuration'])) {
            $duration = (int)$query['maxDuration'];
            if ($duration > 0 && $duration <= 1440) {
                $maxDuration = $duration;
            }
        }

        $minRating = null;
        if (isset($query['minRating'])) {
            $rating = (int)$query['minRating'];
            if ($rating >= 1 && $rating <= 5) {
                $minRating = $rating;
            }
        }

        return compact('economique', 'maxPrice', 'maxDuration', 'minRating');
    }

    /**
     * Valide l'ID d'un covoiturage
     * @throws \Exception
     */
    public static function validateRideId(array $query): int
    {
        $rideId = isset($query['covoiturage_id']) ? (int)$query['covoiturage_id'] : 0;
        if ($rideId <= 0) {
            throw new \Exception('Paramètre covoiturage_id requis et valide', 400);
        }
        return $rideId;
    }
}
