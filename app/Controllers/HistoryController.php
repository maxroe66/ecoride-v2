<?php

namespace App\Controllers;

use App\Factories\DatabaseFactory;
use App\Repositories\ParticipationRepository;
use App\Repositories\TrajetRepository;
use App\Services\HistoryService;
use App\Validators\CancellationValidator;
use App\Middleware\AuthMiddleware;
use Exception;

/**
 * Contrôleur pour l'historique des covoiturages.
 * Routes: GET /api/historique/trajets
 */
class HistoryController
{
    /**
     * Récupère l'historique complet des trajets de l'utilisateur
     * GET /api/historique/trajets
     * Retourne tous les trajets (en tant que chauffeur) et participations (en tant que passager)
     */
    public static function getUserHistory(): void
    {
        try {
            // Authentification requise via middleware
            $authMw = new AuthMiddleware();
            $user = $authMw->authenticate();
            $userId = (int)($user['user_id'] ?? $user['id'] ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Authentification requise']]);
                return;
            }

            // Récupérer la base de données
            $db = DatabaseFactory::getConnection();

            // Initialiser le service avec les repositories
            $trajetRepo = new TrajetRepository($db);
            $participationRepo = new ParticipationRepository($db);
            $historyService = new HistoryService($trajetRepo, $participationRepo);

            // Récupérer l'historique
            $history = $historyService->getUserTripHistory($userId);

            // Retourner la réponse
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $history,
                'count' => count($history)
            ]);

        } catch (Exception $e) {
            error_log('[HistoryController] Exception : ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => $e->getMessage()]]);
        }
    }

    /**
     * Récupère l'historique filtré par statut
     * GET /api/historique/trajets?status=termine
     * Statuts supportés: planifie, en_cours, termine, annule
     */
    public static function getHistoryByStatus(): void
    {
        parse_str($_SERVER['QUERY_STRING'] ?? '', $query);
        $status = $query['status'] ?? null;

        try {
            // Valider le statut
            if (!$status) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => ['code' => 'MISSING_STATUS', 'message' => 'Paramètre status requis']]);
                return;
            }

            try {
                $status = CancellationValidator::validateStatusFilter($status);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]]);
                return;
            }
            // Authentification requise via middleware
            $authMw = new AuthMiddleware();
            $user = $authMw->authenticate();
            $userId = (int)($user['user_id'] ?? $user['id'] ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Authentification requise']]);
                return;
            }

            // Récupérer la base de données
            $db = DatabaseFactory::getConnection();

            // Initialiser le service
            $trajetRepo = new TrajetRepository($db);
            $participationRepo = new ParticipationRepository($db);
            $historyService = new HistoryService($trajetRepo, $participationRepo);

            // Récupérer l'historique complet
            $fullHistory = $historyService->getUserTripHistory($userId);

            // Filtrer par statut
            $filtered = array_filter($fullHistory, fn($trip) => ($trip['statut'] ?? $trip['statut_participation'] ?? null) === $status);
            $filtered = array_values($filtered); // Réindexer le tableau

            // Retourner la réponse
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $filtered,
                'count' => count($filtered)
            ]);

        } catch (Exception $e) {
            error_log('[HistoryController] Exception : ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => $e->getMessage()]]);
        }
    }
}
