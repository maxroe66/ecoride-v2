<?php

namespace App\Controllers;

use App\Factories\DatabaseFactory;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\CsrfService;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Validators\QueryValidator;

/**
 * Contrôleur d'authentification
 * Garantit invariance structure JSON pour frontend.
 */
class AuthController
{
    public static function signup(): void
    {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            http_response_code(400);
            echo json_encode(['success' => false,'error' => ['code' => 'INVALID_JSON','message' => 'Corps JSON invalide']]);
            return;
        }
        // Validation centralisée
        try {
            $data = QueryValidator::validateSignup($json);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false,'error' => ['code' => 'MISSING_FIELDS','message' => $e->getMessage()]]);
            return;
        }
        try {
            $db = DatabaseFactory::getConnection();
            $repo = new UserRepository($db);
            $auth = new AuthService($repo);
            $data = $auth->signup($data['pseudo'],$data['email'],$data['password']);
            http_response_code(201);
            echo json_encode(['success' => true,'data' => $data]);
        } catch (\Exception $e) {
            http_response_code(422);
            echo json_encode(['success' => false,'error' => ['code' => 'SIGNUP_FAILED','message' => $e->getMessage()]]);
        }
    }

    public static function login(): void
    {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            http_response_code(400);
            echo json_encode(['success' => false,'error' => ['code' => 'INVALID_JSON','message' => 'Corps JSON invalide']]);
            return;
        }
        // Validation centralisée
        try {
            $data = QueryValidator::validateLogin($json);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false,'error' => ['code' => 'MISSING_FIELDS','message' => $e->getMessage()]]);
            return;
        }
        try {
            $db = DatabaseFactory::getConnection();
            $repo = new UserRepository($db);
            $auth = new AuthService($repo);
            $userData = $auth->login($data['emailOrPseudo'],$data['password']);
            echo json_encode(['success' => true,'data' => $userData,'redirect' => '/']);
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false,'error' => ['code' => 'LOGIN_FAILED','message' => $e->getMessage()]]);
        }
    }

    public static function logout(): void
    {
        // Exiger une session authentifiée puis un jeton CSRF valide
        try {
            $authMw = new AuthMiddleware();
            $authMw->authenticate();
            (new CsrfMiddleware())->validate();

            $db = DatabaseFactory::getConnection();
            $repo = new UserRepository($db);
            $auth = new AuthService($repo);
            $auth->logout();
            echo json_encode(['success' => true,'data' => ['message' => 'Déconnexion réussie']]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false,'error' => ['code' => 'LOGOUT_FAILED','message' => $e->getMessage()]]);
        }
    }

    /**
     * Endpoint: GET /api/csrf-token
     * Retourne un token CSRF associé à la session authentifiée
     */
    public static function csrf(): void
    {
        header('Content-Type: application/json');
        try {
            $authMw = new AuthMiddleware();
            $authMw->authenticate();
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Authentification requise']]);
            return;
        }

        $token = CsrfService::getToken();
        echo json_encode(['success' => true, 'data' => ['csrfToken' => $token]]);
    }
}
