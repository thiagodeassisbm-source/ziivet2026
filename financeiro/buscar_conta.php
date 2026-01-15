<?php
$base_app = dirname(__DIR__);
require_once $base_app . '/auth.php';
require_once $base_app . '/config/configuracoes.php';

header('Content-Type: application/json');

// Habilitar log de erros
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_conta = $_GET['id'] ?? null;
$id_admin = $_SESSION['id_admin'] ?? 1;

if (!$id_conta) {
    echo json_encode(['status' => 'error', 'message' => 'ID da conta não informado']);
    exit;
}

try {
    // QUERY CORRIGIDA: categoria é VARCHAR, não FK
    $query = "SELECT c.*, 
              CASE 
                WHEN c.entidade_tipo = 'fornecedor' THEN COALESCE(f.nome_fantasia, f.razao_social, f.nome_completo)
                WHEN c.entidade_tipo = 'cliente' THEN cli.nome
                WHEN c.entidade_tipo = 'usuario' THEN u.nome
              END as nome_entidade
              FROM contas c
              LEFT JOIN fornecedores f ON c.id_entidade = f.id AND c.entidade_tipo = 'fornecedor'
              LEFT JOIN clientes cli ON c.id_entidade = cli.id AND c.entidade_tipo = 'cliente'
              LEFT JOIN usuarios u ON c.id_entidade = u.id AND c.entidade_tipo = 'usuario'
              WHERE c.id = ? AND c.id_admin = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$id_conta, $id_admin]);
    $conta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conta) {
        echo json_encode(['status' => 'error', 'message' => 'Conta não encontrada']);
        exit;
    }
    
    // Buscar nome da categoria (se existir na tabela categorias_contas)
    $categoria_nome = 'Categoria não definida';
    if (!empty($conta['categoria'])) {
        try {
            $stmt_cat = $pdo->prepare("SELECT nome_categoria FROM categorias_contas WHERE id = ? LIMIT 1");
            $stmt_cat->execute([$conta['categoria']]);
            $cat_result = $stmt_cat->fetchColumn();
            if ($cat_result) {
                $categoria_nome = $cat_result;
            }
        } catch (Exception $e) {
            // Categoria não existe ou erro, mantém valor padrão
            error_log("Erro ao buscar categoria: " . $e->getMessage());
        }
    }
    $conta['categoria_nome'] = $categoria_nome;
    
    // Buscar ID da compra relacionada (se houver)
    $stmt_compra = $pdo->prepare("SELECT id FROM compras WHERE nf_numero = ? AND id_admin = ? LIMIT 1");
    $stmt_compra->execute([$conta['documento'], $id_admin]);
    $compra = $stmt_compra->fetch(PDO::FETCH_ASSOC);
    $conta['id_compra'] = $compra ? $compra['id'] : null;
    
    // Calcular qual parcela é (se houver múltiplas)
    if ($conta['qtd_parcelas'] > 1) {
        $stmt_parcelas = $pdo->prepare("SELECT COUNT(*) FROM contas WHERE documento = ? AND vencimento <= ? AND id_admin = ?");
        $stmt_parcelas->execute([$conta['documento'], $conta['vencimento'], $id_admin]);
        $conta['parcela_atual'] = $stmt_parcelas->fetchColumn();
    } else {
        $conta['parcela_atual'] = 1;
    }
    
    echo json_encode([
        'status' => 'success',
        'conta' => $conta
    ]);
    
} catch (PDOException $e) {
    error_log("ERRO buscar_conta.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erro ao buscar conta: ' . $e->getMessage()]);
}