<?php

namespace App\Controllers;

use App\Factories\ServiceLocator as SL;
use App\Services\CsrfService;
use App\DTO\LoginRequest;
use App\DTO\SignupRequest;
use App\Validators\LoginValidator;
use App\Validators\SignupValidator;
use App\Exceptions\ValidationException;
use App\Core\Request;
use App\Core\Response;

/**
 * Contrôleur d'authentification
 * Architecture: Request → DTO → Validator → Service → Response
 * Garantit invariance structure JSON pour frontend.
 */
class AuthController
{
    /**
     * Inscription d'un nouvel utilisateur
     * POST /api/auth/signup
     * Body: { pseudo, email, password }
     */
    public static function signup(Request $req): void
    {
        try {
            // 1. DTO : Transformer tableau → objet typé + validation structurelle
            $signupDto = SignupRequest::fromArray($req->getJsonBody());
            
            // 2. Validator : Validation métier (règles business)
            SignupValidator::validate($signupDto);
            
            // 3. Service : Logique métier (vérifier unicité email, hasher password, etc.)
            $auth = SL::getAuthService();
            $userData = $auth->signup($signupDto->pseudo, $signupDto->email, $signupDto->password);
            
            // 4. Response : Retourner succès
            Response::json(201, ['success' => true, 'data' => $userData]);
            
        } catch (ValidationException $e) {
            // Erreurs de validation métier (multiples messages)
            Response::json(422, [
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'messages' => $e->errors
                ]
            ]);
        } catch (\InvalidArgumentException $e) {
            // Erreur DTO (champs manquants)
            Response::json(400, [
                'success' => false,
                'error' => [
                    'code' => 'MISSING_FIELDS',
                    'message' => $e->getMessage()
                ]
            ]);
        } catch (\Exception $e) {
            // Erreur service (email déjà utilisé, etc.)
            Response::json(422, [
                'success' => false,
                'error' => [
                    'code' => 'SIGNUP_FAILED',
                    'message' => $e->getMessage()
                ]
            ]);
        }
    }

    /**
     * Connexion d'un utilisateur
     * POST /api/auth/login
     * Body: { email, password }
     */
    public static function login(Request $req): void
    {
        try {
            // 1. DTO : Transformer tableau → objet typé + validation structurelle
            $loginDto = LoginRequest::fromArray($req->getJsonBody());
            
            // 2. Validator : Validation métier (format email, longueur pseudo, etc.)
            LoginValidator::validate($loginDto);
            
            // 3. Service : Logique métier (vérifier credentials, générer JWT)
            $auth = SL::getAuthService();
            $userData = $auth->login($loginDto->emailOrPseudo, $loginDto->password);
            
            // 4. Response : Retourner succès avec token
            Response::json(200, [
                'success' => true,
                'data' => $userData,
                'redirect' => '/'
            ]);
            
        } catch (ValidationException $e) {
            // Erreurs de validation métier
            Response::json(400, [
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'messages' => $e->errors
                ]
            ]);
        } catch (\InvalidArgumentException $e) {
            // Erreur DTO (champs manquants)
            Response::json(400, [
                'success' => false,
                'error' => [
                    'code' => 'MISSING_FIELDS',
                    'message' => $e->getMessage()
                ]
            ]);
        } catch (\Exception $e) {
            // Erreur service (credentials invalides)
            Response::json(401, [
                'success' => false,
                'error' => [
                    'code' => 'LOGIN_FAILED',
                    'message' => $e->getMessage()
                ]
            ]);
        }
    }

    public static function logout(Request $req): void
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
    public static function csrf(Request $req): void
    {
        $token = CsrfService::getToken();
        Response::json(200, ['success' => true, 'data' => ['csrfToken' => $token]]);
    }
}
