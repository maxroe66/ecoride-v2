<?php

namespace App\Repositories;

use PDO;

class CreditOperationRepository implements CreditOperationRepositoryInterface
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Crée une nouvelle opération de crédit/débit
     * @param int $userId - ID de l'utilisateur
     * @param string $type - 'credit' ou 'debit'
     * @param float $amount - montant de l'opération
     * @return int - ID de l'opération créée
     */
    public function create(int $userId, string $type, float $amount): int
    {
        // Étape 1: Préparer
        $stmt = $this->db->prepare('
            INSERT INTO credit_operation (utilisateur_id, type_operation, montant, date_operation)
            VALUES (:user_id, :type, :amount, NOW())
        ');

        // Étape 2: Exécuter
        $stmt->execute([
            ':user_id' => $userId,
            ':type' => $type,
            ':amount' => $amount
        ]);

        // Étape 3: Retourner l'ID créé
        return (int)$this->db->lastInsertId();
    }

    /**
     * Récupère l'historique des opérations d'un utilisateur
     * @param int $userId - ID de l'utilisateur
     * @param int|null $limit - nombre maximum de résultats (null = pas de limite)
     * @return array - tableau des opérations triées par date décroissante
     */
    public function findByUser(int $userId, ?int $limit = null): array
    {
        // Étape 1: Construire la requête
        $sql = '
            SELECT 
                operation_id,
                utilisateur_id,
                type_operation,
                montant,
                date_operation
            FROM credit_operation
            WHERE utilisateur_id = :user_id
            ORDER BY date_operation DESC
        ';

        // Ajouter LIMIT si fourni
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int)$limit;
        }

        // Étape 2: Préparer et exécuter
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);

        // Étape 3: Retourner tous les résultats
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
    }
}
