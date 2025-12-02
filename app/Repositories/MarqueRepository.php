<?php

namespace App\Repositories;

use PDO;

class MarqueRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function findByName(string $name): ?array
    {
        $stmt = $this->db->prepare('SELECT marque_id, libelle FROM marque WHERE libelle = :name LIMIT 1');
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(string $name): int
    {
        $stmt = $this->db->prepare('INSERT INTO marque (libelle) VALUES (:name)');
        $stmt->execute([':name' => $name]);
        return (int)$this->db->lastInsertId();
    }

    public function findOrCreateByName(string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Le nom de marque est vide');
        }
        $found = $this->findByName($name);
        if ($found) {
            return (int)$found['marque_id'];
        }
        return $this->create($name);
    }
}
