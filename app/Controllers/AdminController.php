<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Factories\DatabaseFactory;
use App\Validators\CreateEmployeeValidator;
use App\DTO\CreateEmployeeRequest;

/**
 * Contrôleur pour l'espace administrateur
 * Routes: POST /api/admin/employees, GET /api/admin/stats/*, POST /api/admin/users/{id}/suspend
 * Middleware requis: auth + admin
 */
class AdminController
{
    /**
     * POST /api/admin/employees
     * Créer un compte employé (admin uniquement)
     */
    public static function createEmployee(Request $req): void
    {
        try {
            $req->getAuthUserId();

            $data = $req->getJsonBody();
            CreateEmployeeRequest::fromArray($data);
            CreateEmployeeValidator::validate($data);

            $db = DatabaseFactory::getConnection();

            $tempPassword = bin2hex(random_bytes(8));
            $passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT);

            $stmt = $db->prepare('
                INSERT INTO utilisateur (nom, prenom, email, pseudo, password, type_utilisateur, role, credit, suspendu)
                VALUES (:nom, :prenom, :email, :pseudo, :password, :type, :role, :credit, :suspendu)
            ');

            $stmt->execute([
                ':nom' => 'Equipe',
                ':prenom' => 'EcoRide',
                ':email' => $data['email'],
                ':pseudo' => $data['pseudo'],
                ':password' => $passwordHash,
                ':type' => 'employe',
                ':role' => 'passager',
                ':credit' => 0,
                ':suspendu' => 0
            ]);

            $employeeId = (int)$db->lastInsertId();

            Response::json(201, [
                'success' => true,
                'data' => [
                    'id' => $employeeId,
                    'email' => $data['email'],
                    'pseudo' => $data['pseudo'],
                    'type' => 'employe',
                    'statut' => 'actif',
                    'temp_password' => $tempPassword,
                    'message' => 'Employé créé avec succès. Mot de passe temporaire : ' . $tempPassword
                ]
            ]);
        } catch (\App\Exceptions\ValidationException $e) {
            Response::json(400, [
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Validation échouée',
                    'details' => $e->getDetails()
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('[AdminController::createEmployee] Exception: ' . $e->getMessage());
            Response::json(500, [
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => $e->getMessage()
                ]
            ]);
        }
    }

    /**
     * GET /api/admin/stats/trips-per-day
     * Récupère le nombre de covoiturages par jour
     */
    public static function getTripsPerDay(Request $req): void
    {
        try {
            $days = (int)($req->getQueryParams()['days'] ?? 30);
            if ($days < 1 || $days > 365) {
                $days = 30;
            }

            $db = DatabaseFactory::getConnection();
            $sql = "
                SELECT DATE(date_depart) as date, COUNT(*) as count
                FROM covoiturage
                WHERE date_depart >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY DATE(date_depart)
                ORDER BY date ASC
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute([':days' => $days]);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $results = array_map(function (array $row) {
                $row['credits_earned'] = (float)($row['credits_earned'] ?? 0);
                $row['total'] = $row['credits_earned'];
                return $row;
            }, $results);

            Response::json(200, [
                'success' => true,
                'data' => [
                    'trips_per_day' => $results,
                    'period_days' => $days
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('[AdminController::getTripsPerDay] Exception: ' . $e->getMessage());
            Response::json(500, [
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => $e->getMessage()
                ]
            ]);
        }
    }

    /**
     * GET /api/admin/employees
     * Liste les comptes employés existants
     */
    public static function listEmployees(Request $req): void
    {
        try {
            $db = DatabaseFactory::getConnection();
            $stmt = $db->query('
                SELECT 
                    utilisateur_id AS id,
                    email,
                    pseudo,
                    type_utilisateur,
                    date_creation,
                    suspendu,
                    CASE WHEN suspendu = 1 THEN "suspendu" ELSE "actif" END AS statut
                FROM utilisateur
                WHERE type_utilisateur = "employe"
                ORDER BY date_creation DESC
            ');
            $employees = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            Response::json(200, [
                'success' => true,
                'data' => [
                    'employees' => $employees
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('[AdminController::listEmployees] Exception: ' . $e->getMessage());
            Response::json(500, [
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => $e->getMessage()
                ]
            ]);
        }
    }

    /**
     * GET /api/admin/users
     * Liste tous les utilisateurs (administration)
     */
    public static function listUsers(Request $req): void
    {
        try {
            $db = DatabaseFactory::getConnection();
            $stmt = $db->query('
                SELECT 
                    utilisateur_id AS id,
                    email,
                    pseudo,
                    type_utilisateur,
                    date_creation,
                    suspendu,
                    CASE WHEN suspendu = 1 THEN "suspendu" ELSE "actif" END AS statut
                FROM utilisateur
                ORDER BY date_creation DESC
            ');
            $users = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            Response::json(200, [
                'success' => true,
                'data' => [
                    'users' => $users
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('[AdminController::listUsers] Exception: ' . $e->getMessage());
            Response::json(500, [
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => $e->getMessage()
                ]
            ]);
        }
    }

    /**
     * GET /api/admin/stats/credits-per-day
     * Récupère les crédits gagnés par la plateforme par jour
     */
    public static function getCreditsPerDay(Request $req): void
    {
        try {
            $days = (int)($req->getQueryParams()['days'] ?? 30);
            if ($days < 1 || $days > 365) {
                $days = 30;
            }

            $db = DatabaseFactory::getConnection();
            $sql = "
                SELECT DATE(date_operation) as date,
                       SUM(CASE WHEN type_operation = 'credit' THEN montant ELSE 0 END) as credits_earned
                FROM credit_operation
                WHERE date_operation >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY DATE(date_operation)
                ORDER BY date ASC
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute([':days' => $days]);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            Response::json(200, [
                'success' => true,
                'data' => [
                    'credits_per_day' => $results,
                    'period_days' => $days
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('[AdminController::getCreditsPerDay] Exception: ' . $e->getMessage());
            Response::json(500, [
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => $e->getMessage()
                ]
            ]);
        }
    }

    /**
     * GET /api/admin/stats/total-credits
     * Récupère le total des crédits gagnés par la plateforme
     */
    public static function getTotalCredits(Request $req): void
    {
        try {
            $db = DatabaseFactory::getConnection();
            $sql = "
                SELECT COALESCE(SUM(montant), 0) as total_credits
                FROM credit_operation
                WHERE type_operation = 'credit'
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            $total = (float)($result['total_credits'] ?? 0);

            Response::json(200, [
                'success' => true,
                'data' => [
                    'total' => $total,
                    'total_credits' => $total
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('[AdminController::getTotalCredits] Exception: ' . $e->getMessage());
            Response::json(500, [
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => $e->getMessage()
                ]
            ]);
        }
    }

    /**
     * POST /api/admin/users/{id}/suspend
     * Suspendre un utilisateur ou employé
     */
    public static function suspendUser(Request $req): void
    {
        try {
            $userId = (int)$req->getPathParam(0);
            if ($userId <= 0) {
                Response::json(400, [
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_INPUT',
                        'message' => 'ID utilisateur invalide'
                    ]
                ]);
                return;
            }

            $db = DatabaseFactory::getConnection();

            // Vérifier que l'utilisateur existe
            $checkStmt = $db->prepare('SELECT utilisateur_id, pseudo, suspendu FROM utilisateur WHERE utilisateur_id = :id');
            $checkStmt->execute([':id' => $userId]);
            $user = $checkStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$user) {
                Response::json(404, [
                    'success' => false,
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Utilisateur non trouvé'
                    ]
                ]);
                return;
            }

            // Vérifier que l'utilisateur n'est pas déjà suspendu
            if ((int)$user['suspendu'] === 1) {
                Response::json(400, [
                    'success' => false,
                    'error' => [
                        'code' => 'ALREADY_SUSPENDED',
                        'message' => 'Cet utilisateur est déjà suspendu'
                    ]
                ]);
                return;
            }

            // Suspendre l'utilisateur
            $updateStmt = $db->prepare('UPDATE utilisateur SET suspendu = 1 WHERE utilisateur_id = :id');
            $updateStmt->execute([':id' => $userId]);

            Response::json(200, [
                'success' => true,
                'data' => [
                    'id' => $userId,
                    'pseudo' => $user['pseudo'],
                    'statut' => 'suspendu',
                    'message' => 'Utilisateur suspendu avec succès'
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('[AdminController::suspendUser] Exception: ' . $e->getMessage());
            Response::json(500, [
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => $e->getMessage()
                ]
            ]);
        }
    }

    /**
     * POST /api/admin/users/{id}/unsuspend
     * Réactiver un utilisateur suspendu
     */
    public static function unsuspendUser(Request $req): void
    {
        try {
            $userId = (int)$req->getPathParam(0);
            if ($userId <= 0) {
                Response::json(400, [
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_INPUT',
                        'message' => 'ID utilisateur invalide'
                    ]
                ]);
                return;
            }

            $db = DatabaseFactory::getConnection();

            // Vérifier que l'utilisateur existe
            $checkStmt = $db->prepare('SELECT utilisateur_id, pseudo, suspendu FROM utilisateur WHERE utilisateur_id = :id');
            $checkStmt->execute([':id' => $userId]);
            $user = $checkStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$user) {
                Response::json(404, [
                    'success' => false,
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Utilisateur non trouvé'
                    ]
                ]);
                return;
            }

            // Vérifier que l'utilisateur est suspendu
            if ((int)$user['suspendu'] !== 1) {
                Response::json(400, [
                    'success' => false,
                    'error' => [
                        'code' => 'NOT_SUSPENDED',
                        'message' => 'Cet utilisateur n\'est pas suspendu'
                    ]
                ]);
                return;
            }

            // Réactiver l'utilisateur
            $updateStmt = $db->prepare('UPDATE utilisateur SET suspendu = 0 WHERE utilisateur_id = :id');
            $updateStmt->execute([':id' => $userId]);

            Response::json(200, [
                'success' => true,
                'data' => [
                    'id' => $userId,
                    'pseudo' => $user['pseudo'],
                    'statut' => 'actif',
                    'message' => 'Utilisateur réactivé avec succès'
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('[AdminController::unsuspendUser] Exception: ' . $e->getMessage());
            Response::json(500, [
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => $e->getMessage()
                ]
            ]);
        }
    }
}
