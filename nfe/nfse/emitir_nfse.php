<?php
require_once '../../auth.php';
require_once '../../config/configuracoes.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emitir NFS-e | ZiipVet</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/menu.css">
    <link rel="stylesheet" href="../../css/header.css">
    <link rel="stylesheet" href="../../css/formularios.css"> <!-- Assuming exists or reuse style -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php $path_prefix = '../../'; ?>
    <aside class="sidebar-container"><?php include '../../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../../menu/faixa.php'; ?></header>

    <main class="main-content">
        <div class="form-header">
             <h1><i class="fas fa-file-contract"></i> Emitir NFS-e</h1>
        </div>

        <form action="#" method="POST" style="background:#fff; padding:30px; border-radius:12px;">
            <h3>Dados do Tomador</h3>
            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:15px; margin-bottom:20px;">
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:bold;">Cliente</label>
                    <select name="cliente" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">
                        <option>Selecione um cliente...</option>
                    </select>
                </div>
                <div>
                     <label style="display:block; margin-bottom:5px; font-weight:bold;">CPF/CNPJ</label>
                     <input type="text" disabled style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px; background:#f9f9f9;">
                </div>
            </div>

            <h3>Dados do Serviço</h3>
            <div style="display:grid; grid-template-columns: 1fr; gap:15px; margin-bottom:20px;">
                 <div>
                    <label style="display:block; margin-bottom:5px; font-weight:bold;">Descrição do Serviço</label>
                    <textarea rows="4" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;"></textarea>
                 </div>
            </div>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:15px; margin-bottom:20px;">
                 <div>
                    <label style="display:block; margin-bottom:5px; font-weight:bold;">Código Serviço (LC 116)</label>
                    <input type="text" placeholder="Ex: 04.03" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">
                 </div>
                 <div>
                    <label style="display:block; margin-bottom:5px; font-weight:bold;">Valor do Serviço (R$)</label>
                    <input type="text" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">
                 </div>
                 <div>
                    <label style="display:block; margin-bottom:5px; font-weight:bold;">Retenção ISS?</label>
                    <select style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">
                        <option value="0">Não</option>
                        <option value="1">Sim</option>
                    </select>
                 </div>
            </div>

            <hr style="margin:20px 0; border:0; border-top:1px solid #eee;">
            
            <button type="button" class="btn-ziip" style="background:#28a745; color:#fff; padding:12px 30px; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">
                <i class="fas fa-paper-plane"></i> Emitir NFS-e
            </button>
        </form>
    </main>
</body>
</html>
