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

    private function ns(): string { return $this->dbName . '.' . $this->collection; }

    public function add(Avis $avis): bool
    {
        $bulk = new BulkWrite();
        $bulk->insert([
            'ride_id' => $avis->rideId,
            'user_id' => $avis->userId,
            'rating'  => $avis->rating,
            'comment' => $avis->comment,
            'created_at' => $avis->createdAt->format(DATE_ATOM),
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
        $query = new Query(['ride_id' => $rideId]);
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
                ['$match' => ['ride_id' => $rideId]],
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
}
