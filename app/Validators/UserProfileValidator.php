<?php

namespace App\Validators;

use App\Exceptions\ValidationException;

class UserProfileValidator
{
    /**
     * Valide la mise à jour du profil utilisateur (US8)
     * 
     * Vérifié que :
     * - Le rôle est valide (passager/chauffeur/chauffeur_passager)
     * - Si chauffeur ou chauffeur_passager, les véhicules sont fournis
     * 
     * @param array $json - Les données du formulaire
     * @return array - Les données validées
     * @throws ValidationException - Si validation échoue
     */
    public static function validateUpdateProfile(array $json): array
    {
        // Étape 1 : Extraire et nettoyer le rôle
        $role = trim((string)($json['role'] ?? ''));

        // Étape 2 : Vérifier que le rôle n'est pas vide
        if (empty($role)) {
            throw new ValidationException(['Le rôle est obligatoire']);
        }

        // Étape 3 : Vérifier que le rôle est dans la liste autorisée
        $rolesValides = ['passager', 'chauffeur', 'chauffeur_passager'];
        if (!in_array($role, $rolesValides)) {
            throw new ValidationException(['Rôle invalide. Doit être: passager, chauffeur ou chauffeur_passager']);
        }
        
        // Étape 4 : Si chauffeur ou chauffeur_passager, on accepte que le tableau de véhicules
        // soit vide ici (l'utilisateur peut déjà avoir des véhicules). La contrainte
        // "au moins un véhicule" sera vérifiée au niveau du service avec accès DB.
        $vehicules = [];
        if ($role !== 'passager') {
            $vehicules = $json['vehicules'] ?? [];
            if (!is_array($vehicules)) {
                throw new ValidationException(['Les véhicules doivent être un tableau']);
            }
        }
        // Étape 5 : Tout est valide, on retourne les données
        return [
            'role' => $role,
            'vehicules' => $vehicules ?? [],
            'preferences' => $json['preferences'] ?? []
        ];
    }
}