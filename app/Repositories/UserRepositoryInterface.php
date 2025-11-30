<?php

namespace App\Repositories;

use App\Models\User;

interface UserRepositoryInterface
{
    public function create(User $user): int;
    public function findByEmail(string $email): ?User;
    public function findByPseudo(string $pseudo): ?User;
    public function emailExists(string $email): bool;
    public function pseudoExists(string $pseudo): bool;
}
