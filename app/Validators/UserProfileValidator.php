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
        
        // Étape 4 : Préparer structures
        $vehicules = [];
        $preferences = [];

        if ($role !== 'passager') {
            // Véhicules: accepter vide (si déjà existants) mais valider ceux fournis
            $vehicules = $json['vehicules'] ?? [];
            if (!is_array($vehicules)) {
                throw new ValidationException(['Les véhicules doivent être un tableau']);
            }
            foreach ($vehicules as $i => $v) {
                $idx = (int)$i + 1;
                $required = ['modele','immatriculation','energie','nb_places','date_premiere_immatriculation'];
                foreach ($required as $field) {
                    if (!isset($v[$field]) || $v[$field] === '' || $v[$field] === null) {
                        throw new ValidationException(["Véhicule #$idx: le champ '$field' est obligatoire"]);
                    }
                }
                // Marque: soit 'marque' (libellé) soit 'marque_id'
                if (empty($v['marque']) && empty($v['marque_id'])) {
                    throw new ValidationException(["Véhicule #$idx: la marque est obligatoire"]);
                }
                if (!is_numeric($v['nb_places']) || (int)$v['nb_places'] <= 0) {
                    throw new ValidationException(["Véhicule #$idx: nombre de places invalide"]);
                }
            }

            // Préférences requises quand on devient/est chauffeur
            $preferences = $json['preferences'] ?? [];
            $allowed = ['accepte','refuse'];
            if (!isset($preferences['fumeur']) || !in_array($preferences['fumeur'], $allowed, true)) {
                throw new ValidationException(['Préférences: valeur fumeur invalide ou manquante']);
            }
            if (!isset($preferences['animaux']) || !in_array($preferences['animaux'], $allowed, true)) {
                throw new ValidationException(['Préférences: valeur animaux invalide ou manquante']);
            }
        }

        // Étape 5 : Retourner les données validées
        return [
            'role' => $role,
            'vehicules' => $vehicules ?? [],
            'preferences' => $preferences
        ];
    }
}