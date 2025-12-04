<?php

namespace App\Services;

use App\Repositories\TrajetRepository;
use App\Repositories\ParticipationRepository;
use App\Repositories\UserRepository;
use App\Repositories\CreditOperationRepository;

class CancellationService
{
    private TrajetRepository $trajetRepo;
    private ParticipationRepository $participationRepo;
    private UserRepository $userRepo;
    private CreditOperationRepository $creditOpRepo;

    public function __construct(
        TrajetRepository $trajetRepo,
        ParticipationRepository $participationRepo,
        UserRepository $userRepo,
        CreditOperationRepository $creditOpRepo
    ) {
        $this->trajetRepo = $trajetRepo;
        $this->participationRepo = $participationRepo;
        $this->userRepo = $userRepo;
        $this->creditOpRepo = $creditOpRepo;
    }



    public function cancelTripAsDriver(int $tripId, int $driverId, ?string $reason = null): array
    {
        // Récupérer le trajet
        $trajet = $this->trajetRepo->getTrajetDetail($tripId);
        if (empty($trajet) || (int)$trajet['utilisateur_id'] !== $driverId) {
            throw new \Exception('Trajet non trouvé ou accès refusé.');
        }
        // Vérifier que le trajet peut être annulé
        if ($trajet['statut'] === 'en_cours' || $trajet['statut'] === 'termine') {
            throw new \Exception('Impossible d\'annuler un trajet en cours ou terminé.');
        }

        // Mettre à jour le statut du trajet
        $this->trajetRepo->updateStatus($tripId, 'annule');

        // Rembourser les passagers
        $participations = $this->participationRepo->findByTrip($tripId);
        $participations = array_filter($participations, fn($p) => $p['statut'] === 'confirmee');
        foreach ($participations as $participation) {
            $userId = (int)$participation['utilisateur_id'];
            $nbPlaces = (int)$participation['nb_places'];
            $prixPersonne = (float)$trajet['prix_personne'];
            $montantRembourse = $nbPlaces * $prixPersonne;

            // Mettre à jour le crédit de l'utilisateur
            $this->userRepo->updateCredit($userId, $montantRembourse);

            // Créer une opération de crédit
            $this->creditOpRepo->create(
                userId: $userId,
                type: 'credit',
                amount: $montantRembourse
            );

            // Mettre à jour le statut de la participation
            $this->participationRepo->updateStatus((int)$participation['participation_id'], 'annulee');
        }

        return [
            'trip_id' => $tripId,
            'status' => 'annule',
            'refunded_passengers' => count($participations)
        ];
    }

    public function cancelParticipationAsPassenger(int $tripId, int $passengerId, ?string $reason = null): array
    {
        // Récupérer la participation
        $participation = $this->participationRepo->findByUserAndTrip($passengerId, $tripId);
        if (empty($participation) || $participation['statut'] !== 'confirmee') {
            throw new \Exception('Participation non trouvée ou non annulable.');
        }

        // Récupérer le trajet
        $trajet = $this->trajetRepo->getTrajetDetail($tripId);
        
        // Vérifier le statut du trajet
        if ($trajet['statut'] === 'en_cours' || $trajet['statut'] === 'termine') {
            throw new \Exception('Impossible d\'annuler une participation pour un trajet en cours ou terminé.');
        }

        // Mettre à jour le statut de la participation
        $this->participationRepo->updateStatus((int)$participation['participation_id'], 'annulee');

        // Rembourser le passager
        $nbPlaces = (int)$participation['nb_places'];
        $prixPersonne = (float)$trajet['prix_personne'];
        $montantRembourse = $nbPlaces * $prixPersonne;

        // Mettre à jour le crédit de l'utilisateur
        $this->userRepo->updateCredit($passengerId, $montantRembourse);

        // Créer une opération de crédit
        $this->creditOpRepo->create(
            userId: $passengerId,
            type: 'credit',
            amount: $montantRembourse
        );

        // Libérer les places du trajet
        $this->trajetRepo->updatePlaces($tripId, $nbPlaces);

        return [
            'participation_id' => (int)$participation['participation_id'],
            'status' => 'annulee',
            'refunded_amount' => $montantRembourse
        ];
    }
}