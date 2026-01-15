<?php
/**
 * =========================================================================================
 * ZIIPVET - SISTEMA DE GESTÃO VETERINÁRIA PROFISSIONAL
 * MÓDULO: LISTAGEM DE PATOLOGIAS E PROTOCOLOS
 * VERSÃO: V16.2 - LAYOUT PADRONIZADO E TEXTO 17PX
 * =========================================================================================
 */

require_once '../auth.php';
require_once '../config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$titulo_pagina = "Lista de Patologias";

// Filtros de Pesquisa
$busca = $_GET['busca'] ?? '';

try {
    // Consulta para listar patologias com nomes do pet e do dono
    $sql = "SELECT p.*, pac.nome_paciente, c.nome as nome_cliente 
            FROM patologias p
            INNER JOIN pacientes pac ON p.id_paciente = pac.id
            INNER JOIN clientes c ON pac.id_cliente = c.id
            WHERE pac.nome_paciente LIKE :busca 
               OR c.nome LIKE :busca 
               OR p.nome_doenca LIKE :busca
            ORDER BY p.data_registro DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':busca' => "%$busca%"]);
    $lista = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar patologias: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    
    <base href="https://www.lepetboutique.com.br/app/">

    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { 
            --fundo: #ecf0f5; 
            --texto-dark: #333; 
            --primaria: #1c329f; 
            --roxo-header: #6f42c1; 
            --sucesso: #28a745; 
            --borda: #d2d6de;
            --sidebar-collapsed: 75px; 
            --sidebar-expanded: 260px; 
            --header-height: 80px;
            --transition-speed: 0.4s;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body { 
            font-family: 'Source Sans Pro', sans-serif; 
            background-color: var(--fundo); 
            color: var(--texto-dark); 
            min-height: 100vh;
            overflow-x: hidden;
            font-size: 17px; /* Tamanho padronizado solicitado */
        }

        /* SIDEBAR E TOP-HEADER COM POSICIONAMENTO FIXO (PADRÃO SISTEMA) */
        aside.sidebar-container { 
            position: fixed; left: 0; top: 0; height: 100vh; 
            width: var(--sidebar-collapsed); z-index: 1000; 
            transition: width var(--transition-speed); 
            background: #fff;
        }
        aside.sidebar-container:hover { width: var(--sidebar-expanded); }

        header.top-header { 
            position: fixed; top: 0; left: var(--sidebar-collapsed); right: 0; 
            height: var(--header-height); z-index: 900; 
            transition: left var(--transition-speed); margin: 0 !important; 
        }
        aside.sidebar-container:hover ~ header.top-header { left: var(--sidebar-expanded); }

        main.main-content { 
            margin-left: var(--sidebar-collapsed); 
            padding: calc(var(--header-height) + 30px) 25px 30px; 
            transition: margin-left var(--transition-speed); 
        }
        aside.sidebar-container:hover ~ main.main-content { margin-left: var(--sidebar-expanded); }

        .faixa-superior { width: 100% !important; margin: 0 !important; border-radius: 0 0 0 30px !important; }

        /* Componentes de Cabeçalho */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-header h2 { font-size: 30px; color: #444; font-weight: 600; }

        .btn-novo { 
            background: var(--roxo-header); 
            color: #fff; 
            padding: 12px 24px; 
            border-radius: 8px; 
            text-decoration: none; 
            font-weight: 700; 
            font-size: 15px; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            text-transform: uppercase;
            transition: 0.2s;
        }
        .btn-novo:hover { background: #5a32a3; transform: translateY(-2px); }

        /* Barra de Busca */
        .search-container {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }

        .search-container input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid var(--borda);
            border-radius: 8px;
            font-size: 17px;
            outline: none;
            background: #fafafa;
        }

        .btn-search { 
            background: #3258db; 
            color: #fff; 
            border: none; 
            padding: 0 25px; 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: 700;
        }

        /* Tabela Estilizada */
        .card-tabela { 
            background: #fff; 
            border-radius: 12px; 
            overflow: hidden; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.03); 
        }
        
        table { width: 100%; border-collapse: collapse; }
        thead { background: var(--roxo-header); }
        th { 
            text-align: left; 
            padding: 18px 15px; 
            color: #fff; 
            font-size: 13px; 
            text-transform: uppercase; 
            font-weight: 700; 
            letter-spacing: 0.5px;
        }
        
        td { 
            padding: 18px 15px; 
            font-size: 17px; 
            color: #444; 
            border-bottom: 1px solid #f1f1f1; 
            vertical-align: middle;
        }

        .tag-doenca {
            background: #f0f2ff;
            color: var(--primaria);
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 14px;
        }

        .btn-acao { 
            color: #777; 
            margin: 0 8px; 
            transition: 0.2s; 
            font-size: 20px; 
        }
        .btn-acao:hover { color: #3258db; }

        .info-paciente strong { color: var(--primaria); display: block; font-size: 18px; }
        .info-paciente span { color: #888; font-size: 14px; }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include '../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../menu/faixa.php'; ?></header>

    <main class="main-content">
        <div class="page-header">
            <h2>Histórico de Patologias</h2>
            <a href="consultas/patologia.php" class="btn-novo">
                <i class="fas fa-plus"></i> NOVO REGISTRO
            </a>
        </div>

        <div class="search-container">
            <form method="GET" style="display: flex; width: 100%; gap: 10px;">
                <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" placeholder="Pesquisar por animal, dono ou doença...">
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> PESQUISAR
                </button>
            </form>
        </div>

        <div class="card-tabela">
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Paciente / Tutor</th>
                        <th>Patologia Detectada</th>
                        <th>Responsável Técnico</th>
                        <th style="text-align: center;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($lista) > 0): ?>
                        <?php foreach ($lista as $item): ?>
                            <tr>
                                <td style="width: 150px; font-weight: 600;"><?= date('d/m/Y H:i', strtotime($item['data_registro'])) ?></td>
                                <td class="info-paciente">
                                    <strong><?= htmlspecialchars($item['nome_paciente']) ?></strong>
                                    <span>Tutor: <?= htmlspecialchars($item['nome_cliente']) ?></span>
                                </td>
                                <td>
                                    <span class="tag-doenca"><?= htmlspecialchars($item['nome_doenca']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($item['usuario_responsavel'] ?? 'N/A') ?></td>
                                <td style="text-align: center;">
                                    <a href="consultas/patologia.php?id=<?= $item['id'] ?>" class="btn-acao" title="Ver Detalhes/Editar">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="consultas/imprimir_patologia.php?id=<?= $item['id'] ?>" target="_blank" class="btn-acao" title="Imprimir Protocolo">
                                        <i class="fas fa-print"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 60px; color: #999;">
                                Nenhuma patologia registrada no sistema.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

</body>
</html>