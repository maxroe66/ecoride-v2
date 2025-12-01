<?php

namespace App\Repositories;

interface ParticipationRepositoryInterface
{
    // Crée une nouvelle participation
    public function create(int $userId, int $covoiturageId, int $nbPlaces): int;

    // Trouver par ID
    public function findById(int $participationId): ?array;

    // TROUVER par utilisateur ET trajet
    public function findByUserAndTrip(int $userId, int $covoiturageId): ?array;

    // Met à jour le statut
    public function updateStatus(int $participationId, string $newStatus): bool;
}
