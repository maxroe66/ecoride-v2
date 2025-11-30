<?php

namespace App\Models;

class User
    /**
     * Retourne les préférences du conducteur depuis la table utilisateur
     */
    public function getPreferences(): array
    {
        $pdo = \App\Factories\DatabaseFactory::getConnection();
        $stmt = $pdo->prepare('SELECT preference_fumeur, preference_animaux, autres_preferences FROM utilisateur WHERE utilisateur_id = :id LIMIT 1');
        $stmt->execute([':id' => $this->id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return [
                'fumeur' => false,
                'animaux' => false,
                'musique' => false,
                'discussion' => false
            ];
        }
        $autres = [];
        if (!empty($row['autres_preferences'])) {
            $autres = json_decode($row['autres_preferences'], true) ?: [];
        }
        return [
            'fumeur' => (bool)$row['preference_fumeur'],
            'animaux' => (bool)$row['preference_animaux'],
            'musique' => isset($autres['musique']) ? (bool)$autres['musique'] : false,
            'discussion' => isset($autres['discussion']) ? (bool)$autres['discussion'] : false
        ];
    }
{
    public int $id;
    public string $nom;
    public string $prenom;
    public string $email;
    public string $pseudo;
    public string $password;
    public ?string $telephone;
    public float $credit;
    public string $date_creation;
    public string $type_utilisateur;
    public int $suspendu;

    public function __construct(
        string $nom,
        string $prenom,
        string $email,
        string $pseudo,
        string $password,
        ?string $telephone = null,
        float $credit = 20.00,
        string $type_utilisateur = 'standard'
    ) {
        $this->nom = $nom;
        $this->prenom = $prenom;
        $this->email = $email;
        $this->pseudo = $pseudo;
        $this->password = $password;
        $this->telephone = $telephone;
        $this->credit = $credit;
        $this->type_utilisateur = $type_utilisateur;
        $this->suspendu = 0;
        $this->date_creation = date('Y-m-d H:i:s');
    }

    /**
     * Crée un hash sécurisé du mot de passe
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Vérifie un mot de passe contre son hash
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Retourne les données publiques (sans mot de passe)
     */
    public function toArray(): array
    {
        return [
            'utilisateur_id' => $this->id ?? null,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'pseudo' => $this->pseudo,
            'telephone' => $this->telephone,
            'credit' => $this->credit,
            'type_utilisateur' => $this->type_utilisateur,
            'date_creation' => $this->date_creation,
        ];
    }
}
