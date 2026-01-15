<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Limpa todas as variáveis de sessão
$_SESSION = array();

// Destrói a sessão no servidor
session_destroy();

// Redireciona para o login com uma mensagem opcional (via GET)
header("Location: login.php?msg=saiu");
exit;
?>