<?php

namespace App\Repositories;

use App\Models\Avis;
use PDO;

class MysqlAvisRepository implements AvisRepositoryInterface
{
    private string $table;

    public function __construct(private PDO $pdo, ?string $table = null)
    {
        $this->table = $table ?? getenv('REVIEWS_FALLBACK_TABLE') ?: 'avis_fallback';
    }

    public function add(Avis $avis): bool
    {
        $sql = "INSERT INTO {$this->table} (covoiturage_id,utilisateur_id,note,commentaire,date_creation,statut_moderation) VALUES (?,?,?,?,?,?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $avis->rideId,
            $avis->userId,
            $avis->rating,
            $avis->comment,
            $avis->createdAt->format('Y-m-d H:i:s'),
            'en_attente'  // Avis en attente de modération par défaut
        ]);
    }

    public function listForRide(int $rideId): array
    {
        // Récupérer uniquement les avis APPROUVÉS (visibles publiquement)
        $sql = "SELECT covoiturage_id, utilisateur_id, note, commentaire, date_creation FROM {$this->table} WHERE covoiturage_id=? AND statut_moderation='approuve' ORDER BY date_creation DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$rideId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(
            fn($r) => new Avis(
                (int)$r['covoiturage_id'],
                (int)$r['utilisateur_id'],
                (int)$r['note'],
                $r['commentaire'] ?? null,
                new \DateTimeImmutable($r['date_creation'])
            ),
            $rows
        );
    }

    public function averageForRide(int $rideId): float
    {
        // Calculer la moyenne uniquement sur les avis APPROUVÉS
        $sql = "SELECT AVG(note) as avg_rating FROM {$this->table} WHERE covoiturage_id=? AND statut_moderation='approuve'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$rideId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (float)$val : 0.0;
    }

    /**
     * Récupère les avis en attente de modération
     * @return array Liste des avis en_attente
     */
    public function findPendingReviews(): array
    {
        $sql = "SELECT avis_id, covoiturage_id, utilisateur_id, note, commentaire, date_creation, statut_moderation 
                FROM {$this->table} 
                WHERE statut_moderation='en_attente' 
                ORDER BY date_creation ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Modère un avis (approuve ou refuse)
     * @param int $avisId ID de l'avis à modérer
     * @param string $action 'approuve' ou 'refuse'
     * @param int $employeId ID de l'employé qui effectue la modération
     * @return bool true si succès
     */
    public function moderateReview(int $avisId, string $action, int $employeId): bool
    {
        $sql = "UPDATE {$this->table} 
                SET statut_moderation=?, date_moderation=NOW(), employe_id=? 
                WHERE avis_id=?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$action, $employeId, $avisId]);
    }
