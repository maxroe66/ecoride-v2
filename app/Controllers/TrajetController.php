<?php

namespace App\Controllers;

use App\Factories\DatabaseFactory;
use App\Repositories\TrajetRepository;
use App\Validators\QueryValidator;
use App\Services\TripService;

/**
 * Contrôleur des trajets.
 * Routes: GET /api/trajets, GET /api/trajets-suggestions
 * Conserve format des réponses et codes d'erreur du legacy.
 */
class TrajetController
{
    public static function search(): void
    {
        parse_str($_SERVER['QUERY_STRING'] ?? '', $query);

        // Validation paramètres principaux
        try {
            $validated = QueryValidator::validateTrajetSearch($query); // exception 400 pour erreurs
        } catch (\Exception $e) {
            http_response_code(400);
            $msg = $e->getMessage();
            $code = str_contains($msg, 'Format de date') ? 'INVALID_DATE' : 'MISSING_FIELDS';
            echo json_encode(['success' => false,'error' => ['code' => $code,'message' => $msg]]);
            return;
        }

        $filters = QueryValidator::extractFilters($query);

        try {
            $db = DatabaseFactory::getConnection();
            $service = new TripService(new TrajetRepository($db));
            $trajets = $service->search(
                $validated['departure'],
                $validated['arrival'],
                $validated['date'],
                $filters['economique'],
                $filters['maxPrice'],
                $filters['maxDuration'],
                $filters['minRating']
            );
            $payload = array_map(fn($t) => TripService::normalize($t), $trajets);
            http_response_code(200);
            echo json_encode(['success' => true,'data' => ['items' => $payload,'count' => count($payload)]]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false,'error' => ['code' => 'SEARCH_FAILED','message' => 'Erreur lors de la recherche. Veuillez réessayer.']]);
        }
    }

    public static function suggestions(): void
    {
        parse_str($_SERVER['QUERY_STRING'] ?? '', $query);
        try {
            $validated = QueryValidator::validateDateSuggestions($query);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false,'error' => ['code' => 'MISSING_FIELDS','message' => $e->getMessage()]]);
            return;
        }
        $filters = QueryValidator::extractFilters($query);
        try {
            $db = DatabaseFactory::getConnection();
            $service = new TripService(new TrajetRepository($db));
            $suggestions = $service->suggestions(
                $validated['departure'],
                $validated['arrival'],
                3,
                $filters['economique'],
                $filters['maxPrice'],
                $filters['maxDuration'],
                $filters['minRating']
            );
            http_response_code(200);
            echo json_encode(['success' => true,'data' => ['suggestions' => $suggestions]]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false,'error' => ['code' => 'SEARCH_FAILED','message' => 'Erreur lors de la recherche. Veuillez réessayer.']]);
        }
    }

    /**
     * Récupère le détail complet d'un covoiturage
     * GET /api/trajets/detail?id={id}
     */
    public static function show(): void
    {
        parse_str($_SERVER['QUERY_STRING'] ?? '', $query);
        $id = $query['id'] ?? null;

        // Valider que l'ID est un entier positif
        if (!$id || !is_numeric($id) || (int)$id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['code' => 'INVALID_ID', 'message' => 'ID invalide']]);
            return;
        }

        $id = (int)$id;

        try {
            $db = DatabaseFactory::getConnection();
            $service = new TripService(new TrajetRepository($db));
            $detail = $service->detail($id);

            // Log temporaire pour debug : affiche le détail et les avis récupérés
            error_log('[TrajetController] Détail trajet : ' . json_encode($detail));

            // Si vide, le trajet n'existe pas
            if (empty($detail)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Trajet introuvable']]);
                return;
            }

            // Retourner le détail
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $detail]);
        } catch (\Exception $e) {
            error_log('[TrajetController] Exception : ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => $e->getMessage()]]);
        }
    }
}
