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
        $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? 'db';
        $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? 3306;
        $database = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? 'ecoride';
        $user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?? 'ecoride';
        $password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?? 'ecoride-v2';

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
