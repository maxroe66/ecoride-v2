<?php
namespace App\Factories;

use App\Repositories\{MongoAvisRepository, MysqlAvisRepository, ResilientAvisRepository};
use PDO;

class AvisRepositoryFactory
{
    private static ?ResilientAvisRepository $instance = null;

    public static function get(): ResilientAvisRepository
    {
        if (self::$instance) return self::$instance;

        // MySQL
        $host = getenv('DB_HOST') ?: 'db';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: 'ecoride';
        $user = getenv('DB_USER') ?: 'ecoride';
        $pass = getenv('DB_PASSWORD') ?: 'ecoride-v2';
        $dsn  = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Mongo
        $mongoDsn = getenv('MONGO_DSN') ?: 'mongodb://mongo:27017';
        $mongoDb  = getenv('MONGO_DB') ?: 'ecoride';
    $mongoRepo = new MongoAvisRepository($mongoDsn, $mongoDb, 'avis');
        $mysqlRepo = new MysqlAvisRepository($pdo);

        self::$instance = new ResilientAvisRepository($mongoRepo, $mysqlRepo);
        return self::$instance;
    }
}
