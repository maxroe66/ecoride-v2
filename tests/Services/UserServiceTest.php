<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Services\UserService;
use App\Models\User;

final class UserServiceTest extends TestCase
{
    private function makeRepo(): object
    {
        return new class implements \App\Repositories\UserRepositoryInterface {
            public array $users = [];
            public function create(User $user): int { $user->id = count($this->users)+1; $this->users[] = $user; return $user->id; }
            public function findByEmail(string $email): ?User { foreach($this->users as $u){ if($u->email===$email) return $u; } return null; }
            public function findByPseudo(string $pseudo): ?User { foreach($this->users as $u){ if($u->pseudo===$pseudo) return $u; } return null; }
            public function findById(int $id): ?User { foreach($this->users as $u){ if(($u->id ?? 0)===$id) return $u; } return null; }
            public function emailExists(string $email): bool { return (bool)$this->findByEmail($email); }
            public function pseudoExists(string $pseudo): bool { return (bool)$this->findByPseudo($pseudo); }
            public function updateRole(int $userId, string $role): bool { $u = $this->findById($userId); if(!$u) return false; $u->role = $role; return true; }
            public function updatePreferences(int $userId, ?string $fumeur, ?string $animaux, ?string $autres_preferences): bool { return true; }
            public function getPreferences(int $userId): array { return []; }
        };
    }

    public function testCreateUserResponse(): void
    {
        $repo = $this->makeRepo();
        $service = new UserService($repo);
        $user = $service->createSignupUser('alice', 'alice@example.test', 'hash123');
        $this->assertNotNull($user);
        $found = $service->findByEmailOrPseudo('alice@example.test');
        $this->assertSame($user->id, $found->id);
        $resp = $service->toResponse($user, 'tokenXYZ');
        $this->assertSame('alice', $resp['pseudo']);
        $this->assertSame('tokenXYZ', $resp['token']);
    }
}