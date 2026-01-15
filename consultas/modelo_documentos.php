<?php
/**
 * =========================================================================================
 * ZIIPVET - MODELO DE DOCUMENTOS (LISTA COMPLETA)
 * ARQUIVO: modelo_documentos.php
 * VERSÃO: 1.4.0 - LAYOUT BLINDADO V16.2 (SEM SCROLL HORIZONTAL)
 * =========================================================================================
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. LÓGICA AJAX PARA BUSCAR DADOS DO ANIMAL
if (isset($_GET['ajax_dados_animal']) && isset($_GET['id_paciente'])) {
    header('Content-Type: application/json');
    $id = (int)$_GET['id_paciente'];
    try {
        $stmt = $pdo->prepare("SELECT p.*, c.nome as nome_tutor, c.cpf_cnpj as cpf_tutor, 
                                      c.endereco, c.numero, c.complemento, c.bairro, c.cidade as cidade_tutor, c.cep
                                FROM pacientes p 
                                INNER JOIN clientes c ON p.id_cliente = c.id 
                                WHERE p.id = ?");
        $stmt->execute([$id]);
        echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
    } catch (Exception $e) { echo json_encode(['erro' => true]); }
    exit;
}

$titulo_pagina = "Emissão de Documentos";
$usuario_logado = $_SESSION['usuario_nome'] ?? 'Veterinário';
$data_hoje_extenso = date('d') . " de " . date('F') . " de " . date('Y');
$cidade_unidade = "Goiânia"; 

// 2. CARREGAMENTO DA LISTA UNIFICADA (CLIENTE - PACIENTE)
try {
    $sql_pac = "SELECT p.id as id_paciente, p.nome_paciente, c.nome as nome_cliente 
                FROM pacientes p 
                INNER JOIN clientes c ON p.id_cliente = c.id 
                ORDER BY c.nome ASC";
    $lista_pacientes = $pdo->query($sql_pac)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $err) {
    error_log("Erro ao carregar pacientes: " . $err->getMessage());
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
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* ==========================================================
           CSS PADRONIZADO V16.2 - AJUSTE DE LARGURA (NO-OVERFLOW)
           ========================================================== */
        :root { 
            --fundo: #ecf0f5; --primaria: #1c329f; --sucesso: #28a745; 
            --borda: #d2d6de; --header-height: 80px; --sidebar-collapsed: 75px; 
            --sidebar-expanded: 260px;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body { 
            font-family: 'Source Sans Pro', sans-serif; 
            background-color: var(--fundo); 
            font-size: 17px; 
            color: #333;
            overflow-x: hidden; /* Impede a barra de rolagem horizontal */
        }

        /* Estrutura Fixo-Responsiva */
        aside.sidebar-container { 
            position: fixed; left: 0; top: 0; height: 100vh; 
            width: var(--sidebar-collapsed); z-index: 1000; 
            background: #fff; transition: width 0.4s; 
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
            width: calc(100% - var(--sidebar-collapsed)); /* Garante que caiba na tela */
        }
        aside.sidebar-container:hover ~ main.main-content { 
            margin-left: var(--sidebar-expanded);
            width: calc(100% - var(--sidebar-expanded));
        }

        .faixa-superior { width: 100% !important; margin: 0 !important; border-radius: 0 0 0 30px !important; }

        .card-doc { 
            background: #fff; padding: 30px; border-radius: 12px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-top: 3px solid var(--primaria); 
            width: 100%; max-width: 100%; 
        }
        
        .row-selecao { 
            display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 20px; 
            margin-bottom: 25px; background: #fcfcfc; padding: 20px; 
            border-radius: 8px; border: 1px solid #eee; 
        }
        
        label { font-size: 13px; font-weight: 700; color: #666; text-transform: uppercase; display: block; margin-bottom: 8px; }
        
        .select2-container--default .select2-selection--single { height: 48px; border: 1px solid var(--borda); display: flex; align-items: center; font-size: 16px; }
        
        #editor-container { height: 600px; background: #fff; border: 1px solid var(--borda); border-radius: 0 0 4px 4px; }
        .ql-toolbar { border-radius: 4px 4px 0 0; background: #f8f9fa; border-color: var(--borda) !important; }

        .footer-actions { display: flex; gap: 12px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
        .btn-ui { padding: 14px 25px; border-radius: 4px; font-weight: 600; cursor: pointer; border: none; display: flex; align-items: center; gap: 10px; text-decoration: none; text-transform: uppercase; font-size: 13px; }
        .btn-save { background: var(--sucesso); color: #fff; }
        .btn-print { background: #eee; color: #333; border: 1px solid #ddd; }

        @media print {
            .sidebar-container, .top-header, .footer-actions, .row-selecao, .ql-toolbar { display: none !important; }
            main.main-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            .card-doc { box-shadow: none; border: none; padding: 0; }
        }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include '../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../menu/faixa.php'; ?></header>

    <main class="main-content">
        <div class="card-doc">
            <div class="row-selecao">
                <div class="form-group">
                    <label>Vincular Cliente / Paciente *</label>
                    <select id="select_paciente_unificado" style="width: 100%;">
                        <option value="">Pesquise por Tutor ou Animal...</option>
                        <?php foreach($lista_pacientes as $p): ?>
                            <option value="<?= $p['id_paciente'] ?>">
                                <?= htmlspecialchars($p['nome_cliente']) ?> - <?= htmlspecialchars($p['nome_paciente']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Modelo do Documento</label>
                    <select id="modelo_documento" onchange="carregarModelo(this.value)" style="width: 100%; height: 48px; border-radius: 4px; border: 1px solid #d2d6de; padding: 0 10px;">
                        <option value="">Escolha um modelo...</option>
                        <option value="atestado_vacina">Atestado de aplicação de vacina</option>
                        <option value="atestado_obito">Atestado de óbito</option>
                        <option value="atestado_saude">Atestado de saúde</option>
                         <option value="atestado_vacina_historico">Atestado de Vacinação</option>
                         <option value="guia_transito">Guia de Trânsito</option>
                         <option value="receita_especial">Receituário de Controle Especial</option>
                         <option value="solicitacao_exame">Solicitação de Exame</option>
                        <option value="termo_autorizacao_exame">Termo de Autorização para Exame</option>
                        <option value="termo_internacao_cirurgia">Termo de Autorização para Internação e Tratamento Clínico Cirurgico</option>
                        <option value="termo_procedimento_cirurgico">Termo de Autorização para Procedimento Cirúrgico</option>
                        <option value="termo_anestesico">Termo de Autorização para Realização de Procedimentos Anestésicos</option>
                        <option value="termo_eutanasia">Termo de Consentimento para Realização de Eutanásia</option>
                        <option value="receita_simples">Receita Simples</option>
                        <option value="receita_especial">Receita de Controle Especial</option>
                        <option value="termo_eutanasia">Termo de Consentimento para Realização de Eutanásia</option>
                        <option value="termo_responsabilidade">Termo de responsabilidade</option>
                    </select>
                </div>
            </div>

            <div id="editor-container"></div>

            <div class="footer-actions">
                <button class="btn-ui btn-save" onclick="salvarDocumento()"><i class="fas fa-save"></i> Gravar Documento</button>
                <button class="btn-ui btn-print" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>
                <a href="consultas/listar_atendimentos.php" class="btn-ui btn-print" style="margin-left:auto; background: #f4f4f4; color: #777;"><i class="fas fa-times"></i> Cancelar</a>
            </div>
        </div>
    </main>

<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<script>
    var quill = new Quill('#editor-container', {
        theme: 'snow',
        modules: { toolbar: [[{ 'header': [1, 2, false] }], ['bold', 'italic', 'underline', 'strike'], [{ 'list': 'ordered'}, { 'list': 'bullet' }], [{ 'align': [] }], ['link', 'clean']] }
    });

    let dadosAnimal = null;

    $(document).ready(function() {
        $('#select_paciente_unificado').select2({ placeholder: "Pesquise por Tutor ou Animal...", allowClear: true });
        
        $('#select_paciente_unificado').on('change', function() {
            let id = $(this).val();
            if(id) {
                $.getJSON('consultas/modelo_documentos.php', { ajax_dados_animal: 1, id_paciente: id }, function(data) {
                    dadosAnimal = data;
                    let mod = $('#modelo_documento').val();
                    if(mod) carregarModelo(mod);
                });
            }
        });
    });

    const modelosBase = {
        atestado_vacina: `
            <p>Atesto para os devidos fins, que o animal abaixo identificado foi vacinado por mim nesta data, conforme informações abaixo:</p>
            <p><br></p>
            <p><strong>Identificação do animal:</strong></p>
            <p>Nome <strong>{NOME_ANIMAL}</strong>, espécie {ESPECIE}, raça {RACA}, {SEXO}, nascida em {NASCIMENTO}, pelagem cor "{PELAGEM}", de {PESO} Kg, Chip: {CHIP}.</p>
            <p><br></p>
            <p>Vacinação contra: .........................</p>
            <p>Nome comercial da vacina: .................</p>
            <p>Número da partida: .........................</p>
            <p>Fabricante: .........................</p>
            <p>Data de fabricação: ........................</p>
            <p>Data de validade: .........................</p>
            <p><br></p>
            <p><strong>Outras observações:</strong></p>
            <p><br></p>
            <p><strong>Identificação do(a) responsável pelo animal:</strong></p>
            <p>Nome: <strong>{NOME_TUTOR}</strong></p>
            <p>CPF: {CPF_TUTOR}</p>
            <p>Endereço Completo: Residente em {ENDERECO}, CEP: {CEP}</p>
            <p><br></p>
            <p style="text-align: right;"><?= $cidade_unidade ?>, <?= $data_hoje_extenso ?></p>
            <p><br></p>
            <p style="text-align: center;">______________________________________________</p>
            <p style="text-align: center;">Assinatura do(a) Médico(a) Veterinário(a)</p>
            <p style="text-align: center;"><strong><?= $usuario_logado ?></strong></p>
            <p><br></p>
            <p style="font-size: 12px; color: #666;">(documento a ser emitido em 2 vias: 1ª via: médico-veterinário; 2ª via: proprietário, tutor/responsável)</p>
        `,
       atestado_obito: `
    <p>Atesto para os devidos fins que o animal abaixo identificado veio a óbito na localidade ........................., às ........................., horas do dia ...................., sendo a provável causa mortis .........................</p>
    <p><br></p>
    <p><strong>Identificação do animal:</strong></p>
    <p>Nome <strong>{NOME_ANIMAL}</strong>, espécie {ESPECIE}, raça {RACA}, {SEXO}, nascida em {NASCIMENTO}, pelagem cor "{PELAGEM}", de {PESO} Kg.</p>
    <p><br></p>
    <p><strong>Outras informações complementares à provável causa mortis e informação de ter sido feita a notificação obrigatória quando for o caso:</strong></p>
    <p><br></p><p><br></p>
    <p><strong>Orientações para destinação do corpo animal (aspectos sanitários e ambientais):</strong></p>
    <p><br></p><p><br></p>
    <p><strong>Identificação do(a) responsável pelo animal:</strong></p>
    <p>Nome: <strong>{NOME_TUTOR}</strong></p>
    <p>CPF: {CPF_TUTOR}</p>
    <p>Endereço Completo: Residente em {ENDERECO}, CEP: {CEP}</p>
    <p><br></p>
    <p style="text-align: right;"><?= $cidade_unidade ?>, <?= $data_hoje_extenso ?></p>
    <p><br></p>
    <p style="text-align: center;">______________________________________________</p>
    <p style="text-align: center;">Assinatura do(a) Médico(a) Veterinário(a)</p>
    <p style="text-align: center;"><strong><?= $usuario_logado ?></strong></p>
    <p><br></p>
    <p style="font-size: 12px; color: #666;">(documento a ser emitido em 2 vias: 1ª via: médico-veterinário; 2ª via: proprietário, tutor/responsável)</p>
`,
        atestado_saude: `
    <p>Atesto para os devidos fins que examinei o animal da espécie {ESPECIE}, raça {RACA}, {SEXO}, nascida em {NASCIMENTO}, nome <strong>{NOME_ANIMAL}</strong>, pelagem cor "{PELAGEM}", de {PESO} Kg, apresentado sob responsabilidade do(a) Sr(a). <strong>{NOME_TUTOR}</strong>, RG {RG_TUTOR}, CPF {CPF_TUTOR}, residentes na {ENDERECO}, Cidade: {CIDADE_TUTOR}, Estado: {UF_TUTOR}, CEP: {CEP}.</p>
    <p><br></p>
    <p>O animal acima identificado encontra-se em bom estado clínico geral, sem sinais de doença infecto contagiosa ativa ou miíase, estando apto a embarcar, em perfeitas condições de saúde e apto a realizar viagem aérea.</p>
    <p><br></p>
    <p><strong>OBSERVAÇÃO:</strong></p>
    <p><br></p><p><br></p>
    <p style="text-align: right;"><?= $cidade_unidade ?>/GO, <?= $data_hoje_extenso ?></p>
    <p><br></p><p><br></p>
    <p style="text-align: center;"><strong><?= $usuario_logado ?></strong></p>
    <p style="text-align: center;">Médico(a) Veterinário(a)</p>
`,
      atestado_vacina_historico: `
    <p>Atesto que o animal acima descrito foi vacinado nas datas indicadas, tendo sido aplicadas as seguintes vacinas:</p>
    <p><br></p>
    <p><strong>Vacinas aplicadas</strong></p>
    <p>Nenhuma vacina aplicada</p>
    <p><br></p>
    <p><strong>Vacinas programadas</strong></p>
    <p>Nenhuma vacina programada</p>
    <p><br></p><p><br></p>
    <p style="text-align: right;"><?= $cidade_unidade ?>/GO, <?= $data_hoje_extenso ?>.</p>
    <p><br></p>
    <p style="text-align: center;">----------------------------------------------</p>
    <p style="text-align: center;"><strong><?= $usuario_logado ?></strong></p>
    <p style="text-align: center;">Auxiliar</p>
`,
     guia_transito: `
    <p style="text-align: center;"><strong style="font-size: 18px;">GUIA DE TRÂNSITO / ATESTADO DE SAÚDE PARA TRANSPORTE</strong></p>
    <p><br></p>
    <p><strong>Identificação do animal:</strong> {NOME_ANIMAL}, espécie {ESPECIE}, raça {RACA}, {SEXO}, nascida em {NASCIMENTO}, pelagem cor "{PELAGEM}".</p>
    <p><strong>Responsável:</strong> {NOME_TUTOR} (CPF: {CPF_TUTOR})</p>
    <p><br></p>
    <p style="text-align: center;"><strong>VACINAÇÃO ANTI-RÁBICA</strong></p>
    <table border="1" style="width: 100%; border-collapse: collapse;">
        <tr style="background-color: #f2f2f2; text-align: center; font-size: 14px;">
            <td style="padding: 8px; width: 40%;"><strong>Nome da Vacina e Fabricante</strong></td>
            <td style="padding: 8px; width: 20%;"><strong>Número do lote</strong></td>
            <td style="padding: 8px; width: 20%;"><strong>Data da vacinação</strong></td>
            <td style="padding: 8px; width: 20%;"><strong>Válida até</strong></td>
        </tr>
        <tr>
            <td style="padding: 20px;">&nbsp;</td>
            <td style="padding: 20px;">&nbsp;</td>
            <td style="padding: 20px;">&nbsp;</td>
            <td style="padding: 20px;">&nbsp;</td>
        </tr>
    </table>
    <p style="font-size: 13px; margin-top: 5px;">A vacinação anti-rábica é exigida para cães e gatos acima de 90 dias de idade e é válida por um ano.</p>
    <p style="font-size: 14px;"><strong>Anexar o cartão de vacinação do animal</strong></p>
    <p><br></p>
    <p>Declaro que o animal acima identificado foi por mim examinado e estava clinicamente sadio, isento de ectoparasitas à inspeção clínica e apto a ser transportado.</p>
    <p><br></p>
    <p style="text-align: right;"><?= $cidade_unidade ?>, <?= $data_hoje_extenso ?></p>
    <p><br></p>
    <p style="text-align: center;">______________________________________________</p>
    <p style="text-align: center;">Assinatura do(a) Médico(a) Veterinário(a)</p>
    <p style="text-align: center;"><strong><?= $usuario_logado ?></strong></p>
    <p><br></p>
    <p><strong>Este atestado é válido por 10 dias.</strong></p>
    <p style="font-size: 12px; color: #555;"><strong>Observação:</strong> Outros animais de companhia somente poderão ser transportados com a Guia de Trânsito Animal – GTA (Instrução Normativa n. 18 de 18/07/2006 do Ministério da Agricultura, Pecuária e Abastecimento, publicado no D.O.U. de 20/07/2006).</p>
`,
    receita_especial: `
    <p style="text-align: center;"><strong style="font-size: 18px;">RECEITUÁRIO DE CONTROLE ESPECIAL</strong></p>
    <p><br></p>
    <p><strong>DADOS DO EMITENTE:</strong></p>
    <p>Nome: <strong>{VET_LOGADO}</strong> &nbsp;&nbsp;&nbsp;&nbsp; CRMV: __________</p>
    <p>Endereço: Avenida Perimetral 2982, 62E, LOJA 12 - Setor Coimbra</p>
    <p>Cidade / Estado: Goiânia, GO</p>
    <p>Telefones: (62) 98563-4588 - (62) 3636-7999 - (62) 98606-9444</p>
    <p>Data de emissão: <?= date('d/m/Y') ?></p>
    <p><br></p>
    <p><strong>DADOS DO PROPRIETÁRIO E ANIMAL:</strong></p>
    <p>Nome do proprietário: <strong>{NOME_TUTOR}</strong></p>
    <p>CPF: {CPF_TUTOR}</p>
    <p>Endereço: {ENDERECO}</p>
    <p>Cidade/Estado: {CIDADE_TUTOR}, {UF_TUTOR}</p>
    <p>Nome do animal: <strong>{NOME_ANIMAL}</strong></p>
    <p>Espécie: {ESPECIE} &nbsp;&nbsp;&nbsp;&nbsp; Raça: {RACA}</p>
    <p>Sexo: {SEXO} &nbsp;&nbsp;&nbsp;&nbsp; Idade: {IDADE_ANIMAL}</p>
    <p><br></p>
    <p><strong>PRESCRIÇÃO:</strong></p>
    <p><br></p><p><br></p><p><br></p>
    <p style="text-align: center;">_________________________________<br><strong>{VET_LOGADO}</strong></p>
    <p><br></p>
    <p><hr></p>
    <p>Farmácia veterinária ( &nbsp; ) &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Farmácia Humana ( &nbsp; )</p>
    <table style="width: 100%; border: none;">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <p><strong>IDENTIFICAÇÃO DO COMPRADOR:</strong></p>
                <p>Nome: __________________________________</p>
                <p>RG: ___________________ Órg. Emissor: ______</p>
                <p>End.: ___________________________________</p>
                <p>Cidade: ______________________ UF: _______</p>
                <p>Telefone: ________________________________</p>
            </td>
            <td style="width: 50%; vertical-align: top;">
                <p><strong>IDENTIFICAÇÃO DO FORNECEDOR:</strong></p>
                <p><br></p><p><br></p><p><br></p>
                <p style="text-align: center;">_____________________________</p>
                <p style="text-align: center;">Assinatura do Farmacêutico DATA __/__/__</p>
            </td>
        </tr>
    </table>
    <p style="font-size: 11px; margin-top: 10px;">1ª via - Farmácia / 2ª via - Paciente</p>
`,
    solicitacao_exame: `
    <p style="text-align: center;"><strong style="font-size: 20px;">SOLICITAÇÃO DE EXAMES</strong></p>
    <p><br></p>
    <p><strong>PACIENTE:</strong> {NOME_ANIMAL} &nbsp;&nbsp;&nbsp;&nbsp; <strong>ESPÉCIE:</strong> {ESPECIE}</p>
    <p><strong>TUTOR:</strong> {NOME_TUTOR}</p>
    <p><hr></p>
    <p><br></p>
    <p>Para o animal acima descrito, solicito:</p>
    <p><br></p>
    <p>1. ................................................................................</p>
    <p>2. ................................................................................</p>
    <p>3. ................................................................................</p>
    <p><br></p><p><br></p>
    <p style="text-align: right;"><?= $cidade_unidade ?> / GO, <?= $data_hoje_extenso ?></p>
    <p><br></p>
    <p style="text-align: center;">______________________________________________</p>
    <p style="text-align: center;"><strong><?= $usuario_logado ?></strong></p>
    <p style="text-align: center;">Médico(a) Veterinário(a)</p>
`,
    termo_autorizacao_exame: `
    <p style="text-align: center;"><strong style="font-size: 18px;">TERMO DE AUTORIZAÇÃO PARA EXAME</strong></p>
    <p><br></p>
    <p>Autorizo a realização do(s) exame(s) ................................................................................ no animal de nome <strong>{NOME_ANIMAL}</strong>, espécie {ESPECIE}, raça {RACA}, sexo {SEXO}, idade (real ou aproximada) {IDADE_ANIMAL}, pelagem {PELAGEM}, a ser realizado pelo(a) Médico(a) Veterinário(a) ........................................ CRMV-..........</p>
    <p><br></p>
    <p><strong>IDENTIFICAÇÃO DO RESPONSÁVEL PELO ANIMAL:</strong></p>
    <ul style="list-style-type: none; padding-left: 0;">
        <li><strong>Nome:</strong> {NOME_TUTOR}</li>
        <li><strong>RG:</strong> {RG_TUTOR}</li>
        <li><strong>CPF:</strong> {CPF_TUTOR}</li>
        <li><strong>Endereço:</strong> {ENDERECO}</li>
        <li><strong>Telefone:</strong> {FONE_TUTOR}</li>
        <li><strong>Email:</strong> {EMAIL_TUTOR}</li>
    </ul>
    <p><br></p>
    <p>Declaro ter sido esclarecido acerca dos possíveis riscos inerentes, durante ou após a realização do(s) citado(s) exame(s), estando o referido profissional isento de quaisquer responsabilidades decorrentes de tais riscos.</p>
    <p><br></p>
    <p style="text-align: right;"><?= $cidade_unidade ?>, <?= $data_hoje_extenso ?></p>
    <p><br></p><p><br></p>
    <p style="text-align: center;">________________________________________</p>
    <p style="text-align: center;">Assinatura do responsável pelo animal</p>
`,
    termo_internacao_cirurgia: `
    <p style="text-align: center;"><strong style="font-size: 18px;">TERMO DE AUTORIZAÇÃO PARA INTERNAÇÃO E TRATAMENTO CLÍNICO CIRÚRGICO</strong></p>
    <p><br></p>
    <p>Autorizo a realização de internação e tratamento(s) necessário(s) ................................................................................ no animal de nome <strong>{NOME_ANIMAL}</strong>, espécie {ESPECIE}, raça {RACA}, sexo {SEXO}, idade (real ou aproximada) {IDADE_ANIMAL}, pelagem {PELAGEM}, a ser realizado pelo(a) Médico(a) Veterinário(a) ........................................ CRMV-..........</p>
    <p><br></p>
    <p><strong>IDENTIFICAÇÃO DO RESPONSÁVEL PELO ANIMAL:</strong></p>
    <ul style="list-style-type: none; padding-left: 0;">
        <li><strong>Nome:</strong> {NOME_TUTOR}</li>
        <li><strong>RG:</strong> {RG_TUTOR}</li>
        <li><strong>CPF:</strong> {CPF_TUTOR}</li>
        <li><strong>Endereço:</strong> {ENDERECO}</li>
        <li><strong>Telefone:</strong> {FONE_TUTOR}</li>
        <li><strong>Email:</strong> {EMAIL_TUTOR}</li>
    </ul>
    <p><br></p>
    <p>Declaro ter sido esclarecido acerca dos possíveis riscos inerentes à situação clínica do animal, bem como do(s) tratamento(s) proposto(s), estando o referido profissional isento de quaisquer responsabilidades decorrentes de tais riscos.</p>
    <p><br></p>
    <p><strong>OBSERVAÇÕES GERAIS (a serem fornecidas pelo proprietário/responsável):</strong> ....................................................................................................................................................................................................</p>
    <p><br></p>
    <p style="text-align: right;"><?= $cidade_unidade ?>, <?= $data_hoje_extenso ?></p>
    <p><br></p><p><br></p>
    <p style="text-align: center;">________________________________________</p>
    <p style="text-align: center;">Assinatura do responsável pelo animal</p>
`,
    termo_procedimento_cirurgico: `
    <p style="text-align: center;"><strong style="font-size: 18px;">TERMO DE AUTORIZAÇÃO PARA PROCEDIMENTO CIRÚRGICO</strong></p>
    <p><br></p>
    <p>Autorizo a realização do(s) procedimento(s) cirurgíco(s) ................................................................................ no animal de nome <strong>{NOME_ANIMAL}</strong>, espécie {ESPECIE}, raça {RACA}, sexo {SEXO}, idade (real ou aproximada) {IDADE_ANIMAL}, pelagem {PELAGEM}, a ser realizado pelo(a) Médico(a) Veterinário(a) ........................................ CRMV-..........</p>
    <p><br></p>
    <p><strong>IDENTIFICAÇÃO DO RESPONSÁVEL PELO ANIMAL:</strong></p>
    <ul style="list-style-type: none; padding-left: 0;">
        <li><strong>Nome:</strong> {NOME_TUTOR}</li>
        <li><strong>RG:</strong> {RG_TUTOR}</li>
        <li><strong>CPF:</strong> {CPF_TUTOR}</li>
        <li><strong>Endereço:</strong> {ENDERECO}</li>
        <li><strong>Telefone:</strong> {FONE_TUTOR}</li>
        <li><strong>Email:</strong> {EMAIL_TUTOR}</li>
    </ul>
    <p><br></p>
    <p>Declaro ter sido esclarecido acerca dos possíveis riscos inerentes, durante ou após a realização do procedimento cirúrgico citado, estando o referido profissional isento de quaisquer responsabilidades decorrentes de tais riscos.</p>
    <p><br></p>
    <p style="text-align: right;"><?= $cidade_unidade ?>, <?= $data_hoje_extenso ?></p>
    <p><br></p><p><br></p>
    <p style="text-align: center;">________________________________________</p>
    <p style="text-align: center;">Assinatura do responsável pelo animal</p>
`,
    termo_anestesico: `
    <p style="text-align: center;"><strong style="font-size: 18px;">TERMO DE AUTORIZAÇÃO PARA REALIZAÇÃO DE PROCEDIMENTOS ANESTÉSICOS</strong></p>
    <p><br></p>
    <p>Autorizo a realização do(s) procedimento(s) anestésico(s) necessário(s) ................................................................................ no animal de nome <strong>{NOME_ANIMAL}</strong>, espécie {ESPECIE}, raça {RACA}, sexo {SEXO}, idade (real ou aproximada) {IDADE_ANIMAL}, pelagem {PELAGEM}, a ser realizado pelo(a) Médico(a) Veterinário(a) ........................................ CRMV-..........</p>
    <p><br></p>
    <p><strong>IDENTIFICAÇÃO DO RESPONSÁVEL PELO ANIMAL:</strong></p>
    <ul style="list-style-type: none; padding-left: 0;">
        <li><strong>Nome:</strong> {NOME_TUTOR}</li>
        <li><strong>RG:</strong> {RG_TUTOR}</li>
        <li><strong>CPF:</strong> {CPF_TUTOR}</li>
        <li><strong>Endereço:</strong> {ENDERECO}</li>
        <li><strong>Telefone:</strong> {FONE_TUTOR}</li>
        <li><strong>Email:</strong> {EMAIL_TUTOR}</li>
    </ul>
    <p><br></p>
    <p>Declaro ter sido esclarecido acerca dos possíveis riscos, inerentes ao(s) procedimento(s) proposto(s), estando o referido profissional isento de quaisquer responsabilidades decorrentes de tais riscos.</p>
    <p><br></p>
    <p style="text-align: right;"><?= $cidade_unidade ?>, <?= $data_hoje_extenso ?></p>
    <p><br></p><p><br></p>
    <p style="text-align: center;">________________________________________</p>
    <p style="text-align: center;">Assinatura do responsável pelo animal</p>
`,
    termo_eutanasia: `
    <p style="text-align: center;"><strong style="font-size: 18px;">TERMO DE CONSENTIMENTO PARA REALIZAÇÃO DE EUTANÁSIA</strong></p>
    <p><br></p>
    <p>Declaro estar ciente dos motivos que levam à necessidade de realização da eutanásia, reconheço que esta é a opção escolhida por mim para cessar definitivamente o sofrimento e, portanto, autorizo a realização da eutanásia do animal de nome <strong>{NOME_ANIMAL}</strong>, espécie {ESPECIE}, raça {RACA}, sexo {SEXO}, idade (real ou aproximada) {IDADE_ANIMAL}, pelagem {PELAGEM}, a ser realizado pelo(a) Médico(a) Veterinário(a) ........................................ CRMV-..........</p>
    <p><br></p>
    <p><strong>IDENTIFICAÇÃO DO RESPONSÁVEL PELO ANIMAL:</strong></p>
    <ul style="list-style-type: none; padding-left: 0;">
        <li><strong>Nome:</strong> {NOME_TUTOR}</li>
        <li><strong>RG:</strong> {RG_TUTOR}</li>
        <li><strong>CPF:</strong> {CPF_TUTOR}</li>
        <li><strong>Endereço:</strong> {ENDERECO}</li>
        <li><strong>Telefone:</strong> {FONE_TUTOR}</li>
        <li><strong>Email:</strong> {EMAIL_TUTOR}</li>
    </ul>
    <p><br></p>
    <p>Declaro, ainda, que fui devidamente esclarecido(a) do método que será utilizado, assim como de que este é um processo irreversível.</p>
    <p><br></p>
    <p style="text-align: right;"><?= $cidade_unidade ?>, <?= $data_hoje_extenso ?></p>
    <p><br></p><p><br></p>
    <p style="text-align: center;">________________________________________</p>
    <p style="text-align: center;">Assinatura do responsável pelo animal</p>
`,
    };

    function carregarModelo(tipo) {
        if (!modelosBase[tipo]) { quill.setText(''); return; }
        let html = modelosBase[tipo];
        if (dadosAnimal) {
            html = html.replace(/{NOME_ANIMAL}/g, dadosAnimal.nome_paciente || '..........')
                       .replace(/{ESPECIE}/g, dadosAnimal.especie || '..........')
                       .replace(/{RACA}/g, dadosAnimal.raca || '..........')
                       .replace(/{SEXO}/g, dadosAnimal.sexo || '..........')
                       .replace(/{NASCIMENTO}/g, dadosAnimal.data_nascimento || '..........')
                       .replace(/{PELAGEM}/g, dadosAnimal.pelagem || '..........')
                       .replace(/{PESO}/g, dadosAnimal.peso || '...')
                       .replace(/{CHIP}/g, dadosAnimal.chip || '...')
                       .replace(/{NOME_TUTOR}/g, dadosAnimal.nome_tutor || '..........')
                       .replace(/{CPF_TUTOR}/g, dadosAnimal.cpf_tutor || '..........')
                       .replace(/{CEP}/g, dadosAnimal.cep || '..........')
                       .replace(/{VET_LOGADO}/g, '<?= $usuario_logado ?>')
                       .replace(/{ENDERECO}/g, (dadosAnimal.endereco ? (dadosAnimal.endereco + ', ' + dadosAnimal.numero + ' ' + (dadosAnimal.complemento || '') + ' - ' + dadosAnimal.bairro + ' - ' + dadosAnimal.cidade_tutor) : '..........'));
        } else {
            html = html.replace(/{.*?}/g, '..........');
        }
        quill.clipboard.dangerouslyPasteHTML(html);
    }

    function salvarDocumento() {
    // 1. Validações básicas
    const idPaciente = $('#select_paciente_unificado').val();
    const tipoDoc = $('#modelo_documento').val();
    const htmlContent = quill.root.innerHTML;

    if (!idPaciente) {
        Swal.fire('Atenção', 'Selecione um Cliente/Paciente primeiro.', 'warning');
        return;
    }
    if (!tipoDoc) {
        Swal.fire('Atenção', 'Selecione um modelo de documento.', 'warning');
        return;
    }
    if (quill.getText().trim().length === 0) {
        Swal.fire('Atenção', 'O conteúdo do documento está vazio.', 'warning');
        return;
    }

    // 2. Envio via AJAX
    Swal.fire({
        title: 'Salvando...',
        text: 'Aguarde enquanto registramos o documento.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    $.ajax({
        url: 'consultas/processar_documentos.php',
        type: 'POST',
        data: {
            id_paciente: idPaciente,
            tipo_documento: tipoDoc,
            conteudo_html: htmlContent
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Gravado!',
                    text: response.message,
                    confirmButtonColor: '#1c329f'
                }).then(() => {
                    // Opcional: Redirecionar para o histórico ou limpar a tela
                    // window.location.reload(); 
                });
            } else {
                Swal.fire('Erro', response.message, 'error');
            }
        },
        error: function() {
            Swal.fire('Erro Crítico', 'Não foi possível conectar ao servidor de processamento.', 'error');
        }
    });
}
</script>
</body>
</html>