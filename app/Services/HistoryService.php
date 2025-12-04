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
        $trips = $this->trajetRepo->getTrajetsByUserId($userId);
        $participations = $this->participationRepo->findByUserAndStatus($userId, 'confirmee');
        $trips = array_map(fn($trip) => array_merge($trip->toArray(), ['role' => 'chauffeur']), $trips);
        $participations = array_map(fn($p) => array_merge($p, ['role' => 'passager']), $participations);
        // Fusionner les 2 listes
        $history = array_merge($trips, $participations);
        // Trier par date_depart en descendant (plus récent d'abord)
        usort($history, fn($a, $b) => strtotime($b['date_depart']) - strtotime($a['date_depart']));
        return $history;
    }
}