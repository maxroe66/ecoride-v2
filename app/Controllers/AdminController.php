<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Factories\DatabaseFactory;
use App\Repositories\UserRepository;
use App\Services\UserService;
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
            $adminId = $req->getAuthUserId();

            // 1. Valider la structure
            $data = $req->getJsonBody();
            CreateEmployeeRequest::fromArray($data);

            // 2. Valider les règles métier
            CreateEmployeeValidator::validate($data);

            // 3. Créer l'employé
            $db = DatabaseFactory::getConnection();
            $userRepo = new UserRepository($db);

            // Générer un mot de passe temporaire
            $tempPassword = bin2hex(random_bytes(8));
            $passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT);

            $stmt = $db->prepare('
                INSERT INTO utilisateur (email, pseudo, password_hash, type_utilisateur, statut, credit, date_creation)
                VALUES (:email, :pseudo, :password, :type, :statut, :credit, NOW())
            ');

            $stmt->execute([
                ':email' => $data['email'],
                ':pseudo' => $data['pseudo'],
                ':password' => $passwordHash,
                ':type' => 'employe',
                ':statut' => 'actif',
                ':credit' => 0
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
                SELECT DATE(c.date_depart) as date, SUM(co.montant) as credits_earned
                FROM covoiturage c
                LEFT JOIN credit_operation co ON c.covoiturage_id = co.trip_id AND co.type = 'credit'
                WHERE c.date_depart >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY DATE(c.date_depart)
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
                WHERE type = 'credit'
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            Response::json(200, [
                'success' => true,
                'data' => [
                    'total_credits' => (float)($result['total_credits'] ?? 0)
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
            $checkStmt = $db->prepare('SELECT utilisateur_id, pseudo, statut FROM utilisateur WHERE utilisateur_id = :id');
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
            if ($user['statut'] === 'suspendu') {
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
            $updateStmt = $db->prepare('UPDATE utilisateur SET statut = :statut WHERE utilisateur_id = :id');
            $updateStmt->execute([':statut' => 'suspendu', ':id' => $userId]);

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
            $checkStmt = $db->prepare('SELECT utilisateur_id, pseudo, statut FROM utilisateur WHERE utilisateur_id = :id');
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
            if ($user['statut'] !== 'suspendu') {
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
            $updateStmt = $db->prepare('UPDATE utilisateur SET statut = :statut WHERE utilisateur_id = :id');
            $updateStmt->execute([':statut' => 'actif', ':id' => $userId]);

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
