<?php
/**
 * =========================================================================================
 * ZIIPVET - LANÇAMENTOS FINANCEIROS
 * ARQUIVO: lancamentos.php
 * VERSÃO: 6.0.0 - COM COMPENSAÇÃO, PARCELAMENTO E EXIBIÇÃO DE TAXAS
 * =========================================================================================
 */
require_once 'auth.php';
require_once 'config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;

// ==========================================================
// CONFIGURAÇÃO DA PÁGINA
// ==========================================================
$titulo_pagina = "Lançamentos Financeiros";
$aba = $_GET['tab'] ?? 'resumo';

// ==========================================================
// PROCESSAMENTO DE DADOS
// ==========================================================
try {
    // 1. Totais dos Cartões
    $stmt = $pdo->prepare("SELECT 
        SUM(CASE WHEN tipo = 'ENTRADA' AND status = 'PAGO' THEN valor ELSE 0 END) as receitas_pagas,
        SUM(CASE WHEN tipo = 'SAIDA' AND status = 'PAGO' THEN valor ELSE 0 END) as despesas_pagas,
        SUM(CASE WHEN tipo = 'ENTRADA' THEN valor ELSE 0 END) as receitas_total,
        SUM(CASE WHEN tipo = 'SAIDA' THEN valor ELSE 0 END) as despesas_total
        FROM lancamentos 
        WHERE id_admin = ?");
    $stmt->execute([$id_admin]);
    $row_totais = $stmt->fetch(PDO::FETCH_ASSOC);

    $total_receitas = $row_totais['receitas_pagas'] ?? 0;
    $total_despesas = $row_totais['despesas_pagas'] ?? 0;
    $resultado = $total_receitas - $total_despesas;

    // 2. Agrupamento por Forma de Pagamento (COM TRATAMENTO)
    $stmt_forma = $pdo->prepare("SELECT 
        CASE 
            WHEN forma_pagamento IS NULL OR forma_pagamento = '' THEN 'Não especificada'
            WHEN forma_pagamento REGEXP '^[0-9]+$' THEN 'Não especificada'
            ELSE forma_pagamento 
        END as forma_pagamento_tratada,
        COUNT(*) as qtd,
        SUM(CASE WHEN tipo = 'ENTRADA' THEN valor ELSE 0 END) as rec,
        SUM(CASE WHEN tipo = 'SAIDA' THEN valor ELSE 0 END) as desp
        FROM lancamentos 
        WHERE id_admin = ?
        GROUP BY forma_pagamento_tratada
        ORDER BY forma_pagamento_tratada");
    $stmt_forma->execute([$id_admin]);
    $formas_pagto = $stmt_forma->fetchAll(PDO::FETCH_ASSOC);

    // 3. Agrupamento por Status
    $stmt_status = $pdo->prepare("SELECT 
        status,
        COUNT(*) as qtd,
        SUM(CASE WHEN tipo = 'ENTRADA' THEN valor ELSE 0 END) as rec,
        SUM(CASE WHEN tipo = 'SAIDA' THEN valor ELSE 0 END) as desp
        FROM lancamentos 
        WHERE id_admin = ?
        GROUP BY status
        ORDER BY FIELD(status, 'PAGO', 'PENDENTE', 'CANCELADO')");
    $stmt_status->execute([$id_admin]);
    $status_list = $stmt_status->fetchAll(PDO::FETCH_ASSOC);

    // 4. Estatísticas de Parcelamento
    $stmt_parcelamento = $pdo->prepare("SELECT 
        COUNT(DISTINCT id_venda) as total_vendas_parceladas,
        SUM(valor) as valor_total_parcelado,
        COUNT(*) as total_parcelas
        FROM lancamentos 
        WHERE id_admin = ? 
        AND total_parcelas > 1
        AND tipo = 'ENTRADA'");
    $stmt_parcelamento->execute([$id_admin]);
    $stats_parcelamento = $stmt_parcelamento->fetch(PDO::FETCH_ASSOC);

    // 5. Próximas Compensações (30 dias)
    $stmt_compensacao = $pdo->prepare("SELECT 
        COUNT(*) as qtd_compensacoes,
        SUM(valor) as valor_compensacoes
        FROM lancamentos 
        WHERE id_admin = ? 
        AND data_compensacao IS NOT NULL
        AND data_compensacao BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        AND status = 'PAGO'
        AND tipo = 'ENTRADA'");
    $stmt_compensacao->execute([$id_admin]);
    $stats_compensacao = $stmt_compensacao->fetch(PDO::FETCH_ASSOC);

    // 6. ✅ BUSCAR CONTAS FINANCEIRAS PARA O FILTRO
    $stmt_contas = $pdo->prepare("SELECT id, nome_conta, tipo_conta, categoria 
                                   FROM contas_financeiras 
                                   WHERE id_admin = ? 
                                   AND status = 'Ativo' 
                                   ORDER BY categoria, nome_conta");
    $stmt_contas->execute([$id_admin]);
    $contas_financeiras = $stmt_contas->fetchAll(PDO::FETCH_ASSOC);

    // 7. ✅ CAPTURAR FILTROS
    $filtro_conta = $_GET['conta'] ?? '';
    $filtro_categoria = $_GET['categoria'] ?? '';

    // 8. ✅ Listagem de Lançamentos COM DADOS DA VENDA E FILTROS
    $sql_lancamentos = "SELECT l.*, v.valor_total as venda_valor_bruto
                        FROM lancamentos l
                        LEFT JOIN vendas v ON l.id_venda = v.id
                        WHERE l.id_admin = ?";
    
    $params = [$id_admin];
    
    // Adicionar filtro de conta se selecionado
    if (!empty($filtro_conta)) {
        $sql_lancamentos .= " AND l.id_conta_financeira = ?";
        $params[] = $filtro_conta;
    }
    
    // Adicionar filtro de categoria se selecionado
    if (!empty($filtro_categoria)) {
        $sql_lancamentos .= " AND l.categoria = ?";
        $params[] = $filtro_categoria;
    }
    
    $sql_lancamentos .= " ORDER BY l.data_vencimento DESC, l.id DESC LIMIT 200";
    
    $stmt_list = $pdo->prepare($sql_lancamentos);
    $stmt_list->execute($params);
    $lista_lancamentos = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_count = $pdo->prepare("SELECT COUNT(*) as total FROM lancamentos WHERE id_admin = ?");
    $stmt_count->execute([$id_admin]);
    $total_lancamentos = $stmt_count->fetchColumn();

} catch (PDOException $e) {
    die("Erro ao processar dados: " . $e->getMessage());
}

function tratarFormaPagamento($forma) {
    if (empty($forma)) return 'Não especificada';
    if (is_numeric($forma)) return 'Não especificada';
    return $forma;
}

function traduzirStatus($status) {
    return match($status) {
        'PAGO' => 'Pago',
        'PENDENTE' => 'Não pago',
        'CANCELADO' => 'Cancelado',
        default => $status
    };
}

/**
 * ✅ NOVA FUNÇÃO: Extrair informações de taxa da descrição
 */
function extrairInfoTaxa($descricao) {
    $info = [
        'tem_taxa' => false,
        'taxa_percent' => 0,
        'taxa_valor' => 0,
        'valor_bruto' => 0,
        'valor_liquido' => 0
    ];
    
    // Padrão: Taxa: 4% (R$ 8,13) | Bruto: R$ 203,20 → Líquido: R$ 195,07
    if (preg_match('/Taxa:\s*([0-9,\.]+)%\s*\(R\$\s*([0-9,\.]+)\)/', $descricao, $matches)) {
        $info['tem_taxa'] = true;
        $info['taxa_percent'] = str_replace(',', '.', $matches[1]);
        $info['taxa_valor'] = str_replace(',', '.', $matches[2]);
    }
    
    if (preg_match('/Bruto:\s*R\$\s*([0-9,\.]+)/', $descricao, $matches)) {
        $info['valor_bruto'] = str_replace(['.', ','], ['', '.'], $matches[1]);
    }
    
    if (preg_match('/Líquido:\s*R\$\s*([0-9,\.]+)/', $descricao, $matches)) {
        $info['valor_liquido'] = str_replace(['.', ','], ['', '.'], $matches[1]);
    }
    
    return $info;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/menu.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/formularios.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .kpi-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .kpi-card { background: #fff; padding: 20px 25px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); position: relative; overflow: hidden; }
        .kpi-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 3px; }
        .kpi-card.receitas::before { background: linear-gradient(90deg, #28a745 0%, #20c997 100%); }
        .kpi-card.despesas::before { background: linear-gradient(90deg, #dc3545 0%, #c82333 100%); }
        .kpi-card.resultado::before { background: linear-gradient(90deg, #1e40af 0%, #1e3a8a 100%); }
        .kpi-card.parcelamento::before { background: linear-gradient(90deg, #f39c12 0%, #e67e22 100%); }
        .kpi-card.compensacao::before { background: linear-gradient(90deg, #1976d2 0%, #1565c0 100%); }
        .kpi-icon { position: absolute; top: 15px; left: 15px; font-size: 32px; opacity: 0.1; }
        .kpi-card.receitas .kpi-icon { color: #28a745; }
        .kpi-card.despesas .kpi-icon { color: #dc3545; }
        .kpi-card.resultado .kpi-icon { color: #1e40af; }
        .kpi-card.parcelamento .kpi-icon { color: #f39c12; }
        .kpi-card.compensacao .kpi-icon { color: #1976d2; }
        .kpi-content { text-align: right; position: relative; z-index: 1; }
        .kpi-label { font-size: 11px; color: #6c757d; font-weight: 700; text-transform: uppercase; margin-bottom: 8px; font-family: 'Exo', sans-serif; display: flex; align-items: center; justify-content: flex-end; gap: 6px; }
        .kpi-value { font-size: 28px; font-weight: 700; font-family: 'Exo', sans-serif; }
        .kpi-card.receitas .kpi-value { color: #28a745; }
        .kpi-card.despesas .kpi-value { color: #dc3545; }
        .kpi-card.resultado .kpi-value { color: #1e40af; }
        .kpi-card.parcelamento .kpi-value { color: #f39c12; }
        .kpi-card.compensacao .kpi-value { color: #1976d2; }
        .kpi-subtitle { font-size: 11px; color: #999; margin-top: 6px; }
        .tabs-container { background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        .tabs-header { display: flex; gap: 0; border-bottom: 2px solid #e0e0e0; background: #f8f9fa; }
        .tab-link { flex: 1; padding: 18px 24px; font-size: 15px; font-weight: 700; color: #6c757d; text-decoration: none; text-align: center; border-bottom: 3px solid transparent; font-family: 'Exo', sans-serif; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.3s; }
        .tab-link:hover { background: #e9ecef; color: #1e40af; }
        .tab-link.active { background: #fff; color: #1e40af; border-bottom-color: #1e40af; }
        .tab-content { padding: 25px; }
        .report-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 12px; margin-bottom: 25px; }
        .report-card.with-overflow { overflow: hidden; }
        .report-header { background: #1e40af; color: #fff; padding: 18px 20px; font-weight: 700; font-size: 16px; font-family: 'Exo', sans-serif; display: flex; align-items: center; gap: 10px; border-radius: 12px 12px 0 0; }
        
        /* ✅ TABELA DE RESUMO - SEM SCROLL */
        .report-body-resumo {
            padding: 0;
            overflow: visible !important;
        }
        
        /* ✅ TABELA DE LANÇAMENTOS - SEM SCROLL TAMBÉM */
        .report-body-lancamentos {
            padding: 0;
            overflow: visible !important;
        }
        .data-table { width: 100%; border-collapse: collapse; font-family: 'Exo', sans-serif; }
        
        /* ✅ TABELA DE LANÇAMENTOS - AJUSTE AUTOMÁTICO SEM SCROLL */
        .report-body-lancamentos .data-table {
            table-layout: fixed;
            width: 100%;
        }
        
        /* ✅ TABELA DE RESUMO - AJUSTE AUTOMÁTICO */
        .report-body-resumo .data-table {
            table-layout: fixed;
            width: 100%;
        }
        
        .data-table thead { background: #f8f9fa; }
        .data-table thead th { text-align: left; padding: 12px 10px; color: #495057; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e0e0e0; }
        .data-table tbody td { padding: 14px 12px; border-bottom: 1px solid #f0f0f0; font-size: 13px; color: #2c3e50; word-wrap: break-word; overflow-wrap: break-word; }
        
        /* ✅ CÉLULAS DE LANÇAMENTOS - PERMITIR QUEBRA */
        .report-body-lancamentos .data-table tbody td {
            word-wrap: break-word;
            overflow-wrap: break-word;
            max-width: 0;
        }
        
        .data-table tbody tr:hover { background: #f8f9fa; }
        .data-table tfoot td { padding: 14px 16px; border-top: 2px solid #e0e0e0; background: #f8f9fa; font-weight: 700; font-size: 15px; }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        .val-receita { color: #28a745; font-weight: 700; font-size: 14px; }
        .val-despesa { color: #dc3545; font-weight: 700; font-size: 14px; }
        .val-total { font-weight: 700; font-size: 14px; color: #2c3e50; }
        .empty-state { text-align: center; padding: 80px 20px; color: #adb5bd; }
        .empty-state i { font-size: 64px; margin-bottom: 20px; opacity: 0.3; }
        .table-footer { padding: 15px 20px; text-align: center; color: #6c757d; font-size: 13px; background: #f8f9fa; border-top: 1px solid #e0e0e0; }
        .small-detail { display: block; margin-top: 3px; font-size: 11px; color: #6c757d; }
        .resumo-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; align-items: start; }
        @media (max-width: 1200px) { .resumo-grid { grid-template-columns: 1fr; } }
        
        /* Estilos para Parcelamento e Compensação */
        .badge-parcela { 
            display: inline-block; 
            padding: 3px 8px; 
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%); 
            color: #fff; 
            border-radius: 12px; 
            font-size: 10px; 
            font-weight: 700; 
            text-transform: uppercase;
            font-family: 'Exo', sans-serif;
        }
        
        .badge-avista { 
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px; 
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%); 
            color: #fff; 
            border-radius: 12px; 
            font-size: 10px; 
            font-weight: 700;
            font-family: 'Exo', sans-serif;
        }
        
        .data-compensacao {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            background: #e3f2fd;
            color: #1976d2;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 600;
            font-family: 'Exo', sans-serif;
        }
        
        .highlight-parcelado {
            background: #f0f4ff !important;
        }
        
        .taxa-info {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 6px;
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-radius: 4px;
            font-size: 9px;
            color: #856404;
            font-weight: 600;
            margin-left: 4px;
        }
        
        /* ✅ NOVOS ESTILOS PARA EXIBIÇÃO DE VALORES */
        .valor-box {
            text-align: right;
        }
        
        .valor-principal {
            font-size: 14px;
            font-weight: 700;
            color: #28a745;
        }
        
        .valor-secundario {
            font-size: 10px;
            color: #6c757d;
            margin-top: 2px;
            display: block;
        }
        
        .valor-bruto-cell {
            text-align: right;
            color: #6c757d;
            font-size: 13px;
        }
        
        .valor-liquido-cell {
            text-align: right;
        }
        
        .com-taxa {
            color: #28a745 !important;
            font-weight: 700;
            font-size: 14px;
        }
        
        .sem-taxa {
            color: #999;
            font-style: italic;
            font-size: 11px;
        }
        
        /* ✅ FILTROS DA ABA LANÇAMENTOS */
        .filtros-lancamentos {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filtro-grupo {
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-width: 200px;
            flex: 1;
        }
        
        .filtro-grupo label {
            font-size: 11px;
            font-weight: 700;
            color: #495057;
            text-transform: uppercase;
            font-family: 'Exo', sans-serif;
        }
        
        .filtro-grupo select,
        .filtro-grupo input {
            height: 42px;
            padding: 0 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Exo', sans-serif;
            transition: all 0.3s ease;
        }
        
        .filtro-grupo select:focus,
        .filtro-grupo input:focus {
            border-color: #1e40af;
            outline: none;
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }
        
        .filtros-acoes {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            padding-top: 18px;
        }
        
        .btn-filtro {
            height: 42px;
            padding: 0 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-filtro.primary {
            background: #1e40af;
            color: #fff;
            box-shadow: 0 2px 8px rgba(30, 64, 175, 0.3);
        }
        
        .btn-filtro.primary:hover {
            background: #1e3a8a;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.4);
        }
        
        .btn-filtro.secondary {
            background: #6c757d;
            color: #fff;
        }
        
        .btn-filtro.secondary:hover {
            background: #5a6268;
        }
        
        .btn-filtro.success {
            background: #28a745;
            color: #fff;
        }
        
        .btn-filtro.success:hover {
            background: #218838;
        }
        
        .btn-filtro.info {
            background: #17a2b8;
            color: #fff;
        }
        
        .btn-filtro.info:hover {
            background: #138496;
        }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include 'menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include 'menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <div class="form-header">
            <h1 class="form-title">
                <i class="fas fa-clipboard-list"></i>
                Lançamentos Financeiros
            </h1>
            <p style="font-size: 14px; color: #6c757d; margin-top: 5px;">
                Total de <strong><?= number_format($total_lancamentos, 0, ',', '.') ?></strong> lançamento(s)
            </p>
        </div>

        <!-- TABS -->
        <div class="tabs-container">
            <div class="tabs-header">
                <a href="?tab=resumo" class="tab-link <?= $aba == 'resumo' ? 'active' : '' ?>">
                    <i class="fas fa-chart-pie"></i> Resumo
                </a>
                <a href="?tab=lancamentos" class="tab-link <?= $aba == 'lancamentos' ? 'active' : '' ?>">
                    <i class="fas fa-list"></i> Lançamentos
                </a>
            </div>

            <div class="tab-content">
                <?php if ($aba == 'resumo'): ?>
                    
                    <!-- ✅ KPI CARDS - APENAS NA ABA RESUMO -->
                    <div class="kpi-cards">
                        <div class="kpi-card receitas">
                            <i class="fas fa-arrow-up kpi-icon"></i>
                            <div class="kpi-content">
                                <div class="kpi-label">Receitas <i class="fas fa-arrow-circle-up"></i></div>
                                <div class="kpi-value">R$ <?= number_format($total_receitas, 2, ',', '.') ?></div>
                            </div>
                        </div>
                        <div class="kpi-card despesas">
                            <i class="fas fa-arrow-down kpi-icon"></i>
                            <div class="kpi-content">
                                <div class="kpi-label">Despesas <i class="fas fa-arrow-circle-down"></i></div>
                                <div class="kpi-value">R$ <?= number_format($total_despesas, 2, ',', '.') ?></div>
                            </div>
                        </div>
                        <div class="kpi-card resultado">
                            <i class="fas fa-<?= $resultado >= 0 ? 'check-circle' : 'exclamation-circle' ?> kpi-icon"></i>
                            <div class="kpi-content">
                                <div class="kpi-label">Resultado <i class="fas fa-calculator"></i></div>
                                <div class="kpi-value" style="color: <?= $resultado >= 0 ? '#28a745' : '#dc3545' ?>">
                                    R$ <?= number_format($resultado, 2, ',', '.') ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($stats_parcelamento['total_vendas_parceladas'] > 0): ?>
                        <div class="kpi-card parcelamento">
                            <i class="fas fa-credit-card kpi-icon"></i>
                            <div class="kpi-content">
                                <div class="kpi-label">Vendas Parceladas <i class="fas fa-layer-group"></i></div>
                                <div class="kpi-value"><?= number_format($stats_parcelamento['total_vendas_parceladas'], 0, ',', '.') ?></div>
                                <div class="kpi-subtitle">
                                    R$ <?= number_format($stats_parcelamento['valor_total_parcelado'], 2, ',', '.') ?> em <?= $stats_parcelamento['total_parcelas'] ?> parcelas
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($stats_compensacao['qtd_compensacoes'] > 0): ?>
                        <div class="kpi-card compensacao">
                            <i class="fas fa-calendar-check kpi-icon"></i>
                            <div class="kpi-content">
                                <div class="kpi-label">Compensações (30 dias) <i class="fas fa-clock"></i></div>
                                <div class="kpi-value"><?= number_format($stats_compensacao['qtd_compensacoes'], 0, ',', '.') ?></div>
                                <div class="kpi-subtitle">
                                    R$ <?= number_format($stats_compensacao['valor_compensacoes'], 2, ',', '.') ?> a receber
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="resumo-grid">
                        <!-- FORMA DE PAGAMENTO -->
                        <div class="report-card">
                            <div class="report-header"><i class="fas fa-credit-card"></i> Forma de Pagamento</div>
                            <div class="report-body report-body-resumo">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 35%;">Forma de Pagamento</th>
                                            <th class="text-right" style="width: 10%;">Qtd.</th>
                                            <th class="text-right" style="width: 18%;">Receitas</th>
                                            <th class="text-right" style="width: 18%;">Despesas</th>
                                            <th class="text-right" style="width: 19%;">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($formas_pagto as $f): 
                                            $f_total = $f['rec'] - $f['desp'];
                                        ?>
                                        <tr>
                                            <td style="font-weight: 600;"><?= htmlspecialchars($f['forma_pagamento_tratada']) ?></td>
                                            <td class="text-right"><?= $f['qtd'] ?></td>
                                            <td class="text-right val-receita">R$ <?= number_format($f['rec'], 2, ',', '.') ?></td>
                                            <td class="text-right val-despesa">R$ <?= number_format($f['desp'], 2, ',', '.') ?></td>
                                            <td class="text-right val-total">
                                                <?= $f_total >= 0 ? '' : '-' ?>R$ <?= number_format(abs($f_total), 2, ',', '.') ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td>TOTAL</td>
                                            <td class="text-right"><?= array_sum(array_column($formas_pagto, 'qtd')) ?></td>
                                            <td class="text-right val-receita">
                                                R$ <?= number_format(array_sum(array_column($formas_pagto, 'rec')), 2, ',', '.') ?>
                                            </td>
                                            <td class="text-right val-despesa">
                                                R$ <?= number_format(array_sum(array_column($formas_pagto, 'desp')), 2, ',', '.') ?>
                                            </td>
                                            <td class="text-right val-total">R$ <?= number_format($resultado, 2, ',', '.') ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- STATUS -->
                        <div class="report-card">
                            <div class="report-header"><i class="fas fa-check-circle"></i> Status</div>
                            <div class="report-body report-body-resumo">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 35%;">Status</th>
                                            <th class="text-right" style="width: 10%;">Qtd.</th>
                                            <th class="text-right" style="width: 18%;">Receitas</th>
                                            <th class="text-right" style="width: 18%;">Despesas</th>
                                            <th class="text-right" style="width: 19%;">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($status_list as $s): 
                                            $s_total = $s['rec'] - $s['desp'];
                                        ?>
                                        <tr>
                                            <td style="font-weight: 600;"><?= traduzirStatus($s['status']) ?></td>
                                            <td class="text-right"><?= $s['qtd'] ?></td>
                                            <td class="text-right val-receita">R$ <?= number_format($s['rec'], 2, ',', '.') ?></td>
                                            <td class="text-right val-despesa">R$ <?= number_format($s['desp'], 2, ',', '.') ?></td>
                                            <td class="text-right val-total">
                                                <?= $s_total >= 0 ? '' : '-' ?>R$ <?= number_format(abs($s_total), 2, ',', '.') ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td>TOTAL</td>
                                            <td class="text-right"><?= array_sum(array_column($status_list, 'qtd')) ?></td>
                                            <td class="text-right val-receita">
                                                R$ <?= number_format($row_totais['receitas_total'], 2, ',', '.') ?>
                                            </td>
                                            <td class="text-right val-despesa">
                                                R$ <?= number_format($row_totais['despesas_total'], 2, ',', '.') ?>
                                            </td>
                                            <td class="text-right val-total">
                                                R$ <?= number_format($row_totais['receitas_total'] - $row_totais['despesas_total'], 2, ',', '.') ?>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    
                    <!-- ✅ BARRA DE FILTROS DA ABA LANÇAMENTOS -->
                    <div class="filtros-lancamentos">
                        <?php if (!empty($filtro_conta) || !empty($filtro_categoria)): ?>
                            <div style="grid-column: 1 / -1; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); padding: 12px 16px; border-radius: 8px; border-left: 4px solid #1976d2; display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                <i class="fas fa-filter" style="color: #1976d2;"></i>
                                <span style="color: #0d47a1; font-weight: 600; font-size: 13px;">
                                    Filtros ativos: 
                                    <?php if (!empty($filtro_conta)): 
                                        $conta_selecionada = array_filter($contas_financeiras, fn($c) => $c['id'] == $filtro_conta);
                                        if ($conta_selecionada) {
                                            $conta_selecionada = reset($conta_selecionada);
                                            echo htmlspecialchars($conta_selecionada['nome_conta']);
                                        }
                                    endif; ?>
                                    <?php if (!empty($filtro_conta) && !empty($filtro_categoria)) echo ' | '; ?>
                                    <?php if (!empty($filtro_categoria)): echo $filtro_categoria; endif; ?>
                                </span>
                                <button onclick="limparFiltros()" style="margin-left: auto; padding: 6px 12px; background: #1976d2; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 5px;">
                                    <i class="fas fa-times"></i> Limpar filtros
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="filtro-grupo">
                            <label>Todas as contas</label>
                            <select id="filtro_conta">
                                <option value="">Todas as contas</option>
                                <?php foreach ($contas_financeiras as $conta): ?>
                                    <option value="<?= $conta['id'] ?>" <?= ($filtro_conta == $conta['id']) ? 'selected' : '' ?>>
                                        <?php if ($conta['categoria'] == 'Caixa'): ?>
                                            💰 <?= htmlspecialchars($conta['nome_conta']) ?>
                                        <?php else: ?>
                                            🏦 <?= htmlspecialchars($conta['nome_conta']) ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filtro-grupo">
                            <label>Todas as categorias</label>
                            <select id="filtro_categoria">
                                <option value="">Todas as categorias</option>
                                <option value="VENDAS" <?= ($filtro_categoria == 'VENDAS') ? 'selected' : '' ?>>Vendas</option>
                                <option value="SERVICOS" <?= ($filtro_categoria == 'SERVICOS') ? 'selected' : '' ?>>Serviços</option>
                                <option value="COMPRAS" <?= ($filtro_categoria == 'COMPRAS') ? 'selected' : '' ?>>Compras</option>
                                <option value="OUTROS" <?= ($filtro_categoria == 'OUTROS') ? 'selected' : '' ?>>Outros</option>
                            </select>
                        </div>
                        
                        <div class="filtros-acoes">
                            <button class="btn-filtro primary" onclick="aplicarFiltros()">
                                <i class="fas fa-search"></i> Buscar
                            </button>
                            <button class="btn-filtro secondary" onclick="limparFiltros()">
                                <i class="fas fa-undo"></i>
                            </button>
                            <button class="btn-filtro success">
                                <i class="fas fa-plus"></i> Adicionar
                            </button>
                            <button class="btn-filtro info">
                                <i class="fas fa-exchange-alt"></i> Transferência
                            </button>
                            <button class="btn-filtro secondary">
                                <i class="fas fa-print"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- DATA SELECTOR -->
                    <div style="text-align: center; margin-bottom: 20px;">
                        <div style="display: inline-flex; align-items: center; gap: 15px; background: #f8f9fa; padding: 10px 20px; border-radius: 10px;">
                            <button style="border: none; background: transparent; color: #1e40af; cursor: pointer; font-size: 18px;">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <span style="font-size: 14px; font-weight: 700; color: #495057; font-family: 'Exo', sans-serif;">
                                <i class="fas fa-calendar"></i> 14/01/2026
                            </span>
                            <button style="border: none; background: transparent; color: #1e40af; cursor: pointer; font-size: 18px;">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- ✅ LISTA DE LANÇAMENTOS COM VALOR BRUTO E LÍQUIDO -->
                    <div class="report-card">
                        <div class="report-body report-body-lancamentos">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th width="6%">ID</th>
                                        <th width="9%">Data Venc.</th>
                                        <th width="10%">Compensação</th>
                                        <th width="22%">Descrição</th>
                                        <th width="15%">Forma de Pagamento</th>
                                        <th width="9%" class="text-center">Parcela</th>
                                        <th width="12%" class="text-right">Valor da Venda</th>
                                        <th width="14%" class="text-right">Valor Recebido</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lista_lancamentos as $l): 
                                        $is_parcelado = ($l['total_parcelas'] > 1);
                                        $info_taxa = extrairInfoTaxa($l['descricao']);
                                        $tem_taxa = $info_taxa['tem_taxa'];
                                    ?>
                                    <tr class="<?= $is_parcelado ? 'highlight-parcelado' : '' ?>">
                                        <td style="color: #1e40af; font-weight: 700; white-space: nowrap;">Caixa <?= $l['id'] ?></td>
                                        <td><?= date('d/m/Y', strtotime($l['data_vencimento'])) ?></td>
                                        <td>
                                            <?php if (!empty($l['data_compensacao'])): ?>
                                                <span class="data-compensacao">
                                                    <i class="fas fa-calendar-check"></i>
                                                    <?= date('d/m/Y', strtotime($l['data_compensacao'])) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #999;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($l['tipo'] == 'ENTRADA' && $l['categoria'] == 'VENDAS' && !empty($l['id_venda'])): ?>
                                                <strong>Venda PDV #<?= $l['id_venda'] ?> - <?= htmlspecialchars($l['fornecedor_cliente']) ?></strong>
                                                <?php if (!empty($l['id_caixa_referencia'])): ?>
                                                    <span class="small-detail"><i class="fas fa-cash-register"></i> Caixa <?= $l['id_caixa_referencia'] ?></span>
                                                <?php endif; ?>
                                            <?php elseif (strpos($l['documento'], 'FECHAMENTO') !== false || strpos($l['documento'], 'ENCERRAMENTO') !== false || strpos($l['documento'], 'SUPRIMENTO') !== false): ?>
                                                <strong style="color: #6610f2;"><i class="fas fa-file-invoice-dollar"></i> <?= htmlspecialchars($l['descricao']) ?></strong>
                                                <?php if (!empty($l['id_caixa_referencia'])): ?>
                                                    <span class="small-detail"><i class="fas fa-cash-register"></i> Ref. Caixa #<?= $l['id_caixa_referencia'] ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <strong><?= htmlspecialchars($l['descricao']) ?></strong>
                                                <?php if (!empty($l['fornecedor_cliente'])): ?>
                                                    <span class="small-detail"><?= htmlspecialchars($l['fornecedor_cliente']) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($l['id_caixa_referencia'])): ?>
                                                    <span class="small-detail"><i class="fas fa-cash-register"></i> Caixa <?= $l['id_caixa_referencia'] ?></span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $forma_tratada = tratarFormaPagamento($l['forma_pagamento']);
                                            if ($forma_tratada != 'Não especificada'): 
                                            ?>
                                                <i class="fas fa-credit-card" style="color: #1e40af;"></i>
                                                <?= htmlspecialchars($forma_tratada) ?>
                                                <?php if ($tem_taxa): ?>
                                                    <span class="taxa-info">
                                                        <i class="fas fa-percentage"></i>
                                                        <?= $info_taxa['taxa_percent'] ?>%
                                                    </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: #6c757d;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($is_parcelado): ?>
                                                <span class="badge-parcela">
                                                    <?= $l['parcela_atual'] ?>/<?= $l['total_parcelas'] ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge-avista">
                                                    <i class="fas fa-check"></i> À vista
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <!-- ✅ COLUNA: VALOR DA VENDA (BRUTO) -->
                                        <td class="valor-bruto-cell">
                                            <?php if ($tem_taxa && $info_taxa['valor_bruto'] > 0): ?>
                                                <div class="valor-box">
                                                    <span style="font-weight: 600; color: #495057;">
                                                        R$ <?= number_format($info_taxa['valor_bruto'], 2, ',', '.') ?>
                                                    </span>
                                                </div>
                                            <?php elseif (!empty($l['venda_valor_bruto']) && $l['tipo'] == 'ENTRADA'): ?>
                                                <div class="valor-box">
                                                    <span style="font-weight: 600; color: #495057;">
                                                        R$ <?= number_format($l['venda_valor_bruto'], 2, ',', '.') ?>
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <span class="sem-taxa">-</span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <!-- ✅ COLUNA: VALOR RECEBIDO (LÍQUIDO) -->
                                        <td class="valor-liquido-cell">
                                            <div class="valor-box">
                                                <span class="<?= $l['tipo'] == 'SAIDA' ? 'val-despesa' : 'com-taxa' ?>">
                                                    <?= $l['tipo'] == 'SAIDA' ? '- ' : '+ ' ?>R$ <?= number_format($l['valor'], 2, ',', '.') ?>
                                                </span>
                                                <?php if ($tem_taxa && $info_taxa['taxa_valor'] > 0): ?>
                                                    <span class="valor-secundario">
                                                        <i class="fas fa-minus-circle" style="color: #dc3545;"></i>
                                                        Taxa: R$ <?= number_format($info_taxa['taxa_valor'], 2, ',', '.') ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div class="table-footer">
                                <i class="fas fa-info-circle"></i>
                                Mostrando <?= count($lista_lancamentos) ?> lançamento(s)
                                <?php if (!empty($filtro_conta) || !empty($filtro_categoria)): ?>
                                    <span style="color: #1976d2; font-weight: 600;"> (filtrado)</span>
                                <?php else: ?>
                                    dos últimos 200
                                <?php endif; ?>
                                <?php if ($stats_parcelamento['total_vendas_parceladas'] > 0): ?>
                                    | <i class="fas fa-layer-group"></i> <?= $stats_parcelamento['total_vendas_parceladas'] ?> vendas parceladas
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // ✅ APLICAR FILTRO AUTOMATICAMENTE AO MUDAR SELEÇÃO
        document.addEventListener('DOMContentLoaded', function() {
            const filtroConta = document.getElementById('filtro_conta');
            const filtroCategoria = document.getElementById('filtro_categoria');
            
            if (filtroConta) {
                filtroConta.addEventListener('change', aplicarFiltros);
            }
            
            if (filtroCategoria) {
                filtroCategoria.addEventListener('change', aplicarFiltros);
            }
        });
        
        function aplicarFiltros() {
            const conta = document.getElementById('filtro_conta').value;
            const categoria = document.getElementById('filtro_categoria').value;
            
            // Recarregar página com parâmetros de filtro
            const params = new URLSearchParams();
            params.append('tab', 'lancamentos');
            if (conta) params.append('conta', conta);
            if (categoria) params.append('categoria', categoria);
            
            window.location.href = '?' + params.toString();
        }
        
        function limparFiltros() {
            // Redirecionar para aba lançamentos sem filtros
            window.location.href = '?tab=lancamentos';
        }
    </script>

</body>
</html>