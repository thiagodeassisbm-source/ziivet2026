<?php
/**
 * =========================================================================================
 * ZIIPVET - LISTAGEM DE EXAMES LABORATORIAIS
 * VERSÃO: 1.3.0 - PESQUISA POR PACIENTE E FILTRO POR PERÍODO (DATA)
 * LAYOUT PADRONIZADO V16.2 - MAIS DE 460 LINHAS
 * =========================================================================================
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$titulo_pagina = "Lista de Exames Laboratoriais";

// 1. CAPTURA DE FILTROS (PACIENTE E PERÍODO)
$id_paciente_busca = $_GET['id_paciente'] ?? '';
$data_inicio       = $_GET['data_inicio'] ?? '';
$data_fim          = $_GET['data_fim'] ?? '';

try {
    // 2. BUSCA DE TODOS OS PACIENTES PARA O SELECT2 (FORMATO: CLIENTE - ANIMAL)
    $sql_opcoes = "SELECT p.id as id_paciente, p.nome_paciente, c.nome as nome_cliente 
                   FROM pacientes p 
                   INNER JOIN clientes c ON p.id_cliente = c.id 
                   ORDER BY c.nome ASC";
    $lista_opcoes = $pdo->query($sql_opcoes)->fetchAll(PDO::FETCH_ASSOC);

    // 3. CONSTRUÇÃO DA CONSULTA PRINCIPAL COM FILTROS DINÂMICOS
    // Filtramos por paciente e por intervalo de datas se fornecidos
    $sql = "SELECT e.*, p.nome_paciente, c.nome as nome_cliente 
            FROM exames e
            INNER JOIN pacientes p ON e.id_paciente = p.id
            INNER JOIN clientes c ON p.id_cliente = c.id
            WHERE (:id_pac = '' OR e.id_paciente = :id_pac)
            AND (:d_inicio = '' OR DATE(e.data_registro) >= :d_inicio)
            AND (:d_fim = '' OR DATE(e.data_registro) <= :d_fim)
            ORDER BY e.data_registro DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_pac'   => $id_paciente_busca,
        ':d_inicio' => $data_inicio,
        ':d_fim'    => $data_fim
    ]);
    $exames = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erro de base de dados em listar_exames.php: " . $e->getMessage());
    die("Erro ao carregar exames. Por favor, tente novamente mais tarde.");
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    
    <base href="https://www.lepetboutique.com.br/app/">

    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
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
            font-family: 'Source Sans Pro', sans-serif; 
            background-color: var(--fundo); 
            min-height: 100vh;
            font-size: 17px; 
            overflow-x: hidden;
            color: #333;
        }

        /* Estrutura de Layout com Sidebar Hover (Correção da Faixa Superior) */
        aside.sidebar-container { position: fixed; left: 0; top: 0; height: 100vh; width: var(--sidebar-collapsed); z-index: 1000; transition: width var(--transition); background: #fff; box-shadow: 2px 0 5px rgba(0,0,0,0.05); }
        aside.sidebar-container:hover { width: var(--sidebar-expanded); }
        
        header.top-header { position: fixed; top: 0; left: var(--sidebar-collapsed); right: 0; height: var(--header-height); z-index: 900; transition: left var(--transition); margin: 0 !important; }
        aside.sidebar-container:hover ~ header.top-header { left: var(--sidebar-expanded); }
        
        main.main-content { margin-left: var(--sidebar-collapsed); padding: calc(var(--header-height) + 30px) 25px 30px; transition: margin-left var(--transition); }
        aside.sidebar-container:hover ~ main.main-content { margin-left: var(--sidebar-expanded); }
        
        /* Garantia de largura total para a faixa superior incluída */
        .faixa-superior { width: 100% !important; margin: 0 !important; border-radius: 0 0 0 30px !important; }

        /* Estilos do Cabeçalho da Página */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-header h2 { font-size: 28px; font-weight: 600; color: #444; display: flex; align-items: center; gap: 12px; }

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

        /* Container de Filtros Avançados */
        .search-container {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 15px;
            align-items: flex-end;
        }

        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }

        .form-control-vet {
            height: 48px;
            border: 1px solid var(--borda);
            border-radius: 8px;
            padding: 0 15px;
            font-size: 16px;
            outline: none;
            background: #fafafa;
            transition: 0.2s;
        }
        .form-control-vet:focus { border-color: var(--primaria); background: #fff; }

        /* Customização Select2 */
        .select2-container--default .select2-selection--single {
            height: 48px;
            border: 1px solid var(--borda);
            border-radius: 8px;
            display: flex;
            align-items: center;
            font-size: 16px;
            background: #fafafa;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 46px; }
        
        .btn-search { 
            background: var(--azul-claro); 
            color: #fff; 
            border: none; 
            padding: 0 30px; 
            height: 48px;
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: 700; 
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-search:hover { background: #2546b8; }
        
        .btn-clear {
            background: #888;
            color: #fff;
            text-decoration: none;
            padding: 0 15px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        /* Estilização da Tabela de Resultados */
        .card-tabela { 
            background: #fff; 
            border-radius: 12px; 
            overflow: hidden; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.03); 
        }
        table { width: 100%; border-collapse: collapse; }
        thead { background: var(--roxo-header); }
        th { text-align: left; padding: 18px 15px; color: #fff; font-size: 13px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
        td { padding: 18px 15px; font-size: 17px; color: #444; border-bottom: 1px solid #f1f1f1; vertical-align: middle; }

        .tag-exame { background: #eef2ff; color: var(--primaria); padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 13px; text-transform: uppercase; }
        .status-badge { font-size: 11px; padding: 5px 10px; border-radius: 20px; font-weight: 800; text-transform: uppercase; }
        .status-signed { background: #d4edda; color: #155724; }

        .btn-acao { color: #777; margin: 0 8px; font-size: 20px; transition: 0.2s; text-decoration: none; }
        .btn-acao:hover { color: var(--azul-claro); }

        .paciente-info strong { color: var(--primaria); display: block; font-size: 18px; }
        .paciente-info small { color: #888; font-weight: 600; font-size: 14px; }
    </style>
</head>
<body>

    <aside class="sidebar-container">
        <?php include '../menu/menulateral.php'; ?>
    </aside>

    <header class="top-header">
        <?php include '../menu/faixa.php'; ?>
    </header>

    <main class="main-content">
        <div class="page-header">
            <h2><i class="fas fa-microscope" style="color: var(--roxo-header);"></i> Histórico de Exames</h2>
            <a href="consultas/exames.php" class="btn-novo">
                <i class="fas fa-plus"></i> NOVO REGISTRO
            </a>
        </div>

        <div class="search-container">
            <form method="GET" class="filter-grid">
                
                <div class="filter-group">
                    <label>Paciente / Tutor</label>
                    <select name="id_paciente" id="busca_paciente" class="form-control-vet">
                        <option value="">Todos os pacientes...</option>
                        <?php foreach($lista_opcoes as $opt): ?>
                            <option value="<?= $opt['id_paciente'] ?>" <?= $id_paciente_busca == $opt['id_paciente'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opt['nome_cliente']) ?> - <?= htmlspecialchars($opt['nome_paciente']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Desde</label>
                    <input type="date" name="data_inicio" class="form-control-vet" value="<?= htmlspecialchars($data_inicio) ?>">
                </div>

                <div class="filter-group">
                    <label>Até</label>
                    <input type="date" name="data_fim" class="form-control-vet" value="<?= htmlspecialchars($data_fim) ?>">
                </div>

                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn-search"><i class="fas fa-filter"></i> FILTRAR</button>
                    <?php if($id_paciente_busca || $data_inicio || $data_fim): ?>
                        <a href="consultas/listar_exames.php" class="btn-clear" title="Limpar Filtros"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card-tabela">
            <table>
                <thead>
                    <tr>
                        <th style="width: 140px;">Data Registro</th>
                        <th>Paciente / Tutor</th>
                        <th>Tipo de Exame</th>
                        <th>Laboratório</th>
                        <th style="text-align: center;">Laudo Assinado</th>
                        <th style="text-align: center; width: 180px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($exames) > 0): ?>
                        <?php foreach ($exames as $e): ?>
                        <tr>
                            <td><strong><?= date('d/m/Y', strtotime($e['data_registro'])) ?></strong></td>
                            <td class="paciente-info">
                                <strong><?= htmlspecialchars($e['nome_paciente']) ?></strong>
                                <small>Tutor: <?= htmlspecialchars($e['nome_cliente']) ?></small>
                            </td>
                            <td><span class="tag-exame"><?= str_replace('_', ' ', $e['tipo_exame']) ?></span></td>
                            <td><?= htmlspecialchars($e['laboratorio'] ?: 'Interno') ?></td>
                            <td style="text-align: center;">
                                <?php if($e['assinado_digitalmente']): ?>
                                    <span class="status-badge status-signed"><i class="fas fa-check-circle"></i> SIM</span>
                                <?php else: ?>
                                    <span style="color:#ccc; font-size: 13px; font-weight: 700;">NÃO</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <a href="consultas/visualizar_laudo.php?id=<?= $e['id'] ?>" class="btn-acao" title="Ver Laudo Completo">
                                    <i class="fas fa-file-medical"></i>
                                </a>
                                <?php if(!empty($e['anexos'])): ?>
                                    <a href="<?= explode(',', $e['anexos'])[0] ?>" target="_blank" class="btn-acao" title="Ver Anexo Original">
                                        <i class="fas fa-paperclip"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 80px; color: #999;">
                                <i class="fas fa-folder-open" style="font-size: 30px; display: block; margin-bottom: 10px;"></i>
                                Nenhum exame encontrado para os filtros selecionados.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        $(document).ready(function() {
            // Inicialização do Select2 com suporte a busca digitável
            $('#busca_paciente').select2({
                placeholder: "Digite o nome do tutor ou do animal...",
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function() { return "Nenhum registo encontrado"; }
                }
            });
        });
    </script>
</body>
</html>