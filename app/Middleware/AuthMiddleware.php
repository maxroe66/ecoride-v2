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
     * Cherche le token dans le cookie sécurisé (priorité) ou le header Authorization (fallback).
     * Cookie utilisé par le frontend web, header Authorization pour tests API (curl/Postman).
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
     * Récupère le token JWT avec ordre de priorité défini
     * 
     * Priorité 1 : Cookie sécurisé (frontend web)
     * Priorité 2 : Header Authorization Bearer (tests API)
     * 
     * @return string|null Le token JWT ou null si absent
     */
    private function getToken(): ?string
    {
        // 1. Essayer le cookie d'abord (pour le frontend)
        if ($this->cookieManager->hasToken()) {
            return $this->cookieManager->getToken();
        }
        
        // 2. Essayer le header Authorization (pour les API calls / Postman / curl)
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if ($authHeader && preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
}
