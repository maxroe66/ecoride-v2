<?php

namespace App\Controllers;

use App\Validators\QueryValidator;
use App\Services\ReviewService;
use App\Models\Avis;
use App\Helpers\ControllerHelper;
use App\Core\Response;

/**
 * Contrôleur des avis.
 * Routes: GET /api/avis, GET /api/avis/stats, POST /api/avis
 * Conserve les formats de réponses existants.
 */
class AvisController
{
    public static function list(): void
    {
        parse_str($_SERVER['QUERY_STRING'] ?? '', $query);
        try {
            $rideId = QueryValidator::validateRideId($query); // Exception 400 si invalide
        } catch (\Exception $e) {
            Response::json(400, ['success' => false,'error' => ['code' => 'INVALID_PARAM','message' => 'Paramètre covoiturage_id requis']]);
            return;
        }

        $service = new ReviewService();
        $avis = $service->listForRide($rideId);
        $payload = array_map(fn(Avis $a) => [
            'covoiturage_id' => $a->rideId,
            'utilisateur_id' => $a->userId,
            'note' => $a->rating,
            'commentaire' => $a->comment,
            'date_creation' => $a->createdAt->format('Y-m-d H:i:s')
        ], $avis);

        Response::json(200, ['success' => true,'data' => [
            'items' => $payload,
            'count' => count($payload),
            'average' => $service->averageForRide($rideId)
        ]]);
    }

    public static function stats(): void
    {
        parse_str($_SERVER['QUERY_STRING'] ?? '', $query);
        try {
            $rideId = QueryValidator::validateRideId($query);
        } catch (\Exception $e) {
            Response::json(400, ['success' => false,'error' => ['code' => 'INVALID_PARAM','message' => 'Paramètre covoiturage_id requis']]);
            return;
        }
        $service = new ReviewService();
        $avg = $service->averageForRide($rideId);
        $count = $service->countForRide($rideId);
        Response::json(200, ['success' => true,'data' => ['covoiturage_id' => $rideId,'average' => $avg,'count' => $count]]);
    }

    public static function create(): void
    {
        // RÉcupérer l'utilisateur authentifié (via middleware)
        $authenticatedUserId = ControllerHelper::getAuthUserId();

        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            Response::json(400, ['success' => false,'error' => ['code' => 'INVALID_JSON','message' => 'Corps JSON invalide']]);
            return;
        }
        // Validation complète via QueryValidator tout en conservant format d'erreur
        try {
            $data = QueryValidator::validateAvisCreation($json);
        } catch (\App\Exceptions\ValidationException $ve) {
            Response::json(422, ['success' => false,'error' => ['code' => 'VALIDATION_FAILED','messages' => $ve->errors]]);
            return;
        } catch (\Exception $e) {
            // Erreur de note etc. (déjà status dans exception mais on force 422 pour cohérence format actuel)
            Response::json(422, ['success' => false,'error' => ['code' => 'VALIDATION_FAILED','messages' => [$e->getMessage()]]]);
            return;
        }

        // Sécurité: ignorer tout utilisateur_id fourni par le client
        // et utiliser uniquement l'identité authentifiée via JWT.
        $rideId = $data['rideId'];
        $rating = $data['rating'];
        $comment = $data['comment'];

        $service = new ReviewService();
        $ok = $service->create($rideId, $authenticatedUserId, $rating, $comment);
        if ($ok) {
            Response::json(201, ['success' => true,'data' => ['message' => 'Créé','note' => $data['rating']]]);
        } else {
            Response::json(500, ['success' => false,'error' => ['code' => 'PERSIST_FAILED','message' => 'Échec enregistrement avis']]);
        }
    }
}
