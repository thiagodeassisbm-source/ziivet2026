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
    <!-- FONTES E ICONES -->
    <link href="https://fonts.googleapis.com/css2?family=Exo:wght@300;400;500;600;700;800&family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS CENTRALIZADO -->
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/menu.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/formularios.css">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { 
            --ia-gradient: linear-gradient(135deg, #131c71 0%, #622599 100%);
            --sidebar-width: 220px;
        }

        body { font-family: 'Source Sans Pro', sans-serif; background-color: #f4f6f9; }
        h1, h2, h3, .form-title { font-family: 'Exo', sans-serif; }

        /* Ajustes de Layout para garantir enquadramento */
        .main-content { 
            margin-left: var(--sidebar-width); 
            padding: calc(var(--header-height) + 30px) 30px 40px; 
            width: auto;
        }

        .page-header-standard { 
            background: var(--ia-gradient); 
            border-radius: 12px; 
            padding: 30px; 
            color: #fff; 
            margin-bottom: 30px; 
            display: flex; 
            align-items: center; 
            gap: 20px;
            box-shadow: 0 4px 15px rgba(19, 28, 113, 0.2);
        }
        
        .card-diagnostico { 
            background: #fff; 
            border-radius: 12px; 
            padding: 25px; 
            margin-bottom: 25px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
            border-left: 6px solid #622599; 
        }

        .diag-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-start; 
            border-bottom: 1px solid #f0f0f0; 
            padding-bottom: 15px; 
            margin-bottom: 20px; 
        }

        .diag-title { 
            color: #131c71; 
            font-size: 18px; 
            font-weight: 700;
            display: flex; 
            align-items: center; 
            gap: 12px; 
        }

        .badge-pet { 
            background: #f0f2ff; 
            color: #131c71; 
            padding: 4px 12px; 
            border-radius: 6px; 
            font-size: 12px; 
            font-weight: 700; 
            text-transform: uppercase; 
        }

        .diag-content { 
            font-size: 15px; 
            line-height: 1.7; 
            color: #444; 
            max-height: 100px; 
            overflow: hidden; 
            transition: max-height 0.4s ease-out; 
        }

        .diag-content.expanded { max-height: 5000px; }
        
        .btn-expandir { 
            color: #622599; 
            cursor: pointer; 
            font-weight: 700; 
            font-size: 14px; 
            margin-top: 15px; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px;
            padding: 8px 16px;
            background: #f8f7ff;
            border-radius: 8px;
        }

        .btn-delete { 
            background: #fff5f5; 
            border: 1px solid #ffe3e3; 
            color: #ff4757; 
            cursor: pointer; 
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sintomas-box {
            background: #f9f9ff; 
            border-radius: 10px; 
            padding: 15px; 
            margin-bottom: 20px; 
            border: 1px solid #edeefa;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">

    <aside class="sidebar-container">
        <?php include '../menu/menulateral.php'; ?>
    </aside>

    <header class="top-header">
        <?php include '../menu/faixa.php'; ?>
    </header>

    <main class="main-content">
        <div class="page-header-standard">
            <i class="fas fa-robot fa-3x"></i>
            <div>
                <h1 style="font-weight: 700; margin-bottom: 5px;"><?= $titulo_pagina ?></h1>
                <p style="opacity: 0.9;">Consulte aqui todas as análises inteligentes geradas para seus pacientes pelo Diagnóstico IA.</p>
            </div>
        </div>

        <?php if (empty($diagnosticos)): ?>
            <div class="no-data">
                <i class="fas fa-comment-slash fa-4x" style="margin-bottom: 25px; opacity: 0.2;"></i>
                <h3 style="color: #444; font-weight: 700;">Nenhum diagnóstico registrado</h3>
                <p>As análises aparecerão aqui após serem concluídas no Console de Atendimento.</p>
            </div>
        <?php else: ?>
            <?php foreach ($diagnosticos as $d): ?>
                <div class="card-diagnostico">
                    <div class="diag-header">
                        <div>
                            <div class="diag-title">
                                <i class="fas fa-dog"></i> <?= htmlspecialchars($d['nome_paciente']) ?> 
                                <span class="badge-pet"><?= htmlspecialchars($d['especie']) ?> | <?= htmlspecialchars($d['raca']) ?></span>
                            </div>
                            <div class="diag-meta">
                                <span><i class="fas fa-user-circle"></i> <strong>Tutor:</strong> <?= htmlspecialchars($d['nome_tutor']) ?></span>
                                <span><i class="far fa-calendar-alt"></i> <?= date('d/m/Y H:i', strtotime($d['data_atendimento'])) ?></span>
                            </div>
                        </div>
                        <button onclick="excluirDiagnostico(<?= $d['id'] ?>)" class="btn-delete" title="Excluir Diagnóstico">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                    
                    <div class="diag-content" id="content-<?= $d['id'] ?>">
                        <div class="sintomas-box">
                            <small style="color: #622599; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">Sintomas Analisados:</small>
                            <div style="margin-top: 5px; font-weight: 600; color: #444;">
                                <?= htmlspecialchars($d['resumo']) ?>
                            </div>
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