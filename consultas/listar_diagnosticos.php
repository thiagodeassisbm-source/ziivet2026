<?php
/**
 * ========================================================================
 * ZIIPVET - LISTAGEM DE DIAGNÓSTICOS POR IA
 * VERSÃO: 1.1.0 (Correção de Sintaxe e Layout)
 * ========================================================================
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$titulo_pagina = "Histórico de Diagnósticos IA";

// Busca os diagnósticos salvos na tabela atendimentos
try {
    $sql = "SELECT a.*, p.nome_paciente, p.especie, p.raca, c.nome as nome_tutor 
            FROM atendimentos a
            INNER JOIN pacientes p ON a.id_paciente = p.id
            INNER JOIN clientes c ON p.id_cliente = c.id
            WHERE a.tipo_atendimento = 'Diagnóstico IA'
            ORDER BY a.data_atendimento DESC";
    $diagnosticos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $diagnosticos = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    <base href="https://www.lepetboutique.com.br/app/">
    
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
    .faixa-superior { width: 100% !important; margin: 0 !important; border-radius: 0 0 0 30px !important; }
        :root { 
            --fundo: #ecf0f5; --primaria: #1c329f; --borda: #d2d6de;
            --sidebar-collapsed: 75px; --sidebar-expanded: 260px; --header-height: 80px;
            --ia-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        /* Fonte Global Source Sans Pro */
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Source Sans Pro', sans-serif; }
        body { background-color: var(--fundo); color: #333; font-weight: 400; }

        /* Pesos de Fonte */
        h1, h2, h3, h4, strong, .card-header, .diag-title { font-weight: 600 !important; }

        /* Estrutura de Layout */
        aside.sidebar-container { position: fixed; left: 0; top: 0; height: 100vh; width: var(--sidebar-collapsed); z-index: 1000; background: #fff; transition: width 0.4s; box-shadow: 2px 0 5px rgba(0,0,0,0.05); }
        aside.sidebar-container:hover { width: var(--sidebar-expanded); }
        header.top-header { position: fixed; top: 0; left: var(--sidebar-collapsed); right: 0; height: var(--header-height); z-index: 900; background: #fff; transition: left 0.4s; }
        aside.sidebar-container:hover ~ header.top-header { left: var(--sidebar-expanded); }
        main.main-content { margin-left: var(--sidebar-collapsed); padding: calc(var(--header-height) + 25px) 25px 40px; transition: margin-left 0.4s; }
        aside.sidebar-container:hover ~ main.main-content { margin-left: var(--sidebar-expanded); }

        .page-header { background: var(--ia-gradient); border-radius: 16px; padding: 25px; color: #fff; margin-bottom: 25px; display: flex; align-items: center; gap: 20px; }
        
        .card-diagnostico { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-left: 6px solid #764ba2; transition: 0.3s; }
        .card-diagnostico:hover { transform: translateX(5px); }

        .diag-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px; }
        .diag-title { color: var(--primaria); font-size: 18px; display: flex; align-items: center; gap: 10px; }
        .diag-meta { font-size: 17px; color: #777; margin-top: 5px; }
        
        .badge-pet { background: #eef1ff; color: var(--primaria); padding: 4px 10px; border-radius: 20px; font-size: 17px; font-weight: 600; text-transform: uppercase; }

        .diag-content { font-size: 17px; line-height: 1.6; color: #444; max-height: 80px; overflow: hidden; position: relative; transition: max-height 0.5s ease; }
        .diag-content.expanded { max-height: 2000px; }
        
        .btn-expandir { color: #764ba2; cursor: pointer; font-weight: 600; font-size: 17px; margin-top: 15px; display: flex; align-items: center; gap: 5px; }
        
        .no-data { text-align: center; padding: 80px 20px; color: #999; background: #fff; border-radius: 12px; }
        .btn-delete { background: none; border: none; color: #dc3545; cursor: pointer; padding: 5px; opacity: 0.6; transition: 0.2s; }
        .btn-delete:hover { opacity: 1; transform: scale(1.1); }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include '../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../menu/faixa.php'; ?></header>

    <main class="main-content">
        <div class="page-header">
            <i class="fas fa-microchip fa-2x"></i>
            <div>
                <h1>Histórico de Diagnósticos IA</h1>
                <p>Consulte aqui todas as análises inteligentes geradas para seus pacientes.</p>
            </div>
        </div>

        <?php if (empty($diagnosticos)): ?>
            <div class="no-data">
                <i class="fas fa-comment-slash fa-4x" style="margin-bottom: 20px; opacity: 0.2;"></i>
                <h3>Nenhum diagnóstico salvo</h3>
                <p>As análises aparecerão aqui após serem salvas no formulário de Diagnóstico IA.</p>
            </div>
        <?php else: ?>
            <?php foreach ($diagnosticos as $d): ?>
                <div class="card-diagnostico">
                    <div class="diag-header">
                        <div>
                            <div class="diag-title">
                                <i class="fas fa-paw"></i> <?= htmlspecialchars($d['nome_paciente']) ?> 
                                <span class="badge-pet"><?= htmlspecialchars($d['especie']) ?> (<?= htmlspecialchars($d['raca']) ?>)</span>
                            </div>
                            <div class="diag-meta">
                                <strong>Tutor:</strong> <?= htmlspecialchars($d['nome_tutor']) ?> | 
                                <i class="far fa-calendar-alt"></i> <?= date('d/m/Y H:i', strtotime($d['data_atendimento'])) ?>
                            </div>
                        </div>
                        <button onclick="excluirDiagnostico(<?= $d['id'] ?>)" class="btn-delete" title="Excluir Diagnóstico">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                    
                    <div class="diag-content" id="content-<?= $d['id'] ?>">
                        <div style="background: #fcfaff; border-radius: 8px; padding: 10px; margin-bottom: 10px; border-left: 3px solid #764ba2;">
                            <small><strong>Sintomas Analisados:</strong></small><br>
                            <?= htmlspecialchars($d['resumo']) ?>
                        </div>
                        <div class="texto-ia">
                            <?= $d['descricao'] ?>
                        </div>
                    </div>
                    
                    <div class="btn-expandir" onclick="toggleContent(<?= $d['id'] ?>, this)">
                        <i class="fas fa-chevron-down"></i> LER ANÁLISE COMPLETA
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <script>
        function toggleContent(id, btn) {
            const content = $('#content-' + id);
            content.toggleClass('expanded');
            if(content.hasClass('expanded')) {
                $(btn).html('<i class="fas fa-chevron-up"></i> RECOLHER ANÁLISE');
            } else {
                $(btn).html('<i class="fas fa-chevron-down"></i> LER ANÁLISE COMPLETA');
            }
        }

        function excluirDiagnostico(id) {
            Swal.fire({
                title: 'Excluir Diagnóstico?',
                text: "Esta análise será removida permanentemente do histórico do paciente.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'consultas/excluir_diagnostico.php?id=' + id;
                }
            });
        }
    </script>
</body>
</html>