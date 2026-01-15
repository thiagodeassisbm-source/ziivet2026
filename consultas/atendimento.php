<?php
/**
 * =========================================================================================
 * ZIIPVET - SISTEMA DE GESTÃO VETERINÁRIA PROFISSIONAL
 * MÓDULO: PRONTUÁRIO ELETRÔNICO E ATENDIMENTO CLÍNICO
 * VERSÃO: 2.6.0 - ARQUIVO ÚNICO (INCLUI LÓGICA AJAX DE PESO INTEGRADA)
 * =========================================================================================
 * ATENÇÃO: Este arquivo processa tanto a interface visual quanto as requisições AJAX.
 */

// 1. INCLUSÃO DE DEPENDÊNCIAS DE AUTENTICAÇÃO E CONFIGURAÇÃO
require_once '../auth.php';
require_once '../config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * 2. LÓGICA AJAX INTEGRADA (Substitui o arquivo get_historico_peso.php)
 * -----------------------------------------------------------------------------------------
 * Se o parâmetro 'ajax_peso' estiver presente na URL, o script retorna apenas o histórico
 */
if (isset($_GET['ajax_peso']) && isset($_GET['id_paciente'])) {
    $id_pac = (int)$_GET['id_paciente'];
    if ($id_pac > 0) {
        try {
            $stmt_ajax = $pdo->prepare("SELECT data_atendimento, peso FROM atendimentos 
                                        WHERE id_paciente = ? AND peso IS NOT NULL AND peso != '' 
                                        ORDER BY data_atendimento DESC");
            $stmt_ajax->execute([$id_pac]);
            $lista_h = $stmt_ajax->fetchAll(PDO::FETCH_ASSOC);

            if (count($lista_h) > 0) {
                echo '<ul style="list-style:none; padding:0; margin:0;">';
                foreach ($lista_h as $hp) {
                    echo '<li style="display:flex; justify-content:space-between; padding:12px 0; border-bottom:1px solid #f4f4f4;">';
                    echo '  <span style="color:#888; font-size:13px; font-weight:600;">' . date('d/m/Y', strtotime($hp['data_atendimento'])) . '</span>';
                    echo '  <span style="font-weight:700; color:#1c329f; font-size:16px;">' . htmlspecialchars($hp['peso']) . ' kg</span>';
                    echo '</li>';
                }
                echo '</ul>';
            } else {
                echo '<div style="text-align:center; padding:20px; color:#999; font-style:italic; font-size:13px;">Sem registros de peso anteriores.</div>';
            }
        } catch (PDOException $e) {
            echo '<span style="color:red; font-size:12px;">Erro ao consultar banco de dados.</span>';
        }
    }
    exit; // Interrompe o carregamento do restante do HTML na requisição AJAX
}

// 3. INICIALIZAÇÃO DE VARIÁVEIS DE AMBIENTE
$titulo_pagina   = "Novo Atendimento";
$msg_feedback    = "";
$status_feedback = "";
$id_atendimento  = isset($_GET['id']) ? (int)$_GET['id'] : null;
$atendimento     = null;
$historico_peso  = [];

/**
 * 4. LÓGICA DE CARREGAMENTO DE DADOS (MODO EDIÇÃO)
 */
if ($id_atendimento) {
    $titulo_pagina = "Editar Atendimento";
    try {
        $stmt_load = $pdo->prepare("SELECT * FROM atendimentos WHERE id = :id_atend LIMIT 1");
        $stmt_load->bindValue(':id_atend', $id_atendimento, PDO::PARAM_INT);
        $stmt_load->execute();
        $atendimento = $stmt_load->fetch(PDO::FETCH_ASSOC);

        if (!$atendimento) {
            die("<div style='padding:50px; text-align:center; font-family:sans-serif;'>
                    <h2 style='color:#dd4b39;'>Registro não encontrado</h2>
                    <p>O atendimento #{$id_atendimento} não existe.</p>
                    <a href='consultas/listar_atendimentos.php' style='color:#1c329f; font-weight:bold;'>Voltar</a>
                 </div>");
        }

        // CARREGAMENTO DO HISTÓRICO DE PESO INICIAL
        $stmt_peso_edit = $pdo->prepare("SELECT data_atendimento, peso FROM atendimentos 
                                         WHERE id_paciente = ? AND peso IS NOT NULL AND peso != '' 
                                         ORDER BY data_atendimento DESC");
        $stmt_peso_edit->execute([$atendimento['id_paciente']]);
        $historico_peso = $stmt_peso_edit->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Erro de Sistema: " . $e->getMessage());
        die("Erro crítico ao carregar prontuário.");
    }
}

/**
 * 5. PROCESSAMENTO DO FORMULÁRIO (SALVAMENTO)
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_paciente'])) {
    try {
        $caminho_anexo = $_POST['anexo_atual'] ?? null;

        if (isset($_FILES['anexo']) && !empty($_FILES['anexo']['name'])) {
            $diretorio_upload = '../uploads/atendimentos/';
            if (!is_dir($diretorio_upload)) mkdir($diretorio_upload, 0777, true);
            
            $extensao_arq = strtolower(pathinfo($_FILES['anexo']['name'], PATHINFO_EXTENSION));
            $formatos_ok  = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

            if (in_array($extensao_arq, $formatos_ok)) {
                $novo_nome_anexo = md5(uniqid(rand(), true)) . "." . $extensao_arq;
                $caminho_anexo   = 'uploads/atendimentos/' . $novo_nome_anexo;
                move_uploaded_file($_FILES['anexo']['tmp_name'], '../' . $caminho_anexo);
            }
        }

        if ($id_atendimento) {
            $sql_final = "UPDATE atendimentos SET 
                            id_paciente = :paciente, tipo_atendimento = :tipo, resumo = :resumo, 
                            descricao = :desc, anexo = :anexo, data_retorno = :retorno, peso = :peso 
                         WHERE id = :id";
        } else {
            $sql_final = "INSERT INTO atendimentos (id_paciente, tipo_atendimento, peso, resumo, descricao, anexo, data_retorno, status, data_atendimento) 
                         VALUES (:paciente, :tipo, :peso, :resumo, :desc, :anexo, :retorno, 'Finalizado', NOW())";
        }

        $stmt_final = $pdo->prepare($sql_final);
        $stmt_final->bindValue(':paciente', $_POST['id_paciente'], PDO::PARAM_INT);
        $stmt_final->bindValue(':tipo',     $_POST['tipo_atendimento']);
        $stmt_final->bindValue(':peso',     !empty($_POST['peso']) ? $_POST['peso'] : null);
        $stmt_final->bindValue(':resumo',   $_POST['resumo']);
        $stmt_final->bindValue(':desc',     $_POST['descricao']);
        $stmt_final->bindValue(':anexo',    $caminho_anexo);
        $stmt_final->bindValue(':retorno',  !empty($_POST['data_retorno']) ? $_POST['data_retorno'] : null);

        if ($id_atendimento) {
            $stmt_final->bindValue(':id', $id_atendimento, PDO::PARAM_INT);
        }

        if ($stmt_final->execute()) {
            header("Location: listar_atendimentos.php?status=success");
            exit;
        }

    } catch (Exception $err) {
        $msg_feedback = "Erro: " . $err->getMessage();
        $status_feedback = "error";
    }
}

// Carrega lista de pacientes para o seletor
$lista_pacientes = $pdo->query("SELECT p.id as id_paciente, p.nome_paciente, c.nome as nome_cliente 
                                FROM pacientes p INNER JOIN clientes c ON p.id_cliente = c.id 
                                ORDER BY c.nome ASC")->fetchAll(PDO::FETCH_ASSOC);
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
        :root { 
            --fundo: #ecf0f5; 
            --primaria: #1c329f; 
            --sucesso: #00a65a; 
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
        }

        /* Padronização de Layout Fixo */
        aside.sidebar-container { 
            position: fixed; left: 0; top: 0; height: 100vh; 
            width: var(--sidebar-collapsed); z-index: 1000; 
            transition: width var(--transition); 
            background: #fff;
        }
        aside.sidebar-container:hover { width: var(--sidebar-expanded); }

        header.top-header { 
            position: fixed; top: 0; left: var(--sidebar-collapsed); right: 0; 
            height: var(--header-height); z-index: 900; 
            transition: left var(--transition); 
            margin: 0 !important; 
        }
        aside.sidebar-container:hover ~ header.top-header { left: var(--sidebar-expanded); }

        main.main-content { 
            margin-left: var(--sidebar-collapsed); 
            padding: calc(var(--header-height) + 30px) 25px 30px; 
            transition: margin-left var(--transition); 
        }
        aside.sidebar-container:hover ~ main.main-content { margin-left: var(--sidebar-expanded); }

        .faixa-superior { width: 100% !important; margin: 0 !important; border-radius: 0 0 0 30px !important; }

        /* Interface do Formulário */
        .page-layout { display: grid; grid-template-columns: 1fr 320px; gap: 25px; align-items: start; }
        @media (max-width: 1100px) { .page-layout { grid-template-columns: 1fr; } }

        .card-atendimento { background: #fff; padding: 35px; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); border-top: 3px solid var(--primaria); }

        .header-fields-row { display: flex; gap: 20px; margin-bottom: 25px; flex-wrap: wrap; }
        .field-paciente { flex: 3; min-width: 300px; }
        .field-tipo     { flex: 1.5; min-width: 180px; }
        .field-peso     { flex: 1; min-width: 120px; }

        .form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px; }
        label { font-size: 13px; font-weight: 700; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }

        input, select { padding: 12px 15px; border: 1px solid var(--borda); border-radius: 4px; font-size: 17px; width: 100%; outline:none; }
        input:focus, select:focus { border-color: var(--primaria); }

        .editor-wrapper { border: 1px solid var(--borda); border-radius: 4px; overflow: hidden; }
        #editor-container { height: 400px; font-size: 17px; background: #fff; }
        .ql-toolbar { background: #f8f9fa; border: none !important; border-bottom: 1px solid var(--borda) !important; }

        /* Painel Lateral Histórico */
        .card-lateral { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-top: 3px solid #f39c12; }
        .lat-title { font-size: 15px; font-weight: 700; color: #444; text-transform: uppercase; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }

        .form-footer { display: flex; gap: 15px; margin-top: 40px; padding-top: 25px; border-top: 1px solid #eee; }
        .btn-action { padding: 15px 30px; border-radius: 4px; font-weight: 600; font-size: 14px; cursor: pointer; border: none; display: flex; align-items: center; gap: 10px; text-transform: uppercase; transition: 0.2s; text-decoration: none; }
        .btn-save { background: var(--sucesso); color: #fff; }
        .btn-back { background: #f4f4f4; color: #555; border: 1px solid #ddd; margin-left: auto; }

        .req { color: #dd4b39; }
    </style>
</head>
<body>

    <?php if ($msg_feedback): ?>
        <script>Swal.fire({ title: 'Atenção', text: '<?= $msg_feedback ?>', icon: '<?= $status_feedback ?>', confirmButtonColor: '#1c329f' });</script>
    <?php endif; ?>

    <aside class="sidebar-container"><?php include '../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../menu/faixa.php'; ?></header>

    <main class="main-content">
        <div class="page-layout">
            
            <div class="card-atendimento">
                <div style="margin-bottom: 30px;">
                    <h2 style="font-size: 28px; font-weight: 700; color: #333;"><?= $titulo_pagina ?></h2>
                    <p style="color: #888; font-size: 14px;">Mantenha o prontuário completo para o melhor histórico clínico.</p>
                </div>

                <form id="formAtendimento" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="anexo_atual" value="<?= $atendimento['anexo'] ?? '' ?>">
                    <input type="hidden" name="descricao" id="descricao_hidden">

                    <div class="header-fields-row">
                        <div class="form-group field-paciente">
                            <label>Paciente / Tutor <span class="req">*</span></label>
                            <select name="id_paciente" id="select_paciente_id" required>
                                <option value="">Selecione o paciente...</option>
                                <?php foreach($lista_pacientes as $p): ?>
                                    <option value="<?= $p['id_paciente'] ?>" <?= ($atendimento && $atendimento['id_paciente'] == $p['id_paciente']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['nome_cliente']) ?> - <?= htmlspecialchars($p['nome_paciente']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group field-tipo">
                            <label>Tipo Atendimento <span class="req">*</span></label>
                            <select name="tipo_atendimento" required>
                                <option value="">Selecione...</option>
                                <?php $ts = ['Consulta', 'Retorno', 'Vacinação', 'Cirurgia', 'Emergência']; 
                                foreach($ts as $t): ?>
                                    <option value="<?= $t ?>" <?= ($atendimento && $atendimento['tipo_atendimento'] == $t) ? 'selected' : '' ?>><?= $t ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group field-peso">
                            <label>Peso (kg)</label>
                            <input type="text" name="peso" value="<?= htmlspecialchars($atendimento['peso'] ?? '') ?>" placeholder="0.000">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Resumo / Queixa Principal</label>
                        <input type="text" name="resumo" value="<?= htmlspecialchars($atendimento['resumo'] ?? '') ?>" placeholder="Ex: Vômitos frequentes">
                    </div>

                    <div class="form-group">
                        <label>Descrição Clínica Detalhada <span class="req">*</span></label>
                        <div class="editor-wrapper"><div id="editor-container"></div></div>
                    </div>

                    <div class="form-group">
                        <label>Anexos (PDF, Imagens)</label>
                        <input type="file" name="anexo">
                    </div>

                    <div style="max-width: 250px;">
                        <div class="form-group">
                            <label><i class="far fa-calendar-check"></i> Próximo Retorno</label>
                            <input type="date" name="data_retorno" value="<?= $atendimento['data_retorno'] ?? '' ?>">
                        </div>
                    </div>

                    <div class="form-footer">
                        <button type="submit" class="btn-action btn-save"><i class="fas fa-save"></i> SALVAR ATENDIMENTO</button>
                        <a href="consultas/listar_atendimentos.php" class="btn-action btn-back"><i class="fas fa-times"></i> CANCELAR</a>
                    </div>
                </form>
            </div>

            <aside class="card-lateral">
                <div class="lat-title"><i class="fas fa-chart-line" style="color:#f39c12"></i> Evolução de Peso</div>
                <div id="historico_peso_display">
                    <?php if(!empty($historico_peso)): ?>
                        <ul style="list-style:none; padding:0;">
                            <?php foreach($historico_peso as $hp): ?>
                                <li style="display:flex; justify-content:space-between; padding:12px 0; border-bottom:1px solid #f4f4f4;">
                                    <span style="color:#888; font-size:13px; font-weight:600;"><?= date('d/m/Y', strtotime($hp['data_atendimento'])) ?></span>
                                    <span style="font-weight:700; color:#1c329f; font-size:16px;"><?= htmlspecialchars($hp['peso']) ?> kg</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p style="font-size: 13px; color: #999; text-align:center;">Selecione um paciente para ver o histórico de peso.</p>
                    <?php endif; ?>
                </div>
            </aside>

        </div>
    </main>

    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script>
        // Configuração do Editor Quill
        var quill = new Quill('#editor-container', {
            theme: 'snow',
            placeholder: 'Anamnese, conduta e prescrição...',
            modules: { toolbar: [[{'header': [1, 2, false]}], ['bold', 'italic', 'underline'], [{'list': 'ordered'}, {'list': 'bullet'}], ['link', 'clean']] }
        });

        // Preenche o editor se for edição
        <?php if ($atendimento && !empty($atendimento['descricao'])): ?>
            quill.root.innerHTML = `<?= $atendimento['descricao'] ?>`;
        <?php endif; ?>

        // Lógica AJAX unificada no mesmo arquivo
        $('#select_paciente_id').on('change', function() {
            var id_pac = $(this).val();
            var display = $('#historico_peso_display');
            if(id_pac) {
                display.html('<p style="text-align:center;"><i class="fas fa-spinner fa-spin"></i></p>');
                
                // Chamamos o próprio arquivo passando o parâmetro ajax_peso=1
                $.get('consultas/atendimento.php', { ajax_peso: 1, id_paciente: id_pac }, function(data) {
                    display.html(data);
                }).fail(function() {
                    display.html('<span style="color:red; font-size:12px;">Erro ao carregar histórico.</span>');
                });
            } else {
                display.html('<p style="font-size: 13px; color: #999; text-align:center;">Selecione um paciente.</p>');
            }
        });

        // Sincroniza Quill antes do envio
        document.getElementById('formAtendimento').onsubmit = function(e) {
            var content = quill.root.innerHTML;
            if (content.trim() === '<p><br></p>') {
                Swal.fire('Atenção', 'O prontuário está vazio.', 'warning');
                e.preventDefault(); return false;
            }
            document.getElementById('descricao_hidden').value = content;
        };
    </script>
</body>
</html>