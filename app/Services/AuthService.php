<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use Exception;

class AuthService
{
    private UserRepositoryInterface $userRepository;
    private JwtService $jwtService;
    private CookieManager $cookieManager;

    public function __construct(UserRepositoryInterface $userRepository, ?JwtService $jwtService = null, ?CookieManager $cookieManager = null)
    {
        $this->userRepository = $userRepository;
        $this->jwtService = $jwtService ?? new JwtService();
        $this->cookieManager = $cookieManager ?? new CookieManager();
    }

    /**
     * Enregistre un nouvel utilisateur
     * @return array Données de l'utilisateur créé
     * @throws Exception en cas d'erreur
     */
    public function signup(string $pseudo, string $email, string $password): array
    {
        // Validation des données
        if (!$this->isValidPseudo($pseudo)) {
            throw new Exception('Le pseudo doit contenir 3-20 caractères (lettres, chiffres, tirets, underscores)');
        }

        if (!$this->isValidEmail($email)) {
            throw new Exception('Adresse email invalide');
        }

        if (!$this->isValidPassword($password)) {
            throw new Exception('Le mot de passe doit contenir au moins 8 caractères, une majuscule et un chiffre');
        }

        // Créer l'utilisateur
        $user = new User(
            'Anonymous',  // À améliorer plus tard
            'User',       // À améliorer plus tard
            $email,
            $pseudo,
            $password,
            null,
            20.00,  // Crédits initiaux
            'standard'
        );

        $userId = $this->userRepository->create($user);
        $user->id = $userId;

        // Log : L'utilisateur a été créé avec succès
        return [
            'utilisateur_id' => $userId,
            'pseudo' => $pseudo,
            'email' => $email,
            'credit' => 20.00,
            'message' => 'Inscription réussie. Vous avez reçu 20 crédits de bienvenue !'
        ];
    }

    /**
     * Authentifie un utilisateur et crée une session + JWT token
     * @return array Données de l'utilisateur + token JWT
     * @throws Exception si identifiants invalides
     */
    public function login(string $emailOrPseudo, string $password): array
    {
        // Chercher l'utilisateur par email ou pseudo
        $user = $this->userRepository->findByEmail($emailOrPseudo);
        if (!$user) {
            $user = $this->userRepository->findByPseudo($emailOrPseudo);
        }

        if (!$user) {
            throw new Exception('Identifiant ou mot de passe incorrect');
        }

        // Vérifier le mot de passe
        if (!User::verifyPassword($password, $user->password)) {
            throw new Exception('Identifiant ou mot de passe incorrect');
        }

        // Vérifier si l'utilisateur est suspendu
        if ($user->suspendu) {
            throw new Exception('Votre compte a été suspendu. Veuillez contacter le support.');
        }

        // Préparer les données utilisateur
        $userData = [
            'utilisateur_id' => $user->id,
            'pseudo' => $user->pseudo,
            'email' => $user->email,
            'nom' => $user->nom,
            'prenom' => $user->prenom,
            'credit' => $user->credit,
            'type_utilisateur' => $user->type_utilisateur,
        ];

        // Créer le JWT token (non exposé au frontend)
        $token = $this->jwtService->generate([
            'user_id' => $user->id,
            'pseudo' => $user->pseudo,
            'email' => $user->email,
        ]);

        // Créer le cookie sécurisé contenant le token
        $this->cookieManager->setToken($token);

        // Créer aussi la session PHP (pour compatibilité)
        $_SESSION['user'] = $userData;

        // Retourner uniquement les données utilisateur affichables (pas le token)
        return $userData;
    }

    /**
     * Déconnecte l'utilisateur (session + cookie)
     */
    public function logout(): void
    {
        // Effacer le cookie
        $this->cookieManager->deleteToken();

        // Détruire la session
        session_destroy();
    }

    /**
     * Vérifie si un pseudo est valide
     */
    private function isValidPseudo(string $pseudo): bool
    {
        if (strlen($pseudo) < 3 || strlen($pseudo) > 20) {
            return false;
        }
        return (bool)preg_match('/^[a-zA-Z0-9_-]+$/', $pseudo);
    }

    /**
     * Vérifie si un email est valide
     */
    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Vérifie si un mot de passe est sécurisé
     */
    private function isValidPassword(string $password): bool
    {
        return strlen($password) >= 8 &&
               preg_match('/[A-Z]/', $password) &&  // Au moins une majuscule
               preg_match('/[0-9]/', $password);    // Au moins un chiffre
    }
}
