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
        public readonly int $nbPlaces,
        public readonly float $prixPersonne,
        public readonly int $voitureId,
        public readonly ?int $dureeEstimee = null,
        public readonly ?string $preferences = null
    ) {
    }

    /**
     * Crée un CreateTripRequest depuis un tableau JSON décodé.
     * Valide uniquement la présence des champs requis et types de base.
     * 
     * @param array $data Données JSON décodées
     * @return self
     * @throws \InvalidArgumentException si champs manquants ou invalides
     */
    public static function fromArray(array $data): self
    {
        // Champs obligatoires
        $lieuDepart = trim((string)($data['lieu_depart'] ?? ''));
        $lieuArrivee = trim((string)($data['lieu_arrivee'] ?? ''));
        $dateDepart = trim((string)($data['date_depart'] ?? ''));
        $heureDepart = trim((string)($data['heure_depart'] ?? ''));
        $nbPlaces = isset($data['nb_places']) ? (int)$data['nb_places'] : 0;
        $prixPersonne = isset($data['prix_personne']) ? (float)$data['prix_personne'] : 0.0;
        $voitureId = isset($data['voiture_id']) ? (int)$data['voiture_id'] : 0;

        // Validation basique
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
        if ($nbPlaces <= 0) {
            throw new \InvalidArgumentException('Le nombre de places doit être supérieur à 0');
        }
        if ($prixPersonne < 0) {
            throw new \InvalidArgumentException('Le prix par personne ne peut pas être négatif');
        }
        if ($voitureId <= 0) {
            throw new \InvalidArgumentException('Le voiture_id est requis et doit être valide');
        }

        // Champs optionnels
        $dureeEstimee = isset($data['duree_estimee']) ? (int)$data['duree_estimee'] : null;
        $preferences = isset($data['preferences']) ? trim((string)$data['preferences']) : null;

        return new self(
            $lieuDepart,
            $lieuArrivee,
            $dateDepart,
            $heureDepart,
            $nbPlaces,
            $prixPersonne,
            $voitureId,
            $dureeEstimee,
            $preferences
        );
    }
}
