<?php
/**
 * =========================================================================================
 * ZIIPVET - CONSOLE UNIFICADO DE ATENDIMENTO VETERINÁRIO
 * ARQUIVO: realizar_consulta.php (MODULARIZADO)
 * VERSÃO: 9.0.0 - ARQUITETURA MODULAR
 * =========================================================================================
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
                    echo '  <span style="font-weight:700; color:#1c329f;">' . htmlspecialchars($hp['peso']) . ' kg</span>';
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
    $sql_pacientes = "SELECT p.id, p.nome_paciente, p.especie, p.raca, p.sexo, p.data_nascimento, p.peso, p.pelagem, p.chip,
                      c.id as id_cliente, c.nome as nome_tutor, c.cpf_cnpj, c.telefone, c.endereco, c.numero, c.bairro, c.cidade, c.estado
                      FROM pacientes p 
                      INNER JOIN clientes c ON p.id_cliente = c.id 
                      ORDER BY c.nome ASC";
    $lista_pacientes = $pdo->query($sql_pacientes)->fetchAll(PDO::FETCH_ASSOC);
    
    $lista_modelos = [];
    try {
        $lista_modelos = $pdo->query("SELECT * FROM modelos_receitas ORDER BY titulo ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
} catch (PDOException $e) {
    $lista_pacientes = [];
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
    
    <base href="https://www.lepetboutique.com.br/app/">
    
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    
    <!-- CSS COMPARTILHADO -->
    <?php include 'consultas/modulos/_shared_styles.php'; ?>
</head>
<body>

    <aside class="sidebar-container"><?php include '../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <!-- SELEÇÃO DE PACIENTE -->
        <div class="select-paciente-container">
            <div class="form-group">
                <label><i class="fas fa-paw"></i> Selecione o Paciente / Tutor</label>
                <select id="select_paciente" onchange="if(this.value) window.location.href='consultas/realizar_consulta.php?id_paciente=' + this.value">
                    <option value="">Pesquise por Tutor ou Pet...</option>
                    <?php foreach($lista_pacientes as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($id_paciente_selecionado == $p['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nome_tutor']) ?> - Pet: <?= htmlspecialchars($p['nome_paciente']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if ($dados_paciente): 
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

                <!-- ============================================================================ -->
                <!-- MÓDULOS INCLUÍDOS DINAMICAMENTE -->
                <!-- ============================================================================ -->
                
                <!-- ATENDIMENTO -->
                <div class="secao-conteudo active" data-secao="atendimento">
                    <?php include 'consultas/modulos/atendimento.php'; ?>
                </div>

                <!-- PATOLOGIA -->
                <div class="secao-conteudo" data-secao="patologia">
                    <?php include 'consultas/modulos/patologia.php'; ?>
                </div>

                <!-- EXAMES -->
                <div class="secao-conteudo" data-secao="exame">
                    <?php include 'consultas/modulos/exames.php'; ?>
                </div>

                <!-- VACINAS -->
                <div class="secao-conteudo" data-secao="vacina">
                    <?php include 'consultas/modulos/vacinas.php'; ?>
                </div>

                <!-- RECEITAS -->
                <div class="secao-conteudo" data-secao="receita">
                    <?php include 'consultas/modulos/receitas.php'; ?>
                </div>

                <!-- DOCUMENTOS -->
                <div class="secao-conteudo" data-secao="documentos">
                    <?php include 'consultas/modulos/documentos.php'; ?>
                </div>

                <!-- DIAGNÓSTICO IA -->
                <div class="secao-conteudo" data-secao="diagnostico-ia">
                    <?php include 'consultas/modulos/diagnostico_ia.php'; ?>
                </div>

            </div>

            <!-- SIDEBAR: HISTÓRICO -->
            <?php include 'consultas/modulos/_sidebar_historico.php'; ?>

        </div>

        <?php else: ?>
            <div style="text-align: center; padding: 80px 20px; color: #999;">
                <i class="fas fa-hand-pointer" style="font-size: 80px; margin-bottom: 20px; opacity: 0.3;"></i>
                <h3 style="color: #666; margin-bottom: 10px;">Selecione um paciente</h3>
            </div>
        <?php endif; ?>
        
        <!-- MODAL: NOVO MODELO DE RECEITA -->
        <?php include 'consultas/modulos/_modal_modelo.php'; ?>

    </main>

    <!-- JAVASCRIPT COMPARTILHADO -->
    <?php include 'consultas/modulos/_shared_scripts.php'; ?>
    
    <script src="js/carregarDetalhesHistorico.js"></script>
</body>
</html>
