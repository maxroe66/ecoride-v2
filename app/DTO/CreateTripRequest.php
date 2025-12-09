<?php

namespace App\DTO;

/**
 * DTO représentant une requête de création de trajet.
 * Validation structurelle uniquement (champs présents, types corrects).
 */
class CreateTripRequest
{
    public function __construct(
        public readonly string $lieuDepart,
        public readonly string $lieuArrivee,
        public readonly string $dateDepart,
        public readonly string $heureDepart,
        public readonly string $heureArrivee,
        public readonly int $nbPlaces,
        public readonly float $prixPersonne,
        public readonly int $voitureId,
        public readonly ?int $dureeEstimee = null,
        public readonly ?string $preferences = null
    ) {
    }

    /**
     * Crée un CreateTripRequest depuis un tableau JSON décodé.
     * Valide uniquement la présence des champs requis (validation structurelle).
     * Les règles métier (nb_places > 0, prix >= 2, etc.) sont dans TripValidator.
     * 
     * @param array $data Données JSON décodées
     * @return self
     * @throws \InvalidArgumentException si champs manquants
     */
    public static function fromArray(array $data): self
    {
        // Champs obligatoires - validation structurelle uniquement
        $lieuDepart = trim((string)($data['lieu_depart'] ?? ''));
        $lieuArrivee = trim((string)($data['lieu_arrivee'] ?? ''));
        $dateDepart = trim((string)($data['date_depart'] ?? ''));
        $heureDepart = trim((string)($data['heure_depart'] ?? ''));
        $heureArrivee = trim((string)($data['heure_arrivee'] ?? ''));
        
        // Validation : champs présents
        if ($lieuDepart === '') {
            throw new \InvalidArgumentException('Le lieu de départ est requis');
        }
        if ($lieuArrivee === '') {
            throw new \InvalidArgumentException('Le lieu d\'arrivée est requis');
        }
        if ($dateDepart === '') {
            throw new \InvalidArgumentException('La date de départ est requise');
        }
        if ($heureDepart === '') {
            throw new \InvalidArgumentException('L\'heure de départ est requise');
        }
        if ($heureArrivee === '') {
            throw new \InvalidArgumentException('L\'heure d\'arrivée est requise');
        }
        
        // Champs numériques - vérifier présence uniquement
        if (!isset($data['nb_places'])) {
            throw new \InvalidArgumentException('Le nombre de places est requis');
        }
        if (!isset($data['prix_personne'])) {
            throw new \InvalidArgumentException('Le prix par personne est requis');
        }
        if (!isset($data['voiture_id'])) {
            throw new \InvalidArgumentException('Le voiture_id est requis');
        }

        $nbPlaces = (int)$data['nb_places'];
        $prixPersonne = (float)$data['prix_personne'];
        $voitureId = (int)$data['voiture_id'];

        // Champs optionnels
        $dureeEstimee = isset($data['duree_estimee']) ? (int)$data['duree_estimee'] : null;
        $preferences = isset($data['preferences']) ? trim((string)$data['preferences']) : null;

        return new self(
            $lieuDepart,
            $lieuArrivee,
            $dateDepart,
            $heureDepart,
            $heureArrivee,
            $nbPlaces,
            $prixPersonne,
            $voitureId,
            $dureeEstimee,
            $preferences
        );
    }
}
