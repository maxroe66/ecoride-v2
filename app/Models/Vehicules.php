<?php

namespace App\Models;

class Vehicules
{
    public int $id;
    public string $modele;
    public int $marque_id;
    public ?string $marque_libelle = null;
    public string $immatriculation;
    public string $energie;
    public int $nb_places;
    public bool $est_ecologique;
    public ?string $couleur;
    public ?string $date_premiere_immatriculation;
    public int $utilisateur_id;

    public function __construct(
        string $modele,
        int $marque_id,
        string $immatriculation,
        string $energie,
        int $nb_places,
        int $utilisateur_id,
        ?string $couleur = null,
        ?string $date_premiere_immatriculation = null,
        bool $est_ecologique = false
    ) {
        $this->modele = $modele;
        $this->marque_id = $marque_id;
        $this->immatriculation = $immatriculation;
        $this->energie = $energie;
        $this->nb_places = $nb_places;
        $this->utilisateur_id = $utilisateur_id;
        $this->couleur = $couleur;
        $this->date_premiere_immatriculation = $date_premiere_immatriculation;
        $this->est_ecologique = $est_ecologique;
    }

    public function toArray(): array
    {
        return [
            'voiture_id' => $this->id ?? null,
            'modele' => $this->modele,
            'marque_id' => $this->marque_id,
            'immatriculation' => $this->immatriculation,
            'energie' => $this->energie,
            'nb_places' => $this->nb_places,
            'est_ecologique' => (bool)$this->est_ecologique,
            'couleur' => $this->couleur,
            'date_premiere_immatriculation' => $this->date_premiere_immatriculation,
            'utilisateur_id' => $this->utilisateur_id,
        ];
    }
}