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
     * Cherche le token uniquement dans le cookie sécurisé (ecoride_token).
     *
     * @return array Données utilisateur du token
     * @throws Exception si pas de token ou token invalide
     */
    public function authenticate(): array
    {
        $token = $this->getToken();

        if (!$token) {
            throw new Exception('Authentification requise. Token manquant.', 401);
        }

        // Les exceptions de JwtService remontent naturellement
        return $this->jwtService->validate($token);
    }

    /**
     * Récupère le token JWT à partir du cookie sécurisé
     */
    private function getToken(): ?string
    {
        if ($this->cookieManager->hasToken()) {
            return $this->cookieManager->getToken();
        }
        return null;
    }
}
