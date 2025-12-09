<?php

namespace App\Services;

use App\Repositories\ParticipationRepository;
use App\Repositories\UserRepository;
use App\Repositories\CreditOperationRepository;
use App\Validators\ParticipantValidationValidator;
use App\Factories\DatabaseFactory;

/**
 * Service pour valider une participation par un passager
 * Change le statut de 'confirmee' à 'validee'
 * Met à jour les crédits du chauffeur
 */
class ParticipantValidationService
{
    private ParticipationRepository $participationRepo;
    private UserRepository $userRepo;
    private CreditOperationRepository $creditOpRepo;

    public function __construct(
        ParticipationRepository $participationRepo = null,
        UserRepository $userRepo = null,
        CreditOperationRepository $creditOpRepo = null
    ) {
        $db = DatabaseFactory::getConnection();
        $this->participationRepo = $participationRepo ?? new ParticipationRepository($db);
        $this->userRepo = $userRepo ?? new UserRepository($db);
        $this->creditOpRepo = $creditOpRepo ?? new CreditOperationRepository($db);
    }

    /**
     * Valide une participation
     * Change le statut à 'validee' et crédite le chauffeur
     * @throws \Exception
     */
    public function validateParticipation(int $participationId, int $userId): array
    {
        try {
            // 1. Validation métier
            ParticipantValidationValidator::validate($participationId, $userId);

            // 2. Récupérer la participation
            $participation = $this->participationRepo->findById($participationId);
            $trajetId = $participation['covoiturage_id'];

            // 3. Récupérer le trajet pour avoir le conducteur et le prix
            $db = DatabaseFactory::getConnection();
            $sql = "SELECT conducteur_id, prix_personne FROM covoiturage WHERE covoiturage_id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $trajetId]);
            $trajet = $stmt->fetch(\PDO::FETCH_ASSOC);

            // 4. Mettre à jour le statut de la participation
            $this->participationRepo->updateStatus($participationId, 'validee');

            // 5. Créditer le chauffeur (prix_personne * nb_places)
            $montantCredit = $trajet['prix_personne'] * $participation['nb_places'];
            $this->creditOpRepo->create(
                (int)$trajet['conducteur_id'],
                'credit',
                $montantCredit,
                'Trajet complété - Covoiturage #' . $trajetId
            );

            // 6. Mettre à jour les crédits de l'utilisateur en base
            $driver = $this->userRepo->findById((int)$trajet['conducteur_id']);
            $newCredit = ($driver->credit ?? 0) + $montantCredit;
            
            $updateSql = "UPDATE utilisateur SET credit = :credit WHERE utilisateur_id = :id";
            $updateStmt = $db->prepare($updateSql);
            $updateStmt->execute([
                ':credit' => $newCredit,
                ':id' => (int)$trajet['conducteur_id']
            ]);

            return [
                'success' => true,
                'message' => 'Participation validée avec succès',
                'participation_id' => $participationId,
                'statut' => 'validee',
                'montant_credit' => $montantCredit,
                'chauffeur_credite' => true
            ];
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
