<?php
/**
 * ZIIPVET - LISTAGEM DE CLIENTES
 * ARQUIVO: listar_clientes.php
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
    $id_cliente = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
        $stmt->execute(array($id_cliente));
        echo json_encode(array('status' => 'success', 'message' => 'Cliente excluído com sucesso!'));
    } catch (PDOException $e) {
        echo json_encode(array('status' => 'error', 'message' => 'Erro: existem animais ou histórico clínico vinculados.'));
    }
    exit;
}

// ==========================================================
// CONFIGURAÇÃO DA PÁGINA
// ==========================================================
$titulo_pagina = "Listagem de Clientes";
$filtro_busca = isset($_GET['busca']) ? $_GET['busca'] : '';

$pagina_atual = (isset($_GET['pagina']) && is_numeric($_GET['pagina'])) ? (int)$_GET['pagina'] : 1;
$itens_per_page = 20;
$offset = ($pagina_atual - 1) * $itens_per_page;

try {
    $sqlCount = "SELECT COUNT(*) as total FROM clientes WHERE nome LIKE :busca OR cpf_cnpj LIKE :busca OR email LIKE :busca";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute(array(':busca' => "%$filtro_busca%"));
    $resCount = $stmtCount->fetch(PDO::FETCH_ASSOC);
    $total_registros = $resCount['total'];
    $total_paginas = ceil($total_registros / $itens_per_page);

    $sql = "SELECT c.id, c.nome, c.cpf_cnpj, c.telefone, c.email,
            (SELECT GROUP_CONCAT(DISTINCT CONCAT(id, ':', nome_paciente) ORDER BY nome_paciente ASC SEPARATOR '|') FROM pacientes WHERE id_cliente = c.id) as lista_animais
            FROM clientes c
            WHERE (c.nome LIKE :busca OR c.cpf_cnpj LIKE :busca OR c.email LIKE :busca)
            ORDER BY c.nome ASC 
            LIMIT " . (int)$offset . ", " . (int)$itens_per_page;
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':busca', "%$filtro_busca%");
    $stmt->execute();
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao buscar clientes: " . $e->getMessage());
}

function gerar_link($pg) {
    global $filtro_busca;
    return "listar_clientes.php?pagina=" . $pg . "&busca=" . urlencode($filtro_busca);
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
        /* CSS ESPECÍFICO PARA LISTAGEM */
        .list-container {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }
        
        .search-box {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .search-form {
            display: flex;
            gap: 12px;
            max-width: 600px;
        }
        
        .search-form input {
            flex: 1;
            height: 48px;
            padding: 12px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            font-family: 'Exo', sans-serif;
            transition: all 0.3s ease;
        }
        
        .search-form input:focus {
            border-color: #131C71;
            outline: none;
            box-shadow: 0 0 0 4px rgba(98, 37, 153, 0.1);
        }
        
        .btn-search {
            width: 48px;
            height: 48px;
            background: #131C71;
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-search:hover {
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
            font-size: 14px;
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
        
        .client-code {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #28A745; /* COR DO QUADRADO DE NOTIFICAÇÃO */
            color: #fff;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
        }
        
        .client-name {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 4px;
        }
        
        .client-cpf {
            font-size: 13px;
            color: #6c757d;
        }
        
        .pet-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #e8f0fe;
            color: #b92426;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            margin: 2px;
            transition: all 0.2s ease;
        }
        
        .pet-tag:hover {
            background: #131c71;
            color: #fff;
            transform: translateY(-1px);
        }
        
        .pet-tag i {
            font-size: 12px;
        }
        
        .no-pets {
            color: #adb5bd;
            font-style: italic;
            font-size: 14px;
        }
        
        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .contact-item i {
            color: #6c757d;
            width: 16px;
            text-align: center;
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
            font-size: 16px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .btn-action.add {
            background: #28a745;
            color: #fff;
        }
        
        .btn-action.add:hover {
            background: #218838;
            transform: scale(1.1);
        }
        
        .btn-action.edit {
            background: #17a2b8;
            color: #fff;
        }
        
        .btn-action.edit:hover {
            background: #138496;
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
            gap: 8px;
        }
        
        .page-link {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            color: #131c71;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .page-link:hover {
            background: #131c71;
            color: #fff;
            border-color: #131c71;
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
                <i class="fas fa-users"></i>
                Listagem de Clientes
            </h1>
            
            <a href="clientes.php" class="btn-voltar">
                <i class="fas fa-plus"></i>
                Novo Cliente
            </a>
        </div>

        <!-- CONTAINER DA LISTAGEM -->
        <div class="list-container">
            
            <!-- BUSCA -->
            <div class="search-box">
                <form method="GET" class="search-form">
                    <input type="text" 
                           name="busca" 
                           value="<?= htmlspecialchars($filtro_busca) ?>" 
                           placeholder="Buscar por nome, CPF ou e-mail...">
                    <button type="submit" class="btn-search">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>

            <!-- TABELA -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th width="80">Cód</th>
                            <th>Cliente</th>
                            <th>Animais</th>
                            <th>Contatos</th>
                            <th width="150" style="text-align: center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($clientes) > 0): ?>
                            <?php foreach($clientes as $c): ?>
                            <tr>
                                <td>
                                    <div class="client-code"><?= $c['id'] ?></div>
                                </td>
                                <td>
                                    <div class="client-name"><?= htmlspecialchars($c['nome']) ?></div>
                                    <div class="client-cpf"><?= htmlspecialchars($c['cpf_cnpj']) ?></div>
                                </td>
                                <td>
                                    <?php 
                                    if(!empty($c['lista_animais'])){
                                        $animais = explode('|', $c['lista_animais']);
                                        foreach($animais as $animal_par){
                                            $dados_pet = explode(':', $animal_par);
                                            $pet_id = $dados_pet[0];
                                            $pet_nome = $dados_pet[1];
                                            echo '<a href="pacientes.php?id='.$pet_id.'" class="pet-tag" title="Editar '.$pet_nome.'">
                                                    <i class="fas fa-paw"></i> '.$pet_nome.'
                                                  </a>';
                                        }
                                    } else {
                                        echo '<span class="no-pets">Nenhum animal cadastrado</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="contact-info">
                                        <div class="contact-item">
                                            <i class="fas fa-phone-alt"></i>
                                            <span><?= htmlspecialchars($c['telefone']) ?></span>
                                        </div>
                                        <div class="contact-item">
                                            <i class="fas fa-envelope"></i>
                                            <span><?= htmlspecialchars($c['email']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="pacientes.php?id_cliente=<?= $c['id'] ?>" 
                                           class="btn-action add" 
                                           title="Adicionar Novo Pet">
                                            <i class="fas fa-plus"></i>
                                        </a>
                                        <a href="clientes.php?id=<?= $c['id'] ?>" 
                                           class="btn-action edit" 
                                           title="Editar Cliente">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="excluirCliente(<?= $c['id'] ?>, '<?= htmlspecialchars($c['nome']) ?>')" 
                                                class="btn-action delete" 
                                                title="Excluir Cliente">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="fas fa-users-slash"></i>
                                        <p>Nenhum cliente encontrado</p>
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
                    Página <?= $pagina_atual ?> de <?= $total_paginas ?>
                </div>
                <div class="page-nav">
                    <a href="<?= gerar_link(max(1, $pagina_atual-1)) ?>" class="page-link">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <a href="<?= gerar_link(min($total_paginas, $pagina_atual+1)) ?>" class="page-link">
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
        function excluirCliente(id, nome) {
            Swal.fire({
                title: 'Deseja excluir este cliente?',
                text: nome + " e todos os vínculos serão removidos.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('listar_clientes.php', { acao: 'excluir', id: id }, function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                title: 'Excluído!',
                                text: 'Cliente removido com sucesso.',
                                icon: 'success',
                                confirmButtonColor: '#131c71'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Erro!',
                                text: res.message,
                                icon: 'error',
                                confirmButtonColor: '#131c71'
                            });
                        }
                    }, 'json');
                }
            });
        }
    </script>
</body>
</html>