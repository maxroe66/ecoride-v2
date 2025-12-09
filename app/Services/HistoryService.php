<?php

namespace App\Services;

use App\Repositories\TrajetRepository;
use App\Repositories\ParticipationRepository;

class HistoryService
{
    private TrajetRepository $trajetRepo;
    private ParticipationRepository $participationRepo;

    public function __construct(
        TrajetRepository $trajetRepo,
        ParticipationRepository $participationRepo
    ) {
        $this->trajetRepo = $trajetRepo;
        $this->participationRepo = $participationRepo;
    }

    public function getUserTripHistory(int $userId): array
    {
        // Trajets en tant que chauffeur (déjà enrichis en tableau associatif)
        $trips = $this->trajetRepo->getTrajetsByUserId($userId);
        $trips = array_map(fn($trip) => $this->normalizeTrip($trip, 'chauffeur'), $trips);

        // Participations en tant que passager (on ne garde que les confirmées dans l'historique principal)
        // Note: même annulées, les participations restent dans l'historique mais marquées 'annulee'
        $participations = $this->participationRepo->findByUserAndStatus($userId, 'confirmee');
        $participations = array_map(fn($p) => $this->normalizeParticipation($p), $participations);
        
        // Fusionner les 2 listes
        $history = array_merge($trips, $participations);
        // Trier par date_depart en descendant (plus récent d'abord)
        usort($history, fn($a, $b) => strtotime($b['date_depart'] ?? '0') - strtotime($a['date_depart'] ?? '0'));
        return $history;
    }

    /**
     * Normalise un trajet (en tant que chauffeur) pour la réponse API
     */
    private function normalizeTrip(array $trip, string $role): array
    {
        return [
            'trajet_id' => (int)($trip['covoiturage_id'] ?? $trip['id'] ?? 0),
            'lieu_depart' => $trip['lieu_depart'] ?? null,
            'lieu_arrivee' => $trip['lieu_arrivee'] ?? null,
            'date_depart' => $trip['date_depart'] ?? null,
            'heure_depart' => $trip['heure_depart'] ?? null,
            'role' => $role,  // 'chauffeur' ou 'passager'
            'statut' => $trip['statut'] ?? null,  // 'planifie', 'en_cours', 'termine', 'annule'
            'prix_personne' => (float)($trip['prix_personne'] ?? 0),
            'nb_places' => (int)($trip['nb_places'] ?? 0),
            'utilisateur_id' => (int)($trip['utilisateur_id'] ?? 0),
            'marque' => $trip['marque'] ?? null,  // ✅ Marque du véhicule
            'modele' => $trip['modele'] ?? null,  // ✅ Modèle du véhicule
        ];
    }

    /**
     * Normalise une participation (en tant que passager) pour la réponse API
     */
    private function normalizeParticipation(array $participation): array
    {
        return [
            'trajet_id' => (int)($participation['covoiturage_id'] ?? 0),
            'lieu_depart' => $participation['lieu_depart'] ?? null,
            'lieu_arrivee' => $participation['lieu_arrivee'] ?? null,
            'date_depart' => $participation['date_depart'] ?? null,
            'heure_depart' => $participation['heure_depart'] ?? null,
            'role' => 'passager',  // Toujours passager pour les participations
            'statut' => $participation['statut'] ?? null,  // 'demandee', 'confirmee', 'annulee', etc.
            'prix_personne' => (float)($participation['prix_personne'] ?? 0),
            'nb_places' => (int)($participation['nb_places'] ?? 0),
            'utilisateur_id' => (int)($participation['utilisateur_id'] ?? 0),
            'participation_id' => (int)($participation['participation_id'] ?? 0),
        ];
    }

    /**
     * Récupère l'historique filtré par statut
     * Inclut maintenant aussi les participations annulées pour l'historique complet
     * 
     * @param int $userId ID de l'utilisateur
     * @param string $status Statut à filtrer (planifie, en_cours, termine, annule)
     * @return array Historique filtré
     */
    public function getUserTripHistoryByStatus(int $userId, string $status): array
    {
        // Trajets en tant que chauffeur
        $trips = $this->trajetRepo->getTrajetsByUserId($userId);
        $trips = array_map(fn($trip) => $this->normalizeTrip($trip, 'chauffeur'), $trips);

        // Participations en tant que passager - pour ce filtre, on récupère TOUS les statuts
        // pour permettre au user de voir aussi ses participations annulées
        $participations = $this->participationRepo->findByUserId($userId);
        $participations = array_map(fn($p) => $this->normalizeParticipation($p), $participations);
        
        // Fusionner
        $history = array_merge($trips, $participations);
        
        // Filtrer par statut
        $filtered = array_filter($history, function($item) use ($status) {
            return ($item['statut'] ?? null) === $status;
        });

        // Réindexer le tableau (pour avoir des indices 0, 1, 2... au lieu de 0, 3, 7...)
        return array_values($filtered);
    }
}