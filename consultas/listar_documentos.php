<?php
/**
 * ZIIPVET - LISTAGEM DE DOCUMENTOS EMITIDOS
 * ARQUIVO: listar_documentos.php
 * VERSÃO: 2.0.1 - PADRÃO V16.2 (AJUSTE DE FONTE)
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$titulo_pagina = "Histórico de Documentos";
$busca = $_GET['busca'] ?? '';

try {
    // Consulta baseada na tabela documentos_emitidos vinculada a pacientes e clientes
    $sql = "SELECT d.*, p.nome_paciente, c.nome as nome_cliente 
            FROM documentos_emitidos d
            INNER JOIN pacientes p ON d.id_paciente = p.id
            INNER JOIN clientes c ON p.id_cliente = c.id
            WHERE (p.nome_paciente LIKE :busca 
               OR c.nome LIKE :busca 
               OR d.tipo_documento LIKE :busca)
            ORDER BY d.data_emissao DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':busca' => "%$busca%"]);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao carregar documentos: " . $e->getMessage());
    $documentos = [];
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
            --fundo: #ecf0f5; --primaria: #1c329f; --sucesso: #28a745; 
            --borda: #d2d6de; --header-height: 80px; --sidebar-collapsed: 75px; 
            --sidebar-expanded: 260px;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body { 
            font-family: 'Source Sans Pro', sans-serif; 
            background-color: var(--fundo); 
            font-size: 16px; 
            color: #333;
            overflow-x: hidden;
        }

        /* Layout V16.2 */
        aside.sidebar-container { 
            position: fixed; left: 0; top: 0; height: 100vh; 
            width: var(--sidebar-collapsed); z-index: 1000; 
            background: #fff; transition: 0.4s; 
            box-shadow: 2px 0 5px rgba(0,0,0,0.05);
        }
        aside.sidebar-container:hover { width: var(--sidebar-expanded); }
        
        header.top-header { 
            position: fixed; top: 0; left: var(--sidebar-collapsed); right: 0; 
            height: var(--header-height); z-index: 900; 
            transition: left 0.4s; 
        }
        aside.sidebar-container:hover ~ header.top-header { left: var(--sidebar-expanded); }
        
        main.main-content { 
            margin-left: var(--sidebar-collapsed); 
            padding: calc(var(--header-height) + 20px) 25px 30px; 
            transition: margin-left 0.4s;
            width: calc(100% - var(--sidebar-collapsed));
        }
        aside.sidebar-container:hover ~ main.main-content { 
            margin-left: var(--sidebar-expanded);
            width: calc(100% - var(--sidebar-expanded));
        }

        .faixa-superior { width: 100% !important; margin: 0 !important; border-radius: 0 0 0 30px !important; }

        /* Estilo da Listagem */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        
        .btn-novo { 
            background: var(--primaria); color: #fff; padding: 12px 20px; 
            border-radius: 6px; text-decoration: none; font-weight: 700; 
            font-size: 16px; display: flex; align-items: center; gap: 8px; 
            text-transform: uppercase; transition: 0.3s;
            font-family: inherit; /* Garante a mesma fonte */
        }
        .btn-novo:hover { background: #15277a; box-shadow: 0 4px 12px rgba(28, 50, 159, 0.2); }

        .search-container {
            background: #fff; padding: 15px; border-radius: 10px; 
            margin-bottom: 20px; display: flex; gap: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        /* AJUSTE AQUI: Adicionado font-family inherit para unificar a fonte */
        .search-container input {
            flex: 1; padding: 10px 15px; border: 1px solid var(--borda);
            border-radius: 4px; outline: none; font-size: 16px;
            font-family: 'Source Sans Pro', sans-serif;
        }
        /* AJUSTE AQUI: Adicionado font-family inherit para o botão */
        .btn-search { 
            background: #555; color: #fff; border: none; padding: 0 20px; 
            border-radius: 4px; cursor: pointer; font-weight: 600; 
            font-family: 'Source Sans Pro', sans-serif;
            transition: 0.2s;
        }
        .btn-search:hover { background: #333; }

        .card-tabela { 
            background: #fff; border-radius: 10px; overflow: hidden; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-top: 3px solid var(--primaria);
        }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fa; text-align: left; padding: 15px; font-size: 16px; text-transform: uppercase; color: #666; border-bottom: 2px solid #eee; }
        td { padding: 15px; border-bottom: 1px solid #f1f1f1; vertical-align: middle; }

        .paciente-info strong { color: var(--primaria); display: block; font-size: 16px; }
        .paciente-info span { font-size: 16px; color: #888; }
        
        .tag-tipo {
            background: #eef0f7; color: #1c329f; padding: 4px 8px;
            border-radius: 4px; font-size: 16px; font-weight: 700; text-transform: uppercase;
        }

        .btn-acao { 
            width: 35px; height: 35px; display: inline-flex; align-items: center; 
            justify-content: center; border-radius: 4px; color: #666; 
            background: #f4f4f4; text-decoration: none; transition: 0.2s;
        }
        .btn-acao:hover { background: var(--primaria); color: #fff; }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include '../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../menu/faixa.php'; ?></header>

    <main class="main-content">
        <div class="page-header">
            <h2 style="font-weight: 700;"><i class="fas fa-file-alt" style="color: var(--primaria);"></i> Histórico de Documentos</h2>
            <a href="consultas/modelo_documentos.php" class="btn-novo">
                <i class="fas fa-plus"></i> Novo Documento
            </a>
        </div>

        <div class="search-container">
            <form method="GET" style="display: flex; width: 100%; gap: 10px;">
                <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" placeholder="Pesquisar por Paciente, Tutor ou Tipo de Documento...">
                <button type="submit" class="btn-search">BUSCAR</button>
            </form>
        </div>

        <div class="card-tabela">
            <table>
                <thead>
                    <tr>
                        <th style="width: 150px;">Data/Hora</th>
                        <th>Paciente / Tutor</th>
                        <th>Tipo</th>
                        <th>Emitido por</th>
                        <th style="text-align: center; width: 100px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($documentos) > 0): ?>
                        <?php foreach ($documentos as $doc): ?>
                        <tr>
                            <td><strong><?= date('d/m/Y H:i', strtotime($doc['data_emissao'])) ?></strong></td>
                            <td class="paciente-info">
                                <strong><?= htmlspecialchars($doc['nome_paciente']) ?></strong>
                                <span>Tutor: <?= htmlspecialchars($doc['nome_cliente']) ?></span>
                            </td>
                            <td><span class="tag-tipo"><?= str_replace('_', ' ', $doc['tipo_documento']) ?></span></td>
                            <td><small><i class="fas fa-user-md"></i> <?= htmlspecialchars($doc['usuario_emissor']) ?></small></td>
                            <td style="text-align: center;">
                                <a href="consultas/imprimir_documento.php?id=<?= $doc['id'] ?>" target="_blank" class="btn-acao" title="Imprimir">
                                    <i class="fas fa-print"></i>
                                </a>
                                <a href="consultas/modelo_documentos.php?editar=<?= $doc['id'] ?>" class="btn-acao" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 50px; color: #999;">Nenhum documento registrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

</body>
</html>