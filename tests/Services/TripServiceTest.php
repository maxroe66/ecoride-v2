<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Services\TripService;

final class TripServiceTest extends TestCase
{
    private function makeRepo(array $resultsSearch, array $resultsSuggestions): object
    {
        return new class($resultsSearch, $resultsSuggestions) implements \App\Repositories\TrajetRepositoryInterface {
            public function __construct(private array $s, private array $d) {}
            public function searchTrajets(string $dep,string $arr,string $date): array { return $this->s; }
            public function searchTrajetsWithFilters(string $dep,string $arr,string $date,?bool $eco,?float $price,?int $dur,?int $rating): array { return $this->s; }
            public function getNextAvailableDates(string $dep,string $arr,int $limit): array { return $this->d; }
            public function getNextAvailableDatesWithFilters(string $dep,string $arr,int $limit,?bool $eco,?float $price,?int $dur,?int $rating): array { return $this->d; }
            public function getTrajetDetail(int $id): array { return []; }
            public function createTrajet(\App\Models\Trajet $trajet): int { return 1; }
            public function getTrajetsByUserId(int $userId): array { return []; }
        };
    }

    public function testSearchWithoutFilters(): void
    {
        $repo = $this->makeRepo([
            ['covoiturage_id'=>1,'date_depart'=>'2025-12-15','heure_depart'=>'08:00:00','lieu_depart'=>'Paris','heure_arrivee'=>'12:00:00','lieu_arrivee'=>'Lyon','nb_places'=>3,'prix_personne'=>'20.00','est_ecologique'=>1,'pseudo'=>'John','utilisateur_id'=>5]
        ], []);
        $service = new TripService($repo);
        $res = $service->search('Paris','Lyon','2025-12-15', null, null, null, null);
        $this->assertCount(1, $res);
        $norm = TripService::normalize($res[0]);
        $this->assertSame(1, $norm['covoiturage_id']);
    }

    public function testSuggestionsWithFilters(): void
    {
        $repo = $this->makeRepo([], [ ['date'=>'2025-12-15','count'=>2], ['date'=>'2025-12-16','count'=>1] ]);
        $service = new TripService($repo);
        $res = $service->suggestions('Paris','Lyon',3,true,30.0,120,4);
        $this->assertCount(2, $res);
        $this->assertSame('2025-12-15', $res[0]['date']);
    }
}