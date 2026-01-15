<?php
/**
 * =========================================================================================
 * ZIIPVET - EMISSÃO DE RECEITAS MÉDICAS
 * ARQUIVO: receitas.php
 * VERSÃO: 3.0.0 - COM IMPRESSÃO PROFISSIONAL
 * =========================================================================================
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. SALVAR NOVO MODELO
if (isset($_POST['acao']) && $_POST['acao'] === 'salvar_modelo') {
    try {
        $sql_mod = "INSERT INTO modelos_receitas (titulo, conteudo) VALUES (:titulo, :conteudo)";
        $stmt_mod = $pdo->prepare($sql_mod);
        $stmt_mod->execute([
            ':titulo' => $_POST['novo_titulo'],
            ':conteudo' => $_POST['novo_conteudo']
        ]);
        header("Location: receitas.php?sucesso_modelo=1");
        exit;
    } catch (PDOException $e) {
        $erro_modelo = $e->getMessage();
    }
}

// 2. SALVAR RECEITA E VOLTAR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar_receita') {
    try {
        // Verificar se tabela receitas existe, se não, criar
        $check_table = $pdo->query("SHOW TABLES LIKE 'receitas'")->fetch();
        if (!$check_table) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `receitas` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `id_paciente` INT(11) NOT NULL,
                `conteudo` LONGTEXT NOT NULL,
                `data_emissao` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `id_usuario` INT(11) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_paciente` (`id_paciente`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        
        // Inserir receita
        $sql_receita = "INSERT INTO receitas (id_paciente, conteudo, id_usuario) VALUES (:id_paciente, :conteudo, :id_usuario)";
        $stmt_receita = $pdo->prepare($sql_receita);
        $stmt_receita->execute([
            ':id_paciente' => $_POST['id_paciente'],
            ':conteudo' => $_POST['conteudo_receita'],
            ':id_usuario' => $_SESSION['id_usuario'] ?? 1
        ]);
        
        header("Location:receitas.php?sucesso_salvar=1");
        exit;
    } catch (PDOException $e) {
        $erro_salvar = "Erro ao salvar receita: " . $e->getMessage();
    }
}

// 2. CARREGAR DADOS
try {
    $lista_pacientes = $pdo->query("SELECT p.id as id_paciente, p.nome_paciente, c.nome as nome_cliente FROM pacientes p INNER JOIN clientes c ON p.id_cliente = c.id ORDER BY c.nome ASC")->fetchAll(PDO::FETCH_ASSOC);
    $lista_modelos = $pdo->query("SELECT * FROM modelos_receitas ORDER BY titulo ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { 
    error_log($e->getMessage()); 
    $lista_pacientes = [];
    $lista_modelos = [];
}

$titulo_pagina = "Emitir Receita Médica";
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

    <style>
        :root { 
            --fundo: #ecf0f5; --primaria: #1c329f; --sucesso: #28a745; 
            --borda: #d2d6de; --sidebar-collapsed: 75px; --sidebar-expanded: 260px; 
            --header-height: 80px; --transition: 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Open Sans', sans-serif; background-color: var(--fundo); font-size: 15px; color: #333; overflow-x: hidden; line-height: 1.6; }

        /* Layout Estruturado */
        aside.sidebar-container { position: fixed; left: 0; top: 0; height: 100vh; width: var(--sidebar-collapsed); z-index: 1000; background: #fff; transition: width var(--transition); box-shadow: 2px 0 5px rgba(0,0,0,0.05); }
        aside.sidebar-container:hover { width: var(--sidebar-expanded); }
        header.top-header { position: fixed; top: 0; left: var(--sidebar-collapsed); right: 0; height: var(--header-height); z-index: 900; transition: left var(--transition); margin: 0 !important; }
        aside.sidebar-container:hover ~ header.top-header { left: var(--sidebar-expanded); }
        main.main-content { margin-left: var(--sidebar-collapsed); padding: calc(var(--header-height) + 25px) 25px 30px; transition: margin-left var(--transition); width: calc(100% - var(--sidebar-collapsed)); }
        aside.sidebar-container:hover ~ main.main-content { margin-left: var(--sidebar-expanded); width: calc(100% - var(--sidebar-expanded)); }

        .card-receita { background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border-top: 5px solid var(--primaria); width: 100%; max-width: 1200px; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px; }
        .form-group { display: flex; flex-direction: column; gap: 10px; }
        .full-width { grid-column: span 2; }

        label { font-size: 13px; font-weight: 600; color: #2c3e50; text-transform: none; letter-spacing: 0.3px; }
        input, select, textarea { padding: 14px 16px; border: 1px solid var(--borda); border-radius: 8px; font-size: 15px; outline: none; background: #fff; font-family: 'Open Sans', sans-serif; transition: all 0.3s ease; }
        input:focus, select:focus, textarea:focus { border-color: var(--primaria); box-shadow: 0 0 0 3px rgba(28, 50, 159, 0.1); }
        
        .modelo-container { display: flex; gap: 10px; align-items: center; }
        .btn-plus { background: var(--primaria); color: #fff; border: none; width: 48px; height: 48px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 18px; transition: 0.3s; }
        .btn-plus:hover { background: var(--sucesso); transform: scale(1.05); }

        /* Editor Quill */
        #editor-container { height: 400px; border-radius: 0 0 8px 8px !important; font-size: 16px; }
        .ql-toolbar { border-radius: 8px 8px 0 0 !important; background: #f8f9fa; border-color: var(--borda) !important; }
        .ql-container { border-color: var(--borda) !important; }

        .footer-actions { display: flex; justify-content: space-between; margin-top: 40px; padding-top: 25px; border-top: 1px solid #eee; }
        .btn-acao { padding: 16px 30px; border-radius: 8px; font-weight: 700; font-size: 14px; cursor: pointer; border: none; display: flex; align-items: center; gap: 10px; text-transform: uppercase; transition: all 0.3s ease; }
        .btn-salvar { background: linear-gradient(135deg, var(--sucesso) 0%, #20c997 100%); color: #fff; box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2); }
        .btn-imprimir { background: linear-gradient(135deg, #0277bd 0%, #01579b 100%); color: #fff; box-shadow: 0 4px 12px rgba(2, 119, 189, 0.2); }
        .btn-cancelar { background: #f4f4f4; color: #777; }
        .btn-acao:hover { transform: translateY(-2px); opacity: 0.95; }

        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); align-items: center; justify-content: center; backdrop-filter: blur(3px); }
        .modal-content { background: #fff; padding: 40px; border-radius: 12px; width: 600px; max-width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        
        .select2-container--default .select2-selection--single { height: 48px; border: 1px solid var(--borda); display: flex; align-items: center; border-radius: 8px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 48px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 48px; }
            .faixa-superior { width: 100% !important; margin: 0 !important; border-radius: 0 0 0 30px !important; }

    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include '../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../menu/faixa.php'; ?></header>

    <main class="main-content">
        <div class="card-receita">
            <h3 style="color: var(--primaria); margin-bottom: 35px; display: flex; align-items: center; gap: 15px; font-size: 26px; font-weight: 700;">
                <i class="fas fa-prescription"></i> Emitir Receita Médica
            </h3>
            
            <form id="formReceita" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Escolher um modelo de receita</label>
                        <div class="modelo-container">
                            <select id="select-modelo" onchange="aplicarModelo(this)" style="flex: 1;">
                                <option value="">Selecione um modelo...</option>
                                <?php foreach($lista_modelos as $mod): ?>
                                    <option value="<?= htmlspecialchars($mod['conteudo']) ?>"><?= htmlspecialchars($mod['titulo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-plus" onclick="abrirModalModelo()" title="Cadastrar novo modelo">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Paciente / Tutor *</label>
                        <select name="id_paciente" id="select_paciente" required>
                            <option value="">Pesquise por Tutor ou Pet...</option>
                            <?php foreach($lista_pacientes as $row): ?>
                                <option value="<?= $row['id_paciente'] ?>"><?= htmlspecialchars($row['nome_cliente']) ?> - Pet: <?= htmlspecialchars($row['nome_paciente']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label>Prescrição Médica</label>
                        <div id="editor-container"></div>
                        <input type="hidden" name="conteudo_receita" id="conteudo_input">
                    </div>
                </div>

                <div class="footer-actions">
                    <div style="display: flex; gap: 12px;">
                        <button type="button" class="btn-acao btn-salvar" onclick="salvarReceita()"><i class="fas fa-save"></i> Salvar e Voltar</button>
                        <button type="button" class="btn-acao btn-imprimir" onclick="imprimirReceita()"><i class="fas fa-print"></i> Visualizar Impressão</button>
                    </div>
                    <button type="button" class="btn-acao btn-cancelar" onclick="history.back()"><i class="fas fa-times"></i> Cancelar</button>
                </div>
            </form>
        </div>
    </main>

    <!-- Modal Novo Modelo -->
    <div id="modalModelo" class="modal">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="acao" value="salvar_modelo">
                <h4 style="margin-bottom: 25px; color: var(--primaria); font-size: 20px; font-weight: 700;">
                    <i class="fas fa-file-medical"></i> Novo Modelo de Receita
                </h4>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Título do Modelo</label>
                    <input type="text" name="novo_titulo" required placeholder="Ex: Receita Antipulgas">
                </div>
                <div class="form-group">
                    <label>Conteúdo (Texto da Receita)</label>
                    <textarea name="novo_conteudo" style="height: 200px; resize: vertical;" required placeholder="Digite o texto padrão aqui..."></textarea>
                </div>
                <div style="display: flex; gap: 12px; margin-top: 30px; justify-content: flex-end;">
                    <button type="button" class="btn-acao btn-cancelar" onclick="fecharModalModelo()"><i class="fas fa-times"></i> Descartar</button>
                    <button type="submit" class="btn-acao btn-salvar"><i class="fas fa-save"></i> Confirmar e Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script>
        $(document).ready(function() { 
            $('#select_paciente').select2({ 
                placeholder: "Pesquise por Tutor ou Pet..." 
            }); 
        });

        var quill = new Quill('#editor-container', {
            theme: 'snow',
            placeholder: 'Digite detalhadamente a prescrição, posologia e recomendações médicas...',
            modules: { 
                toolbar: [
                    ['bold', 'italic', 'underline'], 
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }], 
                    ['clean']
                ] 
            }
        });

        function abrirModalModelo() { 
            document.getElementById('modalModelo').style.display = 'flex'; 
        }
        
        function fecharModalModelo() { 
            document.getElementById('modalModelo').style.display = 'none'; 
        }

        function aplicarModelo(select) {
            if(select.value) {
                quill.root.innerHTML = select.value;
            }
        }

        function salvarReceita() {
            const idPaciente = $('#select_paciente').val();
            const conteudo = quill.root.innerHTML;
            
            if(!idPaciente) {
                return Swal.fire({
                    icon: 'warning',
                    title: 'Atenção',
                    text: 'Selecione um paciente antes de salvar.',
                    confirmButtonColor: '#1c329f'
                });
            }
            
            if(!conteudo || conteudo === '<p><br></p>') {
                return Swal.fire({
                    icon: 'warning',
                    title: 'Atenção',
                    text: 'Escreva a prescrição antes de salvar.',
                    confirmButtonColor: '#1c329f'
                });
            }
            
            Swal.fire({
                title: 'Salvar Receita?',
                text: 'A receita será gravada no histórico do paciente.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-save"></i> Sim, Salvar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Adicionar ação ao formulário
                    const acaoInput = document.createElement('input');
                    acaoInput.type = 'hidden';
                    acaoInput.name = 'acao';
                    acaoInput.value = 'salvar_receita';
                    
                    const form = document.getElementById('formReceita');
                    form.appendChild(acaoInput);
                    
                    // Preencher conteúdo e submeter
                    document.getElementById('conteudo_input').value = conteudo;
                    form.submit();
                }
            });
        }

        function imprimirReceita() {
            const idPaciente = $('#select_paciente').val();
            const conteudo = quill.root.innerHTML;
            
            if(!idPaciente) {
                return Swal.fire({
                    icon: 'warning',
                    title: 'Atenção',
                    text: 'Selecione um paciente antes de visualizar a impressão.',
                    confirmButtonColor: '#1c329f'
                });
            }
            
            if(!conteudo || conteudo === '<p><br></p>') {
                return Swal.fire({
                    icon: 'warning',
                    title: 'Atenção',
                    text: 'Escreva a prescrição antes de imprimir.',
                    confirmButtonColor: '#1c329f'
                });
            }
            
            // Criar formulário temporário para enviar via POST
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'consultas/imprimir_receita.php';
            form.target = '_blank';
            
            const inputPaciente = document.createElement('input');
            inputPaciente.type = 'hidden';
            inputPaciente.name = 'id_paciente';
            inputPaciente.value = idPaciente;
            
            const inputConteudo = document.createElement('input');
            inputConteudo.type = 'hidden';
            inputConteudo.name = 'conteudo_receita';
            inputConteudo.value = conteudo;
            
            form.appendChild(inputPaciente);
            form.appendChild(inputConteudo);
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        <?php if(isset($_GET['sucesso_modelo'])): ?>
            Swal.fire({ 
                icon: 'success',
                title: 'Sucesso!', 
                text: 'Modelo de receita cadastrado com sucesso!', 
                confirmButtonColor: '#1c329f' 
            });
        <?php endif; ?>
        
        <?php if(isset($_GET['sucesso_salvar'])): ?>
            Swal.fire({ 
                icon: 'success',
                title: 'Receita Salva!', 
                text: 'A receita foi gravada no histórico do paciente com sucesso!', 
                confirmButtonColor: '#28a745',
                confirmButtonText: 'OK'
            });
        <?php endif; ?>
        
        <?php if(isset($erro_salvar)): ?>
            Swal.fire({ 
                icon: 'error',
                title: 'Erro ao Salvar', 
                text: '<?= addslashes($erro_salvar) ?>', 
                confirmButtonColor: '#dc3545' 
            });
        <?php endif; ?>
    </script>
</body>
</html>