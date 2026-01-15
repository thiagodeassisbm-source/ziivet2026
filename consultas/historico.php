<?php
/**
 * =========================================================================================
 * ZIIPVET - HISTÓRICO COMPLETO DO PACIENTE
 * ARQUIVO: historico.php
 * VERSÃO: 1.0.0 - TIMELINE UNIFICADA
 * =========================================================================================
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$titulo_pagina = "Histórico do Paciente";
$id_paciente_selecionado = $_GET['id_paciente'] ?? null;
$historico = [];

// 1. CARREGAR LISTA DE PACIENTES
try {
    $sql_pacientes = "SELECT p.id, p.nome_paciente, c.nome as nome_tutor, c.cpf_cnpj, p.especie, p.raca, p.sexo, p.data_nascimento
                      FROM pacientes p 
                      INNER JOIN clientes c ON p.id_cliente = c.id 
                      ORDER BY c.nome ASC";
    $lista_pacientes = $pdo->query($sql_pacientes)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $lista_pacientes = [];
}

// 2. SE UM PACIENTE FOI SELECIONADO, BUSCAR TODO O HISTÓRICO
if ($id_paciente_selecionado) {
    try {
        // 2.1. ATENDIMENTOS
        $stmt_atend = $pdo->prepare("SELECT 'atendimento' as tipo, id, tipo_atendimento as subtipo, 
                                      resumo, descricao, data_atendimento as data, peso, anexo
                                      FROM atendimentos 
                                      WHERE id_paciente = ? 
                                      ORDER BY data_atendimento DESC");
        $stmt_atend->execute([$id_paciente_selecionado]);
        $atendimentos = $stmt_atend->fetchAll(PDO::FETCH_ASSOC);
        
        // 2.2. PATOLOGIAS
        $stmt_pato = $pdo->prepare("SELECT 'patologia' as tipo, id, nome_doenca as subtipo, 
                                    protocolo_descricao as descricao, data_registro as data, 
                                    usuario_responsavel
                                    FROM patologias 
                                    WHERE id_paciente = ? 
                                    ORDER BY data_registro DESC");
        $stmt_pato->execute([$id_paciente_selecionado]);
        $patologias = $stmt_pato->fetchAll(PDO::FETCH_ASSOC);
        
        // 2.3. EXAMES
        $stmt_exam = $pdo->prepare("SELECT 'exame' as tipo, id, tipo_exame as subtipo, 
                                    conclusoes_finais as descricao, data_exame as data, 
                                    laboratorio, anexos
                                    FROM exames 
                                    WHERE id_paciente = ? 
                                    ORDER BY data_exame DESC");
        $stmt_exam->execute([$id_paciente_selecionado]);
        $exames = $stmt_exam->fetchAll(PDO::FETCH_ASSOC);
        
        // 2.4. RECEITAS (verificar se tabela existe)
        $receitas = [];
        $check_receitas = $pdo->query("SHOW TABLES LIKE 'receitas'")->fetch();
        if ($check_receitas) {
            $stmt_rec = $pdo->prepare("SELECT 'receita' as tipo, id, 'Prescrição Médica' as subtipo, 
                                       conteudo as descricao, data_emissao as data
                                       FROM receitas 
                                       WHERE id_paciente = ? 
                                       ORDER BY data_emissao DESC");
            $stmt_rec->execute([$id_paciente_selecionado]);
            $receitas = $stmt_rec->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // 2.5. UNIFICAR TUDO E ORDENAR POR DATA
        $historico = array_merge($atendimentos, $patologias, $exames, $receitas);
        
        // Ordenar por data (mais recente primeiro)
        usort($historico, function($a, $b) {
            return strtotime($b['data']) - strtotime($a['data']);
        });
        
        // 2.6. BUSCAR DADOS DO PACIENTE SELECIONADO
        $stmt_pac = $pdo->prepare("SELECT p.*, c.nome as nome_tutor, c.cpf_cnpj, c.telefone, c.endereco, c.cidade, c.estado
                                   FROM pacientes p
                                   INNER JOIN clientes c ON p.id_cliente = c.id
                                   WHERE p.id = ?");
        $stmt_pac->execute([$id_paciente_selecionado]);
        $dados_paciente = $stmt_pac->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Erro ao buscar histórico: " . $e->getMessage());
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
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <style>
        :root { 
            --fundo: #ecf0f5; 
            --primaria: #1c329f; 
            --sucesso: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --borda: #d2d6de;
            --sidebar-collapsed: 75px; 
            --sidebar-expanded: 260px; 
            --header-height: 80px;
            --transition: 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body { 
            font-family: 'Open Sans', sans-serif; 
            background-color: var(--fundo); 
            font-size: 15px; 
            color: #333;
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        /* Layout Fixo */
        aside.sidebar-container { 
            position: fixed; left: 0; top: 0; height: 100vh; width: var(--sidebar-collapsed); 
            z-index: 1000; background: #fff; transition: width var(--transition); 
            box-shadow: 2px 0 5px rgba(0,0,0,0.05); 
        }
        aside.sidebar-container:hover { width: var(--sidebar-expanded); }
        
        header.top-header { 
            position: fixed; top: 0; left: var(--sidebar-collapsed); right: 0; 
            height: var(--header-height); z-index: 900; transition: left var(--transition); 
            margin: 0 !important;
        }
        aside.sidebar-container:hover ~ header.top-header { left: var(--sidebar-expanded); }
        
        main.main-content { 
            margin-left: var(--sidebar-collapsed); 
            padding: calc(var(--header-height) + 30px) 30px 40px; 
            transition: margin-left var(--transition); 
        }
        aside.sidebar-container:hover ~ main.main-content { margin-left: var(--sidebar-expanded); }
        
        .faixa-superior { width: 100% !important; margin: 0 !important; border-radius: 0 0 0 30px !important; }
        
        /* Card Principal */
        .card-historico { 
            background: #fff; 
            padding: 40px; 
            border-radius: 12px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.05); 
            border-top: 5px solid var(--primaria);
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .page-header h3 {
            font-size: 26px;
            font-weight: 700;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        /* Seletor de Paciente */
        .form-group { 
            display: flex; 
            flex-direction: column; 
            gap: 10px; 
            margin-bottom: 30px;
        }
        
        label { 
            font-size: 13px; 
            font-weight: 600; 
            color: #2c3e50; 
            text-transform: none;
            letter-spacing: 0.3px;
        }
        
        select { 
            padding: 14px 16px; 
            border: 1px solid var(--borda); 
            border-radius: 8px; 
            font-size: 15px; 
            outline: none; 
            background: #fff;
            transition: all 0.3s ease;
            font-family: 'Open Sans', sans-serif;
        }
        
        select:focus {
            border-color: var(--primaria);
            box-shadow: 0 0 0 3px rgba(28, 50, 159, 0.1);
        }
        
        .select2-container--default .select2-selection--single { 
            height: 50px; 
            display: flex; 
            align-items: center; 
            border: 1px solid var(--borda); 
            border-radius: 8px;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 50px;
            font-family: 'Open Sans', sans-serif;
        }
        
        /* Card de Informações do Paciente */
        .paciente-info {
            background: linear-gradient(135deg, var(--primaria) 0%, #0d1f66 100%);
            color: #fff;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 35px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .info-label {
            font-size: 12px;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-size: 16px;
            font-weight: 700;
        }
        
        /* Filtros */
        .filtros {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .btn-filtro {
            padding: 10px 20px;
            border: 2px solid #ddd;
            background: #fff;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-filtro:hover {
            border-color: var(--primaria);
            background: #f8f9ff;
        }
        
        .btn-filtro.active {
            background: var(--primaria);
            color: #fff;
            border-color: var(--primaria);
        }
        
        .btn-filtro .badge {
            background: rgba(0,0,0,0.1);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
        }
        
        .btn-filtro.active .badge {
            background: rgba(255,255,255,0.2);
        }
        
        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 50px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(180deg, var(--primaria) 0%, #ddd 100%);
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 30px;
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -38px;
            top: 8px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 3px solid #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .timeline-item[data-tipo="atendimento"]::before { background: var(--info); }
        .timeline-item[data-tipo="patologia"]::before { background: var(--danger); }
        .timeline-item[data-tipo="exame"]::before { background: var(--warning); }
        .timeline-item[data-tipo="receita"]::before { background: var(--sucesso); }
        
        .timeline-card {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .timeline-card:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transform: translateX(5px);
        }
        
        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .timeline-titulo {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .tipo-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .tipo-badge.atendimento { background: #e3f2fd; color: #0277bd; }
        .tipo-badge.patologia { background: #ffebee; color: #c62828; }
        .tipo-badge.exame { background: #fff3e0; color: #ef6c00; }
        .tipo-badge.receita { background: #e8f5e9; color: #2e7d32; }
        
        .timeline-subtitulo {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin: 0;
        }
        
        .timeline-data {
            font-size: 13px;
            color: #999;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .timeline-body {
            color: #555;
            line-height: 1.7;
            margin-top: 15px;
        }
        
        .timeline-body p {
            margin-bottom: 10px;
        }
        
        .timeline-resumo {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 15px;
            border-left: 4px solid var(--primaria);
        }
        
        .timeline-descricao {
            font-size: 14px;
        }
        
        .sem-historico {
            text-align: center;
            padding: 80px 20px;
            color: #999;
        }
        
        .sem-historico i {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .sem-historico h4 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #666;
        }
        
        .btn-imprimir {
            background: var(--primaria);
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(28, 50, 159, 0.2);
        }
        
        .btn-imprimir:hover {
            background: #15257a;
            transform: translateY(-2px);
        }
        
        @media print {
            aside, header, .filtros, .btn-imprimir, .page-header button { display: none !important; }
            main.main-content { margin-left: 0; padding: 20px; }
            .timeline { padding-left: 0; }
            .timeline::before { display: none; }
            .timeline-item::before { display: none; }
            .timeline-card { page-break-inside: avoid; margin-bottom: 20px; border: 1px solid #333; }
        }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include '../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../menu/faixa.php'; ?></header>

    <main class="main-content">
        <div class="card-historico">
            <div class="page-header">
                <h3>
                    <i class="fas fa-history" style="color: var(--primaria);"></i>
                    Histórico Completo do Paciente
                </h3>
                <?php if ($id_paciente_selecionado): ?>
                    <button class="btn-imprimir" onclick="window.print()">
                        <i class="fas fa-print"></i> Imprimir Histórico
                    </button>
                <?php endif; ?>
            </div>

            <div class="form-group" style="max-width: 600px;">
                <label>Selecione o Paciente / Tutor</label>
                <select id="select_paciente" onchange="if(this.value) window.location.href='consultas/historico.php?id_paciente=' + this.value">
                    <option value="">Pesquise por Tutor ou Pet...</option>
                    <?php foreach($lista_pacientes as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($id_paciente_selecionado == $p['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nome_tutor']) ?> - Pet: <?= htmlspecialchars($p['nome_paciente']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($id_paciente_selecionado && isset($dados_paciente)): ?>
                
                <!-- Informações do Paciente -->
                <div class="paciente-info">
                    <div class="info-item">
                        <div class="info-label">Paciente</div>
                        <div class="info-value"><?= htmlspecialchars($dados_paciente['nome_paciente']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Tutor</div>
                        <div class="info-value"><?= htmlspecialchars($dados_paciente['nome_tutor']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Espécie / Raça</div>
                        <div class="info-value"><?= htmlspecialchars($dados_paciente['especie'] ?? '-') ?> / <?= htmlspecialchars($dados_paciente['raca'] ?? '-') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Sexo</div>
                        <div class="info-value"><?= htmlspecialchars($dados_paciente['sexo'] ?? '-') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Contato</div>
                        <div class="info-value"><?= htmlspecialchars($dados_paciente['telefone'] ?? '-') ?></div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="filtros">
                    <button class="btn-filtro active" data-filtro="todos">
                        <i class="fas fa-check-circle"></i> Todos
                        <span class="badge"><?= count($historico) ?></span>
                    </button>
                    <button class="btn-filtro" data-filtro="atendimento">
                        <i class="fas fa-stethoscope"></i> Atendimentos
                        <span class="badge"><?= count(array_filter($historico, fn($h) => $h['tipo'] == 'atendimento')) ?></span>
                    </button>
                    <button class="btn-filtro" data-filtro="patologia">
                        <i class="fas fa-virus"></i> Patologias
                        <span class="badge"><?= count(array_filter($historico, fn($h) => $h['tipo'] == 'patologia')) ?></span>
                    </button>
                    <button class="btn-filtro" data-filtro="exame">
                        <i class="fas fa-microscope"></i> Exames
                        <span class="badge"><?= count(array_filter($historico, fn($h) => $h['tipo'] == 'exame')) ?></span>
                    </button>
                    <button class="btn-filtro" data-filtro="receita">
                        <i class="fas fa-prescription"></i> Receitas
                        <span class="badge"><?= count(array_filter($historico, fn($h) => $h['tipo'] == 'receita')) ?></span>
                    </button>
                </div>

                <!-- Timeline -->
                <?php if (!empty($historico)): ?>
                    <div class="timeline">
                        <?php foreach($historico as $item): ?>
                            <div class="timeline-item" data-tipo="<?= $item['tipo'] ?>">
                                <div class="timeline-card">
                                    <div class="timeline-header">
                                        <div class="timeline-titulo">
                                            <span class="tipo-badge <?= $item['tipo'] ?>">
                                                <?= ucfirst($item['tipo']) ?>
                                            </span>
                                            <h4 class="timeline-subtitulo"><?= htmlspecialchars($item['subtipo']) ?></h4>
                                        </div>
                                        <div class="timeline-data">
                                            <i class="far fa-calendar-alt"></i>
                                            <?= date('d/m/Y H:i', strtotime($item['data'])) ?>
                                        </div>
                                    </div>
                                    
                                    <div class="timeline-body">
                                        <?php if (isset($item['resumo']) && $item['resumo']): ?>
                                            <div class="timeline-resumo">
                                                <strong>Resumo:</strong> <?= htmlspecialchars($item['resumo']) ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($item['peso']) && $item['peso']): ?>
                                            <p><strong>Peso:</strong> <?= htmlspecialchars($item['peso']) ?> kg</p>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($item['laboratorio']) && $item['laboratorio']): ?>
                                            <p><strong>Laboratório:</strong> <?= htmlspecialchars($item['laboratorio']) ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($item['usuario_responsavel']) && $item['usuario_responsavel']): ?>
                                            <p><strong>Responsável:</strong> <?= htmlspecialchars($item['usuario_responsavel']) ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($item['descricao']) && $item['descricao']): ?>
                                            <div class="timeline-descricao">
                                                <?php 
                                                $desc = $item['descricao'];
                                                // Limitar tamanho se for muito grande
                                                if (strlen(strip_tags($desc)) > 500) {
                                                    $desc = substr(strip_tags($desc), 0, 500) . '...';
                                                    echo '<p>' . nl2br(htmlspecialchars($desc)) . '</p>';
                                                } else {
                                                    // Exibir HTML se já estiver formatado
                                                    echo $desc;
                                                }
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="sem-historico">
                        <i class="fas fa-clipboard-list"></i>
                        <h4>Nenhum registro encontrado</h4>
                        <p>Este paciente ainda não possui histórico de atendimentos, exames, patologias ou receitas.</p>
                    </div>
                <?php endif; ?>
                
            <?php elseif ($id_paciente_selecionado): ?>
                <div class="sem-historico">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h4>Paciente não encontrado</h4>
                    <p>O paciente selecionado não foi encontrado no sistema.</p>
                </div>
            <?php else: ?>
                <div class="sem-historico">
                    <i class="fas fa-hand-pointer"></i>
                    <h4>Selecione um paciente</h4>
                    <p>Escolha um paciente na lista acima para visualizar o histórico completo.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        $(document).ready(function() {
            // Inicializar Select2
            $('#select_paciente').select2({
                placeholder: "Pesquise por Tutor ou Pet...",
                width: '100%'
            });

            // Sistema de Filtros
            $('.btn-filtro').on('click', function() {
                const filtro = $(this).data('filtro');
                
                // Atualizar botões ativos
                $('.btn-filtro').removeClass('active');
                $(this).addClass('active');
                
                // Filtrar timeline
                if (filtro === 'todos') {
                    $('.timeline-item').fadeIn(300);
                } else {
                    $('.timeline-item').hide();
                    $(`.timeline-item[data-tipo="${filtro}"]`).fadeIn(300);
                }
            });
        });
    </script>
</body>
</html>