<?php
/**
 * =========================================================================================
 * ZIIPVET - LISTAGEM DE AGENDA OPERACIONAL
 * ARQUIVO: listar_agenda.php
 * VERSÃO: 8.0.0 - LAYOUT MODERNO PADRONIZADO
 * =========================================================================================
 */
require_once 'auth.php';
require_once 'config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;

// ==========================================================
// LÓGICA DE AÇÕES (AJAX/POST)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    
    header('Content-Type: application/json; charset=utf-8');
    ob_clean();
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
        exit;
    }

    try {
        if ($_POST['acao'] === 'alterar_status') {
            
            $novo_status = isset($_POST['status']) ? trim($_POST['status']) : '';
            
            $status_permitidos = ['Confirmado', 'Cancelado', 'Remarcado', 'Sem Resposta'];
            
            if (!in_array($novo_status, $status_permitidos)) {
                echo json_encode(['status' => 'error', 'message' => 'Status inválido: ' . $novo_status]);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE agendas SET status = ? WHERE id = ?");
            $sucesso = $stmt->execute([$novo_status, $id]);
            
            if ($sucesso && $stmt->rowCount() > 0) {
                echo json_encode([
                    'status' => 'success', 
                    'message' => 'Status alterado para ' . $novo_status,
                    'novo_status' => $novo_status
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Nenhuma linha foi atualizada.']);
            }
            
        } elseif ($_POST['acao'] === 'finalizar') {
            
            $stmt = $pdo->prepare("UPDATE agendas SET status = 'Finalizado', horario_fim = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success', 'message' => 'Atendimento finalizado!']);
            
        } elseif ($_POST['acao'] === 'excluir') {
            
            $stmt = $pdo->prepare("DELETE FROM agendas WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success', 'message' => 'Agendamento removido!']);
            
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Ação desconhecida']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro no banco: ' . $e->getMessage()]);
    }
    
    exit;
}

// ==========================================================
// FILTROS E BUSCA
// ==========================================================
$busca = $_GET['busca'] ?? '';
$filtro_status = $_GET['status'] ?? '';
$data_filtro = $_GET['data'] ?? '';

$where = "WHERE 1=1";
if (!empty($busca)) $where .= " AND (p.nome_paciente LIKE :busca OR c.nome LIKE :busca)";
if (!empty($filtro_status)) $where .= " AND a.status = :status";
if (!empty($data_filtro)) $where .= " AND a.data_agendamento = :data";

// ==========================================================
// PAGINAÇÃO
// ==========================================================
$itens_por_pagina = 40;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $itens_por_pagina;

$stmt_total = $pdo->prepare("SELECT COUNT(*) FROM agendas a INNER JOIN pacientes p ON a.id_paciente = p.id INNER JOIN clientes c ON p.id_cliente = c.id $where");
if (!empty($busca)) $stmt_total->bindValue(':busca', "%$busca%");
if (!empty($filtro_status)) $stmt_total->bindValue(':status', $filtro_status);
if (!empty($data_filtro)) $stmt_total->bindValue(':data', $data_filtro);
$stmt_total->execute();
$total_registros = $stmt_total->fetchColumn();
$total_paginas = ceil($total_registros / $itens_por_pagina);

// ==========================================================
// QUERY PRINCIPAL
// ==========================================================
$sql = "SELECT a.*, p.nome_paciente, c.nome as nome_cliente, c.telefone, s.nome as nome_servico 
        FROM agendas a
        INNER JOIN pacientes p ON a.id_paciente = p.id
        INNER JOIN clientes c ON p.id_cliente = c.id
        INNER JOIN produtos s ON a.id_servico = s.id
        $where
        ORDER BY a.data_agendamento DESC, a.horario ASC
        LIMIT $itens_por_pagina OFFSET $offset";

$stmt = $pdo->prepare($sql);
if (!empty($busca)) $stmt->bindValue(':busca', "%$busca%");
if (!empty($filtro_status)) $stmt->bindValue(':status', $filtro_status);
if (!empty($data_filtro)) $stmt->bindValue(':data', $data_filtro);
$stmt->execute();
$agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = "Lista de Agendamentos";
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
           ESTILOS ESPECÍFICOS PARA LISTAGEM
        ======================================== */
        
        /* Container de Filtros */
        .filtros-container {
            background: #fff;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }
        
        .filtros-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 15px;
            align-items: flex-end;
        }
        
        .filtro-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #6c757d;
            margin-bottom: 8px;
            text-transform: uppercase;
            font-family: 'Exo', sans-serif;
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
        
        /* Card da Tabela */
        .card-tabela {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }
        
        /* Tabela Moderna */
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        
        thead {
            background: linear-gradient(135deg, #131c71 0%, #4a1d75 100%);
        }
        
        th {
            text-align: left;
            padding: 18px 15px;
            color: #fff;
            font-size: 13px;
            text-transform: uppercase;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 16px 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
            font-size: 15px;
            color: #2c3e50;
        }
        
        tbody tr {
            transition: all 0.2s ease;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        /* Layout Otimizado das Colunas */
        th:nth-child(1), td:nth-child(1) { width: 90px; } /* Hora/Data */
        th:nth-child(2), td:nth-child(2) { width: auto; } /* Paciente/Tutor */
        th:nth-child(3), td:nth-child(3) { width: 140px; } /* Tipo (Novo) */
        th:nth-child(4), td:nth-child(4) { width: 180px; } /* Serviço */
        th:nth-child(5), td:nth-child(5) { width: 130px; } /* Status */
        th:nth-child(6), td:nth-child(6) { width: 260px; text-align: center; } /* Ações */
        
        /* Badges de Tipo */
        .badge-tipo {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-family: 'Exo', sans-serif;
        }
        .badge-tipo.estetica {
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
        }
        .badge-tipo.consultorio {
            background: #f3e5f5;
            color: #7b1fa2;
            border: 1px solid #e1bee7;
        }

        /* Informações da Célula */
        td strong {
            font-weight: 600;
            color: #b92426;   /* COR DO NOME DO ANIMAL */
            display: block;
            margin-bottom: 4px;
            font-size: 15px;
        }
        
        td small {
            font-size: 13px;
            color: #6c757d;
            display: block;
        }
        
        .info-hora {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        
        .hora-principal {
            font-size: 18px;
            font-weight: 700;
            color:  #28A745; /* COR DA HORA */
            font-family: 'Exo', sans-serif;
        }
        
        .data-secundaria {
            font-size: 12px;
            color: #95a5a6;
            margin-top: 2px;
        }
        
        /* Badges de Status */
        .badge-status {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-family: 'Exo', sans-serif;
        }
        
        .status-agendado {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1565c0;
        }
        
        .status-confirmado {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            color: #2e7d32;
        }
        
        .status-cancelado {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            color: #c62828;
        }
        
        .status-remarcado {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            color: #ef6c00;
        }
        
        .status-sem.resposta {
            background: linear-gradient(135deg, #e1f5fe 0%, #b3e5fc 100%);
            color: #0277bd;
        }
        
        .status-finalizado {
            background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);
            color: #7b1fa2;
        }
        
        /* Dropdown de Status */
        .dropdown-status {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-status-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: #fff;
            border: none;
            padding: 8px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.3s ease;
            white-space: nowrap;
            text-transform: uppercase;
            box-shadow: 0 2px 6px rgba(40, 167, 69, 0.3);
        }
        
        .dropdown-status-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
        }
        
        .dropdown-status-btn i {
            font-size: 10px;
        }
        
        /* Menu do Dropdown */
        .dropdown-status-menu {
            display: none;
            position: fixed;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            min-width: 200px;
            z-index: 99999;
            overflow: hidden;
        }
        
        .dropdown-status-menu.show {
            display: block;
            animation: dropdownFadeIn 0.2s ease-out;
        }
        
        @keyframes dropdownFadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .dropdown-status-item {
            padding: 14px 16px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Exo', sans-serif;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .dropdown-status-item:last-child {
            border-bottom: none;
        }
        
        .dropdown-status-item i {
            font-size: 16px;
            width: 20px;
            text-align: center;
        }
        
        .dropdown-status-item:hover {
            transform: translateX(5px);
        }
        
        .dropdown-status-item.confirmado { color: #2e7d32; }
        .dropdown-status-item.confirmado:hover { background: #e8f5e9; }
        
        .dropdown-status-item.cancelado { color: #c62828; }
        .dropdown-status-item.cancelado:hover { background: #ffebee; }
        
        .dropdown-status-item.remarcado { color: #ef6c00; }
        .dropdown-status-item.remarcado:hover { background: #fff3e0; }
        
        .dropdown-status-item.sem-resposta { color: #0277bd; }
        .dropdown-status-item.sem-resposta:hover { background: #e1f5fe; }
        
        /* Container de Ações */
        .acoes-container {
            display: flex;
            gap: 6px;
            justify-content: center;
            align-items: center;
            flex-wrap: nowrap;
        }
        
        /* Botões de Ação Modernos */
        .btn-acao {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
        }
        
        .btn-acao::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            transform: translate(-50%, -50%);
            transition: width 0.3s, height 0.3s;
        }
        
        .btn-acao:hover::before {
            width: 100%;
            height: 100%;
        }
        
        .btn-acao i {
            position: relative;
            z-index: 1;
        }
        
        .btn-edit {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: #fff;
        }
        
        .btn-finish {
            background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
            color: #fff;
        }
        
        .btn-whats {
            background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
            color: #fff;
        }
        
        .btn-del {
            background: #b92426;
            color: #fff;
        }
        
        .btn-acao:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .btn-acao:active {
            transform: translateY(-1px);
        }
        
        /* Paginação Moderna */
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 30px;
            padding: 20px 0;
        }
        
        .pagination {
            display: inline-flex;
            list-style: none;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .pagination li {
            margin: 0;
        }
        
        .pagination li a {
            padding: 12px 18px;
            border: 1px solid #e0e0e0;
            border-right: none;
            color: #495057;
            text-decoration: none;
            background: #fff;
            font-weight: 600;
            font-size: 15px;
            font-family: 'Exo', sans-serif;
            transition: all 0.3s ease;
            display: block;
        }
        
        .pagination li:last-child a {
            border-right: 1px solid #e0e0e0;
        }
        
        .pagination li a:hover {
            background: #f8f9fa;
            color: #28A745;
        }
        
        .pagination li.active a {
            background: linear-gradient(135deg, #131c71 0%, #4a1d75 100%);
            border-color: #131c71;
            color: #fff;
        }
        
        /* Mensagem de Vazio */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #95a5a6;
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
        
        /* Responsividade */
        @media (max-width: 1200px) {
            .filtros-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .btn-filtrar {
                grid-column: 1 / -1;
            }
        }
        
        @media (max-width: 768px) {
            .filtros-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 13px;
            }
            
            th, td {
                padding: 10px 8px;
            }
            
            .acoes-container {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include 'menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include 'menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <!-- HEADER: Título e Botão na mesma linha -->
        <div class="form-header">
            <h1 class="form-title">
                <i class="fas fa-calendar-alt"></i>
                <?= $titulo_pagina ?>
            </h1>
            
            <a href="agenda.php" class="btn-voltar" style="background: #131c71;">
                <i class="fas fa-plus"></i>
                Novo Agendamento
            </a>
        </div>

        <!-- FILTROS -->
        <form method="GET" class="filtros-container">
            <div class="filtros-grid">
                <div class="filtro-group">
                    <label>
                        <i class="fas fa-search"></i>
                        Pesquisar
                    </label>
                    <input type="text" name="busca" class="form-control" value="<?= htmlspecialchars($busca) ?>" placeholder="Buscar por pet ou cliente...">
                </div>
                
                <div class="filtro-group">
                    <label>
                        <i class="fas fa-calendar"></i>
                        Data
                    </label>
                    <input type="date" name="data" class="form-control" value="<?= htmlspecialchars($data_filtro) ?>">
                </div>
                
                <div class="filtro-group">
                    <label>
                        <i class="fas fa-filter"></i>
                        Status
                    </label>
                    <select name="status" class="form-control">
                        <option value="">Todos os Status</option>
                        <option value="Agendado" <?= $filtro_status == 'Agendado' ? 'selected' : '' ?>>Agendado</option>
                        <option value="Confirmado" <?= $filtro_status == 'Confirmado' ? 'selected' : '' ?>>Confirmado</option>
                        <option value="Cancelado" <?= $filtro_status == 'Cancelado' ? 'selected' : '' ?>>Cancelado</option>
                        <option value="Remarcado" <?= $filtro_status == 'Remarcado' ? 'selected' : '' ?>>Remarcado</option>
                        <option value="Sem Resposta" <?= $filtro_status == 'Sem Resposta' ? 'selected' : '' ?>>Sem Resposta</option>
                        <option value="Finalizado" <?= $filtro_status == 'Finalizado' ? 'selected' : '' ?>>Finalizado</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-filtrar">
                    <i class="fas fa-filter"></i>
                    Filtrar
                </button>
            </div>
        </form>

        <!-- TABELA DE AGENDAMENTOS -->
        <div class="card-tabela">
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-clock"></i> Horário</th>
                        <th><i class="fas fa-paw"></i> Paciente / Tutor</th>
                        <th><i class="fas fa-tags"></i> Tipo</th>
                        <th><i class="fas fa-briefcase-medical"></i> Serviço</th>
                        <th><i class="fas fa-info-circle"></i> Status</th>
                        <th style="text-align: center;"><i class="fas fa-cogs"></i> Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($agendamentos) > 0): ?>
                        <?php foreach ($agendamentos as $ag): 
                            $whatsapp = "https://api.whatsapp.com/send?phone=55" . preg_replace('/\D/', '', $ag['telefone']);
                        ?>
                            <tr id="linha-<?= $ag['id'] ?>">
                                <td>
                                    <div class="info-hora">
                                        <span class="hora-principal"><?= substr($ag['horario'], 0, 5) ?></span>
                                        <span class="data-secundaria"><?= date('d/m/Y', strtotime($ag['data_agendamento'])) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($ag['nome_paciente']) ?></strong>
                                    <small><?= htmlspecialchars($ag['nome_cliente']) ?></small>
                                </td>
                                <td>
                                    <?php if (isset($ag['tipo_servico']) && $ag['tipo_servico'] == 'consultorio'): ?>
                                        <span class="badge-tipo consultorio"><i class="fas fa-user-md"></i> Consultório</span>
                                    <?php else: ?>
                                        <span class="badge-tipo estetica"><i class="fas fa-cut"></i> Estética</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($ag['nome_servico']) ?></td>
                                <td>
                                    <span class="badge-status status-<?= strtolower(str_replace(' ', '.', $ag['status'])) ?>" id="badge-<?= $ag['id'] ?>">
                                        <?php 
                                        $icons = [
                                            'Agendado' => 'fa-calendar-check',
                                            'Confirmado' => 'fa-check-circle',
                                            'Cancelado' => 'fa-times-circle',
                                            'Remarcado' => 'fa-calendar-alt',
                                            'Sem Resposta' => 'fa-phone-slash',
                                            'Finalizado' => 'fa-flag-checkered'
                                        ];
                                        ?>
                                        <i class="fas <?= $icons[$ag['status']] ?? 'fa-circle' ?>"></i>
                                        <?= $ag['status'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="acoes-container">
                                        <!-- WhatsApp -->
                                        <a href="<?= $whatsapp ?>" target="_blank" class="btn-acao btn-whats" title="Enviar WhatsApp">
                                            <i class="fab fa-whatsapp"></i>
                                        </a>
                                        
                                        <!-- Dropdown Status (só se não finalizado) -->
                                        <?php if($ag['status'] != 'Finalizado'): ?>
                                            <div class="dropdown-status">
                                                <button type="button" class="dropdown-status-btn" onclick="toggleDropdown(event, <?= $ag['id'] ?>)">
                                                    Status
                                                    <i class="fas fa-chevron-down"></i>
                                                </button>
                                                <div class="dropdown-status-menu" id="dropdown-<?= $ag['id'] ?>">
                                                    <div class="dropdown-status-item confirmado" onclick="alterarStatus(event, <?= $ag['id'] ?>, 'Confirmado')">
                                                        <i class="fas fa-check-circle"></i> Confirmado
                                                    </div>
                                                    <div class="dropdown-status-item cancelado" onclick="alterarStatus(event, <?= $ag['id'] ?>, 'Cancelado')">
                                                        <i class="fas fa-times-circle"></i> Cancelado
                                                    </div>
                                                    <div class="dropdown-status-item remarcado" onclick="alterarStatus(event, <?= $ag['id'] ?>, 'Remarcado')">
                                                        <i class="fas fa-calendar-alt"></i> Remarcado
                                                    </div>
                                                    <div class="dropdown-status-item sem-resposta" onclick="alterarStatus(event, <?= $ag['id'] ?>, 'Sem Resposta')">
                                                        <i class="fas fa-phone-slash"></i> Sem Resposta
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Finalizar (só se não finalizado) -->
                                        <?php if($ag['status'] != 'Finalizado'): ?>
                                            <button onclick="gerenciarAgenda(<?= $ag['id'] ?>, 'finalizar')" class="btn-acao btn-finish" title="Finalizar Atendimento">
                                                <i class="fas fa-flag-checkered"></i>
                                            </button>
                                        <?php endif; ?>

                                        <!-- Editar -->
                                        <a href="agenda.php?id=<?= $ag['id'] ?>" class="btn-acao btn-edit" title="Editar Agendamento">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <!-- Excluir -->
                                        <button onclick="gerenciarAgenda(<?= $ag['id'] ?>, 'excluir')" class="btn-acao btn-del" title="Excluir Agendamento">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="fas fa-calendar-times"></i>
                                    <h3>Nenhum agendamento encontrado</h3>
                                    <p>Não há agendamentos que correspondam aos filtros aplicados.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINAÇÃO -->
        <?php if ($total_paginas > 1): ?>
            <div class="pagination-container">
                <ul class="pagination">
                    <li>
                        <a href="?pagina=1&busca=<?= urlencode($busca) ?>&data=<?= urlencode($data_filtro) ?>&status=<?= urlencode($filtro_status) ?>">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    </li>
                    
                    <?php 
                    $inicio = max(1, $pagina_atual - 2);
                    $fim = min($total_paginas, $pagina_atual + 2);
                    
                    for ($i = $inicio; $i <= $fim; $i++): 
                    ?>
                        <li class="<?= $pagina_atual == $i ? 'active' : '' ?>">
                            <a href="?pagina=<?= $i ?>&busca=<?= urlencode($busca) ?>&data=<?= urlencode($data_filtro) ?>&status=<?= urlencode($filtro_status) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <li>
                        <a href="?pagina=<?= $total_paginas ?>&busca=<?= urlencode($busca) ?>&data=<?= urlencode($data_filtro) ?>&status=<?= urlencode($filtro_status) ?>">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // ==========================================================
        // GERENCIAMENTO DE DROPDOWN
        // ==========================================================
        
        // Fechar dropdown ao clicar fora
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown-status')) {
                document.querySelectorAll('.dropdown-status-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });

        // Fechar dropdown ao scrollar
        window.addEventListener('scroll', function() {
            document.querySelectorAll('.dropdown-status-menu').forEach(menu => {
                menu.classList.remove('show');
            });
        });

        function toggleDropdown(event, id) {
            event.stopPropagation();
            
            const dropdown = document.getElementById('dropdown-' + id);
            const button = event.currentTarget;
            
            // Fechar todos os outros dropdowns
            document.querySelectorAll('.dropdown-status-menu').forEach(menu => {
                if (menu.id !== 'dropdown-' + id) {
                    menu.classList.remove('show');
                }
            });
            
            // Toggle dropdown atual
            const isOpening = !dropdown.classList.contains('show');
            
            if (isOpening) {
                // Calcular posição do botão
                const buttonRect = button.getBoundingClientRect();
                const windowHeight = window.innerHeight;
                const windowWidth = window.innerWidth;
                
                // Posicionar dropdown
                let top = buttonRect.bottom + 5;
                let left = buttonRect.left + (buttonRect.width / 2);
                
                // Se passar da altura da janela, abrir para cima
                if (top + 200 > windowHeight) {
                    top = buttonRect.top - 200 - 5;
                }
                
                // Ajustar se sair da lateral
                if (left + 100 > windowWidth) {
                    left = windowWidth - 110;
                }
                if (left - 100 < 0) {
                    left = 100;
                }
                
                dropdown.style.top = top + 'px';
                dropdown.style.left = left + 'px';
                dropdown.style.transform = 'translateX(-50%)';
                
                dropdown.classList.add('show');
            } else {
                dropdown.classList.remove('show');
            }
        }

        // ==========================================================
        // ALTERAR STATUS
        // ==========================================================
        
        async function alterarStatus(event, id, novoStatus) {
            event.stopPropagation();
            
            // Fechar dropdown
            const dropdown = document.getElementById('dropdown-' + id);
            if (dropdown) {
                dropdown.classList.remove('show');
            }
            
            const cores = {
                'Confirmado': { bg: '#e8f5e9', color: '#2e7d32', icon: 'success' },
                'Cancelado': { bg: '#ffebee', color: '#c62828', icon: 'error' },
                'Remarcado': { bg: '#fff3e0', color: '#ef6c00', icon: 'info' },
                'Sem Resposta': { bg: '#e1f5fe', color: '#0277bd', icon: 'warning' }
            };

            const result = await Swal.fire({
                title: 'Alterar Status?',
                text: `Status será alterado para: ${novoStatus}`,
                icon: cores[novoStatus].icon,
                showCancelButton: true,
                confirmButtonColor: cores[novoStatus].color,
                confirmButtonText: 'Sim, alterar',
                cancelButtonText: 'Cancelar',
                customClass: {
                    confirmButton: 'btn btn-primary',
                    cancelButton: 'btn btn-outline'
                }
            });

            if (result.isConfirmed) {
                
                Swal.fire({
                    title: 'Processando...',
                    html: 'Atualizando status do agendamento',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                try {
                    const formData = new FormData();
                    formData.append('acao', 'alterar_status');
                    formData.append('id', id);
                    formData.append('status', novoStatus);
                    
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.status === 'success') {
                        await Swal.fire({
                            title: 'Sucesso!',
                            text: data.message,
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        
                        location.reload();
                    } else {
                        Swal.fire({
                            title: 'Erro!',
                            text: data.message,
                            icon: 'error',
                            confirmButtonColor: '#131c71'
                        });
                    }
                } catch (error) {
                    Swal.fire({
                        title: 'Erro!',
                        text: 'Falha ao comunicar com o servidor',
                        icon: 'error',
                        confirmButtonColor: '#131c71'
                    });
                }
            }
        }

        // ==========================================================
        // FINALIZAR / EXCLUIR
        // ==========================================================
        
        async function gerenciarAgenda(id, acao) {
            let config = {
                finalizar: {
                    title: 'Finalizar Atendimento?',
                    text: 'O atendimento será marcado como finalizado',
                    icon: 'success',
                    color: '#131c71',
                    btnText: 'Sim, finalizar'
                },
                excluir: {
                    title: 'Excluir Agendamento?',
                    text: 'Esta ação não poderá ser desfeita',
                    icon: 'warning',
                    color: '#dc3545',
                    btnText: 'Sim, excluir'
                }
            };

            const result = await Swal.fire({
                title: config[acao].title,
                text: config[acao].text,
                icon: config[acao].icon,
                showCancelButton: true,
                confirmButtonColor: config[acao].color,
                confirmButtonText: config[acao].btnText,
                cancelButtonText: 'Cancelar',
                customClass: {
                    confirmButton: 'btn btn-primary',
                    cancelButton: 'btn btn-outline'
                }
            });

            if (result.isConfirmed) {
                
                Swal.fire({
                    title: 'Processando...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                try {
                    const formData = new FormData();
                    formData.append('acao', acao);
                    formData.append('id', id);
                    
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.status === 'success') {
                        await Swal.fire({
                            title: 'Sucesso!',
                            text: data.message,
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        
                        if (acao === 'excluir') {
                            document.getElementById('linha-' + id).style.opacity = '0';
                            setTimeout(() => {
                                document.getElementById('linha-' + id).remove();
                            }, 300);
                        } else {
                            location.reload();
                        }
                    } else {
                        Swal.fire({
                            title: 'Erro!',
                            text: data.message,
                            icon: 'error',
                            confirmButtonColor: '#131c71'
                        });
                    }
                } catch (error) {
                    Swal.fire({
                        title: 'Erro!',
                        text: 'Falha ao processar requisição',
                        icon: 'error',
                        confirmButtonColor: '#131c71'
                    });
                }
            }
        }
    </script>
</body>
</html>