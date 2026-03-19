<?php
/**
 * ZIIPVET - Movimentação de Caixa
 * ARQUIVO: movimentacao_caixa.php
 * LOCALIZAÇÃO: /app/vendas/
 */

$base_path = dirname(__DIR__) . '/'; 
$path_prefix = '../';

require_once $base_path . 'auth.php';
require_once $base_path . 'config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;
$id_usuario_logado = (int)($_SESSION['usuario_id'] ?? 0);

// Apenas administradores podem ENCERRAR (fazer o "cadeado").
// A forma de detectar "admin" depende da configuração do sistema via permissões.
$podeEncerrarCaixa = false;
try {
    $isAdminPorCargo = false;
    try {
        $stmtAdmin = $pdo->prepare("
            SELECT u.id_cargo, COALESCE(c.nome_cargo, '') AS nome_cargo
            FROM usuarios u
            LEFT JOIN cargos c ON c.id = u.id_cargo
            WHERE u.id = ? AND u.id_admin = ?
            LIMIT 1
        ");
        $stmtAdmin->execute([$id_usuario_logado, $id_admin]);
        $rowAdmin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
        $nomeCargo = strtoupper(trim((string)($rowAdmin['nome_cargo'] ?? '')));
        $idCargo = (int)($rowAdmin['id_cargo'] ?? 0);
        $isAdminPorCargo = ($idCargo === 1) || str_contains($nomeCargo, 'ADMIN');
    } catch (Throwable $e) {
        $isAdminPorCargo = false;
    }

    $podeEncerrarCaixa =
        (($id_usuario_logado > 0) && ($id_usuario_logado === (int)$id_admin))
        || $isAdminPorCargo
        || temPermissao('vendas', 'encerrar_caixa')
        || temPermissao('vendas', 'encerrar')
        || temPermissao('relatorios', 'fechamento_caixa');
} catch (Throwable $e) {
    $podeEncerrarCaixa = false;
}

/**
 * Reparos defensivos para ambiente com schema antigo da tabela `caixas`.
 * - Corrige caixas com id=0 usando pk_id (quando existir).
 * - Preenche data_fechamento ausente em caixas já fechados/encerrados.
 */
$hasPkIdCaixas = false;
try {
    $hasPkIdCaixas = (bool)$pdo->query("SHOW COLUMNS FROM caixas LIKE 'pk_id'")->fetch(PDO::FETCH_ASSOC);

    if ($hasPkIdCaixas) {
        $stmtZero = $pdo->prepare("SELECT pk_id FROM caixas WHERE id_admin = ? AND (id IS NULL OR id = 0) ORDER BY pk_id ASC");
        $stmtZero->execute([$id_admin]);
        $rowsZero = $stmtZero->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rowsZero)) {
            $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(id), 0) FROM caixas WHERE id_admin = ? AND id > 0");
            $stmtMax->execute([$id_admin]);
            $nextId = (int)$stmtMax->fetchColumn() + 1;

            $stmtFixId = $pdo->prepare("UPDATE caixas SET id = ? WHERE pk_id = ? AND (id IS NULL OR id = 0)");
            foreach ($rowsZero as $r0) {
                $stmtFixId->execute([$nextId, (int)$r0['pk_id']]);
                $nextId++;
            }
        }
    }

    $stmtFixFech = $pdo->prepare("
        UPDATE caixas
        SET data_fechamento = COALESCE(NULLIF(data_fechamento, '0000-00-00 00:00:00'), data_cadastro, NOW())
        WHERE id_admin = ?
          AND status IN ('FECHADO', 'ENCERRADO')
          AND (data_fechamento IS NULL OR data_fechamento = '0000-00-00 00:00:00')
    ");
    $stmtFixFech->execute([$id_admin]);
} catch (Throwable $e) {
    // Não quebrar tela por falha de auto-reparo.
}

// PROCESSAMENTO DE EXCLUSÃO (ADMIN)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excluir_caixa') {
    ob_clean();
    header('Content-Type: application/json');

    try {
        $pdo->beginTransaction();

        $id_caixa = $_POST['id'];

        // 1. Busca dados do caixa
        $stmtCaixa = $pdo->prepare("SELECT * FROM caixas WHERE id = ? AND id_admin = ?");
        $stmtCaixa->execute([$id_caixa, $id_admin]);
        $caixa = $stmtCaixa->fetch(PDO::FETCH_ASSOC);

        if (!$caixa) {
            throw new Exception("Caixa não encontrado.");
        }

        // 2. Busca conta do usuário
        $stmtUser = $pdo->prepare("SELECT id_conta_caixa FROM usuarios WHERE id = ?");
        $stmtUser->execute([$caixa['id_usuario']]);
        $id_conta_usuario = $stmtUser->fetchColumn();

        // 3. Estornar valor inicial (Suprimento) se houver tabela 'contas_financeiras'
        if ($caixa['valor_inicial'] > 0 && !empty($caixa['id_conta_origem']) && !empty($id_conta_usuario)) {
            $valor = $caixa['valor_inicial'];
            $conta_origem = $caixa['id_conta_origem'];
            $hoje = date('Y-m-d');

            // Devolver dinheiro para a Origem (Ex: Fundo Fixo)
            $pdo->prepare("UPDATE contas_financeiras SET saldo_inicial = saldo_inicial + ?, data_saldo = ? WHERE id = ?")
                ->execute([$valor, $hoje, $conta_origem]);

            // Tirar dinheiro da conta do Usuário (Ex: Caixa Thiago)
            $pdo->prepare("UPDATE contas_financeiras SET saldo_inicial = saldo_inicial - ?, data_saldo = ? WHERE id = ?")
                ->execute([$valor, $hoje, $id_conta_usuario]);
        }
        
        // 4. Excluir lançamentos financeiros vinculados a este caixa
        $pdo->prepare("DELETE FROM contas WHERE id_caixa_referencia = ?")->execute([$id_caixa]);
        
        // 5. Excluir o caixa
        $pdo->prepare("DELETE FROM caixas WHERE id = ?")->execute([$id_caixa]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Caixa excluído e valores estornados com sucesso!']);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Erro: ' . $e->getMessage()]);
        exit;
    }
}

// PROCESSAMENTO DE ENCERRAMENTO (ADMIN)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'encerrar_caixa') {
    try {
        if (!$podeEncerrarCaixa) {
            echo "<script>window.location.href='movimentacao_caixa.php?msg=sem_permissao';</script>";
            exit;
        }

        $pdo->beginTransaction();

        $id_caixa_recebido = (int)($_POST['id_caixa_fechar'] ?? 0);
        $valor_fechamento = str_replace(['R$', '.', ','], ['', '', '.'], $_POST['valor_fechamento']);
        $id_conta_destino = $_POST['conta_destino'];
        $data_fechamento = date('Y-m-d H:i:s');

        $hasPkIdCaixas = false;
        try {
            $hasPkIdCaixas = (bool)$pdo->query("SHOW COLUMNS FROM caixas LIKE 'pk_id'")->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $hasPkIdCaixas = false;
        }

        // Localizar o caixa pelo identificador real (id ou pk_id) para evitar divergência de schema legado.
        $whereField = 'id';
        $caixaRow = null;

        $stmtCaixa = $pdo->prepare("SELECT id, pk_id, id_usuario, status FROM caixas WHERE id = ? AND id_admin = ? LIMIT 1");
        $stmtCaixa->execute([$id_caixa_recebido, $id_admin]);
        $caixaRow = $stmtCaixa->fetch(PDO::FETCH_ASSOC);

        if (!$caixaRow && $hasPkIdCaixas) {
            $whereField = 'pk_id';
            $stmtCaixa = $pdo->prepare("SELECT id, pk_id, id_usuario, status FROM caixas WHERE pk_id = ? AND id_admin = ? LIMIT 1");
            $stmtCaixa->execute([$id_caixa_recebido, $id_admin]);
            $caixaRow = $stmtCaixa->fetch(PDO::FETCH_ASSOC);
        }

        if (!$caixaRow) {
            throw new Exception('Caixa não encontrado para encerramento.');
        }

        $statusAtual = strtoupper(trim((string)($caixaRow['status'] ?? '')));
        if ($statusAtual !== 'FECHADO') {
            throw new Exception("Encerramento permitido apenas para caixas em status FECHADO. Status atual: {$statusAtual}");
        }

        $id_usuario_caixa = (int)$caixaRow['id_usuario'];
        $id_caixa_ref = ($whereField === 'pk_id') ? (int)$caixaRow['pk_id'] : (int)$caixaRow['id'];

        $stmtUser = $pdo->prepare("SELECT id_conta_caixa, nome FROM usuarios WHERE id = ?");
        $stmtUser->execute([$id_usuario_caixa]);
        $dadosUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $id_conta_usuario = $dadosUser['id_conta_caixa']; 

        if ($whereField === 'id') {
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
                ':id' => $id_caixa_ref,
                ':admin' => $id_admin
            ]);
        } else {
            $sql = "UPDATE caixas SET 
                    status = 'ENCERRADO', 
                    data_fechamento = :data, 
                    valor_fechamento = :valor, 
                    id_conta_fechamento = :conta 
                    WHERE pk_id = :id AND id_admin = :admin";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':data' => $data_fechamento,
                ':valor' => $valor_fechamento,
                ':conta' => $id_conta_destino,
                ':id' => $id_caixa_ref,
                ':admin' => $id_admin
            ]);
        }

        if ($id_conta_destino && $valor_fechamento > 0) {
            $desc_lancamento = "ENCERRAMENTO DE CAIXA #" . $id_caixa_ref . " (" . $dadosUser['nome'] . ")";
            
            $sqlSai = "INSERT INTO contas (id_admin, natureza, categoria, id_conta_origem, entidade_tipo, id_entidade, descricao, documento, vencimento, valor_total, valor_parcela, status_baixa, data_pagamento, data_cadastro, id_caixa_referencia)
                       VALUES (?, 'Despesa', '1', ?, 'usuario', ?, ?, 'ENCERRAMENTO', NOW(), ?, ?, 'PAGO', NOW(), NOW(), ?)";
            $pdo->prepare($sqlSai)->execute([$id_admin, $id_conta_usuario, $id_usuario_caixa, "SAÍDA: " . $desc_lancamento, $valor_fechamento, $valor_fechamento, $id_caixa_ref]);

            $sqlEnt = "INSERT INTO contas (id_admin, natureza, categoria, id_conta_origem, entidade_tipo, id_entidade, descricao, documento, vencimento, valor_total, valor_parcela, status_baixa, data_pagamento, data_cadastro, id_caixa_referencia)
                       VALUES (?, 'Receita', '1', ?, 'usuario', ?, ?, 'ENCERRAMENTO', NOW(), ?, ?, 'PAGO', NOW(), NOW(), ?)";
            $pdo->prepare($sqlEnt)->execute([$id_admin, $id_conta_destino, $id_usuario_caixa, "ENTRADA: " . $desc_lancamento, $valor_fechamento, $valor_fechamento, $id_caixa_ref]);
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
$data_filtro = $_GET['data_filtro'] ?? '';
$filtro_id_caixa = $_GET['id_caixa'] ?? '';
$filtro_cod_caixa = $_GET['cod_caixa'] ?? '';
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

// Filtro por data específica
if (!empty($data_filtro)) {
    $sql .= " AND DATE(c.data_abertura) = :data_filtro";
    $params[':data_filtro'] = $data_filtro;
}

// Filtro por ID do caixa (dropdown)
if (!empty($filtro_id_caixa)) {
    $sql .= " AND c.id = :id_caixa";
    $params[':id_caixa'] = $filtro_id_caixa;
}

// Filtro por código do caixa (campo de texto)
if (!empty($filtro_cod_caixa)) {
    $sql .= " AND c.id = :cod_caixa";
    $params[':cod_caixa'] = $filtro_cod_caixa;
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

$formatarDataHoraLista = static function ($data, $hora = null): string {
    $dataStr = trim((string)$data);
    $horaStr = trim((string)$hora);

    if ($dataStr === '' || $dataStr === '0000-00-00' || $dataStr === '0000-00-00 00:00:00') {
        return '<span style="color: #999;">---</span>';
    }

    if ($horaStr !== '' && !str_contains($dataStr, ':')) {
        $dataStr .= ' ' . $horaStr;
    }

    $ts = strtotime($dataStr);
    if ($ts === false || $ts <= 0) {
        return '<span style="color: #999;">---</span>';
    }
    return date('d/m/Y H:i', $ts);
};

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
        
        .date-preset-option {
            display: block;
            padding: 12px 20px;
            color: #495057;
            text-decoration: none;
            font-size: 14px;
            font-family: 'Exo', sans-serif;
            transition: background 0.2s;
            cursor: pointer;
        }
        
        .date-preset-option:hover {
            background: #f0f7ff;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
        }
        
        .calendar-day-header {
            text-align: center;
            font-size: 11px;
            font-weight: 600;
            color: #6c757d;
            padding: 8px 4px;
            font-family: 'Exo', sans-serif;
        }
        
        .calendar-day {
            text-align: center;
            padding: 8px 4px;
            font-size: 13px;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s;
            font-family: 'Exo', sans-serif;
        }
        
        .calendar-day:hover {
            background: #e3f2fd;
        }
        
        .calendar-day.other-month {
            color: #ccc;
        }
        
        .calendar-day.selected {
            background: #1e40af;
            color: white;
            font-weight: 600;
        }
        
        .calendar-day.in-range {
            background: #e3f2fd;
            color: #1e40af;
        }
        
        .calendar-day.today {
            border: 2px solid #1e40af;
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
            <a href="../abrir_caixa.php" class="btn-voltar">
                <i class="fas fa-plus"></i> Abrir Novo Caixa
            </a>
        </div>

        <div class="list-container">
            <div class="filters-box">
                <form method="GET" id="formFiltros" style="display: flex; align-items: center; gap: 15px; flex-wrap: nowrap; overflow-x: auto;">
                    <!-- Calendário com Dropdown -->
                    <div class="filter-group" style="min-width: 200px; max-width: 280px; flex-shrink: 0; position: relative;">
                        <button type="button" id="btnDatePicker" class="form-control" style="display: flex; align-items: center; gap: 8px; cursor: pointer; background: white; white-space: nowrap; border: 2px solid #e5e7eb; height: 45px;">
                            <i class="fas fa-calendar" style="color: #666; flex-shrink: 0;"></i>
                            <span id="dateDisplay" style="flex: 1; overflow: hidden; text-overflow: ellipsis;"><?= !empty($_GET['data_filtro']) ? date('d/m/Y', strtotime($_GET['data_filtro'])) : date('d/m/Y') ?></span>
                            <i class="fas fa-caret-down" style="color: #666; flex-shrink: 0;"></i>
                        </button>
                        
                        <!-- Hidden input para enviar a data -->
                        <input type="hidden" name="data_filtro" id="data_filtro" value="<?= $_GET['data_filtro'] ?? date('Y-m-d') ?>">
                        
                        <!-- Dropdown do Calendário -->
                        <div id="datePickerDropdown" style="display: none; position: fixed; margin-top: 5px; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 9999; min-width: 200px;">
                            <!-- Opções Predefinidas -->
                            <div id="presetOptions" style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                                <a href="javascript:void(0)" onclick="setDatePreset('hoje'); return false;" class="date-preset-option">
                                    <i class="fas fa-calendar-day" style="width: 20px; color: #1e40af;"></i> Hoje
                                </a>
                                <a href="javascript:void(0)" onclick="setDatePreset('ontem'); return false;" class="date-preset-option">
                                    <i class="fas fa-history" style="width: 20px; color: #1e40af;"></i> Ontem
                                </a>
                                <a href="javascript:void(0)" onclick="setDatePreset('ultimos7dias'); return false;" class="date-preset-option">
                                    <i class="fas fa-calendar-week" style="width: 20px; color: #1e40af;"></i> Últimos 7 dias
                                </a>
                                <a href="javascript:void(0)" onclick="setDatePreset('estemes'); return false;" class="date-preset-option">
                                    <i class="fas fa-calendar-alt" style="width: 20px; color: #1e40af;"></i> Este mês
                                </a>
                                <a href="javascript:void(0)" onclick="setDatePreset('mesanterior'); return false;" class="date-preset-option">
                                    <i class="fas fa-arrow-left" style="width: 20px; color: #1e40af;"></i> Mês anterior
                                </a>
                                <a href="javascript:void(0)" onclick="showCalendar(); return false;" class="date-preset-option" style="background: #f0f7ff; font-weight: 600; color: #1e40af;">
                                    <i class="fas fa-calendar-plus" style="width: 20px; color: #1e40af;"></i> Selecionar período
                                </a>
                            </div>
                            
                            <!-- Calendário Duplo (oculto inicialmente) -->
                            <div id="calendarView" style="display: none; min-width: 600px;">
                                <!-- Área de scroll para os calendários -->
                                <div style="max-height: 60vh; overflow-y: auto; padding: 15px;">
                                    <!-- Botão Voltar -->
                                    <div style="margin-bottom: 10px;">
                                        <button type="button" onclick="backToPresets()" style="background: none; border: none; color: #1e40af; cursor: pointer; font-size: 14px; font-family: 'Exo', sans-serif; display: flex; align-items: center; gap: 5px;">
                                            <i class="fas fa-arrow-left"></i> Voltar
                                        </button>
                                    </div>
                                    
                                    <div style="display: flex; gap: 20px;">
                                        <!-- Calendário Mês 1 (Azul) -->
                                        <div id="calendar1" style="flex: 1; background: #f0f7ff; padding: 15px; border-radius: 12px;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                                <button type="button" onclick="changeMonth(-1)" style="background: none; border: none; cursor: pointer; font-size: 18px; color: #1e40af;">
                                                    <i class="fas fa-chevron-left"></i>
                                                </button>
                                                <span id="month1Title" style="font-weight: 600; font-size: 14px; color: #1e40af;"></span>
                                                <div style="width: 24px;"></div>
                                            </div>
                                            <div id="month1Grid"></div>
                                        </div>
                                        
                                        <!-- Calendário Mês 2 (Verde) -->
                                        <div id="calendar2" style="flex: 1; background: #f0fdf4; padding: 15px; border-radius: 12px;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                                <div style="width: 24px;"></div>
                                                <span id="month2Title" style="font-weight: 600; font-size: 14px; color: #16a34a;"></span>
                                                <button type="button" onclick="changeMonth(1)" style="background: none; border: none; cursor: pointer; font-size: 18px; color: #16a34a;">
                                                    <i class="fas fa-chevron-right"></i>
                                                </button>
                                            </div>
                                            <div id="month2Grid"></div>
                                        </div>
                                    </div>
                                    
                                    <!-- Inputs ocultos para armazenar datas selecionadas -->
                                    <input type="hidden" id="selectedStartDate" value="">
                                    <input type="hidden" id="selectedEndDate" value="">
                                </div>
                                
                                <!-- Botão Aplicar - SEMPRE VISÍVEL -->
                                <div style="padding: 15px; border-top: 2px solid #e5e7eb; background: #f8f9fa; border-radius: 0 0 12px 12px;">
                                    <button type="button" id="btnApplyRange" onclick="applyDateRange()" class="btn-primary" style="width: 100%; padding: 12px;">
                                        <i class="fas fa-check"></i> <span id="applyButtonText">Selecione uma data</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dropdown Todos os Caixas -->
                    <div class="filter-group" style="min-width: 180px; max-width: 200px; flex-shrink: 0;">
                        <select name="id_caixa" class="form-control">
                            <option value="">Todos os caixas</option>
                            <?php 
                            $caixas_list = $pdo->prepare("SELECT id FROM caixas WHERE id_admin = ? ORDER BY id DESC");
                            $caixas_list->execute([$id_admin]);
                            $caixas_list = $caixas_list->fetchAll(PDO::FETCH_ASSOC);
                            foreach($caixas_list as $cx): 
                            ?>
                                <option value="<?= $cx['id'] ?>" <?= ($_GET['id_caixa'] ?? '') == $cx['id'] ? 'selected' : '' ?>>
                                    Caixa #<?= $cx['id'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Campo Cód. Caixa -->
                    <div class="filter-group" style="min-width: 120px; max-width: 140px; flex-shrink: 0;">
                        <input type="text" name="cod_caixa" class="form-control" 
                               placeholder="Cód. Caixa" 
                               value="<?= $_GET['cod_caixa'] ?? '' ?>">
                    </div>
                    
                    <!-- Dropdown Status -->
                    <div class="filter-group" style="min-width: 140px; max-width: 160px; flex-shrink: 0;">
                        <select name="status" class="form-control">
                            <option value="">Status</option>
                            <option value="ABERTO" <?= $filtro_status == 'ABERTO' ? 'selected' : '' ?>>Aberto</option>
                            <option value="FECHADO" <?= $filtro_status == 'FECHADO' ? 'selected' : '' ?>>Fechado</option>
                            <option value="ENCERRADO" <?= $filtro_status == 'ENCERRADO' ? 'selected' : '' ?>>Encerrado</option>
                            <option value="EM_REVISAO" <?= $filtro_status == 'EM_REVISAO' ? 'selected' : '' ?>>Em revisão</option>
                        </select>
                    </div>
                    
                    <!-- Botão Buscar -->
                    <button type="submit" class="btn-primary" style="min-width: 50px; max-width: 50px; padding: 0 20px; flex-shrink: 0;">
                        <i class="fas fa-search"></i>
                    </button>
                    
                    <!-- Botão Limpar Filtros -->
                    <a href="movimentacao_caixa.php" class="btn-cancel" style="display: inline-flex; align-items: center; justify-content: center; min-width: 50px; max-width: 50px; padding: 12px 20px; text-decoration: none; flex-shrink: 0;">
                        <i class="fas fa-undo"></i>
                    </a>
                </form>
                
                <!-- Botões de Navegação de Data -->
                <div style="margin-top: 15px; display: flex; gap: 10px; align-items: center;">
                    <button onclick="diaAnterior()" style="background: linear-gradient(135deg, var(--verde), #218838); color: #fff; border: none; padding: 10px 16px; border-radius: 10px; font-weight: 700; cursor: pointer; font-family: 'Exo', sans-serif; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fas fa-chevron-left"></i> Dia anterior
                    </button>
                    <button onclick="diaSeguinte()" style="background: linear-gradient(135deg, var(--verde), #218838); color: #fff; border: none; padding: 10px 16px; border-radius: 10px; font-weight: 700; cursor: pointer; font-family: 'Exo', sans-serif; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px;">
                        Dia seguinte <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
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
                            <?php
                                $idCaixaLinha = (int)($mov['id'] ?? 0);
                                if ($idCaixaLinha <= 0 && isset($mov['pk_id'])) {
                                    $idCaixaLinha = (int)$mov['pk_id'];
                                }
                            ?>
                            <tr>
                                <td><span class="caixa-code">#<?= $idCaixaLinha ?></span></td>
                                <td><?= $formatarDataHoraLista($mov['data_abertura'] ?? '', $mov['hora_abertura'] ?? '') ?></td>
                                <td><?= $formatarDataHoraLista($mov['data_fechamento'] ?? '', $mov['hora_fechamento'] ?? '') ?></td>
                                <td><?= htmlspecialchars($mov['nome_usuario']) ?></td>
                                <td style="color: var(--verde); font-weight: 700;">
                                    <?php
                                        $statusValor = strtoupper(trim((string)($mov['status'] ?? '')));
                                        $valorFech = (float)($mov['valor_fechamento'] ?? 0);
                                        $valorIni = (float)($mov['valor_inicial'] ?? 0);
                                        // Para ABERTO/FECHADO/EM_REVISAO, se valor_fechamento vier zerado, exibe o valor inicial.
                                        // ENCERRADO continua exibindo valor_fechamento consolidado.
                                        $valorExibir = ($statusValor === 'ENCERRADO')
                                            ? $valorFech
                                            : (($valorFech > 0) ? $valorFech : $valorIni);
                                    ?>
                                    R$ <?= number_format($valorExibir, 2, ',', '.') ?>
                                </td>
                                <td style="text-align: right; padding-right: 20px;">
                                    <a href="detalhes_movimentacao.php?id=<?= $idCaixaLinha ?>" class="btn-view" title="Ver Detalhes">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php $statusMov = strtoupper(trim((string)($mov['status'] ?? ''))); ?>
                                    <?php if($statusMov == 'ABERTO'): ?>
                                        <span class="status-aberto">
                                            <i class="fas fa-check-circle"></i> ABERTO
                                        </span>
                                    <?php elseif($statusMov == 'FECHADO'): ?>
                                        <span class="status-fechado" style="background:var(--laranja); color:#fff; font-weight:700; padding:6px 12px; border-radius:8px; display:inline-block; margin-right:5px;">
                                            <i class="fas fa-clock"></i> FECHADO
                                        </span>
                                        <?php if ($podeEncerrarCaixa): ?>
                                            <button class="btn-icon-action" title="Encerrar Caixa (Admin)" onclick='abrirModalFechamento(<?= json_encode($mov) ?>)'>
                                                <i class="fas fa-lock"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php elseif($statusMov == 'EM_REVISAO'): ?>
                                        <span class="status-fechado" style="background:#6c757d; color:#fff; font-weight:700; padding:6px 12px; border-radius:8px; display:inline-block; margin-right:5px;">
                                            <i class="fas fa-search"></i> EM REVISÃO
                                        </span>
                                        <?php if ($podeEncerrarCaixa): ?>
                                            <button class="btn-icon-action" title="Encerrar Caixa (Admin)" onclick='abrirModalFechamento(<?= json_encode($mov) ?>)'>
                                                <i class="fas fa-lock"></i>
                                            </button>
                                        <?php endif; ?>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Variáveis globais para os calendários
        let currentMonth1 = new Date();
        let currentMonth2 = new Date();
        let selectedStart = null;
        let selectedEnd = null;
        
        // Toggle do dropdown do calendário
        function toggleDatePicker(event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            const dropdown = document.getElementById('datePickerDropdown');
            const calendarView = document.getElementById('calendarView');
            const presetOptions = document.getElementById('presetOptions');
            const btnDatePicker = document.getElementById('btnDatePicker');
            
            console.log('Toggle clicado - Dropdown atual:', dropdown ? dropdown.style.display : 'não encontrado');
            
            if (dropdown.style.display === 'none' || dropdown.style.display === '') {
                // Calcular posição do botão
                const rect = btnDatePicker.getBoundingClientRect();
                
                // Posicionar dropdown abaixo do botão
                dropdown.style.top = (rect.bottom + 5) + 'px';
                dropdown.style.left = rect.left + 'px';
                
                // Mostrar dropdown
                dropdown.style.display = 'block';
                calendarView.style.display = 'none';
                presetOptions.style.display = 'block';
                console.log('Dropdown ABERTO na posição:', dropdown.style.top, dropdown.style.left);
            } else {
                dropdown.style.display = 'none';
                console.log('Dropdown FECHADO');
            }
            
            return false;
        }
        
        // Adicionar event listener ao botão quando a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM carregado, configurando eventos...');
            
            const btnDatePicker = document.getElementById('btnDatePicker');
            if (btnDatePicker) {
                console.log('Botão encontrado, adicionando listener');
                
                // Remover qualquer listener anterior
                btnDatePicker.removeEventListener('click', toggleDatePicker);
                
                // Adicionar novo listener
                btnDatePicker.addEventListener('click', function(e) {
                    console.log('Botão clicado!');
                    e.preventDefault();
                    e.stopPropagation();
                    toggleDatePicker(e);
                    return false;
                });
                
                console.log('Listener configurado com sucesso');
            } else {
                console.error('Botão btnDatePicker NÃO encontrado!');
            }
        });
        
        // Mostrar calendários duplos
        function showCalendar() {
            const calendarView = document.getElementById('calendarView');
            const presetOptions = document.getElementById('presetOptions');
            
            // Esconder opções predefinidas
            if (presetOptions) {
                presetOptions.style.display = 'none';
            }
            
            // Mostrar calendário
            calendarView.style.display = 'block';
            
            // Inicializar com mês atual e próximo mês
            currentMonth1 = new Date();
            currentMonth2 = new Date(currentMonth1.getFullYear(), currentMonth1.getMonth() + 1, 1);
            
            // Resetar seleções
            selectedStart = null;
            selectedEnd = null;
            
            // Atualizar botão
            updateApplyButton();
            
            renderCalendars();
        }
        
        // Voltar para opções predefinidas
        function backToPresets() {
            const calendarView = document.getElementById('calendarView');
            const presetOptions = document.getElementById('presetOptions');
            
            // Esconder calendário
            calendarView.style.display = 'none';
            
            // Mostrar opções predefinidas
            if (presetOptions) {
                presetOptions.style.display = 'block';
            }
        }
        
        // Mudar mês
        function changeMonth(direction) {
            currentMonth1.setMonth(currentMonth1.getMonth() + direction);
            currentMonth2 = new Date(currentMonth1.getFullYear(), currentMonth1.getMonth() + 1, 1);
            renderCalendars();
        }
        
        // Renderizar ambos os calendários
        function renderCalendars() {
            renderMonth(currentMonth1, 'month1');
            renderMonth(currentMonth2, 'month2');
        }
        
        // Renderizar um mês
        function renderMonth(date, monthId) {
            const year = date.getFullYear();
            const month = date.getMonth();
            
            // Atualizar título
            const monthNames = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                              'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
            document.getElementById(monthId + 'Title').textContent = `${monthNames[month]} ${year}`;
            
            // Criar grid
            const grid = document.getElementById(monthId + 'Grid');
            grid.className = 'calendar-grid';
            grid.innerHTML = '';
            
            // Cabeçalhos dos dias
            const dayHeaders = ['Se', 'Te', 'Qu', 'Qu', 'Se', 'Sá', 'Do'];
            dayHeaders.forEach(day => {
                const header = document.createElement('div');
                header.className = 'calendar-day-header';
                header.textContent = day;
                grid.appendChild(header);
            });
            
            // Primeiro dia do mês e último dia
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const startingDayOfWeek = firstDay.getDay();
            
            // Dias do mês anterior
            const prevMonthLastDay = new Date(year, month, 0).getDate();
            for (let i = startingDayOfWeek - 1; i >= 0; i--) {
                const dayDiv = createDayCell(prevMonthLastDay - i, true, year, month - 1);
                grid.appendChild(dayDiv);
            }
            
            // Dias do mês atual
            for (let day = 1; day <= lastDay.getDate(); day++) {
                const dayDiv = createDayCell(day, false, year, month);
                grid.appendChild(dayDiv);
            }
            
            // Dias do próximo mês
            const remainingCells = 42 - grid.children.length + 7; // 7 headers
            for (let day = 1; day <= remainingCells; day++) {
                const dayDiv = createDayCell(day, true, year, month + 1);
                grid.appendChild(dayDiv);
            }
        }
        
        // Criar célula de dia
        function createDayCell(day, isOtherMonth, year, month) {
            const dayDiv = document.createElement('div');
            dayDiv.className = 'calendar-day';
            dayDiv.textContent = day;
            
            if (isOtherMonth) {
                dayDiv.classList.add('other-month');
            }
            
            const cellDate = new Date(year, month, day);
            cellDate.setHours(0, 0, 0, 0);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            // Marcar hoje
            if (cellDate.getTime() === today.getTime() && !isOtherMonth) {
                dayDiv.classList.add('today');
            }
            
            // Marcar selecionados
            if (selectedStart && cellDate.getTime() === selectedStart.getTime()) {
                dayDiv.classList.add('selected');
            }
            if (selectedEnd && cellDate.getTime() === selectedEnd.getTime()) {
                dayDiv.classList.add('selected');
            }
            
            // Marcar range
            if (selectedStart && selectedEnd && cellDate > selectedStart && cellDate < selectedEnd) {
                dayDiv.classList.add('in-range');
            }
            
            // Click handler
            dayDiv.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                selectDate(cellDate);
                return false;
            };
            dayDiv.style.cursor = 'pointer';
            
            return dayDiv;
        }
        
        // Selecionar data
        function selectDate(date) {
            // Normalizar a data (remover horas)
            const normalizedDate = new Date(date);
            normalizedDate.setHours(0, 0, 0, 0);
            
            if (!selectedStart || (selectedStart && selectedEnd)) {
                // Primeira seleção ou resetar
                selectedStart = normalizedDate;
                selectedEnd = null;
            } else {
                // Segunda seleção
                if (normalizedDate.getTime() === selectedStart.getTime()) {
                    // Clicou na mesma data - resetar
                    selectedStart = null;
                    selectedEnd = null;
                } else if (normalizedDate < selectedStart) {
                    // Data anterior - inverter
                    selectedEnd = selectedStart;
                    selectedStart = normalizedDate;
                } else {
                    // Data posterior - normal
                    selectedEnd = normalizedDate;
                }
            }
            
            // Atualizar botão
            updateApplyButton();
            
            // Re-renderizar calendários
            renderCalendars();
        }
        
        // Atualizar texto e cor do botão Aplicar
        function updateApplyButton() {
            const btnApply = document.getElementById('btnApplyRange');
            const btnText = document.getElementById('applyButtonText');
            
            if (!selectedStart) {
                btnText.textContent = 'Selecione uma data';
                btnApply.style.background = 'linear-gradient(135deg, #6c757d, #5a6268)';
            } else if (!selectedEnd) {
                btnText.textContent = 'Selecione a data final (ou clique para aplicar apenas esta data)';
                btnApply.style.background = 'linear-gradient(135deg, #f39c12, #e08e0b)';
            } else {
                btnText.textContent = 'Aplicar período selecionado';
                btnApply.style.background = 'linear-gradient(135deg, #28a745, #218838)';
            }
        }
        
        // Aplicar período selecionado
        function applyDateRange() {
            if (!selectedStart) {
                alert('Por favor, selecione pelo menos uma data!');
                return;
            }
            
            const startStr = selectedStart.toISOString().split('T')[0];
            document.getElementById('data_filtro').value = startStr;
            
            // Atualizar display
            if (selectedEnd) {
                const startFormatted = selectedStart.toLocaleDateString('pt-BR');
                const endFormatted = selectedEnd.toLocaleDateString('pt-BR');
                document.getElementById('dateDisplay').textContent = `${startFormatted} - ${endFormatted}`;
            } else {
                document.getElementById('dateDisplay').textContent = selectedStart.toLocaleDateString('pt-BR');
            }
            
            // Fechar dropdown - NÃO SUBMETER AUTOMATICAMENTE
            document.getElementById('datePickerDropdown').style.display = 'none';
        }
        
        // Definir período predefinido
        function setDatePreset(preset) {
            const hoje = new Date();
            let dataInicio;
            
            switch(preset) {
                case 'hoje':
                    dataInicio = hoje;
                    break;
                case 'ontem':
                    dataInicio = new Date(hoje);
                    dataInicio.setDate(hoje.getDate() - 1);
                    break;
                case 'ultimos7dias':
                    dataInicio = new Date(hoje);
                    dataInicio.setDate(hoje.getDate() - 6);
                    break;
                case 'estemes':
                    dataInicio = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
                    break;
                case 'mesanterior':
                    dataInicio = new Date(hoje.getFullYear(), hoje.getMonth() - 1, 1);
                    break;
            }
            
            const dataStr = dataInicio.toISOString().split('T')[0];
            document.getElementById('data_filtro').value = dataStr;
            document.getElementById('dateDisplay').textContent = dataInicio.toLocaleDateString('pt-BR');
            
            // Fechar dropdown - NÃO SUBMETER AUTOMATICAMENTE
            document.getElementById('datePickerDropdown').style.display = 'none';
        }
        
        // Função para ir para o dia anterior
        function diaAnterior() {
            const dataAtual = document.getElementById('data_filtro').value;
            if (dataAtual) {
                const data = new Date(dataAtual + 'T00:00:00');
                data.setDate(data.getDate() - 1);
                const novaData = data.toISOString().split('T')[0];
                document.getElementById('data_filtro').value = novaData;
                document.getElementById('dateDisplay').textContent = data.toLocaleDateString('pt-BR');
            } else {
                const hoje = new Date();
                hoje.setDate(hoje.getDate() - 1);
                const novaData = hoje.toISOString().split('T')[0];
                document.getElementById('data_filtro').value = novaData;
                document.getElementById('dateDisplay').textContent = hoje.toLocaleDateString('pt-BR');
            }
            // NÃO submeter automaticamente - aguardar clique no botão de busca
        }
        
        // Função para ir para o dia seguinte
        function diaSeguinte() {
            const dataAtual = document.getElementById('data_filtro').value;
            if (dataAtual) {
                const data = new Date(dataAtual + 'T00:00:00');
                data.setDate(data.getDate() + 1);
                const novaData = data.toISOString().split('T')[0];
                document.getElementById('data_filtro').value = novaData;
                document.getElementById('dateDisplay').textContent = data.toLocaleDateString('pt-BR');
            } else {
                const hoje = new Date();
                hoje.setDate(hoje.getDate() + 1);
                const novaData = hoje.toISOString().split('T')[0];
                document.getElementById('data_filtro').value = novaData;
                document.getElementById('dateDisplay').textContent = hoje.toLocaleDateString('pt-BR');
            }
            // NÃO submeter automaticamente - aguardar clique no botão de busca
        }
        
        // Fechar dropdown ao clicar fora
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('datePickerDropdown');
            const btn = document.getElementById('btnDatePicker');
            
            if (dropdown && btn && !dropdown.contains(event.target) && !btn.contains(event.target)) {
                dropdown.style.display = 'none';
            }
        });
    
        function abrirModalFechamento(dados) {
            // Em schema legado, `id` pode ficar 0 e o identificador real vem de `pk_id`.
            const idNumerico = Number(dados.id);
            document.getElementById('modal_id_caixa').value = (idNumerico > 0) ? dados.id : (dados.pk_id ?? 0);
            document.getElementById('modalFechamento').classList.add('show');
        }

        function excluirCaixa(id) {
            Swal.fire({
                title: 'Tem certeza?',
                text: "Ao excluir o caixa, os valores de SUPRIMENTO serão estornados (devolvidos). Deseja continuar?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sim, excluir!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('movimentacao_caixa.php', {
                        acao: 'excluir_caixa', 
                        id: id
                    }, function(response) {
                        if(typeof response === 'string') {
                            try { response = JSON.parse(response); } catch(e) {}
                        }
                        
                        if (response.status === 'success') {
                            Swal.fire('Excluído!', response.message, 'success')
                            .then(() => location.reload());
                        } else {
                            Swal.fire('Erro!', response.message || 'Erro ao excluir.', 'error');
                        }
                    }, 'json');
                }
            })
        }
        
        $(document).ready(function(){
            $('#modal_valor').mask('#.##0,00', {reverse: true});
        });
    </script>
</body>
</html>