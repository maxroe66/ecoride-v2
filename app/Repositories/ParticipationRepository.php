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

    public function findByTrip(int $tripId): array
    {
        // Étape 1: Préparer
        $stmt = $this->db->prepare('
            SELECT * FROM participation
            WHERE covoiturage_id = :trip_id
        ');
        
        // Étape 2: Exécuter
        $stmt->execute([
            ':trip_id' => $tripId
        ]);
        
        // Étape 3: Retourner
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les participations d'un trajet avec un statut spécifique
     */
    public function findByTrajetAndStatus(int $tripId, string $status): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM participation
            WHERE covoiturage_id = :trip_id AND statut = :statut
            ORDER BY date_reservation DESC
        ');

        $stmt->execute([
            ':trip_id' => $tripId,
            ':statut' => $status
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByUserAndStatus(int $userId, string $status): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM participation
            WHERE utilisateur_id = :user_id AND statut = :statut
            ORDER BY date_reservation DESC
        ');

        $stmt->execute([
            ':user_id' => $userId,
            ':statut' => $status
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère TOUTES les participations d'un utilisateur (tous statuts)
     * Utilisé pour l'historique filtré par statut
     */
    public function findByUserId(int $userId): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM participation
            WHERE utilisateur_id = :user_id
            ORDER BY date_reservation DESC
        ');

        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les participations d'un utilisateur avec le statut du trajet
     * Pour afficher les actions disponibles (validation/problème) qui dépendent du statut du trajet
     */
    public function findByUserWithTripStatus(int $userId): array
    {
        $stmt = $this->db->prepare('
            SELECT 
                p.*,
                c.statut as trajet_statut
            FROM participation p
            JOIN covoiturage c ON p.covoiturage_id = c.covoiturage_id
            WHERE p.utilisateur_id = :user_id
            ORDER BY p.date_reservation DESC
        ');

        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les participations confirmées d'un utilisateur avec le statut du trajet
     * Pour la page d'historique principale
     */
    public function findByUserAndStatusWithTripStatus(int $userId, string $status): array
    {
        $stmt = $this->db->prepare('
            SELECT 
                p.*,
                c.covoiturage_id,
                c.statut as trajet_statut,
                c.date_depart,
                c.heure_depart,
                c.lieu_depart,
                c.lieu_arrivee,
                c.prix_personne,
                c.nb_places,
                c.conducteur_id,
                u.pseudo as chauffeur_nom,
                u.utilisateur_id
            FROM participation p
            JOIN covoiturage c ON p.covoiturage_id = c.covoiturage_id
            JOIN utilisateur u ON c.conducteur_id = u.utilisateur_id
            WHERE p.utilisateur_id = :user_id AND p.statut = :statut
            ORDER BY p.date_reservation DESC
        ');

        $stmt->execute([
            ':user_id' => $userId,
            ':statut' => $status
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Update the status of a participation

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


    public function updateStatusByTrip(int $tripId, string $newStatus): int
    {
        $stmt = $this->db->prepare('
            UPDATE participation SET statut = :statut WHERE covoiturage_id = :trip_id
        ');

        $stmt->execute([
            ':statut' => $newStatus,
            ':trip_id' => $tripId
        ]);
        return $stmt->rowCount();
    }
}