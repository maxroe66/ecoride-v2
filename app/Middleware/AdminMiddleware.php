<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Factories\DatabaseFactory;
use PDO;

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
            $authUser = $req->getAuthUser();

            if (!$authUser || !isset($authUser['user_id'])) {
                Response::json(401, [
                    'success' => false,
                    'error' => [
                        'code' => 'UNAUTHORIZED',
                        'message' => 'Authentification requise'
                    ]
                ]);
                exit;
            }

            try {
                $db = DatabaseFactory::getConnection();
                $stmt = $db->prepare('SELECT type_utilisateur FROM utilisateur WHERE utilisateur_id = :id LIMIT 1');
                $stmt->execute([':id' => $authUser['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                $allowedAdminTypes = ['administrateur', 'admin'];

                if (!$user || !in_array($user['type_utilisateur'], $allowedAdminTypes, true)) {
                    Response::json(403, [
                        'success' => false,
                        'error' => [
                            'code' => 'FORBIDDEN',
                            'message' => 'Accès réservé aux administrateurs'
                        ]
                    ]);
                    exit;
                }
            } catch (\Throwable $e) {
                Response::json(500, [
                    'success' => false,
                    'error' => [
                        'code' => 'SERVER_ERROR',
                        'message' => 'Erreur lors de la vérification administrateur'
                    ]
                ]);
                exit;
            }
        };
    }
}
