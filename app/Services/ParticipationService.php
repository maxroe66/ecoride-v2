<?php

namespace App\Services;

use App\Repositories\ParticipationRepository;
use App\Repositories\TrajetRepository;
use App\Repositories\UserRepository;
use App\Validators\ParticipationValidator;
use Exception;

class ParticipationService
{
    private ParticipationRepository $participationRepo;
    private TrajetRepository $trajetRepo;
    private UserRepository $userRepo;

    public function __construct(
        ParticipationRepository $participationRepo,
        TrajetRepository $trajetRepo,
        UserRepository $userRepo
    ) {
        $this->participationRepo = $participationRepo;
        $this->trajetRepo = $trajetRepo;
        $this->userRepo = $userRepo;
    }

    /**
     * Demander une participation (1ère étape)
     * Crée la participation avec statut='demandee'
     */
    public function requestParticipation(int $userId, int $covoiturageId, int $nbPlaces = 1): array
    {
        try {
            // 1. Récupérer le trajet
            $trajet = $this->trajetRepo->getTrajetDetail($covoiturageId);
            if (empty($trajet)) {
                throw new Exception('Le covoiturage n\'existe pas.');
            }

            // 2. Récupérer l'utilisateur
            $user = $this->userRepo->findById($userId);
            if ($user === null) {
                throw new Exception('Utilisateur non trouvé.');
            }

            // 3. Valider avec le Validator
            ParticipationValidator::validateParticipation(
                userId: $userId,
                covoiturageId: $covoiturageId,
                nbPlaces: $nbPlaces,
                userCredit: $user->credit,
                tripPrice: (float)$trajet['prix_personne'],
                tripAvailableSeats: (int)$trajet['nb_places']
            );

            // 4. Vérifier pas de doublon
            $existing = $this->participationRepo->findByUserAndTrip($userId, $covoiturageId);
            if ($existing !== null) {
                throw new Exception('Vous participez déjà à ce covoiturage.');
            }

            // 5. Créer la participation
            $participationId = $this->participationRepo->create($userId, $covoiturageId, $nbPlaces);

            // 6. Retourner les données
            return [
                'success' => true,
                'participation_id' => $participationId,
                'statut' => 'demandee',
                'montant' => (float)$trajet['prix_personne'] * $nbPlaces,
                'message' => 'Participation créée. Merci de confirmer votre demande.'
            ];

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Valider une participation (1ère confirmation)
     * Change le statut de 'demandee' à 'en_attente_validation'
     */
    public function validateParticipation(int $participationId): array
    {
        try {
            // 1. Récupérer la participation
            $participation = $this->participationRepo->findById($participationId);
            if ($participation === null) {
                throw new Exception('Participation non trouvée.');
            }

            // 2. Vérifier que le statut est 'demandee'
            if ($participation['statut'] !== 'demandee') {
                throw new Exception('Impossible de valider une participation avec le statut : ' . $participation['statut']);
            }

            // 3. Changer le statut
            $this->participationRepo->updateStatus($participationId, 'en_attente_validation');

            // 4. Retourner la participation mise à jour
            return [
                'success' => true,
                'participation_id' => $participationId,
                'statut' => 'en_attente_validation',
                'message' => 'En attente de confirmation finale. Cliquez sur "Valider" pour confirmer.'
            ];

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Confirmer une participation (2ème confirmation finale)
     * Débite le crédit, décrémente les places, change le statut à 'validee'
     */
    public function confirmParticipation(int $participationId): array
    {
        try {
            // 1. Récupérer la participation
            $participation = $this->participationRepo->findById($participationId);
            if ($participation === null) {
                throw new Exception('Participation non trouvée.');
            }

            // 2. Vérifier que le statut est 'en_attente_validation'
            if ($participation['statut'] !== 'en_attente_validation') {
                throw new Exception('Impossible de confirmer une participation avec le statut : ' . $participation['statut']);
            }

            // 3. Récupérer le trajet pour le prix
            $trajet = $this->trajetRepo->getTrajetDetail($participation['covoiturage_id']);
            if (empty($trajet)) {
                throw new Exception('Le covoiturage n\'existe pas.');
            }

            // 4. Calculer le montant à débiter
            $montantADebiter = (float)$trajet['prix_personne'] * $participation['nb_places'];

            // 5. DÉBITER LE CRÉDIT
            $this->userRepo->updateCredit(
                userId: $participation['utilisateur_id'],
                amount: $montantADebiter,
                type: 'debit'
            );

            // 6. DÉCRÉMENTER LES PLACES
            $this->trajetRepo->updateNbPlaces(
                covoiturageId: $participation['covoiturage_id'],
                amount: -$participation['nb_places']
            );

            // 7. Changer le statut à 'confirmee' (pas 'validee')
            // 'validee' est utilisé APRÈS la fin du trajet quand le passager confirme
            $this->participationRepo->updateStatus($participationId, 'confirmee');

            // 8. Retourner la confirmation
            return [
                'success' => true,
                'participation_id' => $participationId,
                'statut' => 'confirmee',
                'montant_debite' => $montantADebiter,
                'message' => 'Participation confirmée ! Votre crédit a été débité et votre place est réservée.'
            ];

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}