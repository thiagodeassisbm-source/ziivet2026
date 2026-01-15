<?php
/**
 * =========================================================================================
 * ZIIPVET - LISTAGEM DE PRODUTOS E SERVIÇOS
 * ARQUIVO: listar_produtos.php
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excluir') {
    header('Content-Type: application/json');
    ob_clean();
    
    $id_excluir = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM produtos WHERE id = ? AND id_admin = ?");
        $stmt->execute([$id_excluir, $id_admin]);
        echo json_encode(['status' => 'success', 'message' => 'Item removido com sucesso!']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao excluir: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// CONFIGURAÇÃO DA PÁGINA
// ==========================================================
$titulo_pagina = "Produtos e Serviços";
$filtro_busca = isset($_GET['busca']) ? $_GET['busca'] : '';
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';

$pagina_atual = (isset($_GET['pagina']) && is_numeric($_GET['pagina'])) ? (int)$_GET['pagina'] : 1;
$itens_per_page = 20;
$offset = ($pagina_atual - 1) * $itens_per_page;

try {
    // Construir WHERE
    $where = "WHERE p.id_admin = :id_admin";
    $params = [':id_admin' => $id_admin];
    
    if (!empty($filtro_busca)) {
        $where .= " AND (p.nome LIKE :busca OR p.sku LIKE :busca OR p.gtin LIKE :busca)";
        $params[':busca'] = "%$filtro_busca%";
    }
    
    if (!empty($filtro_tipo)) {
        $where .= " AND p.tipo = :tipo";
        $params[':tipo'] = $filtro_tipo;
    }
    
    // Contagem total
    $sqlCount = "SELECT COUNT(*) as total FROM produtos p $where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $resCount = $stmtCount->fetch(PDO::FETCH_ASSOC);
    $total_registros = $resCount['total'];
    $total_paginas = ceil($total_registros / $itens_per_page);

    // Query principal
    $sql = "SELECT p.*, c.nome_categoria, m.nome_marca 
            FROM produtos p
            LEFT JOIN categorias_produtos c ON p.id_categoria = c.id
            LEFT JOIN marcas m ON p.marca = m.id
            $where
            ORDER BY p.nome ASC 
            LIMIT " . (int)$offset . ", " . (int)$itens_per_page;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao buscar produtos: " . $e->getMessage());
}

function gerar_link($pg) {
    global $filtro_busca, $filtro_tipo;
    return "listar_produtos.php?pagina=" . $pg . "&busca=" . urlencode($filtro_busca) . "&tipo=" . urlencode($filtro_tipo);
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
        
        /* Área de Filtros */
        .filters-box {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .filters-form {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 15px;
            align-items: flex-end;
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
        
        .btn-filter {
            height: 45px;
            padding: 0 24px;
            background: #28A745;
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
        
        /* Informações do Produto */
        .product-name {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 4px;
            display: block;
        }
        
        .product-sku {
            font-size: 13px;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .product-sku i {
            font-size: 11px;
        }
        
        /* Badges de Tipo */
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
        
        .badge-produto {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1565c0;
        }
        
        .badge-servico {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            color: #ef6c00;
        }
        
        .badge-type i {
            font-size: 11px;
        }
        
        /* Preço */
        .product-price {
            font-size: 18px;
            font-weight: 700;
            color: #28A745;
            font-family: 'Exo', sans-serif;
        }
        
        /* Status */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .status-badge i {
            font-size: 10px;
        }
        
        .status-ativo {
            color: #28a745;
        }
        
        .status-inativo {
            color: #b92426;
        }
        
        /* Botões de Ação */
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
        
        .btn-action.edit {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
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
        
        /* Botão de Importação */
        .btn-import {
            background: linear-gradient(135deg, #28A745 0%, #28A745 100%);
            color: #fff;
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
        }
        
        .btn-import:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(111, 66, 193, 0.3);
        }
        
        /* Responsividade */
        @media (max-width: 1200px) {
            .filters-form {
                grid-template-columns: 1fr 1fr;
            }
            
            .btn-filter {
                grid-column: 1 / -1;
            }
        }
        
        @media (max-width: 768px) {
            .filters-form {
                grid-template-columns: 1fr;
            }
            
            .form-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .btn-voltar, .btn-import {
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
        
        <!-- HEADER: Título e Botões -->
        <div class="form-header">
            <h1 class="form-title">
                <i class="fas fa-box-open"></i>
                Produtos e Serviços
            </h1>
            
            <div style="display: flex; gap: 12px;">
                <input type="file" id="upload_csv" style="display:none" accept=".csv">
                <button type="button" class="btn-import" onclick="document.getElementById('upload_csv').click()">
                    <i class="fas fa-file-import"></i>
                    Importar CSV
                </button>
                <a href="produtos.php" class="btn-voltar">
                    <i class="fas fa-plus"></i>
                    Novo Item
                </a>
            </div>
        </div>

        <!-- CONTAINER DA LISTAGEM -->
        <div class="list-container">
            
            <!-- FILTROS -->
            <div class="filters-box">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <label>
                            <i class="fas fa-search"></i>
                            Pesquisar
                        </label>
                        <input type="text" 
                               name="busca" 
                               class="form-control" 
                               value="<?= htmlspecialchars($filtro_busca) ?>" 
                               placeholder="Nome, SKU ou código de barras...">
                    </div>
                    
                    <div class="filter-group">
                        <label>
                            <i class="fas fa-filter"></i>
                            Tipo
                        </label>
                        <select name="tipo" class="form-control">
                            <option value="">Todos os Tipos</option>
                            <option value="Produto" <?= $filtro_tipo === 'Produto' ? 'selected' : '' ?>>Produto</option>
                            <option value="Servico" <?= $filtro_tipo === 'Servico' ? 'selected' : '' ?>>Serviço</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-filter"></i>
                        Filtrar
                    </button>
                </form>
            </div>

            <!-- TABELA -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th width="40%"><i class="fas fa-tag"></i> Nome / SKU</th>
                            <th width="15%"><i class="fas fa-layer-group"></i> Tipo</th>
                            <th width="15%"><i class="fas fa-folder"></i> Categoria</th>
                            <th width="12%"><i class="fas fa-dollar-sign"></i> Preço</th>
                            <th width="10%"><i class="fas fa-toggle-on"></i> Status</th>
                            <th width="8%" style="text-align: center;"><i class="fas fa-cogs"></i> Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($produtos) > 0): ?>
                            <?php foreach($produtos as $p): ?>
                            <tr id="linha-<?= $p['id'] ?>">
                                <td>
                                    <span class="product-name"><?= htmlspecialchars($p['nome']) ?></span>
                                    <span class="product-sku">
                                        <i class="fas fa-barcode"></i>
                                        SKU: <?= htmlspecialchars($p['sku'] ?: 'N/I') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($p['tipo'] === 'Servico'): ?>
                                        <span class="badge-type badge-servico">
                                            <i class="fas fa-concierge-bell"></i>
                                            Serviço
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-type badge-produto">
                                            <i class="fas fa-box"></i>
                                            Produto
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($p['nome_categoria'] ?? 'Sem Categoria') ?></td>
                                <td>
                                    <span class="product-price">R$ <?= number_format($p['preco_venda'], 2, ',', '.') ?></span>
                                </td>
                                <td>
                                    <?php if($p['status'] === 'ATIVO'): ?>
                                        <span class="status-badge status-ativo">
                                            <i class="fas fa-circle"></i>
                                            Ativo
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-inativo">
                                            <i class="fas fa-circle"></i>
                                            Inativo
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="produtos.php?id=<?= $p['id'] ?>" 
                                           class="btn-action edit" 
                                           title="Editar Item">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="excluirProduto(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nome']) ?>')" 
                                                class="btn-action delete" 
                                                title="Excluir Item">
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
                                        <i class="fas fa-box-open"></i>
                                        <h3>Nenhum produto encontrado</h3>
                                        <p>Não há produtos ou serviços que correspondam aos filtros aplicados.</p>
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
                    Mostrando <?= count($produtos) ?> de <?= $total_registros ?> registros
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // ==========================================================
        // FUNÇÃO DE EXCLUSÃO
        // ==========================================================
        async function excluirProduto(id, nome) {
            const result = await Swal.fire({
                title: 'Deseja excluir este item?',
                html: `<strong>${nome}</strong> será removido permanentemente.`,
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
                    html: 'Excluindo item',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                try {
                    const formData = new FormData();
                    formData.append('acao', 'excluir');
                    formData.append('id', id);
                    
                    const response = await fetch('listar_produtos.php', {
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
                        const linha = document.getElementById('linha-' + id);
                        linha.style.opacity = '0';
                        linha.style.transform = 'translateX(-20px)';
                        
                        setTimeout(() => {
                            linha.remove();
                            
                            // Se não houver mais produtos, recarregar página
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
        // IMPORTAÇÃO CSV (placeholder)
        // ==========================================================
        document.getElementById('upload_csv').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                Swal.fire({
                    title: 'Importar CSV',
                    text: 'Funcionalidade em desenvolvimento',
                    icon: 'info',
                    confirmButtonColor: '#131c71'
                });
                // Aqui você pode implementar a lógica de importação
            }
        });
    </script>
</body>
</html>