<?php
/**
 * ZIIPVET - Detalhes da Movimentação do Caixa
 * ARQUIVO: detalhes_movimentacao.php  
 * LOCALIZAÇÃO: /app/vendas/
 * 
 * CORREÇÕES APLICADAS:
 * 1. Todo o valor em dinheiro (incluindo valor inicial) retorna para a conta selecionada
 * 2. Hora atual carregando corretamente no modal de encerramento
 */

$base_path = dirname(__DIR__) . '/'; 
$path_prefix = '../';

require_once $base_path . 'auth.php';
require_once $base_path . 'config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;
$id_caixa = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

if (!$id_caixa) {
    die("<script>alert('Caixa não informado!'); window.location.href='movimentacao_caixa.php';</script>");
}

// PROCESSAMENTO AJAX
if (isset($_POST['acao']) && $_POST['acao'] === 'adicionar_movimentacao') {
    header('Content-Type: application/json');
    
    try {
        $pdo->beginTransaction();
        
        $tipo = $_POST['tipo'];
        $valor = floatval(str_replace(',', '.', str_replace('.', '', $_POST['valor'])));
        $forma_pagamento = $_POST['forma_pagamento'] ?? 'Não especificada';
        $id_conta = $_POST['id_conta'] ?? null;
        $descricao = $_POST['descricao'] ?? '';
        $observacoes = $_POST['observacoes'] ?? '';
        
        $tipo_lancamento = ($tipo == 'SUPRIMENTO') ? 'ENTRADA' : 'SAIDA';
        
        $sql = "INSERT INTO lancamentos 
                (id_admin, tipo, categoria, descricao, forma_pagamento, id_conta, 
                 observacoes, valor, status, id_caixa_referencia, 
                 data_vencimento, data_pagamento, data_cadastro, 
                 parcela_atual, total_parcelas) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PAGO', ?, NOW(), NOW(), NOW(), 1, 1)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $id_admin, $tipo_lancamento, $tipo, $descricao, $forma_pagamento,
            $id_conta, $observacoes, $valor, $id_caixa
        ]);
        
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Movimentação registrada!']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if (isset($_POST['acao']) && $_POST['acao'] === 'encerrar_caixa') {
    header('Content-Type: application/json');
    
    try {
        $pdo->beginTransaction();
        
        $id_conta_destino = $_POST['id_conta_destino'];
        $data_fechamento = $_POST['data_fechamento'];
        $hora_fechamento = $_POST['hora_fechamento'];
        $comentario = $_POST['comentario'] ?? '';
        
        // Calcular valor total em caixa (APENAS DINHEIRO)
        $stmt_total = $pdo->prepare("
            SELECT 
                (SELECT COALESCE(valor_inicial, 0) FROM caixas WHERE id = ?) +
                (SELECT COALESCE(SUM(CASE 
                    WHEN tipo = 'ENTRADA' THEN valor 
                    WHEN tipo = 'SAIDA' THEN -valor 
                    END), 0) FROM lancamentos 
                 WHERE id_caixa_referencia = ? 
                 AND status = 'PAGO'
                 AND UPPER(TRIM(forma_pagamento)) = 'DINHEIRO')
                as total
        ");
        $stmt_total->execute([$id_caixa, $id_caixa]);
        $valor_fechamento = $stmt_total->fetchColumn();
        
        // Buscar dados completos do caixa
        $stmt_caixa = $pdo->prepare("SELECT valor_inicial, id_conta_origem, id_usuario FROM caixas WHERE id = ?");
        $stmt_caixa->execute([$id_caixa]);
        $dados_caixa = $stmt_caixa->fetch(PDO::FETCH_ASSOC);
        $valor_abertura = $dados_caixa['valor_inicial'];
        $conta_origem = $dados_caixa['id_conta_origem'];
        $id_usuario_caixa = $dados_caixa['id_usuario'];
        
        // Atualizar status do caixa
        $sql = "UPDATE caixas SET 
                status = 'FECHADO',
                data_fechamento = ?,
                valor_fechamento = ?,
                id_conta_fechamento = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data_fechamento . ' ' . $hora_fechamento,
            $valor_fechamento, 
            $id_conta_destino, 
            $id_caixa
        ]);
        
        // ✅ CORREÇÃO REAL DO PROBLEMA:
        // 1. REMOVE o valor total do caixa do usuário (para não duplicar)
        // 2. ADICIONA o valor total na conta escolhida no encerramento
        
        // Buscar a conta de caixa do usuário
        $stmt_usuario = $pdo->prepare("
            SELECT u.id_conta_caixa, cf.saldo_inicial, cf.nome_conta
            FROM usuarios u
            INNER JOIN contas_financeiras cf ON u.id_conta_caixa = cf.id
            WHERE u.id = ?
        ");
        $stmt_usuario->execute([$id_usuario_caixa]);
        $conta_usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
        
        // 1. REMOVER do caixa do usuário
        if ($conta_usuario && $conta_usuario['id_conta_caixa']) {
            $novo_saldo_usuario = $conta_usuario['saldo_inicial'] - $valor_fechamento;
            
            // Se ficar negativo, zera
            if ($novo_saldo_usuario < 0) {
                $novo_saldo_usuario = 0;
            }
            
            $sql_usuario = "UPDATE contas_financeiras 
                           SET saldo_inicial = ?, 
                               data_saldo = ? 
                           WHERE id = ?";
            $stmt_upd_usuario = $pdo->prepare($sql_usuario);
            $stmt_upd_usuario->execute([$novo_saldo_usuario, $data_fechamento, $conta_usuario['id_conta_caixa']]);
        }
        
        // 2. ADICIONAR na conta de destino escolhida
        $stmt_destino = $pdo->prepare("SELECT saldo_inicial FROM contas_financeiras WHERE id = ?");
        $stmt_destino->execute([$id_conta_destino]);
        $saldo_destino = $stmt_destino->fetchColumn();
        
        $novo_saldo_destino = $saldo_destino + $valor_fechamento;
        
        $sql_destino = "UPDATE contas_financeiras 
                       SET saldo_inicial = ?, 
                           data_saldo = ? 
                       WHERE id = ?";
        $stmt_upd_destino = $pdo->prepare($sql_destino);
        $stmt_upd_destino->execute([$novo_saldo_destino, $data_fechamento, $id_conta_destino]);
        
        // CRIAR LANÇAMENTO FINANCEIRO para histórico
        if ($valor_fechamento > 0) {
            $descricao_lanc = "Fechamento do caixa #" . $id_caixa . " - Retorno de valores";
            
            $sql_lanc = "INSERT INTO lancamentos 
                        (id_admin, tipo, categoria, descricao, forma_pagamento, 
                         id_conta, valor, status, id_caixa_referencia, 
                         observacoes, data_vencimento, data_pagamento, data_cadastro, 
                         parcela_atual, total_parcelas) 
                        VALUES (?, 'ENTRADA', 'FECHAMENTO_CAIXA', ?, 'Dinheiro', 
                                ?, ?, 'PAGO', ?, ?, NOW(), NOW(), NOW(), 1, 1)";
            
            $stmt_lanc = $pdo->prepare($sql_lanc);
            $stmt_lanc->execute([
                $id_admin,
                $descricao_lanc,
                $id_conta_destino,
                $valor_fechamento,
                $id_caixa,
                $comentario
            ]);
        }
        
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Caixa encerrado e valores transferidos!']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

try {
    $stmtCaixa = $pdo->prepare("
        SELECT c.*, u.nome as nome_usuario
        FROM caixas c
        LEFT JOIN usuarios u ON c.id_usuario = u.id
        WHERE c.id = ? AND c.id_admin = ?
    ");
    $stmtCaixa->execute([$id_caixa, $id_admin]);
    $caixa = $stmtCaixa->fetch(PDO::FETCH_ASSOC);

    if (!$caixa) {
        die("<script>alert('Caixa não encontrado!'); window.location.href='movimentacao_caixa.php';</script>");
    }

    $inicio = $caixa['data_abertura'] . ' ' . $caixa['hora_abertura'];
    $fim = !empty($caixa['data_fechamento']) ? $caixa['data_fechamento'] : date('Y-m-d H:i:s');

    $stmtF = $pdo->prepare("SELECT id, nome_forma FROM formas_pagamento WHERE id_admin = ? ORDER BY nome_forma ASC");
    $stmtF->execute([$id_admin]);
    $formasDoSistema = $stmtF->fetchAll(PDO::FETCH_ASSOC);

    $stmtResumo = $pdo->prepare("
        SELECT 
            UPPER(TRIM(forma_pagamento)) as forma_nome,
            SUM(valor) as total_vendas
        FROM lancamentos
        WHERE id_caixa_referencia = ?
        AND tipo = 'ENTRADA'
        AND status = 'PAGO'
        AND (categoria = 'VENDAS' OR categoria IS NULL)
        GROUP BY forma_nome
    ");
    $stmtResumo->execute([$id_caixa]);
    $resumoData = $stmtResumo->fetchAll(PDO::FETCH_KEY_PAIR);

    $stmtLista = $pdo->prepare("
        SELECT * FROM lancamentos
        WHERE id_caixa_referencia = ?
        AND tipo = 'ENTRADA'
        AND status = 'PAGO'
        ORDER BY data_cadastro DESC
    ");
    $stmtLista->execute([$id_caixa]);
    $listaRecebimentos = $stmtLista->fetchAll(PDO::FETCH_ASSOC);
    
    $contas_financeiras = $pdo->query("SELECT id, nome_conta FROM contas_financeiras 
                                       WHERE id_admin = $id_admin AND status = 'Ativo' 
                                       ORDER BY nome_conta")->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_total = $pdo->prepare("
        SELECT 
            (SELECT COALESCE(valor_inicial, 0) FROM caixas WHERE id = ?) +
            (SELECT COALESCE(SUM(CASE 
                WHEN tipo = 'ENTRADA' THEN valor 
                WHEN tipo = 'SAIDA' THEN -valor 
                END), 0) FROM lancamentos 
             WHERE id_caixa_referencia = ? 
             AND status = 'PAGO'
             AND UPPER(TRIM(forma_pagamento)) = 'DINHEIRO')
            as total
    ");
    $stmt_total->execute([$id_caixa, $id_caixa]);
    $total_em_caixa = $stmt_total->fetchColumn();

} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

$titulo_pagina = "Detalhes do Caixa #" . $id_caixa;

// ✅ CORREÇÃO 2: Preparar hora atual para o JavaScript
$hora_atual = date('H:i');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    
    <link rel="stylesheet" href="<?= URL_BASE ?>css/style.css">
    <link rel="stylesheet" href="<?= URL_BASE ?>css/menu.css">
    <link rel="stylesheet" href="<?= URL_BASE ?>css/header.css">
    <link rel="stylesheet" href="<?= URL_BASE ?>css/formularios.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        :root {
            --roxo-ziip: #622599;
            --azul-ziip: #131c71;
            --verde-ziip: #28a745;
            --laranja-ziip: #f39c12;
            --vermelho-ziip: #b92426;
        }

        body { font-family: 'Exo', sans-serif; background: #ecf0f5; }

        .tabs-container {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
        }
        
        .tabs-header {
            display: flex;
            background: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .tab-button {
            flex: 1;
            padding: 18px 24px;
            background: transparent;
            border: none;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Exo', sans-serif;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .tab-button.active {
            background: #fff;
            color: var(--azul-ziip);
            border-bottom-color: var(--azul-ziip);
        }

        .caixa-info-card {
            background: #fff;
            padding: 25px;
            border-radius: 16px;
            margin-bottom: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .info-block label { display: block; font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase; margin-bottom: 5px; }
        .info-block span { font-size: 15px; font-weight: 600; color: #333; }

        .badge-status { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .status-aberto { background: #dff0d8; color: #3c763d; }
        .status-fechado { background: #f2dede; color: #a94442; }

        .section-title-ziip { margin-bottom: 20px; color: #2c3e50; font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 10px; }

        .table-custom { width: 100%; border-collapse: collapse; }
        .table-custom th { text-align: left; padding: 15px; background: #f8f9fa; border-bottom: 2px solid #eee; font-size: 13px; color: #555; }
        .table-custom td { padding: 15px; border-bottom: 1px solid #f2f2f2; font-size: 14px; }
        .row-total { background: #fdfdfd; font-weight: 700; }

        .footer-actions {
            background: #fff;
            padding: 20px 30px;
            border-radius: 16px;
            display: flex;
            gap: 12px;
            align-items: center;
            box-shadow: 0 -2px 12px rgba(0, 0, 0, 0.05);
            flex-wrap: wrap;
            margin-bottom: 30px;
        }

        .btn-ziip {
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            border: none;
            transition: 0.3s;
            text-decoration: none;
            color: #fff;
        }
        .btn-ziip:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }

        .bg-log { background: #6c757d; }
        .bg-sup { background: var(--verde-ziip); }
        .bg-san { background: var(--laranja-ziip); }
        .bg-des { background: var(--vermelho-ziip); }
        .bg-tra { background: #17a2b8; }
        .bg-enc { background: #2c3e50; }
        .bg-prt { background: #fff; color: #444; border: 2px solid #ddd; }
        .spacer { flex: 1; }
        
        .tab-pane { padding: 25px; }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include $base_path . 'menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include $base_path . 'menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <div class="form-header">
            <h1 class="form-title">
                <i class="fas fa-cash-register"></i>
                Informações do caixa
            </h1>
            <a href="movimentacao_caixa.php" class="btn-voltar">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <div class="caixa-info-card">
            <div class="info-block">
                <label>Caixa</label>
                <span><?= $caixa['id'] ?></span>
            </div>
            <div class="info-block">
                <label>Usuário</label>
                <span><?= htmlspecialchars($caixa['nome_usuario']) ?></span>
            </div>
            <div class="info-block">
                <label>Abertura</label>
                <span><?= date('d/m/Y H:i', strtotime($inicio)) ?></span>
            </div>
            <?php if($caixa['status'] == 'FECHADO'): ?>
            <div class="info-block">
                <label>Fechamento</label>
                <span><?= date('d/m/Y H:i', strtotime($caixa['data_fechamento'])) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-block">
                <label>Status</label>
                <span class="badge-status <?= $caixa['status'] == 'ABERTO' ? 'status-aberto' : 'status-fechado' ?>">
                    <?= $caixa['status'] ?>
                </span>
            </div>
        </div>

        <div class="tabs-container">
            <div class="tabs-header">
                <button class="tab-button active" onclick="trocarAba(event, 'aba-resumo')">Resumo</button>
                <button class="tab-button" onclick="trocarAba(event, 'aba-recebimentos')">Lista de recebimentos</button>
            </div>

            <div class="tabs-content">
                <div id="aba-resumo" class="tab-pane active">
                    <h4 class="section-title-ziip">Valores recebidos no caixa</h4>
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Forma de recebimento</th>
                                <th>Vendas</th>
                                <th>Suprimentos</th>
                                <th style="text-align: right;">Resultado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalVendas = 0; 
                            $totalSuprimento = 0;
                            
                            foreach($formasDoSistema as $f):
                                $nomeFormaOriginal = $f['nome_forma'];
                                $nomeBusca = strtoupper(trim($nomeFormaOriginal));
                                
                                $vendaVal = $resumoData[$nomeBusca] ?? 0;
                                $suprimentoVal = ($nomeBusca == 'DINHEIRO') ? (float)$caixa['valor_inicial'] : 0;
                                $resultado = $vendaVal + $suprimentoVal;
                                
                                if($resultado > 0 || $nomeBusca == 'DINHEIRO'):
                                    $totalVendas += $vendaVal;
                                    $totalSuprimento += $suprimentoVal;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($nomeFormaOriginal) ?></td>
                                <td><?= number_format($vendaVal, 2, ',', '.') ?></td>
                                <td><?= $suprimentoVal > 0 ? number_format($suprimentoVal, 2, ',', '.') : '-' ?></td>
                                <td style="text-align: right;"><strong><?= number_format($resultado, 2, ',', '.') ?></strong></td>
                            </tr>
                            <?php endif; endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="row-total">
                                <td>Total</td>
                                <td><?= number_format($totalVendas, 2, ',', '.') ?></td>
                                <td><?= number_format($totalSuprimento, 2, ',', '.') ?></td>
                                <td style="text-align: right;"><strong><?= number_format($totalVendas + $totalSuprimento, 2, ',', '.') ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <!-- MOVIMENTAÇÕES (SUPRIMENTOS/SANGRIAS) -->
                    <h4 class="section-title-ziip" style="margin-top: 40px;">Movimentações</h4>
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Hora</th>
                                <th>Tipo</th>
                                <th>Descrição</th>
                                <th>Conta</th>
                                <th>Forma</th>
                                <th style="text-align: right;">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Buscar movimentações (incluindo abertura e fechamento do caixa)
                            $movimentacoes = [];
                            
                            // 1. ABERTURA DO CAIXA
                            if ($caixa['valor_inicial'] > 0) {
                                $movimentacoes[] = [
                                    'data_cadastro' => $caixa['data_abertura'] . ' ' . $caixa['hora_abertura'],
                                    'tipo' => 'ABERTURA',
                                    'descricao' => $caixa['descricao'] ?: 'Abertura do caixa - Fundo de troco',
                                    'nome_conta' => null,
                                    'forma_pagamento' => 'Dinheiro',
                                    'valor' => $caixa['valor_inicial'],
                                    'observacoes' => null
                                ];
                            }
                            
                            // 2. MOVIMENTAÇÕES (Suprimentos, Sangrias, Despesas, Transferências)
                            $stmtMov = $pdo->prepare("
                                SELECT 
                                    l.data_cadastro,
                                    l.categoria as tipo,
                                    l.descricao,
                                    c.nome_conta,
                                    l.forma_pagamento,
                                    l.valor,
                                    l.observacoes
                                FROM lancamentos l
                                LEFT JOIN contas_financeiras c ON l.id_conta = c.id
                                WHERE l.id_caixa_referencia = ? 
                                AND l.categoria IN ('SUPRIMENTO', 'SANGRIA', 'DESPESA', 'TRANSFERENCIA')
                                ORDER BY l.data_cadastro DESC
                            ");
                            $stmtMov->execute([$id_caixa]);
                            $movLancamentos = $stmtMov->fetchAll(PDO::FETCH_ASSOC);
                            
                            $movimentacoes = array_merge($movimentacoes, $movLancamentos);
                            
                            // 3. FECHAMENTO DO CAIXA
                            if ($caixa['status'] == 'FECHADO' && $caixa['data_fechamento']) {
                                // Buscar nome da conta de fechamento
                                $stmtContaFech = $pdo->prepare("SELECT nome_conta FROM contas_financeiras WHERE id = ?");
                                $stmtContaFech->execute([$caixa['id_conta_fechamento']]);
                                $contaFech = $stmtContaFech->fetch(PDO::FETCH_ASSOC);
                                
                                $movimentacoes[] = [
                                    'data_cadastro' => $caixa['data_fechamento'],
                                    'tipo' => 'FECHAMENTO',
                                    'descricao' => 'Encerramento do caixa',
                                    'nome_conta' => $contaFech['nome_conta'] ?? null,
                                    'forma_pagamento' => 'Dinheiro',
                                    'valor' => $caixa['valor_fechamento'],
                                    'observacoes' => null
                                ];
                            }
                            
                            // Ordenar por data
                            usort($movimentacoes, function($a, $b) {
                                return strtotime($b['data_cadastro']) - strtotime($a['data_cadastro']);
                            });
                            
                            if(empty($movimentacoes)): 
                            ?>
                                <tr><td colspan="7" style="text-align: center; padding: 40px; color: #999;">Nenhuma movimentação registrada.</td></tr>
                            <?php else: foreach($movimentacoes as $mov): ?>
                            <tr>
                                <td><?= date('d/m', strtotime($mov['data_cadastro'])) ?></td>
                                <td><?= date('H:i', strtotime($mov['data_cadastro'])) ?></td>
                                <td><span class="badge-status" style="background:#eee; color:#555"><?= $mov['tipo'] ?></span></td>
                                <td>
                                    <?= htmlspecialchars($mov['descricao']) ?>
                                    <?php if($mov['observacoes']): ?>
                                        <br><small style="color:#999;font-style:italic">Observações: <?= htmlspecialchars($mov['observacoes']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($mov['nome_conta'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($mov['forma_pagamento']) ?></td>
                                <td style="text-align: right; font-weight: 700;">
                                    <?= number_format($mov['valor'], 2, ',', '.') ?>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <div id="aba-recebimentos" class="tab-pane" style="display:none;">
                    <h4 class="section-title-ziip">Lista de recebimentos</h4>
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Hora</th>
                                <th>Descrição</th>
                                <th>Cliente</th>
                                <th>Forma</th>
                                <th style="text-align: right;">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($listaRecebimentos)): ?>
                                <tr><td colspan="6" style="text-align: center; padding: 40px; color: #999;">Nenhum lançamento encontrado.</td></tr>
                            <?php else: foreach($listaRecebimentos as $l): ?>
                            <tr>
                                <td><?= date('d/m', strtotime($l['data_cadastro'])) ?></td>
                                <td><?= date('H:i', strtotime($l['data_cadastro'])) ?></td>
                                <td><?= htmlspecialchars($l['descricao']) ?></td>
                                <td><?= htmlspecialchars($l['fornecedor_cliente']) ?></td>
                                <td><span class="badge-status" style="background:#eee; color:#555"><?= $l['forma_pagamento'] ?></span></td>
                                <td style="text-align: right; font-weight: 700;">R$ <?= number_format($l['valor'], 2, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if($caixa['status'] == 'ABERTO'): ?>
        <div class="footer-actions">
            <button class="btn-ziip bg-sup" onclick="abrirModalSuprimento()"><i class="fas fa-plus-circle"></i> SUPRIMENTO</button>
            <button class="btn-ziip bg-san" onclick="abrirModalSangria()"><i class="fas fa-minus-circle"></i> SANGRIA</button>
            <button class="btn-ziip bg-des" onclick="abrirModalDespesa()"><i class="fas fa-dollar-sign"></i> DESPESA</button>
            <button class="btn-ziip bg-tra" onclick="abrirModalTransferencia()"><i class="fas fa-exchange-alt"></i> TRANSFERÊNCIA</button>
            
            <div class="spacer"></div>
            
            <button class="btn-ziip bg-enc" onclick="abrirModalEncerramento()"><i class="fas fa-check"></i> REVISAR E ENCERRAR</button>
            <button class="btn-ziip bg-prt" onclick="window.print()"><i class="fas fa-print"></i> IMPRIMIR</button>
        </div>
        <?php else: ?>
        <div class="footer-actions">
            <button class="btn-ziip bg-prt" onclick="window.print()"><i class="fas fa-print"></i> IMPRIMIR</button>
            <a href="movimentacao_caixa.php" class="btn-ziip bg-log"><i class="fas fa-arrow-left"></i> VOLTAR</a>
        </div>
        <?php endif; ?>

    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function trocarAba(event, abaId) {
            const panes = document.querySelectorAll('.tab-pane');
            const btns = document.querySelectorAll('.tab-button');
            panes.forEach(p => p.style.display = 'none');
            btns.forEach(b => b.classList.remove('active'));
            document.getElementById(abaId).style.display = 'block';
            event.currentTarget.classList.add('active');
        }
        
        function abrirModalSuprimento() {
            Swal.fire({
                title: 'Adicionar Suprimento',
                html: `
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px">
                        <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Valor*</label>
                        <input type="text" id="valor_sup" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px" placeholder="0,00"></div>
                        <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Forma*</label>
                        <select id="forma_sup" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px">
                            <option value="">Selecione...</option>
                            <?php foreach($formasDoSistema as $f): ?>
                                <option value="<?= htmlspecialchars($f['nome_forma']) ?>"><?= htmlspecialchars($f['nome_forma']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    </div>
                    <div style="margin-bottom:15px"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Descrição</label>
                    <input type="text" id="descricao_sup" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px"></div>
                    <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Observações</label>
                    <textarea id="obs_sup" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px" rows="3"></textarea></div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Adicionar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#28a745',
                width: '600px',
                preConfirm: () => {
                    if (!document.getElementById('valor_sup').value || !document.getElementById('forma_sup').value) {
                        Swal.showValidationMessage('Preencha todos os campos obrigatórios');
                        return false;
                    }
                    return {
                        valor: document.getElementById('valor_sup').value,
                        forma_pagamento: document.getElementById('forma_sup').value,
                        descricao: document.getElementById('descricao_sup').value || 'Suprimento de caixa',
                        observacoes: document.getElementById('obs_sup').value
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) adicionarMovimentacao('SUPRIMENTO', result.value);
            });
        }
        
        function abrirModalSangria() {
            Swal.fire({
                title: 'Registrar Sangria',
                html: `
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px">
                        <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Valor*</label>
                        <input type="text" id="valor_san" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px" placeholder="0,00"></div>
                        <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Forma*</label>
                        <select id="forma_san" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px">
                            <option value="">Selecione...</option>
                            <?php foreach($formasDoSistema as $f): ?>
                                <option value="<?= htmlspecialchars($f['nome_forma']) ?>"><?= htmlspecialchars($f['nome_forma']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    </div>
                    <div style="margin-bottom:15px"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Descrição</label>
                    <input type="text" id="descricao_san" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px"></div>
                    <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Observações</label>
                    <textarea id="obs_san" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px" rows="3"></textarea></div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Registrar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#f39c12',
                width: '600px',
                preConfirm: () => {
                    if (!document.getElementById('valor_san').value || !document.getElementById('forma_san').value) {
                        Swal.showValidationMessage('Preencha todos os campos obrigatórios');
                        return false;
                    }
                    return {
                        valor: document.getElementById('valor_san').value,
                        forma_pagamento: document.getElementById('forma_san').value,
                        descricao: document.getElementById('descricao_san').value || 'Sangria de caixa',
                        observacoes: document.getElementById('obs_san').value
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) adicionarMovimentacao('SANGRIA', result.value);
            });
        }
        
        function abrirModalDespesa() {
            Swal.fire({
                title: 'Registrar Despesa',
                html: `
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px">
                        <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Valor*</label>
                        <input type="text" id="valor_des" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px" placeholder="0,00"></div>
                        <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Forma*</label>
                        <select id="forma_des" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px">
                            <option value="">Selecione...</option>
                            <?php foreach($formasDoSistema as $f): ?>
                                <option value="<?= htmlspecialchars($f['nome_forma']) ?>"><?= htmlspecialchars($f['nome_forma']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    </div>
                    <div style="margin-bottom:15px"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Descrição*</label>
                    <input type="text" id="descricao_des" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px" required></div>
                    <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Observações</label>
                    <textarea id="obs_des" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px" rows="3"></textarea></div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Registrar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#b92426',
                width: '600px',
                preConfirm: () => {
                    if (!document.getElementById('valor_des').value || !document.getElementById('forma_des').value || !document.getElementById('descricao_des').value) {
                        Swal.showValidationMessage('Preencha todos os campos obrigatórios');
                        return false;
                    }
                    return {
                        valor: document.getElementById('valor_des').value,
                        forma_pagamento: document.getElementById('forma_des').value,
                        descricao: document.getElementById('descricao_des').value,
                        observacoes: document.getElementById('obs_des').value
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) adicionarMovimentacao('DESPESA', result.value);
            });
        }
        
        function abrirModalTransferencia() {
            Swal.fire({
                title: 'Registrar Transferência',
                html: `
                    <div style="margin-bottom:15px"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Valor*</label>
                    <input type="text" id="valor_tra" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px" placeholder="0,00"></div>
                    <div style="margin-bottom:15px"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Conta Destino*</label>
                    <select id="conta_tra" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px">
                        <option value="">Selecione...</option>
                        <?php foreach($contas_financeiras as $conta): ?>
                            <option value="<?= $conta['id'] ?>"><?= htmlspecialchars($conta['nome_conta']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                    <div style="margin-bottom:15px"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Descrição</label>
                    <input type="text" id="descricao_tra" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px"></div>
                    <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Observações</label>
                    <textarea id="obs_tra" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px" rows="3"></textarea></div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Transferir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#17a2b8',
                width: '600px',
                preConfirm: () => {
                    if (!document.getElementById('valor_tra').value || !document.getElementById('conta_tra').value) {
                        Swal.showValidationMessage('Preencha todos os campos obrigatórios');
                        return false;
                    }
                    return {
                        valor: document.getElementById('valor_tra').value,
                        id_conta: document.getElementById('conta_tra').value,
                        forma_pagamento: 'Transferência',
                        descricao: document.getElementById('descricao_tra').value || 'Transferência bancária',
                        observacoes: document.getElementById('obs_tra').value
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) adicionarMovimentacao('TRANSFERENCIA', result.value);
            });
        }
        
        function abrirModalEncerramento() {
            Swal.fire({
                title: 'Encerramento do caixa <?= $caixa['id'] ?>',
                html: `
                    <div style="background:#dbeafe;padding:20px;border-radius:12px;text-align:center;margin:20px 0">
                        <div style="font-size:14px;color:#1e40af;font-weight:600;margin-bottom:8px">Em caixa</div>
                        <div style="font-size:32px;font-weight:700;color:#1e40af">R$ <?= number_format($total_em_caixa, 2, ',', '.') ?></div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px">
                        <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Data*</label>
                        <input type="date" id="data_enc" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px" value="<?= date('Y-m-d') ?>"></div>
                        <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Hora*</label>
                        <input type="time" id="hora_enc" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px" value="<?= $hora_atual ?>"></div>
                    </div>
                    <div style="margin-bottom:15px"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Conta destino*</label>
                    <select id="conta_enc" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px">
                        <option value="">Selecione...</option>
                        <?php foreach($contas_financeiras as $conta): ?>
                            <option value="<?= $conta['id'] ?>"><?= htmlspecialchars($conta['nome_conta']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                    <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Comentário</label>
                    <textarea id="comentario_enc" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px" rows="4"></textarea></div>
                `,
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonText: '<i class="fas fa-check"></i> Encerrar caixa',
                denyButtonText: 'Colocar em revisão',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#28a745',
                denyButtonColor: '#6c757d',
                width: '600px',
                preConfirm: () => {
                    if (!document.getElementById('conta_enc').value || !document.getElementById('data_enc').value || !document.getElementById('hora_enc').value) {
                        Swal.showValidationMessage('Preencha todos os campos obrigatórios');
                        return false;
                    }
                    return {
                        id_conta_destino: document.getElementById('conta_enc').value,
                        data_fechamento: document.getElementById('data_enc').value,
                        hora_fechamento: document.getElementById('hora_enc').value,
                        comentario: document.getElementById('comentario_enc').value
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    encerrarCaixa(result.value);
                } else if (result.isDenied) {
                    Swal.fire('Info', 'Caixa colocado em revisão', 'info');
                }
            });
        }
        
        async function adicionarMovimentacao(tipo, dados) {
            const formData = new FormData();
            formData.append('acao', 'adicionar_movimentacao');
            formData.append('tipo', tipo);
            for (let key in dados) {
                formData.append(key, dados[key]);
            }
            
            try {
                const res = await fetch('detalhes_movimentacao.php?id=<?= $id_caixa ?>', {method: 'POST', body: formData});
                const resposta = await res.json();
                
                if (resposta.status === 'success') {
                    Swal.fire({title: 'Sucesso!', text: resposta.message, icon: 'success', confirmButtonColor: '#28a745'}).then(() => location.reload());
                } else {
                    Swal.fire('Erro', resposta.message, 'error');
                }
            } catch (e) {
                Swal.fire('Erro', 'Erro ao processar movimentação', 'error');
            }
        }
        
        async function encerrarCaixa(dados) {
            const formData = new FormData();
            formData.append('acao', 'encerrar_caixa');
            for (let key in dados) {
                formData.append(key, dados[key]);
            }
            
            try {
                const res = await fetch('detalhes_movimentacao.php?id=<?= $id_caixa ?>', {method: 'POST', body: formData});
                const resposta = await res.json();
                
                if (resposta.status === 'success') {
                    Swal.fire({title: 'Sucesso!', text: resposta.message, icon: 'success', confirmButtonColor: '#28a745'}).then(() => location.reload());
                } else {
                    Swal.fire('Erro', resposta.message, 'error');
                }
            } catch (e) {
                Swal.fire('Erro', 'Erro ao encerrar caixa', 'error');
            }
        }
    </script>

</body>
</html>