<?php
// ATIVAÇÃO DE ERROS PARA DEBUG
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php'; 
require_once 'config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;
$id_conta = $_GET['id'] ?? null;
$acao_baixa = isset($_GET['acao']) && $_GET['acao'] === 'baixa';
$dados = [];

// ==========================================================
// LÓGICA DE PROCESSAMENTO (POST)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // 1. CADASTROS RÁPIDOS (BOTÃO +)
    if (isset($_POST['acao_rapida'])) {
        $acao = $_POST['acao_rapida'];
        $nome = strtoupper(trim($_POST['nome_novo'] ?? ''));
        if (empty($nome)) { echo json_encode(['status' => 'error', 'message' => 'Nome vazio']); exit; }

        try {
            if ($acao === 'add_categoria') {
                $stmt = $pdo->prepare("INSERT INTO categorias_contas (nome_categoria, id_admin) VALUES (?, ?)");
            } else {
                $stmt = $pdo->prepare("INSERT INTO formas_pagamento (nome_forma, id_admin) VALUES (?, ?)");
            }
            $stmt->execute([$nome, $id_admin]);
            echo json_encode(['status' => 'success', 'id' => $pdo->lastInsertId(), 'nome' => $nome]);
        } catch (PDOException $e) { echo json_encode(['status' => 'error', 'message' => 'Erro: ' . $e->getMessage()]); }
        exit;
    }

    // 2. SALVAR CONTA COM LÓGICA DE PARCELAMENTO
    if (isset($_POST['acao_salvar_conta']) || isset($_POST['acao_registrar_pagamento'])) {
        try {
            $id_edit = $_POST['id_conta_edit'] ?? null;
            $qtd_parcelas = (int)($_POST['parcelas'] ?? 1);
            $status_inicial = (isset($_POST['acao_registrar_pagamento']) || isset($_POST['baixado'])) ? 'PAGO' : 'PENDENTE';
            
            // Limpeza de máscara de moeda
            $v_total = (float)str_replace(['R$', '.', ','], ['', '', '.'], $_POST['valor_total']);
            $v_parc  = (float)str_replace(['R$', '.', ','], ['', '', '.'], $_POST['valor_parcela']);
            $v_desc  = (float)str_replace(['R$', '.', ','], ['', '', '.'], $_POST['desconto']);

            // Se for EDIÇÃO, apenas atualizamos o registro atual
            if (!empty($id_edit)) {
                $sql = "UPDATE contas SET 
                        natureza=?, categoria=?, id_forma_pgto=?, id_conta_origem=?, entidade_tipo=?, 
                        id_entidade=?, doc_entidade=?, descricao=?, documento=?, serie=?, 
                        competencia=?, vencimento=?, status_baixa=?, qtd_parcelas=?, valor_parcela=?, 
                        valor_total=?, desconto=?, tipo_baixa=?, observacoes=?, data_pagamento=?
                        WHERE id=? AND id_admin=?";
                
                $data_pago = ($status_inicial === 'PAGO') ? date('Y-m-d H:i:s') : null;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $_POST['natureza'], $_POST['categoria'], $_POST['forma_pgto'] ?: null, $_POST['conta_origem'] ?: null, $_POST['entidade_tipo'],
                    $_POST['id_entidade'], $_POST['doc_entidade'], $_POST['descricao'], $_POST['documento'], $_POST['serie'],
                    $_POST['competencia'], $_POST['vencimento'], $status_inicial, $qtd_parcelas, $v_parc,
                    $v_total, $v_desc, $_POST['tipo_baixa'], $_POST['observacoes'], $data_pago,
                    $id_edit, $id_admin
                ]);
            } else {
                // Se for NOVO, verificamos se há parcelamento
                $vencimento_base = $_POST['vencimento'];

                for ($i = 1; $i <= $qtd_parcelas; $i++) {
                    // Calcula data de vencimento: adiciona (i-1) meses à data inicial
                    $nova_data_venc = date('Y-m-d', strtotime("+" . ($i - 1) . " month", strtotime($vencimento_base)));
                    
                    // Apenas a primeira parcela pode ser salva como PAGA se o usuário marcou o checkbox
                    $status_parcela = ($i === 1) ? $status_inicial : 'PENDENTE';
                    $data_pagamento_parc = ($status_parcela === 'PAGO') ? date('Y-m-d H:i:s') : null;
                    $descricao_parcela = $_POST['descricao'] . " ({$i}/{$qtd_parcelas})";

                    $sql = "INSERT INTO contas (
                                id_admin, natureza, categoria, id_forma_pgto, id_conta_origem, 
                                entidade_tipo, id_entidade, doc_entidade, descricao, documento, 
                                serie, competencia, vencimento, status_baixa, qtd_parcelas, 
                                valor_parcela, valor_total, desconto, tipo_baixa, observacoes, data_pagamento
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $id_admin, $_POST['natureza'], $_POST['categoria'], $_POST['forma_pgto'] ?: null, $_POST['conta_origem'] ?: null,
                        $_POST['entidade_tipo'], $_POST['id_entidade'], $_POST['doc_entidade'], $descricao_parcela, $_POST['documento'],
                        $_POST['serie'], $_POST['competencia'], $nova_data_venc, $status_parcela, $qtd_parcelas,
                        $v_parc, $v_total, $v_desc, $_POST['tipo_baixa'], $_POST['observacoes'], $data_pagamento_parc
                    ]);
                }
            }

            echo json_encode(['status' => 'success', 'message' => 'Lançamento(s) gerado(s) com sucesso!']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Erro: ' . $e->getMessage()]);
        }
        exit;
    }
}

// ==========================================================
// CARREGAMENTO DE DADOS (GET)
// ==========================================================
try {
    if ($id_conta) {
        $stmt = $pdo->prepare("SELECT * FROM contas WHERE id = ? AND id_admin = ?");
        $stmt->execute([$id_conta, $id_admin]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $fornecedores = $pdo->prepare("SELECT id, nome_fantasia, razao_social, cnpj FROM fornecedores WHERE id_admin = ? ORDER BY nome_fantasia ASC");
    $fornecedores->execute([$id_admin]);
    $fornecedores = $fornecedores->fetchAll(PDO::FETCH_ASSOC);

    $clientes = $pdo->prepare("SELECT id, nome, cpf_cnpj FROM clientes WHERE id_admin = ? ORDER BY nome ASC");
    $clientes->execute([$id_admin]);
    $clientes = $clientes->fetchAll(PDO::FETCH_ASSOC);

    $usuarios = $pdo->prepare("SELECT id, nome FROM usuarios WHERE id_admin = ? ORDER BY nome ASC");
    $usuarios->execute([$id_admin]);
    $usuarios = $usuarios->fetchAll(PDO::FETCH_ASSOC);

    $categorias = $pdo->prepare("SELECT id, nome_categoria FROM categorias_contas WHERE id_admin = ? ORDER BY nome_categoria ASC");
    $categorias->execute([$id_admin]);
    $categorias = $categorias->fetchAll(PDO::FETCH_ASSOC);

    $formas_pgto = $pdo->prepare("SELECT id, nome_forma FROM formas_pagamento WHERE id_admin = ? ORDER BY nome_forma ASC");
    $formas_pgto->execute([$id_admin]);
    $formas_pgto = $formas_pgto->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { die("Erro: " . $e->getMessage()); }

$titulo_pagina = $id_conta ? "Editar Lançamento" : "Novo Lançamento";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- CONFIGURAÇÕES GERAIS --- */
        :root { 
            --fundo: #ecf0f5;
            --texto-dark: #333;
            --primaria: #337ab7;
            --sucesso: #00a65a;
            --danger: #dd4b39;
            --warning: #f39c12;
            
            --sidebar-collapsed: 75px;
            --sidebar-expanded: 260px;
            --header-height: 80px;
            
            --transition-speed: 0.4s;
            --transition-ease: cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; }
        
        body {
            font-family: 'Source Sans Pro', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: var(--fundo);
            color: var(--texto-dark);
            min-height: 100vh;
            overflow-x: hidden;
            font-size: 16px;
        }

        /* --- LAYOUT FIXO 220PX --- */
        aside.sidebar-container {
            position: fixed; left: 0; top: 0; height: 100vh;
            width: 220px; z-index: 1000;
        }

        header.top-header {
            position: fixed; top: 0; left: 220px; right: 0;
            height: var(--header-height); z-index: 900;
            margin: 0 !important; 
        }

        main.main-content {
            margin-left: 220px;
            padding-top: calc(var(--header-height) + 30px);
            padding-right: 25px; padding-bottom: 30px; padding-left: 25px;
            min-height: 100vh;
            width: auto;
        }

        .faixa-superior {
            width: 100% !important; margin: 0 !important;
            border-radius: 0 !important;
        }

        /* --- ESTILOS DO FORMULÁRIO --- */
        .page-header-actions { margin-bottom: 25px; }

        .btn-cancel {
            background: #e7e7e7; color: #555; padding: 12px 24px; border-radius: 4px;
            text-decoration: none; font-weight: 600; display: inline-flex; align-items: center;
            font-size: 16px; transition: 0.2s;
        }
        .btn-cancel:hover { background: #d7d7d7; }

        .page-title { font-size: 28px; font-weight: 600; color: #444; margin: 0 0 20px 0; }

        .card-form {
            background: #fff; border-top: 3px solid #d2d6de; border-radius: 3px;
            padding: 30px; box-shadow: 0 1px 1px rgba(0,0,0,0.1); width: 100%;
        }

        /* GRID SYSTEM */
        .form-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        
        .col-12 { grid-column: span 12; }
        .col-8  { grid-column: span 8; }
        .col-6  { grid-column: span 6; }
        .col-4  { grid-column: span 4; }
        .col-3  { grid-column: span 3; }
        .col-2  { grid-column: span 2; }

        @media (max-width: 992px) { .col-6, .col-4 { grid-column: span 6; } .col-3, .col-2 { grid-column: span 4; } }
        @media (max-width: 768px) { .form-grid > div { grid-column: span 12; } }

        /* INPUTS E LABELS */
        .form-group label { font-size: 15px; font-weight: 700; color: #444; }
        
        .form-control {
            width: 100%; height: 42px; padding: 6px 14px; font-size: 16px;
            border: 1px solid #ccc; border-radius: 4px; background-color: #fff;
            transition: border-color ease-in-out .15s; font-family: inherit; color: #555;
        }
        .form-control:focus { border-color: var(--primaria); outline: none; }
        textarea.form-control { height: auto; padding: 12px; }

        /* BOTÃO MAIS RÁPIDO */
        .btn-plus-shortcut {
            background: #fff; border: 1px solid var(--primaria); color: var(--primaria); border-radius: 4px;
            width: 42px; height: 42px; cursor: pointer; display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0; transition: 0.2s;
        }
        .btn-plus-shortcut:hover { background: var(--primaria); color: #fff; }

        /* SELETOR DE TIPO (RADIO BUTTONS ESTILIZADOS) */
        .type-selector {
            display: flex; gap: 15px; padding: 15px; background: #f9f9f9; 
            border: 1px solid #e5e5e5; border-radius: 4px; margin-bottom: 10px;
            align-items: center; justify-content: center;
        }
        .type-option { display: flex; align-items: center; gap: 8px; font-weight: 600; color: #555; cursor: pointer; font-size: 15px; }
        .type-option input { width: 18px; height: 18px; accent-color: var(--primaria); }

        /* TOGGLE BAIXA (MANUAL/AUTO) */
        .baixa-toggle { display: flex; border-radius: 4px; overflow: hidden; height: 42px; border: 1px solid #ccc; }
        .btn-baixa { flex: 1; border: none; font-weight: 700; cursor: pointer; font-size: 14px; text-transform: uppercase; transition: 0.2s; }
        
        .btn-manual { background: var(--danger); color: #fff; }
        .btn-auto { background: #f4f4f4; color: #777; }
        .btn-baixa:not(.btn-manual):not(.btn-auto) { background: #f4f4f4; color: #777; } /* Estado inativo padrão */

        /* FOOTER ACTIONS */
        .footer-actions { margin-top: 35px; display: flex; gap: 15px; justify-content: flex-end; border-top: 1px solid #eee; padding-top: 25px; }
        
        .btn-action { 
            padding: 12px 30px; border-radius: 4px; font-weight: 700; cursor: pointer; 
            text-transform: uppercase; font-size: 16px; transition: 0.3s; border: none; 
            display: flex; align-items: center; gap: 8px; 
        }
        .btn-save { background: var(--primaria); color: #fff; }
        .btn-save:hover { background: #286090; }
        
        .btn-pay { background: var(--sucesso); color: #fff; }
        .btn-pay:hover { background: #008d4c; }

        /* MODAL */
        .modal-popup { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); }
        .modal-popup-content { 
            background: #fff; margin: 15% auto; padding: 30px; border-radius: 5px; 
            width: 450px; box-shadow: 0 5px 25px rgba(0,0,0,0.3); text-align: center; border-top: 4px solid var(--primaria);
        }

    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include 'menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include 'menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <div class="page-header-actions">
            <a href="listar_contas.php" class="btn-cancel">
                <i class="fas fa-arrow-left" style="margin-right: 8px;"></i> Voltar para Lista
            </a>
        </div>

        <h2 class="page-title"><?= $titulo_pagina ?></h2>

        <div class="card-form">
            <form id="formContas">
                <input type="hidden" name="id_conta_edit" value="<?= $id_conta ?>">
                <input type="hidden" name="acao_salvar_conta" value="1">
                
                <div class="form-grid">
                    
                    <div class="form-group col-6">
                        <label>Natureza *</label>
                        <select name="natureza" class="form-control">
                            <option value="Despesa" <?= ($dados['natureza'] ?? '') == 'Despesa' ? 'selected' : '' ?>>Despesa</option>
                            <option value="Receita" <?= ($dados['natureza'] ?? '') == 'Receita' ? 'selected' : '' ?>>Receita</option>
                        </select>
                    </div>
                    
                    <div class="form-group col-6">
                        <label>Categoria de Lançamento *</label>
                        <div style="display:flex; gap:10px;">
                            <select name="categoria" id="sel_categoria" required class="form-control">
                                <option value="">Selecione...</option>
                                <?php foreach($categorias as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($dados['categoria'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= strtoupper($cat['nome_categoria']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-plus-shortcut" onclick="abrirPopupRapido('Categoria', 'add_categoria', 'sel_categoria')"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>

                    <div class="form-group col-4">
                        <label>Forma de Pagamento</label>
                        <div style="display:flex; gap:10px;">
                            <select name="forma_pgto" id="sel_forma" class="form-control">
                                <option value="">Selecione...</option>
                                <?php foreach($formas_pgto as $f): ?>
                                    <option value="<?= $f['id'] ?>" <?= ($dados['id_forma_pgto'] ?? '') == $f['id'] ? 'selected' : '' ?>><?= strtoupper($f['nome_forma']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-plus-shortcut" onclick="abrirPopupRapido('Forma', 'add_forma', 'sel_forma')"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                    
                    <div class="form-group col-8">
                        <label>Conta origem *</label>
                        <select name="conta_origem" required class="form-control">
                            <option value="1">Caixa Geral</option>
                        </select>
                    </div>

                    <div class="col-12 type-selector">
                        <?php $tipo = $dados['entidade_tipo'] ?? 'fornecedor'; ?>
                        <label class="type-option"><input type="radio" name="entidade_tipo" value="fornecedor" <?= $tipo == 'fornecedor' ? 'checked' : '' ?> onchange="toggleEntidade()"> FORNECEDOR</label>
                        <label class="type-option"><input type="radio" name="entidade_tipo" value="cliente" <?= $tipo == 'cliente' ? 'checked' : '' ?> onchange="toggleEntidade()"> CLIENTE</label>
                        <label class="type-option"><input type="radio" name="entidade_tipo" value="usuario" <?= $tipo == 'usuario' ? 'checked' : '' ?> onchange="toggleEntidade()"> USUÁRIO</label>
                    </div>

                    <div class="col-12 form-group">
                        <label id="label_entidade">Entidade</label>
                        <select name="id_entidade" id="id_entidade" onchange="atualizarDoc()" class="form-control"></select>
                    </div>
                    
                    <div class="col-12 form-group">
                        <label id="label_doc">Documento</label>
                        <input type="text" name="doc_entidade" id="doc_entidade" class="form-control" value="<?= $dados['doc_entidade'] ?? '' ?>">
                    </div>
                    
                    <div class="col-12 form-group">
                        <label>Descrição do Lançamento *</label>
                        <input type="text" name="descricao" class="form-control" value="<?= $dados['descricao'] ?? '' ?>" required placeholder="Ex: Pagamento de Energia">
                    </div>

                    <div class="form-group col-6"><label>Documento / NF</label><input type="text" name="documento" class="form-control" value="<?= $dados['documento'] ?? '' ?>"></div>
                    <div class="form-group col-6"><label>Série</label><input type="text" name="serie" class="form-control" value="<?= $dados['serie'] ?? '' ?>"></div>
                    <div class="form-group col-6"><label>Competência</label><input type="date" name="competencia" class="form-control" value="<?= $dados['competencia'] ?? date('Y-m-d') ?>"></div>
                    <div class="form-group col-6"><label>Vencimento</label><input type="date" name="vencimento" class="form-control" value="<?= $dados['vencimento'] ?? date('Y-m-d') ?>"></div>

                    <div class="col-12 form-group" style="flex-direction: row; align-items: center; gap: 15px; margin-top: 10px;">
                        <input type="checkbox" name="baixado" id="baixado" style="width: 20px; height: 20px;" <?= ($dados['status_baixa'] ?? '') == 'PAGO' ? 'checked' : '' ?>>
                        <label for="baixado" style="margin:0; text-transform:none; cursor: pointer;">Marcar como Pago/Recebido agora?</label>
                    </div>

                    <div class="form-group col-2"><label>Parcelas</label><input type="number" name="parcelas" class="form-control" value="<?= $dados['qtd_parcelas'] ?? 1 ?>" min="1"></div>
                    <div class="form-group col-2"><label>Valor Parcela</label><input type="text" name="valor_parcela" class="form-control" value="R$ <?= number_format($dados['valor_parcela'] ?? 0, 2, ',', '.') ?>"></div>
                    <div class="form-group col-4"><label>Valor Total *</label><input type="text" name="valor_total" class="form-control" value="R$ <?= number_format($dados['valor_total'] ?? 0, 2, ',', '.') ?>" required style="font-weight: 700; color: var(--primaria);"></div>
                    <div class="form-group col-4"><label>Desconto</label><input type="text" name="desconto" class="form-control" value="R$ <?= number_format($dados['desconto'] ?? 0, 2, ',', '.') ?>"></div>

                    <div class="col-12 form-group">
                        <label>Baixa:</label>
                        <div class="baixa-toggle">
                            <button type="button" class="btn-baixa btn-manual" id="btnManual" onclick="setBaixa('manual')">MANUAL</button>
                            <button type="button" class="btn-baixa btn-auto" id="btnAuto" onclick="setBaixa('auto')">AUTOMÁTICA</button>
                        </div>
                        <input type="hidden" name="tipo_baixa" id="tipo_baixa" value="<?= $dados['tipo_baixa'] ?? 'manual' ?>">
                    </div>

                    <div class="col-12 form-group">
                        <label>Observações</label>
                        <textarea name="observacoes" class="form-control" rows="4"><?= $dados['observacoes'] ?? '' ?></textarea>
                    </div>
                </div>

                <div class="footer-actions">
                    <a href="listar_contas.php" class="btn-cancel">CANCELAR</a>
                    
                    <?php if($id_conta && ($dados['status_baixa'] ?? '') != 'PAGO'): ?>
                        <button type="button" class="btn-action btn-pay" onclick="salvarConta(true)">
                            <i class="fas fa-check-circle"></i> REGISTRAR PAGAMENTO
                        </button>
                    <?php endif; ?>
                    
                    <button type="button" class="btn-action btn-save" onclick="salvarConta(false)">
                        <i class="fas fa-save"></i> SALVAR CONTA
                    </button>
                </div>
            </form>
        </div>
    </main>

    <div id="modalPopupRapido" class="modal-popup">
        <div class="modal-popup-content">
            <h3 id="popup_titulo" style="margin-bottom:20px; color:#444;"></h3>
            <input type="text" id="popup_input" class="form-control" style="margin-bottom:25px;" placeholder="Digite o nome...">
            <div style="display:flex; gap:15px; justify-content:center;">
                <button type="button" class="btn-cancel" onclick="document.getElementById('modalPopupRapido').style.display='none'">CANCELAR</button>
                <button type="button" class="btn-action btn-pay" id="btn_popup_salvar">ADICIONAR</button>
            </div>
        </div>
    </div>

    <script>
        const ENTIDADES = { fornecedor: <?= json_encode($fornecedores) ?>, cliente: <?= json_encode($clientes) ?>, usuario: <?= json_encode($usuarios) ?> };
        
        function toggleEntidade() {
            const t = document.querySelector('input[name="entidade_tipo"]:checked').value;
            const s = document.getElementById('id_entidade');
            document.getElementById('label_entidade').innerText = t.toUpperCase();
            document.getElementById('label_doc').innerText = (t === 'fornecedor') ? 'CNPJ' : 'CPF';
            s.innerHTML = '';
            const idEdit = "<?= $dados['id_entidade'] ?? '' ?>";
            
            ENTIDADES[t].forEach(item => { 
                let opt = new Option((item.nome_fantasia || item.nome || item.razao_social).toUpperCase(), item.id); 
                opt.setAttribute('data-doc', item.cnpj || item.cpf_cnpj || ''); 
                if(item.id == idEdit) opt.selected = true;
                s.add(opt); 
            });
            atualizarDoc();
        }

        function atualizarDoc() { 
            const sel = document.getElementById('id_entidade'); 
            document.getElementById('doc_entidade').value = sel.options[sel.selectedIndex]?.getAttribute('data-doc') || ''; 
        }

        function setBaixa(m) { 
            document.getElementById('tipo_baixa').value = m; 
            const btnManual = document.getElementById('btnManual');
            const btnAuto = document.getElementById('btnAuto');
            
            if (m === 'manual') {
                btnManual.className = 'btn-baixa btn-manual'; // Vermelho/Destaque
                btnAuto.className = 'btn-baixa btn-auto';     // Cinza
            } else {
                btnManual.className = 'btn-baixa btn-auto';
                btnAuto.className = 'btn-baixa btn-manual';   // Ou outra cor de destaque se preferir
                // Nota: No CSS original "btn-manual" é vermelho (perigo), talvez queira mudar para "btn-save" (azul) se for auto.
                // Ajuste rápido visual:
                btnAuto.style.backgroundColor = 'var(--sucesso)';
                btnAuto.style.color = '#fff';
                btnManual.style.backgroundColor = '#f4f4f4';
                btnManual.style.color = '#777';
            }
        }
        
        async function salvarConta(isPagamento) {
            const btn = isPagamento ? document.querySelector('.btn-pay') : document.querySelector('.btn-save');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> SALVANDO...';
            btn.disabled = true;

            const form = document.getElementById('formContas');
            const formData = new FormData(form);
            if(isPagamento) formData.append('acao_registrar_pagamento', '1');
            
            try {
                const res = await fetch('contas.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if(data.status === 'success') {
                    alert(data.message);
                    window.location.href = 'listar_contas.php';
                } else {
                    alert(data.message);
                }
            } catch (e) {
                alert("Erro de conexão.");
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }

        function abrirPopupRapido(titulo, acao, selectId) {
            document.getElementById('popup_titulo').innerText = 'Nova ' + titulo;
            document.getElementById('modalPopupRapido').style.display = 'block';
            document.getElementById('popup_input').value = '';
            document.getElementById('popup_input').focus();
            
            document.getElementById('btn_popup_salvar').onclick = async function() {
                const val = document.getElementById('popup_input').value.trim();
                if(!val) return;
                
                const fd = new FormData();
                fd.append('acao_rapida', acao);
                fd.append('nome_novo', val);

                try {
                    const res = await fetch('contas.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if(data.status === 'success') { 
                        const sel = document.getElementById(selectId);
                        const opt = new Option(data.nome, data.id, true, true);
                        sel.add(opt);
                        document.getElementById('modalPopupRapido').style.display = 'none'; 
                    } else {
                        alert(data.message);
                    }
                } catch(e) { alert("Erro ao adicionar."); }
            };
        }

        window.onload = () => { 
            toggleEntidade(); 
            // Inicializa estado visual dos botões de baixa
            const tipo = "<?= $dados['tipo_baixa'] ?? 'manual' ?>";
            setBaixa(tipo);
        };
    </script>
</body>
</html>