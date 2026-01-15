<?php
require_once 'auth.php'; 
require_once 'config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================================
// LÓGICA DE PROCESSAMENTO (POST) - UNIFICADO E COMPLETO
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $id_admin = $_SESSION['id_admin'] ?? 1;
    $acao = $_POST['acao'] ?? '';

    // 1. Adicionar Cargo ou Comissão via Modal
    if ($acao === 'adicionar_cargo_rapido' || $acao === 'adicionar_comissao_rapido') {
        $tabela = ($acao === 'adicionar_cargo_rapido') ? 'cargos' : 'comissoes_grupos';
        $coluna = ($acao === 'adicionar_cargo_rapido') ? 'nome_cargo' : 'nome_grupo';
        $nome = trim($_POST['nome'] ?? '');
        
        if (empty($nome)) {
            echo json_encode(['status' => 'error', 'message' => 'O nome não pode estar vazio.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO $tabela ($coluna) VALUES (?)");
            $stmt->execute([$nome]);
            echo json_encode(['status' => 'success', 'id' => $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Erro no banco: ' . $e->getMessage()]);
        }
        exit;
    }

    // 2. Salvar Usuário e Permissões
    if ($acao === 'salvar_usuario') {
        try {
            $pdo->beginTransaction();

            $id = $_POST['id'] ?? null;
            $email = $_POST['email'];
            $nome = $_POST['nome'];
            $apelido = $_POST['apelido'] ?? null;
            $id_cargo = !empty($_POST['id_cargo']) ? $_POST['id_cargo'] : null;
            $id_grupo_comissao = !empty($_POST['id_grupo_comissao']) ? $_POST['id_grupo_comissao'] : null;
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            $acesso_sistema = isset($_POST['acesso_sistema']) ? 1 : 0;
            $observacoes = $_POST['observacoes'] ?? null;
            $nova_senha = $_POST['senha'] ?? '';

            if ($id) {
                // UPDATE
                $sql = "UPDATE usuarios SET email = :email, nome = :nome, apelido = :apelido, 
                        id_cargo = :id_cargo, id_grupo_comissao = :id_grupo_comissao, 
                        ativo = :ativo, acesso_sistema = :acesso_sistema, observacoes = :observacoes";
                
                if (!empty($nova_senha)) { $sql .= ", senha = :senha"; }
                $sql .= " WHERE id = :id AND id_admin = :id_admin";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':id', $id);
                if (!empty($nova_senha)) { $stmt->bindValue(':senha', password_hash($nova_senha, PASSWORD_DEFAULT)); }
            } else {
                // INSERT
                $senha_final = !empty($nova_senha) ? $nova_senha : '123456';
                $sql = "INSERT INTO usuarios (id_admin, nome, email, senha, apelido, id_cargo, id_grupo_comissao, ativo, acesso_sistema, observacoes) 
                        VALUES (:id_admin, :nome, :email, :senha, :apelido, :id_cargo, :id_grupo_comissao, :ativo, :acesso_sistema, :observacoes)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':senha', password_hash($senha_final, PASSWORD_DEFAULT));
            }

            $stmt->bindValue(':id_admin', $id_admin);
            $stmt->bindValue(':nome', $nome);
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':apelido', $apelido);
            $stmt->bindValue(':id_cargo', $id_cargo);
            $stmt->bindValue(':id_grupo_comissao', $id_grupo_comissao);
            $stmt->bindValue(':ativo', $ativo);
            $stmt->bindValue(':acesso_sistema', $acesso_sistema);
            $stmt->bindValue(':observacoes', $observacoes);
            $stmt->execute();

            $id_usuario_final = $id ? $id : $pdo->lastInsertId();

            // --- LÓGICA AUTOMÁTICA: CRIAR CAIXA PARA NOVO USUÁRIO ---
            if (empty($id)) {
                // Cria a conta financeira
                $nome_caixa = "Caixa - " . $nome;
                $stmtCaixa = $pdo->prepare("INSERT INTO contas_financeiras (id_admin, nome_conta, tipo_conta, saldo_inicial, situacao_saldo, status, permitir_lancamentos) 
                                            VALUES (?, ?, 'Espécie', 0.00, 'Positivo', 'Ativo', 1)");
                $stmtCaixa->execute([$id_admin, $nome_caixa]);
                $id_conta_caixa = $pdo->lastInsertId();

                // Vincula ao usuário recém-criado
                $stmtVinc = $pdo->prepare("UPDATE usuarios SET id_conta_caixa = ? WHERE id = ?");
                $stmtVinc->execute([$id_conta_caixa, $id_usuario_final]);
            }
            // --------------------------------------------------------

            // 3. Salvar Permissões (Limpa e Reinsere)
            $stmt_del = $pdo->prepare("DELETE FROM usuarios_permissoes WHERE id_usuario = ?");
            $stmt_del->execute([$id_usuario_final]);

            if (isset($_POST['perm']) && is_array($_POST['perm'])) {
                $stmt_perm = $pdo->prepare("INSERT INTO usuarios_permissoes (id_usuario, modulo, acao) VALUES (?, ?, ?)");
                foreach ($_POST['perm'] as $modulo => $acoes) {
                    foreach ($acoes as $acao_nome => $valor) {
                        $stmt_perm->execute([$id_usuario_final, $modulo, $acao_nome]);
                    }
                }
            }

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Usuário e permissões salvos com sucesso!']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Erro: ' . $e->getMessage()]);
        }
        exit;
    }
}

// ==========================================================
// CARREGAMENTO DA PÁGINA (GET)
// ==========================================================
$id_usuario = $_GET['id'] ?? null;
$dados = [];
$permissoes_usuario = [];

try {
    $cargos = $pdo->query("SELECT * FROM cargos ORDER BY nome_cargo ASC")->fetchAll(PDO::FETCH_ASSOC);
    $grupos_comissao = $pdo->query("SELECT * FROM comissoes_grupos ORDER BY nome_grupo ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    if ($id_usuario) {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$id_usuario]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt_p = $pdo->prepare("SELECT modulo, acao FROM usuarios_permissoes WHERE id_usuario = ?");
        $stmt_p->execute([$id_usuario]);
        while($p = $stmt_p->fetch(PDO::FETCH_ASSOC)){
            $permissoes_usuario[$p['modulo']][$p['acao']] = true;
        }
    }
} catch (PDOException $e) { $cargos = $grupos_comissao = []; }

$titulo_pagina = $id_usuario ? "Editar Usuário" : "Convidar usuário";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primaria: #421b72; --azul-claro: #3258db; --sucesso: #23d297; --fundo: #f4f7f6; --borda: #e0e0e0; --radius: 12px; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { display: grid; grid-template-columns: 260px 1fr; grid-template-rows: 65px 1fr; grid-template-areas: "sidebar header" "sidebar main"; height: 100vh; background: var(--fundo); font-family: 'Inter', sans-serif; overflow: hidden; }
        aside.sidebar-main { grid-area: sidebar; background: #fff; border-right: 1px solid #eee; z-index: 100; }
        header.top-navbar { grid-area: header; background: #fff; border-bottom: 1px solid #eee; height: 65px; }
        main.main-scroller { grid-area: main; padding: 30px; overflow-y: auto; color: #000; }
        
        .tabs-nav { display: flex; gap: 20px; border-bottom: 2px solid #eee; margin-bottom: 25px; }
        .tab-btn { padding: 12px 25px; cursor: pointer; border: none; background: none; font-weight: 600; color: #666; font-size: 16px; position: relative; }
        .tab-btn.active { color: var(--primaria); }
        .tab-btn.active::after { content: ''; position: absolute; bottom: -2px; left: 0; width: 100%; height: 2px; background: var(--primaria); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .card-form { background: #fff; padding: 35px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
        .form-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .full { grid-column: span 4; } .half { grid-column: span 2; }
        .form-group label { font-size: 14px; font-weight: 400; color: #000; text-transform: uppercase; margin-left: 10px; }
        .form-group input, .form-group select, .form-group textarea { padding: 12px 18px; border: 1px solid var(--borda); border-radius: 12px; font-size: 16px; color: #000; outline: none; background: #fafafa; transition: 0.3s; }
        
        /* Permissões UI conforme Anexos 1, 2, 3, 4 */
        .perm-category { border: 1px solid #eee; border-radius: 8px; margin-bottom: 10px; overflow: hidden; }
        .perm-header { padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; background: #fdfdfd; font-weight: 600; }
        .perm-body { padding: 20px; display: none; border-top: 1px solid #eee; background: #fff; }
        .perm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
        .perm-card { border: 1px solid #eee; border-radius: 8px; padding: 15px; background: #fff; }
        .perm-card h4 { font-size: 14px; margin-bottom: 10px; color: #000; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f9f9f9; padding-bottom: 5px;}
        
        .btn-marcar-todas { background: var(--azul-claro); color: #fff; border: none; padding: 6px; border-radius: 4px; font-size: 11px; cursor: pointer; width: 100%; margin-bottom: 12px; font-weight: 700; text-transform: uppercase; }
        .check-item { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; font-size: 15px; cursor: pointer; color: #000; }
        .check-item input { width: 18px; height: 18px; }

        .switch-wrapper { display: flex; align-items: center; justify-content: space-between; padding: 10px 18px; border: 1px solid var(--borda); border-radius: 12px; background: #fafafa; height: 52px; }
        .switch { position: relative; display: inline-block; width: 44px; height: 22px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--sucesso); }
        input:checked + .slider:before { transform: translateX(22px); }

        .btn-plus-inline { background: #fff; border: 1px solid var(--azul-claro); color: var(--azul-claro); border-radius: 8px; width: 52px; height: 52px; cursor: pointer; display: flex; align-items: center; justify-content: center; margin-top: auto; font-size: 18px; }
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(2px); }
        .modal-content { background-color: #fff; margin: 10% auto; border-radius: 15px; width: 90%; max-width: 400px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); animation: slideIn 0.3s; }
        @keyframes slideIn { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header { padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .modal-footer { padding: 15px 20px; border-top: 1px solid #eee; display: flex; gap: 10px; justify-content: flex-end; }
        .footer-actions { margin-top: 30px; display: flex; gap: 10px; justify-content: flex-end; }
        .btn-save { background: var(--sucesso); color: #fff; border: none; padding: 12px 35px; border-radius: 12px; font-weight: 700; cursor: pointer; text-transform: uppercase; font-size: 14px; }
        .btn-cancel { background: #f1f1f1; color: #666; padding: 12px 25px; border-radius: 12px; font-weight: 600; text-decoration: none; display: flex; align-items: center; font-size: 14px; }
    </style>
</head>
<body>
    <aside class="sidebar-main"><?php include 'menu/menulateral.php'; ?></aside>
    <header class="top-navbar"><?php include 'menu/faixa.php'; ?></header>
    <main class="main-scroller">
        <div class="page-header-actions"><a href="listar_usuarios.php" class="btn-cancel"><i class="fas fa-chevron-left" style="margin-right: 8px;"></i> VOLTAR</a></div>
        
        <form id="formUsuario">
            <input type="hidden" name="id" value="<?= $dados['id'] ?? '' ?>">
            <input type="hidden" name="acao" value="salvar_usuario">

            <div class="tabs-nav">
                <button type="button" class="tab-btn active" onclick="switchTab(event, 'tab-info')">Informações</button>
                <button type="button" class="tab-btn" onclick="switchTab(event, 'tab-perm')">Permissões</button>
            </div>

            <div id="tab-info" class="tab-content active">
                <div class="card-form">
                    <div class="form-grid">
                        <div class="form-group half"><label>E-mail *</label><input type="email" name="email" value="<?= $dados['email'] ?? '' ?>" required></div>
                        <div class="form-group half"><label>Nome Completo *</label><input type="text" name="nome" value="<?= $dados['nome'] ?? '' ?>" required></div>
                        <div class="form-group half"><label>Apelido</label><input type="text" name="apelido" value="<?= $dados['apelido'] ?? '' ?>"></div>
                        
                        <div class="form-group" style="grid-column: span 1.5;"><label>Senha</label><input type="text" name="senha" id="input_senha" placeholder="********"></div>
                        <div class="form-group" style="grid-column: span 0.5;"><button type="button" class="btn-plus-inline" onclick="gerarSenha()" title="Gerar Senha"><i class="fas fa-sync-alt"></i></button></div>

                        <div class="form-group" style="grid-column: span 1.5;"><label>Cargo</label>
                            <select name="id_cargo" id="select_cargo">
                                <option value="">Selecione</option>
                                <?php foreach($cargos as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($dados['id_cargo'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= $c['nome_cargo'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column: span 0.5;"><button type="button" class="btn-plus-inline" onclick="abrirModal('Cargo')"><i class="fas fa-plus"></i></button></div>

                        <div class="form-group" style="grid-column: span 1.5;"><label>Comissões</label>
                            <select name="id_grupo_comissao" id="select_comissao">
                                <option value="">Selecione</option>
                                <?php foreach($grupos_comissao as $g): ?>
                                    <option value="<?= $g['id'] ?>" <?= ($dados['id_grupo_comissao'] ?? '') == $g['id'] ? 'selected' : '' ?>><?= $g['nome_grupo'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column: span 0.5;"><button type="button" class="btn-plus-inline" onclick="abrirModal('Comissão')"><i class="fas fa-plus"></i></button></div>

                        <div class="form-group"><label>Usuário Ativo</label><div class="switch-wrapper"><span>Ativo?</span><label class="switch"><input type="checkbox" name="ativo" value="1" <?= ($dados['ativo'] ?? 1) ? 'checked' : '' ?>><span class="slider"></span></label></div></div>
                        <div class="form-group"><label>Acesso Sistema</label><div class="switch-wrapper"><span>Liberado?</span><label class="switch"><input type="checkbox" name="acesso_sistema" value="1" <?= ($dados['acesso_sistema'] ?? 1) ? 'checked' : '' ?>><span class="slider"></span></label></div></div>
                        <div class="form-group full"><label>Observações</label><textarea name="observacoes" rows="3"><?= $dados['observacoes'] ?? '' ?></textarea></div>
                    </div>
                </div>
            </div>

            <div id="tab-perm" class="tab-content">
                <div class="card-form">
                    
                    <div class="perm-category">
                        <div class="perm-header" onclick="toggleAccordion(this)">
                            <span>Administrativo</span>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <span style="font-size: 13px;">Marcar todos <input type="checkbox" onclick="event.stopPropagation(); checkAllCategory(this)"></span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                        <div class="perm-body">
                            <div class="perm-grid">
                                <?php 
                                $admin_modulos = [
                                    'ajuste_estoque' => 'Ajuste de estoque',
                                    'cad_estoque' => 'Cadastro de Estoque',
                                    'cat_produtos' => 'Categorias de produtos',
                                    'grp_comissoes' => 'Grupos de comissões de produtos',
                                    'usuarios' => 'Cargos de usuários',
                                    'transf_estoque' => 'Transferência de estoque'
                                ];
                                foreach($admin_modulos as $key => $label): ?>
                                <div class="perm-card">
                                    <h4><?= $label ?> <i class="fas fa-chevron-up"></i></h4>
                                    <button type="button" class="btn-marcar-todas" onclick="checkAllCard(this)">Marcar Todas</button>
                                    <?php foreach(['listar', 'cadastrar', 'alterar', 'visualizar', 'excluir'] as $acao): ?>
                                    <label class="check-item"><input type="checkbox" name="perm[<?= $key ?>][<?= $acao ?>]" <?= isset($permissoes_usuario[$key][$acao]) ? 'checked' : '' ?>> <?= ucfirst($acao) ?></label>
                                    <?php endforeach; ?>
                                </div>
                                <?php endforeach; ?>

                                <div class="perm-card">
                                    <h4>Dashboard <i class="fas fa-chevron-up"></i></h4>
                                    <button type="button" class="btn-marcar-todas" onclick="checkAllCard(this)">Marcar Todas</button>
                                    <label class="check-item"><input type="checkbox" name="perm[dashboard][painel]" <?= isset($permissoes_usuario['dashboard']['painel']) ? 'checked' : '' ?>> Painel dashboard</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="perm-category">
                        <div class="perm-header" onclick="toggleAccordion(this)"><span>Relatórios</span><i class="fas fa-chevron-down"></i></div>
                        <div class="perm-body">
                            <div class="perm-grid">
                                <div class="perm-card" style="grid-column: span 2;">
                                    <h4>Relatórios Gerais <i class="fas fa-chevron-up"></i></h4>
                                    <button type="button" class="btn-marcar-todas" onclick="checkAllCard(this)">Marcar Todas</button>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                        <?php 
                                        $rels = ['atendimento','clientes','comissoes','compras','produtos','recebimentos','vacinas','saida_prod_serv','insumos','entrada_saida','taxas_oper','fechamento_caixa','estoque','aniversariantes'];
                                        foreach($rels as $r): ?>
                                        <label class="check-item"><input type="checkbox" name="perm[relatorios][<?= $r ?>]" <?= isset($permissoes_usuario['relatorios'][$r]) ? 'checked' : '' ?>> Relatório de <?= str_replace('_',' ',$r) ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="perm-category">
                        <div class="perm-header" onclick="toggleAccordion(this)"><span>Produtos</span><i class="fas fa-chevron-down"></i></div>
                        <div class="perm-body">
                            <div class="perm-grid">
                                <div class="perm-card">
                                    <h4>Produtos <i class="fas fa-chevron-up"></i></h4>
                                    <button type="button" class="btn-marcar-todas" onclick="checkAllCard(this)">Marcar Todas</button>
                                    <?php foreach(['listar', 'cadastrar', 'alterar', 'visualizar', 'excluir'] as $acao): ?>
                                    <label class="check-item"><input type="checkbox" name="perm[produtos][<?= $acao ?>]" <?= isset($permissoes_usuario['produtos'][$acao]) ? 'checked' : '' ?>> <?= ucfirst($acao) ?></label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="footer-actions"><button type="button" onclick="salvarUsuario()" class="btn-save">SALVAR USUÁRIO</button></div>
        </form>
    </main>

    <div id="modalGenerico" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3 id="modal_titulo"></h3><span class="close" onclick="fecharModal()">&times;</span></div>
            <div class="modal-body"><div class="form-group full"><label id="modal_label"></label><input type="text" id="modal_input"></div></div>
            <div class="modal-footer"><button type="button" class="btn-cancel" onclick="fecharModal()">CANCELAR</button><button type="button" class="btn-save" id="btn_salvar_modal">SALVAR</button></div>
        </div>
    </div>

    <script>
        function gerarSenha() {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*';
            let s = ''; for(let i=0; i<8; i++) s += chars.charAt(Math.floor(Math.random()*chars.length));
            document.getElementById('input_senha').value = s; alert("Senha gerada: " + s);
        }

        function switchTab(evt, tabId) {
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active'); evt.currentTarget.classList.add('active');
        }

        function toggleAccordion(header) {
            const body = header.nextElementSibling;
            body.style.display = body.style.display === 'block' ? 'none' : 'block';
        }

        function checkAllCategory(checkbox) {
            const body = checkbox.closest('.perm-category').querySelector('.perm-body');
            body.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = checkbox.checked);
        }

        function checkAllCard(btn) {
            const card = btn.closest('.perm-card');
            const checks = card.querySelectorAll('input[type="checkbox"]');
            const anyUnchecked = Array.from(checks).some(c => !c.checked);
            checks.forEach(c => c.checked = anyUnchecked);
            btn.innerText = anyUnchecked ? 'Desmarcar Todas' : 'Marcar Todas';
        }

        function abrirModal(tipo) {
            document.getElementById('modal_titulo').innerText = 'Novo ' + tipo;
            document.getElementById('modal_label').innerText = 'Nome do ' + tipo;
            document.getElementById('modalGenerico').style.display = 'block';
            const input = document.getElementById('modal_input'); input.value = ''; input.focus();
            document.getElementById('btn_salvar_modal').onclick = async () => {
                if(!input.value) return;
                const fd = new FormData(); fd.append('acao', tipo === 'Cargo' ? 'adicionar_cargo_rapido' : 'adicionar_comissao_rapido'); fd.append('nome', input.value);
                const res = await fetch('usuarios.php', { method: 'POST', body: fd }); const data = await res.json();
                if(data.status === 'success') {
                    const sel = document.getElementById(tipo === 'Cargo' ? 'select_cargo' : 'select_comissao');
                    sel.add(new Option(input.value, data.id)); sel.value = data.id; fecharModal();
                }
            };
        }

        function fecharModal() { document.getElementById('modalGenerico').style.display = 'none'; }

        async function salvarUsuario() {
            const fd = new FormData(document.getElementById('formUsuario'));
            try {
                const res = await fetch('usuarios.php', { method: 'POST', body: fd });
                const data = await res.json(); alert(data.message);
                if (data.status === 'success') window.location.href = 'listar_usuarios.php';
            } catch(e) { alert("Erro ao salvar."); }
        }
    </script>
</body>
</html>