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

    public function detail(int $id, ?int $currentUserId = null): array
    {
        $raw = $this->repo->getTrajetDetail($id);
        if (empty($raw)) {
            return [];
        }

        // Récupérer les préférences du conducteur
        $userRepo = new \App\Repositories\UserRepository(\App\Factories\DatabaseFactory::getConnection());
        $conducteur = $userRepo->findById((int)$raw['utilisateur_id']);
        $preferences = method_exists($conducteur, 'getPreferences') ? $conducteur->getPreferences() : [];

        // Récupérer les avis du conducteur (tous ses trajets, pas juste celui-ci)
        $avisRepo = new \App\Repositories\ResilientAvisRepository(
            new \App\Repositories\MongoAvisRepository('mongodb://mongo:27017'),
            new \App\Repositories\MysqlAvisRepository(\App\Factories\DatabaseFactory::getConnection())
        );
        
        // Récupérer TOUS les trajets du conducteur pour obtenir leurs avis
        $trajetRepo = new \App\Repositories\TrajetRepository(\App\Factories\DatabaseFactory::getConnection());
        $allTrajetsConductor = $trajetRepo->getTrajetsByUserId((int)$raw['utilisateur_id']);
        
        // Collecter tous les avis de tous ses trajets
        $avisConducteur = [];
        foreach ($allTrajetsConductor as $t) {
            $avis = $avisRepo->listForRide((int)$t['covoiturage_id']);
            $avisConducteur = array_merge($avisConducteur, $avis);
        }
        
        // Limiter à 10 avis les plus récents
        usort($avisConducteur, function($a, $b) {
            return $b->createdAt->getTimestamp() - $a->createdAt->getTimestamp();
        });
        $avisConducteur = array_slice($avisConducteur, 0, 10);

        // Vérifier si l'utilisateur actuel a déjà participé à ce trajet
        $userAlreadyParticipated = false;
        
        error_log("[TripService::detail] Debug: currentUserId=$currentUserId, conducteur_id=".$raw['utilisateur_id'].", tripId=".$raw['covoiturage_id']);
        
        if ($currentUserId && $currentUserId !== (int)$raw['utilisateur_id']) {
            // Vérifier dans la table participation
            $participationRepo = new \App\Repositories\ParticipationRepository(\App\Factories\DatabaseFactory::getConnection());
            $userParticipation = $participationRepo->findByUserAndTrip($currentUserId, (int)$raw['covoiturage_id']);
            $userAlreadyParticipated = !empty($userParticipation);
            
            // Debug log
            error_log("[TripService::detail] Participation check: participated=$userAlreadyParticipated, result=".json_encode($userParticipation));
        } else {
            error_log("[TripService::detail] Skipped participation check: currentUserId is null or same as conductor");
        }

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
                'est_ecologique' => (bool)(int)$raw['est_ecologique'],
                'statut' => $raw['statut']
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
                'average' => count($avisConducteur) > 0 
                    ? round(array_sum(array_map(fn($avis) => $avis->rating, $avisConducteur)) / count($avisConducteur), 1)
                    : 0,
                'count' => count($avisConducteur)
            ],
            'preferences_conducteur' => $preferences,
            'avis_conducteur' => array_map(fn($avis) => [
                'auteur' => $avis->userId, // ou pseudo si dispo
                'note' => $avis->rating,
                'commentaire' => $avis->comment,
                'date' => $avis->createdAt->format('Y-m-d')
            ], $avisConducteur),
            'user_already_participated' => $userAlreadyParticipated
        ];
    }

    /**
     * Crée un nouveau trajet
     * Note: La validation est déjà faite dans le Controller via DTO + TripValidator
     * 
     * @param array $data - Données validées du formulaire
     * @param int $conducteurId - ID du chauffeur
     * @return array - Le trajet créé avec son ID
     * @throws \Exception si la création échoue
     */
    public function createTrip(array $data, int $conducteurId): array
    {
        $vehicleRepo = \App\Factories\ServiceLocator::getVehicleRepository();
        $isEco = false;
        foreach ($vehicleRepo->findByUserId($conducteurId) as $vehicle) {
            if (($vehicle->id ?? null) === (int)$data['voiture_id']) {
                $isEco = (bool)$vehicle->est_ecologique;
                break;
            }
        }

        // Créer l'objet Trajet avec les données validées
        $trajet = new \App\Models\Trajet(
            $data['date_depart'],
            $data['heure_depart'],
            $data['lieu_depart'],
            $data['lieu_arrivee'],
            (int)$data['nb_places'],
            (float)$data['prix_personne'],
            $conducteurId,
            (int)$data['voiture_id'],
            'planifie',
            $isEco,
            $data['heure_arrivee'] ?? null
        );
        
        // Persister en BD
        $trajetId = $this->repo->createTrajet($trajet);
        
        // Retourner le trajet avec son ID
        $trajet->id = $trajetId;
        return $trajet->toArray();
    }

    /**
     * Filtre les trajets pour ne garder que ceux à venir
     * Exclut les trajets annulés et passés
     * 
     * @param array $trajets Liste des trajets
     * @return array Trajets à venir réindexés
     */
    public function filterUpcoming(array $trajets): array
    {
        $now = new \DateTime('now');
        
        $filtered = array_filter($trajets, function (array $t) use ($now) {
            // Exclure les trajets annulés
            if (($t['statut'] ?? null) === 'annule') {
                return false;
            }

            $date = $t['date_depart'] ?? null;
            if (!$date) {
                return true; // Garder si pas de date (sécurité)
            }

            try {
                $time = $t['heure_depart'] ?? '00:00:00';
                $dt = new \DateTime($date . ' ' . $time);
                return $dt >= $now;
            } catch (\Throwable $e) {
                return true; // En cas d'erreur, garder le trajet par sécurité
            }
        });
        
        return array_values($filtered);
    }

}
