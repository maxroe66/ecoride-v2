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
            'id' => $this->id ?? null,
            'marque_id' => $this->marque_id,
            'marque' => $this->marque_libelle ?? null,
            'modele' => $this->modele,
            'couleur' => $this->couleur,
            'immatriculation' => $this->immatriculation,
            'date_premiere_immatriculation' => $this->date_premiere_immatriculation,
            'nb_places' => $this->nb_places,
            'energie' => $this->energie,
            'est_ecologique' => (bool)$this->est_ecologique
        ];
    }
}