<?php
/**
 * =========================================================================================
 * ZIIPVET - LISTAGEM DE CONTAS A PAGAR/RECEBER
 * ARQUIVO: listar_contas.php
 * VERSÃO: 4.0.0 - PADRÃO MODERNO
 * =========================================================================================
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php'; 
require_once 'config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;

// ==========================================================
// CONFIGURAÇÃO DA PÁGINA
// ==========================================================
$titulo_pagina = "Contas a Pagar/Receber";

// ==========================================================
// LÓGICA DO FILTRO DE DATAS E NAVEGAÇÃO
// ==========================================================
$filtro = $_GET['filtro'] ?? 'ultimos_365';
$ref_date = $_GET['ref'] ?? date('Y-m-d'); 
$hoje = date('Y-m-d');

$data_inicio = '';
$data_fim = '';

switch ($filtro) {
    case 'hoje':
        $data_inicio = $data_fim = $hoje;
        break;
    case 'ontem':
        $data_inicio = $data_fim = date('Y-m-d', strtotime('-1 day'));
        break;
    case 'esta_semana':
        $timestamp = strtotime($ref_date);
        $day_of_week = date('w', $timestamp);
        $offset_inicio = ($day_of_week == 0) ? -6 : 1 - $day_of_week; 
        $data_inicio = date('Y-m-d', strtotime("$offset_inicio days", $timestamp));
        $data_fim = date('Y-m-d', strtotime("+6 days", strtotime($data_inicio)));
        break;
    case 'este_mes':
        $data_inicio = date('Y-m-01', strtotime($ref_date));
        $data_fim = date('Y-m-t', strtotime($ref_date));
        break;
    case 'ultimos_365':
        $data_inicio = date('Y-m-d', strtotime('-365 days'));
        $data_fim = date('Y-m-d', strtotime('+90 days'));
        break;
    case 'todas':
        $data_inicio = '1970-01-01';
        $data_fim = '2099-12-31';
        break;
    case 'personalizado':
        $data_inicio = $_GET['inicio'] ?? $hoje;
        $data_fim = $_GET['fim'] ?? $hoje;
        break;
}

try {
    // QUERY CORRIGIDA
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
              WHERE c.id_admin = ? AND c.vencimento BETWEEN ? AND ?
              ORDER BY c.vencimento DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$id_admin, $data_inicio, $data_fim]);
    $contas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $t_nao_pagos = 0; 
    $t_pagos = 0; 
    $t_vencidos = 0; 
    $t_a_vencer = 0; 
    $t_todos_bruto = 0;

    foreach ($contas as $c) {
        $v_parcela = (float)$c['valor_parcela'];
        $t_todos_bruto += $v_parcela;

        if ($c['status_baixa'] === 'PAGO') {
            $t_pagos += $v_parcela;
        } else {
            $t_nao_pagos += $v_parcela;
            if ($c['vencimento'] < $hoje) {
                $t_vencidos += $v_parcela;
            } else {
                $t_a_vencer += $v_parcela;
            }
        }
    }
    
    // Buscar contas financeiras
    $stmt_contas = $pdo->prepare("SELECT id, nome_conta, saldo_inicial 
                                  FROM contas_financeiras 
                                  WHERE id_admin = ? 
                                  AND categoria = 'Conta Bancária'
                                  AND status = 'Ativo'
                                  ORDER BY nome_conta");
    $stmt_contas->execute([$id_admin]);
    $contas_fin = $stmt_contas->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Erro ao listar contas: " . $e->getMessage());
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        /* ========================================
           ESTILOS ESPECÍFICOS DAS CONTAS
        ======================================== */
        
        /* KPI Cards */
        .kpi-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .kpi-card {
            background: #fff;
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        
        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }
        
        .kpi-card.active-filter {
            border-bottom: 5px solid #2c3e50;
            background: #f8f9fa;
        }
        
        .kpi-card.total {
            border-left-color: #6c757d;
        }
        
        .kpi-card.pendente {
            border-left-color: #f39c12;
        }
        
        .kpi-card.vencido {
            border-left-color: #dc3545;
        }
        
        .kpi-card.pago {
            border-left-color: #28a745;
        }
        
        .kpi-label {
            font-size: 13px;
            color: #6c757d;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 8px;
            font-family: 'Exo', sans-serif;
        }
        
        .kpi-value {
            font-size: 24px;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
            display: block;
        }
        
        .kpi-card.total .kpi-value {
            color: #6c757d;
        }
        
        .kpi-card.pendente .kpi-value {
            color: #f39c12;
        }
        
        .kpi-card.vencido .kpi-value {
            color: #dc3545;
        }
        
        .kpi-card.pago .kpi-value {
            color: #28a745;
        }
        
        /* Top Action Bar */
        .top-bar {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .date-selector {
            display: flex;
            align-items: center;
            background: #fff;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 10px 20px;
            gap: 15px;
            height: 50px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .date-nav-btn {
            border: none;
            background: none;
            cursor: pointer;
            font-size: 18px;
            color: #6c757d;
            transition: all 0.2s ease;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }
        
        .date-nav-btn:hover {
            background: #f8f9fa;
            color: #131c71;
        }
        
        .date-nav-btn.hidden {
            visibility: hidden;
        }
        
        .date-label {
            font-size: 16px;
            font-weight: 700;
            color: #131c71;
            font-family: 'Exo', sans-serif;
            min-width: 200px;
            text-align: center;
        }
        
        .date-select {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            border: none;
            background: transparent;
            cursor: pointer;
            font-family: 'Exo', sans-serif;
            outline: none;
        }
        
        .top-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        .btn-action-top {
            padding: 12px 20px;
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
            text-decoration: none;
        }
        
        .btn-import {
            background: #17a2b8;
            color: #fff;
        }
        
        .btn-import:hover {
            background: #138496;
            transform: translateY(-2px);
        }
        
        .btn-new {
            background: #28a745;
            color: #fff;
        }
        
        .btn-new:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        /* Container da Tabela */
        .table-container {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }
        
        .table-wrapper {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Exo', sans-serif;
        }
        
        thead th {
            background: #f8f9fa;
            padding: 18px 16px;
            text-align: left;
            font-size: 14px;
            font-weight: 700;
            color: #495057;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        tbody td {
            padding: 16px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 15px;
            color: #2c3e50;
            vertical-align: middle;
        }
        
        tbody tr {
            transition: background 0.2s ease;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .td-desc {
            font-weight: 600;
            color: #2c3e50;
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .td-fornecedor {
            font-weight: 600;
            color: #131c71;
        }
        
        .td-fornecedor.vazio {
            color: #adb5bd;
            font-style: italic;
            font-weight: 400;
        }
        
        .td-fornecedor i {
            margin-right: 5px;
        }
        
        .td-valor {
            font-weight: 700;
            font-size: 16px;
        }
        
        /* Badges de Status */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
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
        
        .badge-vencido {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            color: #c62828;
        }
        
        /* Botão de Ação na Tabela */
        .btn-view {
            color: #6c757d;
            font-size: 20px;
            transition: all 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-view:hover {
            color: #28a745;
            transform: scale(1.2);
        }
        
        /* Estado Vazio */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #adb5bd;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .empty-state h3 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
            font-family: 'Exo', sans-serif;
        }
        
        .empty-state p {
            font-size: 16px;
        }
        
        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
        }
        
        .modal-overlay.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: #fff;
            width: 90%;
            max-width: 550px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, #131c71 0%, #0d1450 100%);
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h4 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: #fff;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }
        
        /* Drawer Lateral */
        .drawer-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9998;
            backdrop-filter: blur(2px);
        }
        
        .drawer-lateral {
            position: fixed;
            top: 0;
            right: -100%;
            width: 480px;
            max-width: 90vw;
            height: 100%;
            background: #f8f9fa;
            z-index: 9999;
            transition: right 0.3s ease;
            display: flex;
            flex-direction: column;
            box-shadow: -4px 0 12px rgba(0, 0, 0, 0.15);
        }
        
        .drawer-lateral.active {
            right: 0;
        }
        
        .drawer-header {
            background: #fff;
            padding: 20px;
            display: flex;
            align-items: center;
            border-bottom: 2px solid #e0e0e0;
            gap: 15px;
        }
        
        .drawer-header h3 {
            margin: 0;
            flex: 1;
            text-align: center;
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
            font-family: 'Exo', sans-serif;
        }
        
        .btn-close-drawer {
            background: none;
            border: none;
            cursor: pointer;
            color: #6c757d;
            font-size: 24px;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .btn-close-drawer:hover {
            background: #f8f9fa;
            color: #2c3e50;
        }
        
        .drawer-content {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }
        
        .info-section {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #2c3e50;
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            font-family: 'Exo', sans-serif;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-size: 14px;
            color: #6c757d;
            font-weight: 600;
        }
        
        .info-value {
            font-size: 14px;
            color: #2c3e50;
            font-weight: 700;
            text-align: right;
        }
        
        .info-alert {
            background: #e7f3ff;
            border-left: 4px solid #17a2b8;
            padding: 12px;
            border-radius: 8px;
            margin-top: 15px;
            display: flex;
            gap: 10px;
            align-items: flex-start;
            font-size: 13px;
            line-height: 1.5;
            color: #2c3e50;
        }
        
        .info-alert i {
            color: #17a2b8;
            margin-top: 2px;
        }
        
        .info-alert a {
            color: #17a2b8;
            font-weight: 600;
        }
        
        .drawer-footer {
            background: #fff;
            padding: 20px;
            border-top: 2px solid #e0e0e0;
        }
        
        .btn-registrar {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-registrar:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(40, 167, 69, 0.4);
        }
        
        .btn-registrar:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Responsividade */
        @media (max-width: 1200px) {
            .top-bar {
                grid-template-columns: 1fr;
            }
            
            .date-selector {
                justify-content: center;
            }
            
            .top-actions {
                justify-content: center;
            }
        }
        
        @media (max-width: 768px) {
            .kpi-cards {
                grid-template-columns: 1fr;
            }
            
            .date-selector {
                flex-wrap: wrap;
                height: auto;
                padding: 15px;
            }
            
            .top-actions {
                flex-direction: column;
            }
            
            .btn-action-top {
                width: 100%;
                justify-content: center;
            }
            
            table {
                font-size: 13px;
            }
            
            thead th, tbody td {
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
                <i class="fas fa-file-invoice-dollar"></i>
                Contas a Pagar/Receber
            </h1>
        </div>

        <!-- TOP BAR: Navegação e Ações -->
        <div class="top-bar">
            <div></div>
            
            <div class="date-selector">
                <button class="date-nav-btn <?= in_array($filtro, ['todas', 'ultimos_365']) ? 'hidden' : '' ?>" 
                        onclick="navegar('prev')">
                    <i class="fas fa-chevron-left"></i>
                </button>
                
                <span class="date-label">
                    <?php if ($filtro == 'todas'): ?>
                        <i class="fas fa-infinity"></i> Todas
                    <?php elseif ($filtro == 'ultimos_365'): ?>
                        📅 Último Ano + 90 dias futuros
                    <?php else: ?>
                        <?= date('d/m', strtotime($data_inicio)) ?> - <?= date('d/m', strtotime($data_fim)) ?>
                    <?php endif; ?>
                </span>
                
                <select class="date-select" onchange="alterarTipoFiltro(this.value)">
                    <option value="todas" <?= $filtro == 'todas' ? 'selected' : '' ?>>📋 Todas as Contas</option>
                    <option value="ultimos_365" <?= $filtro == 'ultimos_365' ? 'selected' : '' ?>>📅 Último Ano</option>
                    <option value="este_mes" <?= $filtro == 'este_mes' ? 'selected' : '' ?>>Este mês</option>
                    <option value="esta_semana" <?= $filtro == 'esta_semana' ? 'selected' : '' ?>>Esta semana</option>
                </select>
                
                <button class="date-nav-btn <?= in_array($filtro, ['todas', 'ultimos_365']) ? 'hidden' : '' ?>" 
                        onclick="navegar('next')">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            
            <div class="top-actions">
                <button class="btn-action-top btn-import" onclick="abrirModal()">
                    <i class="fas fa-file-import"></i> Importar CSV
                </button>
                <a href="contas.php" class="btn-action-top btn-new">
                    <i class="fas fa-plus"></i> Nova Conta
                </a>
            </div>
        </div>

        <!-- KPI CARDS -->
        <div class="kpi-cards">
            <div class="kpi-card total" onclick="filtrarTabela('TODOS', this)">
                <div class="kpi-label">Total no Período (<?= count($contas) ?> contas)</div>
                <div class="kpi-value">R$ <?= number_format($t_todos_bruto, 2, ',', '.') ?></div>
            </div>
            
            <div class="kpi-card pendente" onclick="filtrarTabela('PENDENTE', this)">
                <div class="kpi-label">Pendentes</div>
                <div class="kpi-value">R$ <?= number_format($t_nao_pagos, 2, ',', '.') ?></div>
            </div>
            
            <div class="kpi-card vencido" onclick="filtrarTabela('VENCIDO', this)">
                <div class="kpi-label">Vencidos</div>
                <div class="kpi-value">R$ <?= number_format($t_vencidos, 2, ',', '.') ?></div>
            </div>
            
            <div class="kpi-card pago" onclick="filtrarTabela('PAGO', this)">
                <div class="kpi-label">Pagos</div>
                <div class="kpi-value">R$ <?= number_format($t_pagos, 2, ',', '.') ?></div>
            </div>
        </div>

        <!-- TABELA -->
        <div class="table-container">
            <div class="table-wrapper">
                <table id="tabelaContas">
                    <thead>
                        <tr>
                            <th width="110">Vencimento</th>
                            <th>Descrição</th>
                            <th width="220">Fornecedor/Cliente</th>
                            <th width="120" style="text-align: right;">Valor</th>
                            <th width="100" style="text-align: center;">Parcelas</th>
                            <th width="120" style="text-align: center;">Doc/NF</th>
                            <th width="110" style="text-align: center;">Status</th>
                            <th width="70" style="text-align: center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($contas) > 0): ?>
                            <?php foreach ($contas as $c): 
                                $is_vencido = ($c['vencimento'] < $hoje && $c['status_baixa'] != 'PAGO');
                                
                                if ($c['status_baixa'] == 'PAGO') {
                                    $st_class = 'badge-pago';
                                    $st_nome = 'PAGO';
                                } elseif ($is_vencido) {
                                    $st_class = 'badge-vencido';
                                    $st_nome = 'VENCIDO';
                                } else {
                                    $st_class = 'badge-pendente';
                                    $st_nome = 'PENDENTE';
                                }
                                
                                $tem_fornecedor = !empty($c['nome_entidade']);
                                $class_fornecedor = $tem_fornecedor ? 'td-fornecedor' : 'td-fornecedor vazio';
                                $texto_fornecedor = $tem_fornecedor ? htmlspecialchars($c['nome_entidade']) : 'Sem fornecedor';
                            ?>
                            <tr data-status="<?= $st_nome ?>">
                                <td><?= date('d/m/Y', strtotime($c['vencimento'])) ?></td>
                                <td class="td-desc" title="<?= htmlspecialchars($c['descricao']) ?>">
                                    <?= htmlspecialchars($c['descricao']) ?>
                                </td>
                                <td class="<?= $class_fornecedor ?>">
                                    <?php if($tem_fornecedor): ?>
                                        <i class="fas fa-building"></i>
                                    <?php endif; ?>
                                    <?= $texto_fornecedor ?>
                                </td>
                                <td class="td-valor" style="text-align: right;">
                                    R$ <?= number_format($c['valor_parcela'], 2, ',', '.') ?>
                                </td>
                                <td style="text-align: center; font-weight: 600;">
                                    <?php 
                                    $qtd_parcelas = (int)$c['qtd_parcelas'];
                                    if ($qtd_parcelas > 1) {
                                        echo '<span style="color: #17a2b8;">' . $qtd_parcelas . 'x</span>';
                                    } else {
                                        echo '<span style="color: #6c757d;">À vista</span>';
                                    }
                                    ?>
                                </td>
                                <td style="text-align: center;">
                                    <?= htmlspecialchars($c['documento'] ?? '-') ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="status-badge <?= $st_class ?>"><?= $st_nome ?></span>
                                </td>
                                <td style="text-align: center;">
                                    <a href="#" 
                                       onclick="abrirDrawerBaixa(<?= $c['id'] ?>); return false;" 
                                       class="btn-view" 
                                       title="Visualizar / Dar Baixa">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <h3>Nenhuma conta encontrada</h3>
                                        <p>Não há contas no período selecionado.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- MODAL DE IMPORTAÇÃO -->
    <div id="modalImport" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h4>
                    <i class="fas fa-file-import"></i> Importar Contas (CSV)
                </h4>
                <button onclick="fecharModal()" class="modal-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <iframe src="financeiro/importar_contas.php" frameborder="0" style="width:100%; height:450px;"></iframe>
        </div>
    </div>

    <!-- DRAWER DE VISUALIZAÇÃO/BAIXA -->
    <div id="drawerOverlay" class="drawer-overlay" onclick="fecharDrawerBaixa()"></div>
    <div id="drawerBaixa" class="drawer-lateral">
        <div class="drawer-header">
            <button onclick="fecharDrawerBaixa()" class="btn-close-drawer">
                <i class="fas fa-arrow-left"></i>
            </button>
            <h3>Visualizar Conta</h3>
            <div style="width: 40px;"></div>
        </div>
        
        <div class="drawer-content">
            <!-- Compra de produtos -->
            <div class="info-section">
                <h4 class="section-title">Compra de produtos</h4>
                
                <div class="info-row">
                    <span class="info-label">Fornecedor:</span>
                    <span class="info-value" id="drawer_fornecedor">-</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Código da compra:</span>
                    <span class="info-value" id="drawer_codigo">-</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Documento / NF:</span>
                    <span class="info-value" id="drawer_documento">-</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Data da compra:</span>
                    <span class="info-value" id="drawer_data_compra">-</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Categoria:</span>
                    <span class="info-value" id="drawer_categoria">-</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Competência:</span>
                    <span class="info-value" id="drawer_competencia">-</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Parcela:</span>
                    <span class="info-value" id="drawer_parcela">-</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Valor:</span>
                    <span class="info-value" id="drawer_valor" style="font-size: 18px; color: #28a745;">-</span>
                </div>
                
                <div class="info-alert">
                    <i class="fas fa-info-circle"></i>
                    <span id="drawer_mensagem">
                        Essa conta a pagar é decorrente de uma compra de produtos, por isso não pode ser alterada por aqui. 
                        Se quiser visualizar a compra, <a href="#" id="link_compra">clique aqui</a>
                    </span>
                </div>
            </div>
            
            <!-- Dados para pagamento -->
            <div class="info-section">
                <h4 class="section-title">Dados para pagamento</h4>
                
                <div class="form-group">
                    <label class="info-label">Forma de pagamento:</label>
                    <input type="text" id="drawer_forma_pgto" value="Boleto" readonly class="form-control" style="background: #f5f5f5; cursor: not-allowed;">
                </div>
                
                <div class="form-group">
                    <label class="info-label required">Conta prevista para pagamento:</label>
                    <select id="drawer_conta_financeira" class="form-control" required>
                        <option value="">Selecione a conta...</option>
                        <?php foreach($contas_fin as $cf): 
                            $saldo_formatado = number_format($cf['saldo_inicial'], 2, ',', '.');
                        ?>
                            <option value="<?= $cf['id'] ?>" data-saldo="<?= $cf['saldo_inicial'] ?>">
                                <?= strtoupper(htmlspecialchars($cf['nome_conta'])) ?> (Saldo: R$ <?= $saldo_formatado ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="info-label required">Data do pagamento:</label>
                    <input type="date" id="drawer_data_pagamento" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    <small style="color: #6c757d; font-size: 12px; display: block; margin-top: 5px;">
                        ⚠️ Esta data será usada para registrar o pagamento e dar baixa na conta.
                    </small>
                </div>
            </div>
            
            <div class="info-row" style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
                <span class="info-label">Vencimento:</span>
                <span class="info-value" id="drawer_vencimento" style="font-size: 16px;">-</span>
            </div>
        </div>
        
        <div class="drawer-footer">
            <button type="button" class="btn-registrar" onclick="registrarPagamento()">
                <i class="fas fa-check-circle"></i> Registrar pagamento
            </button>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // ==========================================================
        // FILTRAR TABELA POR STATUS
        // ==========================================================
        function filtrarTabela(status, element) {
            const rows = document.querySelectorAll('#tabelaContas tbody tr');
            const cards = document.querySelectorAll('.kpi-card');
            
            cards.forEach(c => c.classList.remove('active-filter'));
            element.classList.add('active-filter');
            
            rows.forEach(row => {
                if (status === 'TODOS' || row.getAttribute('data-status') === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // ==========================================================
        // NAVEGAÇÃO DE DATAS
        // ==========================================================
        function navegar(dir) {
            const filtroAtual = '<?= $filtro ?>';
            
            if (filtroAtual === 'esta_semana') {
                let d = new Date('<?= $ref_date ?>T00:00:00');
                dir === 'next' ? d.setDate(d.getDate() + 7) : d.setDate(d.getDate() - 7);
                window.location.href = `listar_contas.php?filtro=${filtroAtual}&ref=${d.toISOString().split('T')[0]}`;
            } else if (filtroAtual === 'este_mes') {
                let d = new Date('<?= $ref_date ?>T00:00:00');
                dir === 'next' ? d.setMonth(d.getMonth() + 1) : d.setMonth(d.getMonth() - 1);
                window.location.href = `listar_contas.php?filtro=${filtroAtual}&ref=${d.toISOString().split('T')[0]}`;
            }
        }
        
        function alterarTipoFiltro(v) { 
            window.location.href = `listar_contas.php?filtro=${v}`; 
        }
        
        // ==========================================================
        // MODAL DE IMPORTAÇÃO
        // ==========================================================
        function abrirModal() { 
            document.getElementById('modalImport').classList.add('show');
        }
        
        function fecharModal() { 
            document.getElementById('modalImport').classList.remove('show');
            window.location.reload(); 
        }
        
        // ==========================================================
        // DRAWER DE VISUALIZAÇÃO/BAIXA
        // ==========================================================
        let contaAtual = null;
        
        async function abrirDrawerBaixa(idConta) {
            try {
                const response = await fetch(`financeiro/buscar_conta.php?id=${idConta}`);
                const data = await response.json();
                
                if (data.status === 'success') {
                    contaAtual = data.conta;
                    
                    document.getElementById('drawer_fornecedor').textContent = data.conta.nome_entidade || 'Não informado';
                    document.getElementById('drawer_codigo').textContent = data.conta.id || '-';
                    document.getElementById('drawer_documento').textContent = data.conta.documento || '-';
                    document.getElementById('drawer_data_compra').textContent = data.conta.data_cadastro ? formatarData(data.conta.data_cadastro) : '-';
                    document.getElementById('drawer_categoria').textContent = data.conta.categoria_nome || 'Fornecedores de produtos';
                    document.getElementById('drawer_competencia').textContent = data.conta.competencia ? formatarData(data.conta.competencia) : '-';
                    document.getElementById('drawer_parcela').textContent = `${data.conta.parcela_atual || 1} de ${data.conta.qtd_parcelas || 1}`;
                    document.getElementById('drawer_valor').textContent = 'R$ ' + parseFloat(data.conta.valor_parcela).toLocaleString('pt-BR', {minimumFractionDigits: 2});
                    document.getElementById('drawer_vencimento').textContent = data.conta.vencimento ? formatarData(data.conta.vencimento) : '-';
                    
                    if (data.conta.vencimento) {
                        document.getElementById('drawer_data_pagamento').value = data.conta.vencimento;
                    }
                    
                    if (data.conta.id_compra) {
                        document.getElementById('link_compra').href = `compras.php?id=${data.conta.id_compra}`;
                    }
                    
                    document.getElementById('drawerOverlay').style.display = 'block';
                    setTimeout(() => {
                        document.getElementById('drawerBaixa').classList.add('active');
                    }, 10);
                    
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: 'Erro ao carregar dados da conta: ' + data.message,
                        confirmButtonColor: '#131c71'
                    });
                }
            } catch (error) {
                console.error('Erro:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Erro ao buscar dados da conta',
                    confirmButtonColor: '#131c71'
                });
            }
        }
        
        function fecharDrawerBaixa() {
            document.getElementById('drawerBaixa').classList.remove('active');
            setTimeout(() => {
                document.getElementById('drawerOverlay').style.display = 'none';
            }, 300);
        }
        
        function formatarData(dataStr) {
            if (!dataStr) return '-';
            const data = new Date(dataStr + 'T00:00:00');
            return data.toLocaleDateString('pt-BR');
        }
        
        // ==========================================================
        // REGISTRAR PAGAMENTO
        // ==========================================================
        async function registrarPagamento() {
            if (!contaAtual) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Nenhuma conta selecionada',
                    confirmButtonColor: '#131c71'
                });
                return;
            }
            
            const contaFinanceira = document.getElementById('drawer_conta_financeira').value;
            const dataPagamento = document.getElementById('drawer_data_pagamento').value;
            
            if (!contaFinanceira) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Atenção',
                    text: 'Por favor, selecione a conta financeira para pagamento',
                    confirmButtonColor: '#131c71'
                });
                document.getElementById('drawer_conta_financeira').focus();
                return;
            }
            
            if (!dataPagamento) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Atenção',
                    text: 'Por favor, informe a data do pagamento',
                    confirmButtonColor: '#131c71'
                });
                document.getElementById('drawer_data_pagamento').focus();
                return;
            }
            
            const valorConta = parseFloat(contaAtual.valor_parcela);
            const selectConta = document.getElementById('drawer_conta_financeira');
            const optionSelecionada = selectConta.options[selectConta.selectedIndex];
            const saldoAtual = parseFloat(optionSelecionada.getAttribute('data-saldo')) || 0;
            const nomeConta = optionSelecionada.text.split('(')[0].trim();
            const novoSaldo = saldoAtual - valorConta;
            
            let mensagemHtml = `
                <div style="text-align: left; padding: 10px;">
                    <p style="margin: 5px 0;"><strong>💰 Valor:</strong> R$ ${valorConta.toFixed(2).replace('.', ',')}</p>
                    <p style="margin: 5px 0;"><strong>🏦 Conta:</strong> ${nomeConta}</p>
                    <p style="margin: 5px 0;"><strong>📊 Saldo atual:</strong> R$ ${saldoAtual.toFixed(2).replace('.', ',')}</p>
                    <p style="margin: 5px 0;"><strong>📉 Novo saldo:</strong> R$ ${novoSaldo.toFixed(2).replace('.', ',')}</p>
                    <p style="margin: 5px 0;"><strong>📅 Data:</strong> ${new Date(dataPagamento + 'T00:00:00').toLocaleDateString('pt-BR')}</p>
                    ${novoSaldo < 0 ? '<p style="margin: 10px 0 0 0; color: #dc3545;"><strong>⚠️ ATENÇÃO: O saldo ficará negativo!</strong></p>' : ''}
                </div>
            `;
            
            const result = await Swal.fire({
                title: 'Confirmar pagamento?',
                html: mensagemHtml,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sim, confirmar',
                cancelButtonText: 'Cancelar'
            });
            
            if (!result.isConfirmed) return;
            
            const btn = document.querySelector('.btn-registrar');
            const textOriginal = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
            
            try {
                const formData = new FormData();
                formData.append('id_conta', contaAtual.id);
                formData.append('id_conta_financeira', contaFinanceira);
                formData.append('marcar_pago', '1');
                formData.append('data_pagamento', dataPagamento);
                formData.append('valor', valorConta);
                formData.append('nf', contaAtual.documento || '');
                
                const response = await fetch('financeiro/registrar_pagamento.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    fecharDrawerBaixa();
                    await Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: result.message,
                        confirmButtonColor: '#28a745',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    window.location.reload();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: result.message,
                        confirmButtonColor: '#131c71'
                    });
                    btn.disabled = false;
                    btn.innerHTML = textOriginal;
                }
            } catch (error) {
                console.error('Erro:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Erro ao processar pagamento: ' + error.message,
                    confirmButtonColor: '#131c71'
                });
                btn.disabled = false;
                btn.innerHTML = textOriginal;
            }
        }
    </script>
</body>
</html>