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

    /**
     * Valide les données d'un avis (note et commentaire)
     * @return array ['rating' => int, 'comment' => ?string]
     * @throws \Exception
     */
    public static function validateAvis(array $json): array
    {
        $rating = (int)($json['note'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            throw new \Exception('Note doit être entre 1 et 5', 422);
        }

        $comment = isset($json['commentaire']) ? trim((string)$json['commentaire']) : null;

        return [
            'rating' => $rating,
            'comment' => $comment
        ];
    }

    /**
     * Valide les données d'authentification (signup)
     * @return array ['pseudo' => string, 'email' => string, 'password' => string]
     * @throws \Exception
     */
    public static function validateSignup(array $json): array
    {
        $pseudo = trim((string)($json['pseudo'] ?? ''));
        $email = trim((string)($json['email'] ?? ''));
        $password = $json['password'] ?? '';

        if (!$pseudo || !$email || !$password) {
            throw new \Exception('Pseudo, email et mot de passe requis', 400);
        }

        return compact('pseudo', 'email', 'password');
    }

    /**
     * Valide les données d'authentification (login)
     * @return array ['emailOrPseudo' => string, 'password' => string]
     * @throws \Exception
     */
    public static function validateLogin(array $json): array
    {
        $emailOrPseudo = trim((string)($json['email'] ?? ''));
        $password = $json['password'] ?? '';

        if (!$emailOrPseudo || !$password) {
            throw new \Exception('Email/Pseudo et mot de passe requis', 400);
        }

        return [
            'emailOrPseudo' => $emailOrPseudo,
            'password' => $password
        ];
    }

    /**
     * Valide la création d'un avis (complète côté données avis).
     * L'identité utilisateur n'est plus contrôlée ici car elle provient désormais
     * exclusivement du JWT (AuthMiddleware) et non du corps de la requête.
     *
     * @return array ['rideId' => int, 'rating' => int, 'comment' => ?string]
     * @throws ValidationException
     */
    public static function validateAvisCreation(array $json): array
    {
        $rideId = (int)($json['covoiturage_id'] ?? 0);

        $errors = [];
        if ($rideId <= 0) {
            $errors[] = 'covoiturage_id invalide';
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        $avisData = self::validateAvis($json);

        return [
            'rideId' => $rideId,
            'rating' => $avisData['rating'],
            'comment' => $avisData['comment']
        ];
    }

    /**
     * Valide que le corps de la requête est un JSON valide
     * @param mixed $json - données à valider
     * @return array - JSON validé
     * @throws \Exception si le JSON est invalide
     */
    public static function validateJsonInput($json): array
    {
        if (!is_array($json)) {
            throw new \Exception('Corps JSON invalide', 400);
        }
        return $json;
    }
}
