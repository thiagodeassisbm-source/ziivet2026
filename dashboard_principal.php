<?php
/**
 * =========================================================================================
 * ZIIPVET - DASHBOARD OPERACIONAL PRINCIPAL
 * ARQUIVO: dashboard_principal.php
 * VERSÃO: 2.0.0 - LAYOUT MODERNO PADRONIZADO
 * =========================================================================================
 */
require_once 'auth.php';
require_once 'config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'];
$hoje = date('Y-m-d');
$mes_atual = date('Y-m');
$ano_atual = date('Y');

// ===== INICIALIZAÇÃO DE VARIÁVEIS =====
$total_clientes = 0;
$total_pets = 0;
$atendimentos_mes = 0;
$vacinas_criticas = 0;
$ultimos_atendimentos = [];
$vacinas_atrasadas = [];
$vacinas_proximas = [];

try {
    // ===== CARDS PRINCIPAIS =====
    
    // Total de Clientes
    $stmt_clientes = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE id_admin = ?");
    $stmt_clientes->execute([$id_admin]);
    $total_clientes = $stmt_clientes->fetchColumn();
    
    // Total de Pacientes (Pets) Ativos
    $stmt_pets = $pdo->prepare("SELECT COUNT(*) FROM pacientes WHERE id_cliente IN (SELECT id FROM clientes WHERE id_admin = ?) AND status = 'ATIVO'");
    $stmt_pets->execute([$id_admin]);
    $total_pets = $stmt_pets->fetchColumn();
    
    // Atendimentos realizados no mês atual
    $stmt_atendimentos = $pdo->prepare("SELECT COUNT(*) FROM atendimentos WHERE id_paciente IN (SELECT p.id FROM pacientes p INNER JOIN clientes c ON p.id_cliente = c.id WHERE c.id_admin = ?) AND data_atendimento LIKE ?");
    $stmt_atendimentos->execute([$id_admin, $mes_atual.'%']);
    $atendimentos_mes = $stmt_atendimentos->fetchColumn();

    // Vacinas Críticas (Atrasadas)
    $stmt_vacinas_criticas = $pdo->prepare("
        SELECT COUNT(*) 
        FROM pacientes p
        INNER JOIN clientes c ON p.id_cliente = c.id
        LEFT JOIN atendimentos a ON p.id = a.id_paciente AND a.tipo_atendimento = 'Vacinação'
        WHERE c.id_admin = ?
        AND p.status = 'ATIVO'
        GROUP BY p.id
        HAVING DATEDIFF(?, MAX(a.data_atendimento)) > 365 OR MAX(a.data_atendimento) IS NULL
    ");
    $stmt_vacinas_criticas->execute([$id_admin, $hoje]);
    $vacinas_criticas = $stmt_vacinas_criticas->rowCount();

    // ===== ÚLTIMOS ATENDIMENTOS =====
    $stmt_ultimos = $pdo->prepare("
        SELECT a.resumo, a.data_atendimento, p.nome as nome_paciente, c.nome as nome_cliente
        FROM atendimentos a 
        INNER JOIN pacientes p ON a.id_paciente = p.id 
        INNER JOIN clientes c ON p.id_cliente = c.id
        WHERE c.id_admin = ?
        ORDER BY a.data_atendimento DESC 
        LIMIT 5
    ");
    $stmt_ultimos->execute([$id_admin]);
    $ultimos_atendimentos = $stmt_ultimos->fetchAll(PDO::FETCH_ASSOC);

    // ===== VACINAS ATRASADAS =====
    $stmt_vacinas_atrasadas = $pdo->prepare("
        SELECT p.id as id_paciente, p.nome as nome_paciente, 
               c.nome as nome_cliente, c.telefone,
               MAX(a.data_atendimento) as ultima_vacina,
               DATEDIFF(?, MAX(a.data_atendimento)) as dias_atraso
        FROM pacientes p
        INNER JOIN clientes c ON p.id_cliente = c.id
        LEFT JOIN atendimentos a ON p.id = a.id_paciente AND a.tipo_atendimento = 'Vacinação'
        WHERE c.id_admin = ?
        AND p.status = 'ATIVO'
        GROUP BY p.id
        HAVING DATEDIFF(?, MAX(a.data_atendimento)) > 365 OR MAX(a.data_atendimento) IS NULL
        ORDER BY dias_atraso DESC
        LIMIT 10
    ");
    $stmt_vacinas_atrasadas->execute([$hoje, $id_admin, $hoje]);
    $vacinas_atrasadas = $stmt_vacinas_atrasadas->fetchAll(PDO::FETCH_ASSOC);

    // ===== VACINAS PRÓXIMAS (30 DIAS) =====
    $data_limite_vacina = date('Y-m-d', strtotime('+30 days'));
    $stmt_vacinas_proximas = $pdo->prepare("
        SELECT p.id as id_paciente, p.nome as nome_paciente,
               c.nome as nome_cliente, c.telefone,
               MAX(a.data_atendimento) as ultima_vacina,
               DATE_ADD(MAX(a.data_atendimento), INTERVAL 365 DAY) as proxima_vacina,
               DATEDIFF(DATE_ADD(MAX(a.data_atendimento), INTERVAL 365 DAY), ?) as dias_restantes
        FROM pacientes p
        INNER JOIN clientes c ON p.id_cliente = c.id
        INNER JOIN atendimentos a ON p.id = a.id_paciente AND a.tipo_atendimento = 'Vacinação'
        WHERE c.id_admin = ?
        AND p.status = 'ATIVO'
        GROUP BY p.id
        HAVING DATE_ADD(MAX(a.data_atendimento), INTERVAL 365 DAY) BETWEEN ? AND ?
        ORDER BY proxima_vacina ASC
        LIMIT 10
    ");
    $stmt_vacinas_proximas->execute([$hoje, $id_admin, $hoje, $data_limite_vacina]);
    $vacinas_proximas = $stmt_vacinas_proximas->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erro Dashboard: " . $e->getMessage());
}

$titulo_pagina = "Dashboard Principal";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Exo:wght@300;400;600;700;800&family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS CENTRALIZADO -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/menu.css">
    <link rel="stylesheet" href="css/header.css">
    
    <style>
        /* Apenas ajustes específicos de layout do dashboard que não estão no style.css global */
        
        /* TÍTULO DA PÁGINA */
        .page-header-title {
            font-size: 26px;
            margin-bottom: 25px;
            color: #444;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .page-header-title i {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--cor-principal), #8e44ad);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        /* TÍTULO DA PÁGINA */
        .page-header-title {
            font-size: 26px;
            margin-bottom: 25px;
            color: #444;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .page-header-title i {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--roxo), #8e44ad);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        /* GRID DOS CARDS */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        /* CARDS MODERNOS */
        .small-box {
            border-radius: 12px;
            position: relative;
            display: block;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            color: #fff;
            overflow: hidden;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .small-box:hover {
            text-decoration: none;
            color: #fff;
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.2);
        }

        .small-box .inner {
            padding: 15px 20px 10px;
            position: relative;
            z-index: 2;
        }

        .small-box h3 {
            font-size: 28px;
            font-weight: 800;
            margin: 0 0 5px 0;
            font-family: 'Exo', sans-serif;
        }

        /* Cores dos Cards - Padronizadas */
        .bg-blue { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); }
        .bg-cyan { background: linear-gradient(135deg, #00c0ef 0%, #00a7d0 100%); }
        .bg-orange { background: linear-gradient(135deg, #f39c12 0%, #e08e0b 100%); }
        .bg-red { background: linear-gradient(135deg, #b92426 0%, #a01f21 100%); }

        /* PAINEIS DE INFORMAÇÕES */
        .info-panels {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .panel-box {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow: hidden;
            border-top: 3px solid #d2d6de;
        }
        
        .panel-header {
            background: #fcfcfc;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            font-size: 16px;
            font-weight: 700;
            color: #444;
            font-family: 'Exo', sans-serif;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .panel-header .title {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .panel-header i {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--cor-principal), #8e44ad);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }
        
        .panel-header .badge {
            background: var(--vermelho);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .panel-body {
            padding: 0;
            max-height: 450px;
            overflow-y: auto;
        }

        /* LISTA DE ITENS */
        .item-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .item-list li {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }

        .item-list li:hover {
            background: #f8f9fa;
        }

        .item-list li:last-child {
            border-bottom: none;
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .item-title {
            font-weight: 700;
            color: #2c3e50;
            font-size: 14px;
            font-family: 'Exo', sans-serif;
        }

        .item-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
        }

        .badge-danger {
            background: #fee;
            color: var(--vermelho);
        }

        .badge-critical {
            background: #dc3545;
            color: white;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .item-details {
            display: flex;
            gap: 20px;
            font-size: 13px;
            color: #666;
        }

        .item-details span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .item-details i {
            color: #999;
        }

        /* ESTADO VAZIO */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }

        .empty-state p {
            font-size: 14px;
            font-family: 'Exo', sans-serif;
        }

        /* TABS */
        .tabs-container {
            display: flex;
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        .tab-button {
            flex: 1;
            padding: 15px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            color: #666;
            font-family: 'Exo', sans-serif;
            transition: all 0.3s;
            position: relative;
        }

        .tab-button.active {
            color: var(--roxo);
        }

        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--roxo);
        }

        .tab-button:hover {
            background: rgba(98, 37, 153, 0.05);
        }

        .tab-button .badge {
            background: var(--vermelho);
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            margin-left: 8px;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* BOTÕES DE AÇÃO RÁPIDA */
        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
            padding: 25px;
        }

        .btn-action {
            padding: 15px 20px;
            border-radius: 10px;
            text-decoration: none !important;
            font-weight: 700;
            font-size: 15px;
            font-family: 'Exo', sans-serif;
            text-align: center;
            transition: all 0.3s;
            border: 2px solid;
            display: flex !important;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            box-sizing: border-box;
            position: relative;
            z-index: 1;
        }

        .btn-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
            text-decoration: none !important;
        }

        .btn-primary {
            background: var(--cor-principal);
            color: white !important;
            border-color: var(--cor-principal);
        }

        .btn-primary:hover {
            background: var(--cor-escura);
            color: white !important;
            border-color: var(--cor-escura);
        }

        .btn-outline-primary {
            background: #fff;
            color: var(--cor-principal) !important;
            border-color: var(--cor-principal);
        }

        .btn-outline-primary:hover {
            background: var(--cor-principal);
            color: white !important;
            border-color: var(--cor-principal);
        }

        .btn-outline-success {
            background: #fff;
            color: var(--cor-sucesso) !important;
            border-color: var(--cor-sucesso);
        }

        .btn-outline-success:hover {
            background: var(--cor-sucesso);
            color: white !important;
            border-color: var(--cor-sucesso);
        }

        .btn-danger {
            background: var(--cor-danger);
            color: white !important;
            border-color: var(--cor-danger);
        }

        .btn-danger:hover {
            background: #a01f21;
            color: white !important;
            border-color: #a01f21;
        }

        /* ANIMAÇÕES */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .small-box {
            animation: fadeIn 0.5s ease-out;
        }
        
        .small-box:nth-child(1) { animation-delay: 0.1s; }
        .small-box:nth-child(2) { animation-delay: 0.2s; }
        .small-box:nth-child(3) { animation-delay: 0.3s; }
        .small-box:nth-child(4) { animation-delay: 0.4s; }

        /* RESPONSIVO */
        @media (max-width: 1200px) {
            .info-panels {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            .page-header-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>

    <aside class="sidebar-container">
        <?php include 'menu/menulateral.php'; ?>
    </aside>

    <header class="top-header">
        <?php include 'menu/faixa.php'; ?>
    </header>

    <main class="main-content">
        
        <h2 class="page-header-title">
            <i class="fas fa-chart-line"></i>
            Dashboard - Sistema de Gerenciamento Veterinário
        </h2>

        <!-- CARDS PRINCIPAIS -->
        <div class="dashboard-grid">
            
            <a href="listar_clientes.php" class="small-box bg-blue">
                <div class="inner">
                    <p>Total de Clientes</p>
                    <h3><?= number_format($total_clientes, 0, ',', '.') ?></h3>
                </div>
                <div class="icon-bg">
                    <i class="fas fa-users"></i>
                </div>
                <span class="small-box-footer">
                    Ver Detalhes <i class="fas fa-arrow-circle-right"></i>
                </span>
            </a>

            <a href="listar_pacientes.php" class="small-box bg-cyan">
                <div class="inner">
                    <p>Total de Pets</p>
                    <h3><?= number_format($total_pets, 0, ',', '.') ?></h3>
                </div>
                <div class="icon-bg">
                    <i class="fas fa-paw"></i>
                </div>
                <span class="small-box-footer">
                    Ver Detalhes <i class="fas fa-arrow-circle-right"></i>
                </span>
            </a>

            <a href="consultas/listar_atendimentos.php" class="small-box bg-orange">
                <div class="inner">
                    <p>Atendimentos no Mês</p>
                    <h3><?= number_format($atendimentos_mes, 0, ',', '.') ?></h3>
                </div>
                <div class="icon-bg">
                    <i class="fas fa-stethoscope"></i>
                </div>
                <span class="small-box-footer">
                    Ver Detalhes <i class="fas fa-arrow-circle-right"></i>
                </span>
            </a>

            <a href="consultas/listar_vacinas.php" class="small-box bg-red">
                <div class="inner">
                    <p>Vacinas Atrasadas</p>
                    <h3><?= number_format($vacinas_criticas, 0, ',', '.') ?></h3>
                </div>
                <div class="icon-bg">
                    <i class="fas fa-syringe"></i>
                </div>
                <span class="small-box-footer">
                    Ver Detalhes <i class="fas fa-arrow-circle-right"></i>
                </span>
            </a>

        </div>

        <!-- PAINEIS DE INFORMAÇÕES -->
        <div class="info-panels">
            
            <!-- ÚLTIMOS ATENDIMENTOS -->
            <div class="panel-box">
                <div class="panel-header">
                    <div class="title">
                        <i class="fas fa-history"></i>
                        Últimos Atendimentos Realizados
                    </div>
                </div>
                <div class="panel-body">
                    <?php if (empty($ultimos_atendimentos)): ?>
                        <div class="empty-state">
                            <i class="fas fa-clipboard"></i>
                            <p>Nenhum atendimento realizado recentemente</p>
                        </div>
                    <?php else: ?>
                        <ul class="item-list">
                            <?php foreach($ultimos_atendimentos as $at): ?>
                                <li>
                                    <div class="item-header">
                                        <span class="item-title">
                                            <i class="fas fa-paw"></i> <?= htmlspecialchars($at['nome_paciente']) ?>
                                        </span>
                                        <span class="item-badge badge-success">
                                            <?= date('d/m/Y', strtotime($at['data_atendimento'])) ?>
                                        </span>
                                    </div>
                                    <div class="item-details">
                                        <span><i class="fas fa-user"></i> <?= htmlspecialchars($at['nome_cliente']) ?></span>
                                        <span><i class="fas fa-notes-medical"></i> <?= htmlspecialchars($at['resumo']) ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ACESSO RÁPIDO -->
            <div class="panel-box">
                <div class="panel-header">
                    <div class="title">
                        <i class="fas fa-rocket"></i>
                        Acesso Rápido
                    </div>
                </div>
                <div class="quick-actions">
                    <a href="consultas/atendimento.php" class="btn-action btn-primary">
                        <i class="fas fa-plus-circle"></i> Novo Atendimento
                    </a>
                    <a href="consultas/vacinas.php" class="btn-action btn-outline-success">
                        <i class="fas fa-syringe"></i> Registrar Vacina
                    </a>
                    <a href="consultas/receita.php" class="btn-action btn-outline-primary">
                        <i class="fas fa-file-prescription"></i> Emitir Receita
                    </a>
                    <a href="consultas/listar_vacinas.php" class="btn-action btn-danger">
                        <i class="fas fa-exclamation-triangle"></i> Ver Vacinas Atrasadas
                    </a>
                </div>
            </div>

            <!-- PERIÓDICOS (VACINAS) -->
            <div class="panel-box">
                <div class="panel-header">
                    <div class="title">
                        <i class="fas fa-syringe"></i>
                        Periódicos (Vacinas)
                    </div>
                </div>
                
                <div class="tabs-container">
                    <button class="tab-button active" onclick="switchTab('atrasadas')">
                        Vacinas Atrasadas
                        <?php if (count($vacinas_atrasadas) > 0): ?>
                            <span class="badge"><?= count($vacinas_atrasadas) ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="tab-button" onclick="switchTab('proximas')">
                        Próximas 30 Dias
                        <?php if (count($vacinas_proximas) > 0): ?>
                            <span class="badge"><?= count($vacinas_proximas) ?></span>
                        <?php endif; ?>
                    </button>
                </div>
                
                <div class="panel-body">
                    <!-- TAB: VACINAS ATRASADAS -->
                    <div id="tab-atrasadas" class="tab-content active">
                        <?php if (empty($vacinas_atrasadas)): ?>
                            <div class="empty-state">
                                <i class="fas fa-check-circle"></i>
                                <p>Nenhuma vacina atrasada</p>
                            </div>
                        <?php else: ?>
                            <ul class="item-list">
                                <?php foreach ($vacinas_atrasadas as $vacina): ?>
                                    <li>
                                        <div class="item-header">
                                            <span class="item-title">
                                                <i class="fas fa-paw"></i> <?= htmlspecialchars($vacina['nome_paciente']) ?>
                                            </span>
                                            <span class="item-badge badge-danger">
                                                <?= $vacina['dias_atraso'] ? $vacina['dias_atraso'] . ' dias' : 'Nunca vacinado' ?>
                                            </span>
                                        </div>
                                        <div class="item-details">
                                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($vacina['nome_cliente']) ?></span>
                                            <?php if ($vacina['telefone']): ?>
                                                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($vacina['telefone']) ?></span>
                                            <?php endif; ?>
                                            <?php if ($vacina['ultima_vacina']): ?>
                                                <span><i class="fas fa-calendar"></i> Última: <?= date('d/m/Y', strtotime($vacina['ultima_vacina'])) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    
                    <!-- TAB: VACINAS PRÓXIMAS -->
                    <div id="tab-proximas" class="tab-content">
                        <?php if (empty($vacinas_proximas)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-check"></i>
                                <p>Nenhuma vacina a vencer nos próximos 30 dias</p>
                            </div>
                        <?php else: ?>
                            <ul class="item-list">
                                <?php foreach ($vacinas_proximas as $vacina): ?>
                                    <li>
                                        <div class="item-header">
                                            <span class="item-title">
                                                <i class="fas fa-paw"></i> <?= htmlspecialchars($vacina['nome_paciente']) ?>
                                            </span>
                                            <span class="item-badge badge-warning">
                                                <?= $vacina['dias_restantes'] ?> dias
                                            </span>
                                        </div>
                                        <div class="item-details">
                                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($vacina['nome_cliente']) ?></span>
                                            <?php if ($vacina['telefone']): ?>
                                                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($vacina['telefone']) ?></span>
                                            <?php endif; ?>
                                            <span><i class="fas fa-calendar-plus"></i> Próxima: <?= date('d/m/Y', strtotime($vacina['proxima_vacina'])) ?></span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

    </main>

    <script>
        function switchTab(tabName) {
            // Remover active de todos os botões e conteúdos
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Adicionar active ao botão e conteúdo clicados
            event.target.classList.add('active');
            document.getElementById('tab-' + tabName).classList.add('active');
        }
    </script>
</body>
</html>