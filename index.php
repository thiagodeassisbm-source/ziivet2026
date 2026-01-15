<?php
// index.php
require_once 'config/configuracoes.php';

// Inicia sessão se não estiver iniciada (embora config já faça isso, é bom garantir)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o usuário já está logado
if (isset($_SESSION['usuario_id'])) {
    // Redireciona para o dashboard se estiver logado
    header('Location: ' . URL_BASE . 'dashboard.php');
    exit;
} else {
    // Redireciona para o login se não estiver logado
    header('Location: ' . URL_BASE . 'login.php');
    exit;
}
?>
