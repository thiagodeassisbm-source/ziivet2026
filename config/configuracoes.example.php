<?php
// =========================================================================================
// ZIIPVET - ARQUIVO DE CONFIGURAÇÃO (EXEMPLO)
// =========================================================================================

// Carregar Autoload e Utilitários de Segurança
require_once __DIR__ . '/../vendor/autoload.php';
use App\Utils\Csrf;
use App\Utils\Env;

// Carregar variáveis de ambiente (Crie o arquivo .env na raiz baseado no .env.example)
Env::load(__DIR__ . '/../.env');

// --- PROTEÇÃO CSRF GLOBAL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $pagina_atual = basename($_SERVER['PHP_SELF']);
    
    $is_api = (strpos($uri, '/api/') !== false);
    $is_login = ($pagina_atual === 'login.php');

    if (!$is_api && !$is_login) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Csrf::validate($token)) {
            http_response_code(403);
            die('Erro de segurança CSRF.');
        }
    }
}

// Configurações do Banco de Dados via Variáveis de Ambiente
$host = Env::get('DB_HOST', 'localhost');
$db   = Env::get('DB_NAME', 'nome_do_banco');
$user = Env::get('DB_USER', 'root');
$pass = Env::get('DB_PASS', '');
$sgbd = 'mysql';

try {
    $pdo = new PDO("$sgbd:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco de dados.");
}

// Definição da URL Base
$url_base_env = Env::get('URL_BASE', 'http://localhost:8000/');
define('URL_BASE', $url_base_env);
