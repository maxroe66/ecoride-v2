<?php
/**
 * Test unitaire du service d'historique
 * Vérifie que:
 * 1. Les rôles (chauffeur/passager) sont bien inclus
 * 2. La structure des données est cohérente
 * 3. Les participations annulées apparaissent dans le filtre "annule"
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Services\HistoryService;
use App\Repositories\TrajetRepository;
use App\Repositories\ParticipationRepository;

final class HistoryServiceTest extends TestCase
{
    /**
     * Mock simple pour TrajetRepository
     */
    private function makeTrajetRepoMock(): TrajetRepository
    {
        return new class extends TrajetRepository {
            private \PDO $mockDb;
            
            public function __construct()
            {
                // Ignorer le parent constructor pour les tests
            }

            public function getTrajetsByUserId(int $userId): array
            {
                return [
                    [
                        'covoiturage_id' => 1,
                        'utilisateur_id' => $userId,
                        'lieu_depart' => 'Paris',
                        'lieu_arrivee' => 'Lyon',
                        'date_depart' => '2025-12-15',
                        'heure_depart' => '09:00',
                        'statut' => 'planifie',
                        'prix_personne' => 50.0,
                        'nb_places' => 4,
                    ]
                ];
            }
        };
    }

    /**
     * Mock simple pour ParticipationRepository
     */
    private function makeParticipationRepoMock(): ParticipationRepository
    {
        return new class extends ParticipationRepository {
            public function __construct()
            {
                // Ignorer le parent constructor pour les tests
            }

            public function findByUserAndStatus(int $userId, string $status): array
            {
                if ($status === 'confirmee') {
                    return [
                        [
                            'participation_id' => 10,
                            'covoiturage_id' => 2,
                            'utilisateur_id' => $userId,
                            'lieu_depart' => 'Lyon',
                            'lieu_arrivee' => 'Marseille',
                            'date_depart' => '2025-12-20',
                            'heure_depart' => '14:00',
                            'statut' => 'confirmee',
                            'prix_personne' => 30.0,
                            'nb_places' => 2,
                        ]
                    ];
                }
                return [];
            }

            public function findByUserId(int $userId): array
            {
                return [
                    [
                        'participation_id' => 10,
                        'covoiturage_id' => 2,
                        'utilisateur_id' => $userId,
                        'lieu_depart' => 'Lyon',
                        'lieu_arrivee' => 'Marseille',
                        'date_depart' => '2025-12-20',
                        'heure_depart' => '14:00',
                        'statut' => 'confirmee',
                        'prix_personne' => 30.0,
                        'nb_places' => 2,
                    ],
                    [
                        'participation_id' => 11,
                        'covoiturage_id' => 3,
                        'utilisateur_id' => $userId,
                        'lieu_depart' => 'Marseille',
                        'lieu_arrivee' => 'Nice',
                        'date_depart' => '2025-11-01',
                        'heure_depart' => '10:00',
                        'statut' => 'annulee',
                        'prix_personne' => 25.0,
                        'nb_places' => 1,
                    ]
                ];
            }
        };
    }

    public function testGetUserTripHistoryIncludesRoles(): void
    {
        $trajetRepo = $this->makeTrajetRepoMock();
        $participationRepo = $this->makeParticipationRepoMock();
        $service = new HistoryService($trajetRepo, $participationRepo);

        $userId = 1;
        $history = $service->getUserTripHistory($userId);

        // Vérifier qu'on a au moins 2 éléments (1 trajet + 1 participation)
        $this->assertGreaterThanOrEqual(2, count($history));

        // Vérifier que chaque élément a un rôle
        foreach ($history as $item) {
            $this->assertArrayHasKey('role', $item, 'Chaque trajet doit avoir un rôle');
            $this->assertContains($item['role'], ['chauffeur', 'passager'], 'Rôle doit être chauffeur ou passager');
        }

        // Vérifier qu'on a au moins un chauffeur et un passager
        $roles = array_column($history, 'role');
        $this->assertContains('chauffeur', $roles, 'Devrait avoir au moins un trajet en tant que chauffeur');
        $this->assertContains('passager', $roles, 'Devrait avoir au moins une participation en tant que passager');
    }

    public function testGetUserTripHistoryStructure(): void
    {
        $trajetRepo = $this->makeTrajetRepoMock();
        $participationRepo = $this->makeParticipationRepoMock();
        $service = new HistoryService($trajetRepo, $participationRepo);

        $userId = 1;
        $history = $service->getUserTripHistory($userId);

        // Vérifier la structure normalisée
        foreach ($history as $item) {
            $this->assertArrayHasKey('trajet_id', $item);
            $this->assertArrayHasKey('lieu_depart', $item);
            $this->assertArrayHasKey('lieu_arrivee', $item);
            $this->assertArrayHasKey('date_depart', $item);
            $this->assertArrayHasKey('heure_depart', $item);
            $this->assertArrayHasKey('role', $item);
            $this->assertArrayHasKey('statut', $item);
            $this->assertArrayHasKey('prix_personne', $item);
            $this->assertArrayHasKey('nb_places', $item);
        }
    }

    public function testGetHistoryByStatusFiltersAnnulees(): void
    {
        $trajetRepo = $this->makeTrajetRepoMock();
        $participationRepo = $this->makeParticipationRepoMock();
        $service = new HistoryService($trajetRepo, $participationRepo);

        $userId = 1;
        $annulees = $service->getUserTripHistoryByStatus($userId, 'annulee');

        // Devrait avoir au moins la participation annulée
        $this->assertGreaterThanOrEqual(1, count($annulees), 'Devrait avoir au moins une participation annulée');

        // Vérifier que tous les éléments ont le statut 'annulee'
        foreach ($annulees as $item) {
            $this->assertEquals('annulee', $item['statut'], 'Le statut doit être annulee');
        }
    }
}
