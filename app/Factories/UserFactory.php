<?php
namespace App\Factories;

use App\Models\User;

class UserFactory
{
    /**
     * Crée un utilisateur à partir des données d'inscription.
     * Note: Le hash du mot de passe est géré par le UserRepository lors de la création en base
     * afin d'éviter tout double-hash. Cette factory prépare simplement l'objet métier.
     */
    public static function fromSignup(string $pseudo, string $email, string $password): User
    {
        return new User(
            'Anonymous',
            'User',
            $email,
            $pseudo,
            $password,
            null,
            20.00,
            'standard'
        );
    }

    /**
     * Reconstruit un utilisateur depuis une ligne SQL.
     */
    public static function fromRow(array $row): User
    {
        $user = new User(
            $row['nom'] ?? 'Anonymous',
            $row['prenom'] ?? 'User',
            $row['email'] ?? '',
            $row['pseudo'] ?? '',
            $row['password'] ?? '',
            $row['telephone'] ?? null,
            isset($row['credit']) ? (float)$row['credit'] : 20.00,
            $row['type_utilisateur'] ?? 'standard'
        );
        if (isset($row['utilisateur_id'])) { $user->id = (int)$row['utilisateur_id']; }
        if (isset($row['date_creation'])) { $user->date_creation = (string)$row['date_creation']; }
        if (isset($row['suspendu'])) { $user->suspendu = (int)$row['suspendu']; }
        return $user;
    }
}
