<?php
// =========================================================================================
// ZIIPVET - ARQUIVO DE CONFIGURAÇÃO CENTRAL
// =========================================================================================

// Carregar Autoload e Utilitários de Segurança
require_once __DIR__ . '/../vendor/autoload.php';
use App\Utils\Csrf;
use App\Utils\Env;

// Carregar variáveis de ambiente
Env::load(__DIR__ . '/../.env');

// --- PROTEÇÃO CSRF GLOBAL ---
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $pagina_atual = basename($_SERVER['PHP_SELF']);
    
    // Ignorar validação na página de login e em todas as rotas da API (/api/v1/...)
    $is_api = (strpos($uri, '/api/') !== false);
    $is_login = ($pagina_atual === 'login.php');

    if (!$is_api && !$is_login) {
        // Tenta obter o token do POST ou do Header (AJAX)
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (!Csrf::validate($token)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            die('Erro de segurança CSRF: Token inválido ou ausente. Por favor, recarregue a página.');
        }
    }
}

// Configurações do Banco de Dados via Variáveis de Ambiente (Segurança)
// IMPORTANT: nada de credenciais hardcoded aqui; use `.env`.
$host = (string)Env::get('DB_HOST', 'localhost');
$db   = (string)Env::get('DB_NAME', '');
$user = (string)Env::get('DB_USER', '');
$pass = (string)Env::get('DB_PASS', '');
$sgbd = (string)Env::get('DB_DRIVER', 'mysql');

 $appEnv = (string)Env::get('APP_ENV', 'local');

// Se estiver em ambiente não-local e faltarem credenciais, falha com mensagem clara.
if ($appEnv !== 'local' && ($db === '' || $user === '' || $pass === '')) {
    die('Erro de configuração: variáveis de ambiente DB_NAME/DB_USER/DB_PASS não definidas no .env.');
}

try {
    $pdo = new PDO("$sgbd:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if ($appEnv === 'local') {
        die("Erro ao conectar ao banco de dados: " . $e->getMessage());
    } else {
        die("Erro ao conectar ao banco de dados.");
    }
}

// Início da sessão se não existir
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definição da URL Base
$url_base_env = Env::get('URL_BASE');
if ($url_base_env) {
    define('URL_BASE', $url_base_env);
} else {
    // Fallback dinâmico para garantir funcionamento
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host_name = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('URL_BASE', "$protocol://$host_name/");
}