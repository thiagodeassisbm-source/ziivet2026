<?php
/**
 * =========================================================================================
 * ZIIPVET - SISTEMA DE GESTÃO DE VACINAÇÃO PROFISSIONAL
 * ARQUIVO: vacinas.php
 * VERSÃO: 11.0.0 - EXPANSÃO MASSIVA DE PROTOCOLOS
 * MÓDULOS: HISTÓRICO AJAX, AGENDAMENTO INTELIGENTE, PROTOCOLOS DINÂMICOS
 * =========================================================================================
 */

require_once '../auth.php';
require_once '../config/configuracoes.php';

// Garantia de sessão ativa para segurança do sistema
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * -----------------------------------------------------------------------------------------
 * 1. MÓDULO AJAX: BUSCA DE HISTÓRICO E LEMBRETES
 * -----------------------------------------------------------------------------------------
 */
if (isset($_GET['ajax_historico']) && isset($_GET['id_paciente'])) {
    header('Content-Type: application/json');
    $id = (int)$_GET['id_paciente'];
    
    try {
        $stmt = $pdo->prepare("SELECT resumo, data_atendimento 
                                FROM atendimentos 
                                WHERE id_paciente = ? AND tipo_atendimento = 'Vacinação' 
                                ORDER BY data_atendimento DESC LIMIT 5");
        $stmt->execute([$id]);
        $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt2 = $pdo->prepare("SELECT vacina_nome, dose_prevista, data_prevista 
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

/**
 * -----------------------------------------------------------------------------------------
 * 2. PRÉ-PROCESSAMENTO: CARREGAMENTO DE DADOS EXTERNOS
 * -----------------------------------------------------------------------------------------
 */
$dados_preenchidos = null;
if (isset($_GET['id_paciente'])) {
    $id_p_get = (int)$_GET['id_paciente'];
    $stmt_pre = $pdo->prepare("SELECT * FROM lembretes_vacinas WHERE id_paciente = ? AND status = 'Pendente' ORDER BY id DESC LIMIT 1");
    $stmt_pre->execute([$id_p_get]);
    $dados_preenchidos = $stmt_pre->fetch(PDO::FETCH_ASSOC);
}

/**
 * -----------------------------------------------------------------------------------------
 * 3. PROCESSAMENTO DO FORMULÁRIO (MÉTODO POST)
 * -----------------------------------------------------------------------------------------
 */
$msg_sucesso = false;
$msg_erro = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_paciente'])) {
    try {
        $pdo->beginTransaction();

        $id_paciente     = (int)$_POST['id_paciente'];
        $vacina_nome     = $_POST['vacina_nome'];
        $vacina_dose     = $_POST['vacina_dose'];
        $data_aplicacao  = $_POST['data_aplicacao'];
        $protocolo_texto = $_POST['protocolo_texto'];
        $lote            = $_POST['vacina_lote'] ?? 'N/I';

        $descricao_final = "<strong>Imunizante:</strong> $vacina_nome <br>";
        $descricao_final .= "<strong>Dose:</strong> $vacina_dose <br>";
        $descricao_final .= "<strong>Lote/Fabricante:</strong> $lote <br>";
        $descricao_final .= "<strong>Protocolo Aplicado:</strong><br>" . nl2br(htmlspecialchars($protocolo_texto));

        // 3.1. Inserção no Prontuário
        $sql_at = "INSERT INTO atendimentos (id_paciente, tipo_atendimento, resumo, descricao, data_atendimento, status) 
                   VALUES (:p, 'Vacinação', :res, :des, :dat, 'Finalizado')";
        $st_at = $pdo->prepare($sql_at);
        $st_at->execute([
            ':p'   => $id_paciente,
            ':res' => "Vacina: $vacina_nome ($vacina_dose)",
            ':des' => $descricao_final,
            ':dat' => $data_aplicacao . ' ' . date('H:i:s')
        ]);
        $id_origem = $pdo->lastInsertId();

        // 3.2. Atualização de status de lembretes
        $stmt_upd = $pdo->prepare("UPDATE lembretes_vacinas SET status = 'Concluido' 
                                   WHERE id_paciente = ? AND vacina_nome = ? AND status = 'Pendente'");
        $stmt_upd->execute([$id_paciente, $vacina_nome]);

        // 3.3. Lógica de Agendamento Automático
        $prox_data = null; 
        $prox_dose = "";
        
        // Covenia: 14 dias
        if ($vacina_nome == 'Covenia (Antibiótico 14 dias)') {
            $prox_data = date('Y-m-d', strtotime($data_aplicacao . ' + 14 days'));
            $prox_dose = "Repetição (se necessário)";
        }
        // Cytopoint e Librela: 30 dias
        elseif (in_array($vacina_nome, ['Cytopoint (Anticorpo Monoclonal)', 'Librela (Bedinvetmab)'])) {
            $prox_data = date('Y-m-d', strtotime($data_aplicacao . ' + 30 days'));
            $prox_dose = ($vacina_nome == 'Cytopoint (Anticorpo Monoclonal)') ? "Reaplicação (4-8 semanas)" : "Reaplicação Mensal";
        }
        // Leish-Tec Inicial: não gera lembrete após 3ª dose
        elseif ($vacina_nome == 'Leish-Tec - Protocolo Inicial (3 doses)' && $vacina_dose == '3ª Dose') {
            $prox_data = null;
        }
        // V8 Especial: 4 doses com intervalo de 21 dias, depois anual
        elseif ($vacina_nome == 'V8 Especial (4 doses - 21 dias)') {
            if ($vacina_dose == '1ª Dose') {
                $prox_data = date('Y-m-d', strtotime($data_aplicacao . ' + 21 days'));
                $prox_dose = "2ª Dose";
            } elseif ($vacina_dose == '2ª Dose') {
                $prox_data = date('Y-m-d', strtotime($data_aplicacao . ' + 21 days'));
                $prox_dose = "3ª Dose";
            } elseif ($vacina_dose == '3ª Dose') {
                $prox_data = date('Y-m-d', strtotime($data_aplicacao . ' + 21 days'));
                $prox_dose = "4ª Dose";
            } else {
                $prox_data = date('Y-m-d', strtotime($data_aplicacao . ' + 365 days'));
                $prox_dose = "Reforço Anual";
            }
        }
        // Protocolos especiais que finalizam sem lembrete anual
        elseif (in_array($vacina_nome, [
            'Quádrupla Felina (V4) - Filhotes (3 doses)',
            'V10 (Filhotes - 3 doses 21 dias)',
            'V8 (Filhotes - 3 doses 30 dias)'
        ]) && $vacina_dose == '3ª Dose') {
            $prox_data = null; // Protocolo finalizado
        }
        else {
            // Define o intervalo de dias conforme a vacina
            if ($vacina_nome == 'Biocan (Esquema 14 dias)') {
                $intervalo_dias = 14;
            } elseif (in_array($vacina_nome, [
                'Giárdia (Esquema 30 dias - 3 doses)',
                'V8 (Filhotes - 3 doses 30 dias)'
            ])) {
                $intervalo_dias = 30;
            } else {
                $intervalo_dias = 21;
            }
            
            if ($vacina_dose == '1ª Dose') {
                $prox_data = date('Y-m-d', strtotime($data_aplicacao . " + {$intervalo_dias} days"));
                $prox_dose = "2ª Dose";
            } elseif ($vacina_dose == '2ª Dose') {
                // Protocolos de 2 doses que vão direto para anual
                if(in_array($vacina_nome, ['Giárdia (Anual - 2 doses)', 'Gripe Canina', 'Traqueobronquite (2 doses - 21 dias)'])) {
                    $prox_data = date('Y-m-d', strtotime($data_aplicacao . ' + 365 days'));
                    $prox_dose = "Reforço Anual";
                }
                // Adultos com 2 doses que vão direto para anual
                elseif(in_array($vacina_nome, ['Cinomose (V8/V10)', 'Cinomose e Parvovirose']) && isset($_POST['adulto_2doses']) && $_POST['adulto_2doses'] == '1') {
                    $prox_data = date('Y-m-d', strtotime($data_aplicacao . ' + 365 days'));
                    $prox_dose = "Reforço Anual";
                }
                else {
                    $prox_data = date('Y-m-d', strtotime($data_aplicacao . " + {$intervalo_dias} days"));
                    $prox_dose = "3ª Dose";
                }
            } else {
                // Reforços anuais e ciclos finalizados
                $prox_data = date('Y-m-d', strtotime($data_aplicacao . ' + 365 days'));
                $prox_dose = "Reforço Anual";
            }
        }

        // 3.4. Geração do novo Lembrete
        if ($prox_data) {
            $sql_le = "INSERT INTO lembretes_vacinas (id_paciente, id_atendimento_origem, vacina_nome, dose_prevista, data_prevista, status) 
                       VALUES (:p, :o, :v, :d, :dt, 'Pendente')";
            $st_le = $pdo->prepare($sql_le);
            $st_le->execute([
                ':p'  => $id_paciente, 
                ':o'  => $id_origem, 
                ':v'  => $vacina_nome, 
                ':d'  => $prox_dose, 
                ':dt' => $prox_data
            ]);
        }

        $pdo->commit();
        $msg_sucesso = true;
    } catch (Exception $e) { 
        $pdo->rollBack(); 
        $msg_erro = "Erro crítico ao processar vacinação: " . $e->getMessage(); 
    }
}

// Lista de pacientes
$sql_pacs = "SELECT p.id, p.nome_paciente, c.nome as nome_tutor FROM pacientes p 
              INNER JOIN clientes c ON p.id_cliente = c.id ORDER BY c.nome ASC";
$pacientes = $pdo->query($sql_pacs)->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = "Registro de Imunização";
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
        :root { 
            --fundo: #ecf0f5; 
            --primaria: #1c329f; 
            --sucesso: #28a745; 
            --borda: #d2d6de; 
            --header-height: 80px; 
            --sidebar-collapsed: 75px; 
            --sidebar-expanded: 260px; 
            --transition: 0.4s cubic-bezier(0.25, 0.8, 0.25, 1); 
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Open Sans', sans-serif; 
            background-color: var(--fundo); 
            color: #333; 
            min-height: 100vh; 
            font-size: 15px;
            line-height: 1.6;
        }
        aside.sidebar-container { 
            position: fixed; 
            left: 0; top: 0; 
            height: 100vh; 
            width: var(--sidebar-collapsed); 
            z-index: 1000; 
            background: #fff; 
            transition: width var(--transition); 
            box-shadow: 2px 0 10px rgba(0,0,0,0.05); 
        }
        aside.sidebar-container:hover { width: var(--sidebar-expanded); }
        header.top-header { 
            position: fixed; 
            top: 0; 
            left: var(--sidebar-collapsed); 
            right: 0; 
            height: var(--header-height); 
            z-index: 900; 
            transition: left var(--transition); 
        }
        aside.sidebar-container:hover ~ header.top-header { left: var(--sidebar-expanded); }
        main.main-content { 
            margin-left: var(--sidebar-collapsed); 
            padding: calc(var(--header-height) + 30px) 30px 40px; 
            transition: margin-left var(--transition); 
            max-width: 100%;
        }
        aside.sidebar-container:hover ~ main.main-content { margin-left: var(--sidebar-expanded); }
        .layout-grid { 
            display: grid; 
            grid-template-columns: 1fr 400px; 
            gap: 30px; 
            align-items: start; 
        }
        .card-vacina { 
            background: #fff; 
            padding: 45px; 
            border-radius: 12px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.05); 
            border-top: 5px solid var(--primaria); 
        }
        .card-vacina h3 {
            font-weight: 700;
            font-size: 22px;
            color: #2c3e50;
        }
        .form-row { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 25px; 
            margin-bottom: 5px;
        }
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
        select, input, textarea { 
            padding: 14px 16px; 
            border: 1px solid var(--borda); 
            border-radius: 8px; 
            font-size: 15px; 
            outline: none; 
            background: #fff;
            transition: all 0.3s ease;
            font-family: 'Open Sans', sans-serif;
            font-weight: 400;
        }
        select:focus, input:focus, textarea:focus {
            border-color: var(--primaria);
            box-shadow: 0 0 0 3px rgba(28, 50, 159, 0.1);
        }
        textarea { min-height: 160px; resize: vertical; }
        .side-info-box { 
            background: #fff; 
            border-radius: 12px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.04); 
            overflow: hidden; 
            margin-bottom: 25px; 
        }
        .side-header { 
            background: #f8f9fa; 
            padding: 18px 25px; 
            border-bottom: 1px solid #e9ecef; 
            font-weight: 700; 
            color: var(--primaria); 
            text-transform: uppercase; 
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: 0.5px;
        }
        .side-content { padding: 25px; }
        .list-item { 
            padding: 15px 0; 
            border-bottom: 1px dashed #eee; 
            font-size: 14px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .list-item:last-child { border-bottom: none; }
        .btn-save { 
            background: var(--sucesso); 
            color: #fff; 
            border: none; 
            padding: 18px 24px; 
            border-radius: 8px; 
            font-weight: 700; 
            cursor: pointer; 
            width: 100%; 
            text-transform: uppercase; 
            font-size: 15px; 
            transition: all 0.3s ease; 
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2);
            letter-spacing: 0.5px;
        }
        .btn-save:hover { 
            background: #218838; 
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(40, 167, 69, 0.3);
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
            font-size: 15px;
        }
        .faixa-superior { width: 100% !important; margin: 0 !important; border-radius: 0 0 0 30px !important; }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include '../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../menu/faixa.php'; ?></header>

    <main class="main-content">
        <div class="layout-grid">
            
            <div class="card-vacina">
                <h3 style="margin-bottom: 35px;">
                    <i class="fas fa-syringe" style="margin-right: 12px; color: var(--primaria);"></i> 
                    Registro de Imunização Profissional
                </h3>
                
                <form id="formVacina" method="POST">
                    
                    <div class="form-group">
                        <label>Vincular Cliente / Paciente *</label>
                        <select id="select_paciente" name="id_paciente" required style="width: 100%;">
                            <option value="">Selecione um animal cadastrado...</option>
                            <?php foreach($pacientes as $p): 
                                $sel = (isset($_GET['id_paciente']) && $_GET['id_paciente'] == $p['id']) ? 'selected' : '';
                            ?>
                                <option value="<?= $p['id'] ?>" <?= $sel ?>>
                                    <?= htmlspecialchars($p['nome_tutor']) ?> - Pet: <?= htmlspecialchars($p['nome_paciente']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Vacina Selecionada *</label>
                            <select id="sel_vacina" name="vacina_nome" required onchange="ajustarFormulario()">
                                <option value="">Escolha o imunizante...</option>
                                <option value="Antirrábica">Antirrábica</option>
                                <option value="Biocan (Esquema 14 dias)">Biocan (Esquema 14 dias)</option>
                                <option value="Cinomose (V8/V10)">Cinomose (V8/V10)</option>
                                <option value="Cinomose e Parvovirose">Cinomose e Parvovirose</option>
                                <option value="Covenia (Antibiótico 14 dias)">Covenia (Antibiótico 14 dias)</option>
                                <option value="Cytopoint (Anticorpo Monoclonal)">Cytopoint (Anticorpo Monoclonal)</option>
                                <option value="Giárdia (Anual - 2 doses)">Giárdia (Anual - 2 doses)</option>
                                <option value="Giárdia (Esquema 21 dias - 3 doses)">Giárdia (Esquema 21 dias - 3 doses)</option>
                                <option value="Giárdia (Esquema 30 dias - 3 doses)">Giárdia (Esquema 30 dias - 3 doses)</option>
                                <option value="Gripe Canina">Gripe Canina</option>
                                <option value="Leish-Tec (Leishmaniose)">Leish-Tec (Leishmaniose)</option>
                                <option value="Leish-Tec - Protocolo Inicial (3 doses)">Leish-Tec - Protocolo Inicial (3 doses)</option>
                                <option value="Leptospirose">Leptospirose</option>
                                <option value="Librela (Bedinvetmab)">Librela (Bedinvetmab)</option>
                                <option value="Parvovirose + Coronavirose">Parvovirose + Coronavirose</option>
                                <option value="Quádrupla Felina (V4)">Quádrupla Felina (V4)</option>
                                <option value="Quádrupla Felina (V4) - Filhotes (3 doses)">Quádrupla Felina (V4) - Filhotes (3 doses)</option>
                                <option value="Traqueobronquite (2 doses - 21 dias)">Traqueobronquite (2 doses - 21 dias)</option>
                                <option value="Tríplice Felina (V3)">Tríplice Felina (V3)</option>
                                <option value="V5 (Felina)">V5 (Felina)</option>
                                <option value="V8 (Anual)">V8 (Anual)</option>
                                <option value="V8 Especial (4 doses - 21 dias)">V8 Especial (4 doses - 21 dias)</option>
                                <option value="V8 (Filhotes - 3 doses 30 dias)">V8 (Filhotes - 3 doses 30 dias)</option>
                                <option value="V8 / V10 (Múltipla)">V8 / V10 (Múltipla)</option>
                                <option value="V10 (Anual)">V10 (Anual)</option>
                                <option value="V10 (Filhotes - 3 doses 21 dias)">V10 (Filhotes - 3 doses 21 dias)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Perfil do Paciente (Protocolo) *</label>
                            <select id="tipo_paciente" onchange="ajustarFormulario()">
                                <option value="Filhote">Filhote / Primovacinação</option>
                                <option value="Adulto Nunca Vacinado">Adulto (Sem histórico/Nunca vacinado)</option>
                                <option value="Adulto Já Vacinado">Adulto (Já vacinado / Manutenção)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row" style="grid-template-columns: 1fr 1fr 0.8fr;">
                        <div class="form-group">
                            <label>Dose Aplicada *</label>
                            <select name="vacina_dose" id="vacina_dose" required>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Data da Aplicação</label>
                            <input type="date" id="data_aplicacao" name="data_aplicacao" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label>Lote / Fabricante</label>
                            <input type="text" name="vacina_lote" placeholder="Ex: Zoetis L123">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Protocolo Aplicado & Observações Médicas</label>
                        <textarea id="txt_protocolo" name="protocolo_texto" placeholder="O protocolo será preenchido automaticamente ao selecionar a vacina..."></textarea>
                    </div>

                    <input type="hidden" id="adulto_2doses" name="adulto_2doses" value="">

                    <button type="submit" class="btn-save">
                        <i class="fas fa-save" style="margin-right: 10px;"></i> Finalizar Registro de Vacina
                    </button>
                </form>
            </div>

            <div class="side-panel">
                
                <div class="side-info-box">
                    <div class="side-header"><i class="fas fa-history"></i> Histórico Recente (5)</div>
                    <div class="side-content" id="lista_hist">
                        <p style="color:#999; font-size:14px; text-align: center; padding: 10px;">Aguardando seleção do animal...</p>
                    </div>
                </div>

                <div class="side-info-box">
                    <div class="side-header"><i class="fas fa-calendar-check"></i> Próximos Agendamentos</div>
                    <div class="side-content" id="lista_lemb">
                        <p style="color:#999; font-size:14px; text-align: center; padding: 10px;">Sem retornos previstos.</p>
                    </div>
                </div>

                <div style="background: #1c329f; color: #fff; padding: 25px; border-radius: 12px; font-size: 14px; line-height: 1.7;">
                    <h4 style="margin-bottom: 12px; font-weight: 700;"><i class="fas fa-info-circle"></i> Ajuda Rápida</h4>
                    <p style="font-weight: 400;">Ao salvar uma vacina, o sistema encerra o lembrete atual e gera automaticamente o próximo reforço com base no ciclo imunológico do laboratório.</p>
                </div>

            </div>
        </div>
    </main>

    <script>
        $(document).ready(function() {
            $('#select_paciente').select2({
                placeholder: "Pesquisar Tutor ou Nome do Pet..."
            });

            // Carregar dados do lembrete se vier da URL
            <?php if($dados_preenchidos): ?>
                // Dados do lembrete pendente
                const dadosLembrete = {
                    vacina: "<?= addslashes($dados_preenchidos['vacina_nome']) ?>",
                    dose: "<?= addslashes($dados_preenchidos['dose_prevista']) ?>",
                    data: "<?= $dados_preenchidos['data_prevista'] ?>"
                };
                
                console.log('Carregando lembrete pendente:', dadosLembrete);
                
                // Preencher vacina e disparar o evento change para popular as doses
                setTimeout(function() {
                    $('#sel_vacina').val(dadosLembrete.vacina);
                    
                    // Disparar ajustarFormulario manualmente
                    ajustarFormulario();
                    
                    // Aguardar as doses serem populadas e então selecionar a dose correta
                    setTimeout(function() {
                        $('#vacina_dose').val(dadosLembrete.dose);
                        
                        // Se a dose não existir nas opções, adicionar
                        if($('#vacina_dose option[value="' + dadosLembrete.dose + '"]').length === 0) {
                            $('#vacina_dose').append(new Option(dadosLembrete.dose, dadosLembrete.dose, true, true));
                        }
                        
                        console.log('Formulário preenchido com:', {
                            vacina: $('#sel_vacina').val(),
                            dose: $('#vacina_dose').val()
                        });
                    }, 300);
                }, 500);
            <?php endif; ?>

            // Aguardar Select2 renderizar completamente antes de buscar histórico
            setTimeout(function() {
                const urlPatient = $('#select_paciente').val();
                if(urlPatient) { 
                    buscarHistorico(urlPatient); 
                }
            }, 300);

            $('#select_paciente').on('select2:select', function(e) {
                buscarHistorico(e.params.data.id);
            });

            ajustarFormulario();
        });

        const configVacinas = {
            "V8 / V10 (Múltipla)": { 
                doses: ["1ª Dose", "2ª Dose", "3ª Dose", "Reforço Anual"], 
                protocolo: "Protocolo: 3 doses (intervalo de 21 a 30 dias) para filhotes ou cães nunca vacinados. Reforço anual obrigatório para manutenção de anticorpos." 
            },
            "Gripe Canina": { 
                doses: ["1ª Dose", "2ª Dose", "Reforço Anual"], 
                protocolo: "Protocolo: 2 doses na primovacinação (intervalo de 21 dias). Reforço anual em dose única." 
            },
            "Giárdia (Anual - 2 doses)": {
                "Filhote": {
                    doses: ["1ª Dose", "2ª Dose", "Reforço Anual"],
                    protocolo: "💉 PROTOCOLO VACINA GIÁRDIA – ANUAL (2 doses)\nA vacina contra Giárdia (Giardia lamblia / Giardia duodenalis) é aplicada como prevenção e como parte do tratamento de surtos em canis.\n\n✅ 1. Filhotes\n📌 2 doses iniciais\n• 1ª dose: a partir de 8 semanas\n• 2ª dose: 21 a 30 dias após a 1ª\n\n🔄 Reforço: 1 dose anual"
                },
                "Adulto Nunca Vacinado": {
                    doses: ["1ª Dose", "2ª Dose", "Reforço Anual"],
                    protocolo: "💉 PROTOCOLO VACINA GIÁRDIA – ANUAL (2 doses)\n\n✅ 2. Adultos nunca vacinados\n• 2 doses\n   • 1ª: dia 0\n   • 2ª: após 21 a 30 dias\n\n🔄 Reforço: 1 dose ao ano"
                },
                "Adulto Já Vacinado": {
                    doses: ["Reforço Anual"],
                    protocolo: "💉 PROTOCOLO VACINA GIÁRDIA – ANUAL (2 doses)\n\n🟩 3. Adultos já vacinados (em dia)\n• 1 dose anual como manutenção"
                }
            },
            "Giárdia (Esquema 21 dias - 3 doses)": {
                "Filhote": {
                    doses: ["1ª Dose", "2ª Dose", "3ª Dose", "Reforço Anual"],
                    protocolo: "💉 PROTOCOLO GIÁRDIA – Inicial 21 dias (3 doses)\n\n✅ 1. Filhotes (Esquema Inicial)\n📌 Total: 3 doses\n• 1ª dose: a partir de 8 semanas\n• 2ª dose: 21 dias após a 1ª\n• 3ª dose: 21 dias após a 2ª\n\n🔄 Reforço: 1 vez ao ano"
                },
                "Adulto Nunca Vacinado": {
                    doses: ["1ª Dose", "2ª Dose", "3ª Dose", "Reforço Anual"],
                    protocolo: "💉 PROTOCOLO GIÁRDIA – Inicial 21 dias (3 doses)\n\n✅ 2. Adultos nunca vacinados\n📌 3 doses:\n• 1ª dose: dia 0\n• 2ª dose: após 21 dias\n• 3ª dose: após mais 21 dias\n\n🔄 Reforço anual"
                },
                "Adulto Já Vacinado": {
                    doses: ["Reforço Anual"],
                    protocolo: "💉 PROTOCOLO GIÁRDIA – Inicial 21 dias (3 doses)\n\n🟩 3. Adultos já vacinados\n• 1 dose anual como reforço"
                }
            },
            "Giárdia (Esquema 30 dias - 3 doses)": {
                "Filhote": {
                    doses: ["1ª Dose", "2ª Dose", "3ª Dose", "Reforço Anual"],
                    protocolo: "💉 PROTOCOLO GIÁRDIA – Inicial 30 dias (3 doses)\n\n✅ 1. Filhotes\n• 1ª dose: a partir de 8 semanas\n• 2ª dose: 30 dias após a 1ª\n• 3ª dose: 30 dias após a 2ª\n\n🔄 Reforço: 1 dose anual"
                },
                "Adulto Nunca Vacinado": {
                    doses: ["1ª Dose", "2ª Dose", "3ª Dose", "Reforço Anual"],
                    protocolo: "💉 PROTOCOLO GIÁRDIA – Inicial 30 dias (3 doses)\n\n✅ 2. Adultos nunca vacinados\n📌 3 doses:\n• 1ª dose: dia 0\n• 2ª dose: após 30 dias\n• 3ª dose: após mais 30 dias\n\n🔄 Reforço anual"
                },
                "Adulto Já Vacinado": {
                    doses: ["Reforço Anual"],
                    protocolo: "💉 PROTOCOLO GIÁRDIA – Inicial 30 dias (3 doses)\n\n🟩 3. Adultos já vacinados\n• 1 dose anual de manutenção"
                }
            },
            "Leish-Tec (Leishmaniose)": {
                "Filhote": {
                    doses: ["1ª Dose", "2ª Dose", "3ª Dose", "Reforço Anual"],
                    protocolo: "💉 PROTOCOLO VACINA LEISH-TEC® – ANUAL\n\n✅ 1. Protocolo Inicial\n📌 3 doses (21 dias)\n• 1ª dose: a partir de 4 meses\n• 2ª dose: após 21 dias\n• 3ª dose: após 21 dias\n\n🟩 Reforço Anual: 1 dose ao ano\n\n⚠️ EXIGÊNCIA: Teste sorológico negativo (RIFI/ELISA/DPP)"
                },
                "Adulto Nunca Vacinado": {
                    doses: ["1ª Dose", "2ª Dose", "3ª Dose", "Reforço Anual"],
                    protocolo: "💉 PROTOCOLO VACINA LEISH-TEC® – ANUAL\n\n✅ 1. Protocolo Inicial\n📌 3 doses (21 dias)\n• 1ª dose: dia 0\n• 2ª dose: após 21 dias\n• 3ª dose: após 21 dias\n\n🟩 Reforço Anual: 1 dose ao ano\n\n⚠️ EXIGÊNCIA: Teste sorológico negativo"
                },
                "Adulto Já Vacinado": {
                    doses: ["Reforço Anual"],
                    protocolo: "💉 PROTOCOLO VACINA LEISH-TEC® – ANUAL\n\n🟩 Reforço Anual\n➡️ 1 dose ao ano\n\n⚠️ Manter teste sorológico em dia"
                }
            },
            "Leish-Tec - Protocolo Inicial (3 doses)": {
                doses: ["1ª Dose", "2ª Dose", "3ª Dose"],
                protocolo: "💉 PROTOCOLO LEISH-TEC® – INICIAL (3 DOSES)\n\n✅ Protocolo Inicial\n📌 3 doses (21 dias)\n• 1ª dose: Dia 0\n• 2ª dose: 21 dias após a 1ª\n• 3ª dose: 21 dias após a 2ª\n\n⚠️ Teste sorológico obrigatório (DPP/RIFI/ELISA)\n➡️ Somente cães NEGATIVOS"
            },
            "Leptospirose": {
                "Filhote": {
                    doses: ["1ª Dose", "2ª Dose", "3ª Dose", "Reforço Anual"],
                    protocolo: "💉 PROTOCOLO – LEPTOSPIROSE (ANUAL)\n\n✅ 1. Filhotes (protocolo inicial)\n2 ou 3 doses (V8, V10, V12):\n• 1ª dose: 6 a 8 semanas\n• 2ª dose: 21 a 30 dias depois\n• 3ª dose: 21 a 30 dias depois\n\n🔄 Reforço anual obrigatório"
                },
                "Adulto Nunca Vacinado": {
                    doses: ["1ª Dose", "2ª Dose", "Reforço Anual"],
                    protocolo: "💉 PROTOCOLO – LEPTOSPIROSE (ANUAL)\n\n✅ 2. Adultos nunca vacinados\n• 2 doses\n   • 1ª: dia 0\n   • 2ª: 21–30 dias depois\n\n🔄 Reforço anual",
                    adulto_2doses: true
                },
                "Adulto Já Vacinado": {
                    doses: ["Reforço Anual"],
                    protocolo: "💉 PROTOCOLO – LEPTOSPIROSE (ANUAL)\n\n🟩 3. Reforço ANUAL\n➡️ 1 dose ao ano\n\nO reforço anual é indispensável, pois a leptospira tem curta duração de imunidade."
                }
            },
            "Librela (Bedinvetmab)": {
                doses: ["Dose Inicial", "Reaplicação Mensal"],
                protocolo: "💊 PROTOCOLO LIBRELA® (Bedinvetmab)\nMedicamento injetável para controle de dor crônica por osteoartrite em cães. 👉 Não é vacina.\n\n💉 PROTOCOLO DE APLICAÇÃO\n✅ Dose: 0,5 a 1 mg/kg (SC)\n📅 Intervalo: 1 dose a cada 30 dias (mensal)\n\n🟩 Primeira aplicação\n• Efeito melhora em 3 a 7 dias\n• Controle ideal após 2–3 aplicações consecutivas"
            },
            "Parvovirose + Coronavirose": {
                "Filhote": {
                    doses: ["1ª Dose", "2ª Dose", "3ª Dose", "Reforço Anual"],
                    protocolo: "💉 PROTOCOLO – PARVOVIROSE + CORONAVIROSE (ANUAL)\n\n✅ 1. Filhotes (Protocolo inicial)\n📌 3 doses\n• 1ª dose: 6 a 8 semanas\n• 2ª dose: 21–30 dias depois (14 no Biocan)\n• 3ª dose: 21–30 dias depois da 2ª (14 no Biocan)\n\n🔄 Reforço ANUAL"
                },
                "Adulto Nunca Vacinado": {
                    doses: ["1ª Dose", "2ª Dose", "Reforço Anual"],
                    protocolo: "💉 PROTOCOLO – PARVOVIROSE + CORONAVIROSE (ANUAL)\n\n✅ 2. Adultos nunca vacinados\n📌 2 doses\n• 1ª dose: dia 0\n• 2ª dose: após 21–30 dias (14 no Biocan)\n\n🔄 Reforço ANUAL",
                    adulto_2doses: true
                },
                "Adulto Já Vacinado": {
                    doses: ["Reforço Anual"],
                    protocolo: "💉 PROTOCOLO – PARVOVIROSE + CORONAVIROSE (ANUAL)\n\n🟩 3. Reforço ANUAL\n➡️ 1 dose ao ano (V8, V10, V12 ou Biocan)\n\nProtege contra: Parvovirose, Coronavirose, Cinomose, Hepatite, Adenovirose, Parainfluenza, Leptospirose"
                }
            },
            "Quádrupla Felina (V4)": {
                "Filhote": {
                    doses: ["1ª Dose", "2ª Dose", "3ª Dose", "Reforço Anual"],
                    protocolo: "💉 PROTOCOLO – QUÁDRUPLA FELINA (V4)\nProtege contra: Panleucopenia, Rinotraqueíte (Herpesvírus), Calicivirose, Clamidiose\n\n✅ 1. Filhotes\n📌 3 doses\n• 1ª dose: 8 semanas\n• 2ª dose: 21–30 dias depois\n• 3ª dose: 21–30 dias depois da 2ª\n\n🔄 Reforço anual"
                },
                "Adulto Nunca Vacinado": {
                    doses: ["1ª Dose", "2ª Dose", "Reforço Anual"],
                    protocolo: "💉 PROTOCOLO – QUÁDRUPLA FELINA (V4)\n\n✅ 2. Adultos nunca vacinados\n📌 2 doses\n• 1ª dose: dia 0\n• 2ª dose: 21–30 dias depois\n\n🔄 Reforço anual",
                    adulto_2doses: true
                },
                "Adulto Já Vacinado": {
                    doses: ["Reforço Anual"],
                    protocolo: "💉 PROTOCOLO – QUÁDRUPLA FELINA (V4)\n\n🟩 3. Adultos já vacinados\n📌 1 dose anual"
                }
            },
            "Quádrupla Felina (V4) - Filhotes (3 doses)": {
                doses: ["1ª Dose", "2ª Dose", "3ª Dose"],
                protocolo: "💉 PROTOCOLO – QUÁDRUPLA FELINA (V4)\nProtocolo Inicial – Filhotes\n\nProtege contra: Panleucopenia, Rinotraqueíte, Calicivirose, Clamidiose\n\n✅ FILHOTES – PROTOCOLO INICIAL (3 DOSES)\n📌 Total: 3 doses\n📌 Intervalo: 21 a 30 dias\n\n➡️ 1ª dose: 8 semanas (2 meses)\n➡️ 2ª dose: 21–30 dias após a 1ª\n➡️ 3ª dose: 21–30 dias após a 2ª\n\n🔄 Reforço: 1 dose anual após completar as 3 doses."
            },
            "Traqueobronquite (2 doses - 21 dias)": {
                "Filhote": {
                    doses: ["1ª Dose", "2ª Dose", "Reforço Anual"],
                    protocolo: "💉 PROTOCOLO – TRAQUEOBRONQUITE (INICIAL 2 × 21 dias)\nVacina contra Bordetella bronchiseptica e Parainfluenza\n\n✅ 1. Filhotes – Protocolo Inicial\n📌 2 doses (21 dias)\n• 1ª dose: a partir de 8 semanas\n• 2ª dose: 21 dias após a 1ª\n\n🔄 Reforço: 1 dose anual"
                },
                "Adulto Nunca Vacinado": {
                    doses: ["1ª Dose", "2ª Dose", "Reforço Anual"],
                    protocolo: "💉 PROTOCOLO – TRAQUEOBRONQUITE (INICIAL 2 × 21 dias)\n\n✅ 2. Adultos nunca vacinados\n📌 2 doses\n• 1ª dose: dia 0\n• 2ª dose: 21 dias depois\n\n🔄 Reforço anual"
                },
                "Adulto Já Vacinado": {
                    doses: ["Reforço Anual"],
                    protocolo: "💉 PROTOCOLO – TRAQUEOBRONQUITE (INICIAL 2 × 21 dias)\n\n🟩 3. Adultos já vacinados\n• 1 dose anual\n\n(Alguns lugares reforçam a cada 6 meses para cães de hotelzinho/creche)"
                }
            },
            "Tríplice Felina (V3)": {
                "Filhote": {
                    doses: ["1ª Dose", "2ª Dose", "3ª Dose", "Reforço Anual"],
                    protocolo: "💉 PROTOCOLO – TRÍPLICE FELINA (V3) – ANUAL\nProtege contra: Panleucopenia, Rinotraqueíte (Herpesvírus), Calicivirose\n\n🐱 3. Filhotes (protocolo inicial)\n• 1ª dose: 8 semanas\n• 2ª dose: 12 semanas\n• 3ª dose: 16 semanas\n\n🔄 Reforço: 1 dose anual"
                },
                "Adulto Nunca Vacinado": {
                    doses: ["1ª Dose", "2ª Dose", "Reforço Anual"],
                    protocolo: "💉 PROTOCOLO – TRÍPLICE FELINA (V3) – ANUAL\n\n✅ 2. Gatos adultos nunca vacinados\n📌 2 doses no protocolo inicial\n• 1ª dose: dia 0\n• 2ª dose: 21 a 30 dias após a primeira\n\n🔄 Reforço: 1 dose anual",
                    adulto_2doses: true
                },
                "Adulto Já Vacinado": {
                    doses: ["Reforço Anual"],
                    protocolo: "💉 PROTOCOLO – TRÍPLICE FELINA (V3) – ANUAL\n\n✅ 1. Gatos adultos já vacinados (em dia)\n📌 1 dose anual"
                }
            },
            "V5 (Felina)": { 
                doses: ["1ª Dose", "2ª Dose", "3ª Dose", "Reforço Anual"], 
                protocolo: "Protocolo: 3 doses para filhotes a partir de 8 semanas. Reforço anual." 
            },
            "V8 (Anual)": {
                "Filhote": {
                    doses: ["1ª Dose", "2ª Dose", "3ª Dose", "Reforço Anual"],
                    protocolo: "💉 PROTOCOLO – V8 (Anual)\nProtege contra: Cinomose, Parvovirose, Adenovirose, Parainfluenza e 2 sorovares de Leptospirose\n\n🐶 3. Filhotes (esquema básico)\n• 1ª dose: 6 a 8 semanas\n• 2ª dose: 12 semanas\n• 3ª dose: 16 semanas\n\n🔄 Reforço anual"
                },
                "Adulto Nunca Vacinado": {
                    doses: ["1ª Dose", "2ª Dose", "Reforço Anual"],
                    protocolo: "💉 PROTOCOLO – V8 (Anual)\n\n✅ 2. Cães adultos nunca vacinados\n📌 2 doses no protocolo inicial\n• 1ª dose: dia 0\n• 2ª dose: 21 a 30 dias após a primeira\n\n🔄 Reforço: 1 dose anual",
                    adulto_2doses: true
                },
                "Adulto Já Vacinado": {
                    doses: ["Reforço Anual"],
                    protocolo: "💉 PROTOCOLO – V8 (Anual)\n\n✅ 1. Cães adultos já vacinados (em dia)\n📌 1 dose anual"
                }
            },
            "V8 Especial (4 doses - 21 dias)": {
                doses: ["1ª Dose", "2ª Dose", "3ª Dose", "4ª Dose", "Reforço Anual"],
                protocolo: "💉 PROTOCOLO – V8 Especial (4 × 21 dias)\nProtege contra: Cinomose, Parvovirose, Adenovirose, Parainfluenza e 2 sorovares de Leptospirose\n\n✅ PROTOCOLO INICIAL – 4 DOSES\n📌 Intervalo: sempre 21 dias\n\n• 1ª dose: a partir de 45 dias de vida\n• 2ª dose: 21 dias após a 1ª\n• 3ª dose: 21 dias após a 2ª\n• 4ª dose: 21 dias após a 3ª\n\n🔄 Reforço: 1 dose anual"
            },
            "V8 (Filhotes - 3 doses 30 dias)": {
                doses: ["1ª Dose", "2ª Dose", "3ª Dose"],
                protocolo: "💉 PROTOCOLO – V8 (Filhotes – 3 × 30 dias)\nProteção contra: Cinomose, Parvovirose, Adenovirose, Parainfluenza e 2 sorovares de Leptospirose\n\n✅ PROTOCOLO INICIAL – FILHOTES\n📌 Total: 3 doses\n📌 Intervalo: 30 dias\n\n• 1ª dose: a partir de 45 dias\n• 2ª dose: 30 dias após a 1ª\n• 3ª dose: 30 dias após a 2ª\n\n🔄 Reforço: 1 dose anual após completar as três doses."
            },
            "V10 (Anual)": {
                "Filhote": {
                    doses: ["1ª Dose", "2ª Dose", "3ª Dose", "Reforço Anual"],
                    protocolo: "💉 PROTOCOLO – V10 (Anual)\nProtege contra: Cinomose, Parvovirose, Adenovirose, Coronavirose, Parainfluenza e 4 sorovares de Leptospirose\n\n🐶 3. Filhotes\n• 1ª dose: 6 a 8 semanas\n• 2ª dose: 12 semanas\n• 3ª dose: 16 semanas\n\n🔄 Reforço: anual"
                },
                "Adulto Nunca Vacinado": {
                    doses: ["1ª Dose", "2ª Dose", "Reforço Anual"],
                    protocolo: "💉 PROTOCOLO – V10 (Anual)\n\n✅ 2. Cães adultos nunca vacinados\n📌 2 doses iniciais\n• 1ª dose: dia 0\n• 2ª dose: 21 a 30 dias depois\n\n🔄 Reforço: 1 dose anual",
                    adulto_2doses: true
                },
                "Adulto Já Vacinado": {
                    doses: ["Reforço Anual"],
                    protocolo: "💉 PROTOCOLO – V10 (Anual)\n\n✅ 1. Cães adultos já vacinados (em dia)\n📌 1 dose anual"
                }
            },
            "V10 (Filhotes - 3 doses 21 dias)": {
                doses: ["1ª Dose", "2ª Dose", "3ª Dose"],
                protocolo: "💉 PROTOCOLO – V10 (Filhotes – 3 × 21 dias)\nProteção contra: Cinomose, Parvovirose, Adenovirose, Coronavirose, Parainfluenza e 4 sorovares de Leptospirose\n\n✅ PROTOCOLO INICIAL – FILHOTES\n📌 Total: 3 doses\n📌 Intervalo fixo: 21 dias\n\n• 1ª dose: a partir de 45 dias de vida\n• 2ª dose: 21 dias após a 1ª\n• 3ª dose: 21 dias após a 2ª\n\n🔄 Reforço: 1 dose anual após completar as 3 doses.\n\n🟩 OBSERVAÇÕES\n• Protocolo muito usado em clínicas e campanhas\n• A dose anual mantém proteção contra leptospirose e demais agentes"
            },
            "Antirrábica": {
                "Filhote": { 
                    doses: ["Dose Única", "Reforço Anual"], 
                    protocolo: "PROTOCOLO VACINA ANTIRRÁBICA (Anual)\n✅ 1. Filhotes\n1ª dose: aos 3 meses (12 semanas)\nReforço: anual (1 vez por ano)" 
                },
                "Adulto Nunca Vacinado": { 
                    doses: ["Dose Única", "Reforço Anual"], 
                    protocolo: "PROTOCOLO VACINA ANTIRRÁBICA (Anual)\n✅ 2. Adultos nunca vacinados\n1 dose\nReforço: anual" 
                },
                "Adulto Já Vacinado": { 
                    doses: ["Reforço Anual"], 
                    protocolo: "PROTOCOLO VACINA ANTIRRÁBICA (Anual)\n✅ 3. Adultos já vacinados\nApenas 1 dose anual de manutenção" 
                }
            },
            "Biocan (Esquema 14 dias)": {
                "Filhote": { 
                    doses: ["1ª Dose", "2ª Dose", "3ª Dose", "Reforço Anual"], 
                    protocolo: "📌 PROTOCOLO BIOCAN (Esquema inicial 14 dias)\n✅ 1. Filhotes\n• 1ª dose: a partir de 6 semanas (45 dias)\n• 2ª dose: 14 dias após a 1ª\n• 3ª dose: 14 dias após a 2ª\nReforço anual obrigatório (1 dose por ano)." 
                },
                "Adulto Nunca Vacinado": { 
                    doses: ["1ª Dose", "2ª Dose", "3ª Dose", "Reforço Anual"], 
                    protocolo: "📌 PROTOCOLO BIOCAN (Esquema inicial 14 dias)\n✅ 2. Adultos nunca vacinados\n• 1ª dose agora\n• 2ª dose: após 14 dias\n• 3ª dose: após 14 dias\nReforço anual." 
                },
                "Adulto Já Vacinado": { 
                    doses: ["Reforço Anual"], 
                    protocolo: "📌 PROTOCOLO BIOCAN (Esquema inicial 14 dias)\n✅ 3. Adultos já vacinados (em dia)\n• 1 dose anual de reforço." 
                }
            },
            "Cinomose (V8/V10)": {
                "Filhote": { 
                    doses: ["1ª Dose", "2ª Dose", "3ª Dose", "Reforço Anual"], 
                    protocolo: "📌 PROTOCOLO CINOMOSE (Reforço anual)\n⚠️ A vacina de cinomose está incluída nas vacinas múltiplas (V8, V10, V12, Biocan)\n\n✅ 1. Filhotes\n• 1ª dose: a partir de 6 a 8 semanas\n• 2ª dose: 21 a 30 dias depois (ou 14 dias no BIOCAN)\n• 3ª dose: 21 a 30 dias depois da 2ª (ou 14 dias no BIOCAN)\n\n🟩 Reforço ANUAL: 1 dose por ano",
                    adulto_2doses: false
                },
                "Adulto Nunca Vacinado": { 
                    doses: ["1ª Dose", "2ª Dose", "Reforço Anual"], 
                    protocolo: "📌 PROTOCOLO CINOMOSE (Reforço anual)\n\n✅ 2. Adultos nunca vacinados\n• 1ª dose: dia 0\n• 2ª dose: após 21–30 dias (ou 14 dias no BIOCAN)\n\n🟩 Reforço ANUAL: 1 dose por ano",
                    adulto_2doses: true
                },
                "Adulto Já Vacinado": { 
                    doses: ["Reforço Anual"], 
                    protocolo: "📌 PROTOCOLO CINOMOSE (Reforço anual)\n\n✅ 3. Adultos já vacinados (em dia)\n• 1 dose anual de reforço",
                    adulto_2doses: false
                }
            },
            "Cinomose e Parvovirose": {
                "Filhote": { 
                    doses: ["1ª Dose", "2ª Dose", "3ª Dose", "Reforço Anual"], 
                    protocolo: "💉 PROTOCOLO CINOMOSE E PARVOVIROSE (Anual)\n⚠️ Incluídas nas vacinas múltiplas: V8, V10, V12, Biocan V8 etc.\n\n✅ 1. Filhotes (protocolo inicial)\n3 doses:\n• 1ª dose: 6 a 8 semanas\n• 2ª dose: 21 a 30 dias depois (ou 14 dias BIOCAN)\n• 3ª dose: 21 a 30 dias depois da 2ª (ou 14 dias BIOCAN)\n\n🟩 Reforço ANUAL: 1 dose ao ano",
                    adulto_2doses: false
                },
                "Adulto Nunca Vacinado": { 
                    doses: ["1ª Dose", "2ª Dose", "Reforço Anual"], 
                    protocolo: "💉 PROTOCOLO CINOMOSE E PARVOVIROSE (Anual)\n\n✅ 2. Adultos nunca vacinados\n• 1ª dose: dia 0\n• 2ª dose: 21–30 dias depois (ou 14 dias BIOCAN)\n\n🟩 Reforço ANUAL: 1 dose ao ano",
                    adulto_2doses: true
                },
                "Adulto Já Vacinado": { 
                    doses: ["Reforço Anual"], 
                    protocolo: "💉 PROTOCOLO CINOMOSE E PARVOVIROSE (Anual)\n\n✅ 3. Adultos já vacinados (em dia)\n• 1 dose anual de reforço\n\n🟩 Reforço ANUAL mantém proteção contínua",
                    adulto_2doses: false
                }
            },
            "Covenia (Antibiótico 14 dias)": {
                doses: ["Dose Única", "Repetição (se necessário)"],
                protocolo: "💊 PROTOCOLO DE COVENIA (Cefovecina)\n\n✅ Indicação: Infecções de pele, tecido subcutâneo, infecções urinárias (gatos)\n\n💉 APLICAÇÃO:\n✔ Dose única: 8 mg/kg (SC)\n✔ Duração: até 14 dias\n✔ Pode repetir após 14 dias se necessário"
            },
            "Cytopoint (Anticorpo Monoclonal)": {
                doses: ["Dose Inicial", "Reaplicação (4-8 semanas)"],
                protocolo: "🧬 PROTOCOLO DE CYTOPOINT (Lokivetmab)\n\n✅ Anticorpo monoclonal para prurido/coceira por dermatite atópica\n\n💉 APLICAÇÃO:\n✔ Dose: 2 mg/kg (SC)\n✔ Intervalo: 4 a 8 semanas (média mensal)\n\n🟩 Uso inicial: 1 dose → Avalia resposta → Define intervalo"
            }
        };

        function ajustarFormulario() {
            const vacina = $('#sel_vacina').val();
            const tipoPac = $('#tipo_paciente').val();
            const doseSelect = $('#vacina_dose');
            const txtArea = $('#txt_protocolo');
            
            doseSelect.empty();
            if (!vacina) {
                txtArea.val('');
                $('#adulto_2doses').val('');
                return;
            }

            let data;
            
            // Protocolos que não dependem do tipo de paciente
            if ([
                "Covenia (Antibiótico 14 dias)",
                "Cytopoint (Anticorpo Monoclonal)",
                "Leish-Tec - Protocolo Inicial (3 doses)",
                "Librela (Bedinvetmab)",
                "Quádrupla Felina (V4) - Filhotes (3 doses)",
                "V8 Especial (4 doses - 21 dias)",
                "V8 (Filhotes - 3 doses 30 dias)",
                "V10 (Filhotes - 3 doses 21 dias)"
            ].includes(vacina)) {
                data = configVacinas[vacina];
            }
            // Protocolos com perfis diferenciados
            else if ([
                "Antirrábica", "Biocan (Esquema 14 dias)", "Cinomose (V8/V10)", 
                "Cinomose e Parvovirose", "Giárdia (Anual - 2 doses)", 
                "Giárdia (Esquema 21 dias - 3 doses)", "Giárdia (Esquema 30 dias - 3 doses)",
                "Leish-Tec (Leishmaniose)", "Leptospirose", "Parvovirose + Coronavirose",
                "Quádrupla Felina (V4)", "Traqueobronquite (2 doses - 21 dias)",
                "Tríplice Felina (V3)", "V8 (Anual)", "V10 (Anual)"
            ].includes(vacina)) {
                data = configVacinas[vacina][tipoPac];
            } else {
                data = configVacinas[vacina];
            }

            // Popula o select de doses
            data.doses.forEach(d => {
                doseSelect.append(`<option value="${d}">${d}</option>`);
            });

            // Atualiza o protocolo
            txtArea.val(data.protocolo);

            // Marca protocolo adulto 2 doses
            if (data.adulto_2doses !== undefined && data.adulto_2doses === true) {
                $('#adulto_2doses').val('1');
            } else {
                $('#adulto_2doses').val('');
            }
        }

        function buscarHistorico(id) {
            if(!id) return;
            
            const loading = '<p style="font-size:14px; text-align:center; color:#666; padding:20px;"><i class="fas fa-spinner fa-spin"></i> Carregando dados...</p>';
            $('#lista_hist, #lista_lemb').html(loading);
            
            $.getJSON('consultas/vacinas.php', { ajax_historico: 1, id_paciente: id }, function(res) {
                if(res.status === 'success') {
                    let h = ""; 
                    res.historico.forEach(x => { 
                        const dataF = new Date(x.data_atendimento).toLocaleDateString('pt-BR');
                        h += `<div class="list-item"><span>${x.resumo}</span> <span style="color:#999; font-size:12px;">${dataF}</span></div>`; 
                    });
                    $('#lista_hist').html(h || "<p style='font-size:13px; text-align:center; color:#999; padding:20px;'>Nenhum registro anterior.</p>");
                    
                    let l = ""; 
                    res.lembretes.forEach(x => { 
                        const dataL = new Date(x.data_prevista+'T00:00:00').toLocaleDateString('pt-BR');
                        l += `<div class="list-item"><span>${x.vacina_nome}</span> <span style="color:var(--sucesso); font-weight:bold;">${dataL}</span></div>`; 
                    });
                    $('#lista_lemb').html(l || "<p style='font-size:13px; text-align:center; color:#999; padding:20px;'>Nenhum retorno agendado.</p>");
                }
            }).fail(function() {
                $('#lista_hist, #lista_lemb').html('<p style="font-size:13px; text-align:center; color:#c62828; padding:20px;"><i class="fas fa-exclamation-triangle"></i> Erro ao carregar dados</p>');
            });
        }

        <?php if($msg_sucesso): ?>
            Swal.fire({ 
                icon: 'success', 
                title: 'Vacinação Registrada!', 
                text: 'O histórico foi atualizado e o novo lembrete foi gerado com sucesso.',
                confirmButtonColor: '#1c329f',
                confirmButtonText: 'Ver Controle de Vacinas'
            }).then((result) => { 
                if (result.isConfirmed) {
                    window.location.href = 'consultas/listar_vacinas.php'; 
                }
            });
        <?php endif; ?>

        <?php if($msg_erro != ""): ?>
            Swal.fire({ icon: 'error', title: 'Falha ao Gravar', text: '<?= $msg_erro ?>' });
        <?php endif; ?>
    </script>
</body>
</html>