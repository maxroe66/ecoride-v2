<?php

namespace App\Controllers;

use App\Factories\DatabaseFactory;
use App\Repositories\TrajetRepository;
use App\Repositories\ParticipationRepository;
use App\Repositories\UserRepository;
use App\Repositories\CreditOperationRepository;
use App\Validators\QueryValidator;
use App\Validators\CancellationValidator;
use App\Validators\TripValidator;
use App\Validators\ParticipationValidator;
use App\Services\TripService;
use App\Services\ParticipationService;
use App\Services\CancellationService;
use App\Services\EmailService;
use App\Helpers\ControllerHelper;
use App\Core\Response;
use Exception;

/**
 * Contrôleur des trajets.
 * Routes: GET /api/trajets, GET /api/trajets-suggestions
 * Conserve format des réponses et codes d'erreur du legacy.
 */
class TrajetController
{
    public static function search(): void
    {
        parse_str($_SERVER['QUERY_STRING'] ?? '', $query);

        // Validation paramètres principaux
        try {
            $validated = QueryValidator::validateTrajetSearch($query); // exception 400 pour erreurs
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $code = str_contains($msg, 'Format de date') ? 'INVALID_DATE' : 'MISSING_FIELDS';
            Response::json(400, ['success' => false,'error' => ['code' => $code,'message' => $msg]]);
            return;
        }

        $filters = QueryValidator::extractFilters($query);

        try {
            $db = DatabaseFactory::getConnection();
            $service = new TripService(new TrajetRepository($db));
            $trajets = $service->search(
                $validated['departure'],
                $validated['arrival'],
                $validated['date'],
                $filters['economique'],
                $filters['maxPrice'],
                $filters['maxDuration'],
                $filters['minRating']
            );
            $payload = array_map(fn($t) => TripService::normalize($t), $trajets);
            Response::json(200, ['success' => true,'data' => ['items' => $payload,'count' => count($payload)]]);
        } catch (\Exception $e) {
            Response::json(500, ['success' => false,'error' => ['code' => 'SEARCH_FAILED','message' => 'Erreur lors de la recherche. Veuillez réessayer.']]);
        }
    }

    public static function suggestions(): void
    {
        parse_str($_SERVER['QUERY_STRING'] ?? '', $query);
        try {
            $validated = QueryValidator::validateDateSuggestions($query);
        } catch (\Exception $e) {
            Response::json(400, ['success' => false,'error' => ['code' => 'MISSING_FIELDS','message' => $e->getMessage()]]);
            return;
        }
        $filters = QueryValidator::extractFilters($query);
        try {
            $db = DatabaseFactory::getConnection();
            $service = new TripService(new TrajetRepository($db));
            $suggestions = $service->suggestions(
                $validated['departure'],
                $validated['arrival'],
                3,
                $filters['economique'],
                $filters['maxPrice'],
                $filters['maxDuration'],
                $filters['minRating']
            );
            Response::json(200, ['success' => true,'data' => ['suggestions' => $suggestions]]);
        } catch (\Exception $e) {
            Response::json(500, ['success' => false,'error' => ['code' => 'SEARCH_FAILED','message' => 'Erreur lors de la recherche. Veuillez réessayer.']]);
        }
    }

    /**
     * Récupère le détail complet d'un covoiturage
     * GET /api/trajets/detail?id={id}
     */
    public static function show(): void
    {
        parse_str($_SERVER['QUERY_STRING'] ?? '', $query);
        $id = $query['id'] ?? null;

        try {
            $id = TripValidator::validateTripId($id);
        } catch (Exception $e) {
            Response::json(400, ['success' => false, 'error' => ['code' => 'INVALID_ID', 'message' => $e->getMessage()]]);
            return;
        }

        try {
            $db = DatabaseFactory::getConnection();
            $service = new TripService(new TrajetRepository($db));
            $detail = $service->detail($id);

            // Log temporaire pour debug : affiche le détail et les avis récupérés
            error_log('[TrajetController] Détail trajet : ' . json_encode($detail));

            // Si vide, le trajet n'existe pas
            if (empty($detail)) {
                Response::json(404, ['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Trajet introuvable']]);
                return;
            }

            // Retourner le détail
            Response::json(200, ['success' => true, 'data' => $detail]);
        } catch (\Exception $e) {
            error_log('[TrajetController] Exception : ' . $e->getMessage());
            Response::json(500, ['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => $e->getMessage()]]);
        }
    }
    /**
     * Demander une participation à un covoiturage
     * POST /api/participations/request
     * Body: { covoiturage_id, nb_places }
     */
    public static function requestParticipation(): void
    {
        // 1. RÉCUPÉRER L'UTILISATEUR AUTHENTIFIÉ
        $userId = ControllerHelper::getAuthUserId();

        // 2. LIRE ET VALIDER LE JSON
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        
        try {
            QueryValidator::validateJsonInput($json);
            $validated = ParticipationValidator::validateParticipationRequest($json);
            $covoiturageId = $validated['covoiturage_id'];
            $nbPlaces = $validated['nb_places'];
        } catch (Exception $e) {
            Response::json(400, ['success' => false, 'error' => ['code' => 'INVALID_INPUT', 'message' => $e->getMessage()]]);
            return;
        }
        
        // 3. LOGIQUE MÉTIER
        try {
            $db = DatabaseFactory::getConnection();
            $service = new ParticipationService(
                new ParticipationRepository($db),
                new TrajetRepository($db),
                new UserRepository($db)
            );

            // Appeler le service et retourner le résultat
            $result = $service->requestParticipation($userId, $covoiturageId, $nbPlaces);
            Response::json(201, ['success' => true, 'data' => $result]);
            
        } catch (Exception $e) {
            Response::json(400, ['success' => false, 'error' => ['code' => 'OPERATION_FAILED', 'message' => $e->getMessage()]]);
        }
    }

    /**
     * Valider une participation (1ère confirmation)
     * POST /api/participations/validate
     * Body: { participation_id }
     */
    public static function validateParticipation(): void
    {
        // 1. RÉCUPÉRER L'UTILISATEUR AUTHENTIFIÉ
        $userId = ControllerHelper::getAuthUserId();

        // 2. LIRE ET VALIDER LE JSON
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        
        try {
            QueryValidator::validateJsonInput($json);
            $participationId = ParticipationValidator::validateParticipationId($json['participation_id'] ?? null);
        } catch (Exception $e) {
            Response::json(400, ['success' => false, 'error' => ['code' => 'INVALID_INPUT', 'message' => $e->getMessage()]]);
            return;
        }

        // 3. LOGIQUE MÉTIER
        try {
            $db = DatabaseFactory::getConnection();
            $service = new ParticipationService(
                new ParticipationRepository($db),
                new TrajetRepository($db),
                new UserRepository($db)
            );

            // Appeler le service et retourner le résultat
            $result = $service->validateParticipation($participationId);
            Response::json(200, ['success' => true, 'data' => $result]);
            
        } catch (Exception $e) {
            Response::json(400, ['success' => false, 'error' => ['code' => 'OPERATION_FAILED', 'message' => $e->getMessage()]]);
        }
    }

    /**
     * Confirmer une participation (2ème confirmation finale)
     * POST /api/participations/confirm
     * Body: { participation_id }
     */
    public static function confirmParticipation(): void
    {
        // 1. RÉCUPÉRER L'UTILISATEUR AUTHENTIFIÉ
        $userId = ControllerHelper::getAuthUserId();

        // 2. LIRE ET VALIDER LE JSON
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        
        try {
            QueryValidator::validateJsonInput($json);
            $participationId = ParticipationValidator::validateParticipationId($json['participation_id'] ?? null);
        } catch (Exception $e) {
            Response::json(400, ['success' => false, 'error' => ['code' => 'INVALID_INPUT', 'message' => $e->getMessage()]]);
            return;
        }

        // 3. LOGIQUE MÉTIER
        try {
            $db = DatabaseFactory::getConnection();
            $service = new ParticipationService(
                new ParticipationRepository($db),
                new TrajetRepository($db),
                new UserRepository($db)
            );

            // Appeler le service et retourner le résultat
            $result = $service->confirmParticipation($participationId);
            Response::json(200, ['success' => true, 'data' => $result]);
            
        } catch (Exception $e) {
            Response::json(400, ['success' => false, 'error' => ['code' => 'OPERATION_FAILED', 'message' => $e->getMessage()]]);
        }
    }

    /**
     * Endpoint : POST /api/trajets
     * Crée un nouveau trajet
     */
    public static function create(): void
    {
        // 1. RÉCUPÉRER L'UTILISATEUR AUTHENTIFIÉ
        $userId = ControllerHelper::getAuthUserId();

        // 2. Récupérer les données JSON
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        
        try {
            QueryValidator::validateJsonInput($data);
        } catch (Exception $e) {
            Response::json(400, ['success' => false, 'error' => ['code' => 'INVALID_JSON', 'message' => $e->getMessage()]]);
            return;
        }

        try {
            $db = DatabaseFactory::getConnection();
            
            // 3. Vérifier que l'utilisateur est chauffeur
            $userRepo = new UserRepository($db);
            $user = $userRepo->findById($userId);
            
            if (!$user || !in_array($user->role, ['chauffeur', 'chauffeur_passager'])) {
                Response::json(403, ['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Seuls les chauffeurs peuvent créer des trajets']]);
                return;
            }

            // 4. Vérifier que le véhicule appartient au chauffeur
            if (!empty($data['voiture_id'])) {
                $vehicleRepo = new \App\Repositories\VehicleRepository($db);
                $vehicles = $vehicleRepo->findByUserId($userId);
                $vehicleIds = array_map(fn($v) => $v->id, $vehicles);
                
                if (!in_array((int)$data['voiture_id'], $vehicleIds)) {
                    Response::json(403, ['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Ce véhicule ne vous appartient pas']]);
                    return;
                }
            }

            // 5. Créer le trajet via le service
            $service = new TripService(new TrajetRepository($db));
            $trajet = $service->createTrip($data, $userId);

            // 6. Retourner le trajet créé avec message d'avertissement
            Response::json(201, [
                'success' => true,
                'data' => $trajet,
                'message' => 'Trajet créé avec succès. Rappel : 2 crédits seront prélevés par la plateforme pour chaque participation.'
            ]);

        } catch (\App\Validators\Exception $e) {
            Response::json(400, ['success' => false, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]]);
        } catch (Exception $e) {
            Response::json(500, ['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => $e->getMessage()]]);
        }
    }

    /**
     * Liste les prochains trajets du chauffeur connecté
     */
    public static function myTrips(): void
    {
        // Authentifier l'utilisateur
        try {
            $middleware = new \App\Middleware\AuthMiddleware();
            $userData = $middleware->authenticate();
            $userId = (int)$userData['user_id'];
        } catch (Exception $e) {
            Response::json(401, ['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Authentification requise.']]);
            return;
        }

        try {
            $db = \App\Factories\DatabaseFactory::getConnection();
            $repo = new \App\Repositories\TrajetRepository($db);
            $trajets = $repo->getTrajetsByUserId($userId); // retourne déjà des arrays enrichis

            // Ne garder que les trajets à venir (format array: keys 'date_depart', 'heure_depart')
            $now = new \DateTime('now');
            $upcoming = array_values(array_filter($trajets, function (array $t) use ($now) {
                $date = $t['date_depart'] ?? null;
                $time = $t['heure_depart'] ?? '00:00:00';
                $status = $t['statut'] ?? null;
                // Exclure les trajets annulés du listing des prochains trajets
                if ($status === 'annule') return false;
                if (!$date) return true; // si manque d'info date, ne pas filtrer
                try {
                    $dt = new \DateTime($date . ' ' . ($time ?: '00:00:00'));
                    return $dt >= $now;
                } catch (\Throwable $e) {
                    return true;
                }
            }));

            Response::json(200, ['success' => true, 'data' => $upcoming]);
        } catch (Exception $e) {
            Response::json(500, ['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => $e->getMessage()]]);
        }
    }

    /**
     * Annule un covoiturage en tant que chauffeur
     * POST /api/trajets/{id}/annuler
     * Body: { raison?: string }
     */
    public static function cancelTrip(): void
    {
        // Récupérer l'ID du trajet depuis les paramètres dynamiques du routeur
        $tripId = ControllerHelper::getPathParam(0);
        $userId = ControllerHelper::getAuthUserId();

        try {
            // 1. Récupérer la raison optionnelle
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $reason = $body['raison'] ?? null;

            // Valider les paramètres
            try {
                $validated = CancellationValidator::validateTripCancellation((int)$tripId, $userId, $reason);
                $tripId = $validated['trip_id'];
                $reason = $validated['reason'];
            } catch (Exception $e) {
                Response::json(400, ['success' => false, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]]);
                return;
            }

            // Récupérer la base de données
            $db = DatabaseFactory::getConnection();

            // Initialiser les repositories et services
            $trajetRepo = new TrajetRepository($db);
            $participationRepo = new ParticipationRepository($db);
            $userRepo = new UserRepository($db);
            $creditOpRepo = new CreditOperationRepository($db);
            $emailService = new EmailService();

            $cancellationService = new CancellationService(
                $trajetRepo,
                $participationRepo,
                $userRepo,
                $creditOpRepo
            );

            // Transaction pour garantir atomicité des mises à jour
            $db->beginTransaction();
            try {
                // Annuler le trajet
                $result = $cancellationService->cancelTripAsDriver($tripId, $userId, $reason);
                $db->commit();
            } catch (\Exception $inner) {
                $db->rollBack();
                throw $inner;
            }

            // Envoyer les emails de notification aux passagers
            $trajet = $trajetRepo->getTrajetDetail($tripId);
            $participants = $participationRepo->findByTrip($tripId);

            foreach ($participants as $participant) {
                $emailService->sendCancellationNotification(
                    $participant,
                    $trajet,
                    $user['nom'] . ' ' . $user['prenom'],
                    $trajet['prix'] ?? 0, // Montant du remboursement
                    $reason
                );
            }

            // Retourner la réponse
            Response::json(200, [
                'success' => true,
                'message' => 'Trajet annulé avec succès',
                'participants_notified' => count($participants)
            ]);

        } catch (Exception $e) {
            error_log('[TrajetController::cancelTrip] Exception : ' . $e->getMessage());
            Response::json(500, ['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => $e->getMessage()]]);
        }
    }
}
