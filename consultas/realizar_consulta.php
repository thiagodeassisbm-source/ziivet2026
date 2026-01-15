<?php
/**
 * ZIIPVET - CONSOLE UNIFICADO DE ATENDIMENTO VETERINÁRIO
 * ARQUIVO: realizar_consulta.php
 * VERSÃO: 10.0.0 - PADRÃO MODERNO
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Sao_Paulo');

$titulo_pagina = "Console de Atendimento";
$id_paciente_selecionado = $_GET['id_paciente'] ?? null;
$dados_paciente = null;
$historico_recente = [];
$dados_preenchidos_vacina = null;

// ========================================================================================
// AJAX: DADOS DO ANIMAL
// ========================================================================================
if (isset($_GET['ajax_dados_animal']) && isset($_GET['id_paciente'])) {
    header('Content-Type: application/json; charset=utf-8');
    $id = (int)$_GET['id_paciente'];
    try {
        $stmt = $pdo->prepare("SELECT p.*, c.nome as nome_tutor, c.cpf_cnpj, c.telefone, c.endereco, c.numero, c.bairro, c.cidade, c.estado, c.cep, c.email
                                FROM pacientes p 
                                INNER JOIN clientes c ON p.id_cliente = c.id 
                                WHERE p.id = ?");
        $stmt->execute([$id]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($dados && $dados['data_nascimento']) {
            $nasc = new DateTime($dados['data_nascimento']);
            $hoje = new DateTime();
            $idade = $nasc->diff($hoje);
            $dados['idade_animal'] = $idade->y . ' anos ' . $idade->m . ' meses';
        } else {
            $dados['idade_animal'] = 'não informado';
        }
        
        echo json_encode($dados);
    } catch (Exception $e) { 
        echo json_encode(['erro' => true]); 
    }
    exit;
}

// ========================================================================================
// AJAX: HISTÓRICO DE VACINAS
// ========================================================================================
if (isset($_GET['ajax_historico']) && isset($_GET['id_paciente'])) {
    header('Content-Type: application/json; charset=utf-8');
    $id = (int)$_GET['id_paciente'];
    
    try {
        $stmt = $pdo->prepare("SELECT resumo, data_atendimento 
                                FROM atendimentos 
                                WHERE id_paciente = ? AND tipo_atendimento = 'Vacinação' 
                                ORDER BY data_atendimento DESC LIMIT 5");
        $stmt->execute([$id]);
        $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt2 = $pdo->prepare("SELECT id, vacina_nome, dose_prevista, data_prevista 
                                 FROM lembretes_vacinas 
                                 WHERE id_paciente = ? AND status = 'Pendente' 
                                 ORDER BY data_prevista ASC");
        $stmt2->execute([$id]);
        $lembretes = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'historico' => $historico, 
            'lembretes' => $lembretes
        ]);
    } catch (Exception $e) { 
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]); 
    }
    exit;
}

// ========================================================================================
// AJAX: HISTÓRICO DE PESO
// ========================================================================================
if (isset($_GET['ajax_peso']) && isset($_GET['id_paciente'])) {
    $id_pac = (int)$_GET['id_paciente'];
    if ($id_pac > 0) {
        try {
            $stmt_ajax = $pdo->prepare("SELECT data_atendimento, peso FROM atendimentos 
                                        WHERE id_paciente = ? AND peso IS NOT NULL AND peso != '' 
                                        ORDER BY data_atendimento DESC LIMIT 10");
            $stmt_ajax->execute([$id_pac]);
            $lista_h = $stmt_ajax->fetchAll(PDO::FETCH_ASSOC);

            if (count($lista_h) > 0) {
                echo '<ul style="list-style:none; padding:0; margin:0;">';
                foreach ($lista_h as $hp) {
                    echo '<li style="display:flex; justify-content:space-between; padding:12px 0; border-bottom:1px solid #f4f4f4; font-size:13px;">';
                    echo '  <span style="color:#888; font-weight:600;">' . date('d/m/Y', strtotime($hp['data_atendimento'])) . '</span>';
                    echo '  <span style="font-weight:700; color:#131c71;">' . htmlspecialchars($hp['peso']) . ' kg</span>';
                    echo '</li>';
                }
                echo '</ul>';
            } else {
                echo '<div style="text-align:center; padding:20px; color:#999; font-style:italic; font-size:12px;">Sem registros de peso anteriores</div>';
            }
        } catch (PDOException $e) {
            echo '<span style="color:red; font-size:12px;">Erro ao consultar banco de dados</span>';
        }
    }
    exit;
}

// ========================================================================================
// CARREGAMENTO DE DADOS
// ========================================================================================
try {
    $sql_clientes = "SELECT c.id as id_cliente, c.nome as nome_cliente, 
                     GROUP_CONCAT(p.id ORDER BY p.nome_paciente SEPARATOR '|') as ids_pacientes,
                     GROUP_CONCAT(p.nome_paciente ORDER BY p.nome_paciente SEPARATOR '|') as nomes_pacientes,
                     GROUP_CONCAT(p.especie ORDER BY p.nome_paciente SEPARATOR '|') as especies_pacientes
                     FROM clientes c
                     INNER JOIN pacientes p ON c.id = p.id_cliente
                     WHERE p.status = 'ATIVO'
                     GROUP BY c.id, c.nome
                     ORDER BY c.nome ASC";
    
    $lista_clientes = $pdo->query($sql_clientes)->fetchAll(PDO::FETCH_ASSOC);
    
    $lista_modelos = [];
    try {
        $lista_modelos = $pdo->query("SELECT * FROM modelos_receitas ORDER BY titulo ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
} catch (PDOException $e) {
    $lista_clientes = [];
    $lista_modelos = [];
}

// SE PACIENTE SELECIONADO
if ($id_paciente_selecionado) {
    try {
        $stmt = $pdo->prepare("SELECT p.*, c.nome as nome_tutor, c.cpf_cnpj, c.telefone, c.endereco, c.numero, c.bairro, c.cidade, c.estado
                               FROM pacientes p
                               INNER JOIN clientes c ON p.id_cliente = c.id
                               WHERE p.id = ?");
        $stmt->execute([$id_paciente_selecionado]);
        $dados_paciente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt_pre = $pdo->prepare("SELECT * FROM lembretes_vacinas WHERE id_paciente = ? AND status = 'Pendente' ORDER BY id DESC LIMIT 1");
        $stmt_pre->execute([$id_paciente_selecionado]);
        $dados_preenchidos_vacina = $stmt_pre->fetch(PDO::FETCH_ASSOC);
        
        $hist_atend = $pdo->prepare("SELECT 'atendimento' as tipo, tipo_atendimento as titulo, data_atendimento as data 
                                     FROM atendimentos WHERE id_paciente = ? ORDER BY data_atendimento DESC LIMIT 5");
        $hist_atend->execute([$id_paciente_selecionado]);
        $historico_recente = $hist_atend->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    
    <!-- CSS CENTRALIZADO -->
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/menu.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/formularios.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    
    <!-- CSS COMPARTILHADO -->
    <?php include 'modulos/_shared_styles.php'; ?>
    
    <style>
        /* SELEÇÃO DE PACIENTE */
        .select-paciente-container {
            background: #fff;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }
        
        .select-paciente-container .form-group {
            margin-bottom: 20px;
        }
        
        .select-paciente-container label {
            font-size: 16px;
            font-weight: 600;
            color: #495057;
            font-family: 'Exo', sans-serif;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }
        
        .select-paciente-container label i {
            color: #131c71;
            font-size: 18px;
        }
        
        /* CARDS DE PACIENTES */
        .card-paciente-select {
            background: #fff;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 18px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .card-paciente-select:hover {
            border-color: #131c71;
            background: #f8f9ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(98, 37, 153, 0.15);
        }

        .card-paciente-select.selecionado {
            border-color: #131c71;
            background: linear-gradient(135deg, #f0f4ff, #e8efff);
            box-shadow: 0 4px 15px rgba(98, 37, 153, 0.2);
        }

        .card-paciente-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #b92426;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .card-paciente-info {
            flex: 1;
        }

        .card-paciente-nome {
            font-size: 17px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
            font-family: 'Exo', sans-serif;
        }

        .card-paciente-especie {
            font-size: 14px;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 6px;
            font-family: 'Exo', sans-serif;
        }

        .card-paciente-check {
            width: 28px;
            height: 28px;
            border: 2px solid #ccc;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.3s;
        }

        .card-paciente-select.selecionado .card-paciente-check {
            background: #622599;
            border-color: #622599;
            color: white;
        }
        
        /* CARD DO PACIENTE SELECIONADO */
        .card-paciente {
            background: linear-gradient(135deg, #622599, #8e44ad);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 4px 20px rgba(98, 37, 153, 0.3);
            display: flex;
            align-items: center;
            gap: 25px;
            color: #fff;
        }
        
        .paciente-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: #fff;
            flex-shrink: 0;
        }
        
        .paciente-info {
            flex: 1;
        }
        
        .paciente-info h2 {
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 8px 0;
            font-family: 'Exo', sans-serif;
        }
        
        .subtitulo {
            font-size: 15px;
            opacity: 0.9;
            margin-bottom: 15px;
            font-family: 'Exo', sans-serif;
        }
        
        .paciente-detalhes {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .detalhe-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-family: 'Exo', sans-serif;
        }
        
        .detalhe-item i {
            font-size: 16px;
            opacity: 0.8;
        }
        
        /* ESTADO VAZIO */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #adb5bd;
        }
        
        .empty-state i {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .empty-state h3 {
            color: #6c757d;
            margin-bottom: 10px;
            font-size: 22px;
            font-weight: 600;
            font-family: 'Exo', sans-serif;
        }
        
        .empty-state p {
            color: #adb5bd;
            font-size: 15px;
            font-family: 'Exo', sans-serif;
        }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include '../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <?php if (!$dados_paciente): ?>
        
        <!-- SELEÇÃO DE PACIENTE -->
        <div class="form-header">
            <h1 class="form-title">
                <i class="fas fa-stethoscope"></i>
                Console de Atendimento
            </h1>
        </div>
        
        <div class="select-paciente-container">
            <div class="form-group">
                <label>
                    <i class="fas fa-user"></i>
                    Selecione o Cliente
                </label>
                <select id="select_cliente" class="form-control">
                    <option value="">Pesquise por Cliente...</option>
                    <?php foreach($lista_clientes as $c): ?>
                        <option value="<?= $c['id_cliente'] ?>" 
                                data-pacientes='<?= json_encode([
                                    'ids' => explode('|', $c['ids_pacientes']),
                                    'nomes' => explode('|', $c['nomes_pacientes']),
                                    'especies' => explode('|', $c['especies_pacientes'])
                                ]) ?>'>
                            <?php 
                            $nomes_pacientes = explode('|', $c['nomes_pacientes']);
                            echo htmlspecialchars($c['nome_cliente']) . ' - ' . implode(', ', $nomes_pacientes);
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="grupo_pacientes" style="display: none;">
                <label>
                    <i class="fas fa-paw"></i>
                    Selecione o Paciente
                </label>
                <div id="cards_pacientes" style="display: grid; gap: 15px; margin-top: 10px; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));">
                    <!-- Cards serão inseridos aqui via JavaScript -->
                </div>
            </div>
        </div>
        
        <div class="empty-state">
            <i class="fas fa-stethoscope"></i>
            <h3>Selecione um paciente para iniciar o atendimento</h3>
            <p>Use o campo acima para buscar o cliente e escolher o animal</p>
        </div>

        <?php else: 
            $especie_class = strtolower($dados_paciente['especie']) == 'canina' ? 'canino' : 'felino';
            $icone = $especie_class == 'canino' ? 'fa-dog' : 'fa-cat';
            
            $idade = '';
            if ($dados_paciente['data_nascimento']) {
                $nasc = new DateTime($dados_paciente['data_nascimento']);
                $hoje = new DateTime();
                $diff = $hoje->diff($nasc);
                $idade = $diff->y . " anos, " . $diff->m . " meses";
            }
        ?>
        
        <!-- HEADER COM BOTÃO VOLTAR -->
        <div class="form-header">
            <h1 class="form-title">
                <i class="fas fa-stethoscope"></i>
                Console de Atendimento
            </h1>
            
            <a href="realizar_consulta.php" class="btn-voltar">
                <i class="fas fa-arrow-left"></i>
                Voltar
            </a>
        </div>
        
        <!-- CARD DO PACIENTE -->
        <div class="card-paciente <?= $especie_class ?>">
            <div class="paciente-avatar"><i class="fas <?= $icone ?>"></i></div>
            <div class="paciente-info">
                <h2><?= htmlspecialchars($dados_paciente['nome_paciente']) ?></h2>
                <div class="subtitulo">Tutor: <?= htmlspecialchars($dados_paciente['nome_tutor']) ?></div>
                <div class="paciente-detalhes">
                    <div class="detalhe-item"><i class="fas fa-dna"></i><span><?= htmlspecialchars($dados_paciente['raca'] ?? 'SRD') ?></span></div>
                    <div class="detalhe-item"><i class="fas fa-venus-mars"></i><span><?= htmlspecialchars($dados_paciente['sexo'] ?? '-') ?></span></div>
                    <div class="detalhe-item"><i class="fas fa-birthday-cake"></i><span><?= $idade ?: '-' ?></span></div>
                    <div class="detalhe-item"><i class="fas fa-weight"></i><span><?= htmlspecialchars($dados_paciente['peso'] ?? '-') ?> kg</span></div>
                    <div class="detalhe-item"><i class="fas fa-phone"></i><span><?= htmlspecialchars($dados_paciente['telefone'] ?? '-') ?></span></div>
                </div>
            </div>
        </div>

        <div class="console-grid">
            
            <div class="area-principal">
                
                <!-- NAVEGAÇÃO POR TABS -->
                <div class="tabs-navegacao">
                    <button class="tab-btn active" data-secao="atendimento"><i class="fas fa-stethoscope"></i> Atendimento</button>
                    <button class="tab-btn" data-secao="patologia"><i class="fas fa-virus"></i> Patologia</button>
                    <button class="tab-btn" data-secao="exame"><i class="fas fa-microscope"></i> Exames</button>
                    <button class="tab-btn" data-secao="vacina"><i class="fas fa-syringe"></i> Vacinas</button>
                    <button class="tab-btn" data-secao="receita"><i class="fas fa-prescription"></i> Receitas</button>
                    <button class="tab-btn" data-secao="documentos"><i class="fas fa-file-alt"></i> Documentos</button>
                    <button class="tab-btn tab-ia" data-secao="diagnostico-ia"><i class="fas fa-brain"></i> Diagnóstico IA</button>
                </div>

                <!-- MÓDULOS INCLUÍDOS DINAMICAMENTE -->
                
                <!-- ATENDIMENTO -->
                <div class="secao-conteudo active" data-secao="atendimento">
                    <?php include 'modulos/atendimento.php'; ?>
                </div>

                <!-- PATOLOGIA -->
                <div class="secao-conteudo" data-secao="patologia">
                    <?php include 'modulos/patologia.php'; ?>
                </div>

                <!-- EXAMES -->
                <div class="secao-conteudo" data-secao="exame">
                    <?php include 'modulos/exames.php'; ?>
                </div>

                <!-- VACINAS -->
                <div class="secao-conteudo" data-secao="vacina">
                    <?php include 'modulos/vacinas.php'; ?>
                </div>

                <!-- RECEITAS -->
                <div class="secao-conteudo" data-secao="receita">
                    <?php include 'modulos/receitas.php'; ?>
                </div>

                <!-- DOCUMENTOS -->
                <div class="secao-conteudo" data-secao="documentos">
                    <?php include 'modulos/documentos.php'; ?>
                </div>

                <!-- DIAGNÓSTICO IA -->
                <div class="secao-conteudo" data-secao="diagnostico-ia">
                    <?php include 'modulos/diagnostico_ia.php'; ?>
                </div>

            </div>

            <!-- SIDEBAR: HISTÓRICO -->
            <?php include 'modulos/_sidebar_historico.php'; ?>

        </div>

        <?php endif; ?>
        
        <!-- MODAL: NOVO MODELO DE RECEITA -->
        <?php include 'modulos/_modal_modelo.php'; ?>

    </main>

    <!-- JAVASCRIPT COMPARTILHADO -->
    <?php include 'modulos/_shared_scripts.php'; ?>
    
    <script src="js/carregarDetalhesHistorico.js"></script>
    <script src="js/carregarVacina_ISOLADO.js"></script>

    <script>
        $(document).ready(function() {
            // Inicializar Select2
            $('#select_cliente').select2({
                placeholder: 'Digite para pesquisar o cliente...',
                allowClear: true,
                width: '100%'
            });

            // Ao selecionar cliente
            $('#select_cliente').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                const pacientesInfo = selectedOption.data('pacientes');
                
                if (pacientesInfo) {
                    $('#cards_pacientes').empty();
                    
                    pacientesInfo.ids.forEach((id, index) => {
                        const nome = pacientesInfo.nomes[index];
                        const especie = pacientesInfo.especies[index] || 'Pet';
                        
                        const icone = especie.toLowerCase().includes('felino') || especie.toLowerCase().includes('gato')
                            ? 'fa-cat' 
                            : 'fa-dog';
                        
                        const card = $(`
                            <div class="card-paciente-select" data-id="${id}">
                                <div class="card-paciente-icon">
                                    <i class="fas ${icone}"></i>
                                </div>
                                <div class="card-paciente-info">
                                    <div class="card-paciente-nome">${nome}</div>
                                    <div class="card-paciente-especie">
                                        <i class="fas fa-paw" style="font-size: 11px;"></i>
                                        ${especie}
                                    </div>
                                </div>
                                <div class="card-paciente-check">
                                    <i class="fas fa-check" style="display: none;"></i>
                                </div>
                            </div>
                        `);
                        
                        card.on('click', function() {
                            // Redirecionar para a consulta do paciente
                            window.location.href = 'realizar_consulta.php?id_paciente=' + $(this).data('id');
                        });
                        
                        $('#cards_pacientes').append(card);
                    });
                    
                    $('#grupo_pacientes').slideDown(300);
                } else {
                    $('#grupo_pacientes').slideUp(300);
                }
            });
        });
    </script>
</body>
</html>