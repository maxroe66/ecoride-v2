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
        $sql = "INSERT INTO {$this->table} (covoiturage_id,utilisateur_id,note,commentaire,date_creation) VALUES (?,?,?,?,?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $avis->rideId,
            $avis->userId,
            $avis->rating,
            $avis->comment,
            $avis->createdAt->format('Y-m-d H:i:s')
        ]);
    }

    public function listForRide(int $rideId): array
    {
        $sql = "SELECT covoiturage_id, utilisateur_id, note, commentaire, date_creation FROM {$this->table} WHERE covoiturage_id=? ORDER BY date_creation DESC";
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
        $sql = "SELECT AVG(note) as avg_rating FROM {$this->table} WHERE covoiturage_id=?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$rideId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (float)$val : 0.0;
    }
}
