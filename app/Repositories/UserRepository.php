<?php
namespace App\Repositories;

use App\Models\User;
use PDO;
use Exception;

class UserRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Crée un nouvel utilisateur
     * @throws Exception si email ou pseudo existe déjà
     */
    public function create(User $user): int
    {
        // Vérifier si pseudo existe
        if ($this->pseudoExists($user->pseudo)) {
            throw new Exception('Ce pseudo est déjà utilisé');
        }

        // Vérifier si email existe
        if ($this->emailExists($user->email)) {
            throw new Exception('Cet email est déjà utilisé');
        }

        $stmt = $this->db->prepare('
            INSERT INTO utilisateur 
            (nom, prenom, email, pseudo, password, telephone, credit, type_utilisateur)
            VALUES (:nom, :prenom, :email, :pseudo, :password, :telephone, :credit, :type)
        ');

        $stmt->execute([
            ':nom' => 'Anonymous',  // À modifier plus tard avec infos supplémentaires
            ':prenom' => 'User',    // À modifier plus tard avec infos supplémentaires
            ':email' => $user->email,
            ':pseudo' => $user->pseudo,
            ':password' => User::hashPassword($user->password),
            ':telephone' => $user->telephone,
            ':credit' => $user->credit ?? 20.00,
            ':type' => $user->type_utilisateur
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Récupère un utilisateur par ID
     */
    public function findById(int $id): ?User
    {
        $stmt = $this->db->prepare('
            SELECT * FROM utilisateur WHERE utilisateur_id = :id
        ');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapRowToUser($row);
    }

    /**
     * Récupère un utilisateur par email
     */
    public function findByEmail(string $email): ?User
    {
        $stmt = $this->db->prepare('
            SELECT * FROM utilisateur WHERE email = :email
        ');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapRowToUser($row);
    }

    /**
     * Récupère un utilisateur par pseudo
     */
    public function findByPseudo(string $pseudo): ?User
    {
        $stmt = $this->db->prepare('
            SELECT * FROM utilisateur WHERE pseudo = :pseudo
        ');
        $stmt->execute([':pseudo' => $pseudo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapRowToUser($row);
    }

    /**
     * Vérifie si un pseudo existe
     */
    public function pseudoExists(string $pseudo): bool
    {
        $stmt = $this->db->prepare('
            SELECT 1 FROM utilisateur WHERE pseudo = :pseudo LIMIT 1
        ');
        $stmt->execute([':pseudo' => $pseudo]);
        return (bool)$stmt->fetch();
    }

    /**
     * Vérifie si un email existe
     */
    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare('
            SELECT 1 FROM utilisateur WHERE email = :email LIMIT 1
        ');
        $stmt->execute([':email' => $email]);
        return (bool)$stmt->fetch();
    }

    /**
     * Map une ligne de base de données en objet User
     */
    private function mapRowToUser(array $row): User
    {
        $user = new User(
            $row['nom'],
            $row['prenom'],
            $row['email'],
            $row['pseudo'],
            $row['password'],
            $row['telephone'],
            (float)$row['credit'],
            $row['type_utilisateur']
        );

        $user->id = (int)$row['utilisateur_id'];
        $user->date_creation = $row['date_creation'];
        $user->suspendu = (int)$row['suspendu'];

        return $user;
    }
}
