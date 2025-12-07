<?php

namespace App\Controllers;

use App\Factories\ServiceLocator as SL;
use App\Validators\QueryValidator;
use App\Validators\AvisValidator;
use App\DTO\CreateAvisRequest;
use App\Exceptions\ValidationException;
use App\Models\Avis;
use App\Helpers\ControllerHelper;
use App\Core\Request;
use App\Core\Response;

/**
 * Contrôleur des avis.
 * Routes: GET /api/avis, GET /api/avis/stats, POST /api/avis
 * Architecture: Request → DTO → Validator → Service → Response
 */
class AvisController
{
    /**
     * Liste les avis d'un covoiturage
     * GET /api/avis?covoiturage_id=123
     */
    public static function list(Request $req): void
    {
        $query = $req->getQueryParams();
        try {
            $rideId = QueryValidator::validateRideId($query); // Exception 400 si invalide
        } catch (\Exception $e) {
            Response::json(400, ['success' => false,'error' => ['code' => 'INVALID_PARAM','message' => 'Paramètre covoiturage_id requis']]);
            return;
        }

        $service = SL::getReviewService();
        $avis = $service->listForRide($rideId);
        // Utiliser la méthode toArray() du Model
        $payload = array_map(fn(Avis $a) => $a->toArray(), $avis);

        Response::json(200, ['success' => true,'data' => [
            'items' => $payload,
            'count' => count($payload),
            'average' => $service->averageForRide($rideId)
        ]]);
    }

    /**
     * Statistiques des avis d'un covoiturage
     * GET /api/avis/stats?covoiturage_id=123
     */
    public static function stats(Request $req): void
    {
        $query = $req->getQueryParams();
        try {
            $rideId = QueryValidator::validateRideId($query);
        } catch (\Exception $e) {
            Response::json(400, ['success' => false,'error' => ['code' => 'INVALID_PARAM','message' => 'Paramètre covoiturage_id requis']]);
            return;
        }
        $service = SL::getReviewService();
        $avg = $service->averageForRide($rideId);
        $count = $service->countForRide($rideId);
        Response::json(200, ['success' => true,'data' => ['covoiturage_id' => $rideId,'average' => $avg,'count' => $count]]);
    }

    /**
     * Créer un avis pour un covoiturage
     * POST /api/avis
     * Body: { covoiturage_id, note, commentaire? }
     */
    public static function create(Request $req): void
    {
        // Récupérer l'utilisateur authentifié (via middleware)
        $authenticatedUserId = ControllerHelper::getAuthUserId();

        try {
            // 1. DTO : Transformer tableau → objet typé + validation structurelle
            $avisDto = CreateAvisRequest::fromArray($req->getJsonBody());
            
            // 2. Validator : Validation métier (note 1-5, longueur commentaire, etc.)
            AvisValidator::validate($avisDto);
            
            // 3. Service : Logique métier (vérifier participation, créer avis)
            // Sécurité: utiliser uniquement l'identité authentifiée via JWT
            $service = SL::getReviewService();
            $ok = $service->create(
                $avisDto->covoiturageId,
                $authenticatedUserId,
                $avisDto->note,
                $avisDto->commentaire
            );
            
            // 4. Response : Retourner succès
            if ($ok) {
                Response::json(201, [
                    'success' => true,
                    'data' => [
                        'message' => 'Avis créé avec succès',
                        'note' => $avisDto->note
                    ]
                ]);
            } else {
                Response::json(500, [
                    'success' => false,
                    'error' => [
                        'code' => 'PERSIST_FAILED',
                        'message' => 'Échec enregistrement avis'
                    ]
                ]);
            }
            
        } catch (ValidationException $e) {
            // Erreurs de validation métier (multiples messages)
            Response::json(422, [
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'messages' => $e->errors
                ]
            ]);
        } catch (\InvalidArgumentException $e) {
            // Erreur DTO (champs manquants)
            Response::json(400, [
                'success' => false,
                'error' => [
                    'code' => 'INVALID_INPUT',
                    'message' => $e->getMessage()
                ]
            ]);
        } catch (\Exception $e) {
            // Erreur service
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
