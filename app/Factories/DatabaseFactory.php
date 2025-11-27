<?php
namespace App\Factories;

use PDO;
use Exception;

class DatabaseFactory
{
    private static ?PDO $connection = null;

    /**
     * Obtient une connexion PDO à la base MySQL
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            self::$connection = self::createConnection();
        }
        return self::$connection;
    }

    /**
     * Crée une nouvelle connexion PDO
     */
    private static function createConnection(): PDO
    {
        // En Docker, utiliser 'db' comme host par défaut
        $host = getenv('DB_HOST') ?: 'db';
        $port = getenv('DB_PORT') ?: 3306;
        $database = getenv('DB_NAME') ?: 'ecoride';
        $user = getenv('DB_USER') ?: 'ecoride';
        $password = getenv('DB_PASSWORD') ?: 'ecoride-v2';

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            return $pdo;
        } catch (\PDOException $e) {
            throw new Exception('Erreur de connexion à la base de données : ' . $e->getMessage());
        }
    }

    /**
     * Ferme la connexion
     */
    public static function close(): void
    {
        self::$connection = null;
    }
}
