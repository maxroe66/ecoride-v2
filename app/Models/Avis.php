<?php

namespace App\Models;

class Avis
{
    public function __construct(
        public readonly int $rideId,          // covoiturage_id
        public readonly int $userId,          // utilisateur_id
        public readonly int $rating,          // note (1..5)
        public readonly ?string $comment,     // commentaire
        public readonly \DateTimeImmutable $createdAt = new \DateTimeImmutable()
    ) {
    }
}
