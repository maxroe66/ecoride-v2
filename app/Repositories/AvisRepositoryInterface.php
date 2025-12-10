<?php

namespace App\Repositories;

use App\Models\Avis;

interface AvisRepositoryInterface
{
    public function add(Avis $avis): bool;
    /** @return Avis[] */
    public function listForRide(int $rideId): array;
    public function averageForRide(int $rideId): float;
    /** @return array Liste des avis en attente de modération */
    public function findPendingReviews(): array;
    /**
     * Modère un avis
     * @param string $avisId ID de l'avis (MongoDB _id ou MySQL avis_id)
     * @param string $action 'approuve' ou 'refuse'
     * @param int $employeId ID de l'employé qui effectue la modération
     * @return bool true si succès
     */
    public function moderateReview(string $avisId, string $action, int $employeId): bool;
}
