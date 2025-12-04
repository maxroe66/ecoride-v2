<?php

namespace App\Repositories;

interface CreditOperationRepositoryInterface
{
    /**
     * Crée une nouvelle opération de crédit/débit
     * @param int $userId - ID de l'utilisateur
     * @param string $type - 'credit' ou 'debit'
     * @param float $amount - montant de l'opération
     * @return int - ID de l'opération créée
     */
    public function create(int $userId, string $type, float $amount): int;

    /**
     * Récupère l'historique des opérations d'un utilisateur
     * @param int $userId - ID de l'utilisateur
     * @param int|null $limit - nombre maximum de résultats (null = pas de limite)
     * @return array - tableau des opérations triées par date décroissante
     */
    public function findByUser(int $userId, ?int $limit = null): array;
}
