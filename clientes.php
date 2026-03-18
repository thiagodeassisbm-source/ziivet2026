<?php
/**
 * ZIIPVET - CADASTRO/EDIÇÃO DE CLIENTES
 * ARQUIVO: clientes.php
 * VERSÃO: 3.0.0 - COM ABAS E LAYOUT MODERNO
 */
require_once 'auth.php';
require_once 'config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;

// ==========================================================
// LÓGICA DE PROCESSAMENTO (POST)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar_cliente') {
    header('Content-Type: application/json');
    ob_clean(); 
    
    try {
        $pdo->beginTransaction();

        $id_cliente = $_POST['id_cliente'] ?? null;
        $nome = strtoupper(trim($_POST['nome']));
        $tipo_pessoa = $_POST['tipo_pessoa'] ?? 'Fisica';
        $status = $_POST['status'] ?? 'ATIVO';
        $cpf_cnpj = preg_replace('/\D/', '', $_POST['cpf_cnpj'] ?? '');
        $rg = trim($_POST['rg'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefone_principal = trim($_POST['telefone'] ?? '');
        $data_nasc = !empty($_POST['data_nascimento']) ? $_POST['data_nascimento'] : null;
        $cep = preg_replace('/\D/', '', $_POST['cep'] ?? '');
        $endereco = trim($_POST['endereco'] ?? '');
        $numero = trim($_POST['numero'] ?? '');
        $complemento = trim($_POST['complemento'] ?? '');
        $bairro = trim($_POST['bairro'] ?? '');
        $cidade = trim($_POST['cidade'] ?? '');
        $estado = trim($_POST['estado'] ?? '');
        $origem = trim($_POST['origem_cliente'] ?? '');
        $obs_geral = trim($_POST['observacoes'] ?? '');
        
        $is_favorito = false;
        if (isset($_POST['favorito_contato'])) {
            foreach ($_POST['favorito_contato'] as $fav) {
                if ($fav == "1") { $is_favorito = true; break; }
            }
        }
        $marcacoes = $is_favorito ? 'FAVORITO' : '';

        if (empty($telefone_principal) && isset($_POST['valor_contato'])) {
            foreach ($_POST['valor_contato'] as $v) {
                if (!empty($v)) { $telefone_principal = $v; break; }
            }
        }

        if ($id_cliente) {
            $sql = "UPDATE clientes SET 
                    nome = :nome, cpf_cnpj = :cpf_cnpj, rg = :rg, data_nascimento = :data_nasc,
                    tipo_pessoa = :tipo, cep = :cep, endereco = :endereco, numero = :numero,
                    complemento = :complemento, bairro = :bairro, cidade = :cidade, estado = :estado,
                    marcacoes = :marcacoes, origem_cliente = :origem, observacoes = :obs, 
                    telefone = :tel, email = :email, status = :status
                    WHERE id = :id AND id_admin = :id_admin";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':id', $id_cliente);
        } else {
            $sql = "INSERT INTO clientes (id_admin, nome, cpf_cnpj, rg, data_nascimento, tipo_pessoa, cep, endereco, numero, complemento, bairro, cidade, estado, marcacoes, origem_cliente, observacoes, telefone, email, status) 
                    VALUES (:id_admin, :nome, :cpf_cnpj, :rg, :data_nasc, :tipo, :cep, :endereco, :numero, :complemento, :bairro, :cidade, :estado, :marcacoes, :origem, :obs, :tel, :email, :status)";
            $stmt = $pdo->prepare($sql);
        }

        $stmt->bindValue(':id_admin', $id_admin);
        $stmt->bindValue(':nome', $nome);
        $stmt->bindValue(':cpf_cnpj', $cpf_cnpj);
        $stmt->bindValue(':rg', $rg);
        $stmt->bindValue(':data_nasc', $data_nasc);
        $stmt->bindValue(':tipo', $tipo_pessoa);
        $stmt->bindValue(':cep', $cep);
        $stmt->bindValue(':endereco', $endereco);
        $stmt->bindValue(':numero', $numero);
        $stmt->bindValue(':complemento', $complemento);
        $stmt->bindValue(':bairro', $bairro);
        $stmt->bindValue(':cidade', $cidade);
        $stmt->bindValue(':estado', $estado);
        $stmt->bindValue(':marcacoes', $marcacoes);
        $stmt->bindValue(':origem', $origem);
        $stmt->bindValue(':obs', $obs_geral);
        $stmt->bindValue(':tel', $telefone_principal);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':status', $status);
        $stmt->execute();

        $id_final = $id_cliente ? $id_cliente : $pdo->lastInsertId();

        $pdo->prepare("DELETE FROM clientes_contatos WHERE id_cliente = ?")->execute([$id_final]);
        
        if (isset($_POST['valor_contato'])) {
            $stmt_contato = $pdo->prepare("INSERT INTO clientes_contatos (id_cliente, tipo_contato, valor, obs_contato, notificar, favorito) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($_POST['valor_contato'] as $key => $valor) {
                if (!empty($valor)) {
                    $stmt_contato->execute([
                        $id_final,
                        $_POST['tipo_contato'][$key],
                        $valor,
                        $_POST['obs_contato'][$key] ?? '',
                        (int)($_POST['notificar'][$key] ?? 0),
                        (int)($_POST['favorito_contato'][$key] ?? 0)
                    ]);
                }
            }
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Dados salvos com sucesso!']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Erro ao salvar: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// CARREGAMENTO DA PÁGINA (GET)
// ==========================================================
$id_cliente = $_GET['id'] ?? null;
$dados = [];
$contatos_secundarios = [];

if ($id_cliente) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ? AND id_admin = ?");
        $stmt->execute([$id_cliente, $id_admin]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($dados) {
            $stmt_c = $pdo->prepare("SELECT * FROM clientes_contatos WHERE id_cliente = ?");
            $stmt_c->execute([$id_cliente]);
            $contatos_secundarios = $stmt_c->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) { die("Erro ao carregar dados."); }
}

$titulo_pagina = $id_cliente ? "Editar Cliente" : "Novo Cliente";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    
    <!-- DEPLOY_VERIFY_V5 -->
    <meta name="csrf-token" content="<?= \App\Utils\Csrf::getToken() ?>">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- CSS CENTRALIZADO -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/menu.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/formularios.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <style>
        /* CSS PARA AS ABAS */
        .tabs-container {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
        }
        
        .tabs-header {
            display: flex;
            background: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .tab-button {
            flex: 1;
            padding: 18px 24px;
            background: transparent;
            border: none;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Exo', sans-serif;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .tab-button:hover {
            background: rgba(98, 37, 153, 0.05);
            color: #131c71;
        }
        
        .tab-button.active {
            background: #fff;
            color: #131c71;
            border-bottom-color: #131c71;
        }
        
        .tab-button i {
            font-size: 18px;
        }
        
        .tabs-content {
            padding: 30px;
        }
        
        .tab-pane {
            display: none;
        }
        
        .tab-pane.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Estilo para seção de contatos */
        .secao-contatos h4 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #2c3e50;
            font-size: 20px;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .contato-item {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border: 2px solid #e9ecef;
        }
        
        .contato-row {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .contato-row-bottom {
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }
        
        .contato-row-bottom .form-group {
            flex: 1;
        }
        
        .contato-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-action {
            width: 42px;
            height: 42px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #fff;
            transition: all 0.3s ease;
        }
        
        .btn-action.bg-green {
            background: #28a745;
        }
        
        .btn-action.bg-green:hover {
            background: #218838;
            transform: scale(1.05);
        }
        
        .btn-action.bg-green.inactive {
            background: #6c757d;
            opacity: 0.6;
        }
        
        .btn-action.bg-red {
            background: #b92426;
        }
        
        .btn-action.bg-red:hover {
            background: #c82333;
            transform: scale(1.05);
        }
        
        .btn-add-contato {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: #131c71;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            font-family: 'Exo', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .btn-add-contato:hover {
            background: #4a1d75;
            transform: translateY(-2px);
        }
        
        .btn-add-contato i {
            font-size: 16px;
        }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include 'menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include 'menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <!-- HEADER: Título e Botão na mesma linha -->
        <div class="form-header">
            <h1 class="form-title">
                <i class="fas fa-user-plus"></i>
                <?= $titulo_pagina ?>
            </h1>
            
            <a href="listar_clientes.php" class="btn-voltar">
                <i class="fas fa-arrow-left"></i>
                Voltar para Lista
            </a>
        </div>

        <!-- FORMULÁRIO COM ABAS -->
        <form id="formCliente">
            <input type="hidden" name="acao" value="salvar_cliente">
            <input type="hidden" name="id_cliente" value="<?= $dados['id'] ?? '' ?>">
            
            <div class="tabs-container">
                <!-- CABEÇALHO DAS ABAS -->
                <div class="tabs-header">
                    <button type="button" class="tab-button active" onclick="trocarAba(event, 'aba-dados')">
                        <i class="fas fa-user"></i>
                        Dados Pessoais
                    </button>
                    <button type="button" class="tab-button" onclick="trocarAba(event, 'aba-contatos')">
                        <i class="fas fa-phone"></i>
                        Outros Contatos
                    </button>
                </div>
                
                <!-- CONTEÚDO DAS ABAS -->
                <div class="tabs-content">
                    
                    <!-- ABA 1: DADOS PESSOAIS -->
                    <div id="aba-dados" class="tab-pane active">
                        <div class="form-grid">
                            <div class="form-group half">
                                <label class="required">
                                    <i class="fas fa-user"></i>
                                    Nome do Cliente
                                </label>
                                <input type="text" name="nome" class="form-control" value="<?= $dados['nome'] ?? '' ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-id-card"></i>
                                    Tipo de Pessoa
                                </label>
                                <select name="tipo_pessoa" class="form-control">
                                    <option value="Fisica" <?= ($dados['tipo_pessoa'] ?? '') == 'Fisica' ? 'selected' : '' ?>>Pessoa Física</option>
                                    <option value="Juridica" <?= ($dados['tipo_pessoa'] ?? '') == 'Juridica' ? 'selected' : '' ?>>Pessoa Jurídica</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-toggle-on"></i>
                                    Status
                                </label>
                                <select name="status" class="form-control">
                                    <option value="ATIVO" <?= ($dados['status'] ?? '') == 'ATIVO' ? 'selected' : '' ?>>Ativo</option>
                                    <option value="INATIVO" <?= ($dados['status'] ?? '') == 'INATIVO' ? 'selected' : '' ?>>Inativo</option>
                                </select>
                            </div>
                            
                            <div class="form-group half">
                                <label>
                                    <i class="fas fa-id-badge"></i>
                                    CPF / CNPJ
                                </label>
                                <input type="text" name="cpf_cnpj" class="form-control" value="<?= $dados['cpf_cnpj'] ?? '' ?>">
                            </div>
                            
                            <div class="form-group half">
                                <label>
                                    <i class="fas fa-address-card"></i>
                                    RG
                                </label>
                                <input type="text" name="rg" class="form-control" value="<?= $dados['rg'] ?? '' ?>">
                            </div>
                            
                            <div class="form-group half">
                                <label>
                                    <i class="fas fa-phone"></i>
                                    Telefone / Celular (Principal)
                                </label>
                                <input type="text" name="telefone" class="form-control" value="<?= $dados['telefone'] ?? '' ?>" placeholder="(00) 00000-0000">
                            </div>
                            
                            <div class="form-group half">
                                <label>
                                    <i class="fas fa-envelope"></i>
                                    E-mail
                                </label>
                                <input type="email" name="email" class="form-control" value="<?= $dados['email'] ?? '' ?>">
                            </div>

                            <div class="form-group quarter">
                                <label>
                                    <i class="fas fa-map-pin"></i>
                                    CEP
                                </label>
                                <input type="text" id="cep" name="cep" class="form-control" value="<?= $dados['cep'] ?? '' ?>" onblur="buscarCEP(this.value)">
                            </div>
                            
                            <div class="form-group half">
                                <label>
                                    <i class="fas fa-road"></i>
                                    Endereço
                                </label>
                                <input type="text" id="endereco" name="endereco" class="form-control" value="<?= $dados['endereco'] ?? '' ?>">
                            </div>
                            
                            <div class="form-group quarter">
                                <label>
                                    <i class="fas fa-home"></i>
                                    Número
                                </label>
                                <input type="text" name="numero" class="form-control" value="<?= $dados['numero'] ?? '' ?>">
                            </div>
                            
                            <div class="form-group half">
                                <label>
                                    <i class="fas fa-building"></i>
                                    Complemento
                                </label>
                                <input type="text" name="complemento" class="form-control" value="<?= $dados['complemento'] ?? '' ?>">
                            </div>
                            
                            <div class="form-group half">
                                <label>
                                    <i class="fas fa-map"></i>
                                    Bairro
                                </label>
                                <input type="text" id="bairro" name="bairro" class="form-control" value="<?= $dados['bairro'] ?? '' ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-city"></i>
                                    Cidade
                                </label>
                                <input type="text" id="cidade" name="cidade" class="form-control" value="<?= $dados['cidade'] ?? '' ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-flag"></i>
                                    Estado (UF)
                                </label>
                                <input type="text" id="estado" name="estado" class="form-control" maxlength="2" value="<?= $dados['estado'] ?? '' ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-birthday-cake"></i>
                                    Data Nascimento
                                </label>
                                <input type="date" name="data_nascimento" class="form-control" value="<?= $dados['data_nascimento'] ?? '' ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-question-circle"></i>
                                    Origem (Como conheceu?)
                                </label>
                                <input type="text" name="origem_cliente" class="form-control" value="<?= $dados['origem_cliente'] ?? '' ?>">
                            </div>
                            
                            <div class="form-group full">
                                <label>
                                    <i class="fas fa-comment-alt"></i>
                                    Observações Gerais
                                </label>
                                <textarea name="observacoes" class="form-control" rows="4"><?= $dados['observacoes'] ?? '' ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ABA 2: OUTROS CONTATOS -->
                    <div id="aba-contatos" class="tab-pane">
                        <div class="secao-contatos">
                            <h4>
                                <i class="fas fa-address-book"></i>
                                Contatos Secundários / Recados
                            </h4>
                            
                            <div id="contatos-container">
                                <?php 
                                $lista = !empty($contatos_secundarios) ? $contatos_secundarios : [null];
                                foreach($lista as $c): ?>
                                <div class="contato-item">
                                    <div class="contato-row">
                                        <div class="form-group">
                                            <label>
                                                <i class="fas fa-tag"></i>
                                                Tipo
                                            </label>
                                            <select name="tipo_contato[]" class="form-control">
                                                <option value="Whatsapp" <?= ($c['tipo_contato'] ?? '') == 'Whatsapp' ? 'selected' : '' ?>>Whatsapp</option>
                                                <option value="Celular" <?= ($c['tipo_contato'] ?? '') == 'Celular' ? 'selected' : '' ?>>Celular</option>
                                                <option value="E-mail" <?= ($c['tipo_contato'] ?? '') == 'E-mail' ? 'selected' : '' ?>>E-mail</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>
                                                <i class="fas fa-phone-alt"></i>
                                                Contato
                                            </label>
                                            <input type="text" name="valor_contato[]" class="form-control" value="<?= $c['valor'] ?? '' ?>" placeholder="Digite o contato">
                                        </div>
                                    </div>
                                    <div class="contato-row-bottom">
                                        <div class="form-group">
                                            <label>
                                                <i class="fas fa-sticky-note"></i>
                                                Observação
                                            </label>
                                            <input type="text" name="obs_contato[]" class="form-control" value="<?= $c['obs_contato'] ?? '' ?>" placeholder="Observações (ex: Recado, Mãe)">
                                        </div>
                                        <div class="contato-actions">
                                            <button type="button" class="btn-action bg-green <?= ($c['notificar'] ?? 0) ? '' : 'inactive' ?>" onclick="toggleBtn(this)" title="Receber Notificações">
                                                <i class="fas fa-bell"></i>
                                                <input type="hidden" name="notificar[]" value="<?= $c['notificar'] ?? 0 ?>">
                                            </button>
                                            <button type="button" class="btn-action bg-red" onclick="removerContato(this)" title="Remover">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="button" class="btn-add-contato" onclick="adicionarContato()">
                                <i class="fas fa-plus"></i> Adicionar Outro Contato
                            </button>
                        </div>
                    </div>
                    
                </div>
            </div>
            
            <!-- BOTÕES DE AÇÃO -->
            <div class="form-actions">
                <button type="button" onclick="salvarCliente()" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Cliente
                </button>
                <a href="listar_clientes.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </main>

    <script src="js/csrf_protection.js?v=20260316_v7_final"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // SISTEMA DE ABAS
        function trocarAba(event, abaId) {
            event.preventDefault();
            
            // Remove active de todos os botões e abas
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
            
            // Adiciona active no botão clicado e na aba correspondente
            event.currentTarget.classList.add('active');
            document.getElementById(abaId).classList.add('active');
        }
        
        // GERENCIAMENTO DE CONTATOS
        function adicionarContato() {
            const container = document.getElementById('contatos-container');
            const clone = container.firstElementChild.cloneNode(true);
            
            clone.querySelectorAll('input').forEach(i => {
                if (i.type === 'hidden') i.value = '0';
                else i.value = '';
            });
            
            clone.querySelectorAll('.btn-action').forEach(b => { 
                if(!b.classList.contains('bg-red')) b.classList.add('inactive'); 
            });
            
            container.appendChild(clone);
        }

        function removerContato(btn) {
            const container = document.getElementById('contatos-container');
            if (container.children.length > 1) {
                btn.closest('.contato-item').remove();
            } else {
                const item = btn.closest('.contato-item');
                item.querySelectorAll('input[type="text"]').forEach(i => i.value = '');
            }
        }

        function toggleBtn(btn) {
            const input = btn.querySelector('input');
            const novoValor = input.value === "0" ? "1" : "0";
            input.value = novoValor;
            btn.classList.toggle('inactive', novoValor === "0");
        }

        // BUSCA CEP
        async function buscarCEP(cep) {
            cep = cep.replace(/\D/g, '');
            if (cep.length === 8) {
                try {
                    const res = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
                    const data = await res.json();
                    if (!data.erro) {
                        document.getElementById('endereco').value = data.logradouro;
                        document.getElementById('bairro').value = data.bairro;
                        document.getElementById('cidade').value = data.localidade;
                        document.getElementById('estado').value = data.uf;
                    }
                } catch(e) { console.error("Erro CEP"); }
            }
        }

        // SALVAR CLIENTE
        async function salvarCliente() {
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
            btn.disabled = true;

            const formData = new FormData(document.getElementById('formCliente'));
            
            // Adiciona o token CSRF manualmente para garantir o envio
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrfToken) {
                formData.append('csrf_token', csrfToken);
            }
            
            try {
                const res = await fetch('clientes.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.status === 'success') {
                    Swal.fire({
                        title: 'Sucesso!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonColor: '#131c71'
                    }).then(() => {
                        window.location.href = 'listar_clientes.php';
                    });
                } else {
                    Swal.fire('Erro!', data.message, 'error');
                }
            } catch (e) {
                Swal.fire('Erro!', 'Falha de conexão com o servidor.', 'error');
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>