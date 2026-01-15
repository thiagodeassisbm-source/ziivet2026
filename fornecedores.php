<?php
/**
 * =========================================================================================
 * ZIIPVET - CADASTRO/EDIÇÃO DE FORNECEDORES
 * ARQUIVO: fornecedores.php
 * VERSÃO: 4.0.0 - PADRÃO MODERNO COM ABAS
 * =========================================================================================
 */
require_once 'auth.php';
require_once 'config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;

// ==========================================================
// LÓGICA DE PROCESSAMENTO (POST)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    ob_clean();
    
    $id_edicao = $_POST['id_fornecedor'] ?? null;
    
    // Captura de dados do formulário
    $dados = [
        ':status'           => $_POST['status'] ?? 'ATIVO',
        ':tipo_fornecedor'  => $_POST['tipo_fornecedor'] ?? 'Produtos e/ou serviços',
        ':tipo_pessoa'      => $_POST['tipo_pessoa'] ?? 'Fisica',
        ':nome_completo'    => !empty($_POST['nome_completo']) ? $_POST['nome_completo'] : null,
        ':data_nascimento'  => !empty($_POST['data_nascimento']) ? $_POST['data_nascimento'] : null,
        ':cpf'              => !empty($_POST['cpf']) ? $_POST['cpf'] : null,
        ':rg'               => !empty($_POST['rg']) ? $_POST['rg'] : null,
        ':cnpj'             => !empty($_POST['cnpj']) ? $_POST['cnpj'] : null,
        ':razao_social'     => !empty($_POST['razao_social']) ? $_POST['razao_social'] : null,
        ':nome_fantasia'    => !empty($_POST['nome_fantasia']) ? $_POST['nome_fantasia'] : null,
        ':cep'              => !empty($_POST['cep']) ? $_POST['cep'] : null,
        ':endereco'         => !empty($_POST['endereco']) ? $_POST['endereco'] : null,
        ':numero'           => !empty($_POST['numero']) ? $_POST['numero'] : null,
        ':complemento'      => !empty($_POST['complemento']) ? $_POST['complemento'] : null,
        ':bairro'           => !empty($_POST['bairro']) ? $_POST['bairro'] : null,
        ':cidade'           => !empty($_POST['cidade']) ? $_POST['cidade'] : null,
        ':estado'           => !empty($_POST['estado']) ? $_POST['estado'] : null,
        ':ponto_referencia' => !empty($_POST['ponto_referencia']) ? $_POST['ponto_referencia'] : null,
        ':email'            => !empty($_POST['email']) ? $_POST['email'] : null,
        ':site'             => !empty($_POST['site']) ? $_POST['site'] : null,
        ':telefone1'        => !empty($_POST['tel1']) ? $_POST['tel1'] : null,
        ':telefone2'        => !empty($_POST['tel2']) ? $_POST['tel2'] : null,
        ':telefone3'        => !empty($_POST['tel3']) ? $_POST['tel3'] : null,
        ':observacoes'      => !empty($_POST['observacoes']) ? $_POST['observacoes'] : null
    ];

    try {
        if (!empty($id_edicao)) {
            // MODO EDIÇÃO (UPDATE)
            $dados[':id'] = $id_edicao;
            $dados[':id_admin'] = $id_admin;
            
            $sql = "UPDATE fornecedores SET
                        status = :status,
                        tipo_fornecedor = :tipo_fornecedor,
                        tipo_pessoa = :tipo_pessoa,
                        nome_completo = :nome_completo,
                        data_nascimento = :data_nascimento,
                        cpf = :cpf,
                        rg = :rg,
                        cnpj = :cnpj,
                        razao_social = :razao_social,
                        nome_fantasia = :nome_fantasia,
                        cep = :cep,
                        endereco = :endereco,
                        numero = :numero,
                        complemento = :complemento,
                        bairro = :bairro,
                        cidade = :cidade,
                        estado = :estado,
                        ponto_referencia = :ponto_referencia,
                        email = :email,
                        site = :site,
                        telefone1 = :telefone1,
                        telefone2 = :telefone2,
                        telefone3 = :telefone3,
                        observacoes = :observacoes
                    WHERE id = :id AND id_admin = :id_admin";
            
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute($dados)) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Fornecedor atualizado com sucesso!',
                    'id_fornecedor' => $id_edicao
                ]);
            } else {
                throw new Exception("Erro ao atualizar o fornecedor.");
            }
            
        } else {
            // MODO INSERÇÃO (INSERT)
            $dados[':id_admin'] = $id_admin;
            
            $sql = "INSERT INTO fornecedores (
                        id_admin, status, tipo_fornecedor, tipo_pessoa, 
                        nome_completo, data_nascimento, cpf, rg, 
                        cnpj, razao_social, nome_fantasia, 
                        cep, endereco, numero, complemento, bairro, cidade, estado, ponto_referencia,
                        email, site, telefone1, telefone2, telefone3, observacoes
                    ) VALUES (
                        :id_admin, :status, :tipo_fornecedor, :tipo_pessoa, 
                        :nome_completo, :data_nascimento, :cpf, :rg, 
                        :cnpj, :razao_social, :nome_fantasia, 
                        :cep, :endereco, :numero, :complemento, :bairro, :cidade, :estado, :ponto_referencia,
                        :email, :site, :telefone1, :telefone2, :telefone3, :observacoes
                    )";
            
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute($dados)) {
                $id_novo = $pdo->lastInsertId();
                echo json_encode([
                    'status' => 'success', 
                    'message' => 'Fornecedor cadastrado com sucesso!',
                    'id_novo' => $id_novo
                ]);
            } else {
                throw new Exception("Erro ao processar a gravação.");
            }
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// CARREGAMENTO DE DADOS PARA EDIÇÃO (GET)
// ==========================================================
$id_fornecedor = $_GET['id'] ?? null;
$modo_edicao = !empty($id_fornecedor);
$dados_fornecedor = null;
$titulo_pagina = "Novo Fornecedor";

if ($modo_edicao) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM fornecedores WHERE id = :id AND id_admin = :id_admin");
        $stmt->execute([':id' => $id_fornecedor, ':id_admin' => $id_admin]);
        $dados_fornecedor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$dados_fornecedor) {
            die("Fornecedor não encontrado ou você não tem permissão para editá-lo.");
        }
        
        $titulo_pagina = "Editar Fornecedor";
    } catch (Exception $e) {
        die("Erro ao carregar fornecedor: " . $e->getMessage());
    }
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
            background: #28A745;
            color: #fff;
            box-shadow: 0 2px 8px rgba(98, 37, 153, 0.3);
        }
        
        .status-btn:hover:not(.active) {
            background: rgba(98, 37, 153, 0.1);
            color: #28A745;
        }
        
        /* Containers Condicionais */
        .conditional-container {
            display: none;
            animation: slideDown 0.3s ease;
        }
        
        .conditional-container.show {
            display: contents;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
                border-left-color: #28A745;
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
                    <i class="fas fa-truck"></i>
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
                
                <a href="listar_fornecedores.php" class="btn-voltar">
                    <i class="fas fa-arrow-left"></i>
                    Voltar para Lista
                </a>
            </div>
        </div>

        <!-- FORMULÁRIO COM ABAS -->
        <form id="formFornecedor">
            <?php if ($modo_edicao): ?>
                <input type="hidden" name="id_fornecedor" value="<?= htmlspecialchars($dados_fornecedor['id']) ?>">
            <?php endif; ?>
            
            <input type="hidden" name="status" id="input_status" value="<?= $modo_edicao ? htmlspecialchars($dados_fornecedor['status']) : 'ATIVO' ?>">
            
            <div class="tabs-container">
                <!-- CABEÇALHO DAS ABAS -->
                <div class="tabs-header">
                    <button type="button" class="tab-button active" onclick="trocarAba(event, 'aba-dados')">
                        <i class="fas fa-user"></i>
                        Dados Básicos
                    </button>
                    <button type="button" class="tab-button" onclick="trocarAba(event, 'aba-endereco')">
                        <i class="fas fa-map-marker-alt"></i>
                        Endereço
                    </button>
                    <button type="button" class="tab-button" onclick="trocarAba(event, 'aba-contato')">
                        <i class="fas fa-phone"></i>
                        Contato
                    </button>
                </div>
                
                <!-- CONTEÚDO DAS ABAS -->
                <div class="tabs-content">
                    
                    <!-- ABA 1: DADOS BÁSICOS -->
                    <div id="aba-dados" class="tab-pane active">
                        <div class="form-grid">
                            <div class="form-group half">
                                <label class="required">
                                    <i class="fas fa-tags"></i>
                                    Tipo de Fornecedor
                                </label>
                                <select name="tipo_fornecedor" class="form-control" required>
                                    <option value="Produtos e/ou serviços" <?= ($modo_edicao && $dados_fornecedor['tipo_fornecedor'] === 'Produtos e/ou serviços') ? 'selected' : '' ?>>Produtos e/ou Serviços</option>
                                    <option value="Apenas serviços" <?= ($modo_edicao && $dados_fornecedor['tipo_fornecedor'] === 'Apenas serviços') ? 'selected' : '' ?>>Apenas Serviços</option>
                                </select>
                            </div>

                            <div class="form-group half">
                                <label class="required">
                                    <i class="fas fa-id-card"></i>
                                    Tipo de Pessoa
                                </label>
                                <select name="tipo_pessoa" id="select_tipo_pessoa" class="form-control" onchange="alternarCampos()" required>
                                    <option value="Fisica" <?= ($modo_edicao && $dados_fornecedor['tipo_pessoa'] === 'Fisica') ? 'selected' : '' ?>>Pessoa Física</option>
                                    <option value="Juridica" <?= ($modo_edicao && $dados_fornecedor['tipo_pessoa'] === 'Juridica') ? 'selected' : '' ?>>Pessoa Jurídica</option>
                                </select>
                            </div>

                            <!-- CAMPOS PESSOA FÍSICA -->
                            <div id="campos-fisica" class="conditional-container <?= (!$modo_edicao || $dados_fornecedor['tipo_pessoa'] === 'Fisica') ? 'show' : '' ?>">
                                <div class="form-group full">
                                    <label class="required">
                                        <i class="fas fa-user"></i>
                                        Nome Completo
                                    </label>
                                    <input type="text" 
                                           name="nome_completo" 
                                           class="form-control" 
                                           placeholder="Digite o nome completo"
                                           value="<?= $modo_edicao ? htmlspecialchars($dados_fornecedor['nome_completo'] ?? '') : '' ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>
                                        <i class="fas fa-calendar"></i>
                                        Data de Nascimento
                                    </label>
                                    <input type="date" 
                                           name="data_nascimento" 
                                           class="form-control"
                                           value="<?= $modo_edicao ? htmlspecialchars($dados_fornecedor['data_nascimento'] ?? '') : '' ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>
                                        <i class="fas fa-id-badge"></i>
                                        CPF
                                    </label>
                                    <input type="text" 
                                           name="cpf" 
                                           class="form-control mask-cpf" 
                                           placeholder="000.000.000-00"
                                           value="<?= $modo_edicao ? htmlspecialchars($dados_fornecedor['cpf'] ?? '') : '' ?>">
                                </div>
                                
                                <div class="form-group half">
                                    <label>
                                        <i class="fas fa-address-card"></i>
                                        RG
                                    </label>
                                    <input type="text" 
                                           name="rg" 
                                           class="form-control"
                                           value="<?= $modo_edicao ? htmlspecialchars($dados_fornecedor['rg'] ?? '') : '' ?>">
                                </div>
                            </div>

                            <!-- CAMPOS PESSOA JURÍDICA -->
                            <div id="campos-juridica" class="conditional-container <?= ($modo_edicao && $dados_fornecedor['tipo_pessoa'] === 'Juridica') ? 'show' : '' ?>">
                                <div class="form-group">
                                    <label>
                                        <i class="fas fa-file-alt"></i>
                                        CNPJ
                                    </label>
                                    <input type="text" 
                                           name="cnpj" 
                                           class="form-control mask-cnpj" 
                                           placeholder="00.000.000/0000-00"
                                           value="<?= $modo_edicao ? htmlspecialchars($dados_fornecedor['cnpj'] ?? '') : '' ?>">
                                </div>
                                
                                <div class="form-group half">
                                    <label class="required">
                                        <i class="fas fa-building"></i>
                                        Razão Social
                                    </label>
                                    <input type="text" 
                                           name="razao_social" 
                                           class="form-control"
                                           placeholder="Razão Social da empresa"
                                           value="<?= $modo_edicao ? htmlspecialchars($dados_fornecedor['razao_social'] ?? '') : '' ?>">
                                </div>
                                
                                <div class="form-group half">
                                    <label>
                                        <i class="fas fa-store"></i>
                                        Nome Fantasia
                                    </label>
                                    <input type="text" 
                                           name="nome_fantasia" 
                                           class="form-control"
                                           placeholder="Nome Fantasia"
                                           value="<?= $modo_edicao ? htmlspecialchars($dados_fornecedor['nome_fantasia'] ?? '') : '' ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ABA 2: ENDEREÇO -->
                    <div id="aba-endereco" class="tab-pane">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-map-pin"></i>
                                    CEP
                                </label>
                                <input type="text" 
                                       name="cep" 
                                       id="cep" 
                                       class="form-control mask-cep" 
                                       placeholder="00000-000"
                                       value="<?= $modo_edicao ? htmlspecialchars($dados_fornecedor['cep'] ?? '') : '' ?>">
                            </div>
                            
                            <div class="form-group three-quarter">
                                <label>
                                    <i class="fas fa-road"></i>
                                    Endereço
                                </label>
                                <input type="text" 
                                       name="endereco" 
                                       id="logradouro" 
                                       class="form-control"
                                       placeholder="Rua, Avenida, etc."
                                       value="<?= $modo_edicao ? htmlspecialchars($dados_fornecedor['endereco'] ?? '') : '' ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-home"></i>
                                    Número
                                </label>
                                <input type="text" 
                                       name="numero" 
                                       id="numero" 
                                       class="form-control"
                                       value="<?= $modo_edicao ? htmlspecialchars($dados_fornecedor['numero'] ?? '') : '' ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-building"></i>
                                    Complemento
                                </label>
                                <input type="text" 
                                       name="complemento" 
                                       class="form-control"
                                       placeholder="Apto, Sala, etc."
                                       value="<?= $modo_edicao ? htmlspecialchars($dados_fornecedor['complemento'] ?? '') : '' ?>">
                            </div>
                            
                            <div class="form-group half">
                                <label>
                                    <i class="fas fa-map"></i>
                                    Bairro
                                </label>
                                <input type="text" 
                                       name="bairro" 
                                       id="bairro" 
                                       class="form-control"
                                       value="<?= $modo_edicao ? htmlspecialchars($dados_fornecedor['bairro'] ?? '') : '' ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-city"></i>
                                    Cidade
                                </label>
                                <input type="text" 
                                       name="cidade" 
                                       id="localidade" 
                                       class="form-control"
                                       value="<?= $modo_edicao ? htmlspecialchars($dados_fornecedor['cidade'] ?? '') : '' ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-flag"></i>
                                    Estado (UF)
                                </label>
                                <select name="estado" id="uf" class="form-control">
                                    <option value="">Selecione</option>
                                    <?php 
                                    $ufs = ["AC","AL","AP","AM","BA","CE","DF","ES","GO","MA","MT","MS","MG","PA","PB","PR","PE","PI","RJ","RN","RS","RO","RR","SC","SP","SE","TO"]; 
                                    foreach($ufs as $u) {
                                        $selected = ($modo_edicao && $dados_fornecedor['estado'] === $u) ? 'selected' : '';
                                        echo "<option value='$u' $selected>$u</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="form-group full">
                                <label>
                                    <i class="fas fa-map-marked-alt"></i>
                                    Ponto de Referência
                                </label>
                                <input type="text" 
                                       name="ponto_referencia" 
                                       class="form-control"
                                       placeholder="Ex: Próximo ao supermercado"
                                       value="<?= $modo_edicao ? htmlspecialchars($dados_fornecedor['ponto_referencia'] ?? '') : '' ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- ABA 3: CONTATO -->
                    <div id="aba-contato" class="tab-pane">
                        <div class="form-grid">
                            <div class="form-group half">
                                <label>
                                    <i class="fas fa-envelope"></i>
                                    E-mail
                                </label>
                                <input type="email" 
                                       name="email" 
                                       class="form-control"
                                       placeholder="exemplo@email.com"
                                       value="<?= $modo_edicao ? htmlspecialchars($dados_fornecedor['email'] ?? '') : '' ?>">
                            </div>
                            
                            <div class="form-group half">
                                <label>
                                    <i class="fas fa-globe"></i>
                                    Site
                                </label>
                                <input type="text" 
                                       name="site" 
                                       class="form-control"
                                       placeholder="www.exemplo.com.br"
                                       value="<?= $modo_edicao ? htmlspecialchars($dados_fornecedor['site'] ?? '') : '' ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-phone"></i>
                                    Telefone 1
                                </label>
                                <input type="text" 
                                       name="tel1" 
                                       class="form-control mask-phone"
                                       placeholder="(00) 00000-0000"
                                       value="<?= $modo_edicao ? htmlspecialchars($dados_fornecedor['telefone1'] ?? '') : '' ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-phone"></i>
                                    Telefone 2
                                </label>
                                <input type="text" 
                                       name="tel2" 
                                       class="form-control mask-phone"
                                       placeholder="(00) 00000-0000"
                                       value="<?= $modo_edicao ? htmlspecialchars($dados_fornecedor['telefone2'] ?? '') : '' ?>">
                            </div>
                            
                            <div class="form-group half">
                                <label>
                                    <i class="fas fa-phone"></i>
                                    Telefone 3
                                </label>
                                <input type="text" 
                                       name="tel3" 
                                       class="form-control mask-phone"
                                       placeholder="(00) 00000-0000"
                                       value="<?= $modo_edicao ? htmlspecialchars($dados_fornecedor['telefone3'] ?? '') : '' ?>">
                            </div>
                            
                            <div class="form-group full">
                                <label>
                                    <i class="fas fa-comment-alt"></i>
                                    Observações
                                </label>
                                <textarea name="observacoes" 
                                          class="form-control" 
                                          rows="5"
                                          placeholder="Informações adicionais sobre o fornecedor"><?= $modo_edicao ? htmlspecialchars($dados_fornecedor['observacoes'] ?? '') : '' ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
            
            <!-- BOTÕES DE AÇÃO -->
            <div class="form-actions">
                <button type="button" onclick="salvarFornecedor()" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?= $modo_edicao ? 'Atualizar' : 'Salvar' ?> Fornecedor
                </button>
                <a href="listar_fornecedores.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // ==========================================================
        // INICIALIZAÇÃO
        // ==========================================================
        $(document).ready(function(){
            // Máscaras
            $('.mask-cpf').mask('000.000.000-00');
            $('.mask-cnpj').mask('00.000.000/0000-00');
            $('.mask-cep').mask('00000-000');
            $('.mask-phone').mask('(00) 00000-0000');

            // Inicializar status
            const statusAtual = $('#input_status').val();
            setStatus(statusAtual);
            
            // Inicializar campos condicionais
            alternarCampos();

            // Buscar CEP
            $('#cep').on('blur', buscarEndereco);
        });

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
        // ALTERNAR CAMPOS (FÍSICA/JURÍDICA)
        // ==========================================================
        function alternarCampos() {
            const tipo = document.getElementById('select_tipo_pessoa').value;
            const camposFisica = document.getElementById('campos-fisica');
            const camposJuridica = document.getElementById('campos-juridica');
            
            if (tipo === 'Fisica') {
                camposFisica.classList.add('show');
                camposJuridica.classList.remove('show');
            } else {
                camposFisica.classList.remove('show');
                camposJuridica.classList.add('show');
            }
        }

        // ==========================================================
        // BUSCAR ENDEREÇO POR CEP
        // ==========================================================
        async function buscarEndereco() {
            const cep = $('#cep').val().replace(/\D/g, '');
            if (cep.length !== 8) return;
            
            try {
                const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
                const dados = await response.json();
                
                if (!dados.erro) {
                    $("#logradouro").val(dados.logradouro);
                    $("#bairro").val(dados.bairro);
                    $("#localidade").val(dados.localidade);
                    $("#uf").val(dados.uf);
                    $("#numero").focus();
                }
            } catch (error) {
                console.error('Erro ao buscar CEP:', error);
            }
        }

        // ==========================================================
        // SALVAR FORNECEDOR
        // ==========================================================
        async function salvarFornecedor() {
            const formData = new FormData(document.getElementById('formFornecedor'));
            const btn = event.target;
            const modoEdicao = formData.has('id_fornecedor');
            const txtOriginal = btn.innerHTML;
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + (modoEdicao ? 'Atualizando...' : 'Salvando...');
            btn.disabled = true;

            try {
                const res = await fetch('fornecedores.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.status === 'success') {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: data.message,
                        confirmButtonColor: '#28A745'
                    });
                    window.location.href = 'listar_fornecedores.php';
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: data.message,
                        confirmButtonColor: '#28A745'
                    });
                }
            } catch (e) { 
                Swal.fire({
                    icon: 'error',
                    title: 'Erro de Conexão',
                    text: 'Não foi possível salvar o fornecedor.',
                    confirmButtonColor: '#28A745'
                });
            } finally {
                btn.innerHTML = txtOriginal;
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>