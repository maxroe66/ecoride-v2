<?php

namespace App\Middleware;

use App\Services\CsrfService;

class CsrfMiddleware
{
    /**
     * Valide le token CSRF transmis via l'en-tête X-CSRF-Token.
     * Répond 403 JSON si invalide/absent.
     */
    public function validate(): void
    {
        $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!CsrfService::validate($header)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => ['code' => 'CSRF_FAILED', 'message' => 'Invalid CSRF token']]);
            exit();
        }
    }
}
