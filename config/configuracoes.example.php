<?php
// Configurações do Banco de Dados
// Detecta se está rodando localmente
$is_localhost = ($_SERVER['HTTP_HOST'] == 'localhost:8000' || $_SERVER['SERVER_NAME'] == 'localhost');

if ($is_localhost) {
    // Configurações Locais
    $host = 'localhost';
    $db   = 'u315410518_app';
    $user = 'root';
    $pass = '';
    $sgbd = 'mysql';
} else {
    // Configurações de Produção
    $host = 'localhost';
    $db   = 'NOME_DO_BANCO';
    $user = 'USUARIO_DO_BANCO';
    $pass = 'SENHA_DO_BANCO';
    $sgbd = 'mysql';
}

try {
    $pdo = new PDO("$sgbd:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco de dados: " . $e->getMessage());
}

if (!isset($_SESSION)) {
    session_start();
}

if ($is_localhost) {
    define('URL_BASE', 'http://localhost:8000/');
} else {
    define('URL_BASE', 'https://www.lepetboutique.com.br/app/');
}

define('COR_PRIMARIA', '#d32f2f');
?>
