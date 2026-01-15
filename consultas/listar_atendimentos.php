<?php
/**
 * =========================================================================================
 * ZIIPVET - LISTAGEM INTEGRADA DE ATENDIMENTOS
 * VERSÃO: 2.2.0 - SELECT2 PARA PESQUISA DE PACIENTES
 * Inclui: Busca por Select, Filtros, Gestão de Retorno e Impressão
 * =========================================================================================
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- 1. LÓGICA DE IMPRESSÃO (EMBUTIDA) ---
if (isset($_GET['imprimir'])) {
    $id_imp = (int)$_GET['imprimir'];
    try {
        $stmt = $pdo->prepare("SELECT a.*, p.nome_paciente, p.especie, p.raca, c.nome as nome_cliente 
                                FROM atendimentos a
                                INNER JOIN pacientes p ON a.id_paciente = p.id
                                INNER JOIN clientes c ON p.id_cliente = c.id
                                WHERE a.id = ?");
        $stmt->execute([$id_imp]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dados) die("Atendimento não encontrado.");

        echo "<!DOCTYPE html><html lang='pt-br'><head><meta charset='UTF-8'><title>Relatório - ZiipVet</title>";
        echo "<style>
                body { font-family: 'Open Sans', sans-serif; padding: 40px; color: #333; line-height: 1.6; }
                .header { text-align: center; border-bottom: 2px solid #1c329f; padding-bottom: 20px; margin-bottom: 30px; }
                .section { margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 8px; }
                .label { font-weight: bold; text-transform: uppercase; font-size: 11px; color: #888; display: block; }
                .content { font-size: 14px; color: #222; }
                .prontuario { background: #fafafa; padding: 25px; border: 1px solid #ddd; border-radius: 10px; margin-top: 20px; }
                @media print { .no-print { display: none; } }
              </style></head><body onload='window.print()'>";
        
        echo "<div class='header'><h1>RELATÓRIO CLÍNICO</h1><p>Sistema ZiipVet - Prontuário Digital</p></div>";
        echo "<div class='section'><span class='label'>Paciente</span><div class='content'>{$dados['nome_paciente']} ({$dados['especie']} / {$dados['raca']})</div></div>";
        echo "<div class='section'><span class='label'>Tutor</span><div class='content'>{$dados['nome_cliente']}</div></div>";
        echo "<div class='section'><span class='label'>Data e Hora</span><div class='content'>".date('d/m/Y H:i', strtotime($dados['data_atendimento']))."</div></div>";
        echo "<div class='section'><span class='label'>Tipo / Motivo</span><div class='content'>{$dados['tipo_atendimento']} - {$dados['resumo']}</div></div>";
        echo "<div class='prontuario'><span class='label'>Anamnese / Evolução</span><br>{$dados['descricao']}</div>";
        echo "<div style='margin-top: 70px; text-align: center; border-top: 1px solid #000; width: 300px; margin-left: auto; margin-right: auto;'>Assinatura Veterinária</div>";
        echo "</body></html>";
        exit;
    } catch (PDOException $e) { die("Erro: " . $e->getMessage()); }
}

// --- 2. LÓGICA AJAX PARA EXCLUIR APENAS RETORNO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excluir_retorno') {
    header('Content-Type: application/json');
    $id = (int)$_POST['id_atendimento'];
    try {
        $stmt = $pdo->prepare("UPDATE atendimentos SET data_retorno = NULL WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success']);
    } catch (PDOException $e) { echo json_encode(['status' => 'error']); }
    exit;
}

// --- 3. CAPTURA DE FILTROS ---
$id_paciente_filtro = $_GET['id_paciente'] ?? '';
$filtro_tipo = $_GET['tipo_atendimento'] ?? '';

// --- 4. CARREGAR LISTA DE PACIENTES ---
try {
    $sql_pacs = "SELECT p.id as id_paciente, p.nome_paciente, c.nome as nome_cliente 
                FROM pacientes p 
                INNER JOIN clientes c ON p.id_cliente = c.id 
                ORDER BY c.nome ASC";
    $lista_pacientes = $pdo->query($sql_pacs)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $err) { 
    $lista_pacientes = [];
}

// --- 5. CARREGAMENTO DA LISTAGEM COM FILTROS ---
try {
    $sql = "SELECT a.*, p.nome_paciente, c.nome as nome_cliente 
            FROM atendimentos a
            INNER JOIN pacientes p ON a.id_paciente = p.id
            INNER JOIN clientes c ON p.id_cliente = c.id
            WHERE 1=1";
    
    $params = [];
    
    if ($id_paciente_filtro) {
        $sql .= " AND a.id_paciente = :id_p";
        $params[':id_p'] = $id_paciente_filtro;
    }
    
    if ($filtro_tipo) {
        $sql .= " AND a.tipo_atendimento = :tipo";
        $params[':tipo'] = $filtro_tipo;
    }
    
    $sql .= " ORDER BY a.data_atendimento DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $atendimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { die("Erro de Banco: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Atendimentos | ZiipVet</title>
    
    <base href="https://www.lepetboutique.com.br/app/">
    
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* ==========================================================
           CSS PADRONIZADO V16.2 - ZIIPVET (ESTRUTURA FIXA 17PX)
           ========================================================== */
        :root { 
            --fundo: #ecf0f5; 
            --primaria: #1c329f; 
            --roxo-header: #6f42c1; 
            --azul-claro: #3258db; 
            --borda: #d2d6de;
            --sidebar-collapsed: 75px; 
            --sidebar-expanded: 260px; 
            --header-height: 80px;
            --transition: 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body { 
            font-family: 'Open Sans', sans-serif; 
            background-color: var(--fundo); 
            color: #333; 
            min-height: 100vh; 
            font-size: 15px;
            line-height: 1.6;
        }

        /* Estrutura de Layout Fixa */
        aside.sidebar-container { position: fixed; left: 0; top: 0; height: 100vh; width: var(--sidebar-collapsed); z-index: 1000; transition: width var(--transition); background: #fff; box-shadow: 2px 0 5px rgba(0,0,0,0.05); }
        aside.sidebar-container:hover { width: var(--sidebar-expanded); }
        
        header.top-header { position: fixed; top: 0; left: var(--sidebar-collapsed); right: 0; height: var(--header-height); z-index: 900; transition: left var(--transition); margin: 0 !important; }
        aside.sidebar-container:hover ~ header.top-header { left: var(--sidebar-expanded); }
        
        main.main-content { margin-left: var(--sidebar-collapsed); padding: calc(var(--header-height) + 30px) 25px 30px; transition: margin-left var(--transition); }
        aside.sidebar-container:hover ~ main.main-content { margin-left: var(--sidebar-expanded); }
        
        .faixa-superior { width: 100% !important; margin: 0 !important; border-radius: 0 0 0 30px !important; }

        /* Cabeçalho da Página */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-header h2 { font-size: 28px; font-weight: 600; color: #444; }

        .btn-novo { 
            background: var(--primaria); 
            color: #fff; 
            padding: 12px 24px; 
            border-radius: 8px; 
            text-decoration: none; 
            font-weight: 700; 
            font-size: 14px; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            text-transform: uppercase;
            transition: 0.2s;
        }
        .btn-novo:hover { background: #15257a; transform: translateY(-2px); }

        /* Barra de Filtros */
        .filtros-box { 
            background: #fff; 
            padding: 20px; 
            border-radius: 12px; 
            margin-bottom: 25px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.03); 
            display: grid; 
            grid-template-columns: 2fr 1fr auto; 
            gap: 15px; 
            align-items: flex-end; 
        }
        .filtros-box label { font-size: 12px; font-weight: 700; color: #888; text-transform: uppercase; margin-bottom: 5px; display: block; }
        .form-control { width: 100%; height: 45px; padding: 0 15px; border: 1px solid var(--borda); border-radius: 8px; outline: none; font-size: 16px; }

        /* Tabela Estilizada */
        .card-tabela { 
            background: #fff; 
            border-radius: 12px; 
            overflow: hidden; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.03); 
        }
        table { width: 100%; border-collapse: collapse; }
        thead { background: var(--primaria); }
        th { text-align: left; padding: 18px 15px; color: #fff; font-size: 13px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
        td { padding: 18px 15px; font-size: 17px; color: #444; border-bottom: 1px solid #f1f1f1; vertical-align: middle; }

        .badge-retorno { 
            background: #fff8e1; 
            color: #ffa000; 
            padding: 6px 12px; 
            border-radius: 6px; 
            font-weight: 700; 
            font-size: 14px; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
        }
        .btn-acao { color: #777; margin-right: 15px; font-size: 20px; transition: 0.2s; text-decoration: none; }
        .btn-acao:hover { color: var(--azul-claro); }

        .paciente-info strong { color: var(--primaria); display: block; font-size: 15px; }
        .paciente-info small { color: #888; font-weight: 600; font-size: 14px; }
        
        /* Select2 Customização */
        .select2-container--default .select2-selection--single { height: 45px; border: 1px solid var(--borda); border-radius: 8px; display: flex; align-items: center; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 45px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 45px; }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include '../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../menu/faixa.php'; ?></header>

    <main class="main-content">
        <div class="page-header">
            <h2>Histórico de Atendimentos</h2>
            <a href="consultas/atendimento.php" class="btn-novo"><i class="fas fa-plus"></i> Novo Atendimento</a>
        </div>

        <form method="GET" class="filtros-box">
            <div>
                <label>Paciente ou Tutor</label>
                <select name="id_paciente" id="select_busca_atendimento" class="form-control">
                    <option value="">Todos os Pacientes</option>
                    <?php foreach($lista_pacientes as $p): ?>
                        <option value="<?= $p['id_paciente'] ?>" <?= ($id_paciente_filtro == $p['id_paciente']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nome_cliente']) ?> - Pet: <?= htmlspecialchars($p['nome_paciente']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Tipo de Atendimento</label>
                <select name="tipo_atendimento" class="form-control">
                    <option value="">Todos os tipos</option>
                    <option value="Consulta" <?= $filtro_tipo == 'Consulta' ? 'selected' : '' ?>>Consulta</option>
                    <option value="Vacinação" <?= $filtro_tipo == 'Vacinação' ? 'selected' : '' ?>>Vacinação</option>
                    <option value="Cirurgia" <?= $filtro_tipo == 'Cirurgia' ? 'selected' : '' ?>>Cirurgia</option>
                    <option value="Retorno" <?= $filtro_tipo == 'Retorno' ? 'selected' : '' ?>>Retorno</option>
                </select>
            </div>
            <button type="submit" style="background: var(--azul-claro); color: #fff; border: none; border-radius: 8px; height: 45px; padding: 0 25px; cursor: pointer; font-weight: 700;">FILTRAR</button>
        </form>

        <div class="card-tabela">
            <table>
                <thead>
                    <tr>
                        <th style="width: 140px;">Data</th>
                        <th>Paciente / Tutor</th>
                        <th>Tipo / Resumo</th>
                        <th>Retorno Previsto</th>
                        <th style="text-align: center; width: 180px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($atendimentos) > 0): ?>
                        <?php foreach($atendimentos as $a): ?>
                        <tr>
                            <td><strong><?= date('d/m/Y', strtotime($a['data_atendimento'])) ?></strong></td>
                            <td class="paciente-info">
                                <strong><?= htmlspecialchars($a['nome_paciente']) ?></strong>
                                <small>Tutor: <?= htmlspecialchars($a['nome_cliente']) ?></small>
                            </td>
                            <td>
                                <span style="font-weight: 700; color: #555;"><?= htmlspecialchars($a['tipo_atendimento']) ?></span><br>
                                <small style="color:#888;"><?= htmlspecialchars($a['resumo']) ?></small>
                            </td>
                            <td>
                                <?php if($a['data_retorno']): ?>
                                    <span class="badge-retorno">
                                        <i class="fas fa-calendar-day"></i> <?= date('d/m/Y', strtotime($a['data_retorno'])) ?>
                                        <i class="fas fa-times-circle" onclick="excluirRetorno(<?= $a['id'] ?>)" style="cursor: pointer; color: #ff5252;" title="Remover data de retorno"></i>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#ccc; font-size: 13px;">Sem retorno</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <a href="consultas/atendimento.php?id=<?= $a['id'] ?>" class="btn-acao" title="Editar Atendimento"><i class="fas fa-edit"></i></a>
                                <a href="consultas/listar_atendimentos.php?imprimir=<?= $a['id'] ?>" target="_blank" class="btn-acao" title="Imprimir Relatório"><i class="fas fa-print"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center; padding: 80px; color: #999;">
                            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i><br>
                            Nenhum atendimento encontrado para os filtros aplicados.
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        $(document).ready(function() {
            $('#select_busca_atendimento').select2({ 
                placeholder: "Pesquise por Tutor ou Pet..." 
            });
        });

        function excluirRetorno(id) {
            Swal.fire({
                title: 'Remover Retorno?',
                text: "Deseja limpar apenas a data de retorno agendada para este atendimento?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sim, remover',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('consultas/listar_atendimentos.php', { acao: 'excluir_retorno', id_atendimento: id }, function(res) {
                        if(res.status === 'success') {
                            location.reload();
                        } else {
                            Swal.fire('Erro', 'Não foi possível remover o retorno.', 'error');
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>