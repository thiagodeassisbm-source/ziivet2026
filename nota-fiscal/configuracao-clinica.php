<?php
/**
 * =========================================================================================
 * ZIIPVET - DADOS DA EMPRESA
 * ARQUIVO: nota-fiscal/configuracao-clinica.php
 * VERSÃO: 4.1.0 - SIMPLIFICADO
 * =========================================================================================
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';

use App\Utils\Csrf;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;
$path_prefix = '../';
$titulo_pagina = "Dados da Empresa";

$mensagem = '';
$tipo_mensagem = '';

// ==========================================================
// PROCESSAR FORMULÁRIO
// ==========================================================
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

// ==========================================================
// CARREGAR DADOS DO BANCO
// ==========================================================
$empresa = $pdo->query("SELECT * FROM config_clinica WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$csrf_token = Csrf::generate();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/menu.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/formularios.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #2c3e50;
            text-transform: uppercase;
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
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
        
        .alert-message {
            padding: 18px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
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
        
        .btn-submit-config {
            max-width: 400px;
            padding: 16px;
            background: linear-gradient(135deg, #131c71 0%, #0d1450 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            display: block;
            margin: 30px auto 0;
            text-transform: uppercase;
        }
        
        .btn-submit-config:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(19, 28, 113, 0.4);
        }
    </style>
</head>
<body>
    <aside class="sidebar-container"><?php include '../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <div class="form-header">
            <h1 class="form-title">
                <i class="fas fa-building"></i>
                <?= $titulo_pagina ?>
            </h1>
        </div>

        <?php if (!empty($mensagem)): ?>
            <div class="alert-message alert-<?= $tipo_mensagem ?>">
                <i class="fas fa-<?= $tipo_mensagem == 'success' ? 'check-circle' : 'times-circle' ?>"></i>
                <span><?= $mensagem ?></span>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <!-- DADOS TRIBUTÁRIOS -->
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

                <!-- ENDEREÇO -->
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

                <!-- CONTATO -->
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
    </main>
</body>
</html>