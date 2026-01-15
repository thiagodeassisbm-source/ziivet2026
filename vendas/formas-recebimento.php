<?php
/**
 * =========================================================================================
 * ZIIPVET - FORMAS DE RECEBIMENTO
 * ARQUIVO: formas-recebimento.php
 * LOCALIZAÇÃO: /app/vendas/
 * VERSÃO: 2.0 - COM GERENCIAMENTO DE BANDEIRAS SEPARADO
 * =========================================================================================
 */

$base_path = dirname(__DIR__) . '/';

require_once $base_path . 'auth.php';
require_once $base_path . 'config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;

// ==========================================================
// PROCESSAMENTO - SALVAR BANDEIRAS
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar_bandeiras') {
    header('Content-Type: application/json');
    ob_clean();
    
    try {
        $pdo->beginTransaction();
        
        $id = $_POST['id'];
        $bandeiras = json_decode($_POST['bandeiras'], true);
        
        // Buscar configurações existentes
        $stmt = $pdo->prepare("SELECT configuracoes FROM formas_pagamento WHERE id = ? AND id_admin = ?");
        $stmt->execute([$id, $id_admin]);
        $forma = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $config = json_decode($forma['configuracoes'], true) ?: [];
        $config['bandeiras'] = $bandeiras;
        
        $sql = "UPDATE formas_pagamento SET configuracoes = ? WHERE id = ? AND id_admin = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([json_encode($config), $id, $id_admin]);
        
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Bandeiras atualizadas com sucesso!']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// PROCESSAMENTO - SALVAR FORMA DE RECEBIMENTO
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    header('Content-Type: application/json');
    ob_clean();
    
    try {
        $pdo->beginTransaction();
        
        if ($_POST['acao'] === 'salvar') {
            $id = $_POST['id'] ?? null;
            $tipo = $_POST['tipo'];
            $nome = $_POST['nome'];
            $permite_troco = isset($_POST['permite_troco']) ? 1 : 0;
            
            // Dados específicos por tipo
            $dados_extras = [];
            
            if ($tipo === 'Boleto' || $tipo === 'Depósito, Transferência') {
                $dados_extras['id_conta_destino'] = $_POST['id_conta_destino'] ?? null;
                $dados_extras['baixa_automatica'] = $_POST['baixa_automatica'] ?? 'Não';
            }
            
            if ($tipo === 'Maquininha de cartão') {
                $dados_extras['empresa_maquininha'] = $_POST['empresa_maquininha'] ?? null;
                $dados_extras['id_conta_destino'] = $_POST['id_conta_destino'] ?? null;
                $dados_extras['prazo_recebimento'] = $_POST['prazo_recebimento'] ?? null;
                $dados_extras['antecipacao_automatica'] = $_POST['antecipacao_automatica'] ?? 'Não';
                $dados_extras['aceita_pix'] = $_POST['aceita_pix'] ?? 'Não';
                
                // Salvar bandeiras e taxas
                if (!empty($_POST['bandeiras'])) {
                    $dados_extras['bandeiras'] = json_decode($_POST['bandeiras'], true);
                }
            }
            
            if ($tipo === 'Pix') {
                $dados_extras['id_conta_destino'] = $_POST['id_conta_destino'] ?? null;
            }
            
            $dados_json = json_encode($dados_extras);
            
            if ($id) {
                // Atualizar
                $sql = "UPDATE formas_pagamento SET 
                        nome_forma = ?,
                        tipo = ?,
                        permite_troco = ?,
                        configuracoes = ?
                        WHERE id = ? AND id_admin = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nome, $tipo, $permite_troco, $dados_json, $id, $id_admin]);
                $message = 'Forma de recebimento atualizada com sucesso!';
            } else {
                // Inserir
                $sql = "INSERT INTO formas_pagamento (id_admin, nome_forma, tipo, permite_troco, configuracoes) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id_admin, $nome, $tipo, $permite_troco, $dados_json]);
                $message = 'Forma de recebimento cadastrada com sucesso!';
            }
            
            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => $message]);
        }
        
        if ($_POST['acao'] === 'excluir') {
            $id = $_POST['id'];
            $sql = "DELETE FROM formas_pagamento WHERE id = ? AND id_admin = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id, $id_admin]);
            
            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Forma de recebimento excluída!']);
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// BUSCAR DADOS PARA EDIÇÃO
// ==========================================================
if (isset($_GET['acao']) && $_GET['acao'] === 'buscar' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM formas_pagamento WHERE id = ? AND id_admin = ?");
    $stmt->execute([$id, $id_admin]);
    $forma = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($forma && !empty($forma['configuracoes'])) {
        $forma['configuracoes'] = json_decode($forma['configuracoes'], true);
    }
    
    echo json_encode($forma);
    exit;
}

// ==========================================================
// LISTAR FORMAS DE RECEBIMENTO
// ==========================================================
$stmt = $pdo->prepare("SELECT * FROM formas_pagamento WHERE id_admin = ? ORDER BY nome_forma ASC");
$stmt->execute([$id_admin]);
$formas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar contas financeiras para os selects
$contas = $pdo->query("SELECT id, nome_conta FROM contas_financeiras WHERE id_admin = $id_admin AND status = 'Ativo' ORDER BY nome_conta")->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = "Formas de Recebimento";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    
    <link rel="stylesheet" href="<?= URL_BASE ?>css/style.css">
    <link rel="stylesheet" href="<?= URL_BASE ?>css/menu.css">
    <link rel="stylesheet" href="<?= URL_BASE ?>css/header.css">
    <link rel="stylesheet" href="<?= URL_BASE ?>css/formularios.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        :root {
            --roxo: #622599;
            --azul: #17a2b8;
            --verde: #28a745;
            --laranja: #f39c12;
        }
        
        body {
            font-family: 'Exo', sans-serif;
            background: #ecf0f5;
        }
        
        /* LISTA */
        .list-container {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        
        .form-item-wrapper {
            border-bottom: 1px solid #f0f0f0;
        }
        
        .form-item-wrapper:last-child {
            border-bottom: none;
        }
        
        .form-item {
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s;
        }
        
        .form-item:hover {
            background: #f8f9fa;
        }
        
        .form-item.selected {
            background: #e3f2fd;
        }
        
        .form-name {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        /* BANDEIRAS NA LISTAGEM */
        .bandeiras-list {
            padding: 0 20px 20px 20px;
            background: #f8f9fa;
            border-top: 2px dashed #e0e0e0;
        }
        
        .bandeiras-header {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 15px 0 10px 0;
            font-size: 14px;
            color: var(--roxo);
            font-weight: 700;
        }
        
        .bandeiras-header i {
            font-size: 16px;
        }
        
        .bandeiras-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 12px;
        }
        
        .bandeira-mini-card {
            background: #fff;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px;
            transition: all 0.2s;
        }
        
        .bandeira-mini-card:hover {
            border-color: var(--roxo);
            box-shadow: 0 2px 8px rgba(98, 37, 153, 0.1);
        }
        
        .bandeira-mini-header {
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .bandeira-mini-nome {
            font-weight: 700;
            font-size: 14px;
            color: #333;
        }
        
        .bandeira-mini-taxas {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }
        
        .taxa-mini {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            padding: 6px 8px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .taxa-mini-label {
            color: #666;
            font-weight: 600;
        }
        
        .taxa-mini-valor {
            color: var(--roxo);
            font-weight: 700;
        }
        
        .bandeiras-empty {
            padding: 20px;
            text-align: center;
            color: #999;
            font-size: 13px;
            background: #fff;
            border: 2px dashed #e0e0e0;
            border-radius: 8px;
            margin-top: 10px;
        }
        
        .bandeiras-empty i {
            margin-right: 5px;
            color: var(--laranja);
        }
        
        .btn-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-icon {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-bandeiras {
            background: var(--laranja);
            color: #fff;
        }
        
        .btn-bandeiras:hover {
            background: #e08e0b;
            transform: translateY(-2px);
        }
        
        .btn-edit {
            background: var(--azul);
            color: #fff;
        }
        
        .btn-edit:hover {
            background: #138496;
            transform: translateY(-2px);
        }
        
        .btn-delete {
            background: #dc3545;
            color: #fff;
        }
        
        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        /* SLIDE LATERAL */
        .slide-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9998;
            display: none;
        }
        
        .slide-overlay.show {
            display: block;
        }
        
        .slide-panel {
            position: fixed;
            top: 0;
            right: -700px;
            width: 700px;
            height: 100%;
            background: #fff;
            z-index: 9999;
            box-shadow: -4px 0 12px rgba(0,0,0,0.15);
            transition: right 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        
        .slide-panel.show {
            right: 0;
        }
        
        .slide-header {
            background: linear-gradient(135deg, var(--roxo), #8e44ad);
            color: #fff;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .slide-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
        }
        
        .btn-close-slide {
            background: transparent;
            border: none;
            color: #fff;
            font-size: 24px;
            cursor: pointer;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .slide-body {
            flex: 1;
            overflow-y: auto;
            padding: 25px;
        }
        
        .slide-footer {
            padding: 20px 25px;
            border-top: 2px solid #f0f0f0;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        
        /* BANDEIRAS */
        .bandeira-card {
            background: #fff;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .bandeira-card:hover {
            border-color: var(--roxo);
            box-shadow: 0 4px 12px rgba(98, 37, 153, 0.1);
        }
        
        .bandeira-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .bandeira-title {
            font-weight: 700;
            font-size: 18px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .bandeira-tipo {
            font-size: 12px;
            background: var(--roxo);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-remove-bandeira {
            background: #dc3545;
            color: #fff;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .btn-remove-bandeira:hover {
            background: #c82333;
            transform: scale(1.1);
        }
        
        .taxas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 12px;
        }
        
        .taxa-item {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .taxa-label {
            font-size: 12px;
            font-weight: 700;
            color: #666;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .taxa-input {
            width: 100%;
            padding: 8px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .taxa-input:focus {
            border-color: var(--roxo);
            outline: none;
        }
        
        .max-parcelas-group {
            margin-bottom: 20px;
        }
        
        .max-parcelas-group label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: #444;
            margin-bottom: 8px;
        }
        
        .max-parcelas-select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
        }
        
        .btn-add-bandeira {
            background: linear-gradient(135deg, var(--verde), #218838);
            color: #fff;
            border: none;
            padding: 15px 25px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 15px;
            width: 100%;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
        }
        
        .btn-add-bandeira:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--roxo), #8e44ad);
            color: #fff;
            border: none;
            padding: 14px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            font-size: 15px;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: #fff;
            border: none;
            padding: 14px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            font-size: 15px;
        }
        
        .empty-bandeiras {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-bandeiras i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        /* ✅ FORÇAR SWEETALERT2 ACIMA DO SLIDE */
        .swal2-container {
            z-index: 10000 !important;
        }
        
        .swal2-popup {
            z-index: 10001 !important;
        }
        
        /* FORMULÁRIO PADRÃO */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: #444;
            margin-bottom: 8px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 15px;
            font-family: 'Exo', sans-serif;
        }
        
        .form-control:focus {
            border-color: var(--roxo);
            outline: none;
        }
        
        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .radio-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .radio-item:hover {
            border-color: var(--roxo);
            background: #f9f9f9;
        }
        
        .radio-item input[type="radio"] {
            width: 20px;
            height: 20px;
        }
        
        .radio-item.checked {
            border-color: var(--roxo);
            background: #f3e5f5;
        }
        
        .toggle-group {
            display: flex;
            gap: 0;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .toggle-btn {
            flex: 1;
            padding: 12px;
            background: #fff;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .toggle-btn.active {
            background: var(--roxo);
            color: #fff;
        }
        
        .info-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include $base_path . 'menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include $base_path . 'menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <div class="form-header">
            <h1 class="form-title">
                <i class="fas fa-credit-card"></i>
                Formas de Recebimento
            </h1>
            <button onclick="abrirSlide()" class="btn-voltar">
                <i class="fas fa-plus"></i> Nova Forma
            </button>
        </div>

        <div class="list-container">
            <?php if(empty($formas)): ?>
                <div style="text-align: center; padding: 60px 20px; color: #999;">
                    <i class="fas fa-credit-card" style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;"></i>
                    <h3>Nenhuma forma de recebimento cadastrada</h3>
                    <p>Clique em "Nova Forma" para adicionar.</p>
                </div>
            <?php else: ?>
                <?php foreach($formas as $forma): 
                    $config = !empty($forma['configuracoes']) ? json_decode($forma['configuracoes'], true) : [];
                    $bandeiras = isset($config['bandeiras']) ? $config['bandeiras'] : [];
                ?>
                    <div class="form-item-wrapper" id="wrapper-<?= $forma['id'] ?>">
                        <div class="form-item" id="item-<?= $forma['id'] ?>">
                            <div class="form-name">
                                <?= htmlspecialchars($forma['nome_forma']) ?>
                                <span style="font-size: 12px; color: #999; margin-left: 10px;"><?= $forma['tipo'] ?></span>
                            </div>
                            <div class="btn-actions">
                                <?php if($forma['tipo'] === 'Maquininha de cartão'): ?>
                                    <button class="btn-icon btn-bandeiras" onclick="gerenciarBandeiras(<?= $forma['id'] ?>, '<?= htmlspecialchars($forma['nome_forma']) ?>')" title="Bandeiras e Taxas">
                                        <i class="fas fa-credit-card"></i>
                                    </button>
                                <?php endif; ?>
                                <button class="btn-icon btn-edit" onclick="editarForma(<?= $forma['id'] ?>)" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon btn-delete" onclick="excluirForma(<?= $forma['id'] ?>, '<?= htmlspecialchars($forma['nome_forma']) ?>')" title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        
                        <?php if($forma['tipo'] === 'Maquininha de cartão' && !empty($bandeiras)): ?>
                            <div class="bandeiras-list">
                                <div class="bandeiras-header">
                                    <i class="fas fa-credit-card"></i>
                                    <strong>Bandeiras e Taxas Cadastradas</strong>
                                </div>
                                <div class="bandeiras-grid">
                                    <?php foreach($bandeiras as $bandeira): 
                                        $tipo = $bandeira['tipo'] ?? 'Crédito';
                                        $parcelas = $bandeira['parcelas'] ?? [];
                                    ?>
                                        <div class="bandeira-mini-card">
                                            <div class="bandeira-mini-header">
                                                <span class="bandeira-mini-nome"><?= htmlspecialchars($bandeira['nome']) ?></span>
                                            </div>
                                            <div class="bandeira-mini-taxas">
                                                <?php if($tipo === 'Débito'): ?>
                                                    <div class="taxa-mini">
                                                        <span class="taxa-mini-label">Taxa:</span>
                                                        <span class="taxa-mini-valor"><?= htmlspecialchars($parcelas['debito'] ?? 'N/A') ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="taxa-mini">
                                                        <span class="taxa-mini-label">À vista:</span>
                                                        <span class="taxa-mini-valor"><?= htmlspecialchars($parcelas['avista'] ?? 'N/A') ?></span>
                                                    </div>
                                                    <?php 
                                                    $maxParcelas = $bandeira['max_parcelas'] ?? 3;
                                                    for($i = 2; $i <= min($maxParcelas, 3); $i++):
                                                    ?>
                                                        <div class="taxa-mini">
                                                            <span class="taxa-mini-label"><?= $i ?>x:</span>
                                                            <span class="taxa-mini-valor"><?= htmlspecialchars($parcelas['p'.$i] ?? 'N/A') ?></span>
                                                        </div>
                                                    <?php endfor; ?>
                                                    <?php if($maxParcelas > 3): ?>
                                                        <div class="taxa-mini">
                                                            <span class="taxa-mini-label">...</span>
                                                            <span class="taxa-mini-valor">até <?= $maxParcelas ?>x</span>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php elseif($forma['tipo'] === 'Maquininha de cartão'): ?>
                            <div class="bandeiras-list">
                                <div class="bandeiras-empty">
                                    <i class="fas fa-info-circle"></i>
                                    Nenhuma bandeira cadastrada. Clique no botão <i class="fas fa-credit-card"></i> para adicionar.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </main>

    <!-- SLIDE LATERAL -->
    <div class="slide-overlay" id="slideOverlay" onclick="fecharSlide()"></div>
    <div class="slide-panel" id="slidePanel">
        <div class="slide-header">
            <h3 id="slideTitle">Título do Slide</h3>
            <button class="btn-close-slide" onclick="fecharSlide()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="slide-body" id="slideBody">
            <!-- CONTEÚDO SERÁ INSERIDO VIA JS -->
        </div>
        
        <div class="slide-footer" id="slideFooter">
            <button class="btn-secondary" onclick="fecharSlide()">Cancelar</button>
            <button class="btn-primary" id="btnSalvar">
                <i class="fas fa-check"></i> Salvar
            </button>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        const EMPRESAS_MAQUININHA = ['Stone', 'Rede', 'Cielo', 'PagSeguro', 'GetNet', 'SafraPay', 'Mercado Pago', 'SumUp', 'Outro'];
        const BANDEIRAS = [
            'Master Card', 'Visa', 'Elo', 'American Express', 
            'Hipercard', 'UnionPay', 'Cabal', 'Diners Club', 
            'Discover', 'JCB'
        ];
        const CONTAS = <?= json_encode($contas) ?>;
        
        let currentFormaId = null;
        let bandeirasData = [];
        let editandoId = null;
        let formData = {};
        let currentStep = 1;
        let maxSteps = 4;
        let modoEdicao = 'forma'; // 'forma' ou 'bandeiras'
        
        // ===== ABRIR SLIDE PARA NOVA FORMA =====
        function abrirSlide() {
            modoEdicao = 'forma';
            editandoId = null;
            currentStep = 1;
            formData = {};
            
            document.getElementById('slideTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Adicionar Forma de Recebimento';
            document.getElementById('slideOverlay').classList.add('show');
            document.getElementById('slidePanel').classList.add('show');
            
            document.getElementById('btnSalvar').onclick = salvarForma;
            
            renderStep1();
        }
        
        // ===== EDITAR FORMA =====
        async function editarForma(id) {
            modoEdicao = 'forma';
            editandoId = id;
            currentStep = 1;
            formData = {};
            
            document.getElementById('slideTitle').innerHTML = '<i class="fas fa-edit"></i> Editar Forma de Recebimento';
            document.getElementById('slideOverlay').classList.add('show');
            document.getElementById('slidePanel').classList.add('show');
            
            document.getElementById('btnSalvar').onclick = salvarForma;
            
            try {
                const response = await fetch(`formas-recebimento.php?acao=buscar&id=${id}`);
                const data = await response.json();
                
                formData = {
                    tipo: data.tipo,
                    nome: data.nome_forma,
                    permite_troco: data.permite_troco,
                    configuracoes: data.configuracoes || {}
                };
                
                renderStep1();
            } catch (e) {
                Swal.fire({
                    title: 'Erro',
                    text: 'Erro ao carregar dados da forma de pagamento',
                    icon: 'error',
                    customClass: { container: 'swal-above-slide' }
                });
            }
        }
        
        // ===== GERENCIAR BANDEIRAS =====
        async function gerenciarBandeiras(id, nome) {
            modoEdicao = 'bandeiras';
            currentFormaId = id;
            
            document.getElementById('slideTitle').innerHTML = `<i class="fas fa-credit-card"></i> ${nome} - Bandeiras e Taxas`;
            document.getElementById('slideOverlay').classList.add('show');
            document.getElementById('slidePanel').classList.add('show');
            
            // Buscar bandeiras existentes
            try {
                const response = await fetch(`formas-recebimento.php?acao=buscar&id=${id}`);
                const data = await response.json();
                
                if (data.configuracoes && data.configuracoes.bandeiras) {
                    bandeirasData = data.configuracoes.bandeiras;
                } else {
                    bandeirasData = [];
                }
                
                renderBandeiras();
                
                // Configurar botão de salvar
                document.getElementById('btnSalvar').onclick = salvarBandeiras;
                
            } catch (e) {
                Swal.fire({
                    title: 'Erro',
                    text: 'Erro ao carregar bandeiras',
                    icon: 'error',
                    customClass: { container: 'swal-above-slide' }
                });
            }
        }
        
        // ===== RENDERIZAR PASSO 1 - TIPO =====
        function renderStep1() {
            const html = `
                <h4 style="margin-bottom: 20px; color: var(--roxo); font-size: 18px; font-weight: 700;">
                    <i class="fas fa-list-alt"></i> Tipo de recebimento
                </h4>
                
                <div class="radio-group">
                    <label class="radio-item ${formData.tipo === 'Boleto' ? 'checked' : ''}" onclick="selecionarTipo('Boleto')">
                        <input type="radio" name="tipo" value="Boleto" ${formData.tipo === 'Boleto' ? 'checked' : ''}>
                        <span><i class="fas fa-barcode"></i> Boleto</span>
                    </label>
                    <label class="radio-item ${formData.tipo === 'Cheque' ? 'checked' : ''}" onclick="selecionarTipo('Cheque')">
                        <input type="radio" name="tipo" value="Cheque" ${formData.tipo === 'Cheque' ? 'checked' : ''}>
                        <span><i class="fas fa-money-check"></i> Cheque</span>
                    </label>
                    <label class="radio-item ${formData.tipo === 'Depósito, Transferência' ? 'checked' : ''}" onclick="selecionarTipo('Depósito, Transferência')">
                        <input type="radio" name="tipo" value="Depósito, Transferência" ${formData.tipo === 'Depósito, Transferência' ? 'checked' : ''}>
                        <span><i class="fas fa-university"></i> Depósito, Transferência</span>
                    </label>
                    <label class="radio-item ${formData.tipo === 'Espécie' ? 'checked' : ''}" onclick="selecionarTipo('Espécie')">
                        <input type="radio" name="tipo" value="Espécie" ${formData.tipo === 'Espécie' ? 'checked' : ''}>
                        <span><i class="fas fa-money-bill-wave"></i> Espécie (Dinheiro)</span>
                    </label>
                    <label class="radio-item ${formData.tipo === 'Maquininha de cartão' ? 'checked' : ''}" onclick="selecionarTipo('Maquininha de cartão')">
                        <input type="radio" name="tipo" value="Maquininha de cartão" ${formData.tipo === 'Maquininha de cartão' ? 'checked' : ''}>
                        <span><i class="fas fa-credit-card"></i> Maquininha de cartão</span>
                    </label>
                    <label class="radio-item ${formData.tipo === 'Pix' ? 'checked' : ''}" onclick="selecionarTipo('Pix')">
                        <input type="radio" name="tipo" value="Pix" ${formData.tipo === 'Pix' ? 'checked' : ''}>
                        <span><i class="fas fa-qrcode"></i> Pix</span>
                    </label>
                    <label class="radio-item ${formData.tipo === 'Outros' ? 'checked' : ''}" onclick="selecionarTipo('Outros')">
                        <input type="radio" name="tipo" value="Outros" ${formData.tipo === 'Outros' ? 'checked' : ''}>
                        <span><i class="fas fa-ellipsis-h"></i> Outros</span>
                    </label>
                </div>
                
                <button type="button" class="btn-primary" style="width: 100%; margin-top: 20px;" onclick="proximoPasso()">
                    Avançar <i class="fas fa-arrow-right"></i>
                </button>
            `;
            document.getElementById('slideBody').innerHTML = html;
        }
        
        function selecionarTipo(tipo) {
            formData.tipo = tipo;
            document.querySelectorAll('.radio-item').forEach(item => item.classList.remove('checked'));
            event.currentTarget.classList.add('checked');
        }
        
        function proximoPasso() {
            if (!formData.tipo) {
                Swal.fire({
                    title: 'Atenção',
                    text: 'Selecione um tipo de recebimento',
                    icon: 'warning',
                    customClass: { container: 'swal-above-slide' }
                });
                return;
            }
            
            // Renderizar próximo passo baseado no tipo
            if (formData.tipo === 'Espécie') {
                renderStepEspecie();
            } else if (formData.tipo === 'Maquininha de cartão') {
                renderStepMaquininha();
            } else if (formData.tipo === 'Pix') {
                renderStepPix();
            } else {
                renderStepPadrao();
            }
        }
        
        // ===== RENDERIZAR PASSO ESPÉCIE =====
        function renderStepEspecie() {
            const html = `
                <h4 style="margin-bottom: 20px; color: var(--roxo); font-size: 18px; font-weight: 700;">
                    <i class="fas fa-money-bill-wave"></i> Configurações - Dinheiro
                </h4>
                
                <div class="form-group">
                    <label>Nome da forma de pagamento*</label>
                    <input type="text" class="form-control" id="nome" value="${formData.nome || 'Dinheiro'}" placeholder="Ex: Dinheiro">
                </div>
                
                <div class="form-group">
                    <label>Permite troco*</label>
                    <div class="toggle-group">
                        <button type="button" class="toggle-btn ${!formData.permite_troco || formData.permite_troco == 1 ? 'active' : ''}" onclick="setPermiteTroco(1)">Sim</button>
                        <button type="button" class="toggle-btn ${formData.permite_troco == 0 ? 'active' : ''}" onclick="setPermiteTroco(0)">Não</button>
                    </div>
                    <small style="color: #666; margin-top: 5px; display: block;">Define se o caixa pode dar troco em dinheiro para o cliente.</small>
                </div>
            `;
            document.getElementById('slideBody').innerHTML = html;
        }
        
        // ===== RENDERIZAR PASSO PIX =====
        function renderStepPix() {
            const html = `
                <h4 style="margin-bottom: 20px; color: var(--roxo); font-size: 18px; font-weight: 700;">
                    <i class="fas fa-qrcode"></i> Configurações - Pix
                </h4>
                
                <div class="form-group">
                    <label>Nome da forma de pagamento*</label>
                    <input type="text" class="form-control" id="nome" value="${formData.nome || 'Pix'}" placeholder="Ex: Pix">
                </div>
                
                <div class="form-group">
                    <label>Conta destino*</label>
                    <select class="form-control" id="id_conta_destino">
                        <option value="">Selecione a conta...</option>
                        ${CONTAS.map(c => `<option value="${c.id}" ${formData.configuracoes?.id_conta_destino == c.id ? 'selected' : ''}>${c.nome_conta}</option>`).join('')}
                    </select>
                    <small style="color: #666; margin-top: 5px; display: block;">Conta onde o dinheiro será depositado.</small>
                </div>
            `;
            document.getElementById('slideBody').innerHTML = html;
        }
        
        // ===== RENDERIZAR PASSO PADRÃO =====
        function renderStepPadrao() {
            const html = `
                <h4 style="margin-bottom: 20px; color: var(--roxo); font-size: 18px; font-weight: 700;">
                    <i class="fas fa-cog"></i> Configurações - ${formData.tipo}
                </h4>
                
                <div class="form-group">
                    <label>Nome da forma de pagamento*</label>
                    <input type="text" class="form-control" id="nome" value="${formData.nome || ''}" placeholder="Ex: ${formData.tipo}">
                </div>
                
                <div class="form-group">
                    <label>Conta destino*</label>
                    <select class="form-control" id="id_conta_destino">
                        <option value="">Selecione a conta...</option>
                        ${CONTAS.map(c => `<option value="${c.id}" ${formData.configuracoes?.id_conta_destino == c.id ? 'selected' : ''}>${c.nome_conta}</option>`).join('')}
                    </select>
                    <small style="color: #666; margin-top: 5px; display: block;">Para cada pagamento recebido, criaremos um lançamento financeiro nessa conta bancária.</small>
                </div>
                
                ${formData.tipo !== 'Cheque' ? `
                <div class="form-group">
                    <label>Baixa automática no financeiro*</label>
                    <div class="toggle-group">
                        <button type="button" class="toggle-btn ${!formData.configuracoes?.baixa_automatica || formData.configuracoes?.baixa_automatica === 'Sim' ? 'active' : ''}" onclick="setBaixaAutomatica('Sim')">Sim</button>
                        <button type="button" class="toggle-btn ${formData.configuracoes?.baixa_automatica === 'Não' ? 'active' : ''}" onclick="setBaixaAutomatica('Não')">Não</button>
                    </div>
                    <div class="info-box" style="margin-top: 15px;">
                        <i class="fas fa-info-circle"></i> Baixar uma venda com esta forma de recebimento significa que o valor foi pago e já entrou na conta bancária.
                    </div>
                </div>
                ` : ''}
            `;
            document.getElementById('slideBody').innerHTML = html;
        }
        
        // ===== RENDERIZAR PASSO MAQUININHA =====
        function renderStepMaquininha() {
            const html = `
                <h4 style="margin-bottom: 20px; color: var(--roxo); font-size: 18px; font-weight: 700;">
                    <i class="fas fa-credit-card"></i> Configurações - Maquininha de Cartão
                </h4>
                
                <div class="form-group">
                    <label>Nome da forma de pagamento*</label>
                    <input type="text" class="form-control" id="nome" value="${formData.nome || ''}" placeholder="Ex: Cartão Débito/Crédito">
                </div>
                
                <div class="form-group">
                    <label>Empresa da maquininha*</label>
                    <select class="form-control" id="empresa_maquininha">
                        <option value="">Selecione...</option>
                        ${EMPRESAS_MAQUININHA.map(e => `<option value="${e}" ${formData.configuracoes?.empresa_maquininha === e ? 'selected' : ''}>${e}</option>`).join('')}
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Conta destino*</label>
                    <select class="form-control" id="id_conta_destino">
                        <option value="">Selecione a conta...</option>
                        ${CONTAS.map(c => `<option value="${c.id}" ${formData.configuracoes?.id_conta_destino == c.id ? 'selected' : ''}>${c.nome_conta}</option>`).join('')}
                    </select>
                    <small style="color: #666; margin-top: 5px; display: block;">Para cada pagamento recebido, criaremos um lançamento financeiro nessa conta bancária.</small>
                </div>
                
                <div class="form-group">
                    <label>Prazo para recebimento no débito*</label>
                    <select class="form-control" id="prazo_recebimento">
                        <option value="">Selecione...</option>
                        <option value="Mesmo dia" ${formData.configuracoes?.prazo_recebimento === 'Mesmo dia' ? 'selected' : ''}>Mesmo dia</option>
                        <option value="1 dia" ${formData.configuracoes?.prazo_recebimento === '1 dia' ? 'selected' : ''}>1 dia</option>
                        <option value="2 dias" ${formData.configuracoes?.prazo_recebimento === '2 dias' ? 'selected' : ''}>2 dias</option>
                        <option value="3 dias" ${formData.configuracoes?.prazo_recebimento === '3 dias' ? 'selected' : ''}>3 dias</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Antecipação automática*</label>
                    <div class="toggle-group">
                        <button type="button" class="toggle-btn ${!formData.configuracoes?.antecipacao_automatica || formData.configuracoes?.antecipacao_automatica === 'Sim' ? 'active' : ''}" onclick="setAntecipacao('Sim')">Sim</button>
                        <button type="button" class="toggle-btn ${formData.configuracoes?.antecipacao_automatica === 'Não' ? 'active' : ''}" onclick="setAntecipacao('Não')">Não</button>
                    </div>
                    <small style="color: #666; margin-top: 5px; display: block;">Se você trabalha com antecipação, basta informar que nós lançaremos as previsões de depósito certinhas.</small>
                </div>
                
                <div class="form-group">
                    <label>A maquininha aceita PIX?*</label>
                    <div class="toggle-group">
                        <button type="button" class="toggle-btn ${!formData.configuracoes?.aceita_pix || formData.configuracoes?.aceita_pix === 'Sim' ? 'active' : ''}" onclick="setAceitaPix('Sim')">Sim</button>
                        <button type="button" class="toggle-btn ${formData.configuracoes?.aceita_pix === 'Não' ? 'active' : ''}" onclick="setAceitaPix('Não')">Não</button>
                    </div>
                    <small style="color: #666; margin-top: 5px; display: block;">Marque "Sim" se você realiza vendas em Pix com a sua maquininha.</small>
                </div>
            `;
            document.getElementById('slideBody').innerHTML = html;
        }
        
        // ===== FUNÇÕES DE CONFIGURAÇÃO =====
        function setPermiteTroco(value) {
            formData.permite_troco = value;
            document.querySelectorAll('.toggle-btn').forEach(btn => btn.classList.remove('active'));
            event.currentTarget.classList.add('active');
        }
        
        function setBaixaAutomatica(value) {
            if (!formData.configuracoes) formData.configuracoes = {};
            formData.configuracoes.baixa_automatica = value;
            event.currentTarget.parentElement.querySelectorAll('.toggle-btn').forEach(btn => btn.classList.remove('active'));
            event.currentTarget.classList.add('active');
        }
        
        function setAntecipacao(value) {
            if (!formData.configuracoes) formData.configuracoes = {};
            formData.configuracoes.antecipacao_automatica = value;
            event.currentTarget.parentElement.querySelectorAll('.toggle-btn').forEach(btn => btn.classList.remove('active'));
            event.currentTarget.classList.add('active');
        }
        
        function setAceitaPix(value) {
            if (!formData.configuracoes) formData.configuracoes = {};
            formData.configuracoes.aceita_pix = value;
            event.currentTarget.parentElement.querySelectorAll('.toggle-btn').forEach(btn => btn.classList.remove('active'));
            event.currentTarget.classList.add('active');
        }
        
        // ===== SALVAR FORMA DE PAGAMENTO =====
        async function salvarForma() {
            // Coletar dados do formulário
            const nome = document.getElementById('nome')?.value;
            const contaDestino = document.getElementById('id_conta_destino')?.value;
            const empresaMaquininha = document.getElementById('empresa_maquininha')?.value;
            const prazoRecebimento = document.getElementById('prazo_recebimento')?.value;
            
            if (!formData.tipo) {
                Swal.fire({
                    title: 'Atenção',
                    text: 'Selecione um tipo de recebimento',
                    icon: 'warning',
                    customClass: { container: 'swal-above-slide' }
                });
                return;
            }
            
            if (!nome) {
                Swal.fire({
                    title: 'Atenção',
                    text: 'Preencha o nome da forma de pagamento',
                    icon: 'warning',
                    customClass: { container: 'swal-above-slide' }
                });
                return;
            }
            
            // Validações específicas por tipo
            if (formData.tipo === 'Maquininha de cartão') {
                if (!empresaMaquininha || !contaDestino || !prazoRecebimento) {
                    Swal.fire({
                        title: 'Atenção',
                        text: 'Preencha todos os campos obrigatórios',
                        icon: 'warning',
                        customClass: { container: 'swal-above-slide' }
                    });
                    return;
                }
                
                if (!formData.configuracoes) formData.configuracoes = {};
                formData.configuracoes.empresa_maquininha = empresaMaquininha;
                formData.configuracoes.prazo_recebimento = prazoRecebimento;
            }
            
            if (contaDestino) {
                if (!formData.configuracoes) formData.configuracoes = {};
                formData.configuracoes.id_conta_destino = contaDestino;
            }
            
            const fd = new FormData();
            fd.append('acao', 'salvar');
            if (editandoId) fd.append('id', editandoId);
            fd.append('tipo', formData.tipo);
            fd.append('nome', nome);
            fd.append('permite_troco', formData.permite_troco || 0);
            
            // Adicionar configurações
            if (formData.configuracoes) {
                Object.keys(formData.configuracoes).forEach(key => {
                    fd.append(key, formData.configuracoes[key]);
                });
            }
            
            try {
                const response = await fetch('formas-recebimento.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await response.json();
                
                fecharSlide();
                
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: data.message,
                        confirmButtonColor: '#28a745'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Erro',
                        text: data.message,
                        icon: 'error'
                    });
                }
            } catch (e) {
                fecharSlide();
                Swal.fire({
                    title: 'Erro',
                    text: 'Erro ao salvar forma de pagamento',
                    icon: 'error'
                });
            }
        }
        
        // ===== FUNÇÕES DE BANDEIRAS =====
        function renderBandeiras() {
            const container = document.getElementById('slideBody');
            
            if (bandeirasData.length === 0) {
                container.innerHTML = `
                    <div class="empty-bandeiras">
                        <i class="fas fa-credit-card"></i>
                        <h3>Nenhuma bandeira cadastrada</h3>
                        <p>Adicione bandeiras de cartão para esta forma de pagamento</p>
                    </div>
                `;
            } else {
                let html = '';
                bandeirasData.forEach((bandeira, index) => {
                    html += renderBandeiraCard(bandeira, index);
                });
                container.innerHTML = html;
            }
            
            container.innerHTML += `
                <button class="btn-add-bandeira" onclick="adicionarBandeira()">
                    <i class="fas fa-plus-circle"></i> Adicionar Nova Bandeira
                </button>
            `;
        }
        
        function renderBandeiraCard(bandeira, index) {
            const maxParcelas = bandeira.max_parcelas || 3;
            const tipo = bandeira.tipo || 'Crédito';
            const parcelas = bandeira.parcelas || {};
            
            let taxasHtml = '';
            
            if (tipo === 'Débito') {
                taxasHtml = `
                    <div class="taxa-item">
                        <div class="taxa-label">Taxa Débito</div>
                        <input type="text" class="taxa-input" value="${parcelas.debito || ''}" 
                               onchange="updateTaxa(${index}, 'debito', this.value)" 
                               placeholder="Ex: 1.84%">
                    </div>
                `;
            } else {
                taxasHtml = `
                    <div class="taxa-item">
                        <div class="taxa-label">À Vista</div>
                        <input type="text" class="taxa-input" value="${parcelas.avista || ''}" 
                               onchange="updateTaxa(${index}, 'avista', this.value)" 
                               placeholder="Ex: 3.50%">
                    </div>
                `;
                
                for (let i = 2; i <= maxParcelas; i++) {
                    taxasHtml += `
                        <div class="taxa-item">
                            <div class="taxa-label">${i}x</div>
                            <input type="text" class="taxa-input" value="${parcelas['p'+i] || ''}" 
                                   onchange="updateTaxa(${index}, 'p${i}', this.value)" 
                                   placeholder="Ex: ${(3.50 + (i*0.15)).toFixed(2)}%">
                        </div>
                    `;
                }
            }
            
            return `
                <div class="bandeira-card" id="bandeira-${index}">
                    <div class="bandeira-header">
                        <div class="bandeira-title">
                            <i class="fas fa-credit-card"></i>
                            ${bandeira.nome}
                        </div>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <span class="bandeira-tipo">${tipo}</span>
                            <button class="btn-remove-bandeira" onclick="removerBandeira(${index})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    ${tipo === 'Crédito' ? `
                    <div class="max-parcelas-group">
                        <label>Máximo de Parcelas</label>
                        <select class="max-parcelas-select" onchange="updateMaxParcelas(${index}, this.value)">
                            ${[2,3,4,5,6,7,8,9,10,11,12].map(n => 
                                `<option value="${n}" ${maxParcelas == n ? 'selected' : ''}>${n}x</option>`
                            ).join('')}
                        </select>
                    </div>
                    ` : ''}
                    
                    <div class="taxas-grid">
                        ${taxasHtml}
                    </div>
                </div>
            `;
        }
        
        function adicionarBandeira() {
            Swal.fire({
                title: 'Nova Bandeira',
                html: `
                    <div style="text-align: left; margin: 20px 0;">
                        <label style="display: block; margin-bottom: 10px; font-weight: 700;">Tipo de Cartão:</label>
                        <select id="tipoBandeira" class="swal2-input" style="width: 100%; margin: 0 0 20px 0;">
                            <option value="Crédito">Crédito</option>
                            <option value="Débito">Débito</option>
                        </select>
                        
                        <label style="display: block; margin-bottom: 10px; font-weight: 700;">Bandeira:</label>
                        <select id="nomeBandeira" class="swal2-input" style="width: 100%; margin: 0;">
                            ${BANDEIRAS.map(b => `<option value="${b}">${b}</option>`).join('')}
                        </select>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Adicionar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#28a745',
                customClass: { container: 'swal-above-slide' },
                preConfirm: () => {
                    return {
                        tipo: document.getElementById('tipoBandeira').value,
                        nome: document.getElementById('nomeBandeira').value
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const tipo = result.value.tipo;
                    const nome = `${result.value.nome} - ${tipo}`;
                    
                    bandeirasData.push({
                        nome: nome,
                        tipo: tipo,
                        max_parcelas: tipo === 'Crédito' ? 3 : null,
                        parcelas: {}
                    });
                    
                    renderBandeiras();
                }
            });
        }
        
        function removerBandeira(index) {
            Swal.fire({
                title: 'Confirmar exclusão?',
                text: `Remover ${bandeirasData[index].nome}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sim, remover',
                cancelButtonText: 'Cancelar',
                customClass: { container: 'swal-above-slide' }
            }).then((result) => {
                if (result.isConfirmed) {
                    bandeirasData.splice(index, 1);
                    renderBandeiras();
                }
            });
        }
        
        function updateMaxParcelas(index, value) {
            bandeirasData[index].max_parcelas = parseInt(value);
            renderBandeiras();
        }
        
        function updateTaxa(index, parcela, value) {
            if (!bandeirasData[index].parcelas) {
                bandeirasData[index].parcelas = {};
            }
            bandeirasData[index].parcelas[parcela] = value;
        }
        
        async function salvarBandeiras() {
            const fd = new FormData();
            fd.append('acao', 'salvar_bandeiras');
            fd.append('id', currentFormaId);
            fd.append('bandeiras', JSON.stringify(bandeirasData));
            
            try {
                const response = await fetch('formas-recebimento.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await response.json();
                
                fecharSlide();
                
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: data.message,
                        confirmButtonColor: '#28a745'
                    }).then(() => {
                        location.reload(); // Recarregar para mostrar as bandeiras
                    });
                } else {
                    Swal.fire({
                        title: 'Erro',
                        text: data.message,
                        icon: 'error'
                    });
                }
            } catch (e) {
                fecharSlide();
                Swal.fire({
                    title: 'Erro',
                    text: 'Erro ao salvar bandeiras',
                    icon: 'error'
                });
            }
        }
        
        function fecharSlide() {
            document.getElementById('slideOverlay').classList.remove('show');
            document.getElementById('slidePanel').classList.remove('show');
        }
        
        async function excluirForma(id, nome) {
            const result = await Swal.fire({
                title: 'Deseja excluir?',
                text: `Forma de recebimento: ${nome}`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar'
            });
            
            if (!result.isConfirmed) return;
            
            const fd = new FormData();
            fd.append('acao', 'excluir');
            fd.append('id', id);
            
            try {
                const response = await fetch('formas-recebimento.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await response.json();
                
                if (data.status === 'success') {
                    document.getElementById('item-' + id).remove();
                    Swal.fire({
                        title: 'Sucesso!',
                        text: data.message,
                        icon: 'success'
                    });
                } else {
                    Swal.fire({
                        title: 'Erro',
                        text: data.message,
                        icon: 'error'
                    });
                }
            } catch (e) {
                Swal.fire({
                    title: 'Erro',
                    text: 'Erro ao excluir',
                    icon: 'error'
                });
            }
        }
    </script>
</body>
</html>