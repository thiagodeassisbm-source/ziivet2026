<?php
require_once '../auth.php';
require_once '../config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;
$id_venda = $_GET['id'] ?? null;

if (!$id_venda) {
    header('Location: ../vendas.php');
    exit;
}

try {
    // Buscar dados da venda
    $sql = "SELECT v.*, c.nome as nome_cliente, c.telefone, c.endereco, c.bairro, c.cidade, c.estado,
                   p.nome_paciente, p.especie, p.raca
            FROM vendas v
            LEFT JOIN clientes c ON v.id_cliente = c.id
            LEFT JOIN pacientes p ON v.id_paciente = p.id
            WHERE v.id = ? AND v.id_admin = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_venda, $id_admin]);
    $venda = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venda) {
        header('Location: ../vendas.php');
        exit;
    }

    // Buscar itens da venda
    $sql_itens = "SELECT vi.*, pr.nome as nome_produto
                  FROM vendas_itens vi
                  INNER JOIN produtos pr ON vi.id_produto = pr.id
                  WHERE vi.id_venda = ?";
    
    $stmt_itens = $pdo->prepare($sql_itens);
    $stmt_itens->execute([$id_venda]);
    $itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao carregar venda: " . $e->getMessage());
}

$total_liquido = $venda['valor_total'] - ($venda['desconto'] ?? 0);
$titulo_pagina = "Resumo da Venda #" . $venda['id'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { 
            --fundo: #ecf0f5; 
            --primaria: #5a6c7d; 
            --sucesso: #00a65a; 
            --danger: #dd4b39; 
            --warning: #f39c12;
            --info: #3498db;
            --sidebar-collapsed: 75px;
            --sidebar-expanded: 260px;
            --header-height: 80px;
            --transition: 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body { 
            font-family: 'Source Sans Pro', sans-serif; 
            background-color: var(--fundo); 
            color: #333; 
            font-size: 16px;
            overflow-x: hidden;
        }

        /* Estilos mínimos para o container do menu */
        aside.sidebar-container { 
            position: fixed; 
            left: 0; 
            top: 0; 
            height: 100vh; 
            width: var(--sidebar-collapsed); 
            z-index: 1000; 
            transition: width var(--transition); 
        }
        
        aside.sidebar-container:hover { 
            width: var(--sidebar-expanded); 
        }
        
        /* Header */
        header.top-header { 
            position: fixed; 
            top: 0; 
            left: var(--sidebar-collapsed); 
            right: 0; 
            height: var(--header-height); 
            z-index: 900; 
            transition: left var(--transition); 
            background: #fff; 
            border-bottom: 1px solid #eee; 
            margin: 0 !important; 
        }
        
        aside.sidebar-container:hover ~ header.top-header { 
            left: var(--sidebar-expanded); 
        }

        /* Main content */
        main.main-content { 
            margin-left: var(--sidebar-collapsed); 
            padding: calc(var(--header-height) + 30px) 25px 30px 25px;
            transition: margin-left var(--transition);
            width: calc(100% - var(--sidebar-collapsed));
        }
        
        aside.sidebar-container:hover ~ main.main-content { 
            margin-left: var(--sidebar-expanded); 
            width: calc(100% - var(--sidebar-expanded));
        }

        .page-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: #444;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-voltar {
            background: var(--primaria);
            color: #fff;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
        }

        .btn-voltar:hover {
            background: #4a5c6d;
            transform: translateY(-2px);
        }

        .resumo-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .resumo-header {
            background: linear-gradient(135deg, var(--info) 0%, #2980b9 100%);
            color: #fff;
            padding: 24px 30px;
        }

        .resumo-header h2 {
            font-size: 24px;
            margin-bottom: 8px;
        }

        .resumo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .resumo-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .resumo-item label {
            font-size: 12px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .resumo-item span {
            font-size: 16px;
            font-weight: 600;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-aberto { background: var(--danger); color: #fff; }
        .badge-pago { background: var(--sucesso); color: #fff; }
        .badge-pendente { background: var(--warning); color: #fff; }

        .saldo-devedor {
            background: linear-gradient(135deg, #fee 0%, #fcc 100%);
            padding: 20px;
            border-left: 4px solid var(--danger);
            margin: 20px 30px;
            border-radius: 8px;
        }

        .saldo-devedor h3 {
            font-size: 18px;
            color: var(--danger);
            margin-bottom: 8px;
        }

        .saldo-devedor .valor {
            font-size: 32px;
            font-weight: 700;
            color: var(--danger);
        }

        .info-section {
            padding: 30px;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-section:last-child {
            border-bottom: none;
        }

        .info-section h3 {
            font-size: 18px;
            color: var(--primaria);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }

        .info-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .info-field label {
            font-size: 12px;
            font-weight: 700;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-field span {
            font-size: 15px;
            color: #333;
            font-weight: 600;
        }

        .table-produtos {
            width: 100%;
            border-collapse: collapse;
        }

        .table-produtos thead {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .table-produtos thead th {
            padding: 14px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            color: var(--primaria);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-produtos tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        .table-produtos tbody tr:hover {
            background: #f8f9fa;
        }

        .table-produtos tfoot {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
        }

        .table-produtos tfoot td {
            padding: 16px;
            font-weight: 700;
            font-size: 16px;
            color: var(--sucesso);
        }

        .text-right { text-align: right; }

        .obs-box {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 8px;
            border-left: 4px solid var(--info);
            margin-top: 16px;
        }

        .obs-box h4 {
            font-size: 14px;
            color: var(--primaria);
            margin-bottom: 8px;
        }

        .obs-box p {
            font-size: 14px;
            color: #666;
            line-height: 1.6;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            padding: 20px 30px;
            background: #f8f9fa;
            justify-content: space-between;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
            text-decoration: none;
        }

        .btn-primary { background: var(--primaria); color: #fff; }
        .btn-success { background: var(--sucesso); color: #fff; }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-outline { background: transparent; border: 2px solid var(--primaria); color: var(--primaria); }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        /* Estilos para impressão */
        @media print {
            body { 
                background: white; 
                font-size: 12pt;
            }

            aside.sidebar-container,
            header.top-header,
            .page-header-row,
            .action-buttons {
                display: none !important;
            }

            main.main-content {
                margin-left: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }

            .resumo-container {
                box-shadow: none;
                border: 1px solid #ddd;
            }

            .resumo-header {
                background: #f8f9fa !important;
                color: #333 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                border-bottom: 2px solid #333;
            }

            .status-badge {
                border: 1px solid #333;
            }

            .saldo-devedor {
                background: #f0f0f0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .table-produtos thead {
                background: #f0f0f0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .table-produtos tfoot {
                background: #f8f9fa !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .btn {
                display: none !important;
            }

            .resumo-container {
                page-break-inside: avoid;
            }

            .info-section {
                page-break-inside: avoid;
            }
        }

        /* Slide lateral de configuração */
        .slide-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9998;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .slide-overlay.active {
            display: block;
            opacity: 1;
        }

        .slide-panel {
            position: fixed;
            top: 0;
            right: -450px;
            width: 450px;
            height: 100vh;
            background: #fff;
            box-shadow: -4px 0 20px rgba(0,0,0,0.2);
            z-index: 9999;
            transition: right 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .slide-panel.active {
            right: 0;
        }

        .slide-header {
            background: #f8f9fa;
            padding: 20px 24px;
            border-bottom: 1px solid #e0e0e0;
        }

        .slide-header h3 {
            font-size: 20px;
            color: #333;
            margin: 0;
        }

        .slide-body {
            flex: 1;
            padding: 24px;
            overflow-y: auto;
        }

        .form-group-slide {
            margin-bottom: 24px;
        }

        .form-group-slide label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            margin-bottom: 12px;
        }

        .tipo-impressao-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .tipo-impressao-item {
            padding: 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .tipo-impressao-item:hover {
            border-color: var(--primaria);
            background: #f8f9fa;
        }

        .tipo-impressao-item.selected {
            border-color: var(--primaria);
            background: linear-gradient(135deg, #e8f4f8 0%, #d4e9f2 100%);
        }

        .tipo-impressao-item input[type="radio"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .tipo-impressao-item label {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            cursor: pointer;
            margin: 0;
        }

        .orientacao-group {
            display: flex;
            gap: 12px;
        }

        .orientacao-btn {
            flex: 1;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            background: #fff;
            cursor: pointer;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s;
        }

        .orientacao-btn:hover {
            border-color: var(--primaria);
            background: #f8f9fa;
        }

        .orientacao-btn.selected {
            border-color: var(--primaria);
            background: linear-gradient(135deg, #e8f4f8 0%, #d4e9f2 100%);
            color: var(--primaria);
        }

        .slide-footer {
            padding: 20px 24px;
            background: #f8f9fa;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 12px;
        }

        .btn-slide {
            flex: 1;
            padding: 14px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-cancelar-slide {
            background: #e0e0e0;
            color: #666;
        }

        .btn-cancelar-slide:hover {
            background: #d0d0d0;
        }

        .btn-salvar-slide {
            background: var(--sucesso);
            color: #fff;
        }

        .btn-salvar-slide:hover {
            background: #00923e;
            transform: translateY(-2px);
        }

        .restaurar-link {
            display: block;
            text-align: center;
            color: var(--primaria);
            text-decoration: none;
            font-size: 14px;
            margin-top: 16px;
        }

        .restaurar-link:hover {
            text-decoration: underline;
        }

        /* Estilos específicos para impressão Bobina (58mm/80mm) */
        @media print {
            body.print-bobina {
                width: 80mm;
                font-size: 10pt;
            }

            body.print-bobina .resumo-container {
                width: 100%;
                max-width: 80mm;
            }

            body.print-bobina .resumo-header {
                padding: 12px;
            }

            body.print-bobina .resumo-header h2 {
                font-size: 16px;
            }

            body.print-bobina .resumo-grid {
                grid-template-columns: 1fr;
            }

            body.print-bobina .info-section {
                padding: 12px;
            }

            body.print-bobina .table-produtos {
                font-size: 9pt;
            }

            body.print-bobina .table-produtos th,
            body.print-bobina .table-produtos td {
                padding: 6px 4px;
            }
        }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include '../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <div class="page-header-row">
            <h1 class="page-title">
                <i class="fas fa-file-invoice"></i>
                Resumo
            </h1>
            <a href="../vendas.php" class="btn-voltar">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <div class="resumo-container">
            <div class="resumo-header">
                <h2>Código: <?= str_pad($venda['id'], 5, '0', STR_PAD_LEFT) ?></h2>
                
                <div class="resumo-grid">
                    <div class="resumo-item">
                        <label>Responsável:</label>
                        <span><?= htmlspecialchars($venda['nome_cliente'] ?? 'Consumidor Final') ?> <?= $venda['id_cliente'] ? "({$venda['id_cliente']})" : '' ?></span>
                    </div>
                    <div class="resumo-item">
                        <label>Tipo:</label>
                        <span><?= htmlspecialchars($venda['tipo_venda'] ?? 'Presencial') ?></span>
                    </div>
                    <div class="resumo-item">
                        <label>Status:</label>
                        <span class="status-badge badge-<?= strtolower($venda['status_pagamento']) ?>">
                            <?= $venda['status_pagamento'] ?>
                        </span>
                    </div>
                    <div class="resumo-item">
                        <label>Data:</label>
                        <span><?= date('d/m/Y H:i', strtotime($venda['data_cadastro'])) ?></span>
                    </div>
                    <div class="resumo-item">
                        <label>Usuário:</label>
                        <span><?= htmlspecialchars($venda['usuario_vendedor'] ?? 'Sistema') ?></span>
                    </div>
                </div>
            </div>

            <?php if ($venda['status_pagamento'] != 'PAGO'): ?>
            <div class="saldo-devedor">
                <h3>Saldo devedor: R$ <?= number_format($total_liquido, 2, ',', '.') ?></h3>
                <small style="color: #666;">(ver detalhes)</small>
            </div>
            <?php endif; ?>

            <?php if ($venda['id_cliente']): ?>
            <div class="info-section">
                <h3><i class="fas fa-user"></i> <?= htmlspecialchars($venda['nome_paciente'] ?? 'Animal') ?> <?= $venda['id_paciente'] ? "({$venda['id_paciente']})" : '' ?></h3>
                <div class="info-grid">
                    <?php if ($venda['nome_paciente']): ?>
                    <div class="info-field">
                        <label>Animal</label>
                        <span><?= htmlspecialchars($venda['nome_paciente']) ?></span>
                    </div>
                    <div class="info-field">
                        <label>Espécie</label>
                        <span><?= htmlspecialchars($venda['especie'] ?? '-') ?></span>
                    </div>
                    <div class="info-field">
                        <label>Raça</label>
                        <span><?= htmlspecialchars($venda['raca'] ?? '-') ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <h3 style="margin-top: 24px;"><i class="fas fa-map-marker-alt"></i> Endereço</h3>
                <div class="info-grid">
                    <div class="info-field">
                        <label>Endereço</label>
                        <span><?= htmlspecialchars($venda['endereco'] ?? '-') ?></span>
                    </div>
                    <div class="info-field">
                        <label>Telefones</label>
                        <span><?= htmlspecialchars($venda['telefone'] ?? '-') ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="info-section">
                <h3><i class="fas fa-list"></i> Produto / Serviço</h3>
                
                <table class="table-produtos">
                    <thead>
                        <tr>
                            <th>Produto / Serviço</th>
                            <th>Funcionários</th>
                            <th class="text-right">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens as $item): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($item['nome_produto']) ?></strong>
                                <small style="display: block; color: #999;">(<?= $item['id_produto'] ?>)</small>
                            </td>
                            <td><?= htmlspecialchars($venda['usuario_vendedor'] ?? 'Sistema') ?></td>
                            <td class="text-right">R$ <?= number_format($item['valor_total'], 2, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" class="text-right">Total líquido</td>
                            <td class="text-right">R$ <?= number_format($total_liquido, 2, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <td colspan="2" class="text-right" style="color: var(--danger);">Total em aberto</td>
                            <td class="text-right" style="color: var(--danger);">R$ <?= $venda['status_pagamento'] != 'PAGO' ? number_format($total_liquido, 2, ',', '.') : '0,00' ?></td>
                        </tr>
                    </tfoot>
                </table>

                <?php if ($venda['observacoes']): ?>
                <div class="obs-box">
                    <h4>Observações</h4>
                    <p><?= nl2br(htmlspecialchars($venda['observacoes'])) ?></p>
                </div>
                <?php endif; ?>
            </div>

            <div class="action-buttons">
                <div style="display: flex; gap: 12px;">
                    <button class="btn btn-primary">
                        <i class="fas fa-edit"></i> Editar
                    </button>
                    <button class="btn btn-outline">
                        <i class="fas fa-trash"></i>
                    </button>
                    <button class="btn btn-outline">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <div style="display: flex; gap: 12px;">
                    <?php if ($venda['status_pagamento'] != 'PAGO'): ?>
                    <button class="btn btn-outline">
                        <i class="fas fa-percentage"></i> Conceder desconto
                    </button>
                    <button class="btn btn-success">
                        <i class="fas fa-check"></i> Registrar recebimento
                    </button>
                    <?php endif; ?>
                    <button class="btn btn-outline">
                        <i class="fas fa-envelope"></i>
                    </button>
                    <button class="btn btn-outline" onclick="imprimirResumo()">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                    <button class="btn btn-outline" onclick="abrirConfigImpressao()">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <a href="../vendas.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                </div>
            </div>
        </div>

    </main>

    <!-- Slide Lateral - Configuração de Impressão -->
    <div class="slide-overlay" id="slideOverlay" onclick="fecharConfigImpressao()"></div>
    <div class="slide-panel" id="slidePanel">
        <div class="slide-header">
            <h3>Configuração de impressão</h3>
        </div>
        
        <div class="slide-body">
            <div class="form-group-slide">
                <label>Escolha o tipo de impressão padrão para demonstrativos de venda orçamento:</label>
                
                <div class="tipo-impressao-list">
                    <div class="tipo-impressao-item" onclick="selecionarTipo('a4')">
                        <input type="radio" name="tipo_impressao" id="tipo_a4" value="a4">
                        <label for="tipo_a4">A4</label>
                    </div>
                    
                    <div class="tipo-impressao-item" onclick="selecionarTipo('a5')">
                        <input type="radio" name="tipo_impressao" id="tipo_a5" value="a5">
                        <label for="tipo_a5">A5</label>
                    </div>
                    
                    <div class="tipo-impressao-item selected" onclick="selecionarTipo('bobina')">
                        <input type="radio" name="tipo_impressao" id="tipo_bobina" value="bobina" checked>
                        <label for="tipo_bobina">Bobina</label>
                    </div>
                    
                    <div class="tipo-impressao-item" onclick="selecionarTipo('bobina_economica')">
                        <input type="radio" name="tipo_impressao" id="tipo_bobina_eco" value="bobina_economica">
                        <label for="tipo_bobina_eco">Bobina Econômica</label>
                    </div>
                </div>
            </div>

            <div class="form-group-slide">
                <label>Orientação:</label>
                <div class="orientacao-group">
                    <div class="orientacao-btn" onclick="selecionarOrientacao('esquerda')">
                        <i class="fas fa-align-left"></i> Esquerda
                    </div>
                    <div class="orientacao-btn selected" onclick="selecionarOrientacao('direita')">
                        <i class="fas fa-align-right"></i> Direita
                    </div>
                </div>
            </div>

            <a href="#" class="restaurar-link" onclick="restaurarPadrao(); return false;">Restaurar padrão</a>
        </div>

        <div class="slide-footer">
            <button class="btn-slide btn-salvar-slide" onclick="salvarConfigImpressao()">Salvar</button>
            <button class="btn-slide btn-cancelar-slide" onclick="fecharConfigImpressao()">Cancelar</button>
        </div>
    </div>

    <script>
        // Configuração padrão de impressão
        let configImpressao = {
            tipo: 'bobina',
            orientacao: 'direita'
        };

        // Carregar configuração salva do localStorage
        function carregarConfigImpressao() {
            const saved = localStorage.getItem('config_impressao_venda');
            if (saved) {
                configImpressao = JSON.parse(saved);
                aplicarConfigNaInterface();
            }
        }

        // Aplicar configuração na interface
        function aplicarConfigNaInterface() {
            // Selecionar tipo
            document.querySelectorAll('.tipo-impressao-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            const tipoElement = document.querySelector(`#tipo_${configImpressao.tipo}`);
            if (tipoElement) {
                tipoElement.checked = true;
                tipoElement.closest('.tipo-impressao-item').classList.add('selected');
            }

            // Selecionar orientação
            document.querySelectorAll('.orientacao-btn').forEach(btn => {
                btn.classList.remove('selected');
            });
            
            const orientacaoBtn = Array.from(document.querySelectorAll('.orientacao-btn'))
                .find(btn => btn.textContent.toLowerCase().includes(configImpressao.orientacao));
            if (orientacaoBtn) {
                orientacaoBtn.classList.add('selected');
            }
        }

        // Abrir slide de configuração
        function abrirConfigImpressao() {
            document.getElementById('slideOverlay').classList.add('active');
            document.getElementById('slidePanel').classList.add('active');
            aplicarConfigNaInterface();
        }

        // Fechar slide de configuração
        function fecharConfigImpressao() {
            document.getElementById('slideOverlay').classList.remove('active');
            document.getElementById('slidePanel').classList.remove('active');
        }

        // Selecionar tipo de impressão
        function selecionarTipo(tipo) {
            configImpressao.tipo = tipo;
            
            // Atualizar visual
            document.querySelectorAll('.tipo-impressao-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            const radioButton = document.getElementById('tipo_' + tipo);
            if (radioButton) {
                radioButton.checked = true;
                radioButton.closest('.tipo-impressao-item').classList.add('selected');
            }
        }

        // Selecionar orientação
        function selecionarOrientacao(orientacao) {
            configImpressao.orientacao = orientacao;
            
            // Atualizar visual
            document.querySelectorAll('.orientacao-btn').forEach(btn => {
                btn.classList.remove('selected');
            });
            
            event.target.closest('.orientacao-btn').classList.add('selected');
        }

        // Restaurar configuração padrão
        function restaurarPadrao() {
            configImpressao = {
                tipo: 'bobina',
                orientacao: 'direita'
            };
            aplicarConfigNaInterface();
        }

        // Salvar configuração
        function salvarConfigImpressao() {
            localStorage.setItem('config_impressao_venda', JSON.stringify(configImpressao));
            fecharConfigImpressao();
            
            // Feedback visual
            const btn = event.target;
            const originalText = btn.textContent;
            btn.textContent = 'Salvo!';
            btn.style.background = '#00a65a';
            
            setTimeout(() => {
                btn.textContent = originalText;
                btn.style.background = '';
            }, 1500);
        }

        // Imprimir com configuração
        function imprimirResumo() {
            // Remover classes anteriores
            document.body.classList.remove('print-a4', 'print-a5', 'print-bobina', 'print-bobina-economica');
            
            // Aplicar classe conforme configuração
            switch(configImpressao.tipo) {
                case 'a4':
                    document.body.classList.add('print-a4');
                    break;
                case 'a5':
                    document.body.classList.add('print-a5');
                    break;
                case 'bobina':
                    document.body.classList.add('print-bobina');
                    break;
                case 'bobina_economica':
                    document.body.classList.add('print-bobina-economica');
                    break;
            }
            
            // Configurar CSS para orientação
            if (configImpressao.orientacao === 'esquerda') {
                document.body.style.textAlign = 'left';
            } else {
                document.body.style.textAlign = 'right';
            }
            
            // Imprimir
            window.print();
            
            // Resetar após impressão
            setTimeout(() => {
                document.body.style.textAlign = '';
            }, 500);
        }

        // Carregar configuração ao iniciar
        carregarConfigImpressao();
    </script>

</body>
</html>