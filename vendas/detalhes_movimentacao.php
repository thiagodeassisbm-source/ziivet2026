<?php
/**
 * ZIIPVET - Detalhes da Movimentação do Caixa
 * ARQUIVO: detalhes_movimentacao.php  
 * LOCALIZAÇÃO: /app/vendas/
 * 
 * CORREÇÕES APLICADAS:
 * 1. Todo o valor em dinheiro (incluindo valor inicial) retorna para a conta selecionada
 * 2. Hora atual carregando corretamente no modal de encerramento
 */

$base_path = dirname(__DIR__) . '/'; 
$path_prefix = '../';

require_once $base_path . 'auth.php';
require_once $base_path . 'config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;
$id_caixa = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

// Gerar token CSRF para uso no JavaScript
$csrf_token = \App\Utils\Csrf::generate();

$debug_detalhes_mov = !empty($_GET['debug']) && (string)$_GET['debug'] !== '0' && (string)$_GET['debug'] !== '';

if (!$id_caixa) {
    die("<script>alert('Caixa não informado!'); window.location.href='movimentacao_caixa.php';</script>");
}

// PROCESSAMENTO AJAX
if (isset($_POST['acao']) && $_POST['acao'] === 'adicionar_movimentacao') {
    header('Content-Type: application/json');
    
    try {
        $pdo->beginTransaction();
        
        $tipo = strtoupper(trim((string)($_POST['tipo'] ?? '')));
        $valor = floatval(str_replace(',', '.', str_replace('.', '', $_POST['valor'])));
        $forma_pagamento = $_POST['forma_pagamento'] ?? 'Não especificada';
        $id_conta = $_POST['id_conta'] ?? null;
        $descricao = $_POST['descricao'] ?? '';
        $observacoes = $_POST['observacoes'] ?? '';

        // Garantir consistência dos tipos que alteram "natureza"/categoria.
        $tipoPermitido = ['SUPRIMENTO', 'SANGRIA', 'DESPESA', 'TRANSFERENCIA'];
        if (!in_array($tipo, $tipoPermitido, true)) {
            throw new Exception('Tipo de movimentação inválido: ' . htmlspecialchars($tipo));
        }
        
        // IMPORTANTE:
        // `lancamentos` é uma VIEW e pode não ser estável para inserts (schema divergente).
        // Para evitar erro de coluna desconhecida (ex: `id_conta`), inserimos diretamente em `contas`.
        $natureza = ($tipo === 'SUPRIMENTO') ? 'Receita' : 'Despesa';

        // Quando o modal não manda id_conta (ex: Suprimento/Sangria), tentamos usar a conta do caixa.
        $id_conta_origem = $id_conta;
        if (empty($id_conta_origem)) {
            $stmtCaixa = $pdo->prepare("SELECT id_conta_origem FROM caixas WHERE id = ? AND id_admin = ?");
            $stmtCaixa->execute([$id_caixa, $id_admin]);
            $id_conta_origem = $stmtCaixa->fetchColumn() ?: null;
        }

        // Tenta mapear id_forma_pgto a partir do nome para que a VIEW `lancamentos` retorne a forma corretamente.
        $id_forma_pgto = null;
        if (!empty($forma_pagamento)) {
            $stmtForma = $pdo->prepare("
                SELECT id
                FROM formas_pagamento
                WHERE id_admin = ?
                  AND UPPER(TRIM(nome_forma)) = UPPER(TRIM(?))
                LIMIT 1
            ");
            $stmtForma->execute([$id_admin, $forma_pagamento]);
            $id_forma_pgto = $stmtForma->fetchColumn() ?: null;
        }

        $descricaoFinal = $descricao !== '' ? $descricao : ($tipo . ' do caixa #' . $id_caixa);

        $sql = "INSERT INTO contas
                (id_admin, natureza, categoria, descricao, forma_pagamento_detalhe, id_forma_pgto,
                 id_conta_origem, valor_total, valor_parcela, qtd_parcelas, status_baixa,
                 id_caixa_referencia, observacoes, vencimento, data_pagamento, data_cadastro)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'PAGO', ?, ?, CURDATE(), NOW(), NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $id_admin,
            $natureza,
            $tipo,
            $descricaoFinal,
            $forma_pagamento,
            $id_forma_pgto,
            $id_conta_origem,
            $valor,
            $valor,
            $id_caixa,
            $observacoes
        ]);
        
        $pdo->commit();
        echo json_encode([
            'status' => 'success',
            'message' => 'Movimentação registrada! (debug: tipo_recebido=' . $tipo . ', natureza_gravada=' . $natureza . ', categoria_gravada=' . $tipo . ')'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// FECHAR CAIXA (muda de ABERTO para FECHADO - preparando para encerramento)
if (isset($_POST['acao']) && $_POST['acao'] === 'fechar_caixa') {
    header('Content-Type: application/json');
    
    try {
        $pdo->beginTransaction();
        
        // Verificar se o caixa está aberto
        $stmt_check = $pdo->prepare("SELECT status FROM caixas WHERE id = ?");
        $stmt_check->execute([$id_caixa]);
        $status_atual = $stmt_check->fetchColumn();
        
        if ($status_atual !== 'ABERTO') {
            throw new Exception('Este caixa não está aberto.');
        }
        
        // Atualizar status para FECHADO registrando data/hora de fechamento
        $sql = "UPDATE caixas SET status = 'FECHADO', data_fechamento = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_caixa]);
        
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Caixa fechado com sucesso!']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if (isset($_POST['acao']) && $_POST['acao'] === 'encerrar_caixa') {
    header('Content-Type: application/json');
    
    try {
        $pdo->beginTransaction();
        
        $id_conta_destino = $_POST['id_conta_destino'];
        $data_fechamento = $_POST['data_fechamento'];
        $hora_fechamento = $_POST['hora_fechamento'];
        $comentario = $_POST['comentario'] ?? '';
        
        // Calcular valor total em caixa (APENAS DINHEIRO)
        $stmt_total = $pdo->prepare("
            SELECT 
                (SELECT COALESCE(valor_inicial, 0) FROM caixas WHERE id = ?) +
                (SELECT COALESCE(SUM(CASE 
                    WHEN tipo = 'ENTRADA' THEN valor 
                    WHEN tipo = 'SAIDA' THEN -valor 
                    END), 0) FROM lancamentos 
                 WHERE id_caixa_referencia = ? 
                 AND status = 'PAGO'
                 AND UPPER(TRIM(forma_pagamento)) = 'DINHEIRO')
                as total
        ");
        $stmt_total->execute([$id_caixa, $id_caixa]);
        $valor_fechamento = $stmt_total->fetchColumn();
        
        // Buscar dados completos do caixa
        $stmt_caixa = $pdo->prepare("SELECT valor_inicial, id_conta_origem, id_usuario FROM caixas WHERE id = ?");
        $stmt_caixa->execute([$id_caixa]);
        $dados_caixa = $stmt_caixa->fetch(PDO::FETCH_ASSOC);
        $valor_abertura = $dados_caixa['valor_inicial'];
        $conta_origem = $dados_caixa['id_conta_origem'];
        $id_usuario_caixa = $dados_caixa['id_usuario'];
        
        // Atualizar status do caixa para ENCERRADO (processo finalizado)
        $sql = "UPDATE caixas SET 
                status = 'ENCERRADO',
                data_fechamento = ?,
                valor_fechamento = ?,
                id_conta_fechamento = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data_fechamento . ' ' . $hora_fechamento,
            $valor_fechamento, 
            $id_conta_destino, 
            $id_caixa
        ]);
        
        // ✅ CORREÇÃO REAL DO PROBLEMA:
        // 1. REMOVE o valor total do caixa do usuário (para não duplicar)
        // 2. ADICIONA o valor total na conta escolhida no encerramento
        
        // Buscar a conta de caixa do usuário
        $stmt_usuario = $pdo->prepare("
            SELECT u.id_conta_caixa, cf.saldo_inicial, cf.nome_conta
            FROM usuarios u
            INNER JOIN contas_financeiras cf ON u.id_conta_caixa = cf.id
            WHERE u.id = ?
        ");
        $stmt_usuario->execute([$id_usuario_caixa]);
        $conta_usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
        
        // 1. REMOVER do caixa do usuário
        if ($conta_usuario && $conta_usuario['id_conta_caixa']) {
            $novo_saldo_usuario = $conta_usuario['saldo_inicial'] - $valor_fechamento;
            
            // Se ficar negativo, zera
            if ($novo_saldo_usuario < 0) {
                $novo_saldo_usuario = 0;
            }
            
            $sql_usuario = "UPDATE contas_financeiras 
                           SET saldo_inicial = ?, 
                               data_saldo = ? 
                           WHERE id = ?";
            $stmt_upd_usuario = $pdo->prepare($sql_usuario);
            $stmt_upd_usuario->execute([$novo_saldo_usuario, $data_fechamento, $conta_usuario['id_conta_caixa']]);
        }
        
        // 2. ADICIONAR na conta de destino escolhida
        $stmt_destino = $pdo->prepare("SELECT saldo_inicial FROM contas_financeiras WHERE id = ?");
        $stmt_destino->execute([$id_conta_destino]);
        $saldo_destino = $stmt_destino->fetchColumn();
        
        $novo_saldo_destino = $saldo_destino + $valor_fechamento;
        
        $sql_destino = "UPDATE contas_financeiras 
                       SET saldo_inicial = ?, 
                           data_saldo = ? 
                       WHERE id = ?";
        $stmt_upd_destino = $pdo->prepare($sql_destino);
        $stmt_upd_destino->execute([$novo_saldo_destino, $data_fechamento, $id_conta_destino]);
        
        // CRIAR LANÇAMENTO FINANCEIRO para histórico (usar tabela contas, não a view lancamentos)
        if ($valor_fechamento > 0) {
            $descricao_lanc = "Fechamento do caixa #" . $id_caixa . " - Retorno de valores";
            
            $sql_lanc = "INSERT INTO contas 
                        (id_admin, natureza, categoria, descricao, forma_pagamento_detalhe, 
                         id_conta_origem, valor_total, valor_parcela, qtd_parcelas, status_baixa, 
                         id_caixa_referencia, observacoes, vencimento, data_pagamento, data_cadastro) 
                        VALUES (?, 'Receita', 'FECHAMENTO_CAIXA', ?, 'Dinheiro', 
                                ?, ?, ?, 1, 'PAGO', ?, ?, CURDATE(), NOW(), NOW())";
            
            $stmt_lanc = $pdo->prepare($sql_lanc);
            $stmt_lanc->execute([
                $id_admin,
                $descricao_lanc,
                $id_conta_destino,
                $valor_fechamento,
                $valor_fechamento,
                $id_caixa,
                $comentario
            ]);
        }
        
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Caixa encerrado e valores transferidos!']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

try {
    $hasPkIdCaixas = false;
    try {
        $hasPkIdCaixas = (bool)$pdo->query("SHOW COLUMNS FROM caixas LIKE 'pk_id'")->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $hasPkIdCaixas = false;
    }

    // Importante:
    // Em schemas legados, o link pode passar `pk_id` quando `id` fica 0.
    // Se a busca por `id` retornar algum registro "parecido", a tela pode carregar o caixa errado.
    // Por isso, quando existir pk_id, priorizamos buscar por pk_id.
    $modoBuscaCaixa = 'id';
    $caixaById = null;
    $caixaByPk = null;

    $stmtCaixaById = $pdo->prepare("
        SELECT c.*, u.nome as nome_usuario
        FROM caixas c
        LEFT JOIN usuarios u ON c.id_usuario = u.id
        WHERE c.id = ? AND c.id_admin = ?
        LIMIT 1
    ");
    $stmtCaixaById->execute([$id_caixa, $id_admin]);
    $caixaById = $stmtCaixaById->fetch(PDO::FETCH_ASSOC);

    if ($hasPkIdCaixas) {
        $stmtCaixaByPk = $pdo->prepare("
            SELECT c.*, u.nome as nome_usuario
            FROM caixas c
            LEFT JOIN usuarios u ON c.id_usuario = u.id
            WHERE c.pk_id = ? AND c.id_admin = ?
            LIMIT 1
        ");
        $stmtCaixaByPk->execute([$id_caixa, $id_admin]);
        $caixaByPk = $stmtCaixaByPk->fetch(PDO::FETCH_ASSOC);
    }

    if ($caixaByPk) {
        // Priorizamos o que parece realmente "fechado/encerrado"
        $statusPk = strtoupper(trim((string)($caixaByPk['status'] ?? '')));
        $dataFechPk = (string)($caixaByPk['data_fechamento'] ?? '');
        $okPk = in_array($statusPk, ['FECHADO', 'ENCERRADO'], true) && ($dataFechPk !== '' && $dataFechPk !== '0000-00-00 00:00:00');

        // Se pk tem status/datas, usamos; caso contrário, ainda assim tentamos usar o pk quando o id não existe.
        if ($okPk || !$caixaById) {
            $caixa = $caixaByPk;
            $modoBuscaCaixa = 'pk_id';
        } else {
            // Se pk não parece fechado e id existe, tenta usar o id.
            $caixa = $caixaById ?: $caixaByPk;
            $modoBuscaCaixa = $caixaById ? 'id' : 'pk_id';
        }
    } else {
        $caixa = $caixaById;
        $modoBuscaCaixa = 'id';
    }

    if (!$caixa) {
        die("<script>alert('Caixa não encontrado!'); window.location.href='movimentacao_caixa.php';</script>");
    }

    $formatarDataHoraExibicao = static function ($data, $hora = null): string {
        $dataStr = trim((string)$data);
        $horaStr = trim((string)$hora);

        if ($dataStr === '' || $dataStr === '0000-00-00' || $dataStr === '0000-00-00 00:00:00') {
            return '---';
        }

        if ($horaStr !== '' && !str_contains($dataStr, ':')) {
            $dataStr .= ' ' . $horaStr;
        }

        $ts = strtotime($dataStr);
        if ($ts === false || $ts <= 0) {
            return '---';
        }
        return date('d/m/Y H:i', $ts);
    };

    $inicio = $caixa['data_abertura'] . ' ' . $caixa['hora_abertura'];
    $fim = !empty($caixa['data_fechamento']) ? $caixa['data_fechamento'] : date('Y-m-d H:i:s');

    // Auto-reparo defensivo: se o caixa já estiver fechado/encerrado e sem data de fechamento,
    // preenche para evitar inconsistência visual e de relatório.
    $statusCaixaAtual = strtoupper(trim((string)($caixa['status'] ?? '')));
    if (
        in_array($statusCaixaAtual, ['FECHADO', 'ENCERRADO'], true) &&
        (empty($caixa['data_fechamento']) || $caixa['data_fechamento'] === '0000-00-00 00:00:00')
    ) {
        $dataFechAuto = !empty($caixa['data_cadastro']) ? $caixa['data_cadastro'] : date('Y-m-d H:i:s');

        $idCaixaFix = (int)($caixa['id'] ?? 0);
        $pkIdCaixaFix = (int)($caixa['pk_id'] ?? 0);
        if ($idCaixaFix > 0) {
            $stmtFixFech = $pdo->prepare("UPDATE caixas SET data_fechamento = ? WHERE id = ? AND id_admin = ?");
            $stmtFixFech->execute([$dataFechAuto, $idCaixaFix, $id_admin]);
        } elseif ($pkIdCaixaFix > 0) {
            $stmtFixFech = $pdo->prepare("UPDATE caixas SET data_fechamento = ? WHERE pk_id = ? AND id_admin = ?");
            $stmtFixFech->execute([$dataFechAuto, $pkIdCaixaFix, $id_admin]);
        }

        $caixa['data_fechamento'] = $dataFechAuto;
    }

    if ($debug_detalhes_mov) {
        $statusRaw = (string)($caixa['status'] ?? '');
        $statusNorm = strtoupper(trim($statusRaw));
        $debugPayload = [
            'debug' => 'detalhes_movimentacao',
            'id_caixa_url' => $id_caixa,
            'id_caixa_row' => (int)($caixa['id'] ?? 0),
            'pk_id_row' => (int)($caixa['pk_id'] ?? 0),
            'busca_usada' => $modoBuscaCaixa,
            'status_raw' => $statusRaw,
            'status_norm' => $statusNorm,
            'data_fechamento_raw' => (string)($caixa['data_fechamento'] ?? ''),
            'valor_fechamento' => $caixa['valor_fechamento'] ?? null,
            'id_conta_fechamento' => $caixa['id_conta_fechamento'] ?? null
        ];
        echo "<pre style='background:#111;color:#0f0;padding:12px;border-radius:8px;white-space:pre-wrap;'>";
        echo htmlspecialchars(json_encode($debugPayload, JSON_UNESCAPED_UNICODE));
        echo "</pre>";
    }

    $stmtF = $pdo->prepare("SELECT id, nome_forma FROM formas_pagamento WHERE id_admin = ? ORDER BY nome_forma ASC");
    $stmtF->execute([$id_admin]);
    $formasDoSistema = $stmtF->fetchAll(PDO::FETCH_ASSOC);

    // Resumo de recebimentos por forma.
    // IMPORTANTE: usar `contas` (tabela real), não `lancamentos` (view pode ficar desalinhada).
    $stmtResumo = $pdo->prepare("
        SELECT 
            CASE 
                WHEN UPPER(TRIM(COALESCE(f.nome_forma, c.forma_pagamento_detalhe, ''))) LIKE '%CRÉDITO%' 
                  OR UPPER(TRIM(COALESCE(f.nome_forma, c.forma_pagamento_detalhe, ''))) LIKE '%CREDITO%' THEN 'CARTÃO DE CRÉDITO'
                WHEN UPPER(TRIM(COALESCE(f.nome_forma, c.forma_pagamento_detalhe, ''))) LIKE '%DÉBITO%' 
                  OR UPPER(TRIM(COALESCE(f.nome_forma, c.forma_pagamento_detalhe, ''))) LIKE '%DEBITO%' THEN 'CARTÃO DE DÉBITO'
                ELSE COALESCE(NULLIF(UPPER(TRIM(COALESCE(f.nome_forma, c.forma_pagamento_detalhe, ''))), ''), 'OUTROS')
            END as forma_agrupada,
            COALESCE(f.nome_forma, c.forma_pagamento_detalhe, 'Outros') as forma_original,
            COALESCE(c.valor_parcela, c.valor_total, 0) as valor_liquido,
            COALESCE(v.valor_total, c.valor_total, 0) as valor_bruto,
            c.id_venda
        FROM contas c
        LEFT JOIN formas_pagamento f ON c.id_forma_pgto = f.id
        LEFT JOIN vendas v ON v.id = (
            CASE
                WHEN c.id_venda IS NULL OR c.id_venda = 0 THEN CAST(c.documento AS UNSIGNED)
                ELSE c.id_venda
            END
        )
        WHERE c.id_caixa_referencia = ?
        AND c.id_admin = ?
        AND UPPER(TRIM(COALESCE(c.natureza, ''))) = 'RECEITA'
        AND UPPER(TRIM(COALESCE(c.status_baixa, ''))) = 'PAGO'
        AND (
            c.categoria IS NULL
            OR UPPER(TRIM(COALESCE(c.categoria, ''))) NOT IN ('SUPRIMENTO', 'CAIXA', 'ABERTURA_CAIXA', 'FECHAMENTO_CAIXA')
        )
        ORDER BY forma_agrupada, c.id
    ");
    $stmtResumo->execute([$id_caixa, $id_admin]);
    $resumoRaw = $stmtResumo->fetchAll(PDO::FETCH_ASSOC);
    
    // Processar para agrupar [FormaAgrupada => ['liquido'=>X, 'bruto'=>Y, 'detalhes'=>[...]]]
    $resumoData = [];
    foreach ($resumoRaw as $r) {
        $key = $r['forma_agrupada'];
        if (!isset($resumoData[$key])) {
            $resumoData[$key] = [
                'liquido' => 0,
                'bruto' => 0,
                'detalhes' => []
            ];
        }
        $resumoData[$key]['liquido'] += $r['valor_liquido'];
        $resumoData[$key]['bruto'] += $r['valor_bruto'];
        
        // Armazenar detalhes individuais somente se houver taxa (bruto > liquido)
        if ($r['valor_bruto'] > $r['valor_liquido'] + 0.01) {
            $resumoData[$key]['detalhes'][] = [
                'forma' => $r['forma_original'],
                'bruto' => $r['valor_bruto'],
                'liquido' => $r['valor_liquido'],
                'id_venda' => $r['id_venda']
            ];
        }
    }
    
    // Garantir que DINHEIRO esteja presente para o Suprimento
    if (!isset($resumoData['DINHEIRO'])) {
        $resumoData['DINHEIRO'] = ['liquido' => 0, 'bruto' => 0, 'detalhes' => []];
    }

    $stmtLista = $pdo->prepare("
        SELECT 
            c.id,
            c.descricao,
            c.data_cadastro,
            COALESCE(c.valor_parcela, c.valor_total, 0) as valor,
            COALESCE(f.nome_forma, c.forma_pagamento_detalhe, 'Outros') as forma_pagamento,
            c.id_venda,
            c.documento,
            CASE
                WHEN c.entidade_tipo = 'cliente' THEN (SELECT nome FROM clientes WHERE id = c.id_entidade LIMIT 1)
                WHEN c.entidade_tipo = 'fornecedor' THEN (SELECT nome_fantasia FROM fornecedores WHERE id = c.id_entidade LIMIT 1)
                WHEN c.entidade_tipo = 'usuario' THEN (SELECT nome FROM usuarios WHERE id = c.id_entidade LIMIT 1)
                ELSE NULL
            END as fornecedor_cliente,
            v.valor_total as venda_valor_bruto,
            v.nfce_status,
            v.nfce_url,
            v.nfce_chave,
            v.id as id_real_venda
        FROM contas c
        LEFT JOIN formas_pagamento f ON c.id_forma_pgto = f.id
        LEFT JOIN vendas v ON v.id = (
            CASE
                WHEN c.id_venda IS NULL OR c.id_venda = 0 THEN CAST(c.documento AS UNSIGNED)
                ELSE c.id_venda
            END
        )
        WHERE c.id_caixa_referencia = ?
          AND c.id_admin = ?
          AND UPPER(TRIM(COALESCE(c.natureza, ''))) = 'RECEITA'
          AND UPPER(TRIM(COALESCE(c.status_baixa, ''))) = 'PAGO'
          AND (
            c.categoria IS NULL
            OR UPPER(TRIM(COALESCE(c.categoria, ''))) NOT IN ('SUPRIMENTO', 'CAIXA', 'ABERTURA_CAIXA', 'FECHAMENTO_CAIXA')
          )
        ORDER BY c.data_cadastro DESC
    ");
    $stmtLista->execute([$id_caixa, $id_admin]);
    $listaRecebimentos = $stmtLista->fetchAll(PDO::FETCH_ASSOC);

    // Debug técnico: total de receitas pagas vinculadas ao caixa na tabela real.
    $stmtDbg = $pdo->prepare("
        SELECT COUNT(*) 
        FROM contas c
        WHERE c.id_caixa_referencia = ?
          AND c.id_admin = ?
          AND UPPER(TRIM(COALESCE(c.natureza, ''))) = 'RECEITA'
          AND UPPER(TRIM(COALESCE(c.status_baixa, ''))) = 'PAGO'
    ");
    $stmtDbg->execute([$id_caixa, $id_admin]);
    $debugTotalReceitasCaixa = (int)$stmtDbg->fetchColumn();
    
    $contas_financeiras = $pdo->query("SELECT id, nome_conta FROM contas_financeiras 
                                       WHERE id_admin = $id_admin AND status = 'Ativo' 
                                       ORDER BY nome_conta")->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_total = $pdo->prepare("
        SELECT 
            (SELECT COALESCE(valor_inicial, 0) FROM caixas WHERE id = ?) +
            (SELECT COALESCE(SUM(CASE 
                WHEN tipo = 'ENTRADA' THEN valor 
                WHEN tipo = 'SAIDA' THEN -valor 
                END), 0) FROM lancamentos 
             WHERE id_caixa_referencia = ? 
             AND status = 'PAGO'
             AND UPPER(TRIM(forma_pagamento)) = 'DINHEIRO')
            as total
    ");
    $stmt_total->execute([$id_caixa, $id_caixa]);
    $total_em_caixa = $stmt_total->fetchColumn();
    
    // Hora atual para o modal de encerramento
    $hora_atual = date('H:i');
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

$titulo_pagina = "Detalhes do Caixa #" . $id_caixa;

// ✅ CORREÇÃO 2: Preparar hora atual para o JavaScript
$hora_atual = date('H:i');
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        :root {
            --roxo-ziip: #622599;
            --azul-ziip: #131c71;
            --verde-ziip: #28a745;
            --laranja-ziip: #f39c12;
            --vermelho-ziip: #b92426;
        }

        body { font-family: 'Exo', sans-serif; background: #ecf0f5; }

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
        
        .tab-button.active {
            background: #fff;
            color: var(--azul-ziip);
            border-bottom-color: var(--azul-ziip);
        }

        .caixa-info-card {
            background: #fff;
            padding: 25px;
            border-radius: 16px;
            margin-bottom: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .info-block label { display: block; font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase; margin-bottom: 5px; }
        .info-block span { font-size: 15px; font-weight: 600; color: #333; }

        .badge-status { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .status-aberto { background: #dff0d8; color: #3c763d; }
        .status-fechado { background: #fcf8e3; color: #8a6d3b; }
        .status-encerrado { background: #d9edf7; color: #31708f; }

        .section-title-ziip { margin-bottom: 20px; color: #2c3e50; font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 10px; }

        .table-custom { width: 100%; border-collapse: collapse; }
        .table-custom th { text-align: left; padding: 15px; background: #f8f9fa; border-bottom: 2px solid #eee; font-size: 13px; color: #555; }
        .table-custom td { padding: 15px; border-bottom: 1px solid #f2f2f2; font-size: 14px; }
        .row-total { background: #fdfdfd; font-weight: 700; }

        .footer-actions {
            position: fixed;
            bottom: 0;
            left: 240px; /* Largura do sidebar */
            right: 0;
            background: #fff;
            padding: 15px 30px;
            display: flex;
            gap: 12px;
            align-items: center;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
            flex-wrap: wrap;
            z-index: 100;
            border-top: 1px solid #e0e0e0;
        }
        
        /* Espaço extra no final do conteúdo para não ficar atrás do footer fixo */
        .tabs-container {
            margin-bottom: 100px;
        }

        .btn-ziip {
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            border: none;
            transition: 0.3s;
            text-decoration: none;
            color: #fff;
        }
        .btn-ziip:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }

        .bg-log { background: #6c757d; }
        .bg-sup { background: var(--verde-ziip); }
        .bg-san { background: var(--laranja-ziip); }
        .bg-des { background: var(--vermelho-ziip); }
        .bg-tra { background: #17a2b8; }
        .bg-enc { background: #2c3e50; }
        .bg-prt { background: #fff; color: #444; border: 2px solid #ddd; }
        .spacer { flex: 1; }
        
        .tab-pane { padding: 25px; }
    </style>
    
    <!-- Scripts no HEAD para carregar antes dos botões -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Status atual do caixa (injetado do PHP)
        const statusCaixa = '<?= $caixa['status'] ?>';
        const csrfToken = '<?= $csrf_token ?>';
        
        function abrirModalEncerramento() {
            // VERIFICAR SE O CAIXA ESTÁ ABERTO
            if (statusCaixa === 'ABERTO') {
                Swal.fire({
                    title: '<i class="fas fa-exclamation-triangle" style="color:#f39c12"></i> Caixa Aberto',
                    html: `
                        <div style="text-align:center; padding:20px;">
                            <p style="font-size:16px; color:#666; margin-bottom:20px;">
                                O caixa ainda está <strong style="color:#f39c12;">ABERTO</strong>.<br>
                                Para encerrar, é necessário <strong>fechar o caixa</strong> primeiro.
                            </p>
                            <p style="font-size:14px; color:#888;">
                                Isso vai registrar o fim das operações do operador.<br>
                                Depois você poderá prosseguir com o encerramento.
                            </p>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-lock"></i> Sim, fechar o caixa',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#f39c12',
                    width: '500px'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fecharCaixaParaEncerramento();
                    }
                });
                return;
            }
            
            // Se já está FECHADO, mostra o modal de encerramento
            mostrarModalEncerramento();
        }
        
        async function fecharCaixaParaEncerramento() {
            const formData = new FormData();
            formData.append('acao', 'fechar_caixa');
            formData.append('csrf_token', csrfToken);
            
            Swal.fire({
                title: 'Fechando caixa...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            
            try {
                const res = await fetch('detalhes_movimentacao.php?id=<?= $id_caixa ?>', {method: 'POST', body: formData});
                const text = await res.text();
                console.log('Resposta fechar:', text);
                
                let resposta;
                try {
                    resposta = JSON.parse(text);
                } catch(parseErr) {
                    Swal.fire('Erro', 'Resposta inválida do servidor. Verifique o console.', 'error');
                    return;
                }
                
                if (resposta.status === 'success') {
                    Swal.fire({
                        title: 'Caixa Fechado!',
                        text: 'Agora você pode prosseguir com o encerramento.',
                        icon: 'success',
                        confirmButtonColor: '#28a745'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro', resposta.message || 'Erro desconhecido', 'error');
                }
            } catch (e) {
                console.error('Erro ao fechar:', e);
                Swal.fire('Erro', 'Erro de conexão ao fechar o caixa: ' + e.message, 'error');
            }
        }
        
        function mostrarModalEncerramento() {
            Swal.fire({
                title: 'Encerramento do caixa <?= $caixa['id'] ?>',
                html: `
                    <div style="background:linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);padding:25px;border-radius:12px;text-align:center;margin:20px 0">
                        <div style="font-size:14px;color:#1e40af;font-weight:600;margin-bottom:8px">Em caixa</div>
                        <div style="font-size:36px;font-weight:700;color:#1e40af">R$ <?= number_format($total_em_caixa, 2, ',', '.') ?></div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px">
                        <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Data*</label>
                        <input type="date" id="data_enc" style="width:100%;padding:12px;border:2px solid #e0e0e0;border-radius:8px" value="<?= date('Y-m-d') ?>"></div>
                        <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Hora*</label>
                        <input type="time" id="hora_enc" style="width:100%;padding:12px;border:2px solid #e0e0e0;border-radius:8px" value="<?= $hora_atual ?>"></div>
                    </div>
                    <div style="margin-bottom:15px"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Conta destino*</label>
                    <select id="conta_enc" style="width:100%;padding:12px;border:2px solid #e0e0e0;border-radius:8px">
                        <option value="">Selecione...</option>
                        <?php foreach($contas_financeiras as $conta): ?>
                            <option value="<?= $conta['id'] ?>"><?= htmlspecialchars($conta['nome_conta']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                    <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Comentário</label>
                    <textarea id="comentario_enc" style="width:100%;padding:12px;border:2px solid #e0e0e0;border-radius:8px" rows="4" placeholder="Observações sobre o encerramento..."></textarea></div>
                `,
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonText: '<i class="fas fa-check"></i> Encerrar caixa',
                denyButtonText: 'Colocar em revisão',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#1e40af',
                denyButtonColor: '#6c757d',
                width: '550px',
                preConfirm: () => {
                    if (!document.getElementById('conta_enc').value || !document.getElementById('data_enc').value || !document.getElementById('hora_enc').value) {
                        Swal.showValidationMessage('Preencha todos os campos obrigatórios');
                        return false;
                    }
                    return {
                        id_conta_destino: document.getElementById('conta_enc').value,
                        data_fechamento: document.getElementById('data_enc').value,
                        hora_fechamento: document.getElementById('hora_enc').value,
                        comentario: document.getElementById('comentario_enc').value
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    encerrarCaixa(result.value);
                } else if (result.isDenied) {
                    colocarEmRevisao();
                }
            });
        }
        
        async function encerrarCaixa(dados) {
            const formData = new FormData();
            formData.append('acao', 'encerrar_caixa');
            formData.append('csrf_token', csrfToken);
            for (let key in dados) {
                formData.append(key, dados[key]);
            }
            
            try {
                const res = await fetch('detalhes_movimentacao.php?id=<?= $id_caixa ?>', {method: 'POST', body: formData});
                const resposta = await res.json();
                
                if (resposta.status === 'success') {
                    Swal.fire({title: 'Sucesso!', text: resposta.message, icon: 'success', confirmButtonColor: '#28a745'}).then(() => location.reload());
                } else {
                    Swal.fire('Erro', resposta.message, 'error');
                }
            } catch (e) {
                console.error('Erro detalhes:', e);
                Swal.fire('Erro', 'Erro ao encerrar caixa. Verifique o console.', 'error');
            }
        }
        
        async function colocarEmRevisao() {
            const formData = new FormData();
            formData.append('acao', 'colocar_revisao');
            formData.append('csrf_token', csrfToken);
            
            try {
                const res = await fetch('detalhes_movimentacao.php?id=<?= $id_caixa ?>', {method: 'POST', body: formData});
                const resposta = await res.json();
                
                if (resposta.status === 'success') {
                    Swal.fire({title: 'Info', text: 'Caixa colocado em revisão', icon: 'info', confirmButtonColor: '#6c757d'}).then(() => location.reload());
                } else {
                    Swal.fire('Erro', resposta.message, 'error');
                }
            } catch (e) {
                Swal.fire('Erro', 'Erro ao colocar em revisão', 'error');
            }
        }
    </script>
</head>
<body>

    <aside class="sidebar-container"><?php include $base_path . 'menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include $base_path . 'menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <div class="form-header">
            <h1 class="form-title">
                <i class="fas fa-cash-register"></i>
                Informações do caixa
            </h1>
            <a href="movimentacao_caixa.php" class="btn-voltar">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <!-- BARRA DE AÇÕES DO CAIXA -->
        <div style="background:#fff; padding:15px 20px; border-radius:12px; margin-bottom:20px; display:flex; flex-wrap:wrap; gap:10px; align-items:center; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            <button onclick="abrirModalLog()" style="padding:10px 16px; background:#6c757d; color:#fff; border:none; border-radius:8px; font-weight:600; cursor:pointer; font-size:13px;">
                <i class="fas fa-search"></i> Log
            </button>
            <button onclick="abrirModalSuprimento()" style="padding:10px 16px; background:#28a745; color:#fff; border:none; border-radius:8px; font-weight:600; cursor:pointer; font-size:13px;">
                <i class="fas fa-plus-circle"></i> Suprimento
            </button>
            <button onclick="abrirModalSangria()" style="padding:10px 16px; background:#f39c12; color:#fff; border:none; border-radius:8px; font-weight:600; cursor:pointer; font-size:13px;">
                <i class="fas fa-minus-circle"></i> Sangria
            </button>
            <button onclick="abrirModalDespesa()" style="padding:10px 16px; background:#e74c3c; color:#fff; border:none; border-radius:8px; font-weight:600; cursor:pointer; font-size:13px;">
                <i class="fas fa-dollar-sign"></i> Despesa
            </button>
            <button onclick="abrirModalTransferencia()" style="padding:10px 16px; background:#17a2b8; color:#fff; border:none; border-radius:8px; font-weight:600; cursor:pointer; font-size:13px;">
                <i class="fas fa-exchange-alt"></i> Transferência
            </button>
            <?php $statusRenderTop = strtoupper(trim((string)($caixa['status'] ?? ''))); ?>
            <?php if($statusRenderTop == 'ABERTO' || $statusRenderTop == 'FECHADO'): ?>
            <button onclick="abrirModalEncerramento()" style="padding:10px 16px; background:#343a40; color:#fff; border:none; border-radius:8px; font-weight:600; cursor:pointer; font-size:13px;">
                <i class="fas fa-edit"></i> Revisar e encerrar
            </button>
            <?php endif; ?>
            <div style="flex:1;"></div>
            <button onclick="window.print()" style="padding:10px 16px; background:#fff; color:#333; border:2px solid #ddd; border-radius:8px; font-weight:600; cursor:pointer; font-size:13px;">
                <i class="fas fa-print"></i> Imprimir
            </button>
            <a href="movimentacao_caixa.php" style="padding:10px 16px; background:#6c757d; color:#fff; border:none; border-radius:8px; font-weight:600; cursor:pointer; text-decoration:none; font-size:13px;">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <div class="caixa-info-card">
            <div class="info-block">
                <label>Caixa</label>
                <span><?= $caixa['id'] ?></span>
            </div>
            <div class="info-block">
                <label>Usuário</label>
                <span><?= htmlspecialchars($caixa['nome_usuario']) ?></span>
            </div>
            <div class="info-block">
                <label>Abertura</label>
                <span><?= $formatarDataHoraExibicao($caixa['data_abertura'] ?? '', $caixa['hora_abertura'] ?? '') ?></span>
            </div>
            <?php if($statusRenderTop == 'FECHADO' || $statusRenderTop == 'ENCERRADO'): ?>
            <div class="info-block">
                <label>Fechamento</label>
                <span><?= $formatarDataHoraExibicao($caixa['data_fechamento'] ?? '') ?></span>
            </div>
            <?php endif; ?>
            <div class="info-block">
                <label>Status</label>
                <?php 
                    $statusText = $caixa['status'] ?? 'N/A';
                    $statusClass = 'status-aberto';
                    $statusNorm = strtoupper(trim((string)$statusText));
                    if ($statusNorm == 'FECHADO') $statusClass = 'status-fechado';
                    if ($statusNorm == 'ENCERRADO') $statusClass = 'status-encerrado';
                ?>
                <span class="badge-status <?= $statusClass ?>" style="display:inline-block; min-width:80px; text-align:center;">
                    <?= htmlspecialchars($statusText) ?>
                </span>
            </div>
        </div>

        <div class="tabs-container">
            <div class="tabs-header">
                <button class="tab-button active" onclick="trocarAba(event, 'aba-resumo')">Resumo</button>
                <button class="tab-button" onclick="trocarAba(event, 'aba-recebimentos')">Lista de recebimentos</button>
            </div>

            <div class="tabs-content">
                <div id="aba-resumo" class="tab-pane active">
                    <h4 class="section-title-ziip">Valores recebidos no caixa</h4>
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Forma de recebimento</th>
                                <th>Vendas (Bruto)</th>
                                <th>Vendas (Líquido)</th>
                                <th>Suprimentos</th>
                                <th style="text-align: right;">Resultado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalVendas = 0; 
                            $totalSuprimento = 0;
                            
                            // Ordenar keys para estética
                            ksort($resumoData);

                            foreach($resumoData as $formaNome => $dados):
                                // Formata o nome para exibição (ex: DINHEIRO -> Dinheiro)
                                // Usa mb_convert_case para lidar corretamente com acentos (UTF-8)
                                $nomeExibicao = mb_convert_case($formaNome, MB_CASE_TITLE, "UTF-8");
                                // Tenta buscar a grafia original do sistema
                                foreach($formasDoSistema as $fs) {
                                    if (strtoupper(trim($fs['nome_forma'])) === $formaNome) {
                                        $nomeExibicao = $fs['nome_forma'];
                                        break;
                                    }
                                }
                                
                                $vendaLiquido = $dados['liquido'];
                                $vendaBruto = $dados['bruto'];
                                $detalhes = $dados['detalhes'] ?? [];
                                
                                $suprimentoVal = ($formaNome == 'DINHEIRO') ? (float)$caixa['valor_inicial'] : 0;
                                
                                // O Resultado é sempre o LÍQUIDO que entrou no caixa + Suprimentos
                                $resultado = $vendaLiquido + $suprimentoVal;
                                
                                // Pular se tudo zerado (exceto se for Dinheiro)
                                if ($resultado <= 0.001 && $formaNome != 'DINHEIRO') continue;

                                $totalVendas += $vendaLiquido;
                                $totalSuprimento += $suprimentoVal;
                                
                                // Variação entre Bruto e Líquido?
                                $temTaxa = ($vendaBruto > $vendaLiquido + 0.01);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($nomeExibicao) ?></td>
                                <td>
                                    <?php if ($vendaBruto > 0): ?>
                                        R$ <?= number_format($vendaBruto, 2, ',', '.') ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($vendaLiquido > 0): ?>
                                        <span style="<?= $temTaxa ? 'color:#dc3545; font-weight:600;' : '' ?>">
                                            R$ <?= number_format($vendaLiquido, 2, ',', '.') ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?= $suprimentoVal > 0 ? 'R$ ' . number_format($suprimentoVal, 2, ',', '.') : '-' ?></td>
                                <td style="text-align: right; font-weight: 700; color: #1e40af;">
                                    R$ <?= number_format($resultado, 2, ',', '.') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="row-total">
                                <td>TOTAL GERAL</td>
                                <td>-</td>
                                <td style="font-weight:700;">R$ <?= number_format($totalVendas, 2, ',', '.') ?></td>
                                <td>R$ <?= number_format($totalSuprimento, 2, ',', '.') ?></td>
                                <td style="text-align: right; font-weight:700;">R$ <?= number_format($totalVendas + $totalSuprimento, 2, ',', '.') ?></td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <!-- MOVIMENTAÇÕES (SUPRIMENTOS/SANGRIAS) -->
                    <h4 class="section-title-ziip" style="margin-top: 40px;">Movimentações</h4>
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Hora</th>
                                <th>Tipo</th>
                                <th>Descrição</th>
                                <th>Conta</th>
                                <th>Forma</th>
                                <th style="text-align: right;">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $resolverDataHora = static function ($data, $hora = null): string {
                                $dataStr = trim((string)$data);
                                $horaStr = trim((string)$hora);
                                if ($dataStr !== '' && preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(:\d{2})?$/', $dataStr)) {
                                    return $dataStr;
                                }
                                if ($dataStr !== '' && $horaStr !== '') {
                                    return $dataStr . ' ' . $horaStr;
                                }
                                if ($dataStr !== '') {
                                    return $dataStr . ' 00:00:00';
                                }
                                return date('Y-m-d H:i:s');
                            };
                            $fmtMov = static function ($dt, $mask) {
                                $ts = strtotime((string)$dt);
                                if ($ts === false || $ts <= 0) return '---';
                                return date($mask, $ts);
                            };

                            $contaAberturaNome = '-';
                            if (!empty($caixa['id_conta_origem'])) {
                                try {
                                    $stmtContaAbertura = $pdo->prepare("SELECT nome_conta FROM contas_financeiras WHERE id = ?");
                                    $stmtContaAbertura->execute([$caixa['id_conta_origem']]);
                                    $contaAberturaNome = (string)($stmtContaAbertura->fetchColumn() ?: '-');
                                } catch (Throwable $e) {
                                    $contaAberturaNome = '-';
                                }
                            }
                            ?>

                            <!-- ABERTURA (sempre) -->
                            <?php $dataAberturaMov = $resolverDataHora($caixa['data_abertura'] ?? '', $caixa['hora_abertura'] ?? ''); ?>
                            <tr>
                                <td><?= $fmtMov($dataAberturaMov, 'd/m') ?></td>
                                <td><?= $fmtMov($dataAberturaMov, 'H:i') ?></td>
                                <td><span class="badge-status" style="background:#e9f7ef; color:#28a745;">ABERTURA</span></td>
                                <td>
                                    <?= htmlspecialchars('Abertura do Caixa #' . ($caixa['id'] ?? $id_caixa) . ' - ' . ($caixa['nome_usuario'] ?? 'Operador')) ?>
                                    <?php if (!empty($caixa['descricao'])): ?>
                                        <br><small style="color:#999;font-style:italic">Observações: <?= htmlspecialchars($caixa['descricao']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($contaAberturaNome) ?></td>
                                <td>Dinheiro</td>
                                <td style="text-align: right; font-weight: 700;"><?= number_format((float)($caixa['valor_inicial'] ?? 0), 2, ',', '.') ?></td>
                            </tr>

                            <?php
                            // MOVIMENTAÇÕES FINANCEIRAS
                            $movLancamentos = [];
                            try {
                                $stmtMov = $pdo->prepare("
                                    SELECT 
                                        c.data_cadastro,
                                        c.categoria as tipo,
                                        c.natureza as natureza,
                                        c.descricao,
                                        cf.nome_conta,
                                        COALESCE(f.nome_forma, c.forma_pagamento_detalhe, 'Outros') as forma_pagamento,
                                        COALESCE(c.valor_parcela, c.valor_total, 0) as valor,
                                        c.observacoes
                                    FROM contas c
                                    LEFT JOIN formas_pagamento f ON c.id_forma_pgto = f.id
                                    LEFT JOIN contas_financeiras cf ON c.id_conta_origem = cf.id
                                    WHERE c.id_caixa_referencia = ?
                                      AND c.id_admin = ?
                                      AND c.categoria IN ('SUPRIMENTO', 'SANGRIA', 'DESPESA', 'TRANSFERENCIA')
                                    ORDER BY c.data_cadastro DESC
                                ");
                                $stmtMov->execute([$id_caixa, $id_admin]);
                                $movLancamentos = $stmtMov->fetchAll(PDO::FETCH_ASSOC) ?: [];
                            } catch (Throwable $e) {
                                $movLancamentos = [];
                            }

                            foreach ($movLancamentos as $mov):
                            ?>
                            <tr>
                                <td><?= $fmtMov($mov['data_cadastro'] ?? '', 'd/m') ?></td>
                                <td><?= $fmtMov($mov['data_cadastro'] ?? '', 'H:i') ?></td>
                                <?php
                                $tipoCat = strtoupper(trim((string)($mov['tipo'] ?? '-')));
                                $naturezaCat = strtoupper(trim((string)($mov['natureza'] ?? '')));
                                // Se por qualquer motivo a `categoria` vier trocada, mas a `natureza` estiver correta,
                                // reclassificamos para o usuário não confundir Suprimento x Sangria.
                                if (in_array($tipoCat, ['SUPRIMENTO', 'SANGRIA'], true)) {
                                    if ($naturezaCat === 'RECEITA') $tipoCat = 'SUPRIMENTO';
                                    if ($naturezaCat === 'DESPESA') $tipoCat = 'SANGRIA';
                                }
                                ?>
                                <td><span class="badge-status" style="background:#eee; color:#555"><?= htmlspecialchars($tipoCat) ?></span></td>
                                <td>
                                    <?= htmlspecialchars((string)($mov['descricao'] ?? '-')) ?>
                                    <?php if (!empty($mov['observacoes'])): ?>
                                        <br><small style="color:#999;font-style:italic">Observações: <?= htmlspecialchars((string)$mov['observacoes']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars((string)($mov['nome_conta'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($mov['forma_pagamento'] ?? '-')) ?></td>
                                <td style="text-align: right; font-weight: 700;"><?= number_format((float)($mov['valor'] ?? 0), 2, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>

                            <!-- FECHAMENTO (quando aplicável) -->
                            <?php
                                $statusCaixaRender = strtoupper(trim((string)($caixa['status'] ?? '')));
                            ?>
                            <?php if (in_array($statusCaixaRender, ['FECHADO', 'ENCERRADO'], true)): ?>
                                <?php
                                $contaFechNome = '-';
                                if (!empty($caixa['id_conta_fechamento'])) {
                                    try {
                                        $stmtContaFech = $pdo->prepare("SELECT nome_conta FROM contas_financeiras WHERE id = ?");
                                        $stmtContaFech->execute([$caixa['id_conta_fechamento']]);
                                        $contaFechNome = (string)($stmtContaFech->fetchColumn() ?: '-');
                                    } catch (Throwable $e) {
                                        $contaFechNome = '-';
                                    }
                                }
                                $dataFechMov = $resolverDataHora($caixa['data_fechamento'] ?? '', $caixa['hora_fechamento'] ?? '');
                                ?>
                                <tr>
                                    <td><?= $fmtMov($dataFechMov, 'd/m') ?></td>
                                    <td><?= $fmtMov($dataFechMov, 'H:i') ?></td>
                                    <td><span class="badge-status" style="background:#fbeaea; color:#dc3545;">FECHAMENTO</span></td>
                                    <td>Encerramento do caixa</td>
                                    <td><?= htmlspecialchars($contaFechNome) ?></td>
                                    <td>Dinheiro</td>
                                    <td style="text-align: right; font-weight: 700;"><?= number_format((float)($caixa['valor_fechamento'] ?? 0), 2, ',', '.') ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div id="aba-recebimentos" class="tab-pane" style="display:none;">
                    <h4 class="section-title-ziip">Lista de recebimentos</h4>
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Hora</th>
                                <th>Descrição</th>
                                <th>Cliente</th>
                                <th>NFC-e</th>
                                <th>Forma</th>
                                <th style="text-align: right;">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($listaRecebimentos)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px; color: #999;">
                                        Nenhum lançamento encontrado.
                                        <?php if (($debugTotalReceitasCaixa ?? 0) > 0): ?>
                                            <br><small style="color:#dc3545;">Debug: existem <?= (int)$debugTotalReceitasCaixa ?> receitas pagas no caixa, mas foram filtradas por categoria.</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: foreach($listaRecebimentos as $l): ?>
                            <tr>
                                <td><?= date('d/m', strtotime($l['data_cadastro'])) ?></td>
                                <td><?= date('H:i', strtotime($l['data_cadastro'])) ?></td>
                                <td><?= htmlspecialchars($l['descricao']) ?></td>
                                <td><?= htmlspecialchars($l['fornecedor_cliente']) ?></td>
                                <td>
                                    <?php if (!empty($l['id_real_venda'])): ?>
                                        <?php if (($l['nfce_status'] ?? '') == 'AUTORIZADA'): ?>
                                            <a href="<?= $l['nfce_url'] ?>" target="_blank" class="btn-ziip" style="background:#28a745; padding:5px 10px; font-size:12px;">
                                                <i class="fas fa-check-circle"></i> Ver Nota
                                            </a>
                                        <?php else: ?>
                                            <button onclick="emitirNFCe(<?= $l['id_real_venda'] ?>, this)" class="btn-ziip" style="background:#007bff; padding:5px 10px; font-size:12px; border:none;">
                                                <i class="fas fa-paper-plane"></i> Emitir NFC-e
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:#999;font-size:12px;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge-status" style="background:#eee; color:#555"><?= $l['forma_pagamento'] ?></span></td>
                                <td style="text-align: right; font-weight: 700;">R$ <?= number_format($l['valor'], 2, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
            function emitirNFCe(idVenda, btn) {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Emitindo...';
                btn.disabled = true;

                $.post('../nfe/emitir_nota.php', { id_venda: idVenda }, function(res) {
                    if (res.success) {
                        Swal.fire({
                            title: 'NFC-e Emitida!',
                            html: `
                                <div style="font-size: 14px; color: #555; margin-bottom: 20px;">
                                    A nota fiscal foi autorizada com sucesso.
                                </div>
                                <div style="background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px dashed #ccc; margin-bottom: 20px; font-family: monospace; font-size: 12px; color: #333; word-break: break-all;">
                                    ${res.chave}
                                </div>
                                <a href="${res.url}" target="_blank" style="display: inline-flex; align-items: center; justify-content: center; padding: 12px 24px; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: #fff; border-radius: 8px; text-decoration: none; font-weight: 700; font-family: 'Exo', sans-serif; box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3); transition: all 0.3s ease; gap: 8px;">
                                    <i class="fas fa-qrcode"></i> Visualizar Nota (DANFE)
                                </a>
                            `,
                            icon: 'success',
                            confirmButtonColor: '#6c757d',
                            confirmButtonText: 'Fechar e recarregar'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Erro', res.message, 'error');
                        btn.innerHTML = originalHtml;
                        btn.disabled = false;
                    }
                }, 'json').fail(function() {
                    Swal.fire('Erro', 'Falha na comunicação com o servidor.', 'error');
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                });
            }
        </script>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function trocarAba(event, abaId) {
            const panes = document.querySelectorAll('.tab-pane');
            const btns = document.querySelectorAll('.tab-button');
            panes.forEach(p => p.style.display = 'none');
            btns.forEach(b => b.classList.remove('active'));
            document.getElementById(abaId).style.display = 'block';
            event.currentTarget.classList.add('active');
        }
        
        function abrirModalLog() {
            Swal.fire({
                title: '<i class="fas fa-search"></i> Log de Movimentações',
                html: `
                    <div style="text-align:left; max-height:400px; overflow-y:auto;">
                        <p style="color:#666; font-size:13px; margin-bottom:15px;">
                            Histórico de ações realizadas neste caixa.
                        </p>
                        <div id="log_content" style="background:#f8f9fa; padding:15px; border-radius:8px; font-size:12px; font-family:monospace;">
                            Carregando...
                        </div>
                    </div>
                `,
                width: '700px',
                showConfirmButton: true,
                confirmButtonText: 'Fechar',
                confirmButtonColor: '#6c757d',
                didOpen: () => {
                    // Carregar logs via AJAX se necessário
                    document.getElementById('log_content').innerHTML = `
                        <div style="display:flex; flex-direction:column; gap:8px;">
                            <div style="padding:8px; background:#e9f7ef; border-left:3px solid #28a745; border-radius:4px;">
                                <strong style="color:#28a745;">ABERTURA</strong> - <?= $formatarDataHoraExibicao($caixa['data_abertura'] ?? '', $caixa['hora_abertura'] ?? '') ?><br>
                                <small>Caixa aberto por <?= htmlspecialchars($caixa['nome_usuario'] ?? 'Operador') ?> com fundo de R$ <?= number_format($caixa['valor_inicial'], 2, ',', '.') ?></small>
                            </div>
                            <?php if ($caixa['status'] == 'FECHADO' || $caixa['status'] == 'ENCERRADO'): ?>
                            <div style="padding:8px; background:#fbeaea; border-left:3px solid #dc3545; border-radius:4px;">
                                <strong style="color:#dc3545;">FECHAMENTO</strong> - <?= $formatarDataHoraExibicao($caixa['data_fechamento'] ?? '') ?><br>
                                <small>Caixa encerrado</small>
                            </div>
                            <?php endif; ?>
                        </div>
                    `;
                }
            });
        }
        
        function abrirModalEncerramento() {
            Swal.fire({
                title: 'Encerramento do caixa <?= $caixa['id'] ?>',
                html: `
                    <div style="background:linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);padding:25px;border-radius:12px;text-align:center;margin:20px 0">
                        <div style="font-size:14px;color:#1e40af;font-weight:600;margin-bottom:8px">Em caixa</div>
                        <div style="font-size:36px;font-weight:700;color:#1e40af">R$ <?= number_format($total_em_caixa, 2, ',', '.') ?></div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px">
                        <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Data*</label>
                        <input type="date" id="data_enc" style="width:100%;padding:12px;border:2px solid #e0e0e0;border-radius:8px" value="<?= date('Y-m-d') ?>"></div>
                        <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Hora*</label>
                        <input type="time" id="hora_enc" style="width:100%;padding:12px;border:2px solid #e0e0e0;border-radius:8px" value="<?= $hora_atual ?>"></div>
                    </div>
                    <div style="margin-bottom:15px"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Conta destino*</label>
                    <select id="conta_enc" style="width:100%;padding:12px;border:2px solid #e0e0e0;border-radius:8px">
                        <option value="">Selecione...</option>
                        <?php foreach($contas_financeiras as $conta): ?>
                            <option value="<?= $conta['id'] ?>"><?= htmlspecialchars($conta['nome_conta']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                    <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Comentário</label>
                    <textarea id="comentario_enc" style="width:100%;padding:12px;border:2px solid #e0e0e0;border-radius:8px" rows="4" placeholder="Observações sobre o encerramento..."></textarea></div>
                `,
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonText: '<i class="fas fa-check"></i> Encerrar caixa',
                denyButtonText: 'Colocar em revisão',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#1e40af',
                denyButtonColor: '#6c757d',
                width: '550px',
                preConfirm: () => {
                    if (!document.getElementById('conta_enc').value || !document.getElementById('data_enc').value || !document.getElementById('hora_enc').value) {
                        Swal.showValidationMessage('Preencha todos os campos obrigatórios');
                        return false;
                    }
                    return {
                        id_conta_destino: document.getElementById('conta_enc').value,
                        data_fechamento: document.getElementById('data_enc').value,
                        hora_fechamento: document.getElementById('hora_enc').value,
                        comentario: document.getElementById('comentario_enc').value
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    encerrarCaixa(result.value);
                } else if (result.isDenied) {
                    colocarEmRevisao();
                }
            });
        }
        
        function abrirModalSuprimento() {
            Swal.fire({
                title: 'Adicionar Suprimento',
                html: `
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px">
                        <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Valor*</label>
                        <input type="text" id="valor_sup" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px" placeholder="0,00"></div>
                        <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Forma*</label>
                        <select id="forma_sup" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px">
                            <option value="">Selecione...</option>
                            <?php foreach($formasDoSistema as $f): ?>
                                <option value="<?= htmlspecialchars($f['nome_forma']) ?>"><?= htmlspecialchars($f['nome_forma']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    </div>
                    <div style="margin-bottom:15px"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Descrição</label>
                    <input type="text" id="descricao_sup" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px"></div>
                    <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Observações</label>
                    <textarea id="obs_sup" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px" rows="3"></textarea></div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Adicionar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#28a745',
                width: '600px',
                preConfirm: () => {
                    if (!document.getElementById('valor_sup').value || !document.getElementById('forma_sup').value) {
                        Swal.showValidationMessage('Preencha todos os campos obrigatórios');
                        return false;
                    }
                    return {
                        valor: document.getElementById('valor_sup').value,
                        forma_pagamento: document.getElementById('forma_sup').value,
                        descricao: document.getElementById('descricao_sup').value || 'Suprimento de caixa',
                        observacoes: document.getElementById('obs_sup').value
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) adicionarMovimentacao('SUPRIMENTO', result.value);
            });
        }
        
        function abrirModalSangria() {
            Swal.fire({
                title: 'Registrar Sangria',
                html: `
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px">
                        <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Valor*</label>
                        <input type="text" id="valor_san" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px" placeholder="0,00"></div>
                        <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Forma*</label>
                        <select id="forma_san" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px">
                            <option value="">Selecione...</option>
                            <?php foreach($formasDoSistema as $f): ?>
                                <option value="<?= htmlspecialchars($f['nome_forma']) ?>"><?= htmlspecialchars($f['nome_forma']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    </div>
                    <div style="margin-bottom:15px"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Descrição</label>
                    <input type="text" id="descricao_san" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px"></div>
                    <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Observações</label>
                    <textarea id="obs_san" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px" rows="3"></textarea></div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Registrar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#f39c12',
                width: '600px',
                preConfirm: () => {
                    if (!document.getElementById('valor_san').value || !document.getElementById('forma_san').value) {
                        Swal.showValidationMessage('Preencha todos os campos obrigatórios');
                        return false;
                    }
                    return {
                        valor: document.getElementById('valor_san').value,
                        forma_pagamento: document.getElementById('forma_san').value,
                        descricao: document.getElementById('descricao_san').value || 'Sangria de caixa',
                        observacoes: document.getElementById('obs_san').value
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) adicionarMovimentacao('SANGRIA', result.value);
            });
        }
        
        function abrirModalDespesa() {
            Swal.fire({
                title: 'Registrar Despesa',
                html: `
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px">
                        <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Valor*</label>
                        <input type="text" id="valor_des" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px" placeholder="0,00"></div>
                        <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Forma*</label>
                        <select id="forma_des" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px">
                            <option value="">Selecione...</option>
                            <?php foreach($formasDoSistema as $f): ?>
                                <option value="<?= htmlspecialchars($f['nome_forma']) ?>"><?= htmlspecialchars($f['nome_forma']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    </div>
                    <div style="margin-bottom:15px"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Descrição*</label>
                    <input type="text" id="descricao_des" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px" required></div>
                    <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Observações</label>
                    <textarea id="obs_des" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px" rows="3"></textarea></div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Registrar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#b92426',
                width: '600px',
                preConfirm: () => {
                    if (!document.getElementById('valor_des').value || !document.getElementById('forma_des').value || !document.getElementById('descricao_des').value) {
                        Swal.showValidationMessage('Preencha todos os campos obrigatórios');
                        return false;
                    }
                    return {
                        valor: document.getElementById('valor_des').value,
                        forma_pagamento: document.getElementById('forma_des').value,
                        descricao: document.getElementById('descricao_des').value,
                        observacoes: document.getElementById('obs_des').value
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) adicionarMovimentacao('DESPESA', result.value);
            });
        }
        
        function abrirModalTransferencia() {
            Swal.fire({
                title: 'Registrar Transferência',
                html: `
                    <div style="margin-bottom:15px"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Valor*</label>
                    <input type="text" id="valor_tra" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px" placeholder="0,00"></div>
                    <div style="margin-bottom:15px"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Conta Destino*</label>
                    <select id="conta_tra" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px">
                        <option value="">Selecione...</option>
                        <?php foreach($contas_financeiras as $conta): ?>
                            <option value="<?= $conta['id'] ?>"><?= htmlspecialchars($conta['nome_conta']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                    <div style="margin-bottom:15px"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Descrição</label>
                    <input type="text" id="descricao_tra" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px"></div>
                    <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Observações</label>
                    <textarea id="obs_tra" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px" rows="3"></textarea></div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Transferir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#17a2b8',
                width: '600px',
                preConfirm: () => {
                    if (!document.getElementById('valor_tra').value || !document.getElementById('conta_tra').value) {
                        Swal.showValidationMessage('Preencha todos os campos obrigatórios');
                        return false;
                    }
                    return {
                        valor: document.getElementById('valor_tra').value,
                        id_conta: document.getElementById('conta_tra').value,
                        forma_pagamento: 'Transferência',
                        descricao: document.getElementById('descricao_tra').value || 'Transferência bancária',
                        observacoes: document.getElementById('obs_tra').value
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) adicionarMovimentacao('TRANSFERENCIA', result.value);
            });
        }
        
        function abrirModalEncerramento() {
            Swal.fire({
                title: 'Encerramento do caixa <?= $caixa['id'] ?>',
                html: `
                    <div style="background:#dbeafe;padding:20px;border-radius:12px;text-align:center;margin:20px 0">
                        <div style="font-size:14px;color:#1e40af;font-weight:600;margin-bottom:8px">Em caixa</div>
                        <div style="font-size:32px;font-weight:700;color:#1e40af">R$ <?= number_format($total_em_caixa, 2, ',', '.') ?></div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px">
                        <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Data*</label>
                        <input type="date" id="data_enc" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px" value="<?= date('Y-m-d') ?>"></div>
                        <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Hora*</label>
                        <input type="time" id="hora_enc" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px" value="<?= $hora_atual ?>"></div>
                    </div>
                    <div style="margin-bottom:15px"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Conta destino*</label>
                    <select id="conta_enc" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px">
                        <option value="">Selecione...</option>
                        <?php foreach($contas_financeiras as $conta): ?>
                            <option value="<?= $conta['id'] ?>"><?= htmlspecialchars($conta['nome_conta']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                    <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Comentário</label>
                    <textarea id="comentario_enc" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px" rows="4"></textarea></div>
                `,
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonText: '<i class="fas fa-check"></i> Encerrar caixa',
                denyButtonText: 'Colocar em revisão',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#28a745',
                denyButtonColor: '#6c757d',
                width: '600px',
                preConfirm: () => {
                    if (!document.getElementById('conta_enc').value || !document.getElementById('data_enc').value || !document.getElementById('hora_enc').value) {
                        Swal.showValidationMessage('Preencha todos os campos obrigatórios');
                        return false;
                    }
                    return {
                        id_conta_destino: document.getElementById('conta_enc').value,
                        data_fechamento: document.getElementById('data_enc').value,
                        hora_fechamento: document.getElementById('hora_enc').value,
                        comentario: document.getElementById('comentario_enc').value
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    encerrarCaixa(result.value);
                } else if (result.isDenied) {
                    Swal.fire('Info', 'Caixa colocado em revisão', 'info');
                }
            });
        }
        
        function abrirModalEncerramento() {
            Swal.fire({
                title: 'Encerramento do caixa <?= $caixa['id'] ?>',
                html: `
                    <div style="background:linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);padding:25px;border-radius:12px;text-align:center;margin:20px 0">
                        <div style="font-size:14px;color:#1e40af;font-weight:600;margin-bottom:8px">Em caixa</div>
                        <div style="font-size:36px;font-weight:700;color:#1e40af">R$ <?= number_format($total_em_caixa, 2, ',', '.') ?></div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px">
                        <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Data*</label>
                        <input type="date" id="data_enc" style="width:100%;padding:12px;border:2px solid #e0e0e0;border-radius:8px" value="<?= date('Y-m-d') ?>"></div>
                        <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Hora*</label>
                        <input type="time" id="hora_enc" style="width:100%;padding:12px;border:2px solid #e0e0e0;border-radius:8px" value="<?= $hora_atual ?>"></div>
                    </div>
                    <div style="margin-bottom:15px"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Conta destino*</label>
                    <select id="conta_enc" style="width:100%;padding:12px;border:2px solid #e0e0e0;border-radius:8px">
                        <option value="">Selecione...</option>
                        <?php foreach($contas_financeiras as $conta): ?>
                            <option value="<?= $conta['id'] ?>"><?= htmlspecialchars($conta['nome_conta']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                    <div><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Comentário</label>
                    <textarea id="comentario_enc" style="width:100%;padding:12px;border:2px solid #e0e0e0;border-radius:8px" rows="4" placeholder="Observações sobre o encerramento..."></textarea></div>
                `,
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonText: '<i class="fas fa-check"></i> Encerrar caixa',
                denyButtonText: 'Colocar em revisão',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#1e40af',
                denyButtonColor: '#6c757d',
                width: '550px',
                preConfirm: () => {
                    if (!document.getElementById('conta_enc').value || !document.getElementById('data_enc').value || !document.getElementById('hora_enc').value) {
                        Swal.showValidationMessage('Preencha todos os campos obrigatórios');
                        return false;
                    }
                    return {
                        id_conta_destino: document.getElementById('conta_enc').value,
                        data_fechamento: document.getElementById('data_enc').value,
                        hora_fechamento: document.getElementById('hora_enc').value,
                        comentario: document.getElementById('comentario_enc').value
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    encerrarCaixa(result.value);
                } else if (result.isDenied) {
                    Swal.fire('Info', 'Caixa colocado em revisão', 'info');
                }
            });
        }
        
        async function adicionarMovimentacao(tipo, dados) {
            const formData = new FormData();
            formData.append('acao', 'adicionar_movimentacao');
            formData.append('tipo', tipo);
            for (let key in dados) {
                formData.append(key, dados[key]);
            }
            
            try {
                const res = await fetch('detalhes_movimentacao.php?id=<?= $id_caixa ?>', {method: 'POST', body: formData});
                const resposta = await res.json();
                
                if (resposta.status === 'success') {
                    Swal.fire({title: 'Sucesso!', text: resposta.message, icon: 'success', confirmButtonColor: '#28a745'}).then(() => location.reload());
                } else {
                    Swal.fire('Erro', resposta.message, 'error');
                }
            } catch (e) {
                Swal.fire('Erro', 'Erro ao processar movimentação', 'error');
            }
        }
        
        async function encerrarCaixa(dados) {
            const formData = new FormData();
            formData.append('acao', 'encerrar_caixa');
            for (let key in dados) {
                formData.append(key, dados[key]);
            }
            
            try {
                const res = await fetch('detalhes_movimentacao.php?id=<?= $id_caixa ?>', {method: 'POST', body: formData});
                const resposta = await res.json();
                
                if (resposta.status === 'success') {
                    Swal.fire({title: 'Sucesso!', text: resposta.message, icon: 'success', confirmButtonColor: '#28a745'}).then(() => location.reload());
                } else {
                    Swal.fire('Erro', resposta.message, 'error');
                }
            } catch (e) {
                Swal.fire('Erro', 'Erro ao encerrar caixa', 'error');
            }
        }
        
        async function colocarEmRevisao() {
            const formData = new FormData();
            formData.append('acao', 'colocar_revisao');
            
            try {
                const res = await fetch('detalhes_movimentacao.php?id=<?= $id_caixa ?>', {method: 'POST', body: formData});
                const resposta = await res.json();
                
                if (resposta.status === 'success') {
                    Swal.fire({title: 'Info', text: 'Caixa colocado em revisão', icon: 'info', confirmButtonColor: '#6c757d'}).then(() => location.reload());
                } else {
                    Swal.fire('Erro', resposta.message, 'error');
                }
            } catch (e) {
                Swal.fire('Erro', 'Erro ao colocar em revisão', 'error');
            }
        }
    </script>

</body>
</html>