<?php

namespace App\Controllers;

use App\Factories\ServiceLocator as SL;
use App\Services\TripService;
use App\Validators\QueryValidator;
use App\Validators\CancellationValidator;
use App\Validators\TripValidator;
use App\Validators\TripCreationValidator;
use App\DTO\CreateTripRequest;
use App\Helpers\ControllerHelper;
use App\Core\Request;
use App\Core\Response;
use Exception;

/**
 * Contrôleur des trajets.
 * Routes: GET /api/trajets, POST /api/trajets, GET /api/user/trajets, etc.
 * Architecture: Request → DTO → Validator → Service → Response
 */
class TrajetController
{
    /**
     * Recherche de trajets
     * GET /api/trajets?departure=Paris&arrival=Lyon&date=2025-12-25
     */
    public static function search(Request $req): void
    {
        $query = $req->getQueryParams();

        // Validation paramètres principaux
        try {
            $validated = QueryValidator::validateTrajetSearch($query);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $code = str_contains($msg, 'Format de date') ? 'INVALID_DATE' : 'MISSING_FIELDS';
            Response::json(400, [
                'success' => false,
                'error' => ['code' => $code, 'message' => $msg]
            ]);
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
            
            Response::json(200, [
                'success' => true,
                'data' => ['items' => $payload, 'count' => count($payload)]
            ]);
        } catch (\Exception $e) {
            Response::json(500, [
                'success' => false,
                'error' => ['code' => 'SEARCH_FAILED', 'message' => 'Erreur lors de la recherche. Veuillez réessayer.']
            ]);
        }
    }

    /**
     * Suggestions de dates pour un trajet
     * GET /api/trajets/suggestions?departure=Paris&arrival=Lyon
     */
    public static function suggestions(Request $req): void
    {
        $query = $req->getQueryParams();
        
        try {
            $validated = QueryValidator::validateDateSuggestions($query);
        } catch (\Exception $e) {
            Response::json(400, [
                'success' => false,
                'error' => ['code' => 'MISSING_FIELDS', 'message' => $e->getMessage()]
            ]);
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
            Response::json(200, ['success' => true, 'data' => ['suggestions' => $suggestions]]);
        } catch (\Exception $e) {
            Response::json(500, [
                'success' => false,
                'error' => ['code' => 'SEARCH_FAILED', 'message' => 'Erreur lors de la recherche. Veuillez réessayer.']
            ]);
        }
    }

    /**
     * Récupère le détail complet d'un covoiturage
     * GET /api/trajets/detail?id={id}
     */
    public static function show(Request $req): void
    {
        $query = $req->getQueryParams();
        $id = $query['id'] ?? null;

        try {
            $id = TripValidator::validateTripId($id);
        } catch (Exception $e) {
            Response::json(400, [
                'success' => false,
                'error' => ['code' => 'INVALID_ID', 'message' => $e->getMessage()]
            ]);
            return;
        }

        try {
            $service = SL::getTripService();
            $detail = $service->detail($id);

            // Si vide, le trajet n'existe pas
            if (empty($detail)) {
                Response::json(404, [
                    'success' => false,
                    'error' => ['code' => 'NOT_FOUND', 'message' => 'Trajet introuvable']
                ]);
                return;
            }

            Response::json(200, ['success' => true, 'data' => $detail]);
        } catch (\Exception $e) {
            error_log('[TrajetController::show] Exception : ' . $e->getMessage());
            Response::json(500, [
                'success' => false,
                'error' => ['code' => 'SERVER_ERROR', 'message' => 'Erreur lors de la récupération du trajet']
            ]);
        }
    }
    /**
     * Créer un nouveau trajet
     * POST /api/trajets
     * Body: { lieu_depart, lieu_arrivee, date_depart, heure_depart, nb_places, prix_personne, voiture_id, ... }
     */
    public static function create(Request $req): void
    {
        try {
            // 1. Récupérer l'utilisateur authentifié
            $userId = ControllerHelper::getAuthUserId();

            // 2. DTO : Transformer tableau → objet typé
            $tripDto = CreateTripRequest::fromArray($req->getJsonBody());
            
            // 3. Validation métier : Règles business (date, prix, places, etc.)
            TripCreationValidator::validate($tripDto);
            
            // 4. Validation métier : Rôle chauffeur et propriété du véhicule
            TripValidator::validateDriverRole($userId);
            TripValidator::validateVehicleOwnership($userId, $tripDto->voitureId);

            // 5. Service : Créer le trajet
            $service = SL::getTripService();
            $data = [
                'lieu_depart' => $tripDto->lieuDepart,
                'lieu_arrivee' => $tripDto->lieuArrivee,
                'date_depart' => $tripDto->dateDepart,
                'heure_depart' => $tripDto->heureDepart,
                'nb_places' => $tripDto->nbPlaces,
                'prix_personne' => $tripDto->prixPersonne,
                'voiture_id' => $tripDto->voitureId,
                'duree_estimee' => $tripDto->dureeEstimee,
                'preferences' => $tripDto->preferences
            ];
            $trajet = $service->createTrip($data, $userId);

            // 6. Response : Succès
            Response::json(201, [
                'success' => true,
                'data' => $trajet,
                'message' => 'Trajet créé avec succès. Rappel : 2 crédits seront prélevés par la plateforme pour chaque participation.'
            ]);

        } catch (\InvalidArgumentException $e) {
            // Erreur DTO (champs manquants)
            Response::json(400, [
                'success' => false,
                'error' => ['code' => 'INVALID_JSON', 'message' => $e->getMessage()]
            ]);
        } catch (\App\Validators\Exception $e) {
            // Erreur validation métier
            Response::json(400, [
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]
            ]);
        } catch (Exception $e) {
            // Erreur service
            Response::json(500, [
                'success' => false,
                'error' => ['code' => 'SERVER_ERROR', 'message' => $e->getMessage()]
            ]);
        }
    }

    /**
     * Liste les prochains trajets du chauffeur connecté
     * GET /api/user/trajets
     */
    public static function myTrips(Request $req): void
    {
        try {
            $userId = ControllerHelper::getAuthUserId();

            // Repository : Récupérer tous les trajets
            $repo = SL::getTrajetRepository();
            $trajets = $repo->getTrajetsByUserId($userId);
            
            // Service : Filtrer pour ne garder que ceux à venir (✅ déplacé du controller)
            $service = SL::getTripService();
            $upcoming = $service->filterUpcoming($trajets);

            Response::json(200, ['success' => true, 'data' => $upcoming]);
        } catch (Exception $e) {
            Response::json(500, [
                'success' => false,
                'error' => ['code' => 'SERVER_ERROR', 'message' => 'Erreur lors de la récupération des trajets']
            ]);
        }
    }

    /**
     * Annule un covoiturage en tant que chauffeur
     * POST /api/trajets/{id}/annuler
     * Body: { raison?: string }
     */
    public static function cancelTrip(Request $req): void
    {
        try {
            // 1. Récupérer l'ID du trajet depuis les paramètres dynamiques
            $tripId = ControllerHelper::getPathParam(0);
            $userId = ControllerHelper::getAuthUserId();

            // 2. Récupérer la raison optionnelle
            $body = $req->getJsonBody();
            $reason = $body['raison'] ?? null;

            // 3. Validation
            try {
                $validated = CancellationValidator::validateTripCancellation((int)$tripId, $userId, $reason);
                $tripId = $validated['trip_id'];
                $reason = $validated['reason'];
            } catch (Exception $e) {
                Response::json(400, [
                    'success' => false,
                    'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]
                ]);
                return;
            }

            // 4. Service : Annuler le trajet (transaction gérée)
            $cancellationService = SL::getCancellationService();
            $result = $cancellationService->cancelTripAsDriver($tripId, $userId, $reason);

            // 5. Envoyer les emails de notification aux passagers
            $emailService = SL::getEmailService();
            $trajetRepo = SL::getTrajetRepository();
            $participationRepo = SL::getParticipationRepository();
            $userRepo = SL::getUserRepository();

            $trajet = $trajetRepo->getTrajetDetail($tripId);
            $participants = $participationRepo->findByTrip($tripId);
            $driver = $userRepo->getUserById($userId);
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

            // 6. Response : Succès
            Response::json(200, [
                'success' => true,
                'message' => 'Trajet annulé avec succès',
                'participants_notified' => count($participants)
            ]);

        } catch (Exception $e) {
            error_log('[TrajetController::cancelTrip] Exception : ' . $e->getMessage());
            Response::json(500, [
                'success' => false,
                'error' => ['code' => 'SERVER_ERROR', 'message' => 'Erreur lors de l\'annulation du trajet']
            ]);
        }
    }
}
