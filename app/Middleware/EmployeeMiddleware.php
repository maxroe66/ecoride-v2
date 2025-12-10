<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Factories\DatabaseFactory;
use PDO;

/**
 * Middleware pour vérifier que l'utilisateur est un employé
 * Doit être appelé APRÈS AuthMiddleware (l'utilisateur doit être authentifié)
 */
class EmployeeMiddleware
{
    /**
     * Retourne le middleware callable pour vérifier le rôle employé
     */
    public static function check(): callable
    {
        return function (Request $req) {
            // Vérifier que l'utilisateur est authentifié
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
            
            $userId = $authUser['user_id'];
            
            // Récupérer le type_utilisateur depuis la BD
            try {
                $db = DatabaseFactory::getConnection();
                $stmt = $db->prepare('SELECT type_utilisateur FROM utilisateur WHERE utilisateur_id = ?');
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user || $user['type_utilisateur'] !== 'employe') {
                    Response::json(403, [
                        'success' => false,
                        'error' => [
                            'code' => 'FORBIDDEN',
                            'message' => 'Accès réservé aux employés'
                        ]
                    ]);
                    exit;
                }
            } catch (\Exception $e) {
                Response::json(500, [
                    'success' => false,
                    'error' => [
                        'code' => 'SERVER_ERROR',
                        'message' => 'Erreur lors de la vérification du rôle'
                    ]
                ]);
                exit;
            }
            
            // L'utilisateur est un employé autorisé - continuer
        };
    }
}
