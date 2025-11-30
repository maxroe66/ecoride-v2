<?php

namespace App\Services;

use App\Repositories\TrajetRepositoryInterface;

/**
 * Service trajets : centralise la logique de recherche et suggestions.
 */
class TripService
{
    // $repo peut être un TrajetRepository ou un stub pour tests
    public function __construct(private TrajetRepositoryInterface $repo)
    {
    }

    public function search(
        string $departure,
        string $arrival,
        string $date,
        ?bool $economique,
        ?float $maxPrice,
        ?int $maxDuration,
        ?int $minRating
    ): array {
        $hasFilters = $economique || $maxPrice || $maxDuration || $minRating;
        if ($hasFilters) {
            return $this->repo->searchTrajetsWithFilters($departure, $arrival, $date, $economique, $maxPrice, $maxDuration, $minRating);
        }
        return $this->repo->searchTrajets($departure, $arrival, $date);
    }

    public function suggestions(
        string $departure,
        string $arrival,
        int $limit,
        ?bool $economique,
        ?float $maxPrice,
        ?int $maxDuration,
        ?int $minRating
    ): array {
        $hasFilters = $economique || $maxPrice || $maxDuration || $minRating;
        if ($hasFilters) {
            return $this->repo->getNextAvailableDatesWithFilters($departure, $arrival, $limit, $economique, $maxPrice, $maxDuration, $minRating);
        }
        return $this->repo->getNextAvailableDates($departure, $arrival, $limit);
    }

    public static function normalize(array $trajet): array
    {
        return [
            'covoiturage_id' => $trajet['covoiturage_id'],
            'date_depart' => $trajet['date_depart'],
            'heure_depart' => $trajet['heure_depart'],
            'lieu_depart' => $trajet['lieu_depart'],
            'heure_arrivee' => $trajet['heure_arrivee'],
            'lieu_arrivee' => $trajet['lieu_arrivee'],
            'nb_places' => $trajet['nb_places'],
            'prix_personne' => $trajet['prix_personne'],
            'est_ecologique' => $trajet['est_ecologique'],
            'conducteur_pseudo' => $trajet['pseudo'],
            'conducteur_id' => $trajet['utilisateur_id']
        ];
    }

    public function detail(int $id): array
    {
        $raw = $this->repo->getTrajetDetail($id);
        if (empty($raw)) {
            return [];
        }

        // Récupérer les préférences du conducteur
        $userRepo = new \App\Repositories\UserRepository(\App\Factories\DatabaseFactory::getConnection());
        $conducteur = $userRepo->findById((int)$raw['utilisateur_id']);
        $preferences = method_exists($conducteur, 'getPreferences') ? $conducteur->getPreferences() : [];

        // Récupérer les avis du conducteur
        $avisRepo = new \App\Repositories\ResilientAvisRepository(
            new \App\Repositories\MongoAvisRepository('mongodb://localhost:27017'),
            new \App\Repositories\MysqlAvisRepository(\App\Factories\DatabaseFactory::getConnection())
        );
        $avisConducteur = $avisRepo->listForRide($id);

        return [
            'covoiturage_id' => (int)$raw['covoiturage_id'],
            'trajet' => [
                'date_depart' => $raw['date_depart'],
                'heure_depart' => $raw['heure_depart'],
                'lieu_depart' => $raw['lieu_depart'],
                'heure_arrivee' => $raw['heure_arrivee'],
                'lieu_arrivee' => $raw['lieu_arrivee'],
                'nb_places' => (int)$raw['nb_places'],
                'prix_personne' => (float)$raw['prix_personne'],
                'est_ecologique' => (bool)(int)$raw['est_ecologique']
            ],
            'conducteur' => [
                'utilisateur_id' => (int)$raw['utilisateur_id'],
                'pseudo' => $raw['pseudo']
            ],
            'vehicule' => [
                'modele' => $raw['modele'],
                'marque' => $raw['marque'],
                'energie' => $raw['energie']
            ],
            'rating' => [
                'average' => (float)$raw['avg_rating'],
                'count' => (int)$raw['reviews_count']
            ],
            'preferences_conducteur' => $preferences,
            'avis_conducteur' => array_map(fn($avis) => [
                'auteur' => $avis->userId, // ou pseudo si dispo
                'note' => $avis->rating,
                'commentaire' => $avis->comment,
                'date' => $avis->createdAt->format('Y-m-d')
            ], $avisConducteur)
        ];
    }

    public function detail(int $id): array
    {
        // ...existing code...
    }
}
