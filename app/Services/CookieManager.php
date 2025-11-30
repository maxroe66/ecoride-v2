<?php

namespace App\Services;

class CookieManager
{
    private const COOKIE_NAME = 'ecoride_token';
    private const COOKIE_PATH = '/';
    private int $cookieLifetime;

    /**
     * Initialise le gestionnaire de cookies
     * @param int $cookieLifetime Durée de vie du cookie en secondes (défaut: 7 jours)
     */
    public function __construct(int $cookieLifetime = 604800)
    {
        $this->cookieLifetime = $cookieLifetime;
    }

    /**
     * Crée un cookie sécurisé avec le token JWT
     *
     * Flags de sécurité:
     * - HttpOnly: Le cookie n'est accessible que via HTTP (pas JavaScript)
     * - Secure: Le cookie n'est envoyé que via HTTPS
     * - SameSite: Protection CSRF
     */
    public function setToken(string $token): void
    {
        $options = [
            'expires' => time() + $this->cookieLifetime,
            'path' => self::COOKIE_PATH,
            'secure' => $this->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        setcookie(self::COOKIE_NAME, $token, $options);
    }

    /**
     * Récupère le token du cookie
     */
    public function getToken(): ?string
    {
        return $_COOKIE[self::COOKIE_NAME] ?? null;
    }

    /**
     * Vérifie si un token existe dans le cookie
     */
    public function hasToken(): bool
    {
        return isset($_COOKIE[self::COOKIE_NAME]);
    }

    /**
     * Efface le cookie (déconnexion)
     */
    public function deleteToken(): void
    {
        setcookie(self::COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => self::COOKIE_PATH,
            'secure' => $this->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        unset($_COOKIE[self::COOKIE_NAME]);
    }

    /**
     * Vérifie si la connexion est HTTPS
     * En développement (localhost), le flag secure est inutile
     */
    private function isHttps(): bool
    {
        // Permettre HTTP en développement (localhost)
        if ($_SERVER['HTTP_HOST'] === 'localhost' || str_starts_with($_SERVER['HTTP_HOST'], '127.0.0.1')) {
            return false;
        }

        // En production, vérifier HTTPS
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }

    /**
     * Retourne le nom du cookie
     */
    public static function getCookieName(): string
    {
        return self::COOKIE_NAME;
    }
}
