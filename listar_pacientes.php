<?php
/**
 * ZIIPVET - LISTAGEM DE PACIENTES
 * ARQUIVO: listar_pacientes.php
 * VERSÃO: 3.0.0 - PADRÃO MODERNO
 */
require_once 'auth.php';
require_once 'config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================================
// LÓGICA DE EXCLUSÃO (AJAX)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excluir') {
    header('Content-Type: application/json');
    ob_clean();

    try {
        $id_paciente = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        
        if (!$id_paciente) throw new Exception("ID inválido.");

        $stmt = $pdo->prepare("DELETE FROM pacientes WHERE id = ?");
        $stmt->execute([$id_paciente]);

        echo json_encode(['status' => 'success', 'message' => 'Paciente excluído com sucesso!']);
    } catch (PDOException $e) {
        if ($e->getCode() == '23000') {
            echo json_encode(['status' => 'error', 'message' => 'Não é possível excluir: Paciente possui histórico financeiro ou clínico.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Erro ao excluir: ' . $e->getMessage()]);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// CONFIGURAÇÃO DA PÁGINA
// ==========================================================
$titulo_pagina = "Listagem de Pacientes";

$filtro_nome = filter_input(INPUT_GET, 'busca_nome', FILTER_DEFAULT) ?? '';
$filtro_dono = filter_input(INPUT_GET, 'busca_dono', FILTER_DEFAULT) ?? '';
$filtro_especie = filter_input(INPUT_GET, 'especie', FILTER_DEFAULT) ?? '';
$filtro_status = filter_input(INPUT_GET, 'status', FILTER_DEFAULT) ?? '';

// --- PAGINAÇÃO ---
$pagina_atual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1;
$itens_por_pagina = 20;
$inicio = ($pagina_atual - 1) * $itens_por_pagina;

try {
    // 1. Contagem Total
    $sqlCount = "SELECT COUNT(*) as total 
                 FROM pacientes p 
                 LEFT JOIN clientes c ON p.id_cliente = c.id 
                 WHERE (p.nome_paciente LIKE :nome)
                 AND (c.nome LIKE :dono OR :dono_empty = '')
                 AND (p.especie = :especie OR :especie_empty = '')
                 AND (p.status = :status OR :status_empty = '')";
    
    $stmtCount = $pdo->prepare($sqlCount);
    $term_nome = "%$filtro_nome%";
    $term_dono = "%$filtro_dono%";
    
    $stmtCount->execute([
        ':nome' => $term_nome,
        ':dono' => $term_dono, ':dono_empty' => $filtro_dono,
        ':especie' => $filtro_especie, ':especie_empty' => $filtro_especie,
        ':status' => $filtro_status, ':status_empty' => $filtro_status
    ]);
    
    $total_registros = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    $total_paginas = ceil($total_registros / $itens_por_pagina);

    // 2. Consulta de Dados (Ordenado por Paciente)
    $sql = "SELECT p.*, c.nome as nome_dono 
            FROM pacientes p 
            LEFT JOIN clientes c ON p.id_cliente = c.id 
            WHERE (p.nome_paciente LIKE :nome)
            AND (c.nome LIKE :dono OR :dono_empty = '')
            AND (p.especie = :especie OR :especie_empty = '')
            AND (p.status = :status OR :status_empty = '')
            ORDER BY p.nome_paciente ASC, c.nome ASC 
            LIMIT :inicio, :limite";
            
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':nome', $term_nome);
    $stmt->bindValue(':dono', $term_dono);
    $stmt->bindValue(':dono_empty', $filtro_dono);
    $stmt->bindValue(':especie', $filtro_especie);
    $stmt->bindValue(':especie_empty', $filtro_especie);
    $stmt->bindValue(':status', $filtro_status);
    $stmt->bindValue(':status_empty', $filtro_status);
    $stmt->bindValue(':inicio', $inicio, PDO::PARAM_INT);
    $stmt->bindValue(':limite', $itens_por_pagina, PDO::PARAM_INT);
    
    $stmt->execute();
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao buscar pacientes: " . $e->getMessage());
}

function link_paginacao($pg) {
    global $filtro_nome, $filtro_dono, $filtro_especie, $filtro_status;
    return "?pagina=$pg&busca_nome=$filtro_nome&busca_dono=$filtro_dono&especie=$filtro_especie&status=$filtro_status";
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    
    <!-- CSS CENTRALIZADO -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/menu.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/formularios.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        /* CSS ESPECÍFICO PARA LISTAGEM DE PACIENTES */
        .list-container {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
        }
        
        .filters-box {
            background: #f8f9fa;
            padding: 25px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) 60px;
            gap: 15px;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-group label {
            font-size: 14px;
            font-weight: 600;
            color: #495057;
            font-family: 'Exo', sans-serif;
        }
        
        .filter-group input,
        .filter-group select {
            height: 48px;
            padding: 12px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Exo', sans-serif;
            transition: all 0.3s ease;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            border-color: #131c71;
            outline: none;
            box-shadow: 0 0 0 4px rgba(98, 37, 153, 0.1);
        }
        
        .btn-filter {
            width: 48px;
            height: 48px;
            background: #131c71;
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-filter:hover {
            background: #4a1d75;
            transform: translateY(-2px);
        }
        
        .table-wrapper {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Exo', sans-serif;
        }
        
        thead th {
            background: #f8f9fa;
            padding: 18px 16px;
            text-align: left;
            font-size: 13px;
            font-weight: 700;
            color: #495057;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        tbody td {
            padding: 16px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 15px;
            color: #2c3e50;
            vertical-align: middle;
        }
        
        tbody tr {
            transition: background 0.2s ease;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .patient-code {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #28A745; /* Cor do quadrado agora com a cor verde */
            color: #fff;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
        }
        
        .patient-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .patient-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .patient-avatar-placeholder {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: #b92426;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 18px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .patient-name {
            font-size: 16px;
            font-weight: 700;
            color: #b92426; /*NOME DO PACIENTE */
        }
        
        .owner-name {
            font-size: 15px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .owner-empty {
            color: #adb5bd;
            font-style: italic;
            font-size: 14px;
        }
        
        .species-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .species-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .breed-name {
            font-size: 13px;
            color: #6c757d;
        }
        
        .size-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .size-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .weight-info {
            font-size: 13px;
            color: #6c757d;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .status-badge.active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-badge.deceased {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        
        .btn-action {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .btn-action.edit {
            background: #17a2b8;
            color: #fff;
        }
        
        .btn-action.edit:hover {
            background: #138496;
            transform: scale(1.1);
        }
        
        .btn-action.medical {
            background: #28a745;
            color: #fff;
        }
        
        .btn-action.medical:hover {
            background: #218838;
            transform: scale(1.1);
        }
        
        .btn-action.delete {
            background: #b92426;
            color: #fff;
        }
        
        .btn-action.delete:hover {
            background: #c82333;
            transform: scale(1.1);
        }
        
        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #f8f9fa;
            border-top: 2px solid #e0e0e0;
        }
        
        .page-info {
            font-size: 14px;
            color: #6c757d;
            font-weight: 600;
            font-family: 'Exo', sans-serif;
        }
        
        .page-nav {
            display: flex;
            gap: 6px;
        }
        
        .page-link {
            min-width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 12px;
            background: #fff;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            color: #b92426;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s ease;
            font-family: 'Exo', sans-serif;
        }
        
        .page-link:hover {
            background: #622599;
            color: #fff;
            border-color: #622599;
        }
        
        .page-link.active {
            background: #622599;
            color: #fff;
            border-color: #622599;
        }
        
        .page-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #adb5bd;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .empty-state p {
            font-size: 18px;
            font-weight: 600;
            font-family: 'Exo', sans-serif;
        }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include 'menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include 'menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <!-- HEADER: Título e Botão -->
        <div class="form-header">
            <h1 class="form-title">
                <i class="fas fa-paw"></i>
                Listagem de Pacientes
            </h1>
            
            <a href="pacientes.php" class="btn-voltar">
                <i class="fas fa-plus"></i>
                Novo Paciente
            </a>
        </div>

        <!-- CONTAINER DA LISTAGEM -->
        <div class="list-container">
            
            <!-- FILTROS -->
            <div class="filters-box">
                <form method="GET" class="filters-grid">
                    <div class="filter-group">
                        <label>
                            <i class="fas fa-paw"></i>
                            Nome do Pet
                        </label>
                        <input type="text" 
                               name="busca_nome" 
                               value="<?= htmlspecialchars($filtro_nome) ?>" 
                               placeholder="Ex: Bob">
                    </div>
                    
                    <div class="filter-group">
                        <label>
                            <i class="fas fa-user"></i>
                            Cliente / Tutor
                        </label>
                        <input type="text" 
                               name="busca_dono" 
                               value="<?= htmlspecialchars($filtro_dono) ?>" 
                               placeholder="Nome do dono">
                    </div>
                    
                    <div class="filter-group">
                        <label>
                            <i class="fas fa-dog"></i>
                            Espécie
                        </label>
                        <select name="especie">
                            <option value="">Todas</option>
                            <option value="Canina" <?= $filtro_especie == 'Canina' ? 'selected' : '' ?>>Canina</option>
                            <option value="Felina" <?= $filtro_especie == 'Felina' ? 'selected' : '' ?>>Felina</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>
                            <i class="fas fa-toggle-on"></i>
                            Status
                        </label>
                        <select name="status">
                            <option value="">Todos</option>
                            <option value="ATIVO" <?= $filtro_status == 'ATIVO' ? 'selected' : '' ?>>Ativo</option>
                            <option value="INATIVO" <?= $filtro_status == 'INATIVO' ? 'selected' : '' ?>>Inativo</option>
                            <option value="OBITO" <?= $filtro_status == 'OBITO' ? 'selected' : '' ?>>Óbito</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>

            <!-- TABELA -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th width="70">Cód</th>
                            <th>Paciente</th>
                            <th>Cliente / Tutor</th>
                            <th>Espécie / Raça</th>
                            <th>Porte / Peso</th>
                            <th width="120">Status</th>
                            <th width="140" style="text-align: center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($pacientes) > 0): ?>
                            <?php foreach($pacientes as $p): ?>
                            <tr>
                                <td>
                                    <div class="patient-code"><?= $p['id'] ?></div>
                                </td>
                                
                                <td>
                                    <div class="patient-info">
                                        <?php if(!empty($p['foto'])): ?>
                                            <img src="<?= htmlspecialchars($p['foto']) ?>" 
                                                 class="patient-avatar" 
                                                 alt="<?= htmlspecialchars($p['nome_paciente']) ?>">
                                        <?php else: ?>
                                            <div class="patient-avatar-placeholder">
                                                <i class="fas fa-paw"></i>
                                            </div>
                                        <?php endif; ?>
                                        <span class="patient-name">
                                            <?= htmlspecialchars($p['nome_paciente']) ?>
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <?php if($p['nome_dono']): ?>
                                        <span class="owner-name"><?= htmlspecialchars($p['nome_dono']) ?></span>
                                    <?php else: ?>
                                        <span class="owner-empty">Sem vínculo</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="species-info">
                                        <span class="species-name"><?= htmlspecialchars($p['especie']) ?></span>
                                        <span class="breed-name"><?= htmlspecialchars($p['raca']) ?></span>
                                    </div>
                                </td>

                                <td>
                                    <div class="size-info">
                                        <span class="size-name"><?= htmlspecialchars($p['porte']) ?></span>
                                        <span class="weight-info"><?= htmlspecialchars($p['peso']) ?> kg</span>
                                    </div>
                                </td>

                                <td>
                                    <?php if($p['status'] == 'ATIVO'): ?>
                                        <span class="status-badge active">
                                            <i class="fas fa-check-circle"></i> Ativo
                                        </span>
                                    <?php elseif($p['status'] == 'INATIVO'): ?>
                                        <span class="status-badge inactive">
                                            <i class="fas fa-ban"></i> Inativo
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge deceased">
                                            <i class="fas fa-cross"></i> Óbito
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="action-buttons">
                                        <a href="pacientes.php?id=<?= $p['id'] ?>" 
                                           class="btn-action edit" 
                                           title="Editar Paciente">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="visualizar_prontuario.php?id=<?= $p['id'] ?>" 
                                           class="btn-action medical" 
                                           title="Prontuário">
                                            <i class="fas fa-notes-medical"></i>
                                        </a>
                                        <button onclick="excluirPaciente(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nome_paciente']) ?>')" 
                                                class="btn-action delete" 
                                                title="Excluir Paciente">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-search"></i>
                                        <p>Nenhum paciente encontrado com esses filtros</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINAÇÃO -->
            <?php if($total_paginas > 1): ?>
            <div class="pagination-wrapper">
                <div class="page-info">
                    Mostrando <?= count($pacientes) ?> de <?= $total_registros ?> pacientes
                </div>
                <div class="page-nav">
                    <a href="<?= ($pagina_atual > 1) ? link_paginacao($pagina_atual - 1) : '#' ?>" 
                       class="page-link <?= ($pagina_atual <= 1) ? 'disabled' : '' ?>">
                       <i class="fas fa-chevron-left"></i>
                    </a>

                    <?php 
                    $inicio_pag = max(1, $pagina_atual - 2);
                    $fim_pag = min($total_paginas, $pagina_atual + 2);
                    
                    if($inicio_pag > 1): ?>
                        <span style="padding: 0 8px; color: #adb5bd;">...</span>
                    <?php endif;
                    
                    for ($i = $inicio_pag; $i <= $fim_pag; $i++): ?>
                        <a href="<?= link_paginacao($i) ?>" 
                           class="page-link <?= ($pagina_atual == $i) ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor;
                    
                    if($fim_pag < $total_paginas): ?>
                        <span style="padding: 0 8px; color: #adb5bd;">...</span>
                    <?php endif; ?>

                    <a href="<?= ($pagina_atual < $total_paginas) ? link_paginacao($pagina_atual + 1) : '#' ?>" 
                       class="page-link <?= ($pagina_atual >= $total_paginas) ? 'disabled' : '' ?>">
                       <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function excluirPaciente(id, nome) {
            Swal.fire({
                title: 'Tem certeza?',
                text: "Deseja excluir o paciente " + nome + "?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#b92426',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'listar_pacientes.php',
                        type: 'POST',
                        data: { acao: 'excluir', id: id },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({
                                    title: 'Excluído!',
                                    text: response.message,
                                    icon: 'success',
                                    confirmButtonColor: '#622599'
                                }).then(() => location.reload());
                            } else {
                                Swal.fire({
                                    title: 'Erro!',
                                    text: response.message,
                                    icon: 'error',
                                    confirmButtonColor: '#622599'
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                title: 'Erro!',
                                text: 'Falha na comunicação com o servidor.',
                                icon: 'error',
                                confirmButtonColor: '#622599'
                            });
                        }
                    });
                }
            });
        }
    </script>

</body>
</html>