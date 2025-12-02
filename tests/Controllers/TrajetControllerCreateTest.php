<?php

namespace Tests\Controllers {

    use PHPUnit\Framework\TestCase;
    use App\Services\JwtService;
    use App\Factories\DatabaseFactory;
    use App\Controllers\TrajetController;
    use App\Controllers\TrajetControllerCreateTestStub;
    use PDO;

    // Utiliser la syntaxe entre accolades pour tous les espaces de noms
}

namespace App\Controllers {
    function file_get_contents($filename) {
        if ($filename === 'php://input') {
            return TrajetControllerCreateTestStub::$jsonBody ?? '{}';
        }
        return \file_get_contents($filename);
    }

    class TrajetControllerCreateTestStub {
        public static string $jsonBody = '{}';
    }
}

namespace Tests\Controllers {
    use PHPUnit\Framework\TestCase;
    use App\Services\JwtService;
    use App\Factories\DatabaseFactory;
    use App\Controllers\TrajetController;
    use App\Controllers\TrajetControllerCreateTestStub;
    use PDO;

    class TrajetControllerCreateTest extends TestCase
    {
        private ?PDO $pdo = null;
        private ?int $insertedVehicleId = null;

        protected function setUp(): void
        {
            $this->pdo = DatabaseFactory::getConnection();

            $stmt = $this->pdo->query("SELECT utilisateur_id FROM utilisateur WHERE utilisateur_id = 29");
            $user = $stmt->fetch();
            if (!$user) {
                $this->markTestSkipped('Utilisateur 29 manquant en base.');
            }

            $marqueId = null;
            $stmt = $this->pdo->query('SELECT marque_id FROM marque LIMIT 1');
            $row = $stmt->fetch();
            if ($row) {
                $marqueId = (int)$row['marque_id'];
            } else {
                $this->markTestSkipped('Aucune marque disponible en base.');
            }

            $this->pdo->beginTransaction();

            $immatriculation = 'TEST-' . uniqid();
            $stmt = $this->pdo->prepare('INSERT INTO voiture (modele, marque_id, immatriculation, energie, nb_places, couleur, date_premiere_immatriculation, utilisateur_id, est_ecologique) VALUES (:modele, :marque_id, :immatriculation, :energie, :nb_places, :couleur, :date_premiere_immatriculation, :utilisateur_id, :est_ecologique)');
            $stmt->execute([
                ':modele' => 'PHPUnit-Mobile',
                ':marque_id' => $marqueId,
                ':immatriculation' => $immatriculation,
                ':energie' => 'electrique',
                ':nb_places' => 4,
                ':couleur' => 'noir',
                ':date_premiere_immatriculation' => '2020-01-01',
                ':utilisateur_id' => 29,
                ':est_ecologique' => 1,
            ]);
            $this->insertedVehicleId = (int)$this->pdo->lastInsertId();
        }

        protected function tearDown(): void
        {
            if ($this->pdo && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->insertedVehicleId = null;
        }

        public function testCreateTripAsChauffeurReturnsSuccess(): void
        {
            $jwt = new JwtService();
            $token = $jwt->generate([
                'user_id' => 29,
                'pseudo' => 'testchauffeur',
                'email' => 'chauffeur@test.com',
            ]);

            $_COOKIE['ecoride_token'] = $token;
            $_SERVER['HTTP_HOST'] = 'localhost:8080';

            $body = [
                'lieu_depart' => 'Paris, Gare de Lyon',
                'lieu_arrivee' => 'Lyon, Part-Dieu',
                'date_depart' => '2025-12-10',
                'heure_depart' => '14:30',
                'nb_places' => 2,
                'prix_personne' => 20.50,
                'voiture_id' => $this->insertedVehicleId,
            ];
            TrajetControllerCreateTestStub::$jsonBody = json_encode($body);

            ob_start();
            TrajetController::create();
            $output = ob_get_clean();

            $this->assertNotEmpty($output, 'Aucune sortie du contrôleur.');
            $json = json_decode($output, true);
            $this->assertIsArray($json, 'Réponse JSON invalide');
            $this->assertTrue($json['success'] ?? false, 'La création du trajet aurait dû réussir');

            $data = $json['data'] ?? [];
            $this->assertEquals(29, $data['conducteur_id'] ?? null, 'conducteur_id incorrect');
            $this->assertEquals($this->insertedVehicleId, $data['voiture_id'] ?? null, 'voiture_id incorrect');
        }
    }
}
