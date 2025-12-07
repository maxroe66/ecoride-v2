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
     * Valide uniquement la présence des champs requis (validation structurelle).
     * Les règles métier (note entre 1-5, longueur commentaire) sont dans AvisValidator.
     * 
     * @param array $data Données JSON décodées
     * @return self
     * @throws \InvalidArgumentException si champs manquants
     */
    public static function fromArray(array $data): self
    {
        // Validation structurelle : champs présents
        if (!isset($data['covoiturage_id'])) {
            throw new \InvalidArgumentException('Le covoiturage_id est requis');
        }
        
        if (!isset($data['note'])) {
            throw new \InvalidArgumentException('La note est requise');
        }

        $covoiturageId = (int)$data['covoiturage_id'];
        $note = (int)$data['note'];
        $commentaire = isset($data['commentaire']) ? trim((string)$data['commentaire']) : null;

        // Commentaire vide = null
        if ($commentaire === '') {
            $commentaire = null;
        }

        return new self($covoiturageId, $note, $commentaire);
    }
}
