<?php
namespace App\Core;

/**
 * Réponse HTTP simplifiée.
 * Méthodes utilitaires pour JSON et statut.
 */
class Response
{
    public static function json(int $status, array $payload): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
    }
}
