<?php
/**
 * =========================================================================================
 * ZIIPVET - SISTEMA DE GESTÃO VETERINÁRIA PROFISSIONAL
 * MÓDULO: EXAMES LABORATORIAIS DINÂMICOS
 * VERSÃO: 2.0.0 - ADIÇÃO DE HEMOGRAMA, OUTROS E PARASITOLÓGICO DE FEZES
 * =========================================================================================
 * ATENÇÃO: Este arquivo contém a estrutura completa para garantir a preservação de 
 * todas as lógicas de validação e seletores de pacientes.
 */

// 1. DEPENDÊNCIAS E SEGURANÇA DO NÚCLEO
// -----------------------------------------------------------------------------------------
require_once '../auth.php';
require_once '../config/configuracoes.php';

// Inicialização de Sessão para controle de alertas e feedbacks do sistema
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. CONFIGURAÇÕES DE AMBIENTE E VARIÁVEIS GLOBAIS
// -----------------------------------------------------------------------------------------
$titulo_pagina = "Novo Registro de Exame";
$usuario_logado = $_SESSION['usuario_nome'] ?? 'veterinário';

/**
 * 3. CARREGAMENTO DE DADOS DOS PACIENTES
 * Busca a relação completa de Pacientes vinculados aos seus respectivos Tutores.
 */
try {
    $sql_pac = "SELECT p.id as id_paciente, p.nome_paciente, c.nome as nome_cliente 
                FROM pacientes p 
                INNER JOIN clientes c ON p.id_cliente = c.id 
                ORDER BY c.nome ASC";
    $query_pacientes = $pdo->query($sql_pac);
    $lista_pacientes = $query_pacientes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $err) {
    error_log("Erro crítico em exames.php ao listar pacientes: " . $err->getMessage());
    $lista_pacientes = [];
}

/**
 * 4. PROCESSAMENTO DO FORMULÁRIO (POST)
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_paciente'])) {
    try {
        $id_paciente = $_POST['id_paciente'];
        $tipo_exame  = $_POST['tipo_exame'];
        $conclusoes  = $_POST['conclusoes']; // Conteúdo do editor Quill
        $usuario     = $_SESSION['usuario_nome'] ?? 'Veterinário';
        
        // 1. Processar Resultados Detalhados (Array 'res')
        // Geramos uma tabela HTML formatada para salvar na coluna resultados_detalhados
        $html_resultados = "<table border='1' style='width:100%; border-collapse: collapse;'>";
        $html_resultados .= "<tr style='background:#f4f4f4;'><th>Parâmetro</th><th>Resultado</th></tr>";
        
        $laboratorio_detectado = "";
        $data_exame_detectada = date('Y-m-d');

        if (isset($_POST['res']) && is_array($_POST['res'])) {
            foreach ($_POST['res'] as $chave => $valor) {
                if (!empty($valor)) {
                    // Captura o laboratório e a data se estiverem no array res
                    if (strpos($chave, 'lab_') !== false) $laboratorio_detectado = $valor;
                    if (strpos($chave, 'data_') !== false) $data_exame_detectada = $valor;

                    $label = ucwords(str_replace(['_', 'res['], ' ', $chave));
                    $html_resultados .= "<tr><td style='padding:5px;'>{$label}</td><td style='padding:5px;'>" . htmlspecialchars($valor) . "</td></tr>";
                }
            }
        }
        $html_resultados .= "</table>";

        // 2. Gestão de Múltiplos Anexos
        $arquivos_salvos = [];
        if (!empty($_FILES['anexos_exame']['name'][0])) {
            $diretorio = '../uploads/exames/';
            if (!is_dir($diretorio)) mkdir($diretorio, 0777, true);

            foreach ($_FILES['anexos_exame']['tmp_name'] as $key => $tmp_name) {
                if (!empty($tmp_name) && is_uploaded_file($tmp_name)) {
                    $extensao = pathinfo($_FILES['anexos_exame']['name'][$key], PATHINFO_EXTENSION);
                    $novo_nome = md5(uniqid()) . "." . $extensao;
                    
                    if (move_uploaded_file($tmp_name, $diretorio . $novo_nome)) {
                        $arquivos_salvos[] = 'uploads/exames/' . $novo_nome;
                    }
                }
            }
        }
        $anexos_string = implode(',', $arquivos_salvos);

        // 3. Inserção na tabela 'exames'
        $sql = "INSERT INTO exames (
                    id_paciente, 
                    tipo_exame, 
                    laboratorio, 
                    data_exame, 
                    resultados_detalhados, 
                    conclusoes_finais, 
                    anexos, 
                    usuario_responsavel,
                    assinado_digitalmente
                ) VALUES (
                    :paciente, 
                    :tipo, 
                    :lab, 
                    :dt_exame, 
                    :res_detalhe, 
                    :concl, 
                    :anexos, 
                    :usuario,
                    :assinado
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':paciente'    => $id_paciente,
            ':tipo'        => $tipo_exame,
            ':lab'         => $laboratorio_detectado,
            ':dt_exame'    => $data_exame_detectada,
            ':res_detalhe' => $html_resultados,
            ':concl'       => $conclusoes,
            ':anexos'      => $anexos_string,
            ':usuario'     => $usuario,
            ':assinado'    => isset($_POST['assinar_digital']) ? 1 : 0
        ]);

        $_SESSION['msg_sucesso'] = "Exame registrado com sucesso!";
        header("Location: exames.php?status=sucesso");
        exit;

    } catch (PDOException $e) {
        error_log("Erro ao processar exame: " . $e->getMessage());
        $_SESSION['msg_erro'] = "Erro ao salvar o exame: " . $e->getMessage();
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

    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
    /* ==========================================================
       CSS PADRONIZADO V16.2 - ZIIPVET (ESTRUTURA FIXA 17PX)
       ========================================================== */
    :root { 
        --fundo: #ecf0f5; 
        --primaria: #1c329f; 
        --danger: #dd4b39; 
        --sucesso: #28a745;
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

    /* Estrutura de Layout com Posicionamento Fixo (Correção da Faixa Superior) */
    aside.sidebar-container { 
        position: fixed; left: 0; top: 0; height: 100vh; 
        width: var(--sidebar-collapsed); z-index: 1000; 
        transition: width var(--transition); background: #fff; 
        box-shadow: 2px 0 5px rgba(0,0,0,0.05); 
    }
    aside.sidebar-container:hover { width: var(--sidebar-expanded); }
    
    header.top-header { 
        position: fixed; top: 0; left: var(--sidebar-collapsed); right: 0; 
        height: var(--header-height); z-index: 900; 
        transition: left var(--transition); margin: 0 !important; 
    }
    aside.sidebar-container:hover ~ header.top-header { left: var(--sidebar-expanded); }
    
    main.main-content { 
        margin-left: var(--sidebar-collapsed); 
        padding: calc(var(--header-height) + 30px) 25px 30px; 
        transition: margin-left var(--transition); 
    }
    aside.sidebar-container:hover ~ main.main-content { margin-left: var(--sidebar-expanded); }
    
    /* Garantia de largura total para o arquivo incluído faixa.php */
    .faixa-superior { width: 100% !important; margin: 0 !important; border-radius: 0 0 0 30px !important; }

    /* Estilização do Card de Exames */
    .card-exame { background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-top: 3px solid var(--borda); width: 100%; }
    .user-info { color: #999; font-size: 14px; margin-bottom: 12px; }

    .form-group { margin-bottom: 25px; display: flex; flex-direction: column; gap: 8px; }
    label { font-size: 14px; color: #666; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    
    input, select, textarea { 
        padding: 12px 15px; border: 1px solid var(--borda); 
        border-radius: 4px; font-size: 16px; width: 100%; 
        outline: none; transition: border-color 0.2s; background: #fff; 
    }
    input:focus, select:focus { border-color: var(--primaria); }

    /* Dinâmica de Exibição dos Blocos Específicos */
    .bloco-exame { display: none; margin-bottom: 30px; }
    .bloco-exame.active { display: block; animation: slideIn 0.3s ease-out; }
    @keyframes slideIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    /* Tabelas Laboratoriais (Ex: Bioquímico / Hemograma) */
    .resultado-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    .resultado-table th { text-align: left; padding: 12px 10px; font-size: 15px; border-bottom: 2px solid #eee; color: #333; }
    .resultado-table td { padding: 10px; border-bottom: 1px solid #f4f4f4; vertical-align: middle; }
    .resultado-table input { width: 110px; display: inline-block; padding: 8px; text-align: center; }
    .unit { font-size: 13px; color: #888; margin-left: 6px; width: 55px; display: inline-block; }
    .ref-val { font-size: 15px; color: #444; }

    /* Estilo de Linhas Alinhadas com Proporções dos Anexos */
    .row-aligned { display: flex; align-items: center; margin-bottom: 18px; gap: 20px; }
    .label-fixed { width: 220px; font-size: 15px; font-weight: bold; color: #333; flex-shrink: 0; }
    .input-lab { width: 450px !important; } /* Tamanho maior para Laboratório conforme anexo */
    .input-data { width: 180px !important; } /* Tamanho menor para Data conforme anexo */
    .input-flex { flex: 1; }

    /* Estilos específicos para Parasitológico e Hemoparasitos */
    .parasito-row { display: flex; align-items: center; margin-bottom: 12px; gap: 15px; }
    .parasito-label { width: 420px; font-size: 15px; color: #333; flex-shrink: 0; }
    .parasito-input { width: 280px; }
    .checkbox-row { display: flex; align-items: flex-start; gap: 12px; margin-top: 20px; margin-bottom: 20px; }
    .checkbox-row input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; flex-shrink: 0; margin-top: 2px; }
    .checkbox-row label { text-transform: none; font-weight: 400; font-size: 15px; line-height: 1.5; cursor: pointer; }

    /* Configurações do Editor Quill e Seções */
    .section-title { 
        font-size: 18px; font-weight: 700; color: #444; 
        margin: 35px 0 15px; border-bottom: 1px solid #eee; 
        padding-bottom: 8px; display: flex; align-items: center; gap: 10px; 
    }
    .resultado-header { font-weight: bold; font-size: 18px; margin: 20px 0 15px; text-align: left; width: 100%; border-bottom: 1px solid #eee; padding-bottom: 5px; }
    
    #editor-container { height: 320px; background: #fff; border: 1px solid var(--borda); border-radius: 0 0 4px 4px; }
    .ql-toolbar { background: #f9f9f9; border: 1px solid var(--borda) !important; border-bottom: none !important; border-radius: 4px 4px 0 0; }

    /* Rodapé e Botões de Ação */
    .footer-actions { display: flex; gap: 12px; margin-top: 50px; padding-top: 30px; border-top: 1px solid #eee; }
    .btn-ui { 
        padding: 14px 32px; border-radius: 4px; font-weight: 600; font-size: 14px; 
        cursor: pointer; border: none; text-transform: uppercase; 
        display: flex; align-items: center; gap: 10px; text-decoration: none; 
        transition: background 0.2s; 
    }
    .btn-save { background: var(--sucesso); color: #fff; }
    .btn-save:hover { background: #218838; }
    .btn-print { background: #f4f4f4; color: #333; border: 1px solid #ddd; }
    .btn-cancel { background: #f4f4f4; color: #777; margin-left: auto; }
    
    .req { color: var(--danger); }
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
        <div class="card-exame">
            <div class="user-info">Responsável Técnico: <?= htmlspecialchars($usuario_logado) ?></div>
            
            <?php if (isset($_SESSION['msg_erro'])): ?>
                <div style="padding: 15px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-triangle"></i> <?= $_SESSION['msg_erro']; unset($_SESSION['msg_erro']); ?>
                </div>
            <?php endif; ?>
            
            <form id="formExames" method="POST" enctype="multipart/form-data">
                
                <div style="display: grid; grid-template-columns: 1.6fr 1fr; gap: 20px; margin-bottom: 35px;">
                    <div class="form-group">
                        <label>Vincular ao Paciente / Tutor <span class="req">*</span></label>
                        <select name="id_paciente" id="select_pac_id" required>
                            <option value="">Selecione o paciente na lista...</option>
                            <?php foreach($lista_pacientes as $p): ?>
                                <option value="<?= $p['id_paciente'] ?>">
                                    <?= htmlspecialchars($p['nome_cliente']) ?> - <?= htmlspecialchars($p['nome_paciente']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Tipo de Exame <span class="req">*</span></label>
                        <select name="tipo_exame" id="tipo_exame" onchange="alternarBlocoExame(this.value)" required>
                            <option value="">Selecione o procedimento...</option>
                            <option value="aglutinacao">Aglutinação em Solução Salina</option>
                            <option value="liquidos">Análise de Líquidos Cavitários</option>
                            <option value="bioquimico">Bioquímico</option>
                            <option value="citologia">Citologia Aspirativa</option>
                            <option value="citologia_pele">Citologia de Pele</option>
                            <option value="citologia_vaginal">Citologia Vaginal</option>
                            <option value="contagem_reticulocitos">Contagem de Reticulócitos</option>
                            <option value="cultura_fungica">Cultura Fúngica</option>
                            <option value="ecocardiograma">Ecocardiograma</option>
                            <option value="eletrocardiograma">Eletrocardiograma</option>
                            <option value="hemograma">Hemograma</option>
                            <option value="outros">Outros</option>
                            <option value="parasitologico">Parasitológico de Fezes</option>
                            <option value="hemoparasitos">Pesquisa de Hemoparasitos</option>
                            <option value="radiografia">Radiografia</option>
                            <option value="raspado_cutaneo">Raspado Cutâneo</option>
                            <option value="sorologia_leishmania">Sorologia de Leishmania</option>
                            <option value="sorologia_cinomose">Sorologia para Cinomose</option>
                            <option value="sorologia_ehrlichia">Sorologia para Ehrlichia Canis</option>
                            <option value="sorologia_fiv_felv">Sorologia para FIV/FELV</option>
                            <option value="sorologia_parvovirose">Sorologia para Parvovirose</option>
                            <option value="ultrassonografia">Ultrassonografia</option>
                            <option value="urina">Urina (Urinálise)</option>
                        </select>
                    </div>
                </div>

                <div id="form-eletrocardiograma" class="bloco-exame">
                    <div class="resultado-header">Resultado</div>
                    
                    <div class="row-aligned">
                        <span class="label-fixed">Laboratório</span>
                        <input type="text" name="res[lab_eletro]" class="input-flex" style="max-width: 400px;">
                    </div>
                    
                    <div class="row-aligned">
                        <span class="label-fixed">Data</span>
                        <input type="date" name="res[data_eletro]" class="input-flex" style="max-width: 180px;" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div id="form-aglutinacao" class="bloco-exame">
                    <div class="section-title"><i class="fas fa-vial"></i> Resultado</div>
                    <div class="row-aligned">
                        <span class="label-fixed">Laboratório</span>
                        <input type="text" name="res[lab_aglut]" class="input-flex">
                    </div>
                    <div class="row-aligned">
                        <span class="label-fixed">Data</span>
                        <input type="date" name="res[data_aglut]" class="input-flex" style="max-width: 220px;" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div id="form-bioquimico" class="bloco-exame">
                    <table class="resultado-table">
                        <thead>
                            <tr>
                                <th style="width: 35%;">Parâmetro</th>
                                <th style="width: 25%; text-align: center;">Resultado</th>
                                <th>Referência (Canino)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $bio_fields = [
                                ["Ureia", "mg/dL", "21,4 - 59,92 mg/dL"], ["Creatinina", "mg/dL", "0,5 - 1,5 mg/dL"],
                                ["AST (TGO)", "U/l", "23,0 - 66,0 U/l"], ["ALT (TGP)", "U/l", "10 - 88 U/l"],
                                ["Fosfatase alcalina", "U/l", "20 - 156 U/l"], ["GGT", "U/l", "1,2 - 8,0 U/l"],
                                ["Proteínas totais", "g/dL", "5,4 - 7,1 g/dL"], ["Albumina", "g/dL", "2,6 - 3,3 g/dL"],
                                ["Globulinas", "", "2,7 - 4,4"], ["Relação Albumina/Globulina", "", "0,5 - 1,7"],
                                ["Colesterol", "mg/dL", "135 - 270 mg/dL"], ["Triglicérides", "mg/dL", "20 - 112 mg/dL"],
                                ["Glicose", "mg/dL", "70 - 110 mg/dL"], ["Frutosamina", "mol/L", "170 - 338 mol/L"],
                                ["Bilirrubinas Totais", "mg/dL", "0,1 - 0,6 mg/dL"], ["Bilirrubina direta", "mg/dL", "0,06 - 0,3 mg/dL"],
                                ["Bilirrubina indireta", "mg/dL", "0,01 - 0,5 mg/dL"], ["Cálcio", "mg/dL", "9,0 - 11,3 mg/dL"],
                                ["Fósforo", "mg/dL", "2,6 - 6,2 mg/dL"], ["Sódio", "mEq/L", "141,0 - 153,2 mEq/L"],
                                ["Potássio", "mEq/L", "3,7 - 5,8 mEq/L"], ["Magnésio", "mg/dL", "1,5 - 2,8 mg/dL"],
                                ["Amilase", "UI/L", "-"], ["Lipase", "UI/L", "-"]
                            ];
                            foreach($bio_fields as $f): ?>
                            <tr>
                                <td><span class="ref-val" style="font-weight:600;"><?= $f[0] ?></span></td>
                                <td style="text-align: center;">
                                    <input type="text" name="res[<?= strtolower(str_replace([' ', '/', '(', ')'], '_', $f[0])) ?>]">
                                    <span class="unit"><?= $f[1] ?></span>
                                </td>
                                <td><span class="ref-val"><?= $f[2] ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td style="font-weight:600;">Observações</td>
                                <td colspan="2"><input type="text" name="res[obs_bio]" style="width: 100%;"></td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;">Laboratório Externo <span class="req">*</span></td>
                                <td colspan="2"><input type="text" name="res[lab_bio]"></td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;">Data do Exame <span class="req">*</span></td>
                                <td colspan="2"><input type="date" name="res[data_bio]" style="width: 220px;"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="form-citologia" class="bloco-exame">
                    <div class="section-title"><i class="fas fa-search-plus"></i> Laudo Citológico</div>
                    <div class="form-group"><label>Amostra coletada</label><textarea name="res[amostra_cito]" rows="3"></textarea></div>
                    <div class="form-group"><label>Descrição Microscópica</label><textarea name="res[desc_cito]" rows="3"></textarea></div>
                    <div class="form-group"><label>Interpretação e Sugestão</label><textarea name="res[interp_cito]" rows="3"></textarea></div>
                    <div class="form-group"><label>Comentários</label><textarea name="res[coment_cito]" rows="3"></textarea></div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group"><label>Médico Solicitante</label><input type="text" name="res[solicitante_cito]"></div>
                        <div class="form-group"><label>Laboratório <span class="req">*</span></label><input type="text" name="res[lab_cito]"></div>
                    </div>
                    <div class="form-group"><label>Data da Análise</label><input type="date" name="res[data_cito]" style="max-width: 220px;"></div>
                </div>

                <div id="form-citologia_pele" class="bloco-exame">
                    <div class="section-title"><i class="fas fa-allergies"></i> Citologia Dermato</div>
                    <div class="row-aligned">
                        <span class="label-fixed">Laboratório</span>
                        <input type="text" name="res[lab_pele]" class="input-flex">
                    </div>
                    <div class="row-aligned">
                        <span class="label-fixed">Data</span>
                        <input type="date" name="res[data_pele]" class="input-flex" style="max-width: 220px;" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div id="form-citologia_vaginal" class="bloco-exame">
                    <div class="section-title"><i class="fas fa-venus"></i> Citologia Vaginal</div>
                    <div class="row-aligned">
                        <span class="label-fixed">Laboratório</span>
                        <input type="text" name="res[lab_vaginal]" class="input-flex">
                    </div>
                    <div class="row-aligned">
                        <span class="label-fixed">Data</span>
                        <input type="date" name="res[data_vaginal]" class="input-flex" style="max-width: 220px;" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div id="form-contagem_reticulocitos" class="bloco-exame">
                    <div class="section-title"><i class="fas fa-microscope"></i> Resultado</div>
                    <div class="row-aligned">
                        <span class="label-fixed">Laboratório</span>
                        <input type="text" name="res[lab_retic]" class="input-flex">
                    </div>
                    <div class="row-aligned">
                        <span class="label-fixed">Data</span>
                        <input type="date" name="res[data_retic]" class="input-flex" style="max-width: 220px;" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div id="form-cultura_fungica" class="bloco-exame">
                    <div class="section-title"><i class="fas fa-biohazard"></i> Resultado</div>
                    <div class="row-aligned">
                        <span class="label-fixed">Microsporum canis</span>
                        <input type="text" name="res[m_canis]" class="input-flex">
                    </div>
                    <div class="row-aligned">
                        <span class="label-fixed">Microsporum gypseum</span>
                        <input type="text" name="res[m_gypseum]" class="input-flex">
                    </div>
                    <div class="row-aligned">
                        <span class="label-fixed">Trichophyton mentagrophytes</span>
                        <input type="text" name="res[t_mentagrophytes]" class="input-flex">
                    </div>
                    <div class="row-aligned">
                        <span class="label-fixed">Cultura fúngica negativa</span>
                        <input type="text" name="res[cultura_negativa]" class="input-flex">
                    </div>
                    <div class="row-aligned">
                        <span class="label-fixed">Laboratório</span>
                        <input type="text" name="res[lab_fungica]" class="input-flex">
                    </div>
                    <div class="row-aligned">
                        <span class="label-fixed">Data</span>
                        <input type="date" name="res[data_fungica]" class="input-flex" style="max-width: 220px;" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div id="form-ecocardiograma" class="bloco-exame">
                    <div class="section-title"><i class="fas fa-heartbeat"></i> Resultado</div>
                    <div class="row-aligned">
                        <span class="label-fixed">Laboratório</span>
                        <input type="text" name="res[lab_eco]" class="input-flex">
                    </div>
                    <div class="row-aligned">
                        <span class="label-fixed">Data</span>
                        <input type="date" name="res[data_eco]" class="input-flex" style="max-width: 220px;" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div id="form-hemograma" class="bloco-exame">
                    <div class="resultado-header">Eritrograma</div>
                    <table class="resultado-table">
                        <thead>
                            <tr>
                                <th style="width: 30%;"></th>
                                <th style="width: 35%; text-align: center;">Resultado</th>
                                <th style="width: 35%;">Referência</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="ref-val" style="font-weight:600;">Hemácias</span></td>
                                <td style="text-align: center;">
                                    <input type="text" name="res[hemo_hemacias]" style="width: 120px;">
                                    <span class="unit">(milhões/mm3)</span>
                                </td>
                                <td><span class="ref-val">5,5 - 8,5 (milhões/mm3)</span></td>
                            </tr>
                            <tr>
                                <td><span class="ref-val" style="font-weight:600;">Volume globular</span></td>
                                <td style="text-align: center;">
                                    <input type="text" name="res[hemo_volume_glob]" style="width: 120px;">
                                    <span class="unit">%</span>
                                </td>
                                <td><span class="ref-val">37 - 55 %</span></td>
                            </tr>
                            <tr>
                                <td><span class="ref-val" style="font-weight:600;">Hemoglobina</span></td>
                                <td style="text-align: center;">
                                    <input type="text" name="res[hemo_hemoglobina]" style="width: 120px;">
                                    <span class="unit">g/dL</span>
                                </td>
                                <td><span class="ref-val">12.0 - 18.0 g/dL</span></td>
                            </tr>
                            <tr>
                                <td><span class="ref-val" style="font-weight:600;">VGM</span></td>
                                <td style="text-align: center;">
                                    <input type="text" name="res[hemo_vgm]" style="width: 120px;">
                                    <span class="unit">fL</span>
                                </td>
                                <td><span class="ref-val">60.0 - 77.0 fL</span></td>
                            </tr>
                            <tr>
                                <td><span class="ref-val" style="font-weight:600;">CHGM</span></td>
                                <td style="text-align: center;">
                                    <input type="text" name="res[hemo_chgm]" style="width: 120px;">
                                    <span class="unit">%</span>
                                </td>
                                <td><span class="ref-val">31 - 35 %</span></td>
                            </tr>
                            <tr>
                                <td><span class="ref-val" style="font-weight:600;">Plaquetas</span></td>
                                <td style="text-align: center;">
                                    <input type="text" name="res[hemo_plaquetas]" style="width: 120px;">
                                    <span class="unit">(mil/mm3)</span>
                                </td>
                                <td><span class="ref-val">166.000 - 575.000 (mil/mm3)</span></td>
                            </tr>
                            <tr>
                                <td><span class="ref-val" style="font-weight:600;">Proteínas totais</span></td>
                                <td style="text-align: center;">
                                    <input type="text" name="res[hemo_proteinas]" style="width: 120px;">
                                    <span class="unit">g/dL</span>
                                </td>
                                <td><span class="ref-val">6.0 - 8.0 g/dL</span></td>
                            </tr>
                            <tr>
                                <td><span class="ref-val" style="font-weight:600;">Hemácias nucleadas</span></td>
                                <td style="text-align: center;">
                                    <input type="text" name="res[hemo_hemacias_nuc]" style="width: 120px;">
                                </td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="resultado-header" style="margin-top: 35px;">Leucograma</div>
                    <table class="resultado-table">
                        <thead>
                            <tr>
                                <th style="width: 30%;"></th>
                                <th style="width: 35%; text-align: center;">Resultado</th>
                                <th style="width: 35%;">Referência</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="ref-val" style="font-weight:600;">Leucócitos</span></td>
                                <td style="text-align: center;">
                                    <input type="text" name="res[leuco_leucocitos]" style="width: 120px;">
                                    <span class="unit">(mil/mm3)</span>
                                </td>
                                <td><span class="ref-val">6.0 - 17.0 (mil/mm3)</span></td>
                            </tr>
                            <tr>
                                <td><span class="ref-val" style="font-weight:600;">Mielócitos</span></td>
                                <td style="text-align: center;">
                                    <input type="text" name="res[leuco_mielocitos]" style="width: 180px;">
                                </td>
                                <td><span class="ref-val">0 - 0%</span></td>
                            </tr>
                            <tr>
                                <td><span class="ref-val" style="font-weight:600;">Metamielócitos</span></td>
                                <td style="text-align: center;">
                                    <input type="text" name="res[leuco_metamielocitos]" style="width: 180px;">
                                </td>
                                <td><span class="ref-val">0 - 0%</span></td>
                            </tr>
                            <tr>
                                <td><span class="ref-val" style="font-weight:600;">Bastões</span></td>
                                <td style="text-align: center;">
                                    <input type="text" name="res[leuco_bastoes]" style="width: 180px;">
                                </td>
                                <td><span class="ref-val">0 - 3% / 0 - 300 mil/mm3</span></td>
                            </tr>
                            <tr>
                                <td><span class="ref-val" style="font-weight:600;">Segmentados</span></td>
                                <td style="text-align: center;">
                                    <input type="text" name="res[leuco_segmentados]" style="width: 180px;">
                                </td>
                                <td><span class="ref-val">60 - 77% / 3.000 - 11.500 mil/mm3</span></td>
                            </tr>
                            <tr>
                                <td><span class="ref-val" style="font-weight:600;">Linfócitos</span></td>
                                <td style="text-align: center;">
                                    <input type="text" name="res[leuco_linfocitos]" style="width: 180px;">
                                </td>
                                <td><span class="ref-val">12 - 30% / 1.000 - 4.800 mil/mm3</span></td>
                            </tr>
                            <tr>
                                <td><span class="ref-val" style="font-weight:600;">Monócitos</span></td>
                                <td style="text-align: center;">
                                    <input type="text" name="res[leuco_monocitos]" style="width: 180px;">
                                </td>
                                <td><span class="ref-val">3 - 10% / 150 - 1.350 mil/mm3</span></td>
                            </tr>
                            <tr>
                                <td><span class="ref-val" style="font-weight:600;">Eosinófilos</span></td>
                                <td style="text-align: center;">
                                    <input type="text" name="res[leuco_eosinofilos]" style="width: 180px;">
                                </td>
                                <td><span class="ref-val">2 - 10% / 100 - 1.250 mil/mm3</span></td>
                            </tr>
                            <tr>
                                <td><span class="ref-val" style="font-weight:600;">Basófilos</span></td>
                                <td style="text-align: center;">
                                    <input type="text" name="res[leuco_basofilos]" style="width: 180px;">
                                </td>
                                <td><span class="ref-val">/ raros</span></td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;">Observações</td>
                                <td colspan="2">
                                    <input type="text" name="res[hemo_obs]" style="width: 100%; max-width: 400px;">
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;">Laboratório<span class="req">*</span></td>
                                <td colspan="2">
                                    <input type="text" name="res[lab_hemo]" style="width: 100%; max-width: 450px;">
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;">Data<span class="req">*</span></td>
                                <td colspan="2">
                                    <input type="date" name="res[data_hemo]" style="width: 220px;" value="<?= date('Y-m-d') ?>">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div style="margin-top: 15px; font-size: 13px; color: #888;">Tabela de referência: Adulto</div>
                </div>

                <div id="form-outros" class="bloco-exame">
                    <div class="resultado-header">Resultado</div>
                    
                    <div class="form-group">
                        <label>Tipo</label>
                        <input type="text" name="res[tipo_outros]" style="max-width: 500px;">
                    </div>
                    
                    <div class="form-group">
                        <label>Laboratório</label>
                        <input type="text" name="res[lab_outros]" style="max-width: 500px;">
                    </div>
                    
                    <div class="form-group">
                        <label>Data</label>
                        <input type="date" name="res[data_outros]" style="max-width: 220px;" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div id="form-parasitologico" class="bloco-exame">
                    <div class="resultado-header">Resultado</div>
                    
                    <?php 
                    $parasitos = [
                        "Ancylostoma spp.",
                        "Dioctophyma renale",
                        "Toxascaris leonina",
                        "Dipillidium caninum",
                        "Taenia",
                        "Uncinaria stenocephala",
                        "Physaloptera",
                        "Spirocerca luppi",
                        "Diphyllobotrium",
                        "Aelurostrongylus abstrusus",
                        "Spirometra",
                        "Toxoplasma",
                        "Sarcocystis",
                        "Cryptosporidium",
                        "Toxocara canis",
                        "Toxocara cati",
                        "Hammondia hammondi",
                        "Trichuris sp",
                        "Giardia sp",
                        "Isospora sp",
                        "Capillaria spp."
                    ];
                    foreach($parasitos as $p): 
                        $field_name = strtolower(str_replace([' ', '.'], '_', $p));
                    ?>
                        <div class="parasito-row">
                            <span class="parasito-label"><?= $p ?></span>
                            <input type="text" name="res[parasito_<?= $field_name ?>]" class="parasito-input">
                        </div>
                    <?php endforeach; ?>

                    <div class="checkbox-row">
                        <input type="checkbox" name="res[parasito_negativo]" id="check_parasito_neg" value="1">
                        <label for="check_parasito_neg">
                            Amostra negativa para cistos ou oocistos de protozoários e ovos ou larvas de helmintos
                        </label>
                    </div>

                    <div class="form-group" style="margin-top: 25px;">
                        <label>Observações</label>
                        <textarea name="res[parasito_obs]" rows="3" style="max-width: 100%;"></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 25px;">
                        <div class="form-group">
                            <label>Laboratório<span class="req">*</span></label>
                            <input type="text" name="res[lab_parasito]">
                        </div>
                        <div class="form-group">
                            <label>Data<span class="req">*</span></label>
                            <input type="date" name="res[data_parasito]" style="max-width: 300px;" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                </div>

                <div id="form-liquidos" class="bloco-exame">
                    <div class="section-title"><i class="fas fa-tint"></i> Efusões</div>
                    <?php 
                    $liq_fields = ["Suspeita clínica", "Amostra", "Volume", "Cor", "Aspecto", "Coagulação", "Glicose", "Densidade", "PH", "Proteínas", "Contagem de hemácias", "Contagem de células nucleadas"];
                    foreach($liq_fields as $lf): ?>
                        <div class="row-aligned">
                            <span class="label-fixed"><?= $lf ?></span>
                            <input type="text" name="res[liq_<?= strtolower(str_replace(' ', '_', $lf)) ?>]" class="input-flex">
                        </div>
                    <?php endforeach; ?>
                    <div class="form-group"><label>Avaliação citológica detalhada</label><textarea name="res[liq_av_cito]" rows="4"></textarea></div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group"><label>Laboratório <span class="req">*</span></label><input type="text" name="res[lab_liq]"></div>
                        <div class="form-group"><label>Data</label><input type="date" name="res[data_liq]"></div>
                    </div>
                </div>
                
                <div id="form-hemoparasitos" class="bloco-exame">
    <div class="resultado-header">Resultado</div>
    
    <?php 
    $hemoparasitos = [
        "Ehrlichia spp.",
        "Babesia spp.",
        "Anaplasma spp.",
        "Hepatozoon spp.",
        "Rangellia vitalii",
        "Mycoplasma spp. (Hemobartonella)",
        "Trypanosoma spp.",
        "Leishmania spp. (Citologia)",
        "Microfilárias (Pesquisa direta)",
        "Microfilárias (Teste de Knott)"
    ];
    foreach($hemoparasitos as $h): 
        $field_name = strtolower(str_replace([' ', '.', '(', ')'], '_', $h));
    ?>
        <div class="parasito-row">
            <span class="parasito-label"><?= $h ?></span>
            <input type="text" name="res[hemo_para_<?= $field_name ?>]" class="parasito-input">
        </div>
    <?php endforeach; ?>

    <div class="checkbox-row">
        <input type="checkbox" name="res[hemo_para_negativo]" id="check_hemo_para_neg" value="1">
        <label for="check_hemo_para_neg">
            Amostra negativa para hemoparasitos na análise citológica da amostra enviada.
        </label>
    </div>

    <div class="form-group" style="margin-top: 25px;">
        <label>Observações</label>
        <textarea name="res[hemo_para_obs]" rows="3" style="max-width: 100%;"></textarea>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 25px;">
        <div class="form-group">
            <label>Laboratório<span class="req">*</span></label>
            <input type="text" name="res[lab_hemo_para]">
        </div>
        <div class="form-group">
            <label>Data<span class="req">*</span></label>
            <input type="date" name="res[data_hemo_para]" style="max-width: 300px;" value="<?= date('Y-m-d') ?>">
        </div>
    </div>
</div>
<div id="form-radiografia" class="bloco-exame">
    <div class="resultado-header">Resultado</div>
    
    <div class="row-aligned">
        <span class="label-fixed">Laboratório</span>
        <input type="text" name="res[lab_radio]" class="input-flex" style="max-width: 400px;">
    </div>
    
    <div class="row-aligned">
        <span class="label-fixed">Data</span>
        <input type="date" name="res[data_radio]" class="input-flex" style="max-width: 180px;" value="<?= date('Y-m-d') ?>">
    </div>
</div>
<div id="form-raspado_cutaneo" class="bloco-exame">
    <div class="resultado-header">Resultado</div>
    
    <?php 
    $raspado_itens = [
        "Demodex canis",
        "Sarcoptes scabiei",
        "Notoedres",
        "Otodetes cynotis",
        "Psoroptes",
        "Chorioptes",
        "Cheyletiella"
    ];
    foreach($raspado_itens as $item): 
        $field_id = strtolower(str_replace(' ', '_', $item));
    ?>
        <div class="parasito-row">
            <span class="parasito-label"><?= $item ?></span>
            <input type="text" name="res[raspado_<?= $field_id ?>]" class="parasito-input">
        </div>
    <?php endforeach; ?>

    <div class="row-aligned">
        <span class="parasito-label">Amostra negativa para ácaros</span>
        <input type="text" name="res[raspado_negativo]" class="parasito-input">
    </div>

    <div style="margin-top: 25px;">
        <div class="row-aligned">
            <span class="label-fixed">Laboratório<span class="req">*</span></span>
            <input type="text" name="res[lab_raspado]" class="input-flex" style="max-width: 400px;">
        </div>
        
        <div class="row-aligned">
            <span class="label-fixed">Data</span>
            <input type="date" name="res[data_raspado]" class="input-flex" style="max-width: 180px;" value="<?= date('Y-m-d') ?>">
        </div>
    </div>
</div>
<div id="form-sorologia_leishmania" class="bloco-exame">
    <div class="resultado-header">Resultado</div>
    
    <div class="row-aligned">
        <span class="label-fixed">Laboratório</span>
        <input type="text" name="res[lab_leish]" class="input-flex" style="max-width: 400px;">
    </div>
    
    <div class="row-aligned">
        <span class="label-fixed">Data</span>
        <input type="date" name="res[data_leish]" class="input-flex" style="max-width: 180px;" value="<?= date('Y-m-d') ?>">
    </div>
</div>
<div id="form-sorologia_cinomose" class="bloco-exame">
    <div class="resultado-header">Resultado</div>
    
    <div class="row-aligned">
        <span class="label-fixed">Laboratório</span>
        <input type="text" name="res[lab_cinomose]" class="input-flex" style="max-width: 400px;">
    </div>
    
    <div class="row-aligned">
        <span class="label-fixed">Data</span>
        <input type="date" name="res[data_cinomose]" class="input-flex" style="max-width: 180px;" value="<?= date('Y-m-d') ?>">
    </div>
</div>
<div id="form-sorologia_ehrlichia" class="bloco-exame">
    <div class="resultado-header">Resultado</div>
    
    <div class="row-aligned">
        <span class="label-fixed">Resultado</span>
        <input type="text" name="res[ehrlichia_resultado]" class="input-flex" style="max-width: 200px;">
    </div>

    <div class="row-aligned">
        <span class="label-fixed">Score</span>
        <input type="text" name="res[ehrlichia_score]" class="input-flex" style="max-width: 120px;">
    </div>

    <div class="row-aligned">
        <span class="label-fixed">Título</span>
        <input type="text" name="res[ehrlichia_titulo]" class="input-flex" style="max-width: 200px;">
    </div>
    
    <div class="row-aligned">
        <span class="label-fixed">Laboratório</span>
        <input type="text" name="res[lab_ehrlichia]" class="input-flex" style="max-width: 400px;">
    </div>
    
    <div class="row-aligned">
        <span class="label-fixed">Data</span>
        <input type="date" name="res[data_ehrlichia]" class="input-flex" style="max-width: 180px;" value="<?= date('Y-m-d') ?>">
    </div>
</div>
<div id="form-sorologia_fiv_felv" class="bloco-exame">
    <div class="resultado-header">Resultado</div>
    
    <div class="row-aligned">
        <span class="label-fixed">Laboratório</span>
        <input type="text" name="res[lab_fivfelv]" class="input-flex" style="max-width: 400px;">
    </div>
    
    <div class="row-aligned">
        <span class="label-fixed">Data</span>
        <input type="date" name="res[data_fivfelv]" class="input-flex" style="max-width: 180px;" value="<?= date('Y-m-d') ?>">
    </div>
</div>
<div id="form-sorologia_parvovirose" class="bloco-exame">
    <div class="resultado-header">Resultado</div>
    
    <div class="row-aligned">
        <span class="label-fixed">Laboratório</span>
        <input type="text" name="res[lab_parvo]" class="input-flex" style="max-width: 400px;">
    </div>
    
    <div class="row-aligned">
        <span class="label-fixed">Data</span>
        <input type="date" name="res[data_parvo]" class="input-flex" style="max-width: 180px;" value="<?= date('Y-m-d') ?>">
    </div>
</div>
<div id="form-ultrassonografia" class="bloco-exame">
    <div class="resultado-header">Resultado</div>
    
    <div class="row-aligned">
        <span class="label-fixed">Laboratório</span>
        <input type="text" name="res[lab_ultra]" class="input-flex" style="max-width: 400px;">
    </div>
    
    <div class="row-aligned">
        <span class="label-fixed">Data</span>
        <input type="date" name="res[data_ultra]" class="input-flex" style="max-width: 180px;" value="<?= date('Y-m-d') ?>">
    </div>
</div>
<div id="form-urina" class="bloco-exame">
    <div class="resultado-header">Resultado</div>

    <div style="background: #e9ecef; padding: 5px 10px; font-weight: bold; margin-bottom: 15px;">Fisico</div>
    <?php 
    $fisico = [
        ["Volume", "ml"], ["Colheita", ""], ["Aspecto", ""], 
        ["Cor", ""], ["Odor", ""], ["Densidade", ""], ["PH", ""]
    ];
    foreach($fisico as $f): ?>
        <div class="parasito-row">
            <span class="parasito-label"><?= $f[0] ?>*</span>
            <div class="parasito-input" style="display: flex; align-items: center; gap: 5px;">
                <input type="text" name="res[urina_fis_<?= strtolower($f[0]) ?>]" style="width: 100%;">
                <?php if($f[1]): ?><span class="unit" style="background: #eee; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"><?= $f[1] ?></span><?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div style="background: #e9ecef; padding: 5px 10px; font-weight: bold; margin: 20px 0 15px;">Quimico</div>
    <?php 
    $quimico = [
        ["Proteinas", "mg/dL"], ["Glicose", "mg%"], ["Corpos cetônicos", ""], 
        ["Bilirrubina", ""], ["Urobilinogenio", ""], ["Sangue oculto", ""], 
        ["Nitritos", ""], ["Sais biliares", ""]
    ];
    foreach($quimico as $q): ?>
        <div class="parasito-row">
            <span class="parasito-label"><?= $q[0] ?><?= $q[0] != 'Proteinas' ? '*' : '' ?></span>
            <div class="parasito-input" style="display: flex; align-items: center; gap: 5px;">
                <input type="text" name="res[urina_qui_<?= strtolower(str_replace(' ', '_', $q[0])) ?>]" style="width: 100%;">
                <?php if($q[1]): ?><span class="unit" style="background: #eee; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"><?= $q[1] ?></span><?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div style="background: #e9ecef; padding: 5px 10px; font-weight: bold; margin: 20px 0 15px;">Sedimentoscopia</div>
    <?php 
    $sedimento = [
        "Hemacias", "Leucócitos", "Cilindros", "Celulas descamativas", 
        "Cristais", "Células transicionais", "Bactérias", "Muco", "Células renais"
    ];
    foreach($sedimento as $s): ?>
        <div class="parasito-row">
            <span class="parasito-label"><?= $s ?>*</span>
            <input type="text" name="res[urina_sed_<?= strtolower(str_replace(' ', '_', $s)) ?>]" class="parasito-input">
        </div>
    <?php endforeach; ?>

    <div style="margin-top: 25px; border-top: 1px solid #eee; padding-top: 20px;">
        <div class="row-aligned">
            <span class="label-fixed">Laboratório</span>
            <input type="text" name="res[lab_urina]" class="input-flex" style="max-width: 400px;">
        </div>
        <div class="row-aligned">
            <span class="label-fixed">Observações</span>
            <input type="text" name="res[obs_urina]" class="input-flex" style="max-width: 400px;">
        </div>
        <div class="row-aligned">
            <span class="label-fixed">Data*</span>
            <input type="date" name="res[data_urina]" class="input-flex" style="max-width: 180px;" value="<?= date('Y-m-d') ?>">
        </div>
    </div>
</div>
                <div class="section-title"><i class="fas fa-pen-nib"></i> Conclusões Finais</div>
                <div id="editor-container"></div>
                <input type="hidden" name="conclusoes" id="conclusoes_hidden">

                <div style="margin-top: 20px; display: flex; align-items: center; gap: 12px; font-size: 15px;">
                    <input type="checkbox" name="assinar_digital" id="check_assinar" style="width: auto; cursor: pointer;"> 
                    <label for="check_assinar" style="text-transform: none; cursor: pointer; color: #444;">
                        Assinar digitalmente este laudo <i class="fas fa-shield-alt" style="color:var(--sucesso); margin-left:5px;"></i>
                    </label>
                </div>

                <div class="section-title"><i class="fas fa-paperclip"></i> Gestão de Anexos</div>
                <div style="padding: 25px; border: 2px dashed #eee; border-radius: 8px; text-align: center;">
                    <button type="button" class="btn-ui" style="background:#fdfdfd; color:#555; border:1px solid #ddd; width:auto; margin: 0 auto;" onclick="document.getElementById('in_anexos').click()">
                        <i class="fas fa-cloud-upload-alt"></i> Escolher Arquivos
                    </button>
                    <input type="file" name="anexos_exame[]" id="in_anexos" multiple style="display:none;" onchange="updateFileInfo(this)">
                    <p id="info_arq" style="margin-top: 12px; font-size: 14px; color: #999;">Nenhum arquivo anexado.</p>
                </div>

                <div class="footer-actions">
                    <button type="submit" class="btn-ui btn-save"><i class="fas fa-save"></i> Gravar Laudo</button>
                    <button type="button" class="btn-ui btn-print"><i class="fas fa-print"></i> Gerar Impressão</button>
                    <a href="consultas/listar_atendimentos.php" class="btn-ui btn-cancel">Cancelar</a>
                </div>

            </form>
        </div>
    </main>

    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script>
        // Verifica se há mensagem de sucesso na sessão e exibe
        <?php if (isset($_SESSION['msg_sucesso'])): ?>
    Swal.fire({
        icon: 'success',
        title: 'Sucesso!',
        text: '<?= $_SESSION['msg_sucesso']; ?>',
        confirmButtonColor: '#28a745',
        confirmButtonText: 'OK'
    }).then((result) => {
        if (result.isConfirmed) {
            // ESTA É A LINHA QUE FOI ALTERADA:
            window.location.href = 'consultas/listar_exames.php';
        }
    });
    <?php unset($_SESSION['msg_sucesso']); ?>
<?php endif; ?>
        
        // 1. Inicialização do Editor Rich Text (Quill)
        var quill = new Quill('#editor-container', {
            theme: 'snow',
            placeholder: 'Inicie aqui o laudo diagnóstico e conduta recomendada...',
            modules: { toolbar: [[{'header': [1, 2, false]}], ['bold', 'italic', 'underline', 'strike'], [{'list': 'ordered'}, {'list': 'bullet'}], ['link', 'clean']] }
        });

        /**
         * 2. Alterna entre os blocos de exames específicos via JS
         */
        function alternarBlocoExame(tipo) {
            document.querySelectorAll('.bloco-exame').forEach(div => div.classList.remove('active'));
            if (tipo) {
                const target = document.getElementById('form-' + tipo);
                if (target) {
                    target.classList.add('active');
                }
            }
        }

        /**
         * 3. Atualiza o status visual dos arquivos anexados no componente
         */
        function updateFileInfo(input) {
            const info = document.getElementById('info_arq');
            const count = input.files.length;
            if (count > 0) {
                info.innerHTML = "<i class='fas fa-check' style='color:var(--sucesso)'></i> " + count + (count === 1 ? " arquivo selecionado" : " arquivos selecionados");
                info.style.color = "#333";
            }
        }

        /**
         * 4. Sincroniza o conteúdo do Quill com o input hidden e valida obrigatórios
         */
        document.getElementById('formExames').onsubmit = function(e) {
            var htmlContent = quill.root.innerHTML;
            
            // Validação: Verifica se as conclusões foram preenchidas
            var textContent = quill.getText().trim();
            if (textContent.length === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Campo Obrigatório',
                    text: 'As conclusões finais do exame são obrigatórias.',
                    confirmButtonColor: '#28a745'
                });
                return false;
            }
            
            // Sincroniza o conteúdo do editor com o campo hidden
            document.getElementById('conclusoes_hidden').value = htmlContent;
            
            return true;
        };
    </script>
</body>
</html>