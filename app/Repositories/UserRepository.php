<?php

namespace App\Repositories;

use App\Models\User;
use PDO;
use Exception;

class UserRepository implements UserRepositoryInterface
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

    public function updateCredit(int $userId, float $amount, string $type): bool
    {
        // Vérifier que le type est valide
        if (!in_array($type, ['debit', 'credit'])) {
            throw new Exception('Invalid operation type. Must be "debit" or "credit"');
        }

        // Vérifier que le montant est positif
        if ($amount <= 0) {
            throw new Exception('Amount must be positive');
        }

        try {
            // 1. Préparer la requête selon le type d'opération
            if ($type === 'debit') {
                // Débiter : credit - amount
                $stmt = $this->db->prepare('
                    UPDATE utilisateur 
                    SET credit = credit - :amount 
                    WHERE utilisateur_id = :userId
                ');
            } else {
                // Créditer : credit + amount
                $stmt = $this->db->prepare('
                    UPDATE utilisateur 
                    SET credit = credit + :amount 
                    WHERE utilisateur_id = :userId
                ');
            }

            // 2. Exécuter la mise à jour du crédit de l'utilisateur
            $stmt->execute([
                ':amount' => $amount,
                ':userId' => $userId
            ]);

            // Vérifier que la mise à jour a réussi
            if ($stmt->rowCount() === 0) {
                throw new Exception('User not found');
            }

            // 3. Enregistrer l'opération dans credit_operation
            $operationStmt = $this->db->prepare('
                INSERT INTO credit_operation (utilisateur_id, type_operation, montant, date_operation)
                VALUES (:userId, :type, :montant, NOW())
            ');

            $operationStmt->execute([
                ':userId' => $userId,
                ':type' => $type,
                ':montant' => $amount
            ]);

            return true;

        } catch (Exception $e) {
            throw new Exception('Failed to update credit: ' . $e->getMessage());
        }
    }
}
