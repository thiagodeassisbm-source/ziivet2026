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

// Define relative path for includes
$path_prefix = '../';

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
    
    <!-- Standard System CSS -->
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/menu.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Specific Styles for this Page */
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
            font-family: 'Exo', sans-serif;
        }

        .btn-voltar {
            background: #5a6c7d;
            color: #fff;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
            font-size: 14px;
            font-family: 'Exo', sans-serif;
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
            border: 1px solid #e0e0e0;
        }

        .resumo-header {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: #fff;
            padding: 24px 30px;
        }

        .resumo-header h2 {
            font-size: 24px;
            margin-bottom: 8px;
            font-family: 'Exo', sans-serif;
            font-weight: 700;
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
            font-size: 11px;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-family: 'Exo', sans-serif;
            font-weight: 600;
        }

        .resumo-item span {
            font-size: 15px;
            font-weight: 600;
            color: #fff;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            font-family: 'Exo', sans-serif;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            text-align: center;
            width: fit-content;
        }

        .saldo-devedor {
            background: #fff5f5;
            padding: 20px 30px;
            border-left: 4px solid #dc3545;
            margin: 0;
            border-bottom: 1px solid #eee;
        }

        .saldo-devedor h3 {
            font-size: 18px;
            color: #dc3545;
            margin-bottom: 4px;
            font-family: 'Exo', sans-serif;
        }

        .info-section {
            padding: 30px;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-section:last-child {
            border-bottom: none;
        }

        .info-section h3 {
            font-size: 16px;
            color: #1e40af;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Exo', sans-serif;
            font-weight: 700;
            text-transform: uppercase;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .info-field label {
            font-size: 11px;
            font-weight: 700;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-family: 'Exo', sans-serif;
        }

        .info-field span {
            font-size: 14px;
            color: #2c3e50;
            font-weight: 600;
        }

        .table-produtos {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Source Sans Pro', sans-serif;
        }

        .table-produtos thead th {
            padding: 12px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: #6c757d;
            text-transform: uppercase;
            background: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
            font-family: 'Exo', sans-serif;
        }

        .table-produtos tbody td {
            padding: 16px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
            color: #333;
        }

        .table-produtos tfoot td {
            padding: 16px;
            font-weight: 700;
            font-size: 15px;
            background: #fdfdfd;
            border-top: 2px solid #e0e0e0;
        }

        .obs-box {
            background: #feeff2;
            padding: 16px;
            border-radius: 8px;
            border-left: 4px solid #ef3b2d; /* Cor da Tata */
            margin-top: 25px;
        }

        .obs-box h4 {
            font-size: 12px;
            color: #ef3b2d;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-weight: 700;
        }

        .action-container {
            padding: 20px 30px;
            background: #f8f9fa;
            border-top: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn-action-group {
            display: flex;
            gap: 10px;
        }

        /* Slide Overlay Styles */
        .slide-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1050;
            backdrop-filter: blur(2px);
        }

        .slide-overlay.active { display: block; }

        .slide-panel {
            position: fixed;
            top: 0;
            right: -450px;
            width: 450px;
            height: 100vh;
            background: #fff;
            box-shadow: -5px 0 25px rgba(0,0,0,0.15);
            z-index: 1060;
            transition: right 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            display: flex;
            flex-direction: column;
        }

        .slide-panel.active { right: 0; }

        .slide-header {
            padding: 20px 25px;
            background: #1e40af;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .slide-header h3 {
            font-size: 18px;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
            margin: 0;
            color: white; /* Ensure text is white on blue bg */
        }
        
        .btn-close-slide {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }
        
        .btn-close-slide:hover {
            background: rgba(255,255,255,0.3);
        }

        .slide-body {
            padding: 25px;
            overflow-y: auto;
            flex: 1;
        }
        
        .slide-footer {
            padding: 20px 25px;
            border-top: 1px solid #eee;
            background: #f8f9fa;
        }

        .tipo-impressao-item {
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            transition: 0.2s;
        }

        .tipo-impressao-item.selected {
            border-color: #1e40af;
            background: #f0f7ff;
        }
        
        /* Print Styles */
        @media print {
            aside, header, .page-header-row, .action-container, .btn { display: none !important; }
            main { margin-left: 0; padding: 0; }
            .resumo-container { border: none; box-shadow: none; }
            body { background: white; }
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

            <div class="action-container">
                <div class="btn-action-group">
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
                <div class="btn-action-group">
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
                        <i class="fas fa-cog"></i>
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
            <button class="btn-close-slide" onclick="fecharConfigImpressao()">
                <i class="fas fa-times"></i>
            </button>
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