<?php
/**
 * ========================================================================
 * ZIIPVET - DIAGNÓSTICO ASSISTIDO POR INTELIGÊNCIA ARTIFICIAL
 * VERSÃO: 1.0.0
 * ========================================================================
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';
require_once 'config_ia.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Sao_Paulo');

$titulo_pagina = "Diagnóstico por IA";
$id_paciente_selecionado = $_GET['id_paciente'] ?? null;
$dados_paciente = null;
$historico_medico = [];

// Carregar lista de pacientes
try {
    $sql_pacientes = "SELECT p.id, p.nome_paciente, p.especie, p.raca, p.sexo, p.data_nascimento, p.peso,
                      c.nome as nome_tutor, c.telefone
                      FROM pacientes p 
                      INNER JOIN clientes c ON p.id_cliente = c.id 
                      ORDER BY c.nome ASC";
    $lista_pacientes = $pdo->query($sql_pacientes)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $lista_pacientes = [];
}

// Se paciente selecionado, carregar dados
if ($id_paciente_selecionado) {
    try {
        // Dados do paciente
        $stmt = $pdo->prepare("SELECT p.*, c.nome as nome_tutor, c.telefone
                               FROM pacientes p
                               INNER JOIN clientes c ON p.id_cliente = c.id
                               WHERE p.id = ?");
        $stmt->execute([$id_paciente_selecionado]);
        $dados_paciente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calcular idade
        if ($dados_paciente && $dados_paciente['data_nascimento']) {
            $nasc = new DateTime($dados_paciente['data_nascimento']);
            $hoje = new DateTime();
            $diff = $hoje->diff($nasc);
            $dados_paciente['idade_texto'] = $diff->y . " anos e " . $diff->m . " meses";
            $dados_paciente['idade_meses'] = ($diff->y * 12) + $diff->m;
        }
        
        // Histórico de atendimentos (últimos 5)
        $stmt_atend = $pdo->prepare("SELECT tipo_atendimento, resumo, descricao, data_atendimento 
                                      FROM atendimentos WHERE id_paciente = ? 
                                      ORDER BY data_atendimento DESC LIMIT 5");
        $stmt_atend->execute([$id_paciente_selecionado]);
        $historico_medico['atendimentos'] = $stmt_atend->fetchAll(PDO::FETCH_ASSOC);
        
        // Patologias
        $stmt_pato = $pdo->prepare("SELECT nome_doenca, data_registro 
                                    FROM patologias WHERE id_paciente = ? 
                                    ORDER BY data_registro DESC");
        $stmt_pato->execute([$id_paciente_selecionado]);
        $historico_medico['patologias'] = $stmt_pato->fetchAll(PDO::FETCH_ASSOC);
        
        // Vacinas
        $stmt_vac = $pdo->prepare("SELECT resumo, data_atendimento 
                                   FROM atendimentos WHERE id_paciente = ? AND tipo_atendimento = 'Vacinação'
                                   ORDER BY data_atendimento DESC LIMIT 10");
        $stmt_vac->execute([$id_paciente_selecionado]);
        $historico_medico['vacinas'] = $stmt_vac->fetchAll(PDO::FETCH_ASSOC);
        
        // Exames recentes
        $stmt_exam = $pdo->prepare("SELECT tipo_exame, conclusoes_finais, data_exame 
                                    FROM exames WHERE id_paciente = ? 
                                    ORDER BY data_exame DESC LIMIT 5");
        $stmt_exam->execute([$id_paciente_selecionado]);
        $historico_medico['exames'] = $stmt_exam->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Erro ao carregar paciente: " . $e->getMessage());
    }
}

// Lista de sintomas organizados por categoria
$sintomas_categorias = [
    'Gastrointestinal' => [
        'vomito' => 'Vômito',
        'vomito_sangue' => 'Vômito com sangue',
        'diarreia' => 'Diarréia',
        'diarreia_sangue' => 'Diarréia com sangue',
        'constipacao' => 'Constipação',
        'falta_apetite' => 'Falta de apetite',
        'sede_excessiva' => 'Sede excessiva',
        'perda_peso' => 'Perda de peso'
    ],
    'Respiratório' => [
        'tosse' => 'Tosse',
        'espirros' => 'Espirros frequentes',
        'secrecao_nasal' => 'Secreção nasal',
        'dificuldade_respirar' => 'Dificuldade para respirar',
        'respiracao_rapida' => 'Respiração rápida',
        'respiracao_ruidosa' => 'Respiração ruidosa/chiado'
    ],
    'Comportamental' => [
        'letargia' => 'Letargia/Apatia',
        'agitacao' => 'Agitação excessiva',
        'agressividade' => 'Agressividade incomum',
        'confusao' => 'Confusão/Desorientação',
        'convulsoes' => 'Convulsões',
        'tremores' => 'Tremores',
        'andar_circulos' => 'Andar em círculos'
    ],
    'Pele e Pelos' => [
        'coceira' => 'Coceira intensa',
        'queda_pelo' => 'Queda de pelo',
        'feridas_pele' => 'Feridas na pele',
        'vermelhidao_pele' => 'Vermelhidão na pele',
        'inchacos' => 'Inchaços/Nódulos',
        'pele_descamando' => 'Pele descamando'
    ],
    'Olhos e Ouvidos' => [
        'secrecao_ocular' => 'Secreção nos olhos',
        'olhos_vermelhos' => 'Olhos vermelhos',
        'coceira_ouvido' => 'Coceira no ouvido',
        'secrecao_ouvido' => 'Secreção no ouvido',
        'cabeca_inclinada' => 'Cabeça inclinada'
    ],
    'Locomotor' => [
        'mancar' => 'Mancar/Claudicação',
        'dificuldade_andar' => 'Dificuldade para andar',
        'dificuldade_levantar' => 'Dificuldade para levantar',
        'dor_tocar' => 'Dor ao ser tocado',
        'incoordenacao' => 'Incoordenação motora'
    ],
    'Urinário' => [
        'urina_frequente' => 'Urina frequente',
        'dificuldade_urinar' => 'Dificuldade para urinar',
        'sangue_urina' => 'Sangue na urina',
        'incontinencia' => 'Incontinência urinária'
    ],
    'Outros' => [
        'febre' => 'Febre (nariz quente/seco)',
        'desidratacao' => 'Desidratação',
        'mucosas_palidas' => 'Mucosas pálidas',
        'ictericia' => 'Icterícia (amarelado)',
        'aumento_abdomen' => 'Aumento do abdômen',
        'salivacao_excessiva' => 'Salivação excessiva'
    ]
];
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
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
    .faixa-superior { width: 100% !important; margin: 0 !important; border-radius: 0 0 0 30px !important; }
        :root { 
            --fundo: #ecf0f5; --primaria: #1c329f; --sucesso: #28a745; --info: #17a2b8;
            --warning: #ffc107; --danger: #dc3545; --borda: #d2d6de;
            --sidebar-collapsed: 75px; --sidebar-expanded: 260px; --header-height: 80px;
            --ia-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Open Sans', sans-serif; background-color: var(--fundo); font-size: 15px; color: #333; }
        
        aside.sidebar-container { position: fixed; left: 0; top: 0; height: 100vh; width: var(--sidebar-collapsed); z-index: 1000; background: #fff; transition: width 0.4s; box-shadow: 2px 0 5px rgba(0,0,0,0.05); }
        aside.sidebar-container:hover { width: var(--sidebar-expanded); }
        header.top-header { position: fixed; top: 0; left: var(--sidebar-collapsed); right: 0; height: var(--header-height); z-index: 900; transition: left 0.4s; }
        aside.sidebar-container:hover ~ header.top-header { left: var(--sidebar-expanded); }
        main.main-content { margin-left: var(--sidebar-collapsed); padding: calc(var(--header-height) + 25px) 25px 40px; transition: margin-left 0.4s; }
        aside.sidebar-container:hover ~ main.main-content { margin-left: var(--sidebar-expanded); }
        
        .page-header {
            background: var(--ia-gradient);
            border-radius: 16px; padding: 30px; color: #fff;
            margin-bottom: 25px; display: flex; align-items: center; gap: 20px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        .page-header i { font-size: 50px; opacity: 0.9; }
        .page-header h1 { font-size: 28px; font-weight: 700; margin-bottom: 5px; }
        .page-header p { opacity: 0.9; font-size: 14px; }
        
        .card { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; overflow: hidden; }
        .card-header { background: #f8f9fa; padding: 20px; font-weight: 700; font-size: 16px; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 10px; }
        .card-body { padding: 25px; }
        
        .select-paciente-container { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 13px; font-weight: 600; color: #2c3e50; margin-bottom: 8px; }
        select, input, textarea { width: 100%; padding: 12px 16px; border: 1px solid var(--borda); border-radius: 8px; font-size: 15px; font-family: inherit; }
        select:focus, input:focus, textarea:focus { border-color: var(--primaria); outline: none; box-shadow: 0 0 0 3px rgba(28, 50, 159, 0.1); }
        textarea { min-height: 120px; resize: vertical; }
        
        .select2-container--default .select2-selection--single { height: 50px; display: flex; align-items: center; border: 1px solid var(--borda); border-radius: 8px; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        @media (max-width: 992px) { .grid-2 { grid-template-columns: 1fr; } }
        
        .paciente-resumo {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border-radius: 12px; padding: 20px; color: #fff; margin-bottom: 20px;
        }
        .paciente-resumo.felino { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .paciente-resumo h3 { font-size: 22px; margin-bottom: 10px; display: flex; align-items: center; gap: 10px; }
        .paciente-resumo .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-top: 15px; }
        .paciente-resumo .info-item { font-size: 13px; }
        .paciente-resumo .info-item strong { display: block; opacity: 0.8; font-size: 11px; text-transform: uppercase; }
        
        .sintomas-categoria { margin-bottom: 25px; }
        .sintomas-categoria h4 { font-size: 14px; color: var(--primaria); margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #f0f0f0; }
        .sintomas-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; }
        .sintoma-item {
            display: flex; align-items: center; gap: 10px; padding: 10px 12px;
            background: #f8f9fa; border-radius: 8px; cursor: pointer; transition: all 0.2s;
            border: 2px solid transparent;
        }
        .sintoma-item:hover { background: #e9ecef; }
        .sintoma-item.selected { background: #e8f5e9; border-color: var(--sucesso); }
        .sintoma-item input { display: none; }
        .sintoma-item .check-box {
            width: 22px; height: 22px; border: 2px solid #ccc; border-radius: 4px;
            display: flex; align-items: center; justify-content: center; transition: all 0.2s;
            color: transparent;
        }
        .sintoma-item.selected .check-box { background: var(--sucesso); border-color: var(--sucesso); color: #fff; }
        .sintoma-item span { font-size: 13px; }
        
        .historico-box { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 15px; }
        .historico-box h5 { font-size: 13px; color: #666; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
        .historico-box ul { list-style: none; font-size: 13px; }
        .historico-box li { padding: 5px 0; border-bottom: 1px solid #eee; }
        .historico-box li:last-child { border-bottom: none; }
        
        .btn-diagnosticar {
            background: var(--ia-gradient); color: #fff; border: none;
            padding: 18px 40px; border-radius: 10px; font-size: 16px; font-weight: 700;
            cursor: pointer; display: flex; align-items: center; gap: 12px;
            transition: all 0.3s; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-diagnosticar:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5); }
        .btn-diagnosticar:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .btn-diagnosticar i { font-size: 20px; }
        
        .resultado-ia {
            display: none; background: #fff; border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden; margin-top: 25px;
        }
        .resultado-ia.visible { display: block; animation: slideIn 0.5s ease; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        .resultado-header {
            background: var(--ia-gradient); color: #fff; padding: 20px 25px;
            display: flex; align-items: center; gap: 15px;
        }
        .resultado-header i { font-size: 30px; }
        .resultado-header h3 { font-size: 18px; }
        
        .resultado-body { padding: 25px; }
        .resultado-body h4 { color: var(--primaria); font-size: 16px; margin: 20px 0 10px; display: flex; align-items: center; gap: 8px; }
        .resultado-body h4:first-child { margin-top: 0; }
        .resultado-body p, .resultado-body li { line-height: 1.7; color: #444; }
        .resultado-body ul { margin-left: 20px; }
        
        .urgencia-badge {
            display: inline-block; padding: 8px 16px; border-radius: 20px;
            font-weight: 700; font-size: 14px; margin: 10px 0;
        }
        .urgencia-baixa { background: #d4edda; color: #155724; }
        .urgencia-media { background: #fff3cd; color: #856404; }
        .urgencia-alta { background: #f8d7da; color: #721c24; }
        .urgencia-emergencia { background: #721c24; color: #fff; animation: pulse 1s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
        
        .disclaimer {
            background: #fff3cd; border-left: 4px solid #ffc107;
            padding: 15px 20px; margin-top: 20px; border-radius: 0 8px 8px 0;
            font-size: 13px; color: #856404;
        }
        .disclaimer i { margin-right: 8px; }
        
        .loading-ia {
            display: none; text-align: center; padding: 40px;
        }
        .loading-ia.visible { display: block; }
        .loading-ia i { font-size: 50px; color: var(--primaria); animation: spin 1s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .loading-ia p { margin-top: 15px; color: #666; font-size: 14px; }
        
        .api-warning {
            background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px;
            padding: 20px; margin-bottom: 20px; color: #721c24;
        }
        .api-warning i { margin-right: 10px; }
        
        .tempo-sintomas { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 10px; }
        .tempo-btn {
            padding: 10px 20px; border: 2px solid #ddd; border-radius: 8px;
            background: #fff; cursor: pointer; transition: all 0.2s; font-size: 13px;
        }
        .tempo-btn:hover { border-color: var(--primaria); }
        .tempo-btn.selected { background: var(--primaria); color: #fff; border-color: var(--primaria); }
        
        .btn-salvar-diagnostico {
            background: var(--sucesso); color: #fff; border: none;
            padding: 12px 25px; border-radius: 8px; font-weight: 600;
            cursor: pointer; margin-top: 15px; display: none;
        }
        .btn-salvar-diagnostico.visible { display: inline-flex; align-items: center; gap: 8px; }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include '../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <div class="page-header">
            <i class="fas fa-brain"></i>
            <div>
                <h1>Diagnóstico Assistido por IA</h1>
                <p>Sistema inteligente de apoio ao diagnóstico veterinário • Powered by Google Gemini</p>
            </div>
        </div>
        
        <?php if (!iaConfigurada()): ?>
        <div class="api-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>API não configurada!</strong> 
            Para usar o diagnóstico por IA, configure sua chave da API do Google Gemini no arquivo 
            <code>config_ia.php</code>. 
            <a href="https://makersuite.google.com/app/apikey" target="_blank">Clique aqui para obter uma chave gratuita</a>.
        </div>
        <?php endif; ?>
        
        <div class="select-paciente-container">
            <div class="form-group" style="margin-bottom: 0;">
                <label><i class="fas fa-paw"></i> Selecione o Paciente</label>
                <select id="select_paciente" onchange="if(this.value) window.location.href='consultas/diagnostico.php?id_paciente=' + this.value">
                    <option value="">Pesquise por Tutor ou Pet...</option>
                    <?php foreach($lista_pacientes as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($id_paciente_selecionado == $p['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nome_tutor']) ?> - Pet: <?= htmlspecialchars($p['nome_paciente']) ?>
                            (<?= htmlspecialchars($p['especie']) ?> - <?= htmlspecialchars($p['raca'] ?? 'SRD') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if ($dados_paciente): ?>
        
        <form id="formDiagnostico">
            <input type="hidden" name="id_paciente" value="<?= $dados_paciente['id'] ?>">
            
            <div class="grid-2">
                
                <!-- COLUNA ESQUERDA -->
                <div>
                    <!-- Resumo do Paciente -->
                    <div class="paciente-resumo <?= strtolower($dados_paciente['especie']) == 'felina' ? 'felino' : '' ?>">
                        <h3>
                            <i class="fas <?= strtolower($dados_paciente['especie']) == 'felina' ? 'fa-cat' : 'fa-dog' ?>"></i>
                            <?= htmlspecialchars($dados_paciente['nome_paciente']) ?>
                        </h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <strong>Espécie</strong>
                                <?= htmlspecialchars($dados_paciente['especie']) ?>
                            </div>
                            <div class="info-item">
                                <strong>Raça</strong>
                                <?= htmlspecialchars($dados_paciente['raca'] ?? 'SRD') ?>
                            </div>
                            <div class="info-item">
                                <strong>Idade</strong>
                                <?= $dados_paciente['idade_texto'] ?? 'Não informada' ?>
                            </div>
                            <div class="info-item">
                                <strong>Peso</strong>
                                <?= htmlspecialchars($dados_paciente['peso'] ?? '-') ?> kg
                            </div>
                            <div class="info-item">
                                <strong>Sexo</strong>
                                <?= htmlspecialchars($dados_paciente['sexo'] ?? '-') ?>
                            </div>
                            <div class="info-item">
                                <strong>Tutor</strong>
                                <?= htmlspecialchars($dados_paciente['nome_tutor']) ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Histórico Médico -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-history"></i> Histórico Médico Relevante
                        </div>
                        <div class="card-body">
                            
                            <?php if (!empty($historico_medico['patologias'])): ?>
                            <div class="historico-box">
                                <h5><i class="fas fa-virus" style="color: #dc3545;"></i> Patologias Registradas</h5>
                                <ul>
                                    <?php foreach($historico_medico['patologias'] as $p): ?>
                                    <li><?= htmlspecialchars($p['nome_doenca']) ?> <small style="color:#999;">(<?= date('d/m/Y', strtotime($p['data_registro'])) ?>)</small></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($historico_medico['vacinas'])): ?>
                            <div class="historico-box">
                                <h5><i class="fas fa-syringe" style="color: #20c997;"></i> Vacinas Aplicadas</h5>
                                <ul>
                                    <?php foreach(array_slice($historico_medico['vacinas'], 0, 5) as $v): ?>
                                    <li><?= htmlspecialchars($v['resumo']) ?> <small style="color:#999;">(<?= date('d/m/Y', strtotime($v['data_atendimento'])) ?>)</small></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($historico_medico['atendimentos'])): ?>
                            <div class="historico-box">
                                <h5><i class="fas fa-stethoscope" style="color: #17a2b8;"></i> Últimos Atendimentos</h5>
                                <ul>
                                    <?php foreach($historico_medico['atendimentos'] as $a): ?>
                                    <li>
                                        <strong><?= htmlspecialchars($a['tipo_atendimento']) ?></strong>
                                        <?php if($a['resumo']): ?> - <?= htmlspecialchars($a['resumo']) ?><?php endif; ?>
                                        <small style="color:#999;">(<?= date('d/m/Y', strtotime($a['data_atendimento'])) ?>)</small>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (empty($historico_medico['patologias']) && empty($historico_medico['vacinas']) && empty($historico_medico['atendimentos'])): ?>
                            <p style="color: #999; text-align: center; padding: 20px;">
                                <i class="fas fa-info-circle"></i> Nenhum histórico médico registrado
                            </p>
                            <?php endif; ?>
                            
                        </div>
                    </div>
                </div>
                
                <!-- COLUNA DIREITA -->
                <div>
                    <!-- Sintomas -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-clipboard-list"></i> Sintomas Apresentados
                        </div>
                        <div class="card-body">
                            
                            <?php foreach($sintomas_categorias as $categoria => $sintomas): ?>
                            <div class="sintomas-categoria">
                                <h4><?= $categoria ?></h4>
                                <div class="sintomas-grid">
                                    <?php foreach($sintomas as $key => $label): ?>
                                    <label class="sintoma-item" data-sintoma="<?= $key ?>">
                                        <input type="checkbox" name="sintomas[]" value="<?= $key ?>">
                                        <span class="check-box"><i class="fas fa-check"></i></span>
                                        <span><?= $label ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Informações Adicionais -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-info-circle"></i> Informações Complementares
                </div>
                <div class="card-body">
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Há quanto tempo os sintomas começaram?</label>
                            <div class="tempo-sintomas">
                                <label class="tempo-btn" onclick="selecionarTempo(this)">
                                    <input type="radio" name="tempo_sintomas" value="hoje" style="display:none;">
                                    Hoje
                                </label>
                                <label class="tempo-btn" onclick="selecionarTempo(this)">
                                    <input type="radio" name="tempo_sintomas" value="1-3 dias" style="display:none;">
                                    1-3 dias
                                </label>
                                <label class="tempo-btn" onclick="selecionarTempo(this)">
                                    <input type="radio" name="tempo_sintomas" value="4-7 dias" style="display:none;">
                                    4-7 dias
                                </label>
                                <label class="tempo-btn" onclick="selecionarTempo(this)">
                                    <input type="radio" name="tempo_sintomas" value="1-2 semanas" style="display:none;">
                                    1-2 semanas
                                </label>
                                <label class="tempo-btn" onclick="selecionarTempo(this)">
                                    <input type="radio" name="tempo_sintomas" value="mais de 2 semanas" style="display:none;">
                                    +2 semanas
                                </label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>O animal está alimentando normalmente?</label>
                            <select name="alimentacao">
                                <option value="">Selecione...</option>
                                <option value="Sim, normal">Sim, alimentação normal</option>
                                <option value="Comendo menos que o normal">Comendo menos que o normal</option>
                                <option value="Quase não come">Quase não está comendo</option>
                                <option value="Não come nada">Não está comendo nada</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Descrição detalhada dos sintomas e observações</label>
                        <textarea name="descricao_sintomas" placeholder="Descreva com detalhes o que o animal está apresentando, quando começou, se houve algum evento que possa ter causado (ingestão de algo, contato com outros animais, etc.)..."></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Botão Diagnosticar -->
            <div style="text-align: center; margin: 30px 0;">
                <button type="submit" class="btn-diagnosticar" <?= !iaConfigurada() ? 'disabled' : '' ?>>
                    <i class="fas fa-robot"></i>
                    Analisar com Inteligência Artificial
                </button>
            </div>
            
        </form>
        
        <!-- Loading -->
        <div class="loading-ia" id="loadingIA">
            <i class="fas fa-spinner"></i>
            <p>Analisando sintomas e histórico médico...<br>Aguarde enquanto a IA processa as informações.</p>
        </div>
        
        <!-- Resultado -->
        <div class="resultado-ia" id="resultadoIA">
            <div class="resultado-header">
                <i class="fas fa-brain"></i>
                <div>
                    <h3>Análise Diagnóstica</h3>
                    <small>Resultado gerado por Inteligência Artificial</small>
                </div>
            </div>
            <div class="resultado-body" id="conteudoResultado">
                <!-- Conteúdo será inserido via JavaScript -->
            </div>
            <div style="padding: 0 25px 25px;">
                <button type="button" class="btn-salvar-diagnostico" id="btnSalvarDiagnostico" onclick="salvarDiagnostico()">
                    <i class="fas fa-save"></i> Salvar no Prontuário
                </button>
                
                <div class="disclaimer">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Aviso Importante:</strong> Este diagnóstico é uma sugestão baseada em inteligência artificial e serve apenas como apoio à decisão clínica. 
                    O diagnóstico definitivo deve ser feito pelo médico veterinário através de exame clínico presencial e exames complementares quando necessário.
                </div>
            </div>
        </div>
        
        <?php else: ?>
        
        <div style="text-align: center; padding: 80px 20px; color: #999;">
            <i class="fas fa-search" style="font-size: 80px; margin-bottom: 20px; opacity: 0.3;"></i>
            <h3 style="color: #666;">Selecione um paciente para iniciar o diagnóstico</h3>
            <p>Escolha um animal na lista acima para acessar o sistema de diagnóstico por IA</p>
        </div>
        
        <?php endif; ?>
        
    </main>

    <script>
    var dadosPaciente = <?= json_encode($dados_paciente ?? null) ?>;
    var historicoMedico = <?= json_encode($historico_medico ?? []) ?>;
    var ultimoResultado = null;
    
    $(document).ready(function() {
        $('#select_paciente').select2({ placeholder: "Pesquise...", width: '100%' });
        
        // Clique nos sintomas
        $(document).on('click', '.sintoma-item', function(e) {
            e.preventDefault();
            $(this).toggleClass('selected');
            var checkbox = $(this).find('input[type="checkbox"]');
            checkbox.prop('checked', $(this).hasClass('selected'));
        });
        
        // Submit do formulário
        $('#formDiagnostico').on('submit', function(e) {
            e.preventDefault();
            realizarDiagnostico();
        });
    });
    
    function selecionarTempo(el) {
        document.querySelectorAll('.tempo-btn').forEach(b => b.classList.remove('selected'));
        el.classList.add('selected');
        el.querySelector('input').checked = true;
    }
    
    function realizarDiagnostico() {
    // 1. Coletar sintomas selecionados
    const sintomasSelecionados = [];
    document.querySelectorAll('input[name="sintomas[]"]:checked').forEach(cb => {
        sintomasSelecionados.push(cb.value);
    });
    
    // 2. Validação básica: verificar se há sintomas marcados
    if (sintomasSelecionados.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Marque pelo menos um sintoma para realizar a análise.'
        });
        return;
    }

    // 3. Interface: Bloquear botão e mostrar Loading
    const btn = $('.btn-diagnosticar');
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processando Análise...');
    
    $('#loadingIA').addClass('visible');
    $('#resultadoIA').removeClass('visible');
    $('#btnSalvarDiagnostico').removeClass('visible');

    // 4. Preparar o objeto de dados conforme o seu PHP processar_diagnostico.php
    const formData = {
        id_paciente: dadosPaciente.id,
        paciente: dadosPaciente,
        historico: historicoMedico,
        sintomas: sintomasSelecionados,
        tempo_sintomas: $('input[name="tempo_sintomas"]:checked').val() || 'não informado',
        alimentacao: $('select[name="alimentacao"]').val() || 'não informado',
        descricao: $('textarea[name="descricao_sintomas"]').val()
    };

    console.log('[ZIIPVET IA] Enviando dados para análise...', formData);

    // 5. Chamada AJAX
    $.ajax({
        url: 'consultas/processar_diagnostico.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        timeout: 90000, // 90 segundos para casos complexos
        success: function(resposta) {
            console.log('[ZIIPVET IA] Resposta recebida:', resposta);
            
            if (resposta.sucesso) {
                ultimoResultado = resposta;
                exibirResultado(resposta.diagnostico);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro na análise',
                    text: resposta.erro || 'A IA não conseguiu processar os dados.'
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('[ZIIPVET IA] Erro na requisição:', status, error);
            let msg = 'Erro de comunicação com o servidor.';
            if (status === 'timeout') msg = 'A análise demorou demais. Tente novamente.';
            
            Swal.fire({
                icon: 'error',
                title: 'Falha na Conexão',
                text: msg
            });
        },
        complete: function() {
            // 6. Interface: Reativar botão e esconder loading
            $('#loadingIA').removeClass('visible');
            btn.prop('disabled', false).html('<i class="fas fa-robot"></i> Analisar com Inteligência Artificial');
        }
    });
}
    
    function exibirResultado(texto) {
        // Formatar o texto da IA
        let html = texto
            // Converter emojis e títulos
            .replace(/🔍\s*DIAGNÓSTICOS PROVÁVEIS:/gi, '<h4><i class="fas fa-search-plus"></i> Diagnósticos Prováveis</h4>')
            .replace(/📋\s*EXAMES RECOMENDADOS:/gi, '<h4><i class="fas fa-vial"></i> Exames Recomendados</h4>')
            .replace(/💊\s*CONDUTA SUGERIDA:/gi, '<h4><i class="fas fa-pills"></i> Conduta Sugerida</h4>')
            .replace(/⚠️\s*NÍVEL DE URGÊNCIA:/gi, '<h4><i class="fas fa-exclamation-triangle"></i> Nível de Urgência</h4>')
            .replace(/📝\s*OBSERVAÇÕES:/gi, '<h4><i class="fas fa-clipboard"></i> Observações</h4>')
            // Converter quebras de linha
            .replace(/\n\n/g, '</p><p>')
            .replace(/\n/g, '<br>')
            // Negrito
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            // Listas
            .replace(/^- /gm, '• ')
            .replace(/^\d+\. /gm, function(match) { return match; });
        
        // Adicionar badges de urgência
        html = html.replace(/Baixo/gi, '<span class="urgencia-badge urgencia-baixa">Baixo</span>');
        html = html.replace(/Médio/gi, '<span class="urgencia-badge urgencia-media">Médio</span>');
        html = html.replace(/Alto(?!\s*\/)/gi, '<span class="urgencia-badge urgencia-alta">Alto</span>');
        html = html.replace(/Emergência/gi, '<span class="urgencia-badge urgencia-emergencia">🚨 Emergência</span>');
        
        html = '<p>' + html + '</p>';
        
        $('#conteudoResultado').html(html);
        $('#resultadoIA').addClass('visible');
        $('#btnSalvarDiagnostico').addClass('visible');
        
        // Scroll para o resultado
        $('html, body').animate({
            scrollTop: $('#resultadoIA').offset().top - 100
        }, 500);
    }
    
    function salvarDiagnostico() {
        if (!ultimoResultado) return;
        
        Swal.fire({
            title: 'Salvar no prontuário?',
            text: 'O diagnóstico será registrado no histórico do paciente.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, salvar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'consultas/processar_diagnostico.php',
                    method: 'POST',
                    data: {
                        salvar_diagnostico: 1,
                        id_paciente: dadosPaciente.id,
                        diagnostico: ultimoResultado.diagnostico,
                        sintomas: ultimoResultado.sintomas_enviados
                    },
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.sucesso) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Salvo!',
                                text: 'Diagnóstico registrado no prontuário.'
                            });
                            $('#btnSalvarDiagnostico').removeClass('visible');
                        } else {
                            Swal.fire({ icon: 'error', title: 'Erro', text: resp.erro });
                        }
                    }
                });
            }
        });
    }
    </script>
</body>
</html>