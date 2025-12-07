<?php

namespace App\DTO;

/**
 * DTO représentant une demande de participation à un covoiturage.
 * Validation structurelle uniquement (champs présents, types corrects).
 */
class RequestParticipationRequest
{
    public function __construct(
        public readonly int $covoiturageId,
        public readonly int $nbPlaces
    ) {
    }

    /**
     * Crée un RequestParticipationRequest depuis un tableau JSON décodé.
     * Valide uniquement la présence des champs requis et types de base.
     * 
     * @param array $data Données JSON décodées
     * @return self
     * @throws \InvalidArgumentException si champs manquants ou invalides
     */
    public static function fromArray(array $data): self
    {
        $covoiturageId = isset($data['covoiturage_id']) ? (int)$data['covoiturage_id'] : 0;
        $nbPlaces = isset($data['nb_places']) ? (int)$data['nb_places'] : 0;

        if ($covoiturageId <= 0) {
            throw new \InvalidArgumentException('Le covoiturage_id est requis et doit être valide');
        }

        if ($nbPlaces <= 0) {
            throw new \InvalidArgumentException('Le nombre de places est requis et doit être supérieur à 0');
        }

        return new self($covoiturageId, $nbPlaces);
    }
}
