<?php

namespace App\Services;

use App\Models\User;
use App\DTO\UserResponse;
use App\Repositories\UserRepositoryInterface;

/**
 * Service utilisateur : encapsule accès repository et formatage réponse.
 */
class UserService
{
    public function __construct(private UserRepositoryInterface $users)
    {
    }

    public function findByEmailOrPseudo(string $value): ?User
    {
        $user = $this->users->findByEmail($value);
        if (!$user) {
            $user = $this->users->findByPseudo($value);
        }
        return $user;
    }

    public function createSignupUser(string $pseudo, string $email, string $password): User
    {
        $user = new User('Anonymous', 'User', $email, $pseudo, $password, null, 20.00, 'standard');
        $id = $this->users->create($user);
        $user->id = $id;
        return $user;
    }

    public function toResponse(User $user, ?string $token = null): array
    {
        return UserResponse::fromUser($user, $token);
    }
}
