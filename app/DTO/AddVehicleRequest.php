<?php

namespace App\DTO;

use InvalidArgumentException;

/**
 * DTO pour la création d'un véhicule
 * Architecture: Controller → DTO (validation structurelle)
 */
readonly class AddVehicleRequest
{
    public function __construct(
        public string $marque,
        public string $modele,
        public string $immatriculation,
        public string $energie,
        public int $nbPlaces,
        public string $couleur,
        public string $datePremiereImmatriculation
    ) {}

    /**
     * Crée une instance depuis un tableau (ex: JSON décodé)
     * Validation structurelle uniquement (champs requis + types)
     * @throws InvalidArgumentException si des champs requis manquent
     */
    public static function fromArray(array $data): self
    {
        // Champs requis
        $required = ['marque', 'modele', 'immatriculation', 'energie', 'nb_places', 'couleur', 'date_premiere_immatriculation'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                throw new InvalidArgumentException("Le champ '{$field}' est requis.");
            }
        }

        // Validation type nb_places
        if (!is_numeric($data['nb_places'])) {
            throw new InvalidArgumentException("Le champ 'nb_places' doit être un nombre.");
        }

        return new self(
            trim($data['marque']),
            trim($data['modele']),
            trim($data['immatriculation']),
            trim($data['energie']),
            (int)$data['nb_places'],
            trim($data['couleur']),
            trim($data['date_premiere_immatriculation'])
        );
    }
}
