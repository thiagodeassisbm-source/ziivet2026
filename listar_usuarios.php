<?php
require_once 'config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['usuario_id'] ?? 1;

// Lógica de Busca
$busca = $_GET['busca'] ?? '';

try {
    $sql = "SELECT u.*, c.nome_cargo, g.nome_grupo 
            FROM usuarios u
            LEFT JOIN cargos c ON u.id_cargo = c.id
            LEFT JOIN comissoes_grupos g ON u.id_grupo_comissao = g.id
            WHERE u.id_admin = :id_admin 
            AND (u.nome LIKE :busca OR u.email LIKE :busca)
            ORDER BY u.nome ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_admin' => $id_admin,
        ':busca' => "%$busca%"
    ]);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_usuarios = count($usuarios);
} catch (PDOException $e) {
    die("Erro ao buscar usuários: " . $e->getMessage());
}

$titulo_pagina = "Listagem de Usuários";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primaria: #421b72; 
            --azul-claro: #3258db; 
            --sucesso: #23d297; 
            --perigo: #e33e3e;
            --fundo: #f4f7f6; 
            --borda: #e0e0e0;
            --radius-padrao: 12px;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            display: grid;
            grid-template-columns: 260px 1fr;
            grid-template-rows: 65px 1fr;
            grid-template-areas: "sidebar header" "sidebar main";
            height: 100vh;
            background: var(--fundo);
            font-family: 'Inter', sans-serif;
            overflow: hidden;
        }

        aside.sidebar-main { grid-area: sidebar; background: #fff; border-right: 1px solid #eee; z-index: 100; }
        header.top-navbar { grid-area: header; background: #fff; border-bottom: 1px solid #eee; height: 65px; }
        main.main-scroller { grid-area: main; padding: 25px; overflow-y: auto; color: #000; }

        /* Barra Superior (Search e Novo) conforme Anexo */
        .list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 20px;
        }

        .search-wrapper {
            position: relative;
            flex: 1;
            max-width: 500px;
        }

        .search-wrapper input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #3258db;
            border-radius: 8px;
            font-size: 16px;
            outline: none;
            color: #000;
        }

        .search-wrapper i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #3258db;
            font-size: 20px;
        }

        .user-count {
            font-size: 14px;
            color: #444;
            font-weight: 500;
        }

        .btn-novo {
            background: var(--sucesso);
            color: #fff;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-transform: uppercase;
        }

        /* Tabela conforme Anexo */
        .card-tabela {
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            border: 1px solid #eee;
        }

        table { width: 100%; border-collapse: collapse; }
        
        thead { background: #e0e0e0; }
        th { 
            text-align: left; 
            padding: 15px; 
            font-size: 14px; 
            color: #000; 
            font-weight: 700;
            white-space: nowrap;
        }

        td { 
            padding: 15px; 
            font-size: 16px; 
            color: #000; 
            border-bottom: 1px solid #f1f1f1; 
            vertical-align: middle;
        }

        /* Indicadores de Status (Círculos Verdes do Anexo) */
        .status-indicator {
            width: 18px;
            height: 18px;
            background-color: var(--sucesso);
            border-radius: 50%;
            display: inline-block;
        }
        .status-off { background-color: #ccc; }

        .btn-acao {
            width: 38px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            color: #fff;
            text-decoration: none;
            margin-left: 5px;
            font-size: 14px;
        }
        .btn-edit { background-color: #5c7cdb; }
        .btn-del { background-color: #5c7cdb; } /* Cor azulada conforme anexo */

        .help-icon {
            color: #aaa;
            font-size: 14px;
            margin-left: 5px;
            cursor: help;
        }

        /* Paginação conforme Anexo */
        .table-footer {
            padding: 15px;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 20px;
            font-size: 14px;
            color: #666;
            background: #fff;
        }
    </style>
</head>
<body>

    <aside class="sidebar-main"><?php include 'menu/menulateral.php'; ?></aside>
    <header class="top-navbar"><?php include 'menu/faixa.php'; ?></header>

    <main class="main-scroller">
        <div class="list-header">
            <div class="search-wrapper">
                <form method="GET">
                    <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" placeholder="Pesquisar usuários">
                    <i class="fas fa-search"></i>
                </form>
            </div>
            
            <div class="user-count">
                Usuários com acesso ao sistema <b><?= $total_usuarios ?> de 100</b>
            </div>

            <a href="usuarios.php" class="btn-novo">
                <i class="fas fa-plus"></i> NOVO
            </a>
        </div>

        <div class="card-tabela">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Apelido</th>
                        <th>Cargo</th>
                        <th>Grupo de Comissões de usuários</th>
                        <th>Acesso ao sistema <i class="fas fa-question-circle help-icon"></i></th>
                        <th>Aceitou a clínica <i class="fas fa-question-circle help-icon"></i></th>
                        <th>Ativo <i class="fas fa-question-circle help-icon"></i></th>
                        <th style="text-align: center;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($total_usuarios > 0): ?>
                        <?php foreach($usuarios as $u): ?>
                        <tr>
                            <td style="font-weight: 500;">
                                <?= htmlspecialchars($u['nome']) ?> 
                                <i class="fas fa-users" style="color: #5c7cdb; margin-left: 5px; font-size: 14px;"></i>
                            </td>
                            <td><?= htmlspecialchars($u['apelido'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($u['nome_cargo'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($u['nome_grupo'] ?? 'Grupo padrão') ?></td>
                            <td style="text-align: center;">
                                <span class="status-indicator <?= ($u['acesso_sistema'] ?? 1) ? '' : 'status-off' ?>"></span>
                            </td>
                            <td style="text-align: center;">
                                <span class="status-indicator"></span> </td>
                            <td style="text-align: center;">
                                <span class="status-indicator <?= ($u['ativo'] ?? 1) ? '' : 'status-off' ?>"></span>
                            </td>
                            <td style="text-align: center; min-width: 100px;">
                                <a href="usuarios.php?id=<?= $u['id'] ?>" class="btn-acao btn-edit" title="Editar">
                                    <i class="fas fa-pencil-alt"></i>
                                </a>
                                <a href="#" class="btn-acao btn-del" onclick="if(confirm('Excluir este usuário?')) window.location.href='usuarios.php?excluir=<?= $u['id'] ?>'" title="Excluir">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 50px; color: #999;">Nenhum usuário encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="table-footer">
                <span>Linhas por página: 10 <i class="fas fa-caret-down"></i></span>
                <span>1-<?= $total_usuarios ?> de <?= $total_usuarios ?></span>
                <div style="display: flex; gap: 20px; color: #ccc;">
                    <i class="fas fa-chevron-left"></i>
                    <i class="fas fa-chevron-right"></i>
                </div>
            </div>
        </div>
    </main>

</body>
</html>