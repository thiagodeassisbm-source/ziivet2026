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
        // 1. Tenta pegar a conexão global (criada por config/configuracoes.php)
        global $pdo;

        if (isset($pdo) && $pdo instanceof PDO) {
            $this->connection = $pdo;
            return;
        }

        // 2. Se não existir, tenta carregar o arquivo de configuração
        $configFile = __DIR__ . '/../../config/configuracoes.php';
        
        if (file_exists($configFile)) {
             // Tenta incluir. Usamos require_once, mas se já tiver sido incluído, não roda de novo.
             // O importante são as variáveis que ele gera.
             require_once $configFile;
             
             // Verifica se a variável local foi criada (caso o require tenha rodado agora)
             if (isset($pdo) && $pdo instanceof PDO) {
                $this->connection = $pdo;
                return;
             }
             
             // Verifica se a variável global existe (caso o require tenha rodado antes em outro escopo)
             global $pdo;
             if (isset($pdo) && $pdo instanceof PDO) {
                $this->connection = $pdo;
                return;
             }
        }

        // Se chegou aqui, falha crítica.
        // O usuário solicitou usar APENAS o configuracoes.php, então não tentamos criar conexão nova aqui.
        die("Erro Crítico (Core): Não foi possível obter a conexão do banco de dados. Verifique se o arquivo 'config/configuracoes.php' está correto e configurado.");
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
