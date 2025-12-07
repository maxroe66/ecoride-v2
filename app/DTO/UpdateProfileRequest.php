<?php

namespace App\DTO;

use InvalidArgumentException;

/**
 * DTO pour la mise à jour du profil utilisateur
 * Architecture: Controller → DTO (validation structurelle)
 */
readonly class UpdateProfileRequest
{
    public function __construct(
        public ?string $role = null,
        public ?array $vehicules = null,
        public ?array $preferences = null
    ) {}

    /**
     * Crée une instance depuis un tableau (ex: JSON décodé)
     * Validation structurelle uniquement (types)
     * @throws InvalidArgumentException si les types sont invalides
     */
    public static function fromArray(array $data): self
    {
        $role = $data['role'] ?? null;
        $vehicules = $data['vehicules'] ?? null;
        $preferences = $data['preferences'] ?? null;

        // Validation type
        if ($role !== null && !is_string($role)) {
            throw new InvalidArgumentException("Le champ 'role' doit être une chaîne.");
        }

        if ($vehicules !== null && !is_array($vehicules)) {
            throw new InvalidArgumentException("Le champ 'vehicules' doit être un tableau.");
        }

        if ($preferences !== null && !is_array($preferences)) {
            throw new InvalidArgumentException("Le champ 'preferences' doit être un tableau.");
        }

        return new self(
            $role ? trim($role) : null,
            $vehicules,
            $preferences
        );
    }
}
