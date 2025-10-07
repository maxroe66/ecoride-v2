<?php
namespace App\Core;

use App\Factories\AvisRepositoryFactory;
use App\Repositories\ResilientAvisRepository; // pour les hints
use App\Models\Avis;
use App\Core\Env; 

class Bootstrap
{
    public function __construct()
    {
        Env::load();
    }

    public function run(): void
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (str_starts_with($uri, '/api/')) {
            $this->handleApi($uri);
            return;
        }
        // Serve frontend index
        $frontend = __DIR__ . '/../../frontend/index.html';
        if (is_file($frontend)) {
            readfile($frontend);
        } else {
            echo '<h1>EcoRide</h1>';
        }
    }

    private function handleApi(string $uri): void
    {
        header('Content-Type: application/json');
        if ($uri === '/api/health') {
            echo json_encode(['success' => true, 'data' => 'ok']);
            return;
        }
        if (str_starts_with($uri, '/api/avis')) {
            $this->handleAvis($uri);
            return;
        }
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Endpoint']]);
    }

    private function repo(): ResilientAvisRepository
    {
        return AvisRepositoryFactory::get();
    }

    private function handleAvis(string $uri): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        parse_str($_SERVER['QUERY_STRING'] ?? '', $query);

        if ($method === 'GET' && $uri === '/api/avis') {
            $rideId = isset($query['covoiturage_id']) ? (int)$query['covoiturage_id'] : 0;
            if ($rideId <= 0) {
                http_response_code(400);
                echo json_encode(['success'=>false,'error'=>['code'=>'INVALID_PARAM','message'=>'Paramètre covoiturage_id requis']]);
                return;
            }
            $avis = $this->repo()->listForRide($rideId);
            $payload = array_map(fn(Avis $a) => [
                'covoiturage_id' => $a->rideId,
                'utilisateur_id' => $a->userId,
                'note' => $a->rating,
                'commentaire' => $a->comment,
                'date_creation' => $a->createdAt->format('Y-m-d H:i:s')
            ], $avis);
            echo json_encode(['success'=>true,'data'=>[
                'items'=>$payload,
                'count'=>count($payload),
                'average'=>$this->repo()->averageForRide($rideId)
            ]]);
            return;
        }

        if ($method === 'GET' && $uri === '/api/avis/stats') {
            $rideId = isset($query['covoiturage_id']) ? (int)$query['covoiturage_id'] : 0;
            if ($rideId <= 0) {
                http_response_code(400);
                echo json_encode(['success'=>false,'error'=>['code'=>'INVALID_PARAM','message'=>'Paramètre covoiturage_id requis']]);
                return;
            }
            $avg = $this->repo()->averageForRide($rideId);
            $count = count($this->repo()->listForRide($rideId)); // simple pour MVP
            echo json_encode(['success'=>true,'data'=>['covoiturage_id'=>$rideId,'average'=>$avg,'count'=>$count]]);
            return;
        }

        if ($method === 'POST' && $uri === '/api/avis') {
            $raw = file_get_contents('php://input');
            $json = json_decode($raw, true);
            if (!is_array($json)) {
                http_response_code(400);
                echo json_encode(['success'=>false,'error'=>['code'=>'INVALID_JSON','message'=>'Corps JSON invalide']]);
                return;
            }
            $errors = [];
            $rideId = (int)($json['covoiturage_id'] ?? 0); if ($rideId<=0) $errors[]='covoiturage_id invalide';
            $userId = (int)($json['utilisateur_id'] ?? 0); if ($userId<=0) $errors[]='utilisateur_id invalide';
            $rating = (int)($json['note'] ?? 0); if ($rating<1 || $rating>5) $errors[]='note doit être entre 1 et 5';
            $comment = isset($json['commentaire']) ? trim((string)$json['commentaire']) : null;
            if ($errors) {
                http_response_code(422);
                echo json_encode(['success'=>false,'error'=>['code'=>'VALIDATION_FAILED','messages'=>$errors]]);
                return;
            }
            $avis = new Avis($rideId,$userId,$rating,$comment);
            $ok = $this->repo()->add($avis);
            if ($ok) {
                http_response_code(201);
                echo json_encode(['success'=>true,'data'=>['message'=>'Créé','note'=>$rating]]);
            } else {
                http_response_code(500);
                echo json_encode(['success'=>false,'error'=>['code'=>'PERSIST_FAILED','message'=>'Échec enregistrement avis']]);
            }
            return;
        }

        http_response_code(404);
        echo json_encode(['success'=>false,'error'=>['code'=>'NOT_FOUND','message'=>'Endpoint avis']]);
    }
}
