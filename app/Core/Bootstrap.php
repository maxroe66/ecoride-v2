<?php

namespace App\Core;

use App\Core\Env;
use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\AvisController;
use App\Controllers\TrajetController;
use App\Controllers\UserController;
use App\Middleware\AuthMiddleware;

// Nettoyage: suppression des anciens imports legacy non utilisés

class Bootstrap
{
    public function __construct()
    {
        Env::load();
        // Démarrer la session pour l'accès aux données utilisateur
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function run(): void
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        // Étape 1 migration: on route uniquement /api/auth/* via Router.
        if (str_starts_with($uri, '/api/')) {
            $this->routeApiWithRouter();
            return;
        }

        // Frontend pages
        $this->servePage($uri);
    }

    /**
     * Enregistre et dispatch les routes d'authentification via le nouveau Router.
     */
    private function routeApiWithRouter(): void
    {
        $router = new Router();
        // Health
        $router->add('GET', '/api/health', function () {
            echo json_encode(['success' => true,'data' => 'ok']);
        });
        // Auth
        $router->add('POST', '/api/auth/signup', [AuthController::class, 'signup']);
        $router->add('POST', '/api/auth/login', [AuthController::class, 'login']);
        $router->add('POST', '/api/auth/logout', [AuthController::class, 'logout']);
        // CSRF token (après auth pour usage côté front)
        $router->add('GET', '/api/csrf-token', [AuthController::class, 'csrf']);
        // Avis
        $router->add('GET', '/api/avis', [AvisController::class, 'list']);
        $router->add('GET', '/api/avis/stats', [AvisController::class, 'stats']);
        $router->add('POST', '/api/avis', [AvisController::class, 'create']);
        // Trajets
        $router->add('GET', '/api/trajets', [TrajetController::class, 'search']);
        $router->add('POST', '/api/trajets', [TrajetController::class, 'create']);
        $router->add('GET', '/api/trajets/detail', [TrajetController::class, 'show']);
        // Alias corrigé: chemin attendu par le frontend `/api/trajets/suggestions`
        $router->add('GET', '/api/trajets/suggestions', [TrajetController::class, 'suggestions']);
        // Conserver l'ancien alias si déjà utilisé quelque part
        $router->add('GET', '/api/trajets-suggestions', [TrajetController::class, 'suggestions']);
        // Mes trajets (chauffeur connecté)
        $router->add('GET', '/api/user/trajets', [TrajetController::class, 'myTrips']);

        // Participations (authentification gérée à l'intérieur du contrôleur)
        $router->add('POST', '/api/participations/request', [TrajetController::class, 'requestParticipation']);
        $router->add('POST', '/api/participations/validate', [TrajetController::class, 'validateParticipation']);
        $router->add('POST', '/api/participations/confirm', [TrajetController::class, 'confirmParticipation']);

        // User Profile (US8)
        $router->add('GET', '/api/user/vehicles', [UserController::class, 'getVehicles']);
        $router->add('POST', '/api/user/vehicles', [UserController::class, 'addVehicle']);
        $router->add('DELETE', '/api/user/vehicles', [UserController::class, 'deleteVehicle']);
        $router->add('GET', '/api/user/preferences', [UserController::class, 'getPreferences']);
        $router->add('PUT', '/api/user/profile', [UserController::class, 'updateProfile']);

        header('Content-Type: application/json');
        if ($router->dispatch()) {
            return;
        }
        http_response_code(404);
        echo json_encode(['success' => false,'error' => ['code' => 'NOT_FOUND','message' => 'Endpoint']]);
    }

    private function servePage(string $uri): void
    {
        // Default to accueil for root
        if ($uri === '/' || $uri === '') {
            $page = __DIR__ . '/../../frontend/pages/accueil.php';
        } else {
            // Build page path from URI
            // e.g., /rides -> /frontend/pages/rides.php
            $pageName = trim($uri, '/');
            $page = __DIR__ . "/../../frontend/pages/{$pageName}.php";
        }

        if (is_file($page)) {
            include $page;
        } else {
            http_response_code(404);
            echo '<h1>404 - Page non trouvée</h1>';
        }
    }

    // Anciennes méthodes legacy retirées après migration.

    /**
     * Valide l'authentification avec JWT
     * Retourne les données utilisateur ou lance une exception 401
     */
    private function requireAuth(): array
    {
        try {
            $middleware = new AuthMiddleware();
            return $middleware->authenticate();
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => $e->getMessage()]]);
            exit();
        }
    }
}
