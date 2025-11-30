<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Services\ReviewService;
use App\Models\Avis;

final class ReviewServiceTest extends TestCase
{
    private function makeRepo(): object
    {
        return new class implements \App\Repositories\AvisRepositoryInterface {
            private array $store = [];
            public function add(Avis $avis): bool { $this->store[] = $avis; return true; }
            public function listForRide(int $rideId): array { return array_filter($this->store, fn($a)=>$a->rideId===$rideId); }
            public function averageForRide(int $rideId): float { $l=$this->listForRide($rideId); if(!$l) return 0.0; return array_sum(array_map(fn($a)=>$a->rating,$l))/count($l); }
        };
    }

    public function testCreateAndStats(): void
    {
        $repo = $this->makeRepo();
        $service = new ReviewService($repo);
        $service->create(10, 5, 4, 'Bien');
        $service->create(10, 6, 5, null);
        $this->assertSame(2, $service->countForRide(10));
        $this->assertEquals(4.5, $service->averageForRide(10));
        $list = $service->listForRide(10);
        $this->assertCount(2, $list);
    }
}