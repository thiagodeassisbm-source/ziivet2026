<?php
/**
 * ZIIPVET - Notas de Produto (NFC-e)
 * Arquivo: nfe/envios_nfe.php
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;

// Filtros
$filtro_status = $_GET['status'] ?? '';
$filtro_producao = $_GET['producao'] ?? '';
$filtro_codigo_venda = $_GET['codigo_venda'] ?? '';
$filtro_serie = $_GET['serie'] ?? '';
$filtro_numero = $_GET['numero'] ?? '';
$filtro_valor = $_GET['valor'] ?? '';
$filtro_cliente = $_GET['cliente'] ?? '';
$filtro_data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$filtro_data_fim = $_GET['data_fim'] ?? date('Y-m-d', strtotime('+30 days'));

// Query principal
$sql = "SELECT v.*, c.nome as nome_cliente
        FROM vendas v
        LEFT JOIN clientes c ON v.id_cliente = c.id
        WHERE v.id_admin = ? 
        AND v.numero_nfe IS NOT NULL";

$params = [$id_admin];

if (!empty($filtro_status)) {
    $sql .= " AND v.status_nfe = ?";
    $params[] = $filtro_status;
}

if (!empty($filtro_codigo_venda)) {
    $sql .= " AND v.id = ?";
    $params[] = $filtro_codigo_venda;
}

if (!empty($filtro_serie)) {
    $sql .= " AND v.serie_nfe = ?";
    $params[] = $filtro_serie;
}

if (!empty($filtro_numero)) {
    $sql .= " AND v.numero_nfe = ?";
    $params[] = $filtro_numero;
}

if (!empty($filtro_valor)) {
    $sql .= " AND v.valor_total = ?";
    $params[] = $filtro_valor;
}

if (!empty($filtro_cliente)) {
    $sql .= " AND c.nome LIKE ?";
    $params[] = "%$filtro_cliente%";
}

if (!empty($filtro_data_inicio)) {
    $sql .= " AND DATE(v.data_venda) >= ?";
    $params[] = $filtro_data_inicio;
}

if (!empty($filtro_data_fim)) {
    $sql .= " AND DATE(v.data_venda) <= ?";
    $params[] = $filtro_data_fim;
}

$sql .= " ORDER BY v.data_venda DESC, v.id DESC LIMIT 100";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Totalizadores (considerando apenas autorizadas)
    $stmt_totais = $pdo->prepare("SELECT 
        SUM(CASE WHEN status_nfe = 'AUTORIZADA' THEN valor_total ELSE 0 END) as total_autorizado_sem_st,
        SUM(CASE WHEN status_nfe = 'AUTORIZADA' THEN valor_total ELSE 0 END) as total_autorizado_com_st,
        COUNT(CASE WHEN status_nfe = 'AUTORIZADA' THEN 1 END) as qtd_autorizadas
        FROM vendas 
        WHERE id_admin = ? AND numero_nfe IS NOT NULL");
    $stmt_totais->execute([$id_admin]);
    $totais = $stmt_totais->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $notas = [];
    $totais = ['total_autorizado_sem_st' => 0, 'total_autorizado_com_st' => 0, 'qtd_autorizadas' => 0];
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notas de produto (NFC-e) | ZiipVet</title>
    
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/menu.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/formularios.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .page-title {
            font-size: 24px;
            color: #495057;
            margin-bottom: 25px;
            font-weight: 400;
        }
        
        .filters-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 15px;
        }
        
        .filters-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr 150px 200px 200px;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .filter-input {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .totalizadores-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .card-totalizador {
            text-align: center;
            padding: 25px 20px;
            border-radius: 8px;
            color: #fff;
        }
        
        .card-totalizador.cinza {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
        }
        
        .card-totalizador.cinza-claro {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        }
        
        .card-totalizador.verde {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
        }
        
        .card-totalizador-label {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 10px;
            opacity: 0.95;
        }
        
        .card-totalizador-valor {
            font-size: 32px;
            font-weight: 700;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border: 1px solid #dee2e6;
        }
        
        thead th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
            border-right: 1px solid #dee2e6;
        }
        
        thead th:last-child {
            border-right: none;
        }
        
        tbody td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            border-right: 1px solid #f0f0f0;
            font-size: 13px;
            color: #212529;
        }
        
        tbody td:last-child {
            border-right: none;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
        }
        
        .status-autorizada {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pendente {
            background: #fff3cd;
            color: #856404;
        }
        
        .btn-acao {
            width: 32px;
            height: 32px;
            border-radius: 4px;
            border: none;
            margin: 0 2px;
            cursor: pointer;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
        }
        
        .btn-acao.cinza { background: #6c757d; }
        .btn-acao.azul { background: #17a2b8; }
        .btn-acao.vermelho { background: #dc3545; }
        .btn-acao.cinza-claro { background: #95a5a6; }
        .btn-acao.amarelo { background: #ffc107; color: #212529; }
        .btn-acao.roxo { background: #6f42c1; }
        
        .btn-acao:hover {
            opacity: 0.85;
        }
        
        .btn-relatorios {
            background: #6c757d;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            float: right;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php $path_prefix = '../'; ?>
    <aside class="sidebar-container"><?php include '../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <h1 class="page-title">Notas de produto (NFC-e)</h1>

        <!-- FILTROS -->
        <form method="GET">
            <div class="filters-row">
                <input type="text" name="codigo_venda" class="filter-input" placeholder="Cód. Venda" value="<?= htmlspecialchars($filtro_codigo_venda) ?>">
                <input type="text" name="serie" class="filter-input" placeholder="Série" value="<?= htmlspecialchars($filtro_serie) ?>">
                <input type="text" name="numero" class="filter-input" placeholder="Número" value="<?= htmlspecialchars($filtro_numero) ?>">
                <input type="text" name="valor" class="filter-input" placeholder="Valor NFC-e" value="<?= htmlspecialchars($filtro_valor) ?>">
            </div>
            
            <div class="filters-row-2">
                <input type="text" name="cliente" class="filter-input" placeholder="Cliente" value="<?= htmlspecialchars($filtro_cliente) ?>">
                
                <select name="status" class="filter-input">
                    <option value="">Todos os Status</option>
                    <option value="AUTORIZADA" <?= $filtro_status == 'AUTORIZADA' ? 'selected' : '' ?>>Autorizada</option>
                    <option value="PENDENTE" <?= $filtro_status == 'PENDENTE' ? 'selected' : '' ?>>Pendente</option>
                    <option value="REJEITADA" <?= $filtro_status == 'REJEITADA' ? 'selected' : '' ?>>Rejeitada</option>
                    <option value="CANCELADA" <?= $filtro_status == 'CANCELADA' ? 'selected' : '' ?>>Cancelada</option>
                </select>
                
                <input type="text" class="filter-input" placeholder="Produção" value="Produção" readonly style="background:#e9ecef">
                
                <input type="date" name="data_inicio" class="filter-input" value="<?= $filtro_data_inicio ?>">
                
                <input type="date" name="data_fim" class="filter-input" value="<?= $filtro_data_fim ?>">
            </div>
            
            <button type="submit" class="btn-ziip" style="background:#007bff; color:#fff; border:none; padding:8px 20px; border-radius:4px; cursor:pointer; margin-right:10px;">
                <i class="fas fa-search"></i> Filtrar
            </button>
            
            <button type="button" onclick="window.location.href='?'" class="btn-ziip" style="background:#6c757d; color:#fff; border:none; padding:8px 20px; border-radius:4px; cursor:pointer;">
                <i class="fas fa-redo"></i> Limpar
            </button>
        </form>

        <button class="btn-relatorios">
            <i class="fas fa-file-pdf"></i> Relatórios
        </button>
        <div style="clear:both;"></div>

        <!-- TOTALIZADORES -->
        <div class="totalizadores-grid">
            <div class="card-totalizador cinza">
                <div class="card-totalizador-label">Total AUTORIZADO sem substituição tributária</div>
                <div class="card-totalizador-valor">R$ <?= number_format($totais['total_autorizado_sem_st'], 2, ',', '.') ?></div>
            </div>
            
            <div class="card-totalizador cinza-claro">
                <div class="card-totalizador-label">Total AUTORIZADO com substituição tributária</div>
                <div class="card-totalizador-valor">R$ <?= number_format($totais['total_autorizado_com_st'], 2, ',', '.') ?></div>
            </div>
            
            <div class="card-totalizador verde">
                <div class="card-totalizador-label">Total de vendas AUTORIZADAS</div>
                <div class="card-totalizador-valor"><?= $totais['qtd_autorizadas'] ?></div>
            </div>
        </div>

        <!-- TABELA -->
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Emitido em</th>
                        <th>Série/Núm</th>
                        <th>Ambiente</th>
                        <th>Venda</th>
                        <th>Vl. NF</th>
                        <th>Destinatário</th>
                        <th>Status</th>
                        <th style="text-align:center;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($notas) > 0): ?>
                        <?php foreach ($notas as $nota): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($nota['data_venda'])) ?> às <?= date('H:i:s', strtotime($nota['data_venda'])) ?></td>
                                <td><strong><?= $nota['serie_nfe'] ?>/<?= $nota['numero_nfe'] ?></strong></td>
                                <td><?= $nota['ambiente_nfe'] == 'PRODUCAO' ? 'Produção' : 'Homologação' ?></td>
                                <td><?= $nota['id'] ?></td>
                                <td><?= number_format($nota['valor_total'], 2, ',', '.') ?></td>
                                <td><?= htmlspecialchars($nota['nome_cliente'] ?? 'Não informado') ?></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($nota['status_nfe'] ?? 'pendente') ?>">
                                        <?= $nota['status_nfe'] ?? 'PENDENTE' ?>
                                    </span>
                                </td>
                                <td style="text-align:center; white-space:nowrap;">
                                    <button class="btn-acao cinza" title="Imprimir DANFE">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <button class="btn-acao azul" title="Enviar por Email">
                                        <i class="fas fa-envelope"></i>
                                    </button>
                                    <button class="btn-acao vermelho" title="Cancelar Nota">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                    <button class="btn-acao cinza-claro" title="Download XML">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    <button class="btn-acao amarelo" title="Consultar Status">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                    <button class="btn-acao roxo" title="Ver Log">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align:center; padding:40px; color:#999;">
                                Nenhuma nota fiscal encontrada
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</body>
</html>
