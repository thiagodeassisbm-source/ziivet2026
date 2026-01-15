<?php
/**
 * =========================================================================================
 * ZIIPVET - LISTAGEM DE FORNECEDORES
 * ARQUIVO: listar_fornecedores.php
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
    
    $id_excluir = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if (!$id_excluir) {
        echo json_encode(['status' => 'error', 'message' => 'ID não fornecido.']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM fornecedores WHERE id = :id AND id_admin = :id_admin");
        $stmt->execute([':id' => $id_excluir, ':id_admin' => $id_admin]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Fornecedor removido com sucesso!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Fornecedor não encontrado ou já foi removido.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao excluir: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// CONFIGURAÇÃO DA PÁGINA
// ==========================================================
$titulo_pagina = "Listagem de Fornecedores";
$filtro_busca = isset($_GET['busca']) ? $_GET['busca'] : '';
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';

$pagina_atual = (isset($_GET['pagina']) && is_numeric($_GET['pagina'])) ? (int)$_GET['pagina'] : 1;
$itens_per_page = 20;
$offset = ($pagina_atual - 1) * $itens_per_page;

try {
    // Construir WHERE
    $where = "WHERE id_admin = :id_admin";
    $params = [':id_admin' => $id_admin];
    
    // Filtro de busca
    if (!empty($filtro_busca)) {
        $where .= " AND (nome_completo LIKE :busca OR razao_social LIKE :busca OR nome_fantasia LIKE :busca OR cnpj LIKE :busca OR cpf LIKE :busca)";
        $params[':busca'] = "%$filtro_busca%";
    }
    
    // Filtro de tipo
    if (!empty($filtro_tipo)) {
        $where .= " AND tipo_fornecedor = :tipo";
        $params[':tipo'] = $filtro_tipo;
    }
    
    // Contagem total
    $sqlCount = "SELECT COUNT(*) as total FROM fornecedores $where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $resCount = $stmtCount->fetch(PDO::FETCH_ASSOC);
    $total_registros = $resCount['total'];
    $total_paginas = ceil($total_registros / $itens_per_page);

    // Query principal
    $sql = "SELECT * FROM fornecedores 
            $where
            ORDER BY data_cadastro DESC 
            LIMIT " . (int)$offset . ", " . (int)$itens_per_page;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $fornecedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao buscar fornecedores: " . $e->getMessage());
}

function gerar_link($pg) {
    global $filtro_busca, $filtro_tipo;
    return "listar_fornecedores.php?pagina=" . $pg . "&busca=" . urlencode($filtro_busca) . "&tipo=" . urlencode($filtro_tipo);
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
            grid-template-columns: 2fr 1fr auto;
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
            color: #622599;
        }
        
        .btn-filter {
            height: 45px;
            padding: 0 24px;
            background: #622599;
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
            background: linear-gradient(135deg, #28A745 0%, #28A745 100%);
            color: #fff;
        }
        
        .btn-import:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(23, 162, 184, 0.3);
        }
        
        .btn-print {
            background: #f8f9fa;
            color: #495057;
            border: 2px solid #e0e0e0;
            width: 48px;
            justify-content: center;
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
        
        /* Informações do Fornecedor */
        .fornecedor-name {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 4px;
        }
        
        .fornecedor-fantasia {
            font-size: 13px;
            color: #6c757d;
        }
        
        /* Badge de Tipo */
        .badge-type {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            font-family: 'Exo', sans-serif;
        }
        
        .badge-produtos {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1565c0;
        }
        
        .badge-servicos {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            color: #ef6c00;
        }
        
        .badge-type i {
            font-size: 11px;
        }
        
        /* Informações de Contato */
        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .contact-item i {
            color: #6c757d;
            width: 14px;
            text-align: center;
        }
        
        /* Status */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        
        .status-ativo {
            color: #28a745;
        }
        
        .status-ativo .status-dot {
            background: #28a745;
        }
        
        .status-inativo {
            color: #b92426;
        }
        
        .status-inativo .status-dot {
            background: #b92426;
        }
        
        /* Botões de Ação na Tabela */
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
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
        
        .btn-action.edit {
            background: linear-gradient(135deg, #28A745 0%, #28A745 100%);
            color: #fff;
        }
        
        .btn-action.delete {
            background: linear-gradient(135deg, #b92426 0%, #b92426 100%);
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
            background: linear-gradient(135deg, #131c71 0%, #131c71 100%);
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
                <i class="fas fa-truck"></i>
                Listagem de Fornecedores
                <span style="font-size: 14px; color: #6c757d; font-weight: 400; margin-left: 10px;">
                    (<?= $total_registros ?> <?= $total_registros == 1 ? 'registro' : 'registros' ?>)
                </span>
            </h1>
            
            <a href="fornecedores.php" class="btn-voltar">
                <i class="fas fa-plus"></i>
                Novo Fornecedor
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
                            <i class="fas fa-search"></i>
                            Pesquisar
                        </label>
                        <div class="input-with-icon">
                            <input type="text" 
                                   name="busca" 
                                   class="form-control" 
                                   placeholder="Nome, CPF/CNPJ ou Fantasia..."
                                   value="<?= htmlspecialchars($filtro_busca) ?>">
                            <i class="fas fa-search input-icon" onclick="document.getElementById('formFiltro').submit()"></i>
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label>
                            <i class="fas fa-filter"></i>
                            Tipo de Fornecedor
                        </label>
                        <select name="tipo" class="form-control" onchange="document.getElementById('formFiltro').submit()">
                            <option value="">Todos os Tipos</option>
                            <option value="Produtos e/ou serviços" <?= $filtro_tipo === 'Produtos e/ou serviços' ? 'selected' : '' ?>>Produtos/Serviços</option>
                            <option value="Apenas serviços" <?= $filtro_tipo === 'Apenas serviços' ? 'selected' : '' ?>>Apenas Serviços</option>
                        </select>
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
                    
                    <button class="btn-action-header btn-print" onclick="window.print()">
                        <i class="fas fa-print"></i>
                    </button>
                </div>
            </div>

            <!-- TABELA -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-building"></i> Nome / Razão Social</th>
                            <th width="140"><i class="fas fa-id-card"></i> Documento</th>
                            <th width="140"><i class="fas fa-tags"></i> Tipo</th>
                            <th width="220"><i class="fas fa-phone"></i> Contato</th>
                            <th width="100"><i class="fas fa-toggle-on"></i> Status</th>
                            <th width="100" style="text-align: right;"><i class="fas fa-cogs"></i> Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($fornecedores) > 0): ?>
                            <?php foreach($fornecedores as $f): 
                                $nome_exibir = ($f['tipo_pessoa'] === 'Fisica') 
                                    ? ($f['nome_completo'] ?? 'Sem nome') 
                                    : ($f['razao_social'] ?? 'Sem razão social');
                                $doc_exibir = ($f['tipo_pessoa'] === 'Fisica') 
                                    ? ($f['cpf'] ?? '-') 
                                    : ($f['cnpj'] ?? '-');
                                $tipo_badge_class = ($f['tipo_fornecedor'] === 'Apenas serviços') 
                                    ? 'badge-servicos' 
                                    : 'badge-produtos';
                                $tipo_badge_text = ($f['tipo_fornecedor'] === 'Apenas serviços') 
                                    ? 'Serviços' 
                                    : 'Prod/Serv';
                                $tipo_badge_icon = ($f['tipo_fornecedor'] === 'Apenas serviços') 
                                    ? 'fa-concierge-bell' 
                                    : 'fa-box';
                            ?>
                            <tr id="row-<?= $f['id'] ?>">
                                <td>
                                    <div class="fornecedor-name">
                                        <?= htmlspecialchars($nome_exibir) ?>
                                    </div>
                                    <?php if(!empty($f['nome_fantasia'])): ?>
                                        <div class="fornecedor-fantasia">
                                            <?= htmlspecialchars($f['nome_fantasia']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($doc_exibir) ?></td>
                                <td>
                                    <span class="badge-type <?= $tipo_badge_class ?>">
                                        <i class="fas <?= $tipo_badge_icon ?>"></i>
                                        <?= $tipo_badge_text ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="contact-info">
                                        <?php if(!empty($f['telefone1'])): ?>
                                            <div class="contact-item">
                                                <i class="fas fa-phone"></i>
                                                <span><?= htmlspecialchars($f['telefone1']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if(!empty($f['email'])): ?>
                                            <div class="contact-item">
                                                <i class="fas fa-envelope"></i>
                                                <span><?= htmlspecialchars($f['email']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if(empty($f['telefone1']) && empty($f['email'])): ?>
                                            <span style="color: #adb5bd; font-style: italic;">Sem contato</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if($f['status'] === 'ATIVO'): ?>
                                        <span class="status-badge status-ativo">
                                            <span class="status-dot"></span>
                                            Ativo
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-inativo">
                                            <span class="status-dot"></span>
                                            Inativo
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="fornecedores.php?id=<?= $f['id'] ?>" 
                                           class="btn-action edit" 
                                           title="Editar Fornecedor">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="excluirFornecedor(<?= $f['id'] ?>, '<?= htmlspecialchars($nome_exibir) ?>')" 
                                                class="btn-action delete" 
                                                title="Excluir Fornecedor">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="fas fa-truck-loading"></i>
                                        <h3>Nenhum fornecedor encontrado</h3>
                                        <p>
                                            <?php if(!empty($filtro_busca) || !empty($filtro_tipo)): ?>
                                                Não há fornecedores que correspondam aos filtros aplicados.
                                            <?php else: ?>
                                                Nenhum fornecedor cadastrado ainda.
                                            <?php endif; ?>
                                        </p>
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
                    Mostrando <?= count($fornecedores) ?> de <?= $total_registros ?> registros
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
    <div id="modalImport" class="modal-overlay">
        <div class="modal-dialog">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-file-import"></i>
                    Importar Fornecedores via CSV
                </div>
                <button onclick="fecharModalImport()" class="modal-close">&times;</button>
            </div>
            <iframe src="financeiro/importar_fornecedores.php" class="modal-body"></iframe>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // ==========================================================
        // MODAL DE IMPORTAÇÃO
        // ==========================================================
        function abrirModalImport() {
            document.getElementById('modalImport').style.display = 'block';
        }
        
        function fecharModalImport() {
            document.getElementById('modalImport').style.display = 'none';
            window.location.reload();
        }
        
        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            const modal = document.getElementById('modalImport');
            if (event.target == modal) {
                fecharModalImport();
            }
        }

        // ==========================================================
        // EXCLUIR FORNECEDOR
        // ==========================================================
        async function excluirFornecedor(id, nome) {
            const result = await Swal.fire({
                title: 'Deseja excluir este fornecedor?',
                html: `<strong>${nome}</strong> será removido permanentemente.<br><br>Esta ação não pode ser desfeita.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#b92426',
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
                    html: 'Excluindo fornecedor',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                try {
                    const formData = new FormData();
                    formData.append('acao', 'excluir');
                    formData.append('id', id);
                    
                    const response = await fetch('listar_fornecedores.php', {
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
                        linha.style.transition = 'all 0.3s ease';
                        
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
                        text: 'Falha ao processar requisição: ' + error.message,
                        icon: 'error',
                        confirmButtonColor: '#131c71'
                    });
                }
            }
        }
    </script>
</body>
</html>