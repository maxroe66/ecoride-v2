<?php

namespace App\Services;

use App\Repositories\TrajetRepository;
use App\Validators\TripStartValidator;

/**
 * Service pour démarrer un trajet (chauffeur)
 * Valide les conditions et met à jour le statut du trajet à 'en_cours'
 */
class TripStartService
{
    private TrajetRepository $trajetRepo;

    public function __construct(TrajetRepository $trajetRepo = null)
    {
        $this->trajetRepo = $trajetRepo ?? new TrajetRepository();
    }

    /**
     * Démarre un trajet (change statut de 'planifie' à 'en_cours')
     * @throws \Exception
     */
    public function startTrip(int $trajetId, int $userId): array
    {
        try {
            // 1. Validation métier
            TripStartValidator::validate($trajetId, $userId);

            // 2. Récupérer le trajet complet
            $trajet = $this->trajetRepo->getTrajetDetail($trajetId);

            // 3. Mettre à jour le statut
            $sql = "UPDATE covoiturage SET statut = 'en_cours' WHERE covoiturage_id = :id";
            $db = $GLOBALS['db'];
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $trajetId]);

            return [
                'success' => true,
                'message' => 'Trajet démarré avec succès',
                'trajet_id' => $trajetId,
                'statut' => 'en_cours',
                'lieu_depart' => $trajet['lieu_depart'],
                'lieu_arrivee' => $trajet['lieu_arrivee']
            ];
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
