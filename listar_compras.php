<?php
/**
 * =========================================================================================
 * ZIIPVET - LISTAGEM DE COMPRAS
 * ARQUIVO: listar_compras.php
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
// LÓGICA DE EXCLUSÃO (AJAX)
// ==========================================================
if (isset($_POST['acao']) && $_POST['acao'] === 'excluir') {
    header('Content-Type: application/json');
    ob_clean();
    
    $id_compra = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    try {
        $pdo->beginTransaction();
        
        // Remove itens
        $stmt_itens = $pdo->prepare("DELETE FROM compras_itens WHERE id_compra = ?");
        $stmt_itens->execute([$id_compra]);
        
        // Remove compra
        $stmt = $pdo->prepare("DELETE FROM compras WHERE id = ? AND id_admin = ?");
        $stmt->execute([$id_compra, $id_admin]);
        
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Compra removida com sucesso!']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Erro ao remover: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// CONFIGURAÇÃO DA PÁGINA
// ==========================================================
$titulo_pagina = "Listagem de Compras";
$filtro_busca = isset($_GET['busca']) ? $_GET['busca'] : '';
$filtro_periodo = isset($_GET['periodo']) ? $_GET['periodo'] : '';

$pagina_atual = (isset($_GET['pagina']) && is_numeric($_GET['pagina'])) ? (int)$_GET['pagina'] : 1;
$itens_per_page = 20;
$offset = ($pagina_atual - 1) * $itens_per_page;

try {
    // Construir WHERE
    $where = "WHERE c.id_admin = :id_admin";
    $params = [':id_admin' => $id_admin];
    
    // Filtro de busca
    if (!empty($filtro_busca)) {
        $where .= " AND (f.nome_fantasia LIKE :busca OR f.razao_social LIKE :busca OR f.nome_completo LIKE :busca OR c.nf_numero LIKE :busca)";
        $params[':busca'] = "%$filtro_busca%";
    }
    
    // Filtro de período
    if (!empty($filtro_periodo) && strpos($filtro_periodo, '-') !== false) {
        $datas = explode(' - ', $filtro_periodo);
        if(count($datas) == 2) {
            $dt_inicio = DateTime::createFromFormat('d/m/Y', trim($datas[0]));
            $dt_fim = DateTime::createFromFormat('d/m/Y', trim($datas[1]));
            
            if($dt_inicio && $dt_fim){
                $data_inicio = $dt_inicio->format('Y-m-d') . ' 00:00:00';
                $data_fim = $dt_fim->format('Y-m-d') . ' 23:59:59';
                $where .= " AND c.data_cadastro BETWEEN :data_inicio AND :data_fim";
                $params[':data_inicio'] = $data_inicio;
                $params[':data_fim'] = $data_fim;
            }
        }
    }
    
    // Contagem total
    $sqlCount = "SELECT COUNT(*) as total 
                 FROM compras c
                 LEFT JOIN fornecedores f ON c.id_fornecedor = f.id 
                 $where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $resCount = $stmtCount->fetch(PDO::FETCH_ASSOC);
    $total_registros = $resCount['total'];
    $total_paginas = ceil($total_registros / $itens_per_page);

    // Query principal
    $sql = "SELECT c.*, 
                   COALESCE(f.nome_fantasia, f.razao_social, f.nome_completo) as fornecedor_nome
            FROM compras c
            LEFT JOIN fornecedores f ON c.id_fornecedor = f.id
            $where
            ORDER BY c.data_cadastro DESC 
            LIMIT " . (int)$offset . ", " . (int)$itens_per_page;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $compras = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao buscar compras: " . $e->getMessage());
}

function gerar_link($pg) {
    global $filtro_busca, $filtro_periodo;
    return "listar_compras.php?pagina=" . $pg . "&busca=" . urlencode($filtro_busca) . "&periodo=" . urlencode($filtro_periodo);
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    
    <style>
        /* ========================================
           ESTILOS ESPECÍFICOS PARA LISTAGEM
        ======================================== */
        
        /* Container de Listagem */
        .list-container {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }
        
        /* Área de Filtros e Ações */
        .filters-actions-box {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 15px;
            align-items: flex-end;
            margin-bottom: 15px;
        }
        
        .filter-group {
            position: relative;
        }
        
        .filter-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #6c757d;
            margin-bottom: 8px;
            text-transform: uppercase;
            font-family: 'Exo', sans-serif;
        }
        
        .filter-group label i {
            margin-right: 5px;
        }
        
        .input-with-icon {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .input-with-icon input {
            padding-right: 40px;
        }
        
        .input-icon {
            position: absolute;
            right: 12px;
            color: #6c757d;
            font-size: 16px;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .input-icon:hover {
            color: #131c71;
        }
        
        .btn-filter {
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
        
        .btn-filter:hover {
            background: #4a1d75;
            transform: translateY(-2px);
        }
        
        /* Botões de Ação */
        .actions-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .btn-action-header {
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
            background: #28A745;
            color: #fff;
        }
        
        .btn-import:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(23, 162, 184, 0.3);
        }
        
        .btn-new {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: #fff;
        }
        
        .btn-new:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        
        .btn-print {
            background: #f8f9fa;
            color: #495057;
            border: 2px solid #e0e0e0;
        }
        
        .btn-print:hover {
            background: #e9ecef;
            border-color: #ced4da;
        }
        
        /* Wrapper da Tabela */
        .table-wrapper {
            overflow-x: auto;
        }
        
        /* Tabela Moderna */
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
        
        /* Código da Compra */
        .compra-code {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #28A745; /*QUADRADO DA COMPRA OU CODIGO */
            color: #fff;
            width: 45px;
            height: 45px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 16px;
        }
        
        /* Informações do Fornecedor */
        .fornecedor-name {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 4px;
        }
        
        /* Badge da NF */
        .nf-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #e3f2fd;
            color: #1565c0;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .nf-badge i {
            font-size: 12px;
        }
        
        /* Valor Destacado */
        .valor-total {
            font-size: 18px;
            font-weight: 700;
            color: #28A745;
            font-family: 'Exo', sans-serif;
        }
        
        /* Botões de Ação na Tabela */
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        
        .btn-action {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }
        
        .btn-action::before {
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
        
        .btn-action:hover::before {
            width: 100%;
            height: 100%;
        }
        
        .btn-action i {
            position: relative;
            z-index: 1;
        }
        
        .btn-action.view {
            background: #28A745;
            color: #fff;
        }
        
        .btn-action.delete {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: #fff;
        }
        
        .btn-action:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        /* Paginação */
        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #f8f9fa;
            border-top: 2px solid #e0e0e0;
        }
        
        .page-info {
            font-size: 14px;
            color: #6c757d;
            font-weight: 600;
            font-family: 'Exo', sans-serif;
        }
        
        .page-nav {
            display: flex;
            gap: 8px;
        }
        
        .page-link {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            color: #131c71;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            font-family: 'Exo', sans-serif;
        }
        
        .page-link:hover:not(.disabled) {
            background: #131c71;
            color: #fff;
            border-color: #131c71;
            transform: translateY(-2px);
        }
        
        .page-link.disabled {
            opacity: 0.4;
            cursor: not-allowed;
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
        
        .modal-dialog {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            width: 90%;
            max-width: 800px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translate(-50%, -45%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }
        
        .modal-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, #131c71 0%, #4a1d75 100%);
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 22px;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: #fff;
            font-size: 28px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .modal-close:hover {
            transform: rotate(90deg);
        }
        
        .modal-body {
            width: 100%;
            height: 500px;
            border: none;
        }
        
        /* Responsividade */
        @media (max-width: 1200px) {
            .filters-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .btn-filter {
                grid-column: 1 / -1;
            }
        }
        
        @media (max-width: 768px) {
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .actions-row {
                flex-direction: column;
            }
            
            .btn-action-header {
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
        
        <!-- HEADER: Título e Botão -->
        <div class="form-header">
            <h1 class="form-title">
                <i class="fas fa-shopping-cart"></i>
                Listagem de Compras
                <span style="font-size: 14px; color: #6c757d; font-weight: 400; margin-left: 10px;">
                    (<?= $total_registros ?> <?= $total_registros == 1 ? 'registro' : 'registros' ?>)
                </span>
            </h1>
            
            <a href="compras.php" class="btn-voltar" onclick="sessionStorage.removeItem('xml_import_data');">
                <i class="fas fa-plus"></i>
                Nova Compra
            </a>
        </div>

        <!-- CONTAINER DA LISTAGEM -->
        <div class="list-container">
            
            <!-- FILTROS E AÇÕES -->
            <div class="filters-actions-box">
                <!-- Filtros -->
                <form method="GET" id="formFiltro" class="filters-grid">
                    <div class="filter-group">
                        <label>
                            <i class="fas fa-calendar-alt"></i>
                            Período
                        </label>
                        <div class="input-with-icon">
                            <input type="text" 
                                   name="periodo" 
                                   id="rangeDate" 
                                   class="form-control" 
                                   placeholder="Selecione o período..."
                                   value="<?= htmlspecialchars($filtro_periodo) ?>">
                            <?php if(!empty($filtro_periodo)): ?>
                                <i class="fas fa-times input-icon" onclick="limparPeriodo()" title="Limpar Filtro"></i>
                            <?php else: ?>
                                <i class="fas fa-calendar input-icon"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label>
                            <i class="fas fa-search"></i>
                            Pesquisar
                        </label>
                        <div class="input-with-icon">
                            <input type="text" 
                                   name="busca" 
                                   class="form-control" 
                                   placeholder="Fornecedor ou NF..."
                                   value="<?= htmlspecialchars($filtro_busca) ?>">
                            <i class="fas fa-search input-icon" onclick="document.getElementById('formFiltro').submit()"></i>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-filter"></i>
                        Filtrar
                    </button>
                </form>
                
                <!-- Ações -->
                <div class="actions-row">
                    <button type="button" class="btn-action-header btn-import" onclick="abrirModalImport()">
                        <i class="fas fa-file-import"></i>
                        Importar CSV
                    </button>
                    
                    <input type="file" id="xml_upload" style="display:none" accept=".xml">
                    <button type="button" class="btn-action-header btn-import" onclick="document.getElementById('xml_upload').click()">
                        <i class="fas fa-file-code"></i>
                        Importar XML
                    </button>
                    
                    <button class="btn-action-header btn-print" onclick="window.print()">
                        <i class="fas fa-print"></i>
                        Imprimir
                    </button>
                </div>
            </div>

            <!-- TABELA -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th width="60"><i class="fas fa-hashtag"></i> Cód</th>
                            <th width="120"><i class="fas fa-calendar"></i> Data Entrada</th>
                            <th><i class="fas fa-truck"></i> Fornecedor</th>
                            <th width="150"><i class="fas fa-file-invoice"></i> Nota Fiscal</th>
                            <th width="120"><i class="fas fa-calendar-check"></i> Emissão NF</th>
                            <th width="140"><i class="fas fa-dollar-sign"></i> Valor Total</th>
                            <th width="100" style="text-align: center;"><i class="fas fa-cogs"></i> Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($compras) > 0): ?>
                            <?php foreach($compras as $c): ?>
                            <tr id="row-<?= $c['id'] ?>">
                                <td>
                                    <div class="compra-code"><?= $c['id'] ?></div>
                                </td>
                                <td>
                                    <?= date('d/m/Y', strtotime($c['data_cadastro'])) ?>
                                </td>
                                <td>
                                    <div class="fornecedor-name">
                                        <?= !empty($c['fornecedor_nome']) ? htmlspecialchars($c['fornecedor_nome']) : 'SEM FORNECEDOR' ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if(!empty($c['nf_numero'])): ?>
                                        <span class="nf-badge">
                                            <i class="fas fa-receipt"></i>
                                            NF <?= htmlspecialchars($c['nf_numero']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #adb5bd; font-style: italic;">Sem NF</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= !empty($c['data_emissao']) ? date('d/m/Y', strtotime($c['data_emissao'])) : '-' ?>
                                </td>
                                <td>
                                    <span class="valor-total">
                                        R$ <?= number_format((float)$c['valor_total'], 2, ',', '.') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-action view" 
                                                onclick="verDetalhes(<?= $c['id'] ?>)" 
                                                title="Ver Detalhes">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn-action delete" 
                                                onclick="excluirCompra(<?= $c['id'] ?>, '<?= htmlspecialchars($c['fornecedor_nome'] ?? 'Compra') ?>')" 
                                                title="Excluir">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <h3>Nenhuma compra encontrada</h3>
                                        <p>Não há compras registradas com os filtros aplicados.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINAÇÃO -->
            <?php if($total_paginas > 1): ?>
            <div class="pagination-wrapper">
                <div class="page-info">
                    Mostrando <?= count($compras) ?> de <?= $total_registros ?> registros
                </div>
                <div class="page-nav">
                    <a href="<?= gerar_link(max(1, $pagina_atual-1)) ?>" 
                       class="page-link <?= $pagina_atual == 1 ? 'disabled' : '' ?>"
                       title="Página Anterior">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    
                    <a href="<?= gerar_link(min($total_paginas, $pagina_atual+1)) ?>" 
                       class="page-link <?= $pagina_atual == $total_paginas ? 'disabled' : '' ?>"
                       title="Próxima Página">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
    </main>

    <!-- MODAL DE IMPORTAÇÃO CSV -->
    <div id="modalImportCompras" class="modal-overlay">
        <div class="modal-dialog">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-file-import"></i>
                    Importar Compras via CSV
                </div>
                <button onclick="fecharModalImport()" class="modal-close">&times;</button>
            </div>
            <iframe src="financeiro/importar_compras.php" class="modal-body"></iframe>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/pt.js"></script>
    
    <script>
        // ==========================================================
        // CONFIGURAÇÃO DO CALENDÁRIO
        // ==========================================================
        flatpickr("#rangeDate", {
            mode: "range",
            dateFormat: "d/m/Y",
            locale: "pt",
            onClose: function(selectedDates, dateStr, instance) {
                if(dateStr.includes(' to ')) { 
                    instance.input.value = dateStr.replace(' to ', ' - ');
                    document.getElementById('formFiltro').submit();
                }
            }
        });

        // ==========================================================
        // LIMPAR FILTRO DE PERÍODO
        // ==========================================================
        function limparPeriodo() {
            const url = new URL(window.location.href);
            url.searchParams.delete('periodo');
            window.location.href = url.toString();
        }

        // ==========================================================
        // MODAL DE IMPORTAÇÃO
        // ==========================================================
        function abrirModalImport() {
            document.getElementById('modalImportCompras').style.display = 'block';
        }
        
        function fecharModalImport() {
            document.getElementById('modalImportCompras').style.display = 'none';
            window.location.reload();
        }
        
        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            const modal = document.getElementById('modalImportCompras');
            if (event.target == modal) {
                fecharModalImport();
            }
        }

        // ==========================================================
        // VER DETALHES
        // ==========================================================
        function verDetalhes(id) {
            Swal.fire({
                icon: 'info',
                title: 'Em Desenvolvimento',
                text: 'Funcionalidade de visualização de detalhes em desenvolvimento.',
                confirmButtonColor: '#131c71'
            });
        }

        // ==========================================================
        // EXCLUIR COMPRA
        // ==========================================================
        async function excluirCompra(id, nome) {
            const result = await Swal.fire({
                title: 'Deseja excluir esta compra?',
                html: `<strong>${nome}</strong><br><br>Todos os itens da compra também serão removidos.<br>Esta ação não pode ser desfeita.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar',
                customClass: {
                    confirmButton: 'btn btn-primary',
                    cancelButton: 'btn btn-outline'
                }
            });

            if (result.isConfirmed) {
                
                Swal.fire({
                    title: 'Processando...',
                    html: 'Excluindo compra e itens',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                try {
                    const formData = new FormData();
                    formData.append('acao', 'excluir');
                    formData.append('id', id);
                    
                    const response = await fetch('listar_compras.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.status === 'success') {
                        await Swal.fire({
                            title: 'Excluído!',
                            text: data.message,
                            icon: 'success',
                            confirmButtonColor: '#131c71',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        
                        // Remover linha com animação
                        const linha = document.getElementById('row-' + id);
                        linha.style.opacity = '0';
                        linha.style.transform = 'translateX(-20px)';
                        
                        setTimeout(() => {
                            linha.remove();
                            
                            // Se não houver mais registros, recarregar página
                            const tbody = document.querySelector('tbody');
                            if (tbody.children.length === 0) {
                                location.reload();
                            }
                        }, 300);
                        
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

        // ==========================================================
        // IMPORTAÇÃO DE XML
        // ==========================================================
        document.getElementById('xml_upload').addEventListener('change', async function(e) {
            if (e.target.files.length === 0) return;
            
            const arquivo = e.target.files[0];
            
            if (!arquivo.name.toLowerCase().endsWith('.xml')) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Arquivo Inválido',
                    text: 'Por favor, selecione um arquivo XML válido.',
                    confirmButtonColor: '#131c71'
                });
                e.target.value = '';
                return;
            }
            
            if (arquivo.size > 5 * 1024 * 1024) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Arquivo Muito Grande',
                    text: 'Tamanho máximo permitido: 5MB',
                    confirmButtonColor: '#131c71'
                });
                e.target.value = '';
                return;
            }
            
            Swal.fire({
                title: 'Processando XML...',
                html: 'Aguarde enquanto extraímos os dados da nota fiscal',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            const formData = new FormData();
            formData.append('xml_file', arquivo);
            
            try {
                const response = await fetch('importar_xml.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    sessionStorage.setItem('xml_import_data', JSON.stringify(data.dados));
                    
                    const result = await Swal.fire({
                        icon: 'success',
                        title: 'XML Importado com Sucesso!',
                        html: `
                            <div style="text-align: left; margin-top: 20px;">
                                <p><strong>Fornecedor:</strong> ${data.dados.fornecedor.nome_fantasia}</p>
                                <p><strong>CNPJ:</strong> ${data.dados.fornecedor.cnpj}</p>
                                <p><strong>Nota Fiscal:</strong> ${data.dados.compra.nf_numero} (Série: ${data.dados.compra.nf_serie})</p>
                                <p><strong>Valor Total:</strong> R$ ${data.dados.compra.valor_total.toFixed(2).replace('.', ',')}</p>
                                <p><strong>Total de Itens:</strong> ${data.dados.info_adicional.total_itens}</p>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonColor: '#131c71',
                        confirmButtonText: 'Prosseguir com Cadastro',
                        cancelButtonText: 'Cancelar'
                    });
                    
                    if (result.isConfirmed) {
                        window.location.href = 'compras.php';
                    } else {
                        sessionStorage.removeItem('xml_import_data');
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro ao Importar XML',
                        text: data.message,
                        confirmButtonColor: '#131c71'
                    });
                }
                
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro de Conexão',
                    text: 'Não foi possível processar o XML: ' + error.message,
                    confirmButtonColor: '#131c71'
                });
            }
            
            e.target.value = '';
        });
    </script>
</body>
</html>