<?php

namespace App\DTO;

/**
 * DTO représentant une requête de création d'avis.
 * Validation structurelle uniquement (champs présents, types corrects).
 */
class CreateAvisRequest
{
    public function __construct(
        public readonly int $covoiturageId,
        public readonly int $note,
        public readonly ?string $commentaire = null
    ) {
    }

    /**
     * Crée un CreateAvisRequest depuis un tableau JSON décodé.
     * Valide uniquement la présence des champs requis et types de base.
     * 
     * @param array $data Données JSON décodées
     * @return self
     * @throws \InvalidArgumentException si champs manquants ou invalides
     */
    public static function fromArray(array $data): self
    {
        // Validation BASIQUE : champs présents et types numériques
        $covoiturageId = isset($data['covoiturage_id']) ? (int)$data['covoiturage_id'] : 0;
        $note = isset($data['note']) ? (int)$data['note'] : 0;
        $commentaire = isset($data['commentaire']) ? trim((string)$data['commentaire']) : null;

        if ($covoiturageId <= 0) {
            throw new \InvalidArgumentException('Le covoiturage_id est requis et doit être valide');
        }

        if ($note <= 0) {
            throw new \InvalidArgumentException('La note est requise');
        }

        // Commentaire vide = null
        if ($commentaire === '') {
            $commentaire = null;
        }

        return new self($covoiturageId, $note, $commentaire);
    }
}
