<?php

namespace App\Repositories;

use App\Models\User;

interface UserRepositoryInterface
{
    public function create(User $user): int;
    public function findByEmail(string $email): ?User;
    public function findByPseudo(string $pseudo): ?User;
    public function findById(int $id): ?User;
    public function emailExists(string $email): bool;
    public function pseudoExists(string $pseudo): bool;
    public function updateRole(int $userId, string $role): bool;
    public function updatePreferences(int $userId, ?string $fumeur, ?string $animaux, ?string $autres_preferences): bool;
    public function getPreferences(int $userId): array;
}
