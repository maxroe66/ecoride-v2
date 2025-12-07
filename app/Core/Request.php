<?php

namespace App\Core;

/**
 * Représente une requête HTTP simplifiée.
 * Fournit accès méthode, chemin, query params, corps JSON, auth user et path params.
 */
class Request
{
    public string $method;
    public string $path;
    public array $query = [];
    public array $headers = [];
    public array $json = [];
    public array $pathParams = [];
    public ?array $authUser = null;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        parse_str($_SERVER['QUERY_STRING'] ?? '', $this->query);
        $this->headers = $this->parseHeaders();
        
        // Lazy loading: parser JSON uniquement pour POST/PUT/PATCH/DELETE
        if (in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $this->json = $this->parseJsonBody();
        }
    }

    private function parseHeaders(): array
    {
        $out = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($k, 5)));
                $out[$name] = $v;
            }
        }
        return $out;
    }

    private function parseJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Retourne le corps JSON décodé de la requête
     * @return array Corps JSON décodé ou tableau vide
     */
    public function getJsonBody(): array
    {
        return $this->json;
    }

    /**
     * Retourne les paramètres de query string
     * @return array Paramètres GET
     */
    public function getQueryParams(): array
    {
        return $this->query;
    }

    /**
     * Définit les paramètres de chemin dynamiques (ex: /api/trajets/{id}/annuler)
     * @param array $params Paramètres extraits du chemin
     */
    public function setPathParams(array $params): void
    {
        $this->pathParams = $params;
    }

    /**
     * Retourne les paramètres de chemin dynamiques
     * @return array Paramètres du chemin
     */
    public function getPathParams(): array
    {
        return $this->pathParams;
    }

    /**
     * Retourne un paramètre de chemin par index
     * @param int $index Index du paramètre (0, 1, 2...)
     * @return string|null Valeur du paramètre ou null
     */
    public function getPathParam(int $index): ?string
    {
        return $this->pathParams[$index] ?? null;
    }

    /**
     * Définit l'utilisateur authentifié (par le middleware)
     * @param array $user Données utilisateur du JWT
     */
    public function setAuthUser(array $user): void
    {
        $this->authUser = $user;
    }

    /**
     * Retourne l'utilisateur authentifié
     * @return array|null Données utilisateur ou null
     */
    public function getAuthUser(): ?array
    {
        return $this->authUser;
    }

    /**
     * Retourne l'ID de l'utilisateur authentifié
     * @return int ID utilisateur
     * @throws \Exception si non authentifié
     */
    public function getAuthUserId(): int
    {
        if (!$this->authUser || !isset($this->authUser['user_id'])) {
            throw new \Exception('Utilisateur non authentifié');
        }
        return (int)$this->authUser['user_id'];
    }
}
