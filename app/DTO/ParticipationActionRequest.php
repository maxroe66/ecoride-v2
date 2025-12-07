<?php

namespace App\DTO;

/**
 * DTO représentant une action sur une participation (validate, confirm).
 * Validation structurelle uniquement (champs présents, types corrects).
 */
class ParticipationActionRequest
{
    public function __construct(
        public readonly int $participationId
    ) {
    }

    /**
     * Crée un ParticipationActionRequest depuis un tableau JSON décodé.
     * Valide uniquement la présence du champ requis.
     * 
     * @param array $data Données JSON décodées
     * @return self
     * @throws \InvalidArgumentException si champ manquant ou invalide
     */
    public static function fromArray(array $data): self
    {
        $participationId = isset($data['participation_id']) ? (int)$data['participation_id'] : 0;

        if ($participationId <= 0) {
            throw new \InvalidArgumentException('Le participation_id est requis et doit être valide');
        }

        return new self($participationId);
    }
}
