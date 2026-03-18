<?php
/**
 * ZIIPVET - CONSOLE UNIFICADO DE ATENDIMENTO VETERINÁRIO
 * ARQUIVO: realizar_consulta.php
 * VERSÃO: 11.0.0 - REFATORADO (SRP)
 * RESPONSABILIDADE: Renderizar a interface de atendimento
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
// CARREGAMENTO DE DADOS PARA A VIEW
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
    
    <!-- CSS CUSTOM DA CONSULTA -->
    <link rel="stylesheet" href="../css/consulta_custom.css">

</head>
<body>

    <?php $path_prefix = '../'; ?>
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
    
    <!-- API Client para Consultas -->
    <script src="js/consulta-api-client.js"></script>
    
    <script src="../js/carregarDetalhesHistorico.js"></script>
    <script src="../js/carregarVacina_ISOLADO.js"></script>
    
    <!-- Comportamento da Consulta -->
    <script src="../js/consulta_behavior.js"></script>

</body>
</html>