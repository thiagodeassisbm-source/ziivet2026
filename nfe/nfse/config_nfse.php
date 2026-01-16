<?php
require_once '../../auth.php';
require_once '../../config/configuracoes.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- PHP LOGIC: SAVE CONFIG ---
$id_admin = $_SESSION['id_admin']; // Assuming session var
$msg = "";

// 1. Save Main Config
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_main') {
    $im = $_POST['im'] ?? '';
    $cnae_principal = $_POST['cnae_principal'] ?? '';
    $regime = $_POST['regime_tributario'] ?? '';
    $login_pref = $_POST['login_pref'] ?? '';
    $senha_pref = $_POST['senha_pref'] ?? '';
    $serie_rps = $_POST['serie_rps'] ?? '';
    $num_nfse = $_POST['num_ultima_nfse'] ?? 0;
    
    // Update or Insert logic (simplified update)
    $stmt = $pdo->prepare("UPDATE configuracoes_fiscais SET 
        inscricao_municipal = ?, 
        cnae_principal = ?, 
        regime_tributario = ?,
        login_prefeitura = ?,
        senha_prefeitura = ?,
        serie_nfse = ?,
        num_ultima_nfse = ?
        WHERE id_admin = ?");
    if($stmt->execute([$im, $cnae_principal, $regime, $login_pref, $senha_pref, $serie_rps, $num_nfse, $id_admin])) {
        $msg = "Configurações salvas com sucesso!";
    } else {
        $msg = "Erro ao salvar configurações.";
    }
}

// 2. Add Service Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_service') {
    $cnae = $_POST['cnae'] ?? '';
    $lc116 = $_POST['lc116'] ?? '';
    $nbs = $_POST['nbs'] ?? '';
    $aliq = $_POST['aliq'] ?? 0;
    
    $stmt = $pdo->prepare("INSERT INTO nfse_servicos_config (id_admin, cnae, item_lc116, codigo_nbs, aliquota_iss) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$id_admin, $cnae, $lc116, $nbs, $aliq]);
    $msg = "Serviço adicionado!";
}

// 3. Delete Service Logic
if (isset($_GET['delete_service'])) {
    $id_serv = $_GET['delete_service'];
    $pdo->prepare("DELETE FROM nfse_servicos_config WHERE id = ? AND id_admin = ?")->execute([$id_serv, $id_admin]);
    header("Location: config_nfse.php");
    exit;
}

// --- FETCH DATA ---
$config = $pdo->prepare("SELECT * FROM configuracoes_fiscais WHERE id_admin = ?");
$config->execute([$id_admin]);
$c = $config->fetch(PDO::FETCH_ASSOC) ?: [];

$servicos = $pdo->prepare("SELECT * FROM nfse_servicos_config WHERE id_admin = ? ORDER BY id DESC");
$servicos->execute([$id_admin]);
$lista_servicos = $servicos->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações NFS-e | ZiipVet</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/menu.css">
    <link rel="stylesheet" href="../../css/header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #ddd; }
        .tab-btn { padding: 10px 20px; cursor: pointer; border: none; background: none; font-weight: bold; color: #666; border-bottom: 3px solid transparent; }
        .tab-btn.active { color: #622599; border-bottom: 3px solid #622599; }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #333; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; }
        .ziip-table th { background: #f8f9fa; color: #333; padding: 10px; }
        .ziip-table td { padding: 10px; border-bottom: 1px solid #eee; }
    </style>
</head>
<body>
    <?php $path_prefix = '../../'; ?>
    <aside class="sidebar-container"><?php include '../../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../../menu/faixa.php'; ?></header>

    <main class="main-content">
        <div class="form-header">
             <h1><i class="fas fa-cogs"></i> Configurações NFS-e (Serviços)</h1>
        </div>

        <?php if($msg): ?>
            <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <div class="content-box" style="background:#fff; padding:30px; border-radius:12px;">
            
            <div class="tabs">
                <button class="tab-btn active" onclick="openTab(event, 'tab-config')"><i class="fas fa-sliders-h"></i> Configuração</button>
                <button class="tab-btn" onclick="openTab(event, 'tab-empresa')"><i class="fas fa-building"></i> Dados Empresa</button>
                <button class="tab-btn" onclick="openTab(event, 'tab-servicos')"><i class="fas fa-list-ul"></i> Serviços Prestados (NBS)</button>
            </div>

            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="save_main">
                
                <!-- TAB 1: CONFIGURAÇÃO -->
                <div id="tab-config" class="tab-content active">
                    <h3>Parâmetros de Emissão NFS-e</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Ambiente de Emissão</label>
                            <select name="ambiente" disabled style="background:#eee;">
                                <option>Acompanha Config. Global (<?= ($c['ambiente']??2)==1?'Produção':'Homologação' ?>)</option>
                            </select>
                            <small>Para mudar, vá em NFC-e > Configurações Fiscais</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Último Número NFS-e Emitida</label>
                            <input type="number" name="num_ultima_nfse" value="<?= $c['num_ultima_nfse'] ?? 0 ?>">
                            <small>O sistema incrementará a partir deste número.</small>
                        </div>
                        <div class="form-group">
                            <label>Série NFS-e / RPS</label>
                            <input type="text" name="serie_rps" value="<?= $c['serie_nfse'] ?? '1' ?>">
                        </div>
                    </div>

                    <div class="form-row">
                         <div class="form-group">
                            <label>Login Prefeitura (Se aplicável)</label>
                            <input type="text" name="login_pref" value="<?= $c['login_prefeitura'] ?? '' ?>">
                         </div>
                         <div class="form-group">
                            <label>Senha Prefeitura (Se aplicável)</label>
                            <input type="password" name="senha_pref" value="<?= $c['senha_prefeitura'] ?? '' ?>">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-ziip" style="background:#28a745; color:#fff; padding:10px 25px; border:none; border-radius:5px; margin-top:10px;">
                        <i class="fas fa-save"></i> Salvar Configurações
                    </button>
                </div>

                <!-- TAB 2: EMPRESA -->
                <div id="tab-empresa" class="tab-content">
                    <h3>Dados Cadastrais NFS-e</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Inscrição Municipal *</label>
                            <input type="text" name="im" value="<?= $c['inscricao_municipal'] ?? '' ?>" required>
                        </div>
                        <div class="form-group">
                            <label>CNAE Principal</label>
                            <input type="text" name="cnae_principal" value="<?= $c['cnae_principal'] ?? '' ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Regime Tributário</label>
                        <select name="regime_tributario">
                            <!-- Populated mostly, defaulting to Simples -->
                            <option value="1" <?= ($c['regime_tributario']??'')=='1'?'selected':'' ?>>Simples Nacional</option>
                            <option value="3" <?= ($c['regime_tributario']??'')=='3'?'selected':'' ?>>Regime Normal</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-ziip" style="background:#28a745; color:#fff; padding:10px 25px; border:none; border-radius:5px; margin-top:10px;">
                        <i class="fas fa-save"></i> Salvar Dados Empresa
                    </button>
                </div>
            </form>

            <!-- TAB 3: SERVIÇOS (Requires separate form or logic) -->
            <div id="tab-servicos" class="tab-content">
                <h3>Serviços Prestados (NBS / LC 116)</h3>
                <p style="color:#666; font-size:14px;">Cadastre aqui os serviços que sua clínica realiza para facilitar a emissão.</p>
                
                <form action="" method="POST" style="background:#f9f9f9; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #eee;">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="add_service">
                    <h4>Adicionar Novo Serviço</h4>
                    <div style="display:grid; grid-template-columns: 1fr 1fr 1fr 1fr 100px; gap:10px; align-items:end;">
                        <div class="form-group">
                            <label style="font-size:12px;">CNAE</label>
                            <input type="text" name="cnae" placeholder="Ex: 9609208" required>
                        </div>
                        <div class="form-group">
                            <label style="font-size:12px;">Item LC 116</label>
                            <input type="text" name="lc116" placeholder="Ex: 05.08" required>
                        </div>
                        <div class="form-group">
                            <label style="font-size:12px;">Código NBS</label>
                            <input type="text" name="nbs" placeholder="Ex: 114056000">
                        </div>
                        <div class="form-group">
                            <label style="font-size:12px;">Alíquota (%)</label>
                            <input type="text" name="aliq" placeholder="Ex: 2.00">
                        </div>
                        <div>
                            <button type="submit" style="background:#622599; color:#fff; border:none; padding:10px; border-radius:5px; width:100%; cursor:pointer;">Add</button>
                        </div>
                    </div>
                </form>

                <table class="ziip-table" style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th style="text-align:left;">CNAE</th>
                            <th style="text-align:left;">Item LC 116</th>
                            <th style="text-align:left;">Código NBS</th>
                            <th style="text-align:center;">Alíquota</th>
                            <th style="text-align:center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($lista_servicos as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['cnae']) ?></td>
                            <td><?= htmlspecialchars($s['item_lc116']) ?></td>
                            <td><?= htmlspecialchars($s['codigo_nbs']) ?></td>
                            <td style="text-align:center;"><?= number_format($s['aliquota_iss'], 2, ',', '.') ?>%</td>
                            <td style="text-align:center;">
                                <a href="?delete_service=<?= $s['id'] ?>" style="color:#dc3545;" onclick="return confirm('Remover este item?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($lista_servicos)): ?>
                            <tr><td colspan="5" style="text-align:center; padding:20px; color:#999;">Nenhum serviço configurado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>

    <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
                tabcontent[i].classList.remove("active");
            }
            tablinks = document.getElementsByClassName("tab-btn");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active", "");
            }
            document.getElementById(tabName).style.display = "block";
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.className += " active";
        }
    </script>
</body>
</html>
