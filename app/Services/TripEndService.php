<?php

namespace App\Services;

use App\Repositories\TrajetRepository;
use App\Repositories\ParticipationRepository;
use App\Repositories\UserRepository;
use App\Validators\TripEndValidator;
use App\Factories\DatabaseFactory;

/**
 * Service pour arrêter un trajet (chauffeur)
 * Valide les conditions, met à jour le statut du trajet à 'termine'
 * et envoie des emails aux participants
 */
class TripEndService
{
    private TrajetRepository $trajetRepo;
    private ParticipationRepository $participationRepo;
    private UserRepository $userRepo;
    private EmailService $emailService;

    public function __construct(
        TrajetRepository $trajetRepo = null,
        ParticipationRepository $participationRepo = null,
        UserRepository $userRepo = null,
        EmailService $emailService = null
    ) {
        $db = DatabaseFactory::getConnection();
        $this->trajetRepo = $trajetRepo ?? new TrajetRepository($db);
        $this->participationRepo = $participationRepo ?? new ParticipationRepository($db);
        $this->userRepo = $userRepo ?? new UserRepository($db);
        $this->emailService = $emailService ?? new EmailService();
    }

    /**
     * Arrête un trajet (change statut de 'en_cours' à 'termine')
     * Envoie des emails aux participants confirmés
     * @throws \Exception
     */
    public function endTrip(int $trajetId, int $userId): array
    {
        try {
            // 1. Validation métier
            TripEndValidator::validate($trajetId, $userId);

            // 2. Récupérer le trajet complet
            $trajet = $this->trajetRepo->getTrajetDetail($trajetId);

            // 3. Mettre à jour le statut du trajet
            $db = DatabaseFactory::getConnection();
            $sql = "UPDATE covoiturage SET statut = 'termine' WHERE covoiturage_id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $trajetId]);

            // 4. Récupérer tous les participants confirmés
            $participants = $this->participationRepo->findByTrajetAndStatus($trajetId, 'confirmee');

            // 5. Envoyer email à chaque participant
            foreach ($participants as $participant) {
                $user = $this->userRepo->findById($participant['utilisateur_id']);
                if ($user) {
                    $this->emailService->sendEndTripNotification(
                        [
                            'email' => $user->email,
                            'prenom' => $user->prenom,
                            'pseudo' => $user->pseudo
                        ],
                        [
                            'date_depart' => $trajet['date_depart'],
                            'heure_depart' => $trajet['heure_depart'],
                            'lieu_depart' => $trajet['lieu_depart'],
                            'lieu_arrivee' => $trajet['lieu_arrivee'],
                            'covoiturage_id' => $trajetId
                        ]
                    );
                }
            }

            return [
                'success' => true,
                'message' => 'Trajet terminé avec succès. Les participants ont été notifiés.',
                'trajet_id' => $trajetId,
                'statut' => 'termine',
                'nb_participants_notifies' => count($participants)
            ];
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
