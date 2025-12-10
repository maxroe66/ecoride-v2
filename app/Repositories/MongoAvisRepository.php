<?php

namespace App\Repositories;

use App\Models\Avis;
use MongoDB\Driver\Manager;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Query;
use MongoDB\Driver\Command;
use MongoDB\Driver\Exception\Exception as MongoDriverException;

class MongoAvisRepository implements AvisRepositoryInterface
{
    private Manager $manager;
    public function __construct(private string $dsn, private string $dbName = 'ecoride', private string $collection = 'avis')
    {
        $this->manager = new Manager($dsn);
    }

    private function ns(): string
    {
        return $this->dbName . '.' . $this->collection;
    }

    public function add(Avis $avis): bool
    {
        $bulk = new BulkWrite();
        $bulk->insert([
            'ride_id' => $avis->rideId,
            'user_id' => $avis->userId,
            'rating'  => $avis->rating,
            'comment' => $avis->comment,
            'created_at' => $avis->createdAt->format(DATE_ATOM),
            'statut_moderation' => 'en_attente',  // Avis en attente de modération par défaut
            'date_moderation' => null,
            'employe_id' => null,
        ]);
        try {
            $res = $this->manager->executeBulkWrite($this->ns(), $bulk);
            return $res->getInsertedCount() === 1;
        } catch (MongoDriverException $e) {
            throw $e; // Laisser la résilience gérer
        }
    }

    public function listForRide(int $rideId): array
    {
        // Récupérer uniquement les avis APPROUVÉS (visibles publiquement)
        $query = new Query([
            'ride_id' => $rideId,
            'statut_moderation' => 'approuve'
        ]);
        $cursor = $this->manager->executeQuery($this->ns(), $query);
        $out = [];
        foreach ($cursor as $doc) {
            $doc = (array)$doc;
            $out[] = new Avis(
                (int)($doc['ride_id'] ?? 0),
                (int)($doc['user_id'] ?? 0),
                (int)($doc['rating'] ?? 0),
                $doc['comment'] ?? null,
                new \DateTimeImmutable($doc['created_at'] ?? 'now')
            );
        }
        return $out;
    }

    public function averageForRide(int $rideId): float
    {
        $cmd = new Command([
            'aggregate' => $this->collection,
            'pipeline' => [
                ['$match' => ['ride_id' => $rideId, 'statut_moderation' => 'approuve']],
                ['$group' => ['_id' => null, 'avg' => ['$avg' => '$rating']]]
            ],
            'cursor' => new \stdClass()
        ]);
        try {
            $cursor = $this->manager->executeCommand($this->dbName, $cmd);
            $resArr = iterator_to_array($cursor);
            if (isset($resArr[0]->avg)) {
                return (float)$resArr[0]->avg;
            }
            return 0.0;
        } catch (MongoDriverException $e) {
            throw $e; // Gestion par résilience
        }
    }

    /**
     * Récupère les avis en attente de modération
     * @return array Liste des avis en_attente avec détails
     */
    public function findPendingReviews(): array
    {
        $query = new Query([
            'statut_moderation' => 'en_attente'
        ]);
        $cursor = $this->manager->executeQuery($this->ns(), $query);
        $out = [];
        foreach ($cursor as $doc) {
            $doc = (array)$doc;
            $out[] = [
                '_id' => (string)($doc['_id'] ?? ''),
                'ride_id' => (int)($doc['ride_id'] ?? 0),
                'user_id' => (int)($doc['user_id'] ?? 0),
                'rating' => (int)($doc['rating'] ?? 0),
                'comment' => $doc['comment'] ?? null,
                'created_at' => $doc['created_at'] ?? null,
                'statut_moderation' => $doc['statut_moderation'] ?? 'en_attente',
            ];
        }
        return $out;
    }

    /**
     * Modère un avis (approuve ou refuse)
     * @param string $avisId ID MongoDB du document (_id)
     * @param string $action 'approuve' ou 'refuse'
     * @param int $employeId ID de l'employé qui effectue la modération
     * @return bool true si succès
     */
    public function moderateReview(string $avisId, string $action, int $employeId): bool
    {
        $bulk = new BulkWrite();
        $bulk->update(
            ['_id' => new \MongoDB\BSON\ObjectId($avisId)],
            [
                '$set' => [
                    'statut_moderation' => $action,
                    'date_moderation' => new \MongoDB\BSON\UTCDateTime(time() * 1000),
                    'employe_id' => $employeId,
                ]
            ]
        );
        try {
            $res = $this->manager->executeBulkWrite($this->ns(), $bulk);
            return $res->getModifiedCount() === 1;
        } catch (MongoDriverException $e) {
            throw $e;
        }
    }
}

