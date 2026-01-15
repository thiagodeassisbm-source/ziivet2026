<?php 
require_once 'auth.php'; 
require_once 'config/configuracoes.php'; 

$id_admin = $_SESSION['id_admin'];
$hoje = date('Y-m-d');
$mes_atual = date('m');
$ano_atual = date('Y');

// ===== INICIALIZAÇÃO DE VARIÁVEIS =====
$total_pacientes = 0;
$total_clientes = 0;
$total_agendados = 0;
$total_vendas_hoje = 0;
$produtos_baixo_estoque = [];
$total_produtos_baixo = 0;
$total_pages_produtos = 0;
$page = isset($_GET['page_estoque']) ? (int)$_GET['page_estoque'] : 1;
$per_page = 10;
$contas_pagar = [];
$aniversariantes = [];
$vacinas_atrasadas = [];
$vacinas_mes = [];

try {
    // ===== CARDS PRINCIPAIS =====
    // Total de pacientes ativos
    $stmt_ativos = $pdo->prepare("SELECT COUNT(*) FROM pacientes WHERE id_cliente IN (SELECT id FROM clientes WHERE id_admin = ?) AND status = 'ATIVO'");
    $stmt_ativos->execute([$id_admin]);
    $total_pacientes = $stmt_ativos->fetchColumn();

    // Total de clientes
    $stmt_clientes = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE id_admin = ?");
    $stmt_clientes->execute([$id_admin]);
    $total_clientes = $stmt_clientes->fetchColumn();

    // Total de agendamentos hoje
    $stmt_agendados = $pdo->prepare("SELECT COUNT(*) FROM agendas WHERE id_paciente IN (SELECT p.id FROM pacientes p INNER JOIN clientes c ON p.id_cliente = c.id WHERE c.id_admin = ?) AND data_agendamento = ? AND status <> 'Cancelado'");
    $stmt_agendados->execute([$id_admin, $hoje]);
    $total_agendados = $stmt_agendados->fetchColumn();

    // Total de vendas hoje
    $stmt_vendas = $pdo->prepare("SELECT COALESCE(SUM(valor_total), 0) FROM vendas WHERE id_admin = ? AND DATE(data_venda) = ?");
    $stmt_vendas->execute([$id_admin, $hoje]);
    $total_vendas_hoje = $stmt_vendas->fetchColumn();

    // ===== PRODUTOS COM ESTOQUE BAIXO (Paginação) =====
$offset = ($page - 1) * $per_page;

$stmt_produtos = $pdo->prepare("
    SELECT id, nome, estoque_inicial, sku, gtin
    FROM produtos 
    WHERE id_admin = :id_admin 
    AND tipo = 'Produto'
    AND monitorar_estoque = 1
    AND status = 'ATIVO'
    AND estoque_inicial <= 5
    ORDER BY estoque_inicial ASC
    LIMIT :limit OFFSET :offset
");

$stmt_produtos->bindValue(':id_admin', $id_admin, PDO::PARAM_INT);
$stmt_produtos->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt_produtos->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt_produtos->execute();
$produtos_baixo_estoque = $stmt_produtos->fetchAll(PDO::FETCH_ASSOC);

// Total de produtos para a paginação
$stmt_total_produtos = $pdo->prepare("
    SELECT COUNT(*) 
    FROM produtos 
    WHERE id_admin = ? 
    AND tipo = 'Produto'
    AND monitorar_estoque = 1
    AND status = 'ATIVO'
    AND estoque_inicial <= 5
");
$stmt_total_produtos->execute([$id_admin]);
$total_produtos_baixo = $stmt_total_produtos->fetchColumn();

    // ===== CONTAS A PAGAR (Próximos 30 dias) =====
    $data_limite = date('Y-m-d', strtotime('+30 days'));
    $stmt_contas = $pdo->prepare("
        SELECT c.*, 
               CASE c.entidade_tipo
                   WHEN 'fornecedor' THEN f.nome_fantasia
                   WHEN 'cliente' THEN cl.nome
                   ELSE 'Outros'
               END as nome_entidade
        FROM contas c
        LEFT JOIN fornecedores f ON c.id_entidade = f.id AND c.entidade_tipo = 'fornecedor'
        LEFT JOIN clientes cl ON c.id_entidade = cl.id AND c.entidade_tipo = 'cliente'
        WHERE c.id_admin = ? 
        AND c.natureza = 'Despesa'
        AND c.status_baixa = 'PENDENTE'
        AND c.vencimento BETWEEN ? AND ?
        ORDER BY c.vencimento ASC
        LIMIT 10
    ");
    $stmt_contas->execute([$id_admin, $hoje, $data_limite]);
    $contas_pagar = $stmt_contas->fetchAll(PDO::FETCH_ASSOC);

    // ===== ANIVERSARIANTES DO MÊS =====
    $stmt_aniversariantes = $pdo->prepare("
        SELECT c.id, c.nome, c.telefone, c.data_nascimento,
               DAY(c.data_nascimento) as dia_aniversario
        FROM clientes c
        WHERE c.id_admin = ?
        AND MONTH(c.data_nascimento) = ?
        ORDER BY DAY(c.data_nascimento) ASC
        LIMIT 10
    ");
    $stmt_aniversariantes->execute([$id_admin, $mes_atual]);
    $aniversariantes = $stmt_aniversariantes->fetchAll(PDO::FETCH_ASSOC);

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
        HAVING DATEDIFF(?, MAX(a.data_atendimento)) > 365
        OR MAX(a.data_atendimento) IS NULL
        ORDER BY dias_atraso DESC
        LIMIT 10
    ");
    $stmt_vacinas_atrasadas->execute([$hoje, $id_admin, $hoje]);
    $vacinas_atrasadas = $stmt_vacinas_atrasadas->fetchAll(PDO::FETCH_ASSOC);

    // ===== VACINAS DO MÊS (Vencendo nos próximos 30 dias) =====
    $data_limite_vacina = date('Y-m-d', strtotime('+30 days'));
    $stmt_vacinas_mes = $pdo->prepare("
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
    $stmt_vacinas_mes->execute([$hoje, $id_admin, $hoje, $data_limite_vacina]);
    $vacinas_mes = $stmt_vacinas_mes->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erro no dashboard: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | ZIIPVET</title>
    
    <!-- CSS CENTRALIZADO -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/menu.css">
    <link rel="stylesheet" href="css/header.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root { 
            --fundo: #ecf0f5;
            --texto-dark: #333;
            --azul: #17a2b8;
            --verde: #28a745;
            --vermelho: #b92426;
            --roxo: #622599;
            --laranja: #f39c12;
            --ciano: #00c0ef;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Exo', 'Source Sans Pro', sans-serif;
            background-color: var(--fundo);
            color: var(--texto-dark);
            min-height: 100vh;
        }

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

        /* ✅ GRID DOS CARDS - 4 POR LINHA */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        
        /* RESPONSIVO - 2 por linha em telas médias */
        @media (max-width: 1400px) {
            .dashboard-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* RESPONSIVO - 1 por linha em mobile */
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            .page-header-title {
                font-size: 20px;
            }
        }

        /* CARDS MODERNOS */
        .small-box {
            border-radius: 10px;
            position: relative;
            display: block;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            color: #fff;
            overflow: hidden;
            transition: all 0.3s ease;
            min-height: 130px;
        }
        
        .small-box:hover {
            text-decoration: none;
            color: #fff;
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.2);
        }

        .small-box .inner {
            padding: 18px 20px 15px;
            position: relative;
            z-index: 2;
        }

        .small-box h3 {
            font-size: 32px;
            font-weight: 800;
            margin: 0 0 8px 0;
            font-family: 'Exo', sans-serif;
        }

        .small-box p {
            font-size: 12px;
            margin: 0;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-family: 'Exo', sans-serif;
        }

        .small-box .icon-bg {
            position: absolute;
            top: 5px;
            right: 10px;
            z-index: 0;
            font-size: 70px;
            color: rgba(0,0,0,0.12);
            transition: all 0.3s ease;
        }

        .small-box:hover .icon-bg {
            font-size: 80px;
            transform: rotate(-10deg);
        }

        .small-box-footer {
            position: relative;
            text-align: center;
            padding: 8px 0;
            color: rgba(255,255,255,0.9);
            display: block;
            z-index: 10;
            background: rgba(0,0,0,0.15);
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
            font-family: 'Exo', sans-serif;
        }

        .small-box-footer:hover {
            color: #fff;
            background: rgba(0,0,0,0.25);
        }

        .small-box-footer i {
            margin-left: 8px;
            transition: transform 0.3s;
        }

        .small-box:hover .small-box-footer i {
            transform: translateX(5px);
        }

        /* Cores */
        .bg-blue { background: linear-gradient(135deg, var(--azul) 0%, #138496 100%); }
        .bg-cyan { background: linear-gradient(135deg, var(--ciano) 0%, #00a7d0 100%); }
        .bg-orange { background: linear-gradient(135deg, var(--laranja) 0%, #e08e0b 100%); }
        .bg-red { background: linear-gradient(135deg, var(--vermelho) 0%, #a01f21 100%); }

        /* PAINEIS DE INFORMAÇÕES */
        .info-panels {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .panel-box {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .panel-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 18px 20px;
            border-bottom: 3px solid #dee2e6;
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
            background: linear-gradient(135deg, var(--roxo), #8e44ad);
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

        /* PAGINAÇÃO */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            padding: 15px 20px;
            border-top: 1px solid #f0f0f0;
        }

        .pagination a,
        .pagination span {
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            font-family: 'Exo', sans-serif;
            transition: all 0.2s;
        }

        .pagination a {
            background: #f8f9fa;
            color: #495057;
        }

        .pagination a:hover {
            background: var(--roxo);
            color: white;
        }

        .pagination span {
            background: var(--roxo);
            color: white;
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

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
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

        /* RESPONSIVO - Info Panels */
        @media (max-width: 1200px) {
            .info-panels {
                grid-template-columns: 1fr;
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

        <!-- CARDS PRINCIPAIS - 4 POR LINHA -->
        <div class="dashboard-grid">
            
            <div class="small-box bg-blue">
                <div class="inner">
                    <p>Total de Pets</p>
                    <h3><?= number_format($total_pacientes, 0, ',', '.') ?></h3>
                </div>
                <div class="icon-bg">
                    <i class="fas fa-paw"></i>
                </div>
                <a href="listar_pacientes.php" class="small-box-footer">
                    Ver Detalhes <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>

            <div class="small-box bg-cyan">
                <div class="inner">
                    <p>Total de Clientes</p>
                    <h3><?= number_format($total_clientes, 0, ',', '.') ?></h3>
                </div>
                <div class="icon-bg">
                    <i class="fas fa-users"></i>
                </div>
                <a href="listar_clientes.php" class="small-box-footer">
                    Ver Detalhes <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>

            <div class="small-box bg-orange">
                <div class="inner">
                    <p>Agendamentos Hoje</p>
                    <h3><?= number_format($total_agendados, 0, ',', '.') ?></h3>
                </div>
                <div class="icon-bg">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <a href="agenda.php" class="small-box-footer">
                    Ver Detalhes <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>

            <div class="small-box bg-red">
                <div class="inner">
                    <p>Vendas Hoje</p>
                    <h3>R$ <?= number_format($total_vendas_hoje, 2, ',', '.') ?></h3>
                </div>
                <div class="icon-bg">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <a href="vendas.php" class="small-box-footer">
                    Ver Detalhes <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>

        </div>

        <!-- PAINEIS DE INFORMAÇÕES -->
        <div class="info-panels">
            
            <!-- PRODUTOS COM ESTOQUE BAIXO -->
            <div class="panel-box">
                <div class="panel-header">
                    <div class="title">
                        <i class="fas fa-box"></i>
                        Produtos com estoque baixo ou sem estoque
                    </div>
                    <?php if ($total_produtos_baixo > 0): ?>
                        <span class="badge"><?= $total_produtos_baixo ?></span>
                    <?php endif; ?>
                </div>
                <div class="panel-body">
                    <?php if (empty($produtos_baixo_estoque)): ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>Todos os produtos estão com estoque adequado</p>
                        </div>
                    <?php else: ?>
                        <ul class="item-list">
                            <?php foreach ($produtos_baixo_estoque as $produto): 
                                $badge_class = 'badge-warning';
                                $badge_text = 'Estoque Baixo';
                                
                                if ($produto['estoque_inicial'] < 0) {
                                    $badge_class = 'badge-critical';
                                    $badge_text = 'NEGATIVO!';
                                } elseif ($produto['estoque_inicial'] == 0) {
                                    $badge_class = 'badge-danger';
                                    $badge_text = 'SEM ESTOQUE';
                                }
                            ?>
                                <li>
                                    <div class="item-header">
                                        <span class="item-title"><?= htmlspecialchars($produto['nome']) ?></span>
                                        <span class="item-badge <?= $badge_class ?>">
                                            <?php if ($produto['estoque_inicial'] < 0): ?>
                                                <i class="fas fa-exclamation-triangle"></i> 
                                            <?php endif; ?>
                                            <?= number_format($produto['estoque_inicial'], 0) ?> un - <?= $badge_text ?>
                                        </span>
                                    </div>
                                    <div class="item-details">
                                        <?php if ($produto['sku']): ?>
                                            <span><i class="fas fa-barcode"></i> SKU: <?= htmlspecialchars($produto['sku']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($produto['gtin']): ?>
                                            <span><i class="fas fa-qrcode"></i> GTIN: <?= htmlspecialchars($produto['gtin']) ?></span>
                                        <?php endif; ?>
                                        <span><i class="fas fa-info-circle"></i> ID: <?= $produto['id'] ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <?php if ($total_pages_produtos > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page_estoque=<?= $page - 1 ?>"><i class="fas fa-chevron-left"></i> Anterior</a>
                                <?php endif; ?>
                                
                                <span>Página <?= $page ?> de <?= $total_pages_produtos ?></span>
                                
                                <?php if ($page < $total_pages_produtos): ?>
                                    <a href="?page_estoque=<?= $page + 1 ?>">Próxima <i class="fas fa-chevron-right"></i></a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CONTAS A PAGAR -->
            <div class="panel-box">
                <div class="panel-header">
                    <div class="title">
                        <i class="fas fa-file-invoice-dollar"></i>
                        Contas a Pagar (Próximos 30 dias)
                    </div>
                    <?php if (count($contas_pagar) > 0): ?>
                        <span class="badge"><?= count($contas_pagar) ?></span>
                    <?php endif; ?>
                </div>
                <div class="panel-body">
                    <?php if (empty($contas_pagar)): ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>Nenhuma conta a pagar nos próximos 30 dias</p>
                        </div>
                    <?php else: ?>
                        <ul class="item-list">
                            <?php foreach ($contas_pagar as $conta): 
                                $dias_vencimento = (strtotime($conta['vencimento']) - strtotime($hoje)) / 86400;
                                $badge_class = $dias_vencimento <= 0 ? 'badge-danger' : ($dias_vencimento <= 7 ? 'badge-warning' : 'badge-success');
                            ?>
                                <li>
                                    <div class="item-header">
                                        <span class="item-title"><?= htmlspecialchars($conta['descricao']) ?></span>
                                        <span class="item-badge <?= $badge_class ?>">
                                            R$ <?= number_format($conta['valor_parcela'], 2, ',', '.') ?>
                                        </span>
                                    </div>
                                    <div class="item-details">
                                        <span><i class="fas fa-building"></i> <?= htmlspecialchars($conta['nome_entidade']) ?></span>
                                        <span><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($conta['vencimento'])) ?></span>
                                        <?php if ($dias_vencimento <= 0): ?>
                                            <span style="color: var(--vermelho);"><i class="fas fa-exclamation-triangle"></i> Vencida</span>
                                        <?php else: ?>
                                            <span><i class="fas fa-clock"></i> <?= round($dias_vencimento) ?> dias</span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ANIVERSARIANTES DO MÊS -->
            <div class="panel-box">
                <div class="panel-header">
                    <div class="title">
                        <i class="fas fa-birthday-cake"></i>
                        Aniversariantes do Mês
                    </div>
                    <?php if (count($aniversariantes) > 0): ?>
                        <span class="badge"><?= count($aniversariantes) ?></span>
                    <?php endif; ?>
                </div>
                <div class="panel-body">
                    <?php if (empty($aniversariantes)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <p>Nenhum aniversariante este mês</p>
                        </div>
                    <?php else: ?>
                        <ul class="item-list">
                            <?php foreach ($aniversariantes as $aniversariante): 
                                $dia_atual = date('d');
                                $badge_class = $aniversariante['dia_aniversario'] == $dia_atual ? 'badge-danger' : 'badge-success';
                            ?>
                                <li>
                                    <div class="item-header">
                                        <span class="item-title"><?= htmlspecialchars($aniversariante['nome']) ?></span>
                                        <span class="item-badge <?= $badge_class ?>">
                                            <?= $aniversariante['dia_aniversario'] ?>/<?= $mes_atual ?>
                                        </span>
                                    </div>
                                    <div class="item-details">
                                        <?php if ($aniversariante['telefone']): ?>
                                            <span><i class="fas fa-phone"></i> <?= htmlspecialchars($aniversariante['telefone']) ?></span>
                                        <?php endif; ?>
                                        <span><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($aniversariante['data_nascimento'])) ?></span>
                                        <?php if ($aniversariante['dia_aniversario'] == $dia_atual): ?>
                                            <span style="color: var(--vermelho);"><i class="fas fa-gift"></i> Hoje!</span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
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
                    <button class="tab-button" onclick="switchTab('mes')">
                        Vacinas do Mês
                        <?php if (count($vacinas_mes) > 0): ?>
                            <span class="badge"><?= count($vacinas_mes) ?></span>
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
                    
                    <!-- TAB: VACINAS DO MÊS -->
                    <div id="tab-mes" class="tab-content">
                        <?php if (empty($vacinas_mes)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-check"></i>
                                <p>Nenhuma vacina a vencer nos próximos 30 dias</p>
                            </div>
                        <?php else: ?>
                            <ul class="item-list">
                                <?php foreach ($vacinas_mes as $vacina): ?>
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
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById('tab-' + tabName).classList.add('active');
        }
    </script>
</body>
</html>