<?php
/**
 * =========================================================================================
 * ZIIPVET - PRODUTIVIDADE E RELATÓRIOS DE VENDAS
 * ARQUIVO: produtividade.php
 * VERSÃO: 4.0.0 - PADRÃO MODERNO
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
$titulo_pagina = "Produtividade";

$vendedor_filtro = $_GET['vendedor'] ?? '';
$mes_ano = $_GET['mes_ano'] ?? date('Y-m');

list($ano, $mes) = explode('-', $mes_ano);
$data_inicio = "$ano-$mes-01";
$data_fim = date("Y-m-t", strtotime($data_inicio));

try {
    // 1. BUSCAR USUÁRIOS DA TABELA USUARIOS
    $sql_usuarios = "SELECT id, nome FROM usuarios WHERE id_admin = ? AND ativo = 1 ORDER BY nome ASC";
    $stmt_usuarios = $pdo->prepare($sql_usuarios);
    $stmt_usuarios->execute([$id_admin]);
    $usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);

    $params = [':id_admin' => $id_admin, ':inicio' => $data_inicio, ':fim' => $data_fim];
    $where_vendedor = "";
    
    if ($vendedor_filtro) {
        $stmt_nome = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ?");
        $stmt_nome->execute([$vendedor_filtro]);
        $nome_vendedor = $stmt_nome->fetchColumn();
        
        if ($nome_vendedor) {
            $where_vendedor = " AND v.usuario_vendedor = :vend";
            $params[':vend'] = $nome_vendedor;
        }
    }

    // 2. Dados para gráfico diário (agrupado por usuario_vendedor)
    $sql_grafico = "SELECT v.data_venda, v.usuario_vendedor, SUM(v.valor_total - v.desconto) as total_liquido 
                    FROM vendas v
                    WHERE v.id_admin = :id_admin 
                    AND v.data_venda BETWEEN :inicio AND :fim 
                    AND v.usuario_vendedor IS NOT NULL
                    $where_vendedor
                    GROUP BY v.data_venda, v.usuario_vendedor 
                    ORDER BY v.data_venda ASC";
    
    $stmt_graf = $pdo->prepare($sql_grafico);
    $stmt_graf->execute($params);
    $dados_evolucao = $stmt_graf->fetchAll(PDO::FETCH_ASSOC);

    // 3. Produção por colaborador
    $sql_producao = "SELECT 
                        u.nome as usuario_vendedor,
                        COUNT(DISTINCT v.id_cliente) as clientes,
                        COUNT(v.id) as vendas,
                        COALESCE(SUM(v.valor_total), 0) as bruto,
                        COALESCE(SUM(v.desconto), 0) as desconto,
                        COALESCE(SUM(v.valor_total - v.desconto), 0) as liquido,
                        COALESCE(AVG(v.valor_total - v.desconto), 0) as ticket_medio
                    FROM usuarios u
                    LEFT JOIN vendas v ON v.usuario_vendedor COLLATE utf8mb4_unicode_ci = u.nome COLLATE utf8mb4_unicode_ci
                        AND v.id_admin = :id_admin 
                        AND v.data_venda BETWEEN :inicio AND :fim
                    WHERE u.id_admin = :id_admin 
                    AND u.ativo = 1
                    " . ($vendedor_filtro ? "AND u.id = :vend_id" : "") . "
                    GROUP BY u.id, u.nome 
                    ORDER BY liquido DESC";
    
    $params_producao = $params;
    if ($vendedor_filtro) {
        $params_producao[':vend_id'] = $vendedor_filtro;
        unset($params_producao[':vend']);
    }
    
    $stmt_prod = $pdo->prepare($sql_producao);
    $stmt_prod->execute($params_producao);
    $producao_colaboradores = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);

    // 4. Vendas por dia da semana
    $sql_dias_semana = "SELECT 
                            u.nome as usuario_vendedor,
                            DAYOFWEEK(v.data_venda) as dia_semana,
                            COALESCE(SUM(v.valor_total - v.desconto), 0) as valor
                        FROM usuarios u
                        LEFT JOIN vendas v ON v.usuario_vendedor COLLATE utf8mb4_unicode_ci = u.nome COLLATE utf8mb4_unicode_ci
                            AND v.id_admin = :id_admin 
                            AND v.data_venda BETWEEN :inicio AND :fim
                        WHERE u.id_admin = :id_admin 
                        AND u.ativo = 1
                        " . ($vendedor_filtro ? "AND u.id = :vend_id" : "") . "
                        GROUP BY u.nome, DAYOFWEEK(v.data_venda)";
    
    $stmt_dias = $pdo->prepare($sql_dias_semana);
    $stmt_dias->execute($params_producao);
    $dados_dias_semana = $stmt_dias->fetchAll(PDO::FETCH_ASSOC);

    // 5. Lista de vendas
    $sql_lista = "SELECT v.id, v.data_venda, v.valor_total, v.desconto, v.tipo_venda, v.status_pagamento, v.usuario_vendedor, c.nome as nome_cliente 
                 FROM vendas v 
                 LEFT JOIN clientes c ON v.id_cliente = c.id 
                 WHERE v.id_admin = :id_admin 
                 AND v.data_venda BETWEEN :inicio AND :fim 
                 $where_vendedor
                 ORDER BY v.data_venda DESC, v.id DESC";
    
    $stmt_lista = $pdo->prepare($sql_lista);
    $stmt_lista->execute($params);
    $lista_vendas = $stmt_lista->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}

// Organizar dados por vendedor e data
$vendedores_unicos = array_values(array_unique(array_filter(array_column($dados_evolucao, 'usuario_vendedor'))));
$datas_unicas = array_unique(array_column($dados_evolucao, 'data_venda'));
sort($datas_unicas);

// Organizar dias da semana por vendedor
$vendas_por_vendedor_dia = [];
foreach ($dados_dias_semana as $row) {
    $vendedor = $row['usuario_vendedor'];
    $dia = $row['dia_semana'] ? ($row['dia_semana'] - 1) : 0;
    if (!isset($vendas_por_vendedor_dia[$vendedor])) {
        $vendas_por_vendedor_dia[$vendedor] = array_fill(0, 7, 0);
    }
    $vendas_por_vendedor_dia[$vendedor][$dia] = $row['valor'];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    
    <!-- CSS CENTRALIZADO -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/menu.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/formularios.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* ========================================
           ESTILOS ESPECÍFICOS DA PRODUTIVIDADE
        ======================================== */
        
        /* Filtros */
        .filters-card {
            background: #fff;
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 25px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }
        
        .filters-row {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 15px;
            align-items: flex-end;
        }
        
        .filter-col label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #6c757d;
            margin-bottom: 8px;
            text-transform: uppercase;
            font-family: 'Exo', sans-serif;
        }
        
        .filter-col label i {
            margin-right: 5px;
        }
        
        .btn-filtrar {
            height: 45px;
            padding: 0 24px;
            background: #131c71;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            font-family: 'Exo', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-filtrar:hover {
            background: #4a1d75;
            transform: translateY(-2px);
        }
        
        /* Sistema de Tabs */
        .tabs-container {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }
        
        .tabs-header {
            display: flex;
            list-style: none;
            border-bottom: 2px solid #e0e0e0;
            padding: 0;
            margin: 0;
            background: #f8f9fa;
        }
        
        .tabs-header li {
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.3s;
        }
        
        .tabs-header li.active {
            border-bottom-color: #131c71;
            background: #fff;
        }
        
        .tabs-header li a {
            padding: 18px 28px;
            display: block;
            color: #6c757d;
            text-decoration: none;
            font-weight: 700;
            font-size: 15px;
            font-family: 'Exo', sans-serif;
            transition: all 0.3s;
        }
        
        .tabs-header li.active a {
            color: #131c71;
        }
        
        .tabs-content {
            padding: 0;
        }
        
        .tab-pane {
            display: none;
            padding: 25px;
        }
        
        .tab-pane.active {
            display: block;
        }
        
        /* Cards dentro das tabs */
        .report-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            margin-bottom: 25px;
            overflow: hidden;
        }
        
        .report-header {
            background: linear-gradient(135deg, #b92426 0%, #b92426 100%);
            color: #fff;
            padding: 18px 20px;
            font-weight: 700;
            font-size: 16px;
            font-family: 'Exo', sans-serif;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .report-body {
            padding: 20px;
        }
        
        /* Controles do Gráfico */
        .chart-controls {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .chart-controls select {
            padding: 10px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            color: #131c71;
            background: #fff;
            font-family: 'Exo', sans-serif;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .chart-controls select:focus {
            border-color: #131c71;
            outline: none;
        }
        
        .btn-group-chart {
            display: flex;
            gap: 0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        
        .btn-group-chart button {
            padding: 10px 20px;
            border: none;
            background: #f0f0f0;
            color: #6c757d;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Exo', sans-serif;
            transition: all 0.3s;
        }
        
        .btn-group-chart button.active {
            background: #131c71;
            color: #fff;
        }
        
        .btn-group-chart button:hover:not(.active) {
            background: #e0e0e0;
        }
        
        /* Container do Gráfico */
        .chart-wrapper {
            position: relative;
            height: 400px;
            width: 100%;
        }
        
        /* Tabelas */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Exo', sans-serif;
        }
        
        .data-table thead {
            background: #f8f9fa;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 16px;
            color: #495057;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .data-table tbody td {
            padding: 16px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 15px;
            color: #2c3e50;
        }
        
        .data-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .text-end {
            text-align: right !important;
        }
        
        /* Linha de Total */
        .total-row {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-weight: 700;
        }
        
        .total-row td {
            border-top: 2px solid #dee2e6 !important;
            border-bottom: none !important;
        }
        
        /* Destaque de Melhor Valor */
        .best-value {
            font-weight: 700;
            color: #28a745;
        }
        
        /* Percentual na célula */
        .cell-percent {
            font-size: 11px;
            color: #6c757d;
            display: block;
            margin-top: 3px;
        }
        
        /* Badges de Status */
        .badge-status {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            font-family: 'Exo', sans-serif;
        }
        
        .badge-pago {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            color: #2e7d32;
        }
        
        .badge-pendente {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            color: #e65100;
        }
        
        .badge-cancelado {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            color: #c62828;
        }
        
        /* Responsividade */
        @media (max-width: 1200px) {
            .filters-row {
                grid-template-columns: 1fr 1fr;
            }
            
            .btn-filtrar {
                grid-column: 1 / -1;
            }
        }
        
        @media (max-width: 768px) {
            .filters-row {
                grid-template-columns: 1fr;
            }
            
            .tabs-header {
                flex-direction: column;
            }
            
            .tabs-header li {
                width: 100%;
                border-bottom: 1px solid #e0e0e0;
            }
            
            .tabs-header li.active {
                border-left: 3px solid #131c71;
                border-bottom: 1px solid #e0e0e0;
            }
            
            .chart-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .chart-controls select,
            .btn-group-chart {
                width: 100%;
            }
            
            .data-table {
                font-size: 13px;
            }
            
            .data-table thead th,
            .data-table tbody td {
                padding: 10px 8px;
            }
        }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include 'menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include 'menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <!-- HEADER: Título -->
        <div class="form-header">
            <h1 class="form-title">
                <i class="fas fa-chart-line"></i>
                Produtividade
            </h1>
        </div>

        <!-- FILTROS -->
        <div class="filters-card">
            <form method="GET">
                <div class="filters-row">
                    <div class="filter-col">
                        <label>
                            <i class="fas fa-user"></i>
                            Funcionário
                        </label>
                        <select name="vendedor" class="form-control">
                            <option value="">Todos os funcionários</option>
                            <?php foreach($usuarios as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= $vendedor_filtro == $u['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-col">
                        <label>
                            <i class="fas fa-calendar"></i>
                            Período
                        </label>
                        <input type="month" 
                               name="mes_ano" 
                               class="form-control" 
                               value="<?= $mes_ano ?>">
                    </div>
                    
                    <button type="submit" class="btn-filtrar">
                        <i class="fas fa-filter"></i>
                        Filtrar
                    </button>
                </div>
            </form>
        </div>

        <!-- TABS -->
        <div class="tabs-container">
            <ul class="tabs-header">
                <li class="active">
                    <a href="javascript:void(0)" onclick="openTab(event, 'tab-resumo')">
                        <i class="fas fa-chart-bar"></i> Resumo
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0)" onclick="openTab(event, 'tab-lista')">
                        <i class="fas fa-list"></i> Lista de Vendas
                    </a>
                </li>
            </ul>

            <div class="tabs-content">
                <!-- TAB: RESUMO -->
                <div id="tab-resumo" class="tab-pane active">
                    
                    <!-- Gráfico de Ranking -->
                    <div class="report-card">
                        <div class="report-header">
                            <i class="fas fa-chart-area"></i>
                            Evolução de Vendas por Funcionário
                        </div>
                        <div class="report-body">
                            <div class="chart-controls">
                                <select id="metricaSelect">
                                    <option value="liquido">Venda Líquida</option>
                                    <option value="bruto">Venda Bruta</option>
                                    <option value="desconto">Desconto</option>
                                </select>
                                <div style="flex: 1;"></div>
                                <div class="btn-group-chart">
                                    <button type="button" class="active" onclick="toggleTodos(this)">
                                        Todos
                                    </button>
                                    <button type="button" onclick="toggleTop3(this)">
                                        Top 3
                                    </button>
                                </div>
                            </div>
                            <div class="chart-wrapper">
                                <canvas id="chartRanking"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Tabela de Produção -->
                    <div class="report-card">
                        <div class="report-header">
                            <i class="fas fa-users"></i>
                            Produção por Colaborador
                        </div>
                        <div class="report-body" style="padding: 0;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th class="text-end">Clientes</th>
                                        <th class="text-end">Vendas</th>
                                        <th class="text-end">Bruto</th>
                                        <th class="text-end">Desconto</th>
                                        <th class="text-end">Líquido</th>
                                        <th class="text-end">Ticket Médio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_clientes = 0;
                                    $total_vendas = 0;
                                    $total_bruto = 0;
                                    $total_desconto = 0;
                                    $total_liquido = 0;
                                    
                                    foreach($producao_colaboradores as $colab): 
                                        $total_clientes += $colab['clientes'];
                                        $total_vendas += $colab['vendas'];
                                        $total_bruto += $colab['bruto'];
                                        $total_desconto += $colab['desconto'];
                                        $total_liquido += $colab['liquido'];
                                    ?>
                                    <tr>
                                        <td style="font-weight: 600; color: #2c3e50;">
                                            <?= htmlspecialchars($colab['usuario_vendedor']) ?>
                                        </td>
                                        <td class="text-end"><?= $colab['clientes'] ?></td>
                                        <td class="text-end"><?= $colab['vendas'] ?></td>
                                        <td class="text-end">
                                            R$ <?= number_format($colab['bruto'], 2, ',', '.') ?>
                                        </td>
                                        <td class="text-end" style="color: #dc3545;">
                                            R$ <?= number_format($colab['desconto'], 2, ',', '.') ?>
                                        </td>
                                        <td class="text-end" style="font-weight: 700; color: #28a745; font-size: 16px;">
                                            R$ <?= number_format($colab['liquido'], 2, ',', '.') ?>
                                        </td>
                                        <td class="text-end">
                                            R$ <?= number_format($colab['ticket_medio'], 2, ',', '.') ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <tr class="total-row">
                                        <td><strong>TOTAL</strong></td>
                                        <td class="text-end"><?= $total_clientes ?></td>
                                        <td class="text-end"><?= $total_vendas ?></td>
                                        <td class="text-end">
                                            R$ <?= number_format($total_bruto, 2, ',', '.') ?>
                                        </td>
                                        <td class="text-end">
                                            R$ <?= number_format($total_desconto, 2, ',', '.') ?>
                                        </td>
                                        <td class="text-end">
                                            R$ <?= number_format($total_liquido, 2, ',', '.') ?>
                                        </td>
                                        <td class="text-end">
                                            R$ <?= $total_vendas > 0 ? number_format($total_liquido / $total_vendas, 2, ',', '.') : '0,00' ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tabela Dias da Semana -->
                    <div class="report-card">
                        <div class="report-header">
                            <i class="fas fa-calendar-week"></i>
                            Vendas por Dia da Semana
                        </div>
                        <div class="report-body" style="padding: 0;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Colaborador</th>
                                        <th class="text-end">Segunda</th>
                                        <th class="text-end">Terça</th>
                                        <th class="text-end">Quarta</th>
                                        <th class="text-end">Quinta</th>
                                        <th class="text-end">Sexta</th>
                                        <th class="text-end">Sábado</th>
                                        <th class="text-end">Domingo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $totais_dias = array_fill(0, 7, 0);
                                    foreach($vendas_por_vendedor_dia as $vendedor => $dias): 
                                        $total_vendedor = array_sum($dias);
                                        $melhor_dia_valor = max($dias);
                                        $melhor_dia_index = array_search($melhor_dia_valor, $dias);
                                        
                                        for($i = 0; $i < 7; $i++) {
                                            $totais_dias[$i] += $dias[$i];
                                        }
                                    ?>
                                    <tr>
                                        <td style="font-weight: 600; color: #2c3e50;">
                                            <?= htmlspecialchars($vendedor) ?>
                                        </td>
                                        <?php for($i = 1; $i <= 7; $i++): 
                                            $dia_index = $i % 7;
                                            $valor = $dias[$dia_index];
                                            $is_melhor = ($dia_index == $melhor_dia_index && $melhor_dia_valor > 0);
                                            $percentual = $total_vendedor > 0 ? ($valor / $total_vendedor) * 100 : 0;
                                        ?>
                                        <td class="text-end <?= $is_melhor ? 'best-value' : '' ?>">
                                            R$ <?= number_format($valor, 2, ',', '.') ?>
                                            <?php if($is_melhor && $percentual > 0): ?>
                                                <span class="cell-percent"><?= round($percentual) ?>%</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php endfor; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <tr class="total-row">
                                        <td><strong>TOTAL</strong></td>
                                        <?php for($i = 1; $i <= 7; $i++): 
                                            $dia_index = $i % 7;
                                        ?>
                                        <td class="text-end">
                                            R$ <?= number_format($totais_dias[$dia_index], 2, ',', '.') ?>
                                        </td>
                                        <?php endfor; ?>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                </div>

                <!-- TAB: LISTA DE VENDAS -->
                <div id="tab-lista" class="tab-pane">
                    <div class="report-card">
                        <div class="report-body" style="padding: 0;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th width="80">ID</th>
                                        <th width="120">Data</th>
                                        <th>Vendedor</th>
                                        <th>Cliente</th>
                                        <th class="text-end">Valor Total</th>
                                        <th class="text-end">Desconto</th>
                                        <th class="text-end">Valor Líquido</th>
                                        <th width="120">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($lista_vendas) > 0): ?>
                                        <?php foreach($lista_vendas as $venda): 
                                            $valor_liquido = $venda['valor_total'] - $venda['desconto'];
                                            
                                            if($venda['status_pagamento'] == 'PAGO') {
                                                $badge_class = 'badge-pago';
                                            } elseif($venda['status_pagamento'] == 'PENDENTE') {
                                                $badge_class = 'badge-pendente';
                                            } else {
                                                $badge_class = 'badge-cancelado';
                                            }
                                        ?>
                                        <tr>
                                            <td>
                                                <strong style="color: #131c71;">#<?= $venda['id'] ?></strong>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($venda['data_venda'])) ?></td>
                                            <td style="font-weight: 600;">
                                                <?= htmlspecialchars($venda['usuario_vendedor'] ?? 'Sistema') ?>
                                            </td>
                                            <td><?= htmlspecialchars($venda['nome_cliente'] ?? 'Consumidor Final') ?></td>
                                            <td class="text-end">
                                                R$ <?= number_format($venda['valor_total'], 2, ',', '.') ?>
                                            </td>
                                            <td class="text-end" style="color: #dc3545;">
                                                R$ <?= number_format($venda['desconto'], 2, ',', '.') ?>
                                            </td>
                                            <td class="text-end" style="font-weight: 700; color: #28a745; font-size: 16px;">
                                                R$ <?= number_format($valor_liquido, 2, ',', '.') ?>
                                            </td>
                                            <td>
                                                <span class="badge-status <?= $badge_class ?>">
                                                    <?= $venda['status_pagamento'] ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" style="text-align: center; padding: 60px 20px; color: #adb5bd;">
                                                <i class="fas fa-inbox" style="font-size: 48px; display: block; margin-bottom: 15px; opacity: 0.3;"></i>
                                                Nenhuma venda encontrada no período selecionado.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // ==========================================================
        // SISTEMA DE TABS
        // ==========================================================
        function openTab(evt, tabName) {
            document.querySelectorAll(".tab-pane").forEach(p => p.classList.remove("active"));
            document.querySelectorAll(".tabs-header li").forEach(l => l.classList.remove("active"));
            
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.parentElement.classList.add("active");
        }

        // ==========================================================
        // DADOS PARA O GRÁFICO
        // ==========================================================
        const vendedores = <?= json_encode($vendedores_unicos) ?>;
        const datas = <?= json_encode($datas_unicas) ?>;
        const dadosEvolucao = <?= json_encode($dados_evolucao) ?>;

        const cores = [
            'rgba(98, 37, 153, 0.8)',    // Roxo
            'rgba(40, 167, 69, 0.8)',    // Verde
            'rgba(220, 53, 69, 0.8)',    // Vermelho
            'rgba(255, 193, 7, 0.8)',    // Amarelo
            'rgba(23, 162, 184, 0.8)',   // Ciano
            'rgba(255, 99, 132, 0.8)',   // Rosa
            'rgba(54, 162, 235, 0.8)',   // Azul
            'rgba(255, 159, 64, 0.8)'    // Laranja
        ];

        const datasets = vendedores.map((vendedor, index) => {
            const valores = datas.map(data => {
                const registro = dadosEvolucao.find(d => d.usuario_vendedor === vendedor && d.data_venda === data);
                return registro ? parseFloat(registro.total_liquido) : 0;
            });

            return {
                label: vendedor,
                data: valores,
                borderColor: cores[index % cores.length],
                backgroundColor: cores[index % cores.length].replace('0.8', '0.2'),
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointRadius: 5,
                pointHoverRadius: 7,
                pointBackgroundColor: '#fff',
                pointBorderWidth: 2
            };
        });

        // ==========================================================
        // CRIAR GRÁFICO
        // ==========================================================
        const ctx = document.getElementById('chartRanking').getContext('2d');
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: datas.map(d => {
                    const date = new Date(d + 'T00:00:00');
                    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
                }),
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: { size: 13, weight: '600', family: 'Exo' }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 12,
                        cornerRadius: 8,
                        titleFont: { size: 14, weight: '700', family: 'Exo' },
                        bodyFont: { size: 13, family: 'Exo' },
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': R$ ' + context.parsed.y.toFixed(2).replace('.', ',');
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f0f0f0' },
                        ticks: {
                            callback: function(value) {
                                return 'R$ ' + value.toFixed(2).replace('.', ',');
                            },
                            font: { size: 12, family: 'Exo' }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45,
                            font: { size: 12, family: 'Exo' }
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });

        // ==========================================================
        // CONTROLES DO GRÁFICO
        // ==========================================================
        function toggleTodos(btn) {
            document.querySelectorAll('.btn-group-chart button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            chart.data.datasets = datasets;
            chart.update();
        }

        function toggleTop3(btn) {
            document.querySelectorAll('.btn-group-chart button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            chart.data.datasets = datasets.slice(0, 3);
            chart.update();
        }
    </script>
</body>
</html>