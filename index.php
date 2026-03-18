<?php
// index.php
// Resolve config/configuracoes.php mesmo quando este index.php estiver em subpasta (ex: /app/index.php).
function requireConfiguracoes(): void
{
    $candidates = [
        __DIR__ . '/config/configuracoes.php',
        __DIR__ . '/../config/configuracoes.php',
        __DIR__ . '/../../config/configuracoes.php',
    ];

    foreach ($candidates as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    // Back-compat (caso o include_path esteja configurado)
    require_once 'config/configuracoes.php';
}

requireConfiguracoes();

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
