<?php

namespace App\Core;

use PDO;
use PDOException;
use App\Utils\Env;

class Database
{
    private static ?Database $instance = null;
    private PDO $connection;

    private function __construct()
    {
        // Carregar variáveis de ambiente
        Env::load(__DIR__ . '/../../.env');

        $host = Env::get('DB_HOST', 'localhost');
        $db   = Env::get('DB_NAME', 'u315410518_app');
        $user = Env::get('DB_USER', 'root');
        $pass = Env::get('DB_PASS', '');
        $charset = Env::get('DB_CHARSET', 'utf8mb4');

        $appEnv = Env::get('APP_ENV', 'local');

        try {
            $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
            
            $this->connection = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            if ($appEnv === 'local') {
                die("Database Connection Error (Core): " . $e->getMessage());
            } else {
                die("Erro de conexão com o banco de dados.");
            }
        }
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }
    
    // Prevent cloning
    private function __clone() {}
}
