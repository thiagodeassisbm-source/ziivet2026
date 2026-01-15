<?php
/**
 * =========================================================================================
 * ZIIPVET - CADASTRO/EDIÇÃO DE PRODUTOS E SERVIÇOS
 * ARQUIVO: produtos.php
 * VERSÃO: 4.0.0 - PADRÃO MODERNO COM ABAS
 * =========================================================================================
 */

// Ativação de erros (remover em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php'; 
require_once 'config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;

// ==========================================================
// FUNÇÕES AUXILIARES
// ==========================================================
function limparDinheiro($val) {
    if(empty($val)) return 0.00;
    $val = str_replace(['R$', ' ', '%'], '', $val);
    if(strpos($val, ',') !== false) {
        $val = str_replace('.', '', $val);
        $val = str_replace(',', '.', $val);
    }
    return (float)$val;
}

function fmtDinheiro($val) { 
    return $val ? 'R$ ' . number_format($val, 2, ',', '.') : ''; 
}

function fmtPerc($val) { 
    return $val ? number_format($val, 2, ',', '') . '%' : ''; 
}

// ==========================================================
// LÓGICA DE PROCESSAMENTO (POST)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_clean(); 
    header('Content-Type: application/json');
    
    $acao = $_POST['acao'] ?? '';

    // --- CADASTROS RÁPIDOS (MODAL) ---
    if (in_array($acao, ['add_marca', 'add_categoria', 'add_comissao', 'add_unidade'])) {
        $mapeamento = [
            'add_marca'     => ['tabela' => 'marcas', 'coluna' => 'nome_marca'],
            'add_categoria' => ['tabela' => 'categorias_produtos', 'coluna' => 'nome_categoria'],
            'add_comissao'  => ['tabela' => 'comissoes_grupos', 'coluna' => 'nome_grupo'],
            'add_unidade'   => ['tabela' => 'unidades_medida', 'coluna' => 'nome_unidade']
        ];

        $nome = mb_strtoupper(trim($_POST['nome_novo'] ?? ''), 'UTF-8');
        if (empty($nome)) { 
            echo json_encode(['status'=>'error', 'message'=>'O nome não pode estar vazio.']); 
            exit; 
        }

        try {
            $t = $mapeamento[$acao]['tabela'];
            $c = $mapeamento[$acao]['coluna'];
            $stmt = $pdo->prepare("INSERT INTO $t ($c) VALUES (?)");
            $stmt->execute([$nome]);
            echo json_encode([
                'status'=>'success', 
                'id'=>$pdo->lastInsertId(), 
                'nome'=>$nome, 
                'message'=>'Cadastrado com sucesso!'
            ]);
        } catch (PDOException $e) { 
            echo json_encode(['status'=>'error', 'message'=>'Erro ao cadastrar: '.$e->getMessage()]); 
        }
        exit;
    }

    // --- SALVAR PRODUTO ---
    if ($acao === 'salvar_produto') {
        try {
            if (empty($_POST['nome'])) throw new Exception("O campo Nome é obrigatório.");
            if (empty($_POST['categoria'])) throw new Exception("Selecione uma Categoria.");
            
            // Tratamento de dados
            $id_prod = $_POST['id_produto'] ?? ''; 
            $monitorar = isset($_POST['chk_monitorar']) ? 1 : 0;
            $bloquear = isset($_POST['bloquear_comissao']) ? 1 : 0;
            $reter_iss = isset($_POST['reter_iss']) ? 1 : 0;
            
            $custo = limparDinheiro($_POST['custo'] ?? '0');
            $margem = limparDinheiro($_POST['margem'] ?? '0');
            $venda = limparDinheiro($_POST['venda'] ?? '0');
            $iss = limparDinheiro($_POST['iss'] ?? '0');
            
            $marca = !empty($_POST['marca']) ? $_POST['marca'] : null;
            $validade = !empty($_POST['dr_validade']) ? $_POST['dr_validade'] : null;

            // Array de parâmetros base
            $params = [
                $_POST['nome'] ?? '', 
                $_POST['tipo'] ?? 'Produto', 
                $_POST['unidade'] ?? 'UN', 
                $_POST['gtin'] ?? '', 
                $_POST['sku'] ?? '', 
                $marca, 
                $_POST['categoria'], 
                $_POST['comissao'] ?? null,
                $custo, 
                $margem, 
                $venda, 
                $monitorar, 
                $bloquear,
                $_POST['ncm'] ?? '', 
                $_POST['cfop'] ?? '', 
                $_POST['origem'] ?? '', 
                $_POST['csosn'] ?? '', 
                $_POST['pis'] ?? '', 
                $_POST['cofins'] ?? '', 
                $_POST['cest'] ?? '', 
                $_POST['ipi'] ?? '',
                $_POST['discriminacao'] ?? '', 
                $iss, 
                $reter_iss, 
                $_POST['cnae'] ?? '', 
                $_POST['cod_servico'] ?? '', 
                $_POST['cod_trib_mun'] ?? '',
                $_POST['obs'] ?? '', 
                $_POST['status'] ?? 'ATIVO', 
                $validade
            ];

            if (!empty($id_prod) && is_numeric($id_prod)) {
                // UPDATE
                $sql = "UPDATE produtos SET 
                    nome=?, tipo=?, unidade=?, gtin=?, sku=?, marca=?, id_categoria=?, id_comissao=?, 
                    preco_custo=?, margem=?, preco_venda=?, monitorar_estoque=?, bloquear_comissao=?, 
                    ncm=?, cfop=?, origem_csosn=?, csosn=?, cst_pis=?, cst_cofins=?, cest=?, cst_ipi=?,
                    discriminacao=?, aliquota_iss=?, reter_iss=?, cnae=?, cod_servico=?, cod_trib_mun=?, 
                    observacoes=?, status=?, data_validade=?
                    WHERE id=? AND id_admin=?";
                $params[] = $id_prod;
                $params[] = $id_admin;
                $msg = "Produto atualizado com sucesso!";
            } else {
                // INSERT
                $sql = "INSERT INTO produtos (
                    nome, tipo, unidade, gtin, sku, marca, id_categoria, id_comissao, 
                    preco_custo, margem, preco_venda, monitorar_estoque, bloquear_comissao, 
                    ncm, cfop, origem_csosn, csosn, cst_pis, cst_cofins, cest, cst_ipi,
                    discriminacao, aliquota_iss, reter_iss, cnae, cod_servico, cod_trib_mun, 
                    observacoes, status, data_validade, id_admin
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $params[] = $id_admin;
                $msg = "Produto cadastrado com sucesso!";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['status' => 'success', 'message' => $msg]);

        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Erro: ' . $e->getMessage()]);
        }
        exit;
    }
}

// ==========================================================
// CARREGAMENTO DE DADOS PARA EDIÇÃO (GET)
// ==========================================================
$dados = []; 
$editando = false;
$titulo_pagina = "Novo Produto/Serviço";

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ? AND id_admin = ?");
        $stmt->execute([$_GET['id'], $id_admin]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($resultado) {
            $dados = $resultado;
            $editando = true;
            $titulo_pagina = "Editar Produto/Serviço";
        }
    } catch (PDOException $e) { /* Erro silencioso */ }
}

// ==========================================================
// CARREGAMENTO DE LISTAS (Para Selects)
// ==========================================================
try {
    $categorias = $pdo->query("SELECT * FROM categorias_produtos ORDER BY nome_categoria ASC")->fetchAll(PDO::FETCH_ASSOC);
    $comissoes = $pdo->query("SELECT * FROM comissoes_grupos ORDER BY nome_grupo ASC")->fetchAll(PDO::FETCH_ASSOC);
    $marcas = $pdo->query("SELECT * FROM marcas ORDER BY nome_marca ASC")->fetchAll(PDO::FETCH_ASSOC);
    $unidades = $pdo->query("SELECT * FROM unidades_medida ORDER BY nome_unidade ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { 
    $categorias = $comissoes = $marcas = $unidades = []; 
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
           ESTILOS ESPECÍFICOS DO FORMULÁRIO
        ======================================== */
        
        /* Sistema de Abas */
        .tabs-container {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
        }
        
        .tabs-header {
            display: flex;
            background: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .tab-button {
            flex: 1;
            padding: 18px 24px;
            background: transparent;
            border: none;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Exo', sans-serif;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .tab-button:hover {
            background: rgba(98, 37, 153, 0.05);
            color: #131c71;
        }
        
        .tab-button.active {
            background: #fff;
            color: #131c71;
            border-bottom-color: #131c71;
        }
        
        .tab-button i {
            font-size: 18px;
        }
        
        .tabs-content {
            padding: 30px;
        }
        
        .tab-pane {
            display: none;
        }
        
        .tab-pane.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Toggle de Status no Header */
        .form-header-extended {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .status-toggle {
            display: inline-flex;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 4px;
            border: 2px solid #e0e0e0;
            gap: 4px;
        }
        
        .status-btn {
            border: none;
            padding: 10px 24px;
            font-weight: 700;
            font-size: 14px;
            font-family: 'Exo', sans-serif;
            cursor: pointer;
            border-radius: 8px;
            color: #6c757d;
            background: transparent;
            transition: all 0.3s ease;
            text-transform: uppercase;
        }
        
        .status-btn.active {
            background: #28A745; /* COR DO BOTAO ATIVO */
            color: #fff;
            box-shadow: 0 2px 8px rgba(98, 37, 153, 0.3);
        }
        
        .status-btn:hover:not(.active) {
            background: rgba(98, 37, 153, 0.1);
            color: #131c71;
        }
        
        /* Input com botão + inline */
        .input-with-button {
            display: flex;
            gap: 8px;
            align-items: stretch;
        }
        
        .input-with-button .form-control {
            flex: 1;
        }
        
        .btn-add-inline {
            width: 45px;
            height: 45px;
            background: #28A745;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }
        
        .btn-add-inline:hover {
            background: #4a1d75;
            transform: scale(1.05);
        }
        
        /* Switch Personalizado */
        .switch-group {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
        }
        
        .switch {
            position: relative;
            display: inline-block;
            width: 54px;
            height: 28px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .3s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #622599;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .switch-label {
            font-size: 15px;
            font-weight: 600;
            color: #2c3e50;
            cursor: pointer;
            user-select: none;
        }
        
        /* Seção Fiscal Colapsável */
        .fiscal-section {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            margin-top: 20px;
            overflow: hidden;
        }
        
        .fiscal-header {
            padding: 18px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            background: #fff;
            transition: background 0.2s;
            user-select: none;
        }
        
        .fiscal-header:hover {
            background: #f8f9fa;
        }
        
        .fiscal-header-title {
            font-weight: 700;
            font-size: 17px;
            color: #2c3e50;
            font-family: 'Exo', sans-serif;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .fiscal-header-title i {
            color: #622599;
        }
        
        .fiscal-body {
            padding: 25px;
            background: #fff;
            border-top: 2px solid #e0e0e0;
            display: none;
        }
        
        .fiscal-body.show {
            display: block;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                max-height: 0;
            }
            to {
                opacity: 1;
                max-height: 2000px;
            }
        }
        
        /* Campos Condicionais (Produto/Serviço) */
        .campo-produto,
        .campos-fiscal-produto,
        .campos-fiscal-servico {
            transition: all 0.3s ease;
        }
        
        /* Preço de Venda Destacado */
        .preco-venda-destaque {
            font-weight: 700 !important;
            font-size: 18px !important;
            color: #622599 !important;
            border: 2px solid #622599 !important;
            background: linear-gradient(135deg, #fff 0%, #f8f4fc 100%) !important;
        }
        
        /* Modal Rápido */
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
            animation: fadeIn 0.2s ease;
        }
        
        .modal-content {
            background: #fff;
            margin: 10% auto;
            padding: 35px;
            border-radius: 16px;
            width: 90%;
            max-width: 480px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-title {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 25px;
            font-family: 'Exo', sans-serif;
            text-align: center;
        }
        
        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }
        
        .modal-actions button {
            flex: 1;
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .tabs-header {
                flex-direction: column;
            }
            
            .tab-button {
                border-bottom: none;
                border-left: 3px solid transparent;
            }
            
            .tab-button.active {
                border-left-color: #622599;
            }
            
            .form-header-extended {
                flex-direction: column;
                align-items: stretch;
            }
            
            .status-toggle {
                width: 100%;
            }
            
            .status-btn {
                flex: 1;
            }
        }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include 'menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include 'menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <!-- HEADER: Título, Status e Botão Voltar -->
        <div class="form-header-extended">
            <div>
                <h1 class="form-title">
                    <i class="fas fa-box"></i>
                    <?= $titulo_pagina ?>
                </h1>
            </div>
            
            <div style="display: flex; gap: 15px; align-items: center;">
                <div class="status-toggle">
                    <button type="button" class="status-btn" id="btn-inativo" onclick="setStatus('INATIVO')">
                        Inativo
                    </button>
                    <button type="button" class="status-btn active" id="btn-ativo" onclick="setStatus('ATIVO')">
                        Ativo
                    </button>
                </div>
                
                <a href="listar_produtos.php" class="btn-voltar">
                    <i class="fas fa-arrow-left"></i>
                    Voltar para Lista
                </a>
            </div>
        </div>

        <!-- FORMULÁRIO COM ABAS -->
        <form id="formProduto">
            <input type="hidden" name="acao" value="salvar_produto">
            <input type="hidden" name="id_produto" value="<?= $dados['id'] ?? '' ?>">
            <input type="hidden" name="status" id="input_status" value="<?= $dados['status'] ?? 'ATIVO' ?>">
            
            <div class="tabs-container">
                <!-- CABEÇALHO DAS ABAS -->
                <div class="tabs-header">
                    <button type="button" class="tab-button active" onclick="trocarAba(event, 'aba-basico')">
                        <i class="fas fa-info-circle"></i>
                        Dados Básicos
                    </button>
                    <button type="button" class="tab-button" onclick="trocarAba(event, 'aba-precos')">
                        <i class="fas fa-dollar-sign"></i>
                        Preços e Estoque
                    </button>
                    <button type="button" class="tab-button" onclick="trocarAba(event, 'aba-fiscal')">
                        <i class="fas fa-file-invoice"></i>
                        Dados Fiscais
                    </button>
                </div>
                
                <!-- CONTEÚDO DAS ABAS -->
                <div class="tabs-content">
                    
                    <!-- ABA 1: DADOS BÁSICOS -->
                    <div id="aba-basico" class="tab-pane active">
                        <div class="form-grid">
                            <div class="form-group full">
                                <label class="required">
                                    <i class="fas fa-tag"></i>
                                    Nome do Produto/Serviço
                                </label>
                                <input type="text" 
                                       name="nome" 
                                       class="form-control" 
                                       required 
                                       placeholder="Digite o nome completo do item"
                                       value="<?= htmlspecialchars($dados['nome'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group half">
                                <label>
                                    <i class="fas fa-layer-group"></i>
                                    Tipo
                                </label>
                                <select name="tipo" id="select_tipo" class="form-control" onchange="toggleCamposTipo()">
                                    <option value="Produto" <?= ($dados['tipo'] ?? 'Produto') == 'Produto' ? 'selected' : '' ?>>Produto</option>
                                    <option value="Servico" <?= ($dados['tipo'] ?? '') == 'Servico' ? 'selected' : '' ?>>Serviço</option>
                                </select>
                            </div>

                            <div class="form-group half campo-produto">
                                <label>
                                    <i class="fas fa-balance-scale"></i>
                                    Unidade de Medida
                                </label>
                                <div class="input-with-button">
                                    <select name="unidade" id="select_unidade" class="form-control">
                                        <?php foreach($unidades as $u): ?> 
                                            <option value="<?= $u['nome_unidade'] ?>" <?= ($dados['unidade'] ?? 'UN') == $u['nome_unidade'] ? 'selected' : '' ?>>
                                                <?= $u['nome_unidade'] ?>
                                            </option> 
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn-add-inline" onclick="abrirModal('Unidade', 'add_unidade', 'select_unidade')" title="Adicionar Nova Unidade">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="form-group half campo-produto">
                                <label>
                                    <i class="fas fa-barcode"></i>
                                    Código de Barras (GTIN/EAN)
                                </label>
                                <input type="text" 
                                       name="gtin" 
                                       class="form-control" 
                                       placeholder="789123456789"
                                       value="<?= htmlspecialchars($dados['gtin'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group half">
                                <label>
                                    <i class="fas fa-hashtag"></i>
                                    SKU / Código Interno
                                </label>
                                <input type="text" 
                                       name="sku" 
                                       class="form-control" 
                                       placeholder="Ex: PROD-001"
                                       value="<?= htmlspecialchars($dados['sku'] ?? '') ?>">
                            </div>

                            <div class="form-group half campo-produto">
                                <label>
                                    <i class="fas fa-copyright"></i>
                                    Marca
                                </label>
                                <div class="input-with-button">
                                    <select name="marca" id="select_marca" class="form-control">
                                        <option value="">Selecione uma marca</option>
                                        <?php foreach($marcas as $m): ?> 
                                            <option value="<?= $m['id'] ?>" <?= ($dados['marca'] ?? '') == $m['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($m['nome_marca']) ?>
                                            </option> 
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn-add-inline" onclick="abrirModal('Marca', 'add_marca', 'select_marca')" title="Adicionar Nova Marca">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="form-group half">
                                <label class="required">
                                    <i class="fas fa-folder"></i>
                                    Categoria
                                </label>
                                <div class="input-with-button">
                                    <select name="categoria" id="select_categoria" class="form-control" required>
                                        <option value="">Selecione uma categoria</option>
                                        <?php foreach($categorias as $c): ?> 
                                            <option value="<?= $c['id'] ?>" <?= ($dados['id_categoria'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($c['nome_categoria']) ?>
                                            </option> 
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn-add-inline" onclick="abrirModal('Categoria', 'add_categoria', 'select_categoria')" title="Adicionar Nova Categoria">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="form-group half campo-produto">
                                <label>
                                    <i class="fas fa-calendar-alt"></i>
                                    Data de Validade
                                </label>
                                <input type="date" 
                                       name="dr_validade" 
                                       class="form-control"
                                       value="<?= $dados['data_validade'] ?? '' ?>">
                            </div>
                            
                            <div class="form-group full">
                                <label>
                                    <i class="fas fa-comment-alt"></i>
                                    Observações
                                </label>
                                <textarea name="obs" 
                                          class="form-control" 
                                          rows="4"
                                          placeholder="Informações adicionais sobre o produto ou serviço"><?= htmlspecialchars($dados['observacoes'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ABA 2: PREÇOS E ESTOQUE -->
                    <div id="aba-precos" class="tab-pane">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-coins"></i>
                                    Preço de Custo
                                </label>
                                <input type="text" 
                                       id="custo" 
                                       name="custo" 
                                       class="form-control" 
                                       placeholder="R$ 0,00"
                                       value="<?= fmtDinheiro($dados['preco_custo'] ?? '') ?>" 
                                       onkeyup="calcularVenda()">
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-percentage"></i>
                                    Margem de Lucro (%)
                                </label>
                                <input type="text" 
                                       id="margem" 
                                       name="margem" 
                                       class="form-control" 
                                       placeholder="0%"
                                       value="<?= fmtPerc($dados['margem'] ?? '') ?>" 
                                       onkeyup="calcularVenda()">
                            </div>
                            
                            <div class="form-group half">
                                <label>
                                    <i class="fas fa-dollar-sign"></i>
                                    Preço de Venda
                                </label>
                                <input type="text" 
                                       id="venda" 
                                       name="venda" 
                                       class="form-control preco-venda-destaque" 
                                       placeholder="R$ 0,00"
                                       value="<?= fmtDinheiro($dados['preco_venda'] ?? '') ?>">
                            </div>

                            <div class="form-group full">
                                <label>
                                    <i class="fas fa-money-check-alt"></i>
                                    Grupo de Comissão
                                </label>
                                <div class="input-with-button">
                                    <select name="comissao" id="select_comissao" class="form-control">
                                        <option value="">Selecione um grupo</option>
                                        <?php foreach($comissoes as $g): ?> 
                                            <option value="<?= $g['id'] ?>" <?= ($dados['id_comissao'] ?? '') == $g['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($g['nome_grupo']) ?>
                                            </option> 
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn-add-inline" onclick="abrirModal('Grupo de Comissão', 'add_comissao', 'select_comissao')" title="Adicionar Novo Grupo">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="form-group full campo-produto">
                                <div class="switch-group">
                                    <label class="switch">
                                        <input type="checkbox" 
                                               name="chk_monitorar" 
                                               <?= ($dados['monitorar_estoque'] ?? 1) == 1 ? 'checked' : '' ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <span class="switch-label">
                                        <i class="fas fa-boxes"></i>
                                        Monitorar estoque deste produto
                                    </span>
                                </div>
                            </div>
                            
                            <div class="form-group full">
                                <div class="switch-group">
                                    <label class="switch">
                                        <input type="checkbox" 
                                               name="bloquear_comissao" 
                                               <?= ($dados['bloquear_comissao'] ?? 0) == 1 ? 'checked' : '' ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <span class="switch-label">
                                        <i class="fas fa-ban"></i>
                                        Bloquear comissão para este item
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ABA 3: DADOS FISCAIS -->
                    <div id="aba-fiscal" class="tab-pane">
                        <div class="form-grid">
                            
                            <!-- CAMPOS FISCAIS - PRODUTO -->
                            <div id="campos-fiscal-produto" class="full">
                                <h3 style="font-size: 20px; font-weight: 700; color: #2c3e50; margin-bottom: 20px; font-family: 'Exo', sans-serif;">
                                    <i class="fas fa-box"></i>
                                    Informações Fiscais - Produto
                                </h3>
                                
                                <div class="form-grid">
                                    <div class="form-group half">
                                        <label><i class="fas fa-file-alt"></i> NCM</label>
                                        <input type="text" name="ncm" class="form-control" value="<?= htmlspecialchars($dados['ncm'] ?? '') ?>" placeholder="00000000">
                                    </div>
                                    <div class="form-group half">
                                        <label><i class="fas fa-code"></i> CFOP</label>
                                        <input type="text" name="cfop" class="form-control" value="<?= htmlspecialchars($dados['cfop'] ?? '') ?>" placeholder="0000">
                                    </div>
                                    <div class="form-group half">
                                        <label><i class="fas fa-globe-americas"></i> Origem CSOSN</label>
                                        <select name="origem" class="form-control">
                                            <option value="0" <?= ($dados['origem_csosn'] ?? '0') == '0' ? 'selected' : '' ?>>0 - Nacional</option>
                                            <option value="1">1 - Estrangeira (Importação direta)</option>
                                            <option value="2">2 - Estrangeira (Adquirida no mercado interno)</option>
                                        </select>
                                    </div>
                                    <div class="form-group half">
                                        <label><i class="fas fa-receipt"></i> CSOSN</label>
                                        <select name="csosn" class="form-control">
                                            <option value="101" <?= ($dados['csosn'] ?? '101') == '101' ? 'selected' : '' ?>>101 - Tributada pelo Simples Nacional</option>
                                            <option value="102">102 - Tributada pelo Simples Nacional sem crédito</option>
                                            <option value="103">103 - Isenção do ICMS</option>
                                        </select>
                                    </div>
                                    <div class="form-group half">
                                        <label><i class="fas fa-percent"></i> CST PIS</label>
                                        <select name="pis" class="form-control">
                                            <option value="01" <?= ($dados['cst_pis'] ?? '01') == '01' ? 'selected' : '' ?>>01 - Operação Tributável</option>
                                            <option value="04">04 - Operação Tributável Monofásica</option>
                                            <option value="06">06 - Operação Tributável Alíquota Zero</option>
                                        </select>
                                    </div>
                                    <div class="form-group half">
                                        <label><i class="fas fa-percent"></i> CST COFINS</label>
                                        <select name="cofins" class="form-control">
                                            <option value="01" <?= ($dados['cst_cofins'] ?? '01') == '01' ? 'selected' : '' ?>>01 - Operação Tributável</option>
                                            <option value="04">04 - Operação Tributável Monofásica</option>
                                            <option value="06">06 - Operação Tributável Alíquota Zero</option>
                                        </select>
                                    </div>
                                    <div class="form-group half">
                                        <label><i class="fas fa-barcode"></i> CEST</label>
                                        <input type="text" name="cest" class="form-control" value="<?= htmlspecialchars($dados['cest'] ?? '') ?>" placeholder="0000000">
                                    </div>
                                    <div class="form-group half">
                                        <label><i class="fas fa-industry"></i> CST IPI</label>
                                        <input type="text" name="ipi" class="form-control" value="<?= htmlspecialchars($dados['cst_ipi'] ?? '') ?>" placeholder="00">
                                    </div>
                                </div>
                            </div>

                            <!-- CAMPOS FISCAIS - SERVIÇO -->
                            <div id="campos-fiscal-servico" class="full" style="display: none;">
                                <h3 style="font-size: 20px; font-weight: 700; color: #2c3e50; margin-bottom: 20px; font-family: 'Exo', sans-serif;">
                                    <i class="fas fa-concierge-bell"></i>
                                    Informações Fiscais - Serviço
                                </h3>
                                
                                <div class="form-grid">
                                    <div class="form-group full">
                                        <label><i class="fas fa-align-left"></i> Discriminação do Serviço</label>
                                        <textarea name="discriminacao" class="form-control" rows="3" placeholder="Descrição detalhada do serviço prestado"><?= htmlspecialchars($dados['discriminacao'] ?? '') ?></textarea>
                                    </div>
                                    <div class="form-group half">
                                        <label><i class="fas fa-percentage"></i> Alíquota ISS (%)</label>
                                        <input type="text" name="iss" class="form-control" value="<?= fmtPerc($dados['aliquota_iss'] ?? '') ?>" placeholder="0%">
                                    </div>
                                    <div class="form-group half">
                                        <div class="switch-group" style="padding-top: 30px;">
                                            <label class="switch">
                                                <input type="checkbox" name="reter_iss" <?= ($dados['reter_iss'] ?? 0) == 1 ? 'checked' : '' ?>>
                                                <span class="slider"></span>
                                            </label>
                                            <span class="switch-label">Reter ISS na fonte</span>
                                        </div>
                                    </div>
                                    <div class="form-group full">
                                        <label><i class="fas fa-building"></i> CNAE</label>
                                        <input type="text" name="cnae" class="form-control" value="<?= htmlspecialchars($dados['cnae'] ?? '') ?>" placeholder="0000-0/00">
                                    </div>
                                    <div class="form-group half">
                                        <label><i class="fas fa-list-ol"></i> Código de Serviço LC 116/2003</label>
                                        <input type="text" name="cod_servico" class="form-control" value="<?= htmlspecialchars($dados['cod_servico'] ?? '') ?>" placeholder="00.00">
                                    </div>
                                    <div class="form-group half">
                                        <label><i class="fas fa-city"></i> Código Tributação Município</label>
                                        <input type="text" name="cod_trib_mun" class="form-control" value="<?= htmlspecialchars($dados['cod_trib_mun'] ?? '') ?>" placeholder="000000">
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                    
                </div>
            </div>
            
            <!-- BOTÕES DE AÇÃO -->
            <div class="form-actions">
                <button type="button" onclick="salvarProduto()" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Produto
                </button>
                <a href="listar_produtos.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </main>

    <!-- MODAL RÁPIDO -->
    <div id="modalRapido" class="modal-overlay">
        <div class="modal-content">
            <h3 id="modal_titulo" class="modal-title"></h3>
            <div class="form-group">
                <input type="text" 
                       id="modal_input_nome" 
                       class="form-control" 
                       placeholder="Digite o nome..." 
                       style="height: 48px; font-size: 16px;">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="fecharModal()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="button" class="btn btn-primary" id="btn_modal_confirmar">
                    <i class="fas fa-check"></i> Adicionar
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // ==========================================================
        // INICIALIZAÇÃO
        // ==========================================================
        window.onload = function() {
            const statusAtual = document.getElementById('input_status').value;
            setStatus(statusAtual);
            toggleCamposTipo();
            
            // Se houver dados fiscais, marca como preenchido
            const ncm = "<?= $dados['ncm'] ?? '' ?>";
            const cnae = "<?= $dados['cnae'] ?? '' ?>";
            if(ncm || cnae) {
                // Dados fiscais já preenchidos
            }
        };

        // ==========================================================
        // SISTEMA DE ABAS
        // ==========================================================
        function trocarAba(event, abaId) {
            event.preventDefault();
            
            // Remove active de todos os botões e abas
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
            
            // Adiciona active no botão clicado e na aba correspondente
            event.currentTarget.classList.add('active');
            document.getElementById(abaId).classList.add('active');
        }

        // ==========================================================
        // TOGGLE STATUS
        // ==========================================================
        function setStatus(val) {
            document.getElementById('input_status').value = val;
            const btnAtivo = document.getElementById('btn-ativo');
            const btnInativo = document.getElementById('btn-inativo');
            
            if(val === 'ATIVO') {
                btnAtivo.classList.add('active');
                btnInativo.classList.remove('active');
            } else {
                btnInativo.classList.add('active');
                btnAtivo.classList.remove('active');
            }
        }

        // ==========================================================
        // TOGGLE CAMPOS TIPO (PRODUTO/SERVIÇO)
        // ==========================================================
        function toggleCamposTipo() {
            const tipo = document.getElementById('select_tipo').value;
            const camposProduto = document.querySelectorAll('.campo-produto');
            const fiscalProduto = document.getElementById('campos-fiscal-produto');
            const fiscalServico = document.getElementById('campos-fiscal-servico');

            if (tipo === 'Servico') {
                camposProduto.forEach(el => el.style.display = 'none');
                fiscalProduto.style.display = 'none';
                fiscalServico.style.display = 'block';
            } else {
                camposProduto.forEach(el => el.style.display = 'flex');
                fiscalProduto.style.display = 'block';
                fiscalServico.style.display = 'none';
            }
        }

        // ==========================================================
        // CÁLCULO AUTOMÁTICO DE PREÇO DE VENDA
        // ==========================================================
        function calcularVenda() {
            let custo = parseFloat(document.getElementById('custo').value.replace('R$ ', '').replace(/\./g, '').replace(',', '.')) || 0;
            let margem = parseFloat(document.getElementById('margem').value.replace('%', '').replace(',', '.')) || 0;
            let venda = custo + (custo * (margem / 100));
            document.getElementById('venda').value = 'R$ ' + venda.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        // ==========================================================
        // MODAL RÁPIDO
        // ==========================================================
        function abrirModal(label, acao, selectId) {
            document.getElementById('modal_titulo').innerText = 'Adicionar ' + label;
            document.getElementById('modalRapido').style.display = 'block';
            document.getElementById('modal_input_nome').value = '';
            document.getElementById('modal_input_nome').focus();
            
            document.getElementById('btn_modal_confirmar').onclick = async function() {
                const nome = document.getElementById('modal_input_nome').value.trim();
                if(!nome) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Atenção',
                        text: 'Digite um nome válido!',
                        confirmButtonColor: '#622599'
                    });
                    return;
                }
                
                const formData = new FormData();
                formData.append('acao', acao);
                formData.append('nome_novo', nome);
                
                try {
                    const res = await fetch('produtos.php', { method: 'POST', body: formData });
                    const data = await res.json();
                    
                    if(data.status === 'success') {
                        const select = document.getElementById(selectId);
                        const opt = document.createElement('option');
                        opt.value = data.id;
                        opt.text = data.nome;
                        opt.selected = true;
                        select.add(opt);
                        
                        fecharModal();
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: data.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: data.message,
                            confirmButtonColor: '#622599'
                        });
                    }
                } catch(e) { 
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: 'Erro ao processar requisição',
                        confirmButtonColor: '#622599'
                    });
                }
            };
        }

        function fecharModal() {
            document.getElementById('modalRapido').style.display = 'none';
        }

        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            const modal = document.getElementById('modalRapido');
            if (event.target == modal) {
                fecharModal();
            }
        }

        // Enter no input do modal
        document.getElementById('modal_input_nome').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('btn_modal_confirmar').click();
            }
        });

        // ==========================================================
        // SALVAR PRODUTO
        // ==========================================================
        async function salvarProduto() {
            const formData = new FormData(document.getElementById('formProduto'));
            const btn = event.target;
            const txtOriginal = btn.innerHTML;
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
            btn.disabled = true;

            try {
                const res = await fetch('produtos.php', { method: 'POST', body: formData });
                const text = await res.text();
                
                try {
                    const data = JSON.parse(text);
                    
                    if (data.status === 'success') {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: data.message,
                            confirmButtonColor: '#622599'
                        });
                        window.location.href = 'listar_produtos.php';
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: data.message,
                            confirmButtonColor: '#622599'
                        });
                    }
                } catch (e) {
                    console.error("Resposta inválida do servidor:", text);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro no servidor',
                        text: 'Ocorreu um erro ao processar a resposta. Verifique o console.',
                        confirmButtonColor: '#622599'
                    });
                }
            } catch (e) { 
                Swal.fire({
                    icon: 'error',
                    title: 'Erro de Conexão',
                    text: 'Não foi possível conectar ao servidor.',
                    confirmButtonColor: '#622599'
                });
            } finally {
                btn.innerHTML = txtOriginal;
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>