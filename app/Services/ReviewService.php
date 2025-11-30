<?php

namespace App\Services;

use App\Factories\AvisRepositoryFactory;
use App\Models\Avis;

/**
 * Service avis : encapsule logique CRUD et statistiques.
 */
class ReviewService
{
    public function __construct(private $repo = null)
    {
        $this->repo = $repo ?: AvisRepositoryFactory::get();
    }

    public function listForRide(int $rideId): array
    {
        return $this->repo->listForRide($rideId);
    }

    public function averageForRide(int $rideId): float
    {
        return $this->repo->averageForRide($rideId);
    }

    public function countForRide(int $rideId): int
    {
        return count($this->repo->listForRide($rideId));
    }

    public function create(int $rideId, int $userId, int $rating, ?string $comment): bool
    {
        $avis = new Avis($rideId, $userId, $rating, $comment);
        return $this->repo->add($avis);
    }

    public static function normalize(Avis $a): array
    {
        return [
            'covoiturage_id' => $a->rideId,
            'utilisateur_id' => $a->userId,
            'note' => $a->rating,
            'commentaire' => $a->comment,
            'date_creation' => $a->createdAt->format('Y-m-d H:i:s')
        ];
    }
}
