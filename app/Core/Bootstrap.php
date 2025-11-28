<?php
namespace App\Core;

use App\Factories\AvisRepositoryFactory;
use App\Repositories\ResilientAvisRepository; // pour les hints
use App\Models\Avis;
use App\Core\Env;
use App\Factories\DatabaseFactory;
use App\Repositories\UserRepository;
use App\Repositories\TrajetRepository;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\CookieManager;
use App\Middleware\AuthMiddleware; 

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
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (str_starts_with($uri, '/api/')) {
            $this->handleApi($uri);
            return;
        }
        // Route requests to frontend pages
        $this->servePage($uri);
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

    private function handleApi(string $uri): void
    {
        header('Content-Type: application/json');
        if ($uri === '/api/health') {
            echo json_encode(['success' => true, 'data' => 'ok']);
            return;
        }
        if (str_starts_with($uri, '/api/auth')) {
            $this->handleAuth($uri);
            return;
        }
        if (str_starts_with($uri, '/api/avis')) {
            $this->handleAvis($uri);
            return;
        }
        if (str_starts_with($uri, '/api/trajets')) {
            $this->handleTrajets($uri);
            return;
        }
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Endpoint']]);
    }

    private function repo(): ResilientAvisRepository
    {
        return AvisRepositoryFactory::get();
    }

    private function handleAuth(string $uri): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        if ($method === 'POST' && $uri === '/api/auth/signup') {
            $this->handleSignup();
            return;
        }

        if ($method === 'POST' && $uri === '/api/auth/login') {
            $this->handleLogin();
            return;
        }

        if ($method === 'POST' && $uri === '/api/auth/logout') {
            $this->handleLogout();
            return;
        }

        http_response_code(404);
        echo json_encode(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Endpoint auth']]);
    }

    private function handleSignup(): void
    {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);

        if (!is_array($json)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['code' => 'INVALID_JSON', 'message' => 'Corps JSON invalide']]);
            return;
        }

        $pseudo = trim((string)($json['pseudo'] ?? ''));
        $email = trim((string)($json['email'] ?? ''));
        $password = $json['password'] ?? '';

        if (!$pseudo || !$email || !$password) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['code' => 'MISSING_FIELDS', 'message' => 'Pseudo, email et mot de passe requis']]);
            return;
        }

        try {
            $db = DatabaseFactory::getConnection();
            $userRepository = new UserRepository($db);
            $authService = new AuthService($userRepository);

            $result = $authService->signup($pseudo, $email, $password);

            http_response_code(201);
            echo json_encode(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => ['code' => 'SIGNUP_FAILED', 'message' => $e->getMessage()]]);
        }
    }

    private function handleLogin(): void
    {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);

        if (!is_array($json)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['code' => 'INVALID_JSON', 'message' => 'Corps JSON invalide']]);
            return;
        }

        $emailOrPseudo = trim((string)($json['email'] ?? ''));
        $password = $json['password'] ?? '';

        if (!$emailOrPseudo || !$password) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['code' => 'MISSING_FIELDS', 'message' => 'Email/Pseudo et mot de passe requis']]);
            return;
        }

        try {
            $db = DatabaseFactory::getConnection();
            $userRepository = new UserRepository($db);
            $authService = new AuthService($userRepository);

            $userData = $authService->login($emailOrPseudo, $password);

            echo json_encode([
                'success' => true,
                'data' => $userData,
                'redirect' => '/'
            ]);
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => ['code' => 'LOGIN_FAILED', 'message' => $e->getMessage()]]);
        }
    }

    private function handleLogout(): void
    {
        try {
            $db = DatabaseFactory::getConnection();
            $userRepository = new UserRepository($db);
            $authService = new AuthService($userRepository);

            $authService->logout();

            echo json_encode(['success' => true, 'data' => ['message' => 'Déconnexion réussie']]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['code' => 'LOGOUT_FAILED', 'message' => $e->getMessage()]]);
        }
    }

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

    private function handleAvis(string $uri): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        parse_str($_SERVER['QUERY_STRING'] ?? '', $query);

        if ($method === 'GET' && $uri === '/api/avis') {
            $rideId = isset($query['covoiturage_id']) ? (int)$query['covoiturage_id'] : 0;
            if ($rideId <= 0) {
                http_response_code(400);
                echo json_encode(['success'=>false,'error'=>['code'=>'INVALID_PARAM','message'=>'Paramètre covoiturage_id requis']]);
                return;
            }
            $avis = $this->repo()->listForRide($rideId);
            $payload = array_map(fn(Avis $a) => [
                'covoiturage_id' => $a->rideId,
                'utilisateur_id' => $a->userId,
                'note' => $a->rating,
                'commentaire' => $a->comment,
                'date_creation' => $a->createdAt->format('Y-m-d H:i:s')
            ], $avis);
            echo json_encode(['success'=>true,'data'=>[
                'items'=>$payload,
                'count'=>count($payload),
                'average'=>$this->repo()->averageForRide($rideId)
            ]]);
            return;
        }

        if ($method === 'GET' && $uri === '/api/avis/stats') {
            $rideId = isset($query['covoiturage_id']) ? (int)$query['covoiturage_id'] : 0;
            if ($rideId <= 0) {
                http_response_code(400);
                echo json_encode(['success'=>false,'error'=>['code'=>'INVALID_PARAM','message'=>'Paramètre covoiturage_id requis']]);
                return;
            }
            $avg = $this->repo()->averageForRide($rideId);
            $count = count($this->repo()->listForRide($rideId)); // simple pour MVP
            echo json_encode(['success'=>true,'data'=>['covoiturage_id'=>$rideId,'average'=>$avg,'count'=>$count]]);
            return;
        }

        if ($method === 'POST' && $uri === '/api/avis') {
            $raw = file_get_contents('php://input');
            $json = json_decode($raw, true);
            if (!is_array($json)) {
                http_response_code(400);
                echo json_encode(['success'=>false,'error'=>['code'=>'INVALID_JSON','message'=>'Corps JSON invalide']]);
                return;
            }
            $errors = [];
            $rideId = (int)($json['covoiturage_id'] ?? 0); if ($rideId<=0) $errors[]='covoiturage_id invalide';
            $userId = (int)($json['utilisateur_id'] ?? 0); if ($userId<=0) $errors[]='utilisateur_id invalide';
            $rating = (int)($json['note'] ?? 0); if ($rating<1 || $rating>5) $errors[]='note doit être entre 1 et 5';
            $comment = isset($json['commentaire']) ? trim((string)$json['commentaire']) : null;
            if ($errors) {
                http_response_code(422);
                echo json_encode(['success'=>false,'error'=>['code'=>'VALIDATION_FAILED','messages'=>$errors]]);
                return;
            }
            $avis = new Avis($rideId,$userId,$rating,$comment);
            $ok = $this->repo()->add($avis);
            if ($ok) {
                http_response_code(201);
                echo json_encode(['success'=>true,'data'=>['message'=>'Créé','note'=>$rating]]);
            } else {
                http_response_code(500);
                echo json_encode(['success'=>false,'error'=>['code'=>'PERSIST_FAILED','message'=>'Échec enregistrement avis']]);
            }
            return;
        }

        http_response_code(404);
        echo json_encode(['success'=>false,'error'=>['code'=>'NOT_FOUND','message'=>'Endpoint avis']]);
    }

    private function handleTrajets(string $uri): void 
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        parse_str($_SERVER['QUERY_STRING'] ?? '', $query);

        // 1. Vérifier que c'est une requête GET sur /api/trajets
        if ($method === 'GET' && $uri === '/api/trajets') {
            
            // 2. Récupérer et valider les paramètres
            $departure = trim((string)($query['departure'] ?? ''));
          $arrival = trim((string)($query['arrival'] ?? ''));
            $date = trim((string)($query['date'] ?? ''));
            
            // 3. Vérifier qu'ils ne sont pas vides
            if (!$departure || !$arrival || !$date) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => ['code' => 'MISSING_FIELDS', 'message' => 'Paramètres departure, arrival et date requis']]);
                return;
            }
            
            // 4. Valider le format de la date (YYYY-MM-DD)
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => ['code' => 'INVALID_DATE', 'message' => 'Format de date invalide (YYYY-MM-DD)']]);
                return;
            }
            
            // 5. Appeler le repository pour chercher les trajets
            try {
                $db = DatabaseFactory::getConnection();
                $trajetRepository = new TrajetRepository($db);

                // Chercher les trajets
                $trajets = $trajetRepository->searchTrajets($departure, $arrival, $date);

                // Formater la réponse
                $payload = array_map(fn($trajet) => [
                    'covoiturage_id' => $trajet['covoiturage_id'],
                    'date_depart' => $trajet['date_depart'],
                    'heure_depart' => $trajet['heure_depart'],
                    'lieu_depart' => $trajet['lieu_depart'],
                    'heure_arrivee' => $trajet['heure_arrivee'],
                    'lieu_arrivee' => $trajet['lieu_arrivee'],
                    'nb_places' => $trajet['nb_places'],
                    'prix_personne' => $trajet['prix_personne'],
                    'est_ecologique' => $trajet['est_ecologique'],
                    'conducteur_pseudo' => $trajet['pseudo'],
                    'conducteur_id' => $trajet['utilisateur_id']
                ], $trajets);

                // 6. Retourner le résultat en JSON
                http_response_code(200);
                echo json_encode(['success' => true, 'data' => ['items' => $payload, 'count' => count($payload)]]);
            } catch (\Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => ['code' => 'SEARCH_FAILED', 'message' => $e->getMessage()]]);
            }
            
            return;
        }
    
        // Pas encore implémenté pour les autres méthodes
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Endpoint trajets']]);
    }
}
