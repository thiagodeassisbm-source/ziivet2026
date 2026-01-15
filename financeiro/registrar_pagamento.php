<?php
$base_app = dirname(__DIR__);
require_once $base_app . '/auth.php';
require_once $base_app . '/config/configuracoes.php';

// ATIVAR ERROS NA TELA
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Array para guardar logs
$debug_log = [];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido']);
    exit;
}

$id_conta = $_POST['id_conta'] ?? null;
$id_conta_financeira = $_POST['id_conta_financeira'] ?? null; 
$marcar_pago = $_POST['marcar_pago'] ?? '0';
$data_pagamento = $_POST['data_pagamento'] ?? null;
$valor = floatval($_POST['valor'] ?? 0);
$id_admin = $_SESSION['id_admin'] ?? 1;
$nf = $_POST['nf'] ?? '';

$debug_log[] = "========== DADOS RECEBIDOS ==========";
$debug_log[] = "ID Conta: {$id_conta}";
$debug_log[] = "ID Conta Financeira: {$id_conta_financeira}";
$debug_log[] = "Marcar Pago: {$marcar_pago}";
$debug_log[] = "Data Pagamento: {$data_pagamento}";
$debug_log[] = "Valor: {$valor}";
$debug_log[] = "ID Admin: {$id_admin}";

if (!$id_conta || !$id_conta_financeira) {
    $debug_log[] = "ERRO: Dados incompletos";
    echo json_encode([
        'status' => 'error', 
        'message' => 'Dados incompletos.',
        'debug' => $debug_log
    ]);
    exit;
}

try {
    $pdo->beginTransaction();
    $debug_log[] = "Transaction iniciada";
    
    // 1. Vincular a conta bancária
    $sql1 = "UPDATE contas SET id_conta_origem = ? WHERE id = ? AND id_admin = ?";
    $debug_log[] = "SQL 1: {$sql1}";
    $debug_log[] = "Params: id_conta_origem={$id_conta_financeira}, id={$id_conta}, id_admin={$id_admin}";
    
    $stmt_update = $pdo->prepare($sql1);
    $result1 = $stmt_update->execute([$id_conta_financeira, $id_conta, $id_admin]);
    $rows1 = $stmt_update->rowCount();
    
    $debug_log[] = "UPDATE contas - Sucesso: " . ($result1 ? 'SIM' : 'NÃO');
    $debug_log[] = "UPDATE contas - Linhas afetadas: {$rows1}";
    
    $mensagem = 'Conta financeira vinculada com sucesso!';
    
    // 2. Se marcar como pago
    if ($marcar_pago === '1' && $data_pagamento) {
        
        $debug_log[] = "========== INICIANDO BAIXA ==========";
        
        // Verificar status atual
        $stmt_check = $pdo->prepare("SELECT status_baixa FROM contas WHERE id = ?");
        $stmt_check->execute([$id_conta]);
        $status_atual = $stmt_check->fetchColumn();
        $debug_log[] = "Status atual: {$status_atual}";
        
        if ($status_atual === 'PAGO') {
            $pdo->rollBack();
            $debug_log[] = "ERRO: Conta já paga!";
            echo json_encode([
                'status' => 'error', 
                'message' => 'Esta conta já foi paga anteriormente',
                'debug' => $debug_log
            ]);
            exit;
        }
        
        // Buscar conta financeira
        $sql2 = "SELECT nome_conta, saldo_inicial FROM contas_financeiras WHERE id = ? AND id_admin = ?";
        $debug_log[] = "SQL 2: {$sql2}";
        $debug_log[] = "Params: id={$id_conta_financeira}, id_admin={$id_admin}";
        
        $stmt_conta_fin = $pdo->prepare($sql2);
        $stmt_conta_fin->execute([$id_conta_financeira, $id_admin]);
        $conta_fin = $stmt_conta_fin->fetch(PDO::FETCH_ASSOC);
        
        $debug_log[] = "Conta financeira: " . json_encode($conta_fin);
        
        if (!$conta_fin) {
            $pdo->rollBack();
            $debug_log[] = "ERRO: Conta financeira não encontrada!";
            echo json_encode([
                'status' => 'error', 
                'message' => 'Conta financeira não encontrada',
                'debug' => $debug_log
            ]);
            exit;
        }
        
        $saldo_anterior = floatval($conta_fin['saldo_inicial']);
        $nome_conta = $conta_fin['nome_conta'];
        $novo_saldo = $saldo_anterior - $valor;
        
        $debug_log[] = "Saldo anterior: {$saldo_anterior}";
        $debug_log[] = "Valor a descontar: {$valor}";
        $debug_log[] = "Novo saldo: {$novo_saldo}";
        
        // 3. Marcar como PAGO
        $sql3 = "UPDATE contas SET status_baixa = 'PAGO', data_pagamento = ? WHERE id = ? AND id_admin = ?";
        $debug_log[] = "SQL 3: {$sql3}";
        
        $stmt_baixa = $pdo->prepare($sql3);
        $result3 = $stmt_baixa->execute([$data_pagamento, $id_conta, $id_admin]);
        $rows3 = $stmt_baixa->rowCount();
        
        $debug_log[] = "UPDATE status - Sucesso: " . ($result3 ? 'SIM' : 'NÃO');
        $debug_log[] = "UPDATE status - Linhas: {$rows3}";
        
        // 4. Atualizar saldo
        $sql4 = "UPDATE contas_financeiras SET saldo_inicial = ? WHERE id = ? AND id_admin = ?";
        $debug_log[] = "SQL 4: {$sql4}";
        $debug_log[] = "Params: saldo={$novo_saldo}, id={$id_conta_financeira}, id_admin={$id_admin}";
        
        $stmt_saldo = $pdo->prepare($sql4);
        $result4 = $stmt_saldo->execute([$novo_saldo, $id_conta_financeira, $id_admin]);
        $rows4 = $stmt_saldo->rowCount();
        
        $debug_log[] = "UPDATE saldo - Sucesso: " . ($result4 ? 'SIM' : 'NÃO');
        $debug_log[] = "UPDATE saldo - Linhas: {$rows4}";
        
        // 5. Buscar info da conta
        $stmt_info = $pdo->prepare("SELECT descricao, documento, id_entidade, entidade_tipo FROM contas WHERE id = ?");
        $stmt_info->execute([$id_conta]);
        $info_conta = $stmt_info->fetch(PDO::FETCH_ASSOC);
        
        $debug_log[] = "Info conta: " . json_encode($info_conta);
        
        // Buscar fornecedor/cliente
        $fornecedor_cliente = '';
        if ($info_conta['id_entidade'] && $info_conta['entidade_tipo'] === 'fornecedor') {
            $stmt_forn = $pdo->prepare("SELECT COALESCE(nome_fantasia, razao_social, nome_completo) as nome FROM fornecedores WHERE id = ?");
            $stmt_forn->execute([$info_conta['id_entidade']]);
            $fornecedor_cliente = $stmt_forn->fetchColumn() ?: '';
            $debug_log[] = "Fornecedor: {$fornecedor_cliente}";
        } elseif ($info_conta['id_entidade'] && $info_conta['entidade_tipo'] === 'cliente') {
            $stmt_cli = $pdo->prepare("SELECT nome FROM clientes WHERE id = ?");
            $stmt_cli->execute([$info_conta['id_entidade']]);
            $fornecedor_cliente = $stmt_cli->fetchColumn() ?: '';
            $debug_log[] = "Cliente: {$fornecedor_cliente}";
        }
        
        // 6. INSERT em lancamentos
        $descricao_lancamento = $info_conta['descricao'] ?: "Pagamento de conta";
        if (!empty($nf)) {
            $descricao_lancamento .= " - NF: {$nf}";
        }
        
        $sql5 = "INSERT INTO lancamentos (id_admin, data_vencimento, descricao, documento, fornecedor_cliente, forma_pagamento, parcela_atual, total_parcelas, valor, tipo, data_cadastro) VALUES (?, ?, ?, ?, ?, ?, 1, 1, ?, 'SAIDA', NOW())";
        $debug_log[] = "SQL 5: {$sql5}";
        $debug_log[] = "Params INSERT:";
        $debug_log[] = "  - id_admin: {$id_admin}";
        $debug_log[] = "  - data_vencimento: {$data_pagamento}";
        $debug_log[] = "  - descricao: {$descricao_lancamento}";
        $debug_log[] = "  - documento: " . ($info_conta['documento'] ?: $nf);
        $debug_log[] = "  - fornecedor_cliente: {$fornecedor_cliente}";
        $debug_log[] = "  - forma_pagamento: Conta Bancária: {$nome_conta}";
        $debug_log[] = "  - valor: {$valor}";
        
        $stmt_lancamento = $pdo->prepare($sql5);
        $result5 = $stmt_lancamento->execute([
            $id_admin,
            $data_pagamento,
            $descricao_lancamento,
            $info_conta['documento'] ?: $nf,
            $fornecedor_cliente,
            'Conta Bancária: ' . $nome_conta,
            $valor
        ]);
        $rows5 = $stmt_lancamento->rowCount();
        $last_id = $pdo->lastInsertId();
        
        $debug_log[] = "INSERT lancamentos - Sucesso: " . ($result5 ? 'SIM' : 'NÃO');
        $debug_log[] = "INSERT lancamentos - Linhas: {$rows5}";
        $debug_log[] = "INSERT lancamentos - Last ID: {$last_id}";
        
        $mensagem = "✅ Pagamento registrado!\n\n";
        $mensagem .= "💰 Valor: R$ " . number_format($valor, 2, ',', '.') . "\n";
        $mensagem .= "🏦 Conta: {$nome_conta}\n";
        $mensagem .= "📊 Saldo anterior: R$ " . number_format($saldo_anterior, 2, ',', '.') . "\n";
        $mensagem .= "📉 Novo saldo: R$ " . number_format($novo_saldo, 2, ',', '.') . "\n";
        $mensagem .= "✓ Lançamento ID: {$last_id}";
    }
    
    $pdo->commit();
    $debug_log[] = "========== COMMIT REALIZADO ==========";
    
    echo json_encode([
        'status' => 'success', 
        'message' => $mensagem,
        'debug' => $debug_log
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $debug_log[] = "========== ERRO EXCEPTION ==========";
    $debug_log[] = "Mensagem: " . $e->getMessage();
    $debug_log[] = "Arquivo: " . $e->getFile();
    $debug_log[] = "Linha: " . $e->getLine();
    
    echo json_encode([
        'status' => 'error', 
        'message' => 'Erro: ' . $e->getMessage(),
        'debug' => $debug_log
    ]);
}