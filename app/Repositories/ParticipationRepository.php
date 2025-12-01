<?php

namespace App\Repositories;

use PDO;

class ParticipationRepository implements ParticipationRepositoryInterface
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $userId, int $covoiturageId, int $nbPlaces): int
    {
        // Etape 1: Préparer
        $stmt = $this->db->prepare('
            INSERT INTO participation (utilisateur_id, covoiturage_id, nb_places, statut, date_reservation)
            VALUES (:user_id, :covoiturage_id, :nb_places, :statut, NOW())
            ');
        // Etape 2: Exécuter les vraies valeurs
        $stmt->execute([
            ':user_id' => $userId,
            ':covoiturage_id' => $covoiturageId,
            ':nb_places' => $nbPlaces,
            ':statut' => 'demandee'
        ]);
        // Etape 3: Retourner l'ID créé
        return (int)$this->db->lastInsertId();
    }
    
    public function findById(int $participationId): ?array
    {
    //Etape 1: Préparer
    $stmt = $this->db->prepare('
        SELECT * FROM participation WHERE participation_id = :id LIMIT 1
    '); 
    //Etape 2: Exécuter les vraies valeurs
    $stmt->execute([':id' => $participationId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    //Etape 3: Retourner le résultat ou null
    return $result ?: null;
    }

    public function findByUserAndTrip(int $userId, int $covoiturageId): ?array
    {
        // Etape 1: Préparer
        $stmt = $this->db->prepare('
            SELECT * FROM participation 
            WHERE utilisateur_id = :user_id AND covoiturage_id = :covoiturage_id
            LIMIT 1
        ');
        // Etape 2: Exécuter les vraies valeurs
        $stmt->execute([
            ':user_id' => $userId,
            ':covoiturage_id' => $covoiturageId
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        // Etape 3: Retourner le résultat ou null
        return $result ?: null;
    }

    public function updateStatus(int $participationId, string $newStatus): bool
    {
        // Etape 1: Préparer
        $stmt = $this->db->prepare('
            UPDATE participation SET statut = :statut WHERE participation_id = :id
        ');
        // Etape 2: Exécuter les vraies valeurs
        $stmt->execute([
            ':statut' => $newStatus,
            ':id' => $participationId
        ]);
        // Etape 3: Retourner si au moins une ligne a été affectée
        return $stmt->rowCount() > 0;
    }
}