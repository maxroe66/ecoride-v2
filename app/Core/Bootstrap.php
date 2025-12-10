<?php

namespace App\Core;

use App\Core\Env;
use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\AvisController;
use App\Controllers\TrajetController;
use App\Controllers\UserController;
use App\Controllers\HistoryController;
use App\Controllers\ParticipationController;
use App\Controllers\EmployeeController;
use App\Controllers\AdminController;
use App\Middleware\MiddlewareFactory as MW;
use App\Middleware\EmployeeMiddleware;
use App\Middleware\AdminMiddleware;

// Nettoyage: suppression des anciens imports legacy non utilisés

class Bootstrap
{
    public function __construct()
    {
        // Désactiver l'affichage HTML des erreurs pour éviter de casser les réponses JSON
        // Les erreurs seront loguées côté serveur mais non affichées dans les réponses API.
        ini_set('display_errors', '0');
        ini_set('html_errors', '0');
        // Conserver le reporting complet pour les logs
        error_reporting(E_ALL);

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
        $router->add('GET', '/api/health', function (Request $req) {
            Response::json(200, ['success' => true, 'data' => 'ok']);
        });
        // Auth
        $router->add('POST', '/api/auth/signup', [AuthController::class, 'signup']);
        $router->add('POST', '/api/auth/login', [AuthController::class, 'login']);
        $router->add('POST', '/api/auth/logout', [AuthController::class, 'logout'], MW::authAndCsrf());
        // CSRF token (après auth pour usage côté front)
        $router->add('GET', '/api/csrf-token', [AuthController::class, 'csrf'], [MW::auth()]);
        // Avis
        $router->add('GET', '/api/avis', [AvisController::class, 'list']);
        $router->add('GET', '/api/avis/stats', [AvisController::class, 'stats']);
        $router->add('POST', '/api/avis', [AvisController::class, 'create'], MW::authAndCsrf());
        // Trajets
        $router->add('GET', '/api/trajets', [TrajetController::class, 'search']);
        $router->add('POST', '/api/trajets', [TrajetController::class, 'create'], MW::authAndCsrf());
        $router->add('GET', '/api/trajets/detail', [TrajetController::class, 'show'], [MW::authOptional()]);
        $router->add('GET', '/api/trajets/suggestions', [TrajetController::class, 'suggestions']);
        // Mes trajets (chauffeur connecté)
        $router->add('GET', '/api/user/trajets', [TrajetController::class, 'myTrips'], [MW::auth()]);

        // Participations - Gestion des demandes et confirmations
        $router->add('POST', '/api/participations/request', [ParticipationController::class, 'requestParticipation'], MW::authAndCsrf());
        $router->add('POST', '/api/participations/validate', [ParticipationController::class, 'validateParticipation'], MW::authAndCsrf());
        $router->add('POST', '/api/participations/confirm', [ParticipationController::class, 'confirmParticipation'], MW::authAndCsrf());

        // Historique (US10)
        $router->add('GET', '/api/historique/trajets', [HistoryController::class, 'getUserHistory'], [MW::auth()]);
        // Variante filtrée par statut via chemin dédié pour éviter conflit de même path
        $router->add('GET', '/api/historique/trajets/filtre', [HistoryController::class, 'getHistoryByStatus'], [MW::auth()]);

        // User Profile (US8)
        $router->add('GET', '/api/user/vehicles', [UserController::class, 'getVehicles'], [MW::auth()]);
        $router->add('POST', '/api/user/vehicles', [UserController::class, 'addVehicle'], MW::authAndCsrf());
        $router->add('DELETE', '/api/user/vehicles', [UserController::class, 'deleteVehicle'], MW::authAndCsrf());
        $router->add('GET', '/api/user/preferences', [UserController::class, 'getPreferences'], [MW::auth()]);
        // Crédit utilisateur (US10)
        $router->add('GET', '/api/user/credit', [UserController::class, 'getCredit'], [MW::auth()]);
        $router->add('GET', '/api/user/credit/operations', [UserController::class, 'getCreditOperations'], [MW::auth()]);
        $router->add('PUT', '/api/user/profile', [UserController::class, 'updateProfile'], MW::authAndCsrf());

        // Historique des covoiturages (US10)
        $router->add('GET', '/api/historique/trajets', [HistoryController::class, 'getUserHistory'], [MW::auth()]);

        // Annulation de trajets (US10) - routes dynamiques paraméttriques
        $router->add('POST', '/api/trajets/{id}/annuler', [TrajetController::class, 'cancelTrip'], MW::authAndCsrf());
        $router->add('POST', '/api/participations/{id}/annuler', [ParticipationController::class, 'cancelParticipation'], MW::authAndCsrf());

        // Démarrer et arrêter un covoiturage (US11)
        $router->add('POST', '/api/trajets/{id}/start', [TrajetController::class, 'start'], MW::authAndCsrf());
        $router->add('PUT', '/api/trajets/{id}/end', [TrajetController::class, 'end'], MW::authAndCsrf());
        
        // Validation de participation et signalement de problème (US11)
        $router->add('POST', '/api/participations/{id}/validate', [ParticipationController::class, 'validateParticipationAtEnd'], MW::authAndCsrf());
        $router->add('POST', '/api/participations/{id}/problem', [ParticipationController::class, 'reportProblem'], MW::authAndCsrf());

        // Espace employé (US12) - Modération des avis et gestion des incidents
        $authAndEmployeeMiddleware = [MW::auth(), EmployeeMiddleware::check(), MW::csrf()];
        $router->add('GET', '/api/employee/reviews/pending', [EmployeeController::class, 'getPendingReviews'], [MW::auth(), EmployeeMiddleware::check()]);
        $router->add('POST', '/api/employee/reviews/{slug}/moderation', [EmployeeController::class, 'moderateReview'], $authAndEmployeeMiddleware);
        $router->add('GET', '/api/employee/incidents', [EmployeeController::class, 'getIncidents'], [MW::auth(), EmployeeMiddleware::check()]);
        $router->add('GET', '/api/employee/incidents/{id}', [EmployeeController::class, 'getIncidentDetail'], [MW::auth(), EmployeeMiddleware::check()]);
        $router->add('POST', '/api/employee/incidents/{id}/release', [EmployeeController::class, 'releaseIncidentFunds'], $authAndEmployeeMiddleware);

        // Espace administrateur (US13) - Gestion employés, stats, suspension comptes
        $authAndAdminMiddleware = [MW::auth(), AdminMiddleware::check(), MW::csrf()];
        $router->add('GET', '/api/admin/employees', [AdminController::class, 'listEmployees'], [MW::auth(), AdminMiddleware::check()]);
        $router->add('POST', '/api/admin/employees', [AdminController::class, 'createEmployee'], $authAndAdminMiddleware);
        $router->add('GET', '/api/admin/stats/trips-per-day', [AdminController::class, 'getTripsPerDay'], [MW::auth(), AdminMiddleware::check()]);
        $router->add('GET', '/api/admin/stats/credits-per-day', [AdminController::class, 'getCreditsPerDay'], [MW::auth(), AdminMiddleware::check()]);
        $router->add('GET', '/api/admin/stats/total-credits', [AdminController::class, 'getTotalCredits'], [MW::auth(), AdminMiddleware::check()]);
        $router->add('POST', '/api/admin/users/{id}/suspend', [AdminController::class, 'suspendUser'], $authAndAdminMiddleware);
        $router->add('POST', '/api/admin/users/{id}/unsuspend', [AdminController::class, 'unsuspendUser'], $authAndAdminMiddleware);
        $router->add('GET', '/api/admin/users', [AdminController::class, 'listUsers'], [MW::auth(), AdminMiddleware::check()]);

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
}
