<?php

namespace App\Services;

use App\Models\User;
use App\DTO\UserResponse;
use App\Repositories\UserRepositoryInterface;
use App\Repositories\VehicleRepository;  
use App\Repositories\MarqueRepository;
use App\Factories\DatabaseFactory;
/**
 * Service utilisateur : encapsule accès repository et formatage réponse.
 */
class UserService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private VehicleRepository $vehicles
        ){
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

    /**
     * Met à jour le profil utilisateur (rôle, véhicules, préférences)
     * @param int $userId - ID de l'utilisateur
     * @param string $role - Le nouveau rôle
     * @param array $vehicules - Tableau des données de véhicules
     * @param array $preferences - Tableau des préférences (fumeur, animaux, autres_preferences)
     * @return User - L'utilisateur mis à jour
     */

    public function updateProfile(int $userId, string $role, array $vehicules, array $preferences): User
    {
        // Etape 1 : Récupérer l'utilisateur existant
        $user = $this->users->findById($userId);
        if (!$user) {
            throw new \Exception("Utilisateur non trouvé", 404);
        }

        // Etape 2 : Mettre à jour le rôle
        $user->role = $role;

         // Étape 3 : Mettre à jour les préférences
        // On utilise une requête UPDATE pour les colonnes preference_fumeur, preference_animaux, autres_preferences
        $this->users->updatePreferences(
            $userId,
            $preferences['fumeur'] ?? null,
            $preferences['animaux'] ?? null,
            $preferences['autres_preferences'] ?? null
        );

         // Étape 4 : Vérifier la contrainte "au moins un véhicule" si chauffeur
        if (in_array($role, ['chauffeur', 'chauffeur_passager'])) {
            $existing = $this->vehicles->findByUserId($userId);
            $hasAnyVehicle = count($existing) > 0 || count($vehicules) > 0;
            if (!$hasAnyVehicle) {
                throw new \Exception('En tant que chauffeur, vous devez avoir au moins un véhicule.');
            }
        }

             // Étape 5 : Créer les nouveaux véhicules (si fournis)
            if (!empty($vehicules)) {
                $marqueRepo = new MarqueRepository(DatabaseFactory::getConnection());
                foreach ($vehicules as $vehicleData) {
                    // Résoudre la marque: accepte marque (libellé) ou marque_id
                    $marqueId = isset($vehicleData['marque_id']) ? (int)$vehicleData['marque_id'] : null;
                    if (!$marqueId && !empty($vehicleData['marque'])) {
                        $marqueId = $marqueRepo->findOrCreateByName((string)$vehicleData['marque']);
                    }
                    if (!$marqueId) {
                        // fallback par sécurité
                        $marqueId = 1;
                    }
                    $vehicle = new \App\Models\Vehicules(
                        $vehicleData['modele'],
                        $marqueId,
                        $vehicleData['immatriculation'],
                        $vehicleData['energie'],
                        $vehicleData['nb_places'],
                        $userId,
                        $vehicleData['couleur'] ?? null,
                        $vehicleData['date_premiere_immatriculation'] ?? null,
                        $vehicleData['est_ecologique'] ?? false
                    );
                    $this->vehicles->create($vehicle);
                }
            }

        // Étape 6 : Persister le rôle mis à jour
        $this->users->updateRole($userId, $role);

        // Étape 7 : Retourner l'utilisateur mis à jour
        return $user;
        }
}
