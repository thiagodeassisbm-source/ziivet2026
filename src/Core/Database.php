<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private PDO $connection;

    private function __construct()
    {
        // Detect environment logic similar to legacy config, but cleaner
        $port = $_SERVER['SERVER_PORT'] ?? '80';
        $hostName = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        $isLocalhost = ($hostName === 'localhost:8000' || $hostName === 'localhost' || str_contains($hostName, '127.0.0.1'));

        if ($isLocalhost) {
            $host = 'localhost';
            $db   = 'u315410518_app';
            $user = 'root';
            $pass = '';
        } else {
            // Production credentials
            $host = 'localhost';
            $db   = 'u315410518_app';
            $user = 'u315410518_app';
            $pass = '|zQrNOud4Kt';
        }

        try {
            // Using utf8mb4 for full unicode support
            $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
            
            $this->connection = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            // In a better Clean Arch, we would log this to a file and throw a generic DomainException
            // ensuring we don't expose credentials.
            if ($isLocalhost) {
                die("Database Connection Error (Core): " . $e->getMessage());
            } else {
                // Log error safely here if logger exists
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
