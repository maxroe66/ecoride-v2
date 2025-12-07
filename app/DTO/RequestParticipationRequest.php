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
     * Valide uniquement la présence des champs requis (validation structurelle).
     * Les règles métier (nb_places valide, disponibilité) sont dans le Service.
     * 
     * @param array $data Données JSON décodées
     * @return self
     * @throws \InvalidArgumentException si champs manquants
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['covoiturage_id'])) {
            throw new \InvalidArgumentException('Le covoiturage_id est requis');
        }

        if (!isset($data['nb_places'])) {
            throw new \InvalidArgumentException('Le nombre de places est requis');
        }

        $covoiturageId = (int)$data['covoiturage_id'];
        $nbPlaces = (int)$data['nb_places'];

        return new self($covoiturageId, $nbPlaces);
    }
}
