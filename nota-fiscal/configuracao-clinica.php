<?php
/**
 * =========================================================================================
 * ZIIPVET - CONFIGURAÇÃO DE NOTA FISCAL
 * ARQUIVO: notas_fiscais/configuracao_nf.php
 * VERSÃO: 4.0.0 - PADRÃO MODERNO
 * =========================================================================================
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;

// ==========================================================
// CONFIGURAÇÃO DA PÁGINA
// ==========================================================
$titulo_pagina = "Configuração de Nota Fiscal";

$mensagem = '';
$tipo_mensagem = '';

// ==========================================================
// PROCESSAR FORMULÁRIOS
// ==========================================================

// 1. DADOS DA EMPRESA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cnpj'])) {
    try {
        $sql = "REPLACE INTO config_clinica (id, cnpj, razao_social, nome_fantasia, inscricao_estadual, inscricao_municipal, simples_nacional, regime_tributario, regime_especial, cep, tipo_logradouro, logradouro, complemento, tipo_bairro, bairro, municipio, numero, email_nf, ddd_nf, telefone_nf) 
                VALUES (1, :cnpj, :razao, :fantasia, :ie, :im, :simples, :regime, :reg_esp, :cep, :tipo_log, :logr, :compl, :tipo_bai, :bairro, :muni, :num, :email, :ddd, :tel)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cnpj' => $_POST['cnpj'], 
            ':razao' => $_POST['razao_social'], 
            ':fantasia' => $_POST['nome_fantasia'], 
            ':ie' => $_POST['inscricao_estadual'],
            ':im' => $_POST['inscricao_municipal'], 
            ':simples' => $_POST['simples_nacional'], 
            ':regime' => $_POST['regime_tributario'], 
            ':reg_esp' => $_POST['regime_especial'],
            ':cep' => $_POST['cep'], 
            ':tipo_log' => $_POST['tipo_logradouro'], 
            ':logr' => $_POST['logradouro'], 
            ':compl' => $_POST['complemento'],
            ':tipo_bai' => $_POST['tipo_bairro'], 
            ':bairro' => $_POST['bairro'], 
            ':muni' => $_POST['municipio'], 
            ':num' => $_POST['numero'],
            ':email' => $_POST['email_nf'], 
            ':ddd' => $_POST['ddd_nf'], 
            ':tel' => $_POST['telefone_nf']
        ]);
        $mensagem = "Dados da empresa salvos com sucesso!";
        $tipo_mensagem = 'success';
    } catch (PDOException $e) {
        $mensagem = "Erro ao salvar: " . $e->getMessage();
        $tipo_mensagem = 'error';
    }
}

// 2. CERTIFICADO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['senha_cert'])) {
    try {
        $caminho_final = "";
        if (!empty($_FILES['cert_file']['name'])) {
            $uploaddir = '../uploads/certificados/';
            if (!is_dir($uploaddir)) mkdir($uploaddir, 0777, true);
            $caminho_final = $uploaddir . basename($_FILES['cert_file']['name']);
            move_uploaded_file($_FILES['cert_file']['tmp_name'], $caminho_final);
        }

        $sql = "REPLACE INTO config_certificados (id, email_responsavel, senha_certificado, caminho_arquivo) VALUES (1, :email, :senha, :path)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':email' => $_POST['email_cert'], 
            ':senha' => $_POST['senha_cert'], 
            ':path' => $caminho_final
        ]);
        $mensagem = "Configurações de certificado atualizadas!";
        $tipo_mensagem = 'success';
    } catch (PDOException $e) {
        $mensagem = "Erro ao salvar certificado: " . $e->getMessage();
        $tipo_mensagem = 'error';
    }
}

// 3. NFC-E
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['csc'])) {
    try {
        $status = isset($_POST['status_nfce']) ? 1 : 0;
        $ambiente = isset($_POST['ambiente']) ? 'Homologacao' : 'Producao';
        $email = isset($_POST['envio_email']) ? 1 : 0;
        $ibpt = isset($_POST['ibpt']) ? 1 : 0;

        $sql = "REPLACE INTO config_nfce (id, status_nfce, ambiente, envio_email, calculo_ibpt, csc, csc_id) 
                VALUES (1, :status, :amb, :email, :ibpt, :csc, :csc_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':status' => $status, 
            ':amb' => $ambiente, 
            ':email' => $email, 
            ':ibpt' => $ibpt, 
            ':csc' => $_POST['csc'], 
            ':csc_id' => $_POST['csc_id']
        ]);
        $mensagem = "Configurações de NFC-e salvas com sucesso!";
        $tipo_mensagem = 'success';
    } catch (PDOException $e) {
        $mensagem = "Erro ao salvar NFC-e: " . $e->getMessage();
        $tipo_mensagem = 'error';
    }
}

// ==========================================================
// CARREGAR DADOS DO BANCO
// ==========================================================
$empresa = $pdo->query("SELECT * FROM config_clinica WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$cert = $pdo->query("SELECT * FROM config_certificados WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$nfce = $pdo->query("SELECT * FROM config_nfce WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    
    <!-- CSS CENTRALIZADO -->
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/menu.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/formularios.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        /* ========================================
           ESTILOS ESPECÍFICOS DA CONFIGURAÇÃO NF
        ======================================== */
        
        /* Sistema de Tabs */
        .tabs-nav {
            display: flex;
            gap: 0;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 30px;
            background: #f8f9fa;
            border-radius: 12px 12px 0 0;
            overflow: hidden;
        }
        
        .tab-item {
            flex: 1;
            padding: 18px 24px;
            font-size: 15px;
            font-weight: 700;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            border-bottom: 3px solid transparent;
            font-family: 'Exo', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .tab-item:hover {
            background: #e9ecef;
            color: #131c71;
        }
        
        .tab-item.active {
            background: #fff;
            color: #131c71;
            border-bottom-color: #131c71;
        }
        
        /* Tab Content */
        .tab-content {
            background: #fff;
            padding: 30px;
            border-radius: 0 0 16px 16px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }
        
        .hidden {
            display: none;
        }
        
        /* Section Titles */
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #2c3e50;
            text-transform: uppercase;
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
            font-family: 'Exo', sans-serif;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title:first-of-type {
            margin-top: 0;
        }
        
        .section-title i {
            color: #131c71;
        }
        
        /* Dropzone para Upload */
        .dropzone {
            border: 3px dashed #d0d0d0;
            border-radius: 12px;
            padding: 60px 20px;
            text-align: center;
            color: #6c757d;
            background: #f8f9fa;
            margin: 25px 0;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .dropzone:hover {
            background: #e9ecef;
            border-color: #131c71;
        }
        
        .dropzone i {
            color: #131c71;
            margin-bottom: 15px;
            display: block;
        }
        
        .dropzone .file-name {
            display: block;
            margin-top: 15px;
            padding: 10px;
            background: #e8f5e9;
            border-radius: 8px;
            color: #2e7d32;
            font-weight: 600;
        }
        
        .dropzone .choose-text {
            color: #131c71;
            font-weight: 600;
        }
        
        /* Switch Container */
        .switch-container {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
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
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #28a745;
        }
        
        input:checked + .slider:before {
            transform: translateX(24px);
        }
        
        .switch-label {
            font-size: 15px;
            font-weight: 600;
            color: #2c3e50;
            font-family: 'Exo', sans-serif;
        }
        
        /* Switches Grid */
        .switches-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }
        
        /* Botão Submit */
        .btn-submit-config {
            width: 100%;
            max-width: 400px;
            padding: 16px;
            background: linear-gradient(135deg, #131c71 0%, #0d1450 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
            cursor: pointer;
            transition: all 0.3s;
            display: block;
            margin: 30px auto 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-submit-config:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(19, 28, 113, 0.4);
        }
        
        /* Alert Message */
        .alert-message {
            padding: 18px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 15px;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            animation: slideDown 0.3s ease;
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
        
        .alert-message i {
            font-size: 20px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c8e6c9 100%);
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #f8d7da 0%, #ffcdd2 100%);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .tabs-nav {
                flex-direction: column;
                border-radius: 0;
            }
            
            .tab-item {
                border-bottom: 1px solid #e0e0e0;
            }
            
            .tab-item.active {
                border-left: 4px solid #131c71;
                border-bottom: 1px solid #e0e0e0;
            }
            
            .switches-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include '../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <!-- HEADER: Título -->
        <div class="form-header">
            <h1 class="form-title">
                <i class="fas fa-file-invoice"></i>
                Configuração de Nota Fiscal
            </h1>
        </div>

        <!-- MENSAGEM DE FEEDBACK -->
        <?php if (!empty($mensagem)): ?>
            <div class="alert-message alert-<?= $tipo_mensagem ?>">
                <i class="fas fa-<?= $tipo_mensagem == 'success' ? 'check-circle' : 'times-circle' ?>"></i>
                <span><?= $mensagem ?></span>
            </div>
        <?php endif; ?>

        <!-- SISTEMA DE TABS -->
        <nav class="tabs-nav">
            <div class="tab-item active" id="btn-empresa" onclick="switchTab('empresa')">
                <i class="fas fa-building"></i>
                Dados da Empresa
            </div>
            <div class="tab-item" id="btn-certificados" onclick="switchTab('certificados')">
                <i class="fas fa-certificate"></i>
                Certificados
            </div>
            <div class="tab-item" id="btn-nfce" onclick="switchTab('nfce')">
                <i class="fas fa-receipt"></i>
                NFC-e
            </div>
        </nav>

        <!-- TAB: DADOS DA EMPRESA -->
        <div id="tab-empresa" class="tab-content">
            <form method="POST">
                
                <h3 class="section-title">
                    <i class="fas fa-file-contract"></i>
                    Dados Tributários
                </h3>
                <div class="form-grid">
                    <div class="form-group" style="grid-column: span 12;">
                        <label>CPF/CNPJ</label>
                        <input type="text" name="cnpj" class="form-control" value="<?= $empresa['cnpj'] ?? '' ?>" placeholder="00.000.000/0000-00">
                    </div>
                    <div class="form-group" style="grid-column: span 6;">
                        <label class="required">Razão Social</label>
                        <input type="text" name="razao_social" class="form-control" value="<?= $empresa['razao_social'] ?? '' ?>" required>
                    </div>
                    <div class="form-group" style="grid-column: span 6;">
                        <label class="required">Nome Fantasia</label>
                        <input type="text" name="nome_fantasia" class="form-control" value="<?= $empresa['nome_fantasia'] ?? '' ?>" required>
                    </div>
                    <div class="form-group" style="grid-column: span 6;">
                        <label class="required">Inscrição Estadual</label>
                        <input type="text" name="inscricao_estadual" class="form-control" value="<?= $empresa['inscricao_estadual'] ?? '' ?>" required>
                    </div>
                    <div class="form-group" style="grid-column: span 6;">
                        <label>Inscrição Municipal</label>
                        <input type="text" name="inscricao_municipal" class="form-control" value="<?= $empresa['inscricao_municipal'] ?? '' ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 4;">
                        <label class="required">Simples Nacional</label>
                        <select name="simples_nacional" class="form-control">
                            <option value="Sim" <?= ($empresa['simples_nacional'] ?? '') == 'Sim' ? 'selected' : '' ?>>É optante</option>
                            <option value="Não" <?= ($empresa['simples_nacional'] ?? '') == 'Não' ? 'selected' : '' ?>>Não optante</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 4;">
                        <label class="required">Regime Tributário</label>
                        <select name="regime_tributario" class="form-control">
                            <option value="0" <?= ($empresa['regime_tributario'] ?? '') == '0' ? 'selected' : '' ?>>0 - Nenhum</option>
                            <option value="1" <?= ($empresa['regime_tributario'] ?? '') == '1' ? 'selected' : '' ?>>1 - Simples Nacional</option>
                            <option value="3" <?= ($empresa['regime_tributario'] ?? '') == '3' ? 'selected' : '' ?>>3 - Regime Normal</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 4;">
                        <label class="required">Regime Tributário Especial</label>
                        <select name="regime_especial" class="form-control">
                            <option value="0" <?= ($empresa['regime_especial'] ?? '') == '0' ? 'selected' : '' ?>>0 - Sem Regime</option>
                            <option value="1" <?= ($empresa['regime_especial'] ?? '') == '1' ? 'selected' : '' ?>>1 - Micro Empresa</option>
                        </select>
                    </div>
                </div>

                <h3 class="section-title">
                    <i class="fas fa-map-marker-alt"></i>
                    Endereço
                </h3>
                <div class="form-grid">
                    <div class="form-group" style="grid-column: span 3;">
                        <label>CEP</label>
                        <input type="text" name="cep" class="form-control" value="<?= $empresa['cep'] ?? '' ?>" placeholder="00000-000">
                    </div>
                    <div class="form-group" style="grid-column: span 3;">
                        <label class="required">Tipo Logradouro</label>
                        <select name="tipo_logradouro" class="form-control">
                            <option value="Rua" <?= ($empresa['tipo_logradouro'] ?? '') == 'Rua' ? 'selected' : '' ?>>Rua</option>
                            <option value="Avenida" <?= ($empresa['tipo_logradouro'] ?? '') == 'Avenida' ? 'selected' : '' ?>>Avenida</option>
                            <option value="Travessa" <?= ($empresa['tipo_logradouro'] ?? '') == 'Travessa' ? 'selected' : '' ?>>Travessa</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 6;">
                        <label class="required">Logradouro</label>
                        <input type="text" name="logradouro" class="form-control" value="<?= $empresa['logradouro'] ?? '' ?>" required>
                    </div>
                    <div class="form-group" style="grid-column: span 3;">
                        <label>Complemento</label>
                        <input type="text" name="complemento" class="form-control" value="<?= $empresa['complemento'] ?? '' ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 3;">
                        <label class="required">Tipo Bairro</label>
                        <select name="tipo_bairro" class="form-control">
                            <option value="Bairro">Bairro</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 6;">
                        <label class="required">Bairro</label>
                        <input type="text" name="bairro" class="form-control" value="<?= $empresa['bairro'] ?? '' ?>" required>
                    </div>
                    <div class="form-group" style="grid-column: span 9;">
                        <label class="required">Município</label>
                        <input type="text" name="municipio" class="form-control" value="<?= $empresa['municipio'] ?? '' ?>" placeholder="(5208707) Goiânia/Goiás" required>
                    </div>
                    <div class="form-group" style="grid-column: span 3;">
                        <label class="required">Número</label>
                        <input type="text" name="numero" class="form-control" value="<?= $empresa['numero'] ?? '' ?>" required>
                    </div>
                </div>

                <h3 class="section-title">
                    <i class="fas fa-envelope"></i>
                    Contato
                </h3>
                <div class="form-grid">
                    <div class="form-group" style="grid-column: span 12;">
                        <label class="required">E-mail</label>
                        <input type="email" name="email_nf" class="form-control" value="<?= $empresa['email_nf'] ?? '' ?>" required>
                    </div>
                    <div class="form-group" style="grid-column: span 3;">
                        <label class="required">DDD</label>
                        <input type="text" name="ddd_nf" class="form-control" maxlength="2" value="<?= $empresa['ddd_nf'] ?? '' ?>" placeholder="62" required>
                    </div>
                    <div class="form-group" style="grid-column: span 9;">
                        <label class="required">Número de telefone</label>
                        <input type="text" name="telefone_nf" class="form-control" value="<?= $empresa['telefone_nf'] ?? '' ?>" placeholder="999887766" required>
                    </div>
                </div>

                <button type="submit" class="btn-submit-config">
                    <i class="fas fa-save"></i> Salvar Dados da Empresa
                </button>
            </form>
        </div>

        <!-- TAB: CERTIFICADOS -->
        <div id="tab-certificados" class="tab-content hidden">
            <form method="POST" enctype="multipart/form-data">
                
                <h3 class="section-title">
                    <i class="fas fa-upload"></i>
                    Enviar Novo Certificado
                </h3>
                
                <div class="form-grid">
                    <div class="form-group" style="grid-column: span 6;">
                        <label>E-mail do Responsável</label>
                        <input type="email" name="email_cert" class="form-control" value="<?= $cert['email_responsavel'] ?? '' ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 6;">
                        <label class="required">Senha do Certificado</label>
                        <input type="password" name="senha_cert" class="form-control" value="<?= $cert['senha_certificado'] ?? '' ?>" required>
                    </div>
                </div>
                
                <div class="dropzone" onclick="document.getElementById('cert_file').click()">
                    <i class="fas fa-file-contract fa-3x"></i>
                    <div style="margin-top: 15px; font-size: 16px;">
                        <?php if(!empty($cert['caminho_arquivo'])): ?>
                            <span class="file-name">
                                <i class="fas fa-check-circle"></i>
                                Arquivo carregado: <?= basename($cert['caminho_arquivo']) ?>
                            </span>
                        <?php endif; ?>
                        <p style="margin-top: 10px;">
                            Arraste o arquivo <strong>.pfx</strong> até aqui ou <span class="choose-text">escolha um arquivo</span>
                        </p>
                    </div>
                    <input type="file" id="cert_file" name="cert_file" class="hidden" accept=".pfx">
                </div>

                <button type="submit" class="btn-submit-config">
                    <i class="fas fa-save"></i> Salvar Certificado
                </button>
            </form>
        </div>

        <!-- TAB: NFC-E -->
        <div id="tab-nfce" class="tab-content hidden">
            <form method="POST">
                
                <div class="switch-container">
                    <label class="switch">
                        <input type="checkbox" id="nfce_toggle" name="status_nfce" onchange="toggleNfce(this)" <?= ($nfce['status_nfce'] ?? 0) ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                    <span id="nfce_status_label" class="switch-label">
                        <?= ($nfce['status_nfce'] ?? 0) ? 'NFC-e Ativo' : 'NFC-e Inativo' ?>
                    </span>
                </div>

                <div id="nfce_campos" class="<?= ($nfce['status_nfce'] ?? 0) ? '' : 'hidden' ?>">
                    
                    <div class="switches-grid">
                        <div class="switch-container">
                            <label class="switch">
                                <input type="checkbox" name="ambiente" <?= ($nfce['ambiente'] ?? '') == 'Homologacao' ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                            <span class="switch-label">
                                <i class="fas fa-flask"></i>
                                Estado: Em Homologação
                            </span>
                        </div>
                        
                        <div class="switch-container">
                            <label class="switch">
                                <input type="checkbox" name="envio_email" <?= ($nfce['envio_email'] ?? 0) ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                            <span class="switch-label">
                                <i class="fas fa-envelope"></i>
                                Envio de e-mail habilitado
                            </span>
                        </div>
                    </div>

                    <h3 class="section-title">
                        <i class="fas fa-cog"></i>
                        Configurações do SEFAZ
                    </h3>
                    <div class="form-grid">
                        <div class="form-group" style="grid-column: span 6;">
                            <label class="required">Código de Segurança do Contribuinte (CSC)</label>
                            <input type="text" name="csc" class="form-control" value="<?= $nfce['csc'] ?? '' ?>" required>
                        </div>
                        <div class="form-group" style="grid-column: span 6;">
                            <label class="required">ID do CSC</label>
                            <input type="text" name="csc_id" class="form-control" value="<?= $nfce['csc_id'] ?? '' ?>" required>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit-config">
                    <i class="fas fa-save"></i> Salvar Configurações NFC-e
                </button>
            </form>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // ==========================================================
        // SISTEMA DE TABS
        // ==========================================================
        function switchTab(tabName) {
            // Remover active de todas as tabs
            document.querySelectorAll('.tab-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Esconder todos os conteúdos
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Ativar tab clicada
            document.getElementById('btn-' + tabName).classList.add('active');
            document.getElementById('tab-' + tabName).classList.remove('hidden');
        }
        
        // ==========================================================
        // TOGGLE NFC-E
        // ==========================================================
        function toggleNfce(checkbox) {
            const campos = document.getElementById('nfce_campos');
            const label = document.getElementById('nfce_status_label');
            
            if (checkbox.checked) {
                campos.classList.remove('hidden');
                label.textContent = 'NFC-e Ativo';
            } else {
                campos.classList.add('hidden');
                label.textContent = 'NFC-e Inativo';
            }
        }
        
        // ==========================================================
        // FEEDBACK DO ARQUIVO SELECIONADO
        // ==========================================================
        document.getElementById('cert_file')?.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const fileName = this.files[0].name;
                const dropzone = this.closest('.dropzone');
                
                // Atualizar feedback visual
                const existingFileName = dropzone.querySelector('.file-name');
                if (existingFileName) {
                    existingFileName.innerHTML = `<i class="fas fa-check-circle"></i> Arquivo selecionado: ${fileName}`;
                } else {
                    const fileNameSpan = document.createElement('span');
                    fileNameSpan.className = 'file-name';
                    fileNameSpan.innerHTML = `<i class="fas fa-check-circle"></i> Arquivo selecionado: ${fileName}`;
                    dropzone.querySelector('div').insertBefore(fileNameSpan, dropzone.querySelector('p'));
                }
            }
        });
    </script>
</body>
</html>