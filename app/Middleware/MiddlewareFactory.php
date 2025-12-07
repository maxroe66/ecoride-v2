<?php

namespace App\Middleware;

use App\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;

/**
 * Middlewares réutilisables pour le Router
 * Ces fonctions retournent des callables qui reçoivent Request en paramètre
 */
class MiddlewareFactory
{
    /**
     * Middleware d'authentification
     * Vérifie le JWT et stocke les données utilisateur dans Request->authUser
     * 
     * @return callable
     */
    public static function auth(): callable
    {
        return function(Request $req) {
            try {
                $middleware = new AuthMiddleware();
                $userData = $middleware->authenticate();
                
                // Stocker dans l'objet Request (moderne)
                $req->setAuthUser($userData);
                
                // Conserver aussi dans $_REQUEST pour compatibilité avec code existant
                $_REQUEST['_auth_user'] = $userData;
            } catch (\Exception $e) {
                Response::json(401, ['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Authentification requise']]);
                exit;
            }
        };
    }

    /**
     * Middleware CSRF
     * Vérifie le token CSRF pour les requêtes modifiantes (POST, PUT, DELETE)
     * 
     * @return callable
     */
    public static function csrf(): callable
    {
        return function(Request $req) {
            try {
                (new CsrfMiddleware())->validate();
            } catch (\Exception $e) {
                Response::json(403, ['success' => false, 'error' => ['code' => 'CSRF_INVALID', 'message' => 'Token CSRF invalide']]);
                exit;
            }
        };
    }

    /**
     * Middleware combiné Auth + CSRF
     * Pratique pour les routes POST/PUT/DELETE protégées
     * 
     * @return array
     */
    public static function authAndCsrf(): array
    {
        return [
            self::auth(),
            self::csrf()
        ];
    }
}
