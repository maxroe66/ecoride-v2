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
        $trips = array_map(fn($trip) => array_merge($trip, [
            'role' => 'chauffeur',
            // Normaliser quelques champs pour le frontend
            'id' => $trip['covoiturage_id'] ?? null,
            'prix' => $trip['prix_personne'] ?? null,
            'nb_places_disponibles' => $trip['nb_places'] ?? null,
        ]), $trips);

        // Participations en tant que passager (on ne garde que les confirmées dans l'historique principal)
        $participations = $this->participationRepo->findByUserAndStatus($userId, 'confirmee');
        $participations = array_map(fn($p) => array_merge($p, [
            'role' => 'passager',
            'statut_participation' => $p['statut'] ?? null,
        ]), $participations);
        // Fusionner les 2 listes
        $history = array_merge($trips, $participations);
        // Trier par date_depart en descendant (plus récent d'abord)
        usort($history, fn($a, $b) => strtotime($b['date_depart']) - strtotime($a['date_depart']));
        return $history;
    }
}