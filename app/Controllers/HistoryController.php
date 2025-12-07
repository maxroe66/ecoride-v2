<?php

namespace App\Controllers;

use App\Factories\ServiceLocator as SL;
use App\Validators\CancellationValidator;
use App\Helpers\ControllerHelper;
use App\Core\Request;
use App\Core\Response;
use Exception;

/**
 * Contrôleur pour l'historique des covoiturages.
 * Routes: GET /api/historique/trajets
 * Architecture: Request → Service → Response (pas de DTO pour GET simple)
 */
class HistoryController
{
    /**
     * Récupère l'historique complet des trajets de l'utilisateur
     * GET /api/historique/trajets
     * Retourne tous les trajets (en tant que chauffeur) et participations (en tant que passager)
     */
    public static function getUserHistory(Request $req): void
    {
        try {
            // Récupérer l'utilisateur authentifié (via middleware)
            $userId = ControllerHelper::getAuthUserId();

            // Service : Récupérer l'historique
            $historyService = SL::getHistoryService();
            $history = $historyService->getUserTripHistory($userId);

            // Response : Retourner les données
            Response::json(200, [
                'success' => true,
                'data' => $history,
                'count' => count($history)
            ]);

        } catch (Exception $e) {
            error_log('[HistoryController::getUserHistory] Exception : ' . $e->getMessage());
            Response::json(500, [
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => 'Erreur lors de la récupération de l\'historique'
                ]
            ]);
        }
    }

    /**
     * Récupère l'historique filtré par statut
     * GET /api/historique/trajets/filtre?status=termine
     * Statuts supportés: planifie, en_cours, termine, annule
     */
    public static function getHistoryByStatus(Request $req): void
    {
        try {
            // 1. Récupérer les paramètres
            $query = $req->getQueryParams();
            $status = $query['status'] ?? null;

            // 2. Validation : Statut requis
            if (!$status) {
                Response::json(400, [
                    'success' => false,
                    'error' => [
                        'code' => 'MISSING_STATUS',
                        'message' => 'Paramètre status requis'
                    ]
                ]);
                return;
            }

            // 3. Validation : Statut valide (utilise CancellationValidator pour cohérence)
            try {
                $validatedStatus = CancellationValidator::validateStatusFilter($status);
            } catch (Exception $e) {
                Response::json(400, [
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => $e->getMessage()
                    ]
                ]);
                return;
            }
            
            // 4. Récupérer l'utilisateur authentifié
            $userId = ControllerHelper::getAuthUserId();

            // 5. Service : Récupérer et filtrer l'historique
            $historyService = SL::getHistoryService();
            $filteredHistory = $historyService->getUserTripHistoryByStatus($userId, $validatedStatus);

            // 6. Response : Retourner les données filtrées
            Response::json(200, [
                'success' => true,
                'data' => $filteredHistory,
                'count' => count($filteredHistory)
            ]);

        } catch (Exception $e) {
            error_log('[HistoryController::getHistoryByStatus] Exception : ' . $e->getMessage());
            Response::json(500, [
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => 'Erreur lors de la récupération de l\'historique'
                ]
            ]);
        }
    }
}
