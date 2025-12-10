<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\IncidentRepository;
use App\Factories\DatabaseFactory;

/**
 * Contrôleur pour l'espace employé
 * Routes: GET /api/employee/reviews/pending, POST /api/employee/reviews/{id}/moderation, 
 *         GET /api/employee/incidents, GET /api/employee/incidents/{id}
 * Middleware requis: auth + employee_type
 */
class EmployeeController
{
    /**
     * GET /api/employee/reviews/pending
     * Récupère les avis en attente de modération
     */
    public static function getPendingReviews(Request $req): void
    {
        try {
            $employeId = $req->getAuthUserId();
            
            // Récupérer les avis en attente via le repository résilient (Mongo + fallback MySQL)
            $avisRepo = \App\Factories\AvisRepositoryFactory::get();
            
            error_log('[EmployeeController] Récupération des avis en attente pour employé ID: ' . $employeId);
            
            $pendingReviews = $avisRepo->findPendingReviews();
            
            error_log('[EmployeeController] Avis en attente: ' . count($pendingReviews));
            
            Response::json(200, [
                'success' => true,
                'data' => [
                    'count' => count($pendingReviews),
                    'items' => $pendingReviews
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('[EmployeeController] Erreur: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
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
     * POST /api/employee/reviews/{id}/moderation
     * Modère un avis (approuve ou refuse)
     * Body: { action: 'approuve'|'refuse' }
     */
    public static function moderateReview(Request $req): void
    {
        try {
            $employeId = $req->getAuthUserId();
            $avisId = $req->getPathParam(0); // Depuis la route /api/employee/reviews/{id}/moderation
            
            if (!$avisId) {
                Response::json(400, [
                    'success' => false,
                    'error' => ['code' => 'INVALID_INPUT', 'message' => 'ID avis requis']
                ]);
                return;
            }
            
            $data = $req->getJsonBody();
            
            // Valider l'action
            if (empty($data['action']) || !in_array($data['action'], ['approuve', 'refuse'])) {
                Response::json(400, [
                    'success' => false,
                    'error' => ['code' => 'INVALID_INPUT', 'message' => "Action doit être 'approuve' ou 'refuse'"]
                ]);
                return;
            }
            
            $action = $data['action'];
            
            // Modérer l'avis via le repository résilient
            $avisRepo = \App\Factories\AvisRepositoryFactory::get();
            $success = $avisRepo->moderateReview($avisId, $action, $employeId);
            
            if ($success) {
                Response::json(200, [
                    'success' => true,
                    'data' => [
                        'message' => "Avis {$action} avec succès",
                        'avis_id' => $avisId,
                        'action' => $action
                    ]
                ]);
            } else {
                Response::json(500, [
                    'success' => false,
                    'error' => ['code' => 'PERSIST_ERROR', 'message' => 'Erreur lors de la modération']
                ]);
            }
        } catch (\Throwable $e) {
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
     * GET /api/employee/incidents
     * Récupère tous les incidents (participations avec problème)
     */
    public static function getIncidents(Request $req): void
    {
        try {
            $db = DatabaseFactory::getConnection();
            $incidentRepo = new IncidentRepository($db);
            
            $incidents = $incidentRepo->findPending();
            
            Response::json(200, [
                'success' => true,
                'data' => [
                    'count' => count($incidents),
                    'items' => $incidents
                ]
            ]);
        } catch (\Throwable $e) {
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
     * GET /api/employee/incidents/{id}
     * Récupère les détails d'un incident spécifique
     */
    public static function getIncidentDetail(Request $req): void
    {
        try {
            $participationId = (int)$req->getPathParam(0);
            
            if ($participationId <= 0) {
                Response::json(400, [
                    'success' => false,
                    'error' => ['code' => 'INVALID_INPUT', 'message' => 'ID incident invalide']
                ]);
                return;
            }
            
            $db = DatabaseFactory::getConnection();
            $incidentRepo = new IncidentRepository($db);
            
            $incident = $incidentRepo->findById($participationId);
            
            if (!$incident) {
                Response::json(404, [
                    'success' => false,
                    'error' => ['code' => 'NOT_FOUND', 'message' => 'Incident non trouvé']
                ]);
                return;
            }
            
            Response::json(200, [
                'success' => true,
                'data' => $incident
            ]);
        } catch (\Throwable $e) {
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
