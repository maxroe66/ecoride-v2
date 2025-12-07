<?php

namespace App\Core;

/**
 * Représente une requête HTTP simplifiée.
 * Fournit accès méthode, chemin, query params et corps JSON décodé.
 */
class Request
{
    public string $method;
    public string $path;
    public array $query = [];
    public array $headers = [];
    public array $json = [];

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        parse_str($_SERVER['QUERY_STRING'] ?? '', $this->query);
        $this->headers = $this->parseHeaders();
        $this->json = $this->parseJsonBody();
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
}
