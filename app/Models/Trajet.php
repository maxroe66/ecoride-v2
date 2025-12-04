<?php

namespace App\Models;

class Trajet
{
    public int $id;                          // covoiturage_id
    public string $dateDepart;               // date_depart (DATE)
    public string $heureDepart;              // heure_depart (TIME)
    public string $lieuDepart;               // lieu_depart
    public ?string $heureArrivee;            // heure_arrivee (nullable)
    public string $lieuArrivee;              // lieu_arrivee
    public int $nbPlaces;                    // nb_places
    public float $prixPersonne;              // prix_personne
    public string $statut;                   // statut (enum)
    public bool $estEcologique;              // est_ecologique
    public int $conducteurId;                // conducteur_id
    public int $voitureId;                   // voiture_id

    public function __construct(
        string $dateDepart,
        string $heureDepart,
        string $lieuDepart,
        string $lieuArrivee,
        int $nbPlaces,
        float $prixPersonne,
        int $conducteurId,
        int $voitureId,
        string $statut = 'planifie',
        bool $estEcologique = false,
        ?string $heureArrivee = null
    ) {
        $this->dateDepart = $dateDepart;
        $this->heureDepart = $heureDepart;
        $this->lieuDepart = $lieuDepart;
        $this->lieuArrivee = $lieuArrivee;
        $this->nbPlaces = $nbPlaces;
        $this->prixPersonne = $prixPersonne;
        $this->conducteurId = $conducteurId;
        $this->voitureId = $voitureId;
        $this->statut = $statut;
        $this->estEcologique = $estEcologique;
        $this->heureArrivee = $heureArrivee;
    }

    public function toArray(): array
    {
        return [
            'covoiturage_id' => $this->id ?? null,
            'date_depart' => $this->dateDepart,
            'heure_depart' => $this->heureDepart,
            'lieu_depart' => $this->lieuDepart,
            'heure_arrivee' => $this->heureArrivee,
            'lieu_arrivee' => $this->lieuArrivee,
            'nb_places' => $this->nbPlaces,
            'prix_personne' => $this->prixPersonne,
            'statut' => $this->statut,
            'est_ecologique' => (bool)$this->estEcologique,
            'conducteur_id' => $this->conducteurId,
            'voiture_id' => $this->voitureId,
        ];
    }
}