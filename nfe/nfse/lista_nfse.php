<?php
require_once '../../auth.php';
require_once '../../config/configuracoes.php';

// Check if user has permission
// ...

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notas de Serviço (NFS-e) | ZiipVet</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/menu.css">
    <link rel="stylesheet" href="../../css/header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php $path_prefix = '../../'; ?>
    <aside class="sidebar-container">
        <?php include '../../menu/menulateral.php'; ?>
    </aside>
    <header class="top-header">
        <?php include '../../menu/faixa.php'; ?>
    </header>

    <main class="main-content">
        <div class="main-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h1><i class="fas fa-file-contract"></i> Notas Fiscais de Serviço (NFS-e)</h1>
            <a href="emitir_nfse.php" class="btn-ziip" style="background:#1e40af; color:#fff; padding:10px 20px; border-radius:8px; text-decoration:none;">
                <i class="fas fa-plus"></i> Emitir Nova NFS-e
            </a>
        </div>

        <div class="content-box">
             <div class="alert-info" style="background:#e3f2fd; color:#0d47a1; padding:15px; border-radius:8px; display:flex; gap:10px; align-items:center;">
                <i class="fas fa-info-circle" style="font-size:20px;"></i>
                <div>
                    <strong>Módulo em Desenvolvimento</strong><br>
                    Este módulo está sendo preparado para emissão de notas de serviço. Por favor configure as credenciais da prefeitura em "Configurações NFS-e".
                </div>
             </div>
             
             <!-- Tabela vazia por enquanto -->
             <table class="ziip-table" style="width:100%; margin-top:20px; border-collapse:collapse;">
                <thead>
                    <tr style="background:#f8f9fa;">
                        <th style="padding:10px; text-align:left;">Número</th>
                        <th style="padding:10px; text-align:left;">Data</th>
                        <th style="padding:10px; text-align:left;">Cliente</th>
                        <th style="padding:10px; text-align:left;">Serviço</th>
                        <th style="padding:10px; text-align:right;">Valor (R$)</th>
                        <th style="padding:10px; text-align:center;">Status</th>
                        <th style="padding:10px; text-align:center;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="7" style="padding:20px; text-align:center; color:#666;">Nenhuma nota emitida.</td>
                    </tr>
                </tbody>
             </table>
        </div>
    </main>
</body>
</html>
