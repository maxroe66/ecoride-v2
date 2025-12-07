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

    /**
     * Convertit l'avis en tableau pour la réponse API
     * Format attendu par le frontend
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'covoiturage_id' => $this->rideId,
            'utilisateur_id' => $this->userId,
            'note' => $this->rating,
            'commentaire' => $this->comment,
            'date_creation' => $this->createdAt->format('Y-m-d H:i:s')
        ];
    }
}
