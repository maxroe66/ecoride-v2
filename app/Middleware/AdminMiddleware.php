<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * Middleware pour vérifier que l'utilisateur est administrateur
 * À appliquer après AuthMiddleware pour les routes admin uniquement
 */
class AdminMiddleware
{
    /**
     * Retourne un middleware qui vérifie le rôle admin
     * @return \Closure
     */
    public static function check(): \Closure
    {
        return function (Request $req) {
            // Récupérer l'utilisateur authentifié
            $authUser = $req->authUser ?? null;

            // Vérifier que l'utilisateur est authentifié
            if (empty($authUser)) {
                Response::json(401, [
                    'success' => false,
                    'error' => [
                        'code' => 'UNAUTHORIZED',
                        'message' => 'Authentification requise'
                    ]
                ]);
                exit;
            }

            // Vérifier que l'utilisateur a le rôle admin
            if (($authUser['type_utilisateur'] ?? null) !== 'admin') {
                Response::json(403, [
                    'success' => false,
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => 'Accès réservé aux administrateurs'
                    ]
                ]);
                exit;
            }

            // Tout est OK, continuer
        };
    }
}
