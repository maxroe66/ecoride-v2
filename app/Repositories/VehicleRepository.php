<?php

namespace App\Repositories;

use App\Models\Vehicules;
use PDO;
use Exception;

class VehicleRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    /**
     * Crée un nouveau véhicule
     * @param Vehicules $vehicle - Le véhicule à créer
     * @return int - L'ID du véhicule créé
     */
    public function create(Vehicules $vehicle): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO voiture (modele, marque_id, immatriculation, energie, nb_places, couleur, date_premiere_immatriculation, utilisateur_id, est_ecologique)
            VALUES (:modele, :marque_id, :immatriculation, :energie, :nb_places, :couleur, :date_premiere_immatriculation, :utilisateur_id, :est_ecologique)
        ');

        $stmt->execute([
            ':modele' => $vehicle->modele,
            ':marque_id' => $vehicle->marque_id,
            ':immatriculation' => $vehicle->immatriculation,
            ':energie' => $vehicle->energie,
            ':nb_places' => $vehicle->nb_places,
            ':couleur' => $vehicle->couleur,
            ':date_premiere_immatriculation' => $vehicle->date_premiere_immatriculation,
            ':utilisateur_id' => $vehicle->utilisateur_id,
            ':est_ecologique' => $vehicle->est_ecologique ? 1 : 0,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Récupère tous les véhicules d'un utilisateur
     * @param int $userId - L'ID de l'utilisateur
     * @return array - Tableau de véhicules
     */
    public function findByUserId(int $userId): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM voiture WHERE utilisateur_id = :user_id
        ');

        $stmt->execute([':user_id' => $userId]);

        $vehicles = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        $vehicle = new Vehicules(
            $row['modele'],
            $row['marque_id'],
            $row['immatriculation'],
            $row['energie'],
            $row['nb_places'],
            $row['utilisateur_id'],
            $row['couleur'],
            $row['date_premiere_immatriculation'],
            (bool)$row['est_ecologique']
        );
        $vehicle->id = $row['voiture_id'];
        $vehicles[] = $vehicle;
    }
        return $vehicles;
    }
}