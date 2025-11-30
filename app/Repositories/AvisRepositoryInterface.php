<?php

namespace App\Repositories;

use App\Models\Avis;

interface AvisRepositoryInterface
{
    public function add(Avis $avis): bool;
    /** @return Avis[] */
    public function listForRide(int $rideId): array;
    public function averageForRide(int $rideId): float;
}
