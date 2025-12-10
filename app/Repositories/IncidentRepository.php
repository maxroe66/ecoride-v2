<?php

namespace App\Repositories;

use PDO;

/**
 * Repository pour gérer les incidents (problèmes de trajets)
 * Table: incident
 */
class IncidentRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Crée un nouvel incident
     * @return int ID de l'incident créé
     */
    public function create(int $participationId, int $covoiturageId, int $utilisateurId, string $description): int
    {
        $sql = "INSERT INTO incident (participation_id, covoiturage_id, utilisateur_id, description, statut, date_creation)
                VALUES (:participation_id, :covoiturage_id, :utilisateur_id, :description, 'en_cours', NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':participation_id' => $participationId,
            ':covoiturage_id' => $covoiturageId,
            ':utilisateur_id' => $utilisateurId,
            ':description' => $description
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Récupère un incident par son ID
     */
    public function findById(int $incidentId): ?array
    {
        $sql = "SELECT * FROM incident WHERE incident_id = :incident_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':incident_id' => $incidentId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Récupère les détails complets d'un incident par participation_id (pour l'espace employé)
     */
    public function findDetailByParticipation(int $participationId): ?array
    {
        $sql = "SELECT i.*, 
                       u_signalee.pseudo AS utilisateur_pseudo, u_signalee.email AS utilisateur_email,
                       c.lieu_depart, c.lieu_arrivee, c.date_depart, c.heure_depart, c.heure_arrivee,
                       u_conducteur.pseudo AS conducteur_pseudo, u_conducteur.email AS conducteur_email,
                       u_passager.pseudo AS passager_pseudo, u_passager.email AS passager_email,
                       p.utilisateur_id AS passager_id
                FROM incident i
                LEFT JOIN utilisateur u_signalee ON i.utilisateur_id = u_signalee.utilisateur_id
                LEFT JOIN covoiturage c ON i.covoiturage_id = c.covoiturage_id
                LEFT JOIN utilisateur u_conducteur ON c.conducteur_id = u_conducteur.utilisateur_id
                LEFT JOIN participation p ON i.participation_id = p.participation_id
                LEFT JOIN utilisateur u_passager ON p.utilisateur_id = u_passager.utilisateur_id
                WHERE i.participation_id = :participation_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':participation_id' => $participationId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Récupère tous les incidents d'une participation
     */
    public function findByParticipation(int $participationId): array
    {
        $sql = "SELECT * FROM incident WHERE participation_id = :participation_id ORDER BY date_creation DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':participation_id' => $participationId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère tous les incidents d'un trajet
     */
    public function findByTrajet(int $covoiturageId): array
    {
        $sql = "SELECT * FROM incident WHERE covoiturage_id = :covoiturage_id ORDER BY date_creation DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':covoiturage_id' => $covoiturageId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère tous les incidents en attente (pour les employés)
     */
    public function findPending(): array
    {
        $sql = "SELECT i.*, 
                       u_signalee.pseudo AS utilisateur_pseudo, u_signalee.email AS utilisateur_email,
                       c.lieu_depart, c.lieu_arrivee, c.date_depart, c.heure_depart, c.heure_arrivee,
                       u_conducteur.pseudo AS conducteur_pseudo, u_conducteur.email AS conducteur_email,
                       u_passager.pseudo AS passager_pseudo, u_passager.email AS passager_email,
                       p.utilisateur_id AS passager_id
                FROM incident i
                LEFT JOIN utilisateur u_signalee ON i.utilisateur_id = u_signalee.utilisateur_id
                LEFT JOIN covoiturage c ON i.covoiturage_id = c.covoiturage_id
                LEFT JOIN utilisateur u_conducteur ON c.conducteur_id = u_conducteur.utilisateur_id
                LEFT JOIN participation p ON i.participation_id = p.participation_id
                LEFT JOIN utilisateur u_passager ON p.utilisateur_id = u_passager.utilisateur_id
                WHERE i.statut = 'en_cours'
                ORDER BY i.date_creation DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Met à jour le statut d'un incident
     */
    public function updateStatus(int $incidentId, string $status): void
    {
        $sql = "UPDATE incident SET statut = :statut WHERE incident_id = :incident_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':statut' => $status,
            ':incident_id' => $incidentId
        ]);
    }

    /**
     * Résout un incident (change statut à 'resolu')
     */
    public function resolve(int $incidentId): void
    {
        $this->updateStatus($incidentId, 'resolu');
    }
}
