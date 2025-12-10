<?php

namespace App\Repositories;

use App\Models\Avis;

class ResilientAvisRepository implements AvisRepositoryInterface
{
    private bool $mongoDown = false;
    private int $lastFailureTs = 0;
    private int $cooldownSeconds = 30;

    public function __construct(
        private MongoAvisRepository $mongo,
        private MysqlAvisRepository $mysql
    ) {
    }

    private function mongoAvailable(): bool
    {
        if ($this->mongoDown && (time() - $this->lastFailureTs) < $this->cooldownSeconds) {
            return false;
        }
        return true;
    }

    private function markMongoFailure(): void
    {
        $this->mongoDown = true;
        $this->lastFailureTs = time();
    }

    public function add(Avis $avis): bool
    {
        if ($this->mongoAvailable()) {
            try {
                return $this->mongo->add($avis);
            } catch (\Throwable) {
                $this->markMongoFailure();
            }
        }
        return $this->mysql->add($avis);
    }

    public function listForRide(int $rideId): array
    {
        if ($this->mongoAvailable()) {
            try {
                return $this->mongo->listForRide($rideId);
            } catch (\Throwable) {
                $this->markMongoFailure();
            }
        }
        return $this->mysql->listForRide($rideId);
    }

    public function averageForRide(int $rideId): float
    {
        if ($this->mongoAvailable()) {
            try {
                return $this->mongo->averageForRide($rideId);
            } catch (\Throwable) {
                $this->markMongoFailure();
            }
        }
        return $this->mysql->averageForRide($rideId);
    }

    /**
     * Récupère les avis en attente de modération (MongoDB prioritaire, fallback MySQL)
     */
    public function findPendingReviews(): array
    {
        if ($this->mongoAvailable()) {
            try {
                return $this->mongo->findPendingReviews();
            } catch (\Throwable) {
                $this->markMongoFailure();
            }
        }
        return $this->mysql->findPendingReviews();
    }

    /**
     * Modère un avis (MongoDB prioritaire, fallback MySQL)
     */
    public function moderateReview(string $avisId, string $action, int $employeId): bool
    {
        if ($this->mongoAvailable()) {
            try {
                return $this->mongo->moderateReview($avisId, $action, $employeId);
            } catch (\Throwable) {
                $this->markMongoFailure();
            }
        }
        // Pour MySQL, utiliser l'ID comme int
        return $this->mysql->moderateReview((int)$avisId, $action, $employeId);
    }
}
