<?php

namespace App\Services;

use App\Repositories\IncidentRepository;
use App\Repositories\ParticipationRepository;
use App\Validators\ProblemReportValidator;
use App\Factories\DatabaseFactory;

/**
 * Service pour gérer les incidents (problèmes de trajets)
 * Crée un incident et change le statut de la participation à 'probleme'
 */
class IncidentService
{
    private IncidentRepository $incidentRepo;
    private ParticipationRepository $participationRepo;
    private EmailService $emailService;

    public function __construct(
        IncidentRepository $incidentRepo = null,
        ParticipationRepository $participationRepo = null,
        EmailService $emailService = null
    ) {
        $db = DatabaseFactory::getConnection();
        $this->incidentRepo = $incidentRepo ?? new IncidentRepository($db);
        $this->participationRepo = $participationRepo ?? new ParticipationRepository($db);
        $this->emailService = $emailService ?? new EmailService();
    }

    /**
     * Crée un incident (signalé par un passager)
     * Change le statut de la participation à 'probleme'
     * Envoie une notification aux employés
     * @throws \Exception
     */
    public function reportProblem(int $participationId, int $userId, array $data): array
    {
        try {
            // 1. Validation métier
            ProblemReportValidator::validate($participationId, $userId, $data);

            // 2. Récupérer la participation
            $participation = $this->participationRepo->findById($participationId);
            $trajetId = $participation['covoiturage_id'];

            // 3. Créer l'incident
            $incidentId = $this->incidentRepo->create(
                $participationId,
                $trajetId,
                $userId,
                $data['reason'] ?? ''
            );

            // 4. Mettre à jour le statut de la participation
            $this->participationRepo->updateStatus($participationId, 'probleme');

            // 5. Log pour notification aux employés
            // En production, ce serait un email ou une notification
            error_log("⚠️ INCIDENT CRÉÉ #$incidentId | Participation: $participationId | Trajet: $trajetId | Passager: $userId");

            return [
                'success' => true,
                'message' => 'Problème signalé avec succès. Un employé vous contactera sous peu.',
                'incident_id' => $incidentId,
                'participation_id' => $participationId,
                'statut' => 'probleme',
                'next_step' => 'Un employé EcoRide vous contactera pour résoudre la situation'
            ];
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Récupère tous les incidents en attente (pour les employés)
     */
    public function getPendingIncidents(): array
    {
        return $this->incidentRepo->findPending();
    }

    /**
     * Résout un incident (utilisé par les employés)
     */
    public function resolveIncident(int $incidentId): array
    {
        try {
            // Récupérer l'incident
            $incident = $this->incidentRepo->findById($incidentId);
            if (!$incident) {
                throw new \Exception('Incident non trouvé');
            }

            // Résoudre l'incident
            $this->incidentRepo->resolve($incidentId);

            return [
                'success' => true,
                'message' => 'Incident résolu',
                'incident_id' => $incidentId
            ];
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
