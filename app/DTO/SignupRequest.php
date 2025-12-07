<?php

namespace App\DTO;

/**
 * DTO représentant une requête d'inscription.
 * Validation structurelle uniquement (champs présents, types corrects).
 */
class SignupRequest
{
    public function __construct(
        public readonly string $pseudo,
        public readonly string $email,
        public readonly string $password
    ) {
    }

    /**
     * Crée un SignupRequest depuis un tableau JSON décodé.
     * Valide uniquement la présence des champs requis.
     * 
     * @param array $data Données JSON décodées
     * @return self
     * @throws \InvalidArgumentException si champs manquants
     */
    public static function fromArray(array $data): self
    {
        $pseudo = trim((string)($data['pseudo'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');

        // Validation BASIQUE : champs présents
        if ($pseudo === '') {
            throw new \InvalidArgumentException('Le pseudo est requis');
        }

        if ($email === '') {
            throw new \InvalidArgumentException('L\'email est requis');
        }

        if ($password === '') {
            throw new \InvalidArgumentException('Le mot de passe est requis');
        }

        return new self($pseudo, $email, $password);
    }

    /**
     * Validation basique : tous les champs sont présents
     * 
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->pseudo !== '' 
            && $this->email !== '' 
            && $this->password !== '';
    }
}
