<?php
/**
 * ZIIPVET - Configurações Fiscais da Empresa
 * Com abas: Empresa, CSC, Certificado
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';

use App\Utils\Csrf;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;
$mensagem = $_GET['msg'] ?? '';
$erro = $_GET['erro'] ?? '';
$aba_ativa = $_GET['aba'] ?? 'empresa';

// Processar salvamento
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tipo_form = $_POST['tipo_form'] ?? '';
    
    try {
        // Verificar se já existe configuração
        $stmt_check = $pdo->prepare("SELECT id FROM configuracoes_fiscais WHERE id_admin = ?");
        $stmt_check->execute([$id_admin]);
        $existe = $stmt_check->fetch();
        
        if ($tipo_form == 'empresa') {
            if ($existe) {
                $sql = "UPDATE configuracoes_fiscais SET 
                        tipo_empresa = ?,
                        regime_tributario = ?,
                        percentual_icms = ?,
                        ambiente = ?,
                        updated_at = NOW()
                        WHERE id_admin = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $_POST['tipo_empresa'],
                    $_POST['regime_tributario'],
                    $_POST['percentual_icms'] ?: 0,
                    $_POST['ambiente'] ?: 2,
                    $id_admin
                ]);
            } else {
                $sql = "INSERT INTO configuracoes_fiscais 
                        (id_admin, tipo_empresa, regime_tributario, percentual_icms, ambiente, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $id_admin,
                    $_POST['tipo_empresa'],
                    $_POST['regime_tributario'],
                    $_POST['percentual_icms'] ?: 0,
                    $_POST['ambiente'] ?: 2
                ]);
            }
            $mensagem = "Configurações da empresa salvas com sucesso!";
            $aba_ativa = 'empresa';
            
        } elseif ($tipo_form == 'csc') {
            if ($existe) {
                $sql = "UPDATE configuracoes_fiscais SET 
                        csc_id = ?,
                        csc_producao = ?,
                        updated_at = NOW()
                        WHERE id_admin = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $_POST['csc_id'] ?: null,
                    $_POST['csc_producao'] ?: null,
                    $id_admin
                ]);
            } else {
                $sql = "INSERT INTO configuracoes_fiscais 
                        (id_admin, csc_id, csc_producao, created_at) 
                        VALUES (?, ?, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $id_admin,
                    $_POST['csc_id'] ?: null,
                    $_POST['csc_producao'] ?: null
                ]);
            }
            $mensagem = "Código CSC salvo com sucesso!";
            $aba_ativa = 'csc';
            
        } elseif ($tipo_form == 'serie') {
            if ($existe) {
                $sql = "UPDATE configuracoes_fiscais SET 
                        nfce_serie = ?,
                        nfce_numero = ?,
                        updated_at = NOW()
                        WHERE id_admin = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $_POST['nfce_serie'] ?: 1,
                    $_POST['nfce_numero'] ?: 1,
                    $id_admin
                ]);
            } else {
                $sql = "INSERT INTO configuracoes_fiscais 
                        (id_admin, nfce_serie, nfce_numero, created_at) 
                        VALUES (?, ?, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $id_admin,
                    $_POST['nfce_serie'] ?: 1,
                    $_POST['nfce_numero'] ?: 1
                ]);
            }
            $mensagem = "Série e número atualizados com sucesso!";
            $aba_ativa = 'serie';
        }
        
    } catch (PDOException $e) {
        $erro = "Erro ao salvar: " . $e->getMessage();
    }
}

// Buscar configurações atualizadas
$stmt = $pdo->prepare("SELECT * FROM configuracoes_fiscais WHERE id_admin = ?");
$stmt->execute([$id_admin]);
$config = $stmt->fetch(PDO::FETCH_ASSOC);

// Buscar senha do certificado (tabela separada)
$stmtCert = $pdo->query("SELECT senha_certificado FROM config_certificados WHERE id = 1");
$certData = $stmtCert->fetch(PDO::FETCH_ASSOC);
if ($certData) {
    if (!$config) $config = [];
    $config['senha_certificado'] = $certData['senha_certificado'];
}

$csrf_token = Csrf::generate();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações Fiscais | ZiipVet</title>
    
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/menu.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/formularios.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .tabs-container {
            display: flex;
            gap: 5px;
            margin-bottom: 0;
            background: #f8f9fa;
            padding: 10px 10px 0;
            border-radius: 12px 12px 0 0;
        }
        
        .tab-btn {
            padding: 12px 25px;
            border: none;
            background: #e9ecef;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #495057;
            border-radius: 8px 8px 0 0;
            transition: all 0.3s;
        }
        
        .tab-btn:hover {
            background: #dee2e6;
        }
        
        .tab-btn.active {
            background: #fff;
            color: #1e40af;
            border-bottom: 2px solid #1e40af;
        }
        
        .tab-content {
            display: none;
            background: #fff;
            padding: 30px;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .tab-content.active {
            display: block;
        }
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .info-table tr {
            border-bottom: 1px solid #e0e0e0;
        }
        
        .info-table td {
            padding: 15px;
        }
        
        .info-table td:first-child {
            font-weight: 700;
            color: #6c757d;
            width: 250px;
        }
        
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #856404;
        }
        
        .alert-info {
            background: #ffc107;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            color: #212529;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 100000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            overflow-y: auto;
            padding: 20px;
            box-sizing: border-box;
        }
        
        .modal-content {
            background: #fff;
            margin: 20px auto;
            padding: 0;
            border-radius: 12px;
            width: 100%;
            max-width: 500px;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: #fff;
            padding: 20px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 18px;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #495057;
            margin-bottom: 8px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        .link-sefaz {
            color: #17a2b8;
            text-decoration: none;
            font-size: 13px;
        }
        
        .link-sefaz:hover {
            text-decoration: underline;
        }
        
        .modal-footer {
            padding: 15px 25px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 10px;
        }
        
        .btn-success {
            background: #28a745;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php $path_prefix = '../'; ?>
    <aside class="sidebar-container"><?php include '../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <div class="form-header">
            <h1 class="form-title">
                <i class="fas fa-cog"></i>
                Configurações Fiscais
            </h1>
            <a href="perfil_tributario.php" class="btn-voltar">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <?php if ($mensagem): ?>
        <div style="background:#d4edda; color:#155724; padding:15px; border-radius:8px; margin-bottom:20px;">
            <i class="fas fa-check-circle"></i> <?= $mensagem ?>
        </div>
        <?php endif; ?>
        
        <?php if ($erro): ?>
        <div style="background:#f8d7da; color:#721c24; padding:15px; border-radius:8px; margin-bottom:20px;">
            <i class="fas fa-exclamation-circle"></i> <?= $erro ?>
        </div>
        <?php endif; ?>

        <!-- ABAS -->
        <div class="tabs-container">
            <button class="tab-btn <?= $aba_ativa == 'empresa' ? 'active' : '' ?>" onclick="trocarAba('empresa')">
                <i class="fas fa-building"></i> Empresa
            </button>
            <button class="tab-btn <?= $aba_ativa == 'csc' ? 'active' : '' ?>" onclick="trocarAba('csc')">
                <i class="fas fa-key"></i> Código CSC
            </button>
            <button class="tab-btn <?= $aba_ativa == 'certificado' ? 'active' : '' ?>" onclick="trocarAba('certificado')">
                <i class="fas fa-certificate"></i> Certificado Digital
            </button>
            <button class="tab-btn <?= $aba_ativa == 'serie' ? 'active' : '' ?>" onclick="trocarAba('serie')">
                <i class="fas fa-list-ol"></i> Série e Número
            </button>
        </div>

        <!-- ABA EMPRESA -->
        <div id="tab-empresa" class="tab-content <?= $aba_ativa == 'empresa' ? 'active' : '' ?>">
            <button onclick="abrirModal('modal-empresa')" class="btn-ziip" style="background:#1e40af; color:#fff; border:none; padding:12px 24px; border-radius:8px; cursor:pointer; font-weight:700; margin-bottom:20px;">
                <i class="fas fa-edit"></i> Editar Configurações
            </button>

            <div class="alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Atenção:</strong> Caso necessite realizar uma alteração na Razão social, será necessário solicitar a equipe de encantadores pelo chat do sistema.
            </div>

            <table class="info-table">
                <tr>
                    <td>Tipo de Empresa:</td>
                    <td><?= $config['tipo_empresa'] ?? 'Não informado' ?></td>
                </tr>
                <tr>
                    <td>Regime Tributário:</td>
                    <td><?= $config['regime_tributario'] ?? 'Não informado' ?></td>
                </tr>
                <tr>
                    <td>Percentual de ICMS:</td>
                    <td><?= number_format($config['percentual_icms'] ?? 0, 2, ',', '.') ?>%</td>
                </tr>
                <tr>
                    <td>Ambiente:</td>
                    <td>
                        <?php if(($config['ambiente'] ?? 2) == 1): ?>
                            <span style="color:#28a745; font-weight:700;"><i class="fas fa-check-circle"></i> PRODUÇÃO (Com Validade Jurídica)</span>
                        <?php else: ?>
                            <span style="color:#f39c12; font-weight:700;"><i class="fas fa-flask"></i> HOMOLOGAÇÃO (Testes)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <!-- Inscrição Estadual removida (Usada em Dados Empresa) -->
            </table>
        </div>

        <!-- ABA CSC -->
        <div id="tab-csc" class="tab-content <?= $aba_ativa == 'csc' ? 'active' : '' ?>">
            <button onclick="abrirModal('modal-csc')" class="btn-ziip" style="background:#1e40af; color:#fff; border:none; padding:12px 24px; border-radius:8px; cursor:pointer; font-weight:700; margin-bottom:20px;">
                <i class="fas fa-edit"></i> Editar CSC
            </button>

            <table class="info-table">
                <tr>
                    <td>ID do CSC:</td>
                    <td><?= $config['csc_id'] ?? 'Não informado' ?></td>
                </tr>
                <tr>
                    <td>CSC de Produção:</td>
                    <td><?= $config['csc_producao'] ? '••••••••••••••••' : 'Não informado' ?></td>
                </tr>
            </table>

            <div class="alert-info" style="margin-top:20px;">
                <p><strong>O CSC (Código de Segurança do Contribuinte)</strong> é um código alfanumérico (que contém letras e números), de conhecimento exclusivo do contribuinte e da SEFAZ, usado para garantir a autoria e a autenticidade do DANFE NFC-e.</p>
                <p style="margin-top:15px;">A Sefaz pode revogar o CSC sem aviso prévio e neste caso, precisará gerar um novo código pelo site da Sefaz, para que em seguida, atualize-o por aqui.</p>
                <p style="margin-top:15px;"><strong>Recomendamos que o contador participe desse processo.</strong></p>
            </div>
        </div>

        <!-- ABA CERTIFICADO -->
        <div id="tab-certificado" class="tab-content <?= $aba_ativa == 'certificado' ? 'active' : '' ?>">
            <button onclick="abrirModal('modal-certificado')" class="btn-ziip" style="background:#1e40af; color:#fff; border:none; padding:12px 24px; border-radius:8px; cursor:pointer; font-weight:700; margin-bottom:20px;">
                <i class="fas fa-upload"></i> Enviar Certificado Digital
            </button>

            <?php if (!empty($config['certificado_nome'])): ?>
            <div style="background:#d4edda; border:1px solid #c3e6cb; border-radius:8px; padding:20px; margin-bottom:20px;">
                <h4 style="margin:0 0 15px; color:#155724;"><i class="fas fa-check-circle"></i> Informações do certificado atual</h4>
                <ul style="margin:0; padding-left:20px; color:#155724;">
                    <li>Válido até <?= $config['certificado_validade'] ? date('d/m/Y', strtotime($config['certificado_validade'])) : '-' ?></li>
                    <li>Nome: <?= htmlspecialchars($config['certificado_nome']) ?></li>
                </ul>
            </div>
            <?php else: ?>
            <div class="alert-warning">
                <i class="fas fa-info-circle"></i>
                <strong>Certificado Digital A1:</strong> Necessário para emissão de NFC-e. O arquivo deve estar no formato .pfx ou .p12
            </div>
            <?php endif; ?>

            <table class="info-table">
                <tr>
                    <td>Status do Certificado:</td>
                    <td>
                        <?php if (!empty($config['certificado_nome'])): ?>
                            <span style="color:#28a745; font-weight:700;"><i class="fas fa-check-circle"></i> Configurado</span>
                        <?php else: ?>
                            <span style="color:#dc3545; font-weight:700;"><i class="fas fa-times-circle"></i> Não configurado</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>Validade:</td>
                    <td><?= $config['certificado_validade'] ? date('d/m/Y', strtotime($config['certificado_validade'])) : '-' ?></td>
                </tr>
                <tr>
                    <td>Arquivo:</td>
                    <td><?= $config['certificado_nome'] ?? '-' ?></td>
                </tr>
            </table>
        </div>

        <!-- ABA SÉRIE E NÚMERO -->
        <div id="tab-serie" class="tab-content <?= $aba_ativa == 'serie' ? 'active' : '' ?>">
            <button onclick="abrirModal('modal-serie')" class="btn-ziip" style="background:#1e40af; color:#fff; border:none; padding:12px 24px; border-radius:8px; cursor:pointer; font-weight:700; margin-bottom:20px;">
                <i class="fas fa-edit"></i> Editar Série e Número
            </button>

            <div class="alert-info" style="margin-bottom:20px;">
                <p><strong>A série e número</strong> servem para a Sefaz identificar a faixa atual de emissão e a quantidade de notas emitidas até o momento.</p>
                <p style="margin-top:10px;">Alterá-la significa que a próxima nota emitida terá uma série ou número diferente da sequência que estava sendo seguida.</p>
                <p style="margin-top:10px;"><strong>O ideal é que converse com o seu contador antes de realizar essa alteração pois ela pode deixar "furos" na Sefaz.</strong></p>
            </div>

            <table class="info-table">
                <tr>
                    <td>Série atual:</td>
                    <td><strong><?= $config['nfce_serie'] ?? '1' ?></strong></td>
                </tr>
                <tr>
                    <td>Próximo número:</td>
                    <td><strong><?= $config['nfce_numero'] ?? '1' ?></strong></td>
                </tr>
            </table>
        </div>

    </main>

    <!-- MODAL EMPRESA -->
    <div id="modal-empresa" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Informações fiscais da empresa</h2>
                <button onclick="fecharModal('modal-empresa')" style="background:transparent; border:none; font-size:24px; cursor:pointer; color:#fff;">×</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="tipo_form" value="empresa">
                    
                    <div class="form-group">
                        <label>Ambiente de Emissão *</label>
                        <select name="ambiente" required style="border: 2px solid #1e40af; background: #f0f8ff;">
                            <option value="2" <?= ($config['ambiente'] ?? 2) == 2 ? 'selected' : '' ?>>Homologação (Testes - Sem valor fiscal)</option>
                            <option value="1" <?= ($config['ambiente'] ?? 0) == 1 ? 'selected' : '' ?>>PRODUÇÃO (Com valor fiscal)</option>
                        </select>
                        <p style="font-size:12px; color:#666; margin-top:5px;">Para testes, mantenha em Homologação. Mude para Produção apenas quando for emitir notas reais.</p>
                    </div>

                    <div class="form-group">
                        <label>Tipo de empresa *</label>
                        <select name="tipo_empresa" required>
                            <option value="PRIVADA" <?= ($config['tipo_empresa'] ?? 'PRIVADA') == 'PRIVADA' ? 'selected' : '' ?>>Empresa Privada</option>
                            <option value="PUBLICA" <?= ($config['tipo_empresa'] ?? '') == 'PUBLICA' ? 'selected' : '' ?>>Empresa Pública</option>
                            <option value="MEI" <?= ($config['tipo_empresa'] ?? '') == 'MEI' ? 'selected' : '' ?>>MEI</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Regime Tributário *</label>
                        <select name="regime_tributario" required>
                            <option value="SIMPLES_NACIONAL" <?= ($config['regime_tributario'] ?? 'SIMPLES_NACIONAL') == 'SIMPLES_NACIONAL' ? 'selected' : '' ?>>Simples Nacional</option>
                            <option value="LUCRO_PRESUMIDO" <?= ($config['regime_tributario'] ?? '') == 'LUCRO_PRESUMIDO' ? 'selected' : '' ?>>Lucro Presumido</option>
                            <option value="LUCRO_REAL" <?= ($config['regime_tributario'] ?? '') == 'LUCRO_REAL' ? 'selected' : '' ?>>Lucro Real</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Percentual de ICMS</label>
                        <input type="number" name="percentual_icms" step="0.01" value="<?= $config['percentual_icms'] ?? '0.00' ?>">
                    </div>

                    <!-- Campo Inscrição Estadual removido -->

                    <div class="alert-warning">
                        <strong>Atenção:</strong> Caso necessite realizar uma alteração na Razão social, será necessário solicitar a equipe de encantadores pelo chat do sistema.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn-success">
                        <i class="fas fa-check"></i> Salvar
                    </button>
                    <button type="button" onclick="fecharModal('modal-empresa')" class="btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL CSC -->
    <div id="modal-csc" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
                <h2>CSC - Código de segurança do contribuinte</h2>
                <button onclick="fecharModal('modal-csc')" style="background:transparent; border:none; font-size:24px; cursor:pointer; color:#fff;">×</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="tipo_form" value="csc">
                    
                    <div class="form-group">
                        <label>ID *</label>
                        <input type="text" name="csc_id" value="<?= $config['csc_id'] ?? '' ?>" placeholder="000003" maxlength="10">
                    </div>

                    <div class="form-group">
                        <label>CSC de Produção *</label>
                        <input type="text" name="csc_producao" value="<?= $config['csc_producao'] ?? '' ?>" placeholder="3a68fa6dfb08f837">
                    </div>

                    <a href="https://www.sefaz.rs.gov.br/nfc-e/nfc-e-csc.aspx" target="_blank" class="link-sefaz">
                        <i class="fas fa-external-link-alt"></i> Saiba como obter o código CSC na Sefaz
                    </a>

                    <div class="alert-info" style="margin-top:20px;">
                        <p>O CSC (Código de Segurança do Contribuinte) é um código alfanumérico (que contém letras e números), de conhecimento exclusivo do contribuinte e da SEFAZ, usado para garantir a autoria e a autenticidade do DANFE NFC-e.</p>
                        <p style="margin-top:10px;">A Sefaz pode revogar o CSC sem aviso prévio e neste caso, precisará gerar um novo código pelo site da Sefaz, para que em seguida, atualize-o por aqui.</p>
                        <p style="margin-top:10px;"><strong>Recomendamos que o contador participe desse processo.</strong></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn-success">
                        <i class="fas fa-check"></i> Salvar
                    </button>
                    <button type="button" onclick="fecharModal('modal-csc')" class="btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL CERTIFICADO -->
    <div id="modal-certificado" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);">
                <h2>Certificado digital A1</h2>
                <button onclick="fecharModal('modal-certificado')" style="background:transparent; border:none; font-size:24px; cursor:pointer; color:#fff;">×</button>
            </div>
            <form method="POST" enctype="multipart/form-data" action="upload_certificado.php">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    
                    <?php if (!empty($config['certificado_nome'])): ?>
                    <div style="background:#f8f9fa; border-radius:8px; padding:15px; margin-bottom:20px;">
                        <h4 style="margin:0 0 10px;"><i class="fas fa-info-circle"></i> Informações do certificado atual</h4>
                        <ul style="margin:0; padding-left:20px;">
                            <li>Válido até <?= $config['certificado_validade'] ? date('d/m/Y', strtotime($config['certificado_validade'])) : '-' ?></li>
                            <li>Nome: <?= htmlspecialchars($config['certificado_nome']) ?></li>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <h4 style="margin:0 0 15px;"><i class="fas fa-upload"></i> Enviar novo certificado</h4>
                    
                    <div class="form-group">
                        <label>Arquivo do Certificado (.pfx ou .p12)</label>
                        <input type="file" name="certificado_arquivo" class="form-control" accept=".pfx,.p12" required style="padding:10px;">
                    </div>

                    <div class="form-group" style="margin-top:20px;">
                        <label>Senha do Certificado</label>
                        <input type="text" name="certificado_senha" class="form-control" value="<?= htmlspecialchars($config['senha_certificado'] ?? '') ?>" placeholder="Digite a senha" required>
                    </div>

                    <div style="margin-top:20px; background:#fff3cd; border:1px solid #ffeeba; padding:10px; border-radius:5px;">
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                            <input type="checkbox" name="forcar_salvamento" value="1" style="width:20px; height:20px;">
                            <span style="font-weight:bold; color:#856404;">Forçar salvamento (Ignorar erros de criptografia Legacy)</span>
                        </label>
                        <p style="margin:5px 0 0 30px; font-size:12px; color:#856404;">
                            Marque se o sistema recusar seu arquivo por "Erro de Compatibilidade".
                        </p>
                    </div>
                    
                    <p style="color:#666; font-size:13px; margin-top:20px;">
                        Atenção, o certificado deve ser do tipo <strong>A1</strong> e ter o formato <strong>.p12 ou .pfx</strong>. 
                        <a href="#" class="link-sefaz">Saiba mais</a>
                    </p>

                    
                    <p style="color:#666; font-size:13px;">
                        Esta senha foi cadastrada por quem realizou a exportação do certificado digital no seu computador.
                    </p>

                    <button type="submit" class="btn-success" style="margin-top:15px; width:100%;">
                        <i class="fas fa-upload"></i> Enviar certificado
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="fecharModal('modal-certificado')" class="btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL SÉRIE E NÚMERO -->
    <div id="modal-serie" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
                <h2>Série e Número da NFC-e</h2>
                <button onclick="fecharModal('modal-serie')" style="background:transparent; border:none; font-size:24px; cursor:pointer; color:#fff;">×</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="tipo_form" value="serie">
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
                        <div class="form-group">
                            <label>Série</label>
                            <input type="number" name="nfce_serie" value="<?= $config['nfce_serie'] ?? '1' ?>" min="1" required style="width:100px;">
                        </div>
                        
                        <div class="form-group">
                            <label>Número</label>
                            <input type="number" name="nfce_numero" value="<?= $config['nfce_numero'] ?? '1' ?>" min="1" required>
                        </div>
                    </div>

                    <a href="#" class="link-sefaz" style="display:block; margin-bottom:20px;">
                        <i class="fas fa-external-link-alt"></i> Ver última numeração na Caixa de saída
                    </a>

                    <div class="alert-info">
                        <p>A série e número servem para a Sefaz identificar a faixa atual de emissão e a quantidade de notas emitidas até o momento.</p>
                        <p style="margin-top:10px;">Alterá-la significa que a próxima nota emitida terá uma série ou número diferente da sequência que estava sendo seguida.</p>
                        <p style="margin-top:10px;"><strong>O ideal é que converse com o seu contador antes de realizar essa alteração pois ela pode deixar "furos" na Sefaz.</strong></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn-success">
                        <i class="fas fa-check"></i> Salvar
                    </button>
                    <button type="button" onclick="fecharModal('modal-serie')" class="btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function trocarAba(aba) {
            // Esconder todas as abas
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            
            // Mostrar aba selecionada
            document.getElementById('tab-' + aba).classList.add('active');
            event.target.classList.add('active');
        }
        
        function abrirModal(id) {
            document.getElementById(id).style.display = 'block';
        }
        
        function fecharModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        
        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
