<?php
ob_start();
// ==========================================================
// CONFIGURAÇÕES GERAIS
// ==========================================================
date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'config/configuracoes.php';

use App\Utils\Csrf;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;

// ==========================================================
// 1. PROCESSAMENTO (POST) - ABRIR CAIXA - ✅ CORRIGIDO
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'abrir_caixa') {
    // Desativar erros visuais para evitar quebra do JSON
    ini_set('display_errors', 0);
    error_reporting(0);

    if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        ob_clean();
    }

    try {
        $pdo->beginTransaction();

        if (empty($_POST['id_usuario'])) throw new Exception("Selecione um usuário para abrir o caixa.");
        if (empty($_POST['data_abertura'])) throw new Exception("A data de abertura é obrigatória.");
        if (empty($_POST['hora_abertura'])) throw new Exception("A hora de abertura é obrigatória.");
        if (empty($_POST['forma_pagamento'])) throw new Exception("Selecione a forma de pagamento do suprimento.");

        $id_usuario = $_POST['id_usuario'];
        $valor = !empty($_POST['valor']) ? str_replace(['R$', '.', ','], ['', '', '.'], $_POST['valor']) : 0.00;
        $conta_origem = !empty($_POST['conta_origem']) ? $_POST['conta_origem'] : null;

        $stmtCheck = $pdo->prepare("SELECT id FROM caixas WHERE id_usuario = ? AND id_admin = ? AND status = 'ABERTO'");
        $stmtCheck->execute([$id_usuario, $id_admin]);
        
        if ($stmtCheck->rowCount() > 0) {
            $caixaAberto = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            throw new Exception("Este usuário já possui o caixa #" . $caixaAberto['id'] . " aberto! É necessário fechá-lo antes de abrir um novo.");
        }

        $stmtUser = $pdo->prepare("SELECT id_conta_caixa, nome FROM usuarios WHERE id = ?");
        $stmtUser->execute([$id_usuario]);
        $dadosUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $id_conta_usuario = $dadosUser['id_conta_caixa'];

        if (empty($id_conta_usuario)) {
            $nome_conta_new = "Caixa - " . $dadosUser['nome'];
            $stmtNewC = $pdo->prepare("INSERT INTO contas_financeiras (id_admin, nome_conta, tipo_conta, saldo_inicial, status, permitir_lancamentos, categoria) VALUES (?, ?, 'Espécie', 0.00, 'Ativo', 1, 'Caixa')");
            $stmtNewC->execute([$id_admin, $nome_conta_new]);
            $id_conta_usuario = $pdo->lastInsertId();
            
            $pdo->prepare("UPDATE usuarios SET id_conta_caixa = ? WHERE id = ?")->execute([$id_conta_usuario, $id_usuario]);
        }

        $sql = "INSERT INTO caixas (id_admin, id_usuario, data_abertura, hora_abertura, id_conta_origem, valor_inicial, id_forma_pagamento, descricao, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ABERTO')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $id_admin,
            $id_usuario,
            $_POST['data_abertura'],
            $_POST['hora_abertura'],
            $conta_origem,
            $valor,
            $_POST['forma_pagamento'],
            $_POST['descricao']
        ]);
        $id_caixa_gerado = $pdo->lastInsertId();

        // ✅✅✅ CORREÇÃO APLICADA AQUI ✅✅✅
        if ($valor > 0 && $conta_origem) {
            // 1. DEBITAR o valor da conta de origem (Fundo Fixo) na tabela contas_financeiras
            $stmt_origem = $pdo->prepare("SELECT saldo_inicial FROM contas_financeiras WHERE id = ?");
            $stmt_origem->execute([$conta_origem]);
            $saldo_origem_atual = $stmt_origem->fetchColumn();
            
            $novo_saldo_origem = $saldo_origem_atual - $valor;
            
            $sql_atualiza_origem = "UPDATE contas_financeiras 
                                   SET saldo_inicial = ?, 
                                       data_saldo = ? 
                                   WHERE id = ?";
            $stmt_upd = $pdo->prepare($sql_atualiza_origem);
            $stmt_upd->execute([$novo_saldo_origem, date('Y-m-d'), $conta_origem]);
            
            // 2. CREDITAR o valor na conta do usuário (Caixa Thiago) na tabela contas_financeiras
            $stmt_usuario_conta = $pdo->prepare("SELECT saldo_inicial FROM contas_financeiras WHERE id = ?");
            $stmt_usuario_conta->execute([$id_conta_usuario]);
            $saldo_usuario_atual = $stmt_usuario_conta->fetchColumn();
            
            $novo_saldo_usuario = $saldo_usuario_atual + $valor;
            
            $sql_atualiza_usuario = "UPDATE contas_financeiras 
                                    SET saldo_inicial = ?, 
                                        data_saldo = ? 
                                    WHERE id = ?";
            $stmt_upd_usr = $pdo->prepare($sql_atualiza_usuario);
            $stmt_upd_usr->execute([$novo_saldo_usuario, date('Y-m-d'), $id_conta_usuario]);
            
            // 3. Manter os lançamentos na tabela 'contas' para histórico (como estava antes)
            $desc_lancamento = "SUPRIMENTO DE CAIXA #" . $id_caixa_gerado . " (" . $dadosUser['nome'] . ")";
            $data_hoje = date('Y-m-d');

            $sqlSaida = "INSERT INTO contas (id_admin, natureza, categoria, id_conta_origem, entidade_tipo, id_entidade, descricao, documento, vencimento, valor_total, valor_parcela, status_baixa, data_pagamento, data_cadastro, id_caixa_referencia) 
                         VALUES (?, 'Despesa', '1', ?, 'usuario', ?, ?, 'SUPRIMENTO', ?, ?, ?, 'PAGO', NOW(), NOW(), ?)";
            
            $stmtSaida = $pdo->prepare($sqlSaida);
            $stmtSaida->execute([$id_admin, $conta_origem, $id_usuario, $desc_lancamento, $data_hoje, $valor, $valor, $id_caixa_gerado]);

            $sqlEntrada = "INSERT INTO contas (id_admin, natureza, categoria, id_conta_origem, entidade_tipo, id_entidade, descricao, documento, vencimento, valor_total, valor_parcela, status_baixa, data_pagamento, data_cadastro, id_caixa_referencia) 
                           VALUES (?, 'Receita', '1', ?, 'usuario', ?, ?, 'SUPRIMENTO', ?, ?, ?, 'PAGO', NOW(), NOW(), ?)";
            
            $stmtEntrada = $pdo->prepare($sqlEntrada);
            $stmtEntrada->execute([$id_admin, $id_conta_usuario, $id_usuario, $desc_lancamento, $data_hoje, $valor, $valor, $id_caixa_gerado]);
        }

        $pdo->commit();

        if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['status' => 'success', 'message' => 'Caixa aberto com sucesso!']);
            exit;
        } else {
            echo "<script>alert('Caixa aberto com sucesso!'); window.location.href='vendas/movimentacao_caixa.php';</script>";
            exit;
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        } else {
            echo "<script>alert('Erro: " . $e->getMessage() . "'); history.back();</script>";
            exit;
        }
    }
}

// ==========================================================
// 2. CARREGAMENTO DE DADOS (SELECTS)
// ==========================================================
try {
    $usuarios = $pdo->query("SELECT id, nome FROM usuarios WHERE id_admin = $id_admin AND ativo = 1 ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
    $contas = $pdo->query("SELECT id, nome_conta FROM contas_financeiras WHERE id_admin = $id_admin AND status = 'Ativo' ORDER BY nome_conta ASC")->fetchAll(PDO::FETCH_ASSOC);
    $stmtFormas = $pdo->prepare("SELECT id, nome_forma FROM formas_pagamento WHERE id_admin = ? AND nome_forma = 'DINHEIRO'");
    $stmtFormas->execute([$id_admin]);
    $formas = $stmtFormas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}

$titulo_pagina = "Abrir Caixa";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    
    <!-- CSS CENTRALIZADO -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/menu.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/formularios.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        /* CORES PADRONIZADAS */
        :root { 
            --fundo: #ecf0f5;
            --texto-dark: #333;
            --azul: #17a2b8;
            --verde: #28a745;
            --vermelho: #b92426;
            --roxo: #622599;
            --laranja: #f39c12;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; }
        
        body {
            font-family: 'Exo', 'Source Sans Pro', sans-serif;
            background-color: var(--fundo);
            color: var(--texto-dark);
            min-height: 100vh;
            font-size: 15px;
        }

        /* HEADER DA PÁGINA */
        .page-header-actions {
            margin-bottom: 25px;
        }
        
        .btn-cancel {
            background: white;
            color: #666;
            border: 2px solid #e0e0e0;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            font-size: 15px;
            transition: all 0.3s;
            font-family: 'Exo', sans-serif;
        }
        .btn-cancel:hover {
            background: #f8f9fa;
            border-color: var(--roxo);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .btn-cancel i {
            margin-right: 8px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #444;
            margin: 0 0 25px 0;
            font-family: 'Exo', sans-serif;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .page-title::before {
            content: '';
            width: 5px;
            height: 32px;
            background: linear-gradient(135deg, var(--roxo), #8e44ad);
            border-radius: 3px;
        }

        /* CARD DO FORMULÁRIO */
        .card-form {
            background: #fff;
            border-radius: 16px;
            padding: 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            width: 100%;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .card-header {
            background: linear-gradient(135deg, var(--roxo) 0%, #8e44ad 100%);
            color: #fff;
            padding: 20px 30px;
            font-size: 18px;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .card-header i {
            font-size: 22px;
        }

        .card-body {
            padding: 35px;
        }

        /* GRID DO FORMULÁRIO */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
        }
        
        .full { grid-column: span 4; }
        .half { grid-column: span 2; }
        .quarter { grid-column: span 1; }
        
        @media (max-width: 992px) {
            .quarter { grid-column: span 2; }
            .half { grid-column: span 4; }
        }

        /* CAMPOS DO FORMULÁRIO */
        .form-group label {
            font-size: 14px;
            font-weight: 700;
            color: #444;
            margin-bottom: 10px;
            display: block;
            font-family: 'Exo', sans-serif;
        }

        .form-control {
            width: 100%;
            height: 48px;
            padding: 0 16px;
            font-size: 15px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            background-color: #fff;
            transition: all 0.3s;
            font-family: 'Exo', sans-serif;
            color: #333;
        }
        
        .form-control:focus {
            border-color: var(--roxo);
            box-shadow: 0 0 0 4px rgba(98, 37, 153, 0.1);
        }
        
        textarea.form-control {
            height: auto;
            padding: 14px 16px;
            resize: vertical;
            min-height: 100px;
        }

        select.form-control {
            cursor: pointer;
        }

        /* DIVISOR DE SEÇÃO */
        .section-divider {
            grid-column: span 4;
            margin-top: 20px;
            margin-bottom: 15px;
            border-bottom: 3px solid;
            border-image: linear-gradient(135deg, var(--roxo), #8e44ad) 1;
            padding-bottom: 12px;
        }

        .section-title {
            font-size: 17px;
            font-weight: 700;
            color: #444;
            font-family: 'Exo', sans-serif;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--roxo), #8e44ad);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 15px;
        }

        /* FOOTER DO CARD */
        .card-footer {
            background: #f8f9fa;
            padding: 25px 35px;
            border-top: 2px solid #f0f0f0;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }

        .btn-save {
            background: linear-gradient(135deg, var(--verde) 0%, #218838 100%);
            color: #fff;
            border: none;
            padding: 14px 40px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            font-size: 15px;
            text-transform: uppercase;
            transition: all 0.3s;
            font-family: 'Exo', sans-serif;
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.2);
            letter-spacing: 0.5px;
        }
        
        .btn-save:hover {
            background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(40, 167, 69, 0.3);
        }
        
        .btn-save:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .btn-save i {
            margin-right: 8px;
        }

        /* PLACEHOLDER PERSONALIZADO */
        .form-control::placeholder {
            color: #999;
            opacity: 1;
        }

        /* ANIMAÇÕES */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .card-form {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include 'menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include 'menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <div class="page-header-actions">
            <a href="vendas/movimentacao_caixa.php" class="btn-cancel">
                <i class="fas fa-arrow-left"></i> Voltar para Movimentação
            </a>
        </div>

        <h2 class="page-title"><?= $titulo_pagina ?></h2>

        <form id="formAbrirCaixa">
            <input type="hidden" name="acao" value="abrir_caixa">
            <input type="hidden" name="csrf_token" value="<?= Csrf::getToken() ?>">

            <div class="card-form">
                <div class="card-header">
                    <i class="fas fa-cash-register"></i>
                    Dados de Abertura do Caixa
                </div>
                
                <div class="card-body">
                    <div class="form-grid">
                        
                        <div class="form-group half">
                            <label><i class="fas fa-user"></i> Usuário *</label>
                            <select name="id_usuario" class="form-control" required>
                                <option value="">Selecione o usuário...</option>
                                <?php foreach($usuarios as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= strtoupper($u['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group quarter">
                            <label><i class="far fa-calendar"></i> Data *</label>
                            <input type="date" name="data_abertura" class="form-control" required>
                        </div>
                        
                        <div class="form-group quarter">
                            <label><i class="far fa-clock"></i> Hora *</label>
                            <input type="time" name="hora_abertura" class="form-control" required>
                        </div>

                        <div class="section-divider">
                            <span class="section-title">
                                <i class="fas fa-money-bill-wave"></i>
                                Suprimento Inicial (Fundo de Troco)
                            </span>
                        </div>

                        <div class="form-group full">
                            <label><i class="fas fa-university"></i> Conta de Origem (De onde sai o dinheiro)</label>
                            <select name="conta_origem" class="form-control">
                                <option value="">Selecione a conta...</option>
                                <?php foreach($contas as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= strtoupper($c['nome_conta']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group quarter">
                            <label><i class="fas fa-dollar-sign"></i> Valor (R$)</label>
                            <input type="text" name="valor" id="valor_suprimento" class="form-control" placeholder="0,00">
                        </div>
                        
                        <div class="form-group" style="grid-column: span 3;"> 
                            <label><i class="fas fa-credit-card"></i> Forma de Pagamento</label>
                            <select name="forma_pagamento" class="form-control" required>
                                <?php foreach($formas as $f): ?>
                                    <option value="<?= $f['id'] ?>" selected><?= strtoupper($f['nome_forma']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group full">
                            <label><i class="fas fa-align-left"></i> Descrição / Observações</label>
                            <textarea name="descricao" class="form-control" rows="3" placeholder="Ex: Fundo de troco inicial para operação do caixa"></textarea>
                        </div>

                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn-save">
                        <i class="fas fa-check-circle"></i> CONFIRMAR ABERTURA DO CAIXA
                    </button>
                </div>
            </div>
        </form>

    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function(){
            // Máscara de dinheiro
            $('#valor_suprimento').mask('#.##0,00', {reverse: true});

            // Forçar Data e Hora atuais
            const now = new Date();
            const day = ("0" + now.getDate()).slice(-2);
            const month = ("0" + (now.getMonth() + 1)).slice(-2);
            const year = now.getFullYear();
            const hours = ("0" + now.getHours()).slice(-2);
            const minutes = ("0" + now.getMinutes()).slice(-2);

            $('input[name="data_abertura"]').val(year + "-" + month + "-" + day);
            $('input[name="hora_abertura"]').val(hours + ":" + minutes);

            // AJAX Submit com SweetAlert2
            $('#formAbrirCaixa').on('submit', function(e) {
                e.preventDefault();
                const btn = $(this).find('button[type="submit"]');
                const txtOriginal = btn.html();
                
                btn.html('<i class="fas fa-spinner fa-spin"></i> Processando...').prop('disabled', true);

                $.ajax({
                    url: 'abrir_caixa.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                title: 'Sucesso!',
                                text: res.message,
                                icon: 'success',
                                confirmButtonColor: '#28a745',
                                confirmButtonText: '<i class="fas fa-check"></i> OK, ir para Movimentação',
                                width: '450px',
                                allowOutsideClick: false,
                                customClass: {
                                    popup: 'swal-popup-modern',
                                    confirmButton: 'swal-btn-success'
                                }
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = 'vendas/movimentacao_caixa.php';
                                }
                            });
                        } else {
                            Swal.fire({
                                title: 'Atenção!',
                                text: res.message,
                                icon: 'warning',
                                confirmButtonColor: '#f39c12',
                                confirmButtonText: '<i class="fas fa-edit"></i> Corrigir',
                                customClass: {
                                    popup: 'swal-popup-modern'
                                }
                            });
                            btn.html(txtOriginal).prop('disabled', false);
                        }
                    },
                    error: function() {
                        Swal.fire({
                            title: 'Erro!',
                            text: 'Ocorreu um erro de conexão com o servidor.',
                            icon: 'error',
                            confirmButtonColor: '#b92426',
                            confirmButtonText: '<i class="fas fa-times"></i> Fechar',
                            customClass: {
                                popup: 'swal-popup-modern'
                            }
                        });
                        btn.html(txtOriginal).prop('disabled', false);
                    }
                });
            });
        });
    </script>
    
    <style>
        /* SweetAlert2 Customizado */
        .swal-popup-modern {
            font-family: 'Exo', sans-serif !important;
            border-radius: 16px !important;
        }
        
        .swal-btn-success {
            font-family: 'Exo', sans-serif !important;
            font-weight: 700 !important;
            padding: 12px 30px !important;
            border-radius: 10px !important;
        }
    </style>
</body>
</html>