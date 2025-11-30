<?php
namespace App\DTO;

use App\Models\User;

/**
 * DTO de sortie pour un utilisateur.
 * Normalise le format JSON déjà utilisé par l'API (invariance frontend).
 */
class UserResponse
{
    public static function fromUser(User $user, ?string $token = null): array
    {
        $base = [
            'utilisateur_id' => $user->id,
            'pseudo' => $user->pseudo,
            'email' => $user->email,
            'nom' => $user->nom,
            'prenom' => $user->prenom,
            'credit' => $user->credit,
            'type_utilisateur' => $user->type_utilisateur,
        ];
        if ($token) {
            $base['token'] = $token;
        }
        return $base;
    }
}
