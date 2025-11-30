<?php
namespace App\Core;

/**
 * Router minimaliste pour routes API exactes.
 * Supporte middlewares (callables) avant exécution de l'action.
 */
class Router
{
    private array $routes = [];

    public function add(string $method, string $path, callable $action, array $middlewares = []): void
    {
        $method = strtoupper($method);
        $this->routes[$method][$path] = ['action' => $action, 'middlewares' => $middlewares];
    }

    /**
     * Dispatch la requête.
     * @return bool true si une route a été exécutée, false sinon.
     */
    public function dispatch(): bool
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        if (isset($this->routes[$method][$path])) {
            $route = $this->routes[$method][$path];
            foreach ($route['middlewares'] as $mw) { $mw(); }
            ($route['action'])();
            return true;
        }
        return false; // aucun match
    }
}
