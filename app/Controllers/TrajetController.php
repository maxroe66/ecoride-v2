<?php

namespace App\Controllers;

use App\Factories\DatabaseFactory;
use App\Repositories\TrajetRepository;
use App\Repositories\ParticipationRepository;
use App\Repositories\UserRepository;
use App\Validators\QueryValidator;
use App\Services\TripService;
use App\Services\ParticipationService;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use Exception;

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
    /**
     * Demander une participation à un covoiturage
     * POST /api/participations/request
     * Body: { covoiturage_id, nb_places }
     */
    public static function requestParticipation(): void
    {
        // Définir le header avant toute sortie
        header('Content-Type: application/json');

        // 1. AUTHENTIFICATION
        try {
            $middleware = new AuthMiddleware();
            $userData = $middleware->authenticate();
            $userId = (int)$userData['user_id'];
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Authentification requise. Veuillez vous connecter.']]);
            return;
        }

        // 1bis. CSRF
        (new CsrfMiddleware())->validate();

        // 2. LIRE ET VALIDER LE JSON
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['code' => 'INVALID_JSON', 'message' => 'Corps JSON invalide']]);
            return;
        }

        // Extraire covoiturage_id et nb_places du JSON
        $covoiturageId = $json['covoiturage_id'] ?? null;
        $nbPlaces = $json['nb_places'] ?? null;

        // Valider que ces champs existent et sont des entiers > 0
        if (!is_int($covoiturageId) || $covoiturageId <= 0 || !is_int($nbPlaces) || $nbPlaces <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['code' => 'INVALID_INPUT', 'message' => 'covoiturage_id et nb_places doivent être des entiers positifs']]);
            return;
        }
        
        // 3. LOGIQUE MÉTIER
        try {
            $db = DatabaseFactory::getConnection();
            $service = new ParticipationService(
                new ParticipationRepository($db),
                new TrajetRepository($db),
                new UserRepository($db)
            );

            // Appeler le service et retourner le résultat
            $result = $service->requestParticipation($userId, $covoiturageId, $nbPlaces);
            http_response_code(201);
            echo json_encode(['success' => true, 'data' => $result]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['code' => 'OPERATION_FAILED', 'message' => $e->getMessage()]]);
        }
    }

    /**
     * Valider une participation (1ère confirmation)
     * POST /api/participations/validate
     * Body: { participation_id }
     */
    public static function validateParticipation(): void
    {
        // Définir le header avant toute sortie
        header('Content-Type: application/json');

        // 1. AUTHENTIFICATION
        try {
            $middleware = new AuthMiddleware();
            $userData = $middleware->authenticate();
            $userId = (int)$userData['user_id'];
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Authentification requise. Veuillez vous connecter.']]);
            return;
        }

        // 1bis. CSRF
        (new CsrfMiddleware())->validate();

        // 2. LIRE ET VALIDER LE JSON
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['code' => 'INVALID_JSON', 'message' => 'Corps JSON invalide']]);
            return;
        }

        // Extraire participation_id du JSON
        $participationId = $json['participation_id'] ?? null;

        // Valider que participation_id est un entier > 0
        if (!is_int($participationId) || $participationId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['code' => 'INVALID_PARTICIPATION_ID', 'message' => 'participation_id doit être un entier positif']]);
            return;
        }

        // 3. LOGIQUE MÉTIER
        try {
            $db = DatabaseFactory::getConnection();
            $service = new ParticipationService(
                new ParticipationRepository($db),
                new TrajetRepository($db),
                new UserRepository($db)
            );

            // Appeler le service et retourner le résultat
            $result = $service->validateParticipation($participationId);
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $result]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['code' => 'OPERATION_FAILED', 'message' => $e->getMessage()]]);
        }
    }

    /**
     * Confirmer une participation (2ème confirmation finale)
     * POST /api/participations/confirm
     * Body: { participation_id }
     */
    public static function confirmParticipation(): void
    {
        // Définir le header avant toute sortie
        header('Content-Type: application/json');

        // 1. AUTHENTIFICATION
        try {
            $middleware = new AuthMiddleware();
            $userData = $middleware->authenticate();
            $userId = (int)$userData['user_id'];
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Authentification requise. Veuillez vous connecter.']]);
            return;
        }

        // 1bis. CSRF
        (new CsrfMiddleware())->validate();

        // 2. LIRE ET VALIDER LE JSON
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['code' => 'INVALID_JSON', 'message' => 'Corps JSON invalide']]);
            return;
        }

        // Extraire participation_id du JSON
        $participationId = $json['participation_id'] ?? null;

        // Valider que participation_id est un entier > 0
        if (!is_int($participationId) || $participationId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['code' => 'INVALID_PARTICIPATION_ID', 'message' => 'participation_id doit être un entier positif']]);
            return;
        }

        // 3. LOGIQUE MÉTIER
        try {
            $db = DatabaseFactory::getConnection();
            $service = new ParticipationService(
                new ParticipationRepository($db),
                new TrajetRepository($db),
                new UserRepository($db)
            );

            // Appeler le service et retourner le résultat
            $result = $service->confirmParticipation($participationId);
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $result]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['code' => 'OPERATION_FAILED', 'message' => $e->getMessage()]]);
        }
    }

    /**
     * Endpoint : POST /api/trajets
     * Crée un nouveau trajet
     */
    public static function create(): void
    {
        header('Content-Type: application/json');

        // 1. Vérifier l'authentification
        try {
            $middleware = new AuthMiddleware();
            $userData = $middleware->authenticate();
            $userId = (int)$userData['user_id'];
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Authentification requise.']]);
            return;
        }

        // CSRF
        (new CsrfMiddleware())->validate();

        // 2. Récupérer les données JSON
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['code' => 'INVALID_JSON', 'message' => 'Corps JSON invalide']]);
            return;
        }

        try {
            $db = DatabaseFactory::getConnection();
            
            // 3. Vérifier que l'utilisateur est chauffeur
            $userRepo = new UserRepository($db);
            $user = $userRepo->findById($userId);
            
            if (!$user || !in_array($user->role, ['chauffeur', 'chauffeur_passager'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Seuls les chauffeurs peuvent créer des trajets']]);
                return;
            }

            // 4. Vérifier que le véhicule appartient au chauffeur
            if (!empty($data['voiture_id'])) {
                $vehicleRepo = new \App\Repositories\VehicleRepository($db);
                $vehicles = $vehicleRepo->findByUserId($userId);
                $vehicleIds = array_map(fn($v) => $v->id, $vehicles);
                
                if (!in_array((int)$data['voiture_id'], $vehicleIds)) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Ce véhicule ne vous appartient pas']]);
                    return;
                }
            }

            // 5. Créer le trajet via le service
            $service = new TripService(new TrajetRepository($db));
            $trajet = $service->createTrip($data, $userId);

            // 6. Retourner le trajet créé avec message d'avertissement
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'data' => $trajet,
                'message' => 'Trajet créé avec succès. Rappel : 2 crédits seront prélevés par la plateforme pour chaque participation.'
            ]);

        } catch (\App\Validators\Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => $e->getMessage()]]);
        }
    }

    /**
     * Liste les prochains trajets du chauffeur connecté
     */
    public static function myTrips(): void
    {
        header('Content-Type: application/json');

        // Authentifier l'utilisateur
        try {
            $middleware = new \App\Middleware\AuthMiddleware();
            $userData = $middleware->authenticate();
            $userId = (int)$userData['user_id'];
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Authentification requise.']]);
            return;
        }

        try {
            $db = \App\Factories\DatabaseFactory::getConnection();
            $repo = new \App\Repositories\TrajetRepository($db);
            $trajets = $repo->getTrajetsByUserId($userId);

            // Ne garder que les trajets à venir
            $nowDate = new \DateTime('now');
            $upcoming = array_values(array_filter($trajets, function ($t) use ($nowDate) {
                try {
                    $dt = new \DateTime($t->dateDepart . ' ' . ($t->heureDepart ?: '00:00:00'));
                    return $dt >= $nowDate; 
                } catch (\Throwable $e) {
                    return true; // en doute, on affiche
                }
            }));

            // Mapper vers arrays simples
            $data = array_map(fn($t) => $t->toArray(), $upcoming);

            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => $e->getMessage()]]);
        }
    }
}
