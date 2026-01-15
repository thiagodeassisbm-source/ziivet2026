<?php
/**
 * =========================================================================================
 * ZIIPVET - CONTROLE DE ESTOQUE
 * ARQUIVO: estoque.php (ou nome do arquivo original)
 * VERSÃO: 4.0.0 - PADRÃO MODERNO
 * =========================================================================================
 */
ini_set('display_errors', 1);
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
$titulo_pagina = "Controle de Estoque";

$pagina_atual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1;
$itens_por_pagina = 20;
$inicio = ($pagina_atual - 1) * $itens_por_pagina;

$busca = $_GET['busca'] ?? '';
$periodo = $_GET['periodo'] ?? '';

// ==========================================================
// CONSTRUÇÃO DA QUERY
// ==========================================================
$sql_condicoes = "WHERE p.id_admin = :id_admin";
$params = [':id_admin' => $id_admin];

$from_table = "produtos p";
$join_clause = "";
$distinct_clause = '';

if (!empty($busca)) {
    $sql_condicoes .= " AND (p.nome COLLATE utf8mb4_unicode_ci LIKE :busca 
                         OR p.sku COLLATE utf8mb4_unicode_ci LIKE :busca 
                         OR p.gtin COLLATE utf8mb4_unicode_ci LIKE :busca)";
    $params[':busca'] = "%$busca%";
}

if ($periodo == 'ultimas_compras_2dias') {
    $distinct_clause = 'DISTINCT';
    $join_clause = " 
        INNER JOIN compras_itens ci ON p.gtin COLLATE utf8mb4_unicode_ci = ci.codigo_produto_fornecedor COLLATE utf8mb4_unicode_ci
        INNER JOIN compras c ON ci.id_compra = c.id AND c.id_admin = :id_admin_compras
    ";
    $sql_condicoes .= " AND c.data_cadastro >= DATE_SUB(NOW(), INTERVAL 2 DAY)";
    $params[':id_admin_compras'] = $id_admin;
    
} elseif ($periodo == 'ultimas_compras_30dias') {
    $distinct_clause = 'DISTINCT';
    $join_clause = " 
        INNER JOIN compras_itens ci ON p.gtin COLLATE utf8mb4_unicode_ci = ci.codigo_produto_fornecedor COLLATE utf8mb4_unicode_ci
        INNER JOIN compras c ON ci.id_compra = c.id AND c.id_admin = :id_admin_compras
    ";
    $sql_condicoes .= " AND c.data_cadastro >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $params[':id_admin_compras'] = $id_admin;
    
} elseif ($periodo == 'semana') {
    $sql_condicoes .= " AND p.data_cadastro >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($periodo == 'mes') {
    $sql_condicoes .= " AND MONTH(p.data_cadastro) = MONTH(NOW()) AND YEAR(p.data_cadastro) = YEAR(NOW())";
}

try {
    // Contagem total
    $sqlCount = "SELECT COUNT($distinct_clause p.id) as total 
                 FROM $from_table $join_clause $sql_condicoes";
    
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total_registros = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    $total_paginas = ceil($total_registros / $itens_por_pagina);
    
    // KPIs
    $sql_kpis = "SELECT $distinct_clause p.id, p.estoque_inicial, p.ncm, p.cfop, p.data_validade
                 FROM $from_table $join_clause $sql_condicoes";
    
    $stmt_kpis = $pdo->prepare($sql_kpis);
    $stmt_kpis->execute($params);
    $todos_os_produtos = $stmt_kpis->fetchAll(PDO::FETCH_ASSOC);
    
    $count_total = count($todos_os_produtos);
    $count_existentes = 0; 
    $count_zerados = 0; 
    $count_fiscal = 0;

    foreach ($todos_os_produtos as $p) {
        $est = (float)($p['estoque_inicial'] ?? 0);
        if ($est >= 1) $count_existentes++; 
        else $count_zerados++;
        if (empty($p['ncm']) || empty($p['cfop'])) $count_fiscal++;
    }

    // Listagem
    $sql_list = "SELECT $distinct_clause p.id, p.nome, p.sku, p.gtin, p.estoque_inicial, p.preco_custo, p.preco_venda, p.status
                 FROM $from_table $join_clause $sql_condicoes 
                 ORDER BY p.nome ASC 
                 LIMIT :limit OFFSET :offset";
    
    $stmt_list = $pdo->prepare($sql_list);
    
    foreach ($params as $key => $value) {
        $stmt_list->bindValue($key, $value);
    }
    
    $stmt_list->bindValue(':limit', $itens_por_pagina, PDO::PARAM_INT);
    $stmt_list->bindValue(':offset', $inicio, PDO::PARAM_INT);
    
    $stmt_list->execute();
    $produtos = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro: " . $e->getMessage());
}

function fmtMoeda($val) { 
    return 'R$ ' . number_format((float)$val, 2, ',', '.'); 
}

function link_paginacao($pg) {
    global $busca, $periodo;
    return "?pagina=$pg&busca=" . urlencode($busca) . "&periodo=" . urlencode($periodo);
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
    
    <style>
        /* ========================================
           ESTILOS ESPECÍFICOS DO ESTOQUE
        ======================================== */
        
        /* Badge de Filtro Ativo */
        .filter-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            margin-left: 15px;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        /* Grid de KPIs */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .kpi-card {
            background: #fff;
            padding: 25px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        
        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }
        
        .kpi-card.info {
            border-left-color: #3b82f6;
        }
        
        .kpi-card.success {
            border-left-color: #10b981;
        }
        
        .kpi-card.danger {
            border-left-color: #ef4444;
        }
        
        .kpi-card.warning {
            border-left-color: #f59e0b;
        }
        
        .kpi-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #fff;
        }
        
        .kpi-card.info .kpi-icon {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }
        
        .kpi-card.success .kpi-icon {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .kpi-card.danger .kpi-icon {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }
        
        .kpi-card.warning .kpi-icon {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        
        .kpi-content {
            flex: 1;
        }
        
        .kpi-value {
            font-size: 32px;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1;
            font-family: 'Exo', sans-serif;
        }
        
        .kpi-label {
            font-size: 13px;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 5px;
            letter-spacing: 0.5px;
        }
        
        /* Área de Filtros */
        .filters-card {
            background: #fff;
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 25px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: 2fr 1.5fr auto;
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
        
        /* Container da Tabela */
        .products-container {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }
        
        /* Tabela */
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
        
        /* Nome do Produto */
        .produto-nome {
            font-weight: 600;
            color: #131c71;
            font-size: 16px;
        }
        
        /* Badge de Status */
        .badge {
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
        
        .badge-normal {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            color: #2e7d32;
        }
        
        .badge-repor {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            color: #c62828;
        }
        
        .badge i {
            font-size: 11px;
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
            align-items: center;
        }
        
        .page-link {
            min-width: 36px;
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
            padding: 0 12px;
        }
        
        .page-link:hover:not(.disabled):not(.active) {
            background: #f8f9fa;
            border-color: #131c71;
        }
        
        .page-link.active {
            background: #131c71;
            color: #fff;
            border-color: #131c71;
        }
        
        .page-link.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .page-separator {
            padding: 0 8px;
            color: #d1d5db;
            font-weight: 600;
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
            
            .kpi-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 13px;
            }
            
            thead th, tbody td {
                padding: 10px 8px;
            }
            
            .filter-badge {
                margin-left: 0;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include 'menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include 'menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <!-- HEADER: Título com Badge de Filtro Ativo -->
        <div class="form-header" style="flex-wrap: wrap;">
            <h1 class="form-title" style="flex: 1; min-width: 300px;">
                <i class="fas fa-boxes"></i>
                Controle de Estoque
                <?php if ($periodo == 'ultimas_compras_2dias'): ?>
                    <span class="filter-badge">
                        <i class="fas fa-shopping-cart"></i> Últimas Compras (2 dias)
                    </span>
                <?php elseif ($periodo == 'ultimas_compras_30dias'): ?>
                    <span class="filter-badge">
                        <i class="fas fa-shopping-cart"></i> Últimas Compras (30 dias)
                    </span>
                <?php endif; ?>
            </h1>
        </div>

        <!-- KPIs -->
        <div class="kpi-grid">
            <div class="kpi-card info">
                <div class="kpi-icon">
                    <i class="fas fa-cubes"></i>
                </div>
                <div class="kpi-content">
                    <div class="kpi-value"><?= number_format($count_total, 0, ',', '.') ?></div>
                    <div class="kpi-label">
                        Total<?= (strpos($periodo, 'ultimas_compras') !== false) ? ' (Filtrado)' : '' ?>
                    </div>
                </div>
            </div>
            
            <div class="kpi-card success">
                <div class="kpi-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="kpi-content">
                    <div class="kpi-value"><?= number_format($count_existentes, 0, ',', '.') ?></div>
                    <div class="kpi-label">Disponível</div>
                </div>
            </div>
            
            <div class="kpi-card danger">
                <div class="kpi-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="kpi-content">
                    <div class="kpi-value"><?= number_format($count_zerados, 0, ',', '.') ?></div>
                    <div class="kpi-label">Zerado</div>
                </div>
            </div>
            
            <div class="kpi-card warning">
                <div class="kpi-icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="kpi-content">
                    <div class="kpi-value"><?= number_format($count_fiscal, 0, ',', '.') ?></div>
                    <div class="kpi-label">Pendente Fiscal</div>
                </div>
            </div>
        </div>

        <!-- FILTROS -->
        <div class="filters-card">
            <form method="GET" class="filters-grid">
                <div class="filter-group">
                    <label>
                        <i class="fas fa-search"></i>
                        Pesquisar Produto
                    </label>
                    <input type="text" 
                           name="busca" 
                           class="form-control" 
                           placeholder="Nome, SKU ou GTIN..."
                           value="<?= htmlspecialchars($busca) ?>">
                </div>
                
                <div class="filter-group">
                    <label>
                        <i class="fas fa-filter"></i>
                        Filtrar Por
                    </label>
                    <select name="periodo" class="form-control" onchange="this.form.submit()">
                        <option value="">Todos os produtos</option>
                        <option value="ultimas_compras_2dias" <?= $periodo == 'ultimas_compras_2dias' ? 'selected' : '' ?>>🛒 Últimas Compras (2 dias)</option>
                        <option value="ultimas_compras_30dias" <?= $periodo == 'ultimas_compras_30dias' ? 'selected' : '' ?>>🛒 Últimas Compras (30 dias)</option>
                        <option value="semana" <?= $periodo == 'semana' ? 'selected' : '' ?>>📅 Cadastrados há 7 dias</option>
                        <option value="mes" <?= $periodo == 'mes' ? 'selected' : '' ?>>📅 Cadastrados este mês</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-filter">
                    <i class="fas fa-search"></i>
                    Buscar
                </button>
            </form>
        </div>

        <!-- TABELA DE PRODUTOS -->
        <div class="products-container">
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-tag"></i> Nome do Produto</th>
                        <th width="140"><i class="fas fa-barcode"></i> Código/SKU</th>
                        <th width="100" style="text-align: center;"><i class="fas fa-boxes"></i> Estoque</th>
                        <th width="120"><i class="fas fa-dollar-sign"></i> Custo</th>
                        <th width="120"><i class="fas fa-dollar-sign"></i> Venda</th>
                        <th width="120"><i class="fas fa-info-circle"></i> Situação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($produtos) > 0): ?>
                        <?php foreach ($produtos as $p): 
                            $est = (float)$p['estoque_inicial'];
                            $badge = ($est <= 0) ? ['repor', 'Repor', 'fa-exclamation-triangle'] : ['normal', 'Normal', 'fa-check-circle'];
                        ?>
                        <tr>
                            <td>
                                <span class="produto-nome"><?= htmlspecialchars($p['nome']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($p['sku'] ?: 'N/I') ?></td>
                            <td style="text-align: center; font-weight: 700; font-size: 18px; color: #131c71;">
                                <?= number_format($est, 0, ',', '.') ?>
                            </td>
                            <td><?= fmtMoeda($p['preco_custo']) ?></td>
                            <td style="font-weight: 700; color: #28a745;">
                                <?= fmtMoeda($p['preco_venda']) ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $badge[0] ?>">
                                    <i class="fas <?= $badge[2] ?>"></i>
                                    <?= $badge[1] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <h3>Nenhum produto encontrado</h3>
                                    <p>
                                        <?php if (strpos($periodo, 'ultimas_compras') !== false): ?>
                                            <?php if ($periodo == 'ultimas_compras_2dias'): ?>
                                                Nenhum produto foi comprado nos últimos 2 dias.
                                            <?php else: ?>
                                                Nenhum produto foi comprado nos últimos 30 dias.
                                            <?php endif; ?>
                                        <?php else: ?>
                                            Não há produtos que correspondam aos filtros aplicados.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- PAGINAÇÃO -->
            <?php if ($total_paginas > 1): ?>
            <div class="pagination-wrapper">
                <div class="page-info">
                    Mostrando <?= count($produtos) ?> de <?= number_format($total_registros, 0, ',', '.') ?> produtos
                </div>
                <div class="page-nav">
                    <a href="<?= ($pagina_atual > 1) ? link_paginacao($pagina_atual - 1) : '#' ?>" 
                       class="page-link <?= ($pagina_atual <= 1) ? 'disabled' : '' ?>"
                       title="Página Anterior">
                        <i class="fas fa-chevron-left"></i>
                    </a>

                    <?php 
                    $inicio_pag = max(1, $pagina_atual - 2);
                    $fim_pag = min($total_paginas, $pagina_atual + 2);
                    
                    if ($inicio_pag > 1) {
                        echo '<a href="' . link_paginacao(1) . '" class="page-link">1</a>';
                        if ($inicio_pag > 2) echo '<span class="page-separator">...</span>';
                    }
                    
                    for ($i = $inicio_pag; $i <= $fim_pag; $i++): 
                    ?>
                        <a href="<?= link_paginacao($i) ?>" 
                           class="page-link <?= ($pagina_atual == $i) ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php 
                    endfor;
                    
                    if ($fim_pag < $total_paginas) {
                        if ($fim_pag < $total_paginas - 1) echo '<span class="page-separator">...</span>';
                        echo '<a href="' . link_paginacao($total_paginas) . '" class="page-link">' . $total_paginas . '</a>';
                    }
                    ?>

                    <a href="<?= ($pagina_atual < $total_paginas) ? link_paginacao($pagina_atual + 1) : '#' ?>" 
                       class="page-link <?= ($pagina_atual >= $total_paginas) ? 'disabled' : '' ?>"
                       title="Próxima Página">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

</body>
</html>