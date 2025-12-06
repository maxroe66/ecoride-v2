<?php

namespace App\Controllers;

use App\Factories\DatabaseFactory;
use App\Factories\ServiceLocator as SL;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\CsrfService;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Validators\QueryValidator;
use App\Core\Response;

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
        
        try {
            QueryValidator::validateJsonInput($json);
            $data = QueryValidator::validateSignup($json);
        } catch (\Exception $e) {
            Response::json(400, ['success' => false,'error' => ['code' => 'MISSING_FIELDS','message' => $e->getMessage()]]);
            return;
        }
        try {
            $auth = SL::getAuthService();
            $data = $auth->signup($data['pseudo'],$data['email'],$data['password']);
            Response::json(201, ['success' => true,'data' => $data]);
        } catch (\Exception $e) {
            Response::json(422, ['success' => false,'error' => ['code' => 'SIGNUP_FAILED','message' => $e->getMessage()]]);
        }
    }

    public static function login(): void
    {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        
        try {
            QueryValidator::validateJsonInput($json);
            $data = QueryValidator::validateLogin($json);
        } catch (\Exception $e) {
            Response::json(400, ['success' => false,'error' => ['code' => 'VALIDATION_ERROR','message' => $e->getMessage()]]);
            return;
        }
        try {
            $auth = SL::getAuthService();
            $userData = $auth->login($data['emailOrPseudo'],$data['password']);
            Response::json(200, ['success' => true,'data' => $userData,'redirect' => '/']);
        } catch (\Exception $e) {
            Response::json(401, ['success' => false,'error' => ['code' => 'LOGIN_FAILED','message' => $e->getMessage()]]);
        }
    }

    public static function logout(): void
    {
        try {
            $auth = SL::getAuthService();
            $auth->logout();
            Response::json(200, ['success' => true,'data' => ['message' => 'Déconnexion réussie']]);
        } catch (\Exception $e) {
            Response::json(500, ['success' => false,'error' => ['code' => 'LOGOUT_FAILED','message' => $e->getMessage()]]);
        }
    }

    /**
     * Endpoint: GET /api/csrf-token
     * Retourne un token CSRF associé à la session authentifiée
     */
    public static function csrf(): void
    {
        $token = CsrfService::getToken();
        Response::json(200, ['success' => true, 'data' => ['csrfToken' => $token]]);
    }
}
