<?php
/**
 * ========================================================================
 * ZIIPVET - LISTAGEM DE DIAGNÓSTICOS POR IA
 * VERSÃO: 1.2.0 - PADRONIZAÇÃO E FILTROS
 * ========================================================================
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- 1. LÓGICA DE EXCLUSÃO (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excluir') {
    header('Content-Type: application/json');
    $id = (int)$_POST['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM atendimentos WHERE id = ? AND tipo_atendimento = 'Diagnóstico IA'");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success']);
    } catch (PDOException $e) { 
        echo json_encode(['status' => 'error', 'erro' => $e->getMessage()]); 
    }
    exit;
}

$titulo_pagina = "Histórico de Diagnósticos IA";

// --- 2. CAPTURA DE FILTROS ---
$id_paciente_filtro = $_GET['id_paciente'] ?? '';

// --- 3. CARREGAR LISTA DE PACIENTES PARA FILTRO ---
try {
    $sql_pacs = "SELECT p.id as id_paciente, p.nome_paciente, c.nome as nome_cliente 
                FROM pacientes p 
                INNER JOIN clientes c ON p.id_cliente = c.id 
                ORDER BY c.nome ASC";
    $lista_pacientes = $pdo->query($sql_pacs)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $err) { 
    $lista_pacientes = [];
}

// --- 4. CARREGAR LISTA DE DIAGNÓSTICOS COM FILTROS ---
try {
    $sql = "SELECT a.*, p.nome_paciente, p.especie, p.raca, c.nome as nome_tutor 
            FROM atendimentos a
            INNER JOIN pacientes p ON a.id_paciente = p.id
            INNER JOIN clientes c ON p.id_cliente = c.id
            WHERE a.tipo_atendimento = 'Diagnóstico IA'";
    
    $params = [];
    if ($id_paciente_filtro) {
        $sql .= " AND a.id_paciente = :id_p";
        $params[':id_p'] = $id_paciente_filtro;
    }
    
    $sql .= " ORDER BY a.data_atendimento DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $diagnosticos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $diagnosticos = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    
    <!-- FONTES, ICONES E ESTILOS -->
    <link href="https://fonts.googleapis.com/css2?family=Exo:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS CENTRALIZADO -->
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/menu.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/formularios.css">
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { 
            --ia-gradient: linear-gradient(135deg, #131c71 0%, #622599 100%);
            --borda: #d2d6de;
        }

        .filtros-box { 
            background: #fff; 
            padding: 20px; 
            border-radius: 12px; 
            margin-bottom: 25px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.03); 
            display: grid; 
            grid-template-columns: 2fr auto auto; 
            gap: 15px; 
            align-items: flex-end; 
        }
        .filtros-box label { font-size: 12px; font-weight: 700; color: #888; text-transform: uppercase; margin-bottom: 5px; display: block; }

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
            margin-bottom: 15px; 
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

        .diag-meta { font-size: 13px; color: #666; margin-top: 5px; display: flex; gap: 20px; }
        .diag-meta i { color: #622599; margin-right: 5px; }

        .diag-content { 
            font-size: 15px; 
            line-height: 1.8; 
            color: #444; 
            max-height: 120px; 
            overflow: hidden; 
            position: relative;
            transition: max-height 0.4s ease; 
        }

        .diag-content.expanded { max-height: 10000px; }
        
        .diag-content:not(.expanded)::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 40px;
            background: linear-gradient(transparent, #fff);
        }

        .btn-expandir { 
            color: #622599; 
            cursor: pointer; 
            font-weight: 700; 
            font-size: 13px; 
            margin-top: 10px; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px;
            text-transform: uppercase;
        }

        .btn-delete { 
            background: #fff5f5; 
            border: 1px solid #ffe3e3; 
            color: #ff4757; 
            cursor: pointer; 
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }
        .btn-delete:hover { background: #ff4757; color: #fff; }

        .sintomas-box {
            background: #f8f9fa; 
            border-radius: 10px; 
            padding: 15px; 
            margin-bottom: 20px; 
            border-left: 3px solid #131c71;
        }
        
        .texto-ia h4 { color: #131c71; margin: 20px 0 10px; font-size: 16px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .texto-ia strong { color: #555; }
        
        .select2-container--default .select2-selection--single { height: 45px; border: 1px solid var(--borda); border-radius: 8px; display: flex; align-items: center; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 45px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 45px; }

    </style>
</head>
<body class="hold-transition sidebar-mini">

    <aside class="sidebar-container"><?php include '../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../menu/faixa.php'; ?></header>

    <main class="main-content">
        <div class="form-header">
            <h1 class="form-title">
                <i class="fas fa-robot"></i>
                <?= $titulo_pagina ?>
            </h1>
            <p style="font-size: 15px; color: #666; font-weight: 500;">
                Visualize as análises detalhadas geradas pela inteligência artificial.
            </p>
        </div>

        <form method="GET" class="filtros-box">
            <div>
                <label>Filtrar por Paciente ou Tutor</label>
                <select name="id_paciente" id="select_filtro_ia" class="form-control">
                    <option value="">Todos os Diagnósticos</option>
                    <?php foreach($lista_pacientes as $p): ?>
                        <option value="<?= $p['id_paciente'] ?>" <?= ($id_paciente_filtro == $p['id_paciente']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nome_cliente']) ?> - Pet: <?= htmlspecialchars($p['nome_paciente']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" style="background: var(--cor-principal); color: #fff; border: none; border-radius: 8px; height: 45px; padding: 0 25px; cursor: pointer; font-weight: 700; text-transform: uppercase;">Filtrar</button>
            <?php if($id_paciente_filtro): ?>
                <a href="listar_diagnosticos.php" style="background: #e0e0e0; color: #333; border-radius: 8px; height: 45px; padding: 0 20px; display: inline-flex; align-items: center; text-decoration: none; font-weight: 600; font-size: 13px;">Limpar</a>
            <?php endif; ?>
        </form>

        <?php if (empty($diagnosticos)): ?>
            <div class="card-diagnostico" style="text-align: center; padding: 60px;">
                <i class="fas fa-brain fa-4x" style="margin-bottom: 20px; opacity: 0.2; color: #622599;"></i>
                <h3>Nenhum diagnóstico por IA encontrado</h3>
                <p>Realize um diagnóstico assistido no console de atendimento do paciente.</p>
            </div>
        <?php else: ?>
            <?php foreach ($diagnosticos as $d): ?>
                <div class="card-diagnostico" id="diagnostico-item-<?= $d['id'] ?>">
                    <div class="diag-header">
                        <div>
                            <div class="diag-title">
                                <i class="fas fa-paw"></i> <?= htmlspecialchars($d['nome_paciente']) ?> 
                                <span class="badge-pet"><?= htmlspecialchars($d['especie']) ?> | <?= htmlspecialchars($d['raca'] ?: 'SRD') ?></span>
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
                            <small style="color: #131c71; font-weight: 800; text-transform: uppercase;">Resumo do Atendimento:</small>
                            <div style="margin-top: 5px; font-weight: 600; color: #333;">
                                <?= htmlspecialchars($d['resumo']) ?>
                            </div>
                        </div>
                        <div class="texto-ia" style="white-space: pre-wrap;"><?= $d['descricao'] ?></div>
                    </div>
                    
                    <div class="btn-expandir" onclick="toggleContent(<?= $d['id'] ?>, this)">
                        <i class="fas fa-chevron-down"></i> Ver análise completa
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <script>
        $(document).ready(function() {
            // Inicializar Select2
            $('#select_filtro_ia').select2({
                placeholder: "Pesquise por Pet ou Tutor...",
                width: '100%'
            });
        });

        function toggleContent(id, btn) {
            const content = $('#content-' + id);
            content.toggleClass('expanded');
            if(content.hasClass('expanded')) {
                $(btn).html('<i class="fas fa-chevron-up"></i> Recolher análise');
            } else {
                $(btn).html('<i class="fas fa-chevron-down"></i> Ver análise completa');
            }
        }

        function excluirDiagnostico(id) {
            Swal.fire({
                title: 'Remover Análise IA?',
                text: "O registro será excluído permanentemente do histórico do paciente.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ff4757',
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('listar_diagnosticos.php', { acao: 'excluir', id: id }, function(res) {
                        if(res.status === 'success') {
                            $('#diagnostico-item-' + id).fadeOut(400, function() { $(this).remove(); });
                            Swal.fire('Sucesso', 'Análise removida com sucesso.', 'success');
                        } else {
                            Swal.fire('Erro', 'Não foi possível remover o registro.', 'error');
                        }
                    }, 'json');
                }
            });
        }
    </script>
</body>
</html>
ript>
</body>
</html>