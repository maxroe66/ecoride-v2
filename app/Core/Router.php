<?php

namespace App\Core;

/**
 * Router minimaliste pour routes API exactes et paraméttriques.
 * Supporte middlewares (callables) avant exécution de l'action.
 * Supporte les paramètres dynamiques: /api/trajets/{id}/annuler
 */
class Router
{
    private array $routes = [];
    private array $dynamicRoutes = []; // Pour les routes paraméttriques

    public function add(string $method, string $path, callable $action, array $middlewares = []): void
    {
        $method = strtoupper($method);
        
        // Vérifier si c'est une route dynamique (contient {})
        if (strpos($path, '{') !== false) {
            $this->dynamicRoutes[$method][] = [
                'pattern' => $this->pathToRegex($path),
                'action' => $action,
                'middlewares' => $middlewares
            ];
        } else {
            $this->routes[$method][$path] = ['action' => $action, 'middlewares' => $middlewares];
        }
    }

    /**
     * Convertit un path avec {} en regex
     * /api/trajets/{id}/annuler -> /^\/api\/trajets\/\d+\/annuler$/
     */
    private function pathToRegex(string $path): string
    {
        $pattern = preg_quote($path, '/');
        $pattern = preg_replace('/\\\{id\\\}/', '(\\d+)', $pattern);
        $pattern = preg_replace('/\\\{slug\\\}/', '([a-zA-Z0-9_-]+)', $pattern);
        return '/^' . $pattern . '$/';
    }

    /**
     * Dispatch la requête.
     * @return bool true si une route a été exécutée, false sinon.
     */
    public function dispatch(): bool
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        error_log("[Router] Attempting to match $method $path");

        // Créer l'objet Request une seule fois
        $request = new Request();

        // D'abord, chercher les routes exactes
        if (isset($this->routes[$method][$path])) {
            error_log("[Router] Matched exact route: $method $path");
            $route = $this->routes[$method][$path];
            foreach ($route['middlewares'] as $mw) {
                $mw($request);
            }
            ($route['action'])($request);
            return true;
        }

        // Ensuite, chercher les routes dynamiques
        if (isset($this->dynamicRoutes[$method])) {
            foreach ($this->dynamicRoutes[$method] as $route) {
                if (preg_match($route['pattern'], $path, $matches)) {
                    error_log("[Router] Matched dynamic route: $method $path with pattern " . $route['pattern']);
                    error_log("[Router] Number of middlewares: " . count($route['middlewares']));
                    // Stocker les paramètres dans l'objet Request
                    $request->setPathParams(array_slice($matches, 1));
                    
                    // Note: Conserver aussi dans $_REQUEST pour compatibilité avec middlewares
                    $_REQUEST['_path_params'] = $request->pathParams;
                    
                    foreach ($route['middlewares'] as $mw) {
                        $mw($request);
                    }
                    ($route['action'])($request);
                    return true;
                }
            }
        }

        error_log("[Router] No route matched for $method $path");
        return false; // aucun match
    }
}

