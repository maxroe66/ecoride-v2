<?php
namespace App\DTO;

/**
 * DTO représentant une requête de login.
 * Fournit une factory à partir d'un tableau et une validation légère.
 */
class LoginRequest
{
    public function __construct(
        public readonly string $emailOrPseudo,
        public readonly string $password
    ) {}

    /**
     * Crée un LoginRequest depuis un tableau JSON décodé.
     * Laisse la validation métier plus poussée à QueryValidator si nécessaire.
     */
    public static function fromArray(array $data): self
    {
        $emailOrPseudo = trim((string)($data['email'] ?? $data['pseudo'] ?? ''));
        $password = (string)($data['password'] ?? '');
        return new self($emailOrPseudo, $password);
    }

    public function isValid(): bool
    {
        return $this->emailOrPseudo !== '' && $this->password !== '';
    }
}
