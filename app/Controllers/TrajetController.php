<?php

namespace App\Controllers;

use App\Factories\ServiceLocator as SL;
use App\Services\TripService;
use App\Validators\QueryValidator;
use App\Validators\CancellationValidator;
use App\Validators\TripValidator;
use App\Helpers\ControllerHelper;
use App\Core\Response;
use Exception;

/**
 * Contrôleur des trajets.
 * Routes: GET /api/trajets, POST /api/trajets, GET /api/user/trajets, etc.
 * Gestion des trajets (recherche, création, annulation) - les participations sont dans ParticipationController
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
            $service = SL::getTripService();
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
            $service = SL::getTripService();
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
            $service = SL::getTripService();
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
            // 3. Valider le rôle chauffeur et la propriété du véhicule
            TripValidator::validateDriverRole($userId);
            TripValidator::validateVehicleOwnership($userId, (int)($data['voiture_id'] ?? 0));

            // 4. Créer le trajet via le service
            $service = SL::getTripService();
            $trajet = $service->createTrip($data, $userId);

            // 5. Retourner le trajet créé avec message d'avertissement
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
     * GET /api/user/trajets
     */
    public static function myTrips(): void
    {
        $userId = ControllerHelper::getAuthUserId();

        try {
            $repo = SL::getTrajetRepository();
            $trajets = $repo->getTrajetsByUserId($userId);
            $upcoming = self::filterUpcomingTrips($trajets);

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

            // Initialiser les services via ServiceLocator
            $cancellationService = SL::getCancellationService();
            $emailService = SL::getEmailService();
            $trajetRepo = SL::getTrajetRepository();
            $participationRepo = SL::getParticipationRepository();

            // Annuler le trajet (transaction gérée dans le service)
            $result = $cancellationService->cancelTripAsDriver($tripId, $userId, $reason);

            // Envoyer les emails de notification aux passagers
            $trajet = $trajetRepo->getTrajetDetail($tripId);
            $participants = $participationRepo->findByTrip($tripId);
            $driver = SL::getUserRepository()->getUserById($userId);
            $driverName = $driver ? ($driver['nom'] . ' ' . $driver['prenom']) : 'Le chauffeur';

            foreach ($participants as $participant) {
                $emailService->sendCancellationNotification(
                    $participant,
                    $trajet,
                    $driverName,
                    $trajet['prix'] ?? 0,
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

    /**
     * Filtre les trajets pour ne garder que ceux à venir (non annulés, date future)
     * @param array $trajets Liste des trajets
     * @return array Trajets à venir réindexés
     */
    private static function filterUpcomingTrips(array $trajets): array
    {
        $now = new \DateTime('now');
        return array_values(array_filter($trajets, function (array $t) use ($now) {
            // Exclure les trajets annulés
            if (($t['statut'] ?? null) === 'annule') {
                return false;
            }

            $date = $t['date_depart'] ?? null;
            if (!$date) {
                return true; // Garder si pas de date (sécurité)
            }

            try {
                $time = $t['heure_depart'] ?? '00:00:00';
                $dt = new \DateTime($date . ' ' . $time);
                return $dt >= $now;
            } catch (\Throwable $e) {
                return true; // En cas d'erreur, garder le trajet par sécurité
            }
        }));
    }
}
