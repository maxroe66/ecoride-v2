<?php

namespace App\Services;

/**
 * Service de gestion des opérations de crédit
 * Logique métier : calculs, statistiques, filtrage
 */
class CreditOperationService
{
    /**
     * Calcule les totaux de crédit et débit à partir d'opérations
     * 
     * @param array $operations Liste des opérations (chaque opération = ['type_operation' => 'credit'|'debit', 'montant' => float])
     * @return array ['total_credit' => float, 'total_debit' => float]
     */
    public function calculateTotals(array $operations): array
    {
        $totalCredit = 0.0;
        $totalDebit = 0.0;

        foreach ($operations as $op) {
            $type = $op['type_operation'] ?? '';
            $montant = (float)($op['montant'] ?? 0);

            if ($type === 'credit') {
                $totalCredit += $montant;
            } elseif ($type === 'debit') {
                $totalDebit += $montant;
            }
        }

        return [
            'total_credit' => $totalCredit,
            'total_debit' => $totalDebit
        ];
    }
}
