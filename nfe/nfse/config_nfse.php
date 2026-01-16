<?php
require_once '../../auth.php';
require_once '../../config/configuracoes.php';
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
</head>
<body>
    <?php $path_prefix = '../../'; ?>
    <aside class="sidebar-container"><?php include '../../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../../menu/faixa.php'; ?></header>

    <main class="main-content">
        <div class="form-header">
             <h1><i class="fas fa-cogs"></i> Configurações NFS-e (Prefeitura)</h1>
        </div>

        <form action="#" method="POST" style="background:#fff; padding:30px; border-radius:12px; max-width:800px;">
            <div class="alert-info" style="background:#fff3cd; color:#856404; padding:15px; border-radius:8px; margin-bottom:20px;">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Atenção:</strong> As configurações abaixo variam de acordo com a prefeitura do seu município.
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:bold;">Inscrição Municipal</label>
                    <input type="text" name="im" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:bold;">Série RPS</label>
                    <input type="text" name="serie_rps" placeholder="Ex: 1" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:bold;">Login/Usuário Prefeitura</label>
                    <input type="text" name="login_pref" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:bold;">Senha Prefeitura</label>
                    <input type="password" name="senha_pref" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">
                </div>
            </div>

            <button type="button" class="btn-ziip" style="background:#1e40af; color:#fff; padding:12px 30px; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">
                <i class="fas fa-save"></i> Salvar Configurações
            </button>
        </form>
    </main>
</body>
</html>
