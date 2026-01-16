<?php
/**
 * ZIIPVET - Perfis Tributários
 * Arquivo: nfe/perfil_tributario.php
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;
$titulo_pagina = "Perfis Tributários";

// Buscar perfis COM ST
$sql_com_st = "SELECT * FROM perfis_tributarios WHERE id_admin = ? AND tipo = 'COM_ST' ORDER BY id DESC";
$stmt_com_st = $pdo->prepare($sql_com_st);
$stmt_com_st->execute([$id_admin]);
$perfis_com_st = $stmt_com_st->fetchAll(PDO::FETCH_ASSOC);

// Buscar perfis SEM ST
$sql_sem_st = "SELECT * FROM perfis_tributarios WHERE id_admin = ? AND tipo = 'SEM_ST' ORDER BY id DESC";
$stmt_sem_st = $pdo->prepare($sql_sem_st);
$stmt_sem_st->execute([$id_admin]);
$perfis_sem_st = $stmt_sem_st->fetchAll(PDO::FETCH_ASSOC);

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
        .filtros-top {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .filtros-top select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            min-width: 200px;
        }
        
        .section-perfis {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .section-header {
            background: #28a745;
            color: #fff;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-header.sem-st {
            background: #17a2b8;
        }
        
        .section-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
        }
        
        .btn-adicionar {
            background: rgba(255,255,255,0.2);
            color: #fff;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-adicionar:hover {
            background: rgba(255,255,255,0.3);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: #495057;
            text-transform: uppercase;
            border-bottom: 2px solid #e0e0e0;
        }
        
        tbody td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .btn-acao {
            background: #6c757d;
            color: #fff;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-acao:hover {
            background: #5a6268;
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
                <i class="fas fa-tags"></i>
                <?= $titulo_pagina ?>
            </h1>
            <a href="envios_nfe.php" class="btn-voltar">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <!-- FILTROS -->
        <div class="filtros-top">
            <select onchange="window.location.href='?filtro=' + this.value">
                <option value="">Todos os perfis</option>
                <option value="com_st">COM Substituição Tributária</option>
                <option value="sem_st">SEM Substituição Tributária</option>
            </select>
            <button class="btn-ziip" style="background:#1e40af; color:#fff; border:none; padding:10px 20px; border-radius:8px; cursor:pointer;">
                <i class="fas fa-redo"></i> Recarregar
            </button>
            <a href="cadastro_perfil_tributario.php?tipo=COM_ST" class="btn-ziip" style="background:#28a745; color:#fff; text-decoration:none; padding:10px 20px; border-radius:8px; display:inline-flex; align-items:center; gap:8px;">
                <i class="fas fa-plus"></i> Adicionar
            </a>
        </div>

        <!-- PERFIS COM ST -->
        <div class="section-perfis">
            <div class="section-header">
                <h3>
                    <i class="fas fa-check-circle"></i>
                    Produtos COM Substituição Tributária
                </h3>
                <a href="cadastro_perfil_tributario.php?tipo=COM_ST" class="btn-adicionar">
                    <i class="fas fa-plus"></i>
                    Adicionar regra
                </a>
                <button class="btn-adicionar" style="display:inline-flex; align-items:center; gap:8px;">
                    <i class="fas fa-file-import"></i>
                    Importar
                </button>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Cód</th>
                        <th>Início</th>
                        <th>Término</th>
                        <th>Operação</th>
                        <th>NCM</th>
                        <th>CEST</th>
                        <th>EX TIPI</th>
                        <th>Aquisição</th>
                        <th>Origem</th>
                        <th>CSOSN</th>
                        <th>CST PIS/COFINS</th>
                        <th style="text-align:center;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($perfis_com_st) > 0): ?>
                        <?php foreach ($perfis_com_st as $perfil): ?>
                            <tr>
                                <td><?= $perfil['id'] ?></td>
                                <td><?= date('d/m/Y', strtotime($perfil['inicio_vigencia'])) ?></td>
                                <td><?= $perfil['fim_vigencia'] ? date('d/m/Y', strtotime($perfil['fim_vigencia'])) : '-' ?></td>
                                <td><?= htmlspecialchars($perfil['operacao']) ?></td>
                                <td><?= htmlspecialchars($perfil['ncm'] ?? '') ?></td>
                                <td><?= htmlspecialchars($perfil['cest'] ?? '') ?></td>
                                <td><?= htmlspecialchars($perfil['ex_tipi'] ?? '') ?></td>
                                <td><?= htmlspecialchars($perfil['forma_aquisicao'] ?? '') ?></td>
                                <td><?= $perfil['origem_mercadoria'] ?? '0' ?></td>
                                <td><?= htmlspecialchars($perfil['csosn'] ?? '500') ?></td>
                                <td><?= htmlspecialchars($perfil['cst_pis'] ?? '99') ?></td>
                                <td style="text-align:center;">
                                    <a href="cadastro_perfil_tributario.php?id=<?= $perfil['id'] ?>" class="btn-acao">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" style="text-align:center; padding:30px; color:#999;">
                                Nenhum perfil cadastrado
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PERFIS SEM ST -->
        <div class="section-perfis">
            <div class="section-header sem-st">
                <h3>
                    <i class="fas fa-times-circle"></i>
                    Produtos SEM Substituição Tributária
                </h3>
                <a href="cadastro_perfil_tributario.php?tipo=SEM_ST" class="btn-adicionar">
                    <i class="fas fa-plus"></i>
                    Adicionar regra
                </a>
                <button class="btn-adicionar" style="display:inline-flex; align-items:center; gap:8px;">
                    <i class="fas fa-file-import"></i>
                    Importar
                </button>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Cód</th>
                        <th>Início</th>
                        <th>Término</th>
                        <th>Operação</th>
                        <th>NCM</th>
                        <th>CEST</th>
                        <th>EX TIPI</th>
                        <th>Aquisição</th>
                        <th>Origem</th>
                        <th>CSOSN</th>
                        <th>CST PIS/COFINS</th>
                        <th style="text-align:center;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($perfis_sem_st) > 0): ?>
                        <?php foreach ($perfis_sem_st as $perfil): ?>
                            <tr>
                                <td><?= $perfil['id'] ?></td>
                                <td><?= date('d/m/Y', strtotime($perfil['inicio_vigencia'])) ?></td>
                                <td><?= $perfil['fim_vigencia'] ? date('d/m/Y', strtotime($perfil['fim_vigencia'])) : '-' ?></td>
                                <td><?= htmlspecialchars($perfil['operacao']) ?></td>
                                <td><?= htmlspecialchars($perfil['ncm'] ?? '') ?></td>
                                <td><?= htmlspecialchars($perfil['cest'] ?? '') ?></td>
                                <td><?= htmlspecialchars($perfil['ex_tipi'] ?? '') ?></td>
                                <td><?= htmlspecialchars($perfil['forma_aquisicao'] ?? '') ?></td>
                                <td><?= $perfil['origem_mercadoria'] ?? '0' ?></td>
                                <td><?= htmlspecialchars($perfil['csosn'] ?? '102') ?></td>
                                <td><?= htmlspecialchars($perfil['cst_pis'] ?? '99') ?></td>
                                <td style="text-align:center;">
                                    <a href="cadastro_perfil_tributario.php?id=<?= $perfil['id'] ?>" class="btn-acao">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" style="text-align:center; padding:30px; color:#999;">
                                Nenhum perfil cadastrado
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</body>
</html>
