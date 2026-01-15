<?php
/**
 * ZIIPVET - Movimentação de Caixa
 * ARQUIVO: movimentacao_caixa.php
 * LOCALIZAÇÃO: /app/vendas/
 */

$base_path = dirname(__DIR__) . '/'; 

require_once $base_path . 'auth.php';
require_once $base_path . 'config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;

// PROCESSAMENTO DE ENCERRAMENTO (ADMIN)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'encerrar_caixa') {
    try {
        $pdo->beginTransaction();

        $id_caixa = $_POST['id_caixa_fechar'];
        $valor_fechamento = str_replace(['R$', '.', ','], ['', '', '.'], $_POST['valor_fechamento']);
        $id_conta_destino = $_POST['conta_destino'];
        $data_fechamento = date('Y-m-d H:i:s');

        $stmtCaixa = $pdo->prepare("SELECT id_usuario FROM caixas WHERE id = ?");
        $stmtCaixa->execute([$id_caixa]);
        $id_usuario_caixa = $stmtCaixa->fetchColumn();

        $stmtUser = $pdo->prepare("SELECT id_conta_caixa, nome FROM usuarios WHERE id = ?");
        $stmtUser->execute([$id_usuario_caixa]);
        $dadosUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $id_conta_usuario = $dadosUser['id_conta_caixa']; 

        $sql = "UPDATE caixas SET 
                status = 'ENCERRADO', 
                data_fechamento = :data, 
                valor_fechamento = :valor, 
                id_conta_fechamento = :conta 
                WHERE id = :id AND id_admin = :admin";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':data' => $data_fechamento,
            ':valor' => $valor_fechamento,
            ':conta' => $id_conta_destino,
            ':id' => $id_caixa,
            ':admin' => $id_admin
        ]);

        if ($id_conta_destino && $valor_fechamento > 0) {
            $desc_lancamento = "ENCERRAMENTO DE CAIXA #" . $id_caixa . " (" . $dadosUser['nome'] . ")";
            
            $sqlSai = "INSERT INTO contas (id_admin, natureza, categoria, id_conta_origem, entidade_tipo, id_entidade, descricao, documento, vencimento, valor_total, valor_parcela, status_baixa, data_pagamento, data_cadastro, id_caixa_referencia)
                       VALUES (?, 'Despesa', '1', ?, 'usuario', ?, ?, 'ENCERRAMENTO', NOW(), ?, ?, 'PAGO', NOW(), NOW(), ?)";
            $pdo->prepare($sqlSai)->execute([$id_admin, $id_conta_usuario, $id_usuario_caixa, "SAÍDA: " . $desc_lancamento, $valor_fechamento, $valor_fechamento, $id_caixa]);

            $sqlEnt = "INSERT INTO contas (id_admin, natureza, categoria, id_conta_origem, entidade_tipo, id_entidade, descricao, documento, vencimento, valor_total, valor_parcela, status_baixa, data_pagamento, data_cadastro, id_caixa_referencia)
                       VALUES (?, 'Receita', '1', ?, 'usuario', ?, ?, 'ENCERRAMENTO', NOW(), ?, ?, 'PAGO', NOW(), NOW(), ?)";
            $pdo->prepare($sqlEnt)->execute([$id_admin, $id_conta_destino, $id_usuario_caixa, "ENTRADA: " . $desc_lancamento, $valor_fechamento, $valor_fechamento, $id_caixa]);
        }

        $pdo->commit();
        echo "<script>window.location.href='movimentacao_caixa.php?msg=sucesso';</script>";
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erro ao encerrar caixa: " . $e->getMessage());
    }
}

// LISTAGEM E FILTROS
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');
$filtro_usuario = $_GET['id_usuario'] ?? '';
$filtro_status = $_GET['status'] ?? '';

$pagina_atual = (isset($_GET['pagina']) && is_numeric($_GET['pagina'])) ? (int)$_GET['pagina'] : 1;
$itens_per_page = 20;
$offset = ($pagina_atual - 1) * $itens_per_page;

$sql = "SELECT c.*, u.nome as nome_usuario 
        FROM caixas c 
        LEFT JOIN usuarios u ON c.id_usuario = u.id 
        WHERE c.id_admin = :id_admin";

$params = [':id_admin' => $id_admin];

if (!empty($data_inicio) && !empty($data_fim)) {
    $sql .= " AND c.data_abertura BETWEEN :data_inicio AND :data_fim";
    $params[':data_inicio'] = $data_inicio;
    $params[':data_fim'] = $data_fim;
}
if (!empty($filtro_usuario)) {
    $sql .= " AND c.id_usuario = :id_usuario";
    $params[':id_usuario'] = $filtro_usuario;
}
if (!empty($filtro_status)) {
    $sql .= " AND c.status = :status";
    $params[':status'] = $filtro_status;
}

$stmtCount = $pdo->prepare(str_replace('c.*, u.nome as nome_usuario', 'COUNT(*) as total', $sql));
$stmtCount->execute($params);
$total_registros = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$total_paginas = ceil($total_registros / $itens_per_page);

$sql .= " ORDER BY c.id DESC LIMIT " . (int)$offset . ", " . (int)$itens_per_page;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$lista_usuarios = $pdo->prepare("SELECT id, nome FROM usuarios WHERE id_admin = ? ORDER BY nome ASC");
$lista_usuarios->execute([$id_admin]);
$lista_usuarios = $lista_usuarios->fetchAll(PDO::FETCH_ASSOC);

$lista_contas = $pdo->prepare("SELECT id, nome_conta FROM contas_financeiras WHERE id_admin = ? AND status = 'Ativo'");
$lista_contas->execute([$id_admin]);
$lista_contas = $lista_contas->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = "Movimentação de Caixas";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    
    <link rel="stylesheet" href="<?= URL_BASE ?>css/style.css">
    <link rel="stylesheet" href="<?= URL_BASE ?>css/menu.css">
    <link rel="stylesheet" href="<?= URL_BASE ?>css/header.css">
    <link rel="stylesheet" href="<?= URL_BASE ?>css/formularios.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { 
            --fundo: #ecf0f5;
            --azul: #17a2b8;
            --verde: #28a745;
            --vermelho: #b92426;
            --roxo: #622599;
            --laranja: #f39c12;
        }
        
        body {
            font-family: 'Exo', 'Source Sans Pro', sans-serif;
            background-color: var(--fundo);
        }
        
        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .form-title {
            font-size: 28px;
            font-weight: 700;
            color: #444;
            font-family: 'Exo', sans-serif;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .btn-voltar {
            background: linear-gradient(135deg, var(--verde) 0%, #218838 100%);
            color: #fff;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-family: 'Exo', sans-serif;
        }
        
        .btn-voltar:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(40, 167, 69, 0.3);
        }
        
        .list-container {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        
        .filters-box {
            background: #f8f9fa;
            padding: 25px;
            border-bottom: 3px solid;
            border-image: linear-gradient(135deg, var(--roxo), #8e44ad) 1;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: flex-end;
        }
        
        .filter-group label {
            font-size: 14px;
            font-weight: 700;
            color: #444;
            margin-bottom: 8px;
            display: block;
            font-family: 'Exo', sans-serif;
        }
        
        .form-control {
            width: 100%;
            height: 45px;
            padding: 0 14px;
            font-size: 15px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-family: 'Exo', sans-serif;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--roxo), #8e44ad);
            color: #fff;
            border: none;
            padding: 0 24px;
            height: 45px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            font-family: 'Exo', sans-serif;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(98, 37, 153, 0.3);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead tr {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        
        th {
            padding: 18px 15px;
            text-align: left;
            font-weight: 700;
            color: #444;
            font-family: 'Exo', sans-serif;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        td {
            padding: 15px;
            font-family: 'Exo', sans-serif;
            font-size: 14px;
        }
        
        .caixa-code {
            background: linear-gradient(135deg, var(--roxo), #8e44ad);
            color: #fff;
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 700;
            display: inline-block;
        }
        
        .btn-view {
            background: linear-gradient(135deg, var(--azul), #138496);
            color: #fff;
            padding: 8px 12px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(23, 162, 184, 0.3);
        }
        
        .btn-fechar-caixa {
            background: linear-gradient(135deg, var(--laranja), #e08e0b);
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            transition: all 0.3s;
            font-family: 'Exo', sans-serif;
        }
        
        .btn-fechar-caixa:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(243, 156, 12, 0.3);
        }
        
        .status-aberto {
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            background: var(--verde);
            padding: 6px 12px;
            border-radius: 8px;
            display: inline-block;
            margin-right: 5px;
        }

        .btn-icon-action {
            background: linear-gradient(135deg, var(--laranja), #e08e0b);
            color: #fff;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: middle;
        }

        .btn-icon-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(224, 142, 11, 0.4);
        }

        .status-fechado {
            color: var(--verde);
            font-size: 13px;
            font-weight: 700;
            background: #d4edda;
            padding: 6px 12px;
            border-radius: 8px;
            display: inline-block;
        }
        
        .modal-overlay {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.show {
            display: flex;
        }
        
        .modal-dialog {
            background: #fff;
            width: 90%;
            max-width: 500px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--laranja), #e08e0b);
            color: #fff;
            padding: 20px 25px;
            font-size: 18px;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .modal-footer {
            padding: 20px 30px;
            background: #f8f9fa;
            border-top: 2px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
        }
        
        .btn-cancel {
            background: #e0e0e0;
            color: #666;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            font-family: 'Exo', sans-serif;
            transition: all 0.3s;
        }
        
        .btn-cancel:hover {
            background: #d0d0d0;
        }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include $base_path . 'menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include $base_path . 'menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <div class="form-header">
            <h1 class="form-title">
                <i class="fas fa-cash-register"></i>
                Movimentação de Caixas
            </h1>
            <!-- 🔧 LINK CORRIGIDO: Remove "vendas/" do caminho -->
            <a href="../abrir_caixa.php" class="btn-voltar">
                <i class="fas fa-plus"></i> Abrir Novo Caixa
            </a>
        </div>

        <div class="list-container">
            <div class="filters-box">
                <form method="GET" class="filters-grid">
                    <div class="filter-group">
                        <label>Data Início</label>
                        <input type="date" name="data_inicio" class="form-control" value="<?= $data_inicio ?>">
                    </div>
                    <div class="filter-group">
                        <label>Data Fim</label>
                        <input type="date" name="data_fim" class="form-control" value="<?= $data_fim ?>">
                    </div>
                    <div class="filter-group">
                        <label>Usuário</label>
                        <select name="id_usuario" class="form-control">
                            <option value="">Todos os usuários</option>
                            <?php foreach($lista_usuarios as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= $filtro_usuario == $u['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                </form>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Cód</th>
                            <th>Abertura</th>
                            <th>Fechamento</th>
                            <th>Usuário</th>
                            <th>Valor</th>
                            <th style="text-align: right; padding-right: 20px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($movimentos)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: #999;">
                                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>
                                Nenhum caixa encontrado
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach($movimentos as $mov): ?>
                            <tr>
                                <td><span class="caixa-code">#<?= $mov['id'] ?></span></td>
                                <td><?= date('d/m/Y H:i', strtotime($mov['data_abertura'].' '.$mov['hora_abertura'])) ?></td>
                                <td><?= $mov['data_fechamento'] ? date('d/m/Y H:i', strtotime($mov['data_fechamento'])) : '<span style="color: #999;">---</span>' ?></td>
                                <td><?= htmlspecialchars($mov['nome_usuario']) ?></td>
                                <td style="color: var(--verde); font-weight: 700;">
                                    R$ <?= number_format($mov['valor_fechamento'] ?? $mov['valor_inicial'], 2, ',', '.') ?>
                                </td>
                                <td style="text-align: right; padding-right: 20px;">
                                    <a href="detalhes_movimentacao.php?id=<?= $mov['id'] ?>" class="btn-view" title="Ver Detalhes">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <?php if($mov['status'] == 'ABERTO'): ?>
                                        <span class="status-aberto">
                                            <i class="fas fa-check-circle"></i> ABERTO
                                        </span>
                                    <?php elseif($mov['status'] == 'FECHADO'): ?>
                                        <span class="status-fechado" style="background:var(--laranja); color:#fff; font-weight:700; padding:6px 12px; border-radius:8px; display:inline-block; margin-right:5px;">
                                            <i class="fas fa-clock"></i> FECHADO
                                        </span>
                                        <button class="btn-icon-action" title="Encerrar Caixa (Admin)" onclick='abrirModalFechamento(<?= json_encode($mov) ?>)'>
                                            <i class="fas fa-lock"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="status-fechado" style="background:#d4edda; color:var(--verde);">
                                            <i class="fas fa-check-double"></i> ENCERRADO
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- MODAL DE FECHAMENTO -->
    <div id="modalFechamento" class="modal-overlay">
        <div class="modal-dialog">
            <form method="POST">
                <input type="hidden" name="acao" value="encerrar_caixa">
                <input type="hidden" name="id_caixa_fechar" id="modal_id_caixa">
                
                <div class="modal-header">
                    <i class="fas fa-lock"></i> Encerrar Caixa (Admin)
                </div>
                
                <div class="modal-body">
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="font-weight: 700; font-family: 'Exo', sans-serif; margin-bottom: 10px; display: block;">
                            Valor em Dinheiro (Conferência) *
                        </label>
                        <input type="text" name="valor_fechamento" class="form-control" id="modal_valor" required placeholder="R$ 0,00">
                    </div>
                    
                    <div class="form-group">
                        <label style="font-weight: 700; font-family: 'Exo', sans-serif; margin-bottom: 10px; display: block;">
                            Enviar para Conta *
                        </label>
                        <select name="conta_destino" class="form-control" required>
                            <option value="">Selecione a conta...</option>
                            <?php foreach($lista_contas as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome_conta']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" onclick="document.getElementById('modalFechamento').classList.remove('show')" class="btn-cancel">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-primary" style="padding: 12px 30px;">
                        <i class="fas fa-check"></i> Confirmar Fechamento
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    
    <script>
        function abrirModalFechamento(dados) {
            document.getElementById('modal_id_caixa').value = dados.id;
            document.getElementById('modalFechamento').classList.add('show');
        }
        
        $(document).ready(function(){
            $('#modal_valor').mask('#.##0,00', {reverse: true});
        });
    </script>
</body>
</html>