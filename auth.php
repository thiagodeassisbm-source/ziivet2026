<?php
/**
 * ZIIPVET - Sistema de Autenticação e Segurança
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Verifica se o usuário está autenticado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['id_admin'])) {
    session_unset();
    session_destroy();
    
    // Detectar nível de diretório para corrigir path do login
    $path = dirname($_SERVER['PHP_SELF']);
    $depth = substr_count($path, '/');
    // Ajuste simples: se tiver mais de 1 barra (ex: /nfe/arquivo.php), sobe um nível.
    // Melhor abordagem: Usar URL absoluta se possível, ou detectar se está em subpasta.
    $loginPath = (strpos($_SERVER['REQUEST_URI'], '/nfe/') !== false || strpos($_SERVER['REQUEST_URI'], '/nota-fiscal/') !== false) ? '../login.php' : 'login.php';
    
    header("Location: " . $loginPath . "?erro=sessao_expirada");
    exit;
}

use App\Core\Database;
use App\Utils\Csrf;

require_once __DIR__ . '/vendor/autoload.php';

// ==========================================================
// INICIALIZAR PROTEÇÃO CSRF
// ==========================================================
// Gerar token CSRF se não existir
if (!isset($_SESSION['csrf_token'])) {
    Csrf::generate();
}

/**
 * FUNÇÃO: temPermissao
 */
function temPermissao($modulo, $acao) {
    // Refatorado para usar Singleton Database (Clean Arch)
    try {
        $pdo = Database::getInstance()->getConnection();
    } catch (Exception $e) {
        return false;
    }

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