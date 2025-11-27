<?php
namespace App\Middleware;

use App\Services\JwtService;
use App\Services\CookieManager;
use Exception;

class AuthMiddleware
{
    private JwtService $jwtService;
    private CookieManager $cookieManager;

    public function __construct(?JwtService $jwtService = null, ?CookieManager $cookieManager = null)
    {
        $this->jwtService = $jwtService ?? new JwtService();
        $this->cookieManager = $cookieManager ?? new CookieManager();
    }

    /**
     * Valide l'authentification de l'utilisateur
     * 
     * Cherche le token dans:
     * 1. Cookie (ecoride_token) - priorité haute
     * 2. Header Authorization: "Bearer <token>" - priorité moyenne
     * 3. Query parameter ?token=xxx - priorité basse
     * 
     * @return array Données utilisateur du token
     * @throws Exception si pas de token ou token invalide
     */
    public function authenticate(): array
    {
        // Récupérer le token
        $token = $this->getToken();

        if (!$token) {
            throw new Exception('Authentification requise. Token manquant.', 401);
        }

        // Valider le token
        try {
            $payload = $this->jwtService->validate($token);
        } catch (Exception $e) {
            throw new Exception('Token invalide ou expiré: ' . $e->getMessage(), 401);
        }

        return $payload;
    }

    /**
     * Récupère le token JWT de plusieurs sources
     * Priorité: Cookie > Header Authorization > Query Parameter
     */
    private function getToken(): ?string
    {
        // 1. Vérifier le cookie
        if ($this->cookieManager->hasToken()) {
            return $this->cookieManager->getToken();
        }

        // 2. Vérifier le header Authorization
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if ($authHeader) {
            $token = JwtService::getTokenFromHeader($authHeader);
            if ($token) {
                return $token;
            }
        }

        // 3. Vérifier le query parameter (moins sûr, pour API mobile)
        if (isset($_GET['token'])) {
            return $_GET['token'];
        }

        return null;
    }

    /**
     * Vérifie si l'utilisateur est authentifié (sans lever d'exception)
     */
    public function isAuthenticated(): bool
    {
        try {
            $this->authenticate();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Récupère les données utilisateur s'il est authentifié
     */
    public function getUser(): ?array
    {
        try {
            return $this->authenticate();
        } catch (Exception $e) {
            return null;
        }
    }
}
