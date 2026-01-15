<?php
/**
 * ZIIPVET - Sistema de Autenticação e Segurança
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Verifica se o utilizador está autenticado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['id_admin'])) {
    session_unset();
    session_destroy();
    header("Location: login.php?erro=sessao_expirada");
    exit;
}

/**
 * FUNÇÃO: temPermissao
 */
function temPermissao($modulo, $acao) {
    global $pdo;
    if (!$pdo) return false;

    $id_usuario = $_SESSION['usuario_id'];

    try {
        $sql = "SELECT COUNT(*) FROM usuarios_permissoes 
                WHERE id_usuario = :id_user 
                AND modulo = :mod 
                AND acao = :act";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id_user', $id_usuario, PDO::PARAM_INT);
        $stmt->bindValue(':mod', $modulo, PDO::PARAM_STR);
        $stmt->bindValue(':act', $acao, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchColumn() > 0;

    } catch (PDOException $e) {
        error_log("Erro ao verificar permissão: " . $e->getMessage());
        return false;
    }
}

/**
 * FUNÇÃO: validarAcessoPagina (ATUALIZADA)
 */
function validarAcessoPagina($modulo) {
    if (!temPermissao($modulo, 'listar')) {
        // Redireciona com o gatilho para o popup
        header("Location: dashboard.php?permissao_negada=1");
        exit;
    }
}

define('USER_ID', $_SESSION['usuario_id']);
define('ADMIN_ID', $_SESSION['id_admin']);
define('USER_NAME', $_SESSION['usuario_nome'] ?? 'Utilizador');
?>