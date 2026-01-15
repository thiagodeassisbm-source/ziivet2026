<?php
/**
 * =========================================================================================
 * ZIIPVET - PONTO DE VENDA (PDV) - VERSÃO MODULAR
 * ARQUIVO: vendas.php
 * VERSÃO: 5.0.0 - ESTRUTURA MODULAR COM BANDEIRAS E PARCELAS
 * =========================================================================================
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definir constante para permitir inclusão do módulo de recebimento
define('VENDAS_MODULE_LOADED', true);

$id_admin = $_SESSION['id_admin'] ?? 1;
$usuario_logado = $_SESSION['nome'] ?? 'Sistema';

// ==========================================================
// PROCESSAMENTO AJAX (JSON)
// ==========================================================

// BUSCAR ANIMAIS
if (isset($_POST['acao']) && $_POST['acao'] === 'buscar_animais') {
    ob_clean();
    header('Content-Type: application/json');
    
    $id_cli = $_POST['id_cliente'] ?? '';
    
    try {
        if (!empty($id_cli)) {
            $sql = "SELECT p.id, p.nome_paciente, p.id_cliente, c.nome as nome_dono 
                    FROM pacientes p 
                    INNER JOIN clientes c ON p.id_cliente = c.id 
                    WHERE p.id_cliente = :id_cli AND p.status = 'ATIVO' AND c.id_admin = :id_admin 
                    ORDER BY p.nome_paciente ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id_cli' => $id_cli, ':id_admin' => $id_admin]);
        } else {
            $sql = "SELECT p.id, p.nome_paciente, p.id_cliente, c.nome as nome_dono 
                    FROM pacientes p 
                    INNER JOIN clientes c ON p.id_cliente = c.id 
                    WHERE c.id_admin = :id_admin AND p.status = 'ATIVO' 
                    ORDER BY p.nome_paciente ASC LIMIT 500";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id_admin' => $id_admin]);
        }
        
        $animais = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'dados' => $animais]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// SALVAR VENDA / ORÇAMENTO
if (isset($_POST['acao']) && $_POST['acao'] === 'salvar_venda') {
    ob_clean();
    header('Content-Type: application/json');

    try {
        $pdo->beginTransaction();
        $dados = json_decode($_POST['dados_venda'], true);
        
        if (empty($dados['itens'])) throw new Exception("Nenhum item adicionado.");

        $is_orcamento = ($dados['tipo'] === 'Orçamento');
        $status_pgto = ($dados['acao_btn'] === 'receber' && !$is_orcamento) ? 'PAGO' : 'PENDENTE';
        $tipo_venda_salvar = $is_orcamento ? null : $dados['tipo_venda'];
        $data_validade = $is_orcamento ? $dados['data_validade'] : null;

        // 1. Inserir Venda
        $sqlVenda = "INSERT INTO vendas (id_admin, usuario_vendedor, id_cliente, id_paciente, data_venda, data_validade, tipo_movimento, tipo_venda, valor_total, observacoes, status_pagamento) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmtV = $pdo->prepare($sqlVenda);
        $stmtV->execute([
            $id_admin,
            $usuario_logado,
            !empty($dados['id_cliente']) ? $dados['id_cliente'] : null,
            !empty($dados['id_paciente']) ? $dados['id_paciente'] : null,
            $dados['data'],
            $data_validade,
            $dados['tipo'], 
            $tipo_venda_salvar,
            $dados['total_geral'],
            $dados['obs'],
            $status_pgto
        ]);
        $id_venda = $pdo->lastInsertId();

        // 2. Itens e Baixa de Estoque
        $sqlItem = "INSERT INTO vendas_itens (id_venda, id_produto, quantidade, valor_unitario, valor_total) VALUES (?, ?, ?, ?, ?)";
        $stmtItem = $pdo->prepare($sqlItem);
        
        $sqlEstoque = "UPDATE produtos SET estoque_inicial = estoque_inicial - ? WHERE id = ? AND monitorar_estoque = 1";
        $stmtEstoque = $pdo->prepare($sqlEstoque);

        foreach ($dados['itens'] as $item) {
            $stmtItem->execute([$id_venda, $item['id'], $item['qtd'], $item['valor'], $item['total']]);
            
            if (!$is_orcamento) {
                $stmtEstoque->execute([$item['qtd'], $item['id']]);
            }
        }

        // 3. LANÇAMENTO FINANCEIRO (apenas se for recebimento)
        if (!$is_orcamento && $status_pgto === 'PAGO') {
            // Buscar nome do cliente
            $nome_cliente = 'Consumidor Final';
            if (!empty($dados['id_cliente'])) {
                $stmtCli = $pdo->prepare("SELECT nome FROM clientes WHERE id = ?");
                $stmtCli->execute([$dados['id_cliente']]);
                $cliente_data = $stmtCli->fetch(PDO::FETCH_ASSOC);
                if ($cliente_data) {
                    $nome_cliente = $cliente_data['nome'];
                }
            }

            // Buscar dados da forma de pagamento
            $nome_forma_pgto = $dados['nome_forma_pagamento'] ?? 'Não informada';
            $tipo_forma_pgto = null;
            $id_conta_destino = null;
            $id_forma_base = $dados['forma_pagamento'] ?? null;
            
            // Dados de parcelamento
            $qtd_parcelas = $dados['qtd_parcelas'] ?? 1;
            $taxa_aplicada = $dados['taxa_aplicada'] ?? '0%';
            
            // ✅ CALCULAR VALOR LÍQUIDO (DESCONTANDO A TAXA DA OPERADORA)
            $valor_bruto = $dados['total_geral']; // Valor total da venda
            $valor_liquido = $valor_bruto; // Inicialmente é o mesmo
            $valor_taxa_descontada = 0;
            
            // Extrair porcentagem da taxa (ex: "4%" -> 4)
            $percentual_taxa = 0;
            if (preg_match('/(\d+(?:\.\d+)?)%/', $taxa_aplicada, $matches)) {
                $percentual_taxa = floatval($matches[1]);
            }
            
            // Se houver taxa, calcular o valor líquido
            if ($percentual_taxa > 0) {
                $valor_taxa_descontada = ($valor_bruto * $percentual_taxa) / 100;
                $valor_liquido = $valor_bruto - $valor_taxa_descontada;
            }
            
            if ($id_forma_base) {
                $stmtForma = $pdo->prepare("SELECT nome_forma, tipo, configuracoes FROM formas_pagamento WHERE id = ?");
                $stmtForma->execute([$id_forma_base]);
                $forma_data = $stmtForma->fetch(PDO::FETCH_ASSOC);
                
                if ($forma_data) {
                    $tipo_forma_pgto = $forma_data['tipo'];
                    
                    if (!empty($forma_data['configuracoes'])) {
                        $configuracoes_forma = json_decode($forma_data['configuracoes'], true);
                        $id_conta_destino = $configuracoes_forma['id_conta_destino'] ?? null;
                    }
                }
            }

            // Determinar conta financeira destino
            $id_conta_financeira_destino = null;
            
            if ($tipo_forma_pgto === 'Espécie') {
                $id_caixa = !empty($dados['caixa_ativo']) ? $dados['caixa_ativo'] : null;
                
                if ($id_caixa) {
                    $stmtCaixaUser = $pdo->prepare("
                        SELECT u.id_conta_caixa 
                        FROM caixas c 
                        INNER JOIN usuarios u ON c.id_usuario = u.id 
                        WHERE c.id = ?
                    ");
                    $stmtCaixaUser->execute([$id_caixa]);
                    $conta_caixa_data = $stmtCaixaUser->fetch(PDO::FETCH_ASSOC);
                    
                    if ($conta_caixa_data && !empty($conta_caixa_data['id_conta_caixa'])) {
                        $id_conta_financeira_destino = $conta_caixa_data['id_conta_caixa'];
                        
                        $stmtSaldoAtual = $pdo->prepare("SELECT saldo_inicial FROM contas_financeiras WHERE id = ?");
                        $stmtSaldoAtual->execute([$id_conta_financeira_destino]);
                        $saldo_atual = $stmtSaldoAtual->fetchColumn();
                        
                        // ✅ USAR VALOR LÍQUIDO (já descontada a taxa)
                        $novo_saldo = $saldo_atual + $valor_liquido;
                        
                        $stmtAtualizaSaldo = $pdo->prepare("UPDATE contas_financeiras SET saldo_inicial = ?, data_saldo = ? WHERE id = ?");
                        $stmtAtualizaSaldo->execute([$novo_saldo, date('Y-m-d'), $id_conta_financeira_destino]);
                    }
                }
            } else {
                if ($id_conta_destino) {
                    $id_conta_financeira_destino = $id_conta_destino;
                    
                    $stmtSaldoAtual = $pdo->prepare("SELECT saldo_inicial FROM contas_financeiras WHERE id = ?");
                    $stmtSaldoAtual->execute([$id_conta_financeira_destino]);
                    $saldo_atual = $stmtSaldoAtual->fetchColumn();
                    
                    // ✅ USAR VALOR LÍQUIDO (já descontada a taxa)
                    $novo_saldo = $saldo_atual + $valor_liquido;
                    
                    $stmtAtualizaSaldo = $pdo->prepare("UPDATE contas_financeiras SET saldo_inicial = ?, data_saldo = ? WHERE id = ?");
                    $stmtAtualizaSaldo->execute([$novo_saldo, date('Y-m-d'), $id_conta_financeira_destino]);
                }
            }

            $id_caixa = !empty($dados['caixa_ativo']) ? $dados['caixa_ativo'] : null;

            // Inserir lançamento com parcelamento
            $sqlLancamento = "INSERT INTO lancamentos (
                id_admin, 
                tipo, 
                categoria, 
                descricao, 
                documento, 
                fornecedor_cliente, 
                id_venda, 
                data_vencimento, 
                data_pagamento, 
                valor,
                parcela_atual,
                total_parcelas,
                forma_pagamento,
                id_conta_financeira,
                status,
                id_caixa_referencia,
                data_cadastro
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            // ✅ DESCRIÇÃO DETALHADA COM VALORES BRUTO E LÍQUIDO
            $descricao_lancamento = "Venda PDV #$id_venda";
            if ($qtd_parcelas > 1) {
                $descricao_lancamento .= " - {$qtd_parcelas}x";
            }
            if ($percentual_taxa > 0) {
                $descricao_lancamento .= " | Taxa: {$taxa_aplicada} (R$ " . number_format($valor_taxa_descontada, 2, ',', '.') . ")";
                $descricao_lancamento .= " | Bruto: R$ " . number_format($valor_bruto, 2, ',', '.') . " → Líquido: R$ " . number_format($valor_liquido, 2, ',', '.');
            }
            $documento_lancamento = (string)$id_venda;
            
            $stmtLanc = $pdo->prepare($sqlLancamento);
            $stmtLanc->execute([
                $id_admin,
                'ENTRADA',
                'VENDAS',
                $descricao_lancamento,
                $documento_lancamento,
                $nome_cliente,
                $id_venda,
                $dados['data'],
                $valor_liquido, // ✅ REGISTRAR VALOR LÍQUIDO (JÁ DESCONTADA A TAXA)
                1,
                $qtd_parcelas,
                $nome_forma_pgto,
                $id_conta_financeira_destino,
                'PAGO',
                $id_caixa
            ]);
        }

        $pdo->commit();
        $msg_sucesso = $is_orcamento ? 'Orçamento salvo com sucesso!' : 'Venda realizada com sucesso!';
        echo json_encode(['status' => 'success', 'message' => $msg_sucesso, 'id' => $id_venda]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Erro ao salvar: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// CARREGAMENTO INICIAL
// ==========================================================
$titulo_pagina = "Ponto de Venda (PDV)";

try {
    $clientes = $pdo->query("SELECT id, nome FROM clientes WHERE id_admin = $id_admin AND status = 'ATIVO' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    $sql_animais = "SELECT p.id, p.nome_paciente, p.id_cliente, c.nome as nome_dono 
                    FROM pacientes p 
                    INNER JOIN clientes c ON p.id_cliente = c.id 
                    WHERE c.id_admin = $id_admin AND p.status = 'ATIVO' 
                    ORDER BY p.nome_paciente ASC LIMIT 500"; 
    $animais = $pdo->query($sql_animais)->fetchAll(PDO::FETCH_ASSOC);

    $produtos = $pdo->query("SELECT id, nome, preco_venda FROM produtos WHERE id_admin = $id_admin AND status = 'ATIVO' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Buscar formas de pagamento com bandeiras expandidas
    $formas_pagamento_raw = $pdo->query("SELECT id, nome_forma, tipo, configuracoes FROM formas_pagamento WHERE id_admin = $id_admin AND status = 'Ativo' ORDER BY nome_forma ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    $formas_pagamento = [];
    
    foreach ($formas_pagamento_raw as $forma) {
        if ($forma['tipo'] === 'Maquininha de cartão' && !empty($forma['configuracoes'])) {
            $config = json_decode($forma['configuracoes'], true);
            
            if (!empty($config['bandeiras']) && is_array($config['bandeiras'])) {
                foreach ($config['bandeiras'] as $bandeira) {
                    $formas_pagamento[] = [
                        'id' => $forma['id'],
                        'nome_forma' => $bandeira['nome'],
                        'id_forma_base' => $forma['id'],
                        'tipo_bandeira' => $bandeira['tipo'],
                        'max_parcelas' => $bandeira['max_parcelas'] ?? 1,
                        'parcelas' => $bandeira['parcelas'] ?? []
                    ];
                }
            } else {
                $formas_pagamento[] = [
                    'id' => $forma['id'],
                    'nome_forma' => $forma['nome_forma'],
                    'id_forma_base' => $forma['id'],
                    'tipo_bandeira' => null,
                    'max_parcelas' => 1,
                    'parcelas' => []
                ];
            }
        } else {
            $formas_pagamento[] = [
                'id' => $forma['id'],
                'nome_forma' => $forma['nome_forma'],
                'id_forma_base' => $forma['id'],
                'tipo_bandeira' => null,
                'max_parcelas' => 1,
                'parcelas' => []
            ];
        }
    }

    $caixas_ativos = $pdo->query("SELECT c.id, c.descricao, u.nome as usuario_nome 
                                   FROM caixas c 
                                   INNER JOIN usuarios u ON c.id_usuario = u.id 
                                   WHERE c.id_admin = $id_admin AND c.status = 'ABERTO' 
                                   ORDER BY c.data_abertura DESC")->fetchAll(PDO::FETCH_ASSOC);

    // ✅ BUSCAR ID DO USUÁRIO LOGADO - MÚLTIPLAS FONTES
    $id_usuario = null;
    
    // Tentar buscar o ID do usuário de várias formas
    if (isset($_SESSION['id_usuario'])) {
        $id_usuario = $_SESSION['id_usuario'];
    } elseif (isset($_SESSION['id'])) {
        $id_usuario = $_SESSION['id'];
    } elseif (isset($_SESSION['user_id'])) {
        $id_usuario = $_SESSION['user_id'];
    } elseif (isset($_SESSION['usuario_id'])) {
        $id_usuario = $_SESSION['usuario_id'];
    }
    
    // Se não encontrou, tentar buscar pelo email
    if (!$id_usuario && isset($_SESSION['email'])) {
        try {
            $stmt_user = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id_admin = ?");
            $stmt_user->execute([$_SESSION['email'], $id_admin]);
            $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
            if ($user_data) {
                $id_usuario = $user_data['id'];
            }
        } catch (Exception $e) {
            // Ignorar erro
        }
    }
    
    // Se ainda não encontrou, tentar buscar pelo nome
    if (!$id_usuario && isset($_SESSION['nome'])) {
        try {
            $stmt_user = $pdo->prepare("SELECT id FROM usuarios WHERE nome = ? AND id_admin = ?");
            $stmt_user->execute([$_SESSION['nome'], $id_admin]);
            $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
            if ($user_data) {
                $id_usuario = $user_data['id'];
            }
        } catch (Exception $e) {
            // Ignorar erro
        }
    }
    
    $caixa_usuario_aberto = null;
    $ultimas_vendas = [];
    
    if ($id_usuario) {
        // Buscar caixa aberto do usuário
        $stmt_caixa_usuario = $pdo->prepare("
            SELECT c.id, c.descricao, c.valor_inicial, c.data_abertura, u.nome as usuario_nome
            FROM caixas c 
            INNER JOIN usuarios u ON c.id_usuario = u.id
            WHERE c.id_admin = ? AND c.id_usuario = ? AND c.status = 'ABERTO' 
            LIMIT 1
        ");
        $stmt_caixa_usuario->execute([$id_admin, $id_usuario]);
        $caixa_usuario_aberto = $stmt_caixa_usuario->fetch(PDO::FETCH_ASSOC);
        
        if ($caixa_usuario_aberto) {
            $id_caixa_aberto = $caixa_usuario_aberto['id'];
            
            // Buscar vendas deste caixa
            $sql_vendas = "SELECT v.id, v.valor_total, v.status_pagamento, v.tipo_movimento, v.usuario_vendedor, c.nome as nome_cliente 
                          FROM vendas v 
                          LEFT JOIN clientes c ON v.id_cliente = c.id 
                          INNER JOIN lancamentos l ON v.id = l.id_venda
                          WHERE v.id_admin = ? 
                          AND l.id_caixa_referencia = ?
                          AND v.status_pagamento = 'PAGO'
                          ORDER BY v.data_cadastro DESC 
                          LIMIT 5";
            
            $stmt_vendas = $pdo->prepare($sql_vendas);
            $stmt_vendas->execute([$id_admin, $id_caixa_aberto]);
            $ultimas_vendas = $stmt_vendas->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // ✅ FALLBACK: Se não encontrou caixa do usuário, buscar QUALQUER caixa aberto do admin
    if (!$caixa_usuario_aberto) {
        $stmt_caixa_qualquer = $pdo->prepare("
            SELECT c.id, c.descricao, c.valor_inicial, c.data_abertura, u.nome as usuario_nome
            FROM caixas c 
            INNER JOIN usuarios u ON c.id_usuario = u.id
            WHERE c.id_admin = ? AND c.status = 'ABERTO' 
            ORDER BY c.data_abertura DESC
            LIMIT 1
        ");
        $stmt_caixa_qualquer->execute([$id_admin]);
        $caixa_usuario_aberto = $stmt_caixa_qualquer->fetch(PDO::FETCH_ASSOC);
        
        if ($caixa_usuario_aberto) {
            $id_caixa_aberto = $caixa_usuario_aberto['id'];
            
            // Buscar vendas deste caixa
            $sql_vendas = "SELECT v.id, v.valor_total, v.status_pagamento, v.tipo_movimento, v.usuario_vendedor, c.nome as nome_cliente 
                          FROM vendas v 
                          LEFT JOIN clientes c ON v.id_cliente = c.id 
                          INNER JOIN lancamentos l ON v.id = l.id_venda
                          WHERE v.id_admin = ? 
                          AND l.id_caixa_referencia = ?
                          AND v.status_pagamento = 'PAGO'
                          ORDER BY v.data_cadastro DESC 
                          LIMIT 5";
            
            $stmt_vendas = $pdo->prepare($sql_vendas);
            $stmt_vendas->execute([$id_admin, $id_caixa_aberto]);
            $ultimas_vendas = $stmt_vendas->fetchAll(PDO::FETCH_ASSOC);
        }
    }

} catch (PDOException $e) {
    die("Erro ao carregar dados iniciais: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/menu.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/formularios.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        /* Layout em Grid PDV */
        .pdv-layout {
            display: grid;
            grid-template-columns: 1fr 280px;
            gap: 20px;
            max-width: 100%;
            overflow-x: hidden;
        }
        
        @media (max-width: 1400px) {
            .pdv-layout {
                grid-template-columns: 1fr 260px;
                gap: 15px;
            }
        }
        
        @media (max-width: 1200px) {
            .pdv-layout {
                grid-template-columns: 1fr;
            }
        }
        
        .venda-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            max-width: 100%;
        }
        
        .venda-header {
            background: #1e40af;
            color: #fff;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .venda-header h2 {
            font-size: 18px;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
            margin: 0;
        }
        
        .venda-body {
            padding: 20px;
        }
        
        @media (max-width: 768px) {
            .venda-body {
                padding: 15px;
            }
        }
        
        .toggle-tipo {
            display: inline-flex;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 4px;
            border: 2px solid #e0e0e0;
            gap: 4px;
        }
        
        .toggle-btn {
            padding: 8px 18px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            font-family: 'Exo', sans-serif;
            cursor: pointer;
            color: #6c757d;
            background: transparent;
            transition: all 0.3s ease;
            text-transform: uppercase;
        }
        
        @media (max-width: 768px) {
            .toggle-btn {
                padding: 8px 14px;
                font-size: 12px;
            }
        }
        
        .toggle-btn.active {
            background: #28a745;
            color: #fff;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
        }
        
        .toggle-btn.active-orcamento {
            background: #1e40af;
            color: #fff;
            box-shadow: 0 2px 8px rgba(30, 64, 175, 0.3);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-grid .full {
            grid-column: 1 / -1;
        }
        
        .form-grid .half {
            grid-column: span 1;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .form-grid .half {
                grid-column: 1;
            }
        }
        
        .add-product-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            margin: 15px 0;
            border: 2px solid #e0e0e0;
        }
        
        .add-product-row {
            display: grid;
            grid-template-columns: 1fr 130px 120px 50px;
            gap: 10px;
            align-items: end;
        }
        
        @media (max-width: 1024px) {
            .add-product-row {
                grid-template-columns: 1fr 120px 110px 50px;
                gap: 8px;
            }
        }
        
        @media (max-width: 768px) {
            .add-product-row {
                grid-template-columns: 1fr;
                gap: 12px;
            }
        }
        
        .qty-controls {
            display: flex;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            height: 45px;
        }
        
        .btn-qty {
            width: 40px;
            height: 45px;
            background: #1e40af;
            border: none;
            color: #fff;
            font-weight: 700;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-qty:hover {
            background: #1e3a8a;
        }
        
        .input-qty {
            flex: 1;
            text-align: center;
            border: 2px solid #1e40af;
            border-left: none;
            border-right: none;
            border-radius: 0;
            height: 45px;
            font-weight: 700;
            font-size: 16px;
            min-width: 50px;
        }
        
        .btn-add-product {
            width: 50px;
            height: 45px;
            background: #28a745;
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        
        @media (max-width: 768px) {
            .btn-add-product {
                width: 100%;
            }
        }
        
        .btn-add-product:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(40, 167, 69, 0.4);
            background: #218838;
        }
        
        .btn-icon {
            width: 45px;
            height: 45px;
            background: #1e40af;
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(30, 64, 175, 0.3);
        }
        
        .btn-icon:hover {
            background: #1e3a8a;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.4);
        }
        
        .cart-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 15px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        @media (max-width: 768px) {
            .cart-table {
                font-size: 14px;
            }
        }
        
        .cart-table thead th {
            background: #f8f9fa;
            padding: 12px 10px;
            text-align: left;
            font-size: 12px;
            color: #495057;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-family: 'Exo', sans-serif;
        }
        
        .cart-table tbody td {
            padding: 12px 10px;
            font-size: 14px;
            border-bottom: 1px solid #f0f0f0;
            color: #2c3e50;
        }
        
        .cart-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .cart-table tfoot td {
            font-weight: 700;
            font-size: 20px;
            color: #28a745;
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            padding: 16px 10px;
            font-family: 'Exo', sans-serif;
        }
        
        .btn-remove-item {
            color: #dc3545;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .btn-remove-item:hover {
            color: #c82333;
            transform: scale(1.2);
        }
        
        .venda-footer {
            background: #f8f9fa;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 2px solid #e0e0e0;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .venda-footer {
                flex-direction: column;
                padding: 12px 15px;
            }
        }
        
        .footer-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .footer-actions {
                width: 100%;
                flex-direction: column;
            }
        }
        
        .sidebar-widgets {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .widget {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }
        
        .widget-header {
            background: #555259;
            color: #fff;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .widget-body {
            padding: 15px;
        }
        
        .btn-widget {
            display: block;
            width: 100%;
            padding: 10px;
            background: #1e40af;
            color: #fff;
            text-align: center;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            margin-bottom: 10px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(30, 64, 175, 0.3);
            font-family: 'Exo', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 13px;
        }
        
        .btn-widget:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.4);
        }
        
        .sale-item {
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .sale-item:hover {
            background: #f8f9fa;
            padding-left: 8px;
            padding-right: 8px;
            border-radius: 8px;
        }
        
        .sale-item:last-child {
            border-bottom: none;
        }
        
        .sale-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        
        .sale-id {
            font-weight: 700;
            color: #1e40af;
            font-size: 13px;
            font-family: 'Exo', sans-serif;
        }
        
        .sale-vendor {
            font-size: 10px;
            color: #6c757d;
        }
        
        .sale-price {
            font-weight: 700;
            color: #28a745;
            font-size: 14px;
            font-family: 'Exo', sans-serif;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            color: #fff;
            font-family: 'Exo', sans-serif;
        }
        
        .badge-pago {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .badge-pendente {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        }
        
        .badge-orcamento {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
        }
        
        .empty-state {
            text-align: center;
            padding: 25px 15px;
            color: #adb5bd;
        }
        
        .empty-state i {
            font-size: 36px;
            margin-bottom: 10px;
            opacity: 0.3;
        }
        
        .select2-container .select2-selection--single {
            height: 45px !important;
            border: 2px solid #e0e0e0 !important;
            border-radius: 10px !important;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 41px !important;
            padding-left: 12px !important;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 43px !important;
        }
        
        .swal-wide {
            width: 600px !important;
            max-width: 90% !important;
        }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include 'menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include 'menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <div class="form-header" style="grid-column: 1 / -1;">
            <h1 class="form-title">
                <i class="fas fa-cash-register"></i>
                Ponto de Venda (PDV)
            </h1>
            
            <?php if ($caixa_usuario_aberto): ?>
                <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 10px 20px; border-radius: 10px; display: inline-flex; align-items: center; gap: 10px; margin-left: 20px; box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);">
                    <i class="fas fa-check-circle" style="font-size: 20px;"></i>
                    <div style="text-align: left;">
                        <div style="font-size: 11px; opacity: 0.9; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Caixa Aberto</div>
                        <div style="font-size: 14px; font-weight: 700;">#<?= $caixa_usuario_aberto['id'] ?> - <?= $caixa_usuario_aberto['usuario_nome'] ?></div>
                    </div>
                </div>
            <?php else: ?>
                <div style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); color: white; padding: 10px 20px; border-radius: 10px; display: inline-flex; align-items: center; gap: 10px; margin-left: 20px; box-shadow: 0 2px 8px rgba(243, 156, 18, 0.3);">
                    <i class="fas fa-exclamation-triangle" style="font-size: 20px;"></i>
                    <div style="text-align: left;">
                        <div style="font-size: 11px; opacity: 0.9; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Atenção</div>
                        <div style="font-size: 14px; font-weight: 700;">Nenhum caixa aberto</div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['debug'])): ?>
                <div style="background: #fff; padding: 10px; border-radius: 5px; margin-top: 10px; font-size: 12px; grid-column: 1 / -1;">
                    <strong>🔍 DEBUG DE SESSÃO:</strong><br>
                    ID Admin: <?= $id_admin ?><br>
                    ID Usuário Detectado: <?= $id_usuario ?? 'NÃO ENCONTRADO' ?><br>
                    Nome: <?= $_SESSION['nome'] ?? 'N/A' ?><br>
                    Email: <?= $_SESSION['email'] ?? 'N/A' ?><br>
                    <strong>Variáveis de Sessão Disponíveis:</strong> <?= implode(', ', array_keys($_SESSION)) ?><br>
                    <strong>Caixa Encontrado:</strong> <?= $caixa_usuario_aberto ? 'SIM (#'.$caixa_usuario_aberto['id'].')' : 'NÃO' ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="pdv-layout">
            <div class="venda-card">
                <div class="venda-header">
                    <i class="fas fa-shopping-cart"></i>
                    <h2>Nova Venda</h2>
                </div>
                
                <div class="venda-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-calendar"></i> Data</label>
                            <input type="date" id="data_venda" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Tipo</label>
                            <div class="toggle-tipo">
                                <button type="button" class="toggle-btn active" id="btnTipoVenda" onclick="setTipo('Venda')">Venda</button>
                                <button type="button" class="toggle-btn" id="btnTipoOrcamento" onclick="setTipo('Orçamento')">Orçamento</button>
                            </div>
                            <input type="hidden" id="tipo_movimento" value="Venda">
                        </div>
                        
                        <div class="form-group half" id="col_tipo_venda">
                            <label><i class="fas fa-store"></i> Tipo de Venda</label>
                            <select id="tipo_venda_select" class="form-control">
                                <option value="Presencial, para consumidor final">Presencial, consumidor final</option>
                                <option value="Presencial, para revenda">Presencial, revenda</option>
                                <option value="Delivery ou atendimento domiciliar">Delivery/Domiciliar</option>
                                <option value="Delivery para revenda">Delivery revenda</option>
                                <option value="Pedido via internet">Pedido via internet</option>
                            </select>
                        </div>

                        <div class="form-group half" id="col_validade" style="display:none;">
                            <label><i class="fas fa-calendar-check"></i> Válido até</label>
                            <input type="date" id="data_validade" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                        </div>
                    </div>

                    <div class="form-grid" style="margin-top: 15px;">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Cliente</label>
                            <select id="sel_cliente" class="select2-style form-control">
                                <option value="">Consumidor Final (Sem cadastro)</option>
                                <?php foreach($clientes as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= strtoupper($c['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-paw"></i> Animal (Paciente)</label>
                            <select id="sel_animal" class="select2-style form-control">
                                <option value="">Pesquise ou selecione...</option>
                                <?php foreach($animais as $a): ?>
                                    <option value="<?= $a['id'] ?>" data-cliente="<?= $a['id_cliente'] ?>">
                                        <?= strtoupper($a['nome_paciente']) ?> <?= !empty($a['nome_dono']) ? '('.$a['nome_dono'].')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div style="display: flex; gap: 8px; padding-top: 28px;">
                            <button class="btn-icon" title="Atualizar" onclick="location.reload()">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button class="btn-icon" title="Limpar" onclick="limparCliente()">
                                <i class="fas fa-undo"></i>
                            </button>
                        </div>
                    </div>

                    <div class="add-product-section">
                        <div class="add-product-row">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label><i class="fas fa-box"></i> Produtos e Serviços</label>
                                <select id="sel_produto" class="select2-style form-control">
                                    <option value="">Pesquisar item...</option>
                                    <?php foreach($produtos as $p): ?>
                                        <option value="<?= $p['id'] ?>" data-preco="<?= $p['preco_venda'] ?>"><?= strtoupper($p['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label><i class="fas fa-sort-numeric-up"></i> Quantidade</label>
                                <div class="qty-controls">
                                    <button class="btn-qty" onclick="alterarQtd(-1)">−</button>
                                    <input type="text" id="qtd_item" class="input-qty" value="1">
                                    <button class="btn-qty" onclick="alterarQtd(1)">+</button>
                                </div>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label><i class="fas fa-dollar-sign"></i> Preço Unit.</label>
                                <input type="text" id="preco_item" class="form-control" readonly style="background:#f5f5f5;">
                            </div>
                            
                            <div style="padding-top: 28px;">
                                <button class="btn-add-product" onclick="adicionarItem()" title="Adicionar Item">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th width="90" style="text-align: center;">Qtd</th>
                                <th width="120" style="text-align: right;">Unit.</th>
                                <th width="120" style="text-align: right;">Total</th>
                                <th width="50"></th>
                            </tr>
                        </thead>
                        <tbody id="lista_itens_body">
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <i class="fas fa-shopping-basket"></i><br>
                                    Nenhum item adicionado
                                </td>
                            </tr>
                        </tbody>
                        <tfoot id="cart_footer" style="display:none;">
                            <tr>
                                <td colspan="3" style="text-align:right;">TOTAL:</td>
                                <td colspan="2" style="text-align:right;" id="txt_total_geral">R$ 0,00</td>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="form-grid" style="margin-top: 15px;">
                        <div class="form-group full">
                            <label><i class="fas fa-comment-alt"></i> Observações</label>
                            <textarea id="obs_venda" class="form-control" rows="3" placeholder="Informações adicionais sobre a venda..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="venda-footer">
                    <div class="footer-actions">
                        <button id="btn_receber" class="btn btn-primary" onclick="salvarVenda('receber', this)">
                            <i class="fas fa-check-circle"></i> Registrar Recebimento
                        </button>
                        <button id="btn_salvar" class="btn btn-outline" onclick="salvarVenda('salvar', this)">
                            <i class="fas fa-save"></i> Salvar
                        </button>
                    </div>
                    <button class="btn btn-outline" style="border-color: #dc3545; color: #dc3545;" onclick="location.reload()">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </div>

            <div class="sidebar-widgets">
                <div class="widget">
                    <div class="widget-header">
                        <span><i class="fas fa-cog"></i> Outros Caixas</span>
                    </div>
                    <div class="widget-body">
                        <button class="btn-widget" onclick="window.location.href='vendas/movimentacao_caixa.php'">
                            <i class="fas fa-cash-register"></i> Movimentação de Caixas
                        </button>
                    </div>
                </div>

                <div class="widget">
                    <div class="widget-header">
                        <span><i class="fas fa-history"></i> Vendas deste Caixa</span>
                        <i class="fas fa-sync-alt refresh" onclick="location.reload()" style="cursor:pointer;"></i>
                    </div>
                    <div class="widget-body">
                        <button class="btn-widget" style="background: #555259;" onclick="window.location.href='listar_vendas.php'">
                            <i class="fas fa-search"></i> Localizar Venda
                        </button>
                        
                        <?php if(empty($ultimas_vendas)): ?>
                            <div class="empty-state">
                                <i class="fas fa-receipt"></i><br>
                                <?php if(!$caixa_usuario_aberto): ?>
                                    Você precisa ter um caixa aberto
                                <?php else: ?>
                                    Nenhuma venda neste caixa ainda
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <?php foreach($ultimas_vendas as $v): ?>
                                <div class="sale-item" onclick="window.location.href='vendas/resumo.php?id=<?= $v['id'] ?>'">
                                    <div class="sale-info">
                                        <span class="sale-id">
                                            #<?= $v['id'] ?> - <?= !empty($v['nome_cliente']) ? explode(' ', $v['nome_cliente'])[0] : 'Consumidor' ?>
                                        </span>
                                        <span class="sale-vendor">
                                            <i class="fas fa-user"></i> <?= $v['usuario_vendedor'] ?? 'Sistema' ?>
                                        </span>
                                        <span class="sale-price">
                                            R$ <?= number_format($v['valor_total'], 2, ',', '.') ?>
                                        </span>
                                    </div>
                                    <?php 
                                        $classe_status = 'badge-pendente';
                                        $texto_status = 'PENDENTE';
                                        
                                        if ($v['tipo_movimento'] == 'Orcamento') {
                                            $classe_status = 'badge-orcamento';
                                            $texto_status = 'ORÇAMENTO';
                                        } elseif ($v['status_pagamento'] == 'PAGO') {
                                            $classe_status = 'badge-pago';
                                            $texto_status = 'PAGO';
                                        }
                                    ?>
                                    <span class="status-badge <?= $classe_status ?>"><?= $texto_status ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'vendas/registrar_recebimento.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let isSystemChange = false; 
        let itensVenda = [];

        $(document).ready(function() {
            $('.select2-style').select2({
                width: '100%'
            });
            
            $('#sel_cliente').on('change', function() {
                if(isSystemChange) return;
                var idCliente = $(this).val();
                
                if (idCliente) {
                    $.ajax({
                        url: 'vendas.php', 
                        type: 'POST', 
                        dataType: 'json',
                        data: { acao: 'buscar_animais', id_cliente: idCliente },
                        success: function(response) {
                            var animalSelect = $('#sel_animal');
                            animalSelect.empty();
                            if (response.status === 'success' && response.dados.length > 0) {
                                animalSelect.append('<option value="">Selecione o animal...</option>');
                                $.each(response.dados, function(i, animal) {
                                    animalSelect.append($('<option>', { 
                                        value: animal.id, 
                                        text: animal.nome_paciente, 
                                        'data-cliente': animal.id_cliente 
                                    }));
                                });
                            } else {
                                animalSelect.append('<option value="">Nenhum animal encontrado</option>');
                            }
                            animalSelect.trigger('change.select2'); 
                        }
                    });
                } else {
                    carregarTodosAnimais();
                }
            });

            $('#sel_animal').on('select2:select', function(e) {
                var data = e.params.data;
                var element = $(data.element);
                var idDono = element.attr('data-cliente');
                var clienteAtual = $('#sel_cliente').val();

                if(idDono && idDono !== clienteAtual) {
                    isSystemChange = true; 
                    $('#sel_cliente').val(idDono).trigger('change');
                    isSystemChange = false;
                }
            });

            $('#sel_produto').on('select2:select', function (e) {
                var element = e.params.data.element;
                var preco = $(element).attr('data-preco');
                if (preco) {
                    $('#preco_item').val(parseFloat(preco).toLocaleString('pt-BR', {minimumFractionDigits: 2}));
                } else {
                    $('#preco_item').val('0,00');
                }
            });
        });

        function carregarTodosAnimais() {
            $.ajax({
                url: 'vendas.php', 
                type: 'POST', 
                dataType: 'json',
                data: { acao: 'buscar_animais' }, 
                success: function(response) {
                    var animalSelect = $('#sel_animal');
                    animalSelect.empty().append('<option value="">Pesquise ou selecione...</option>');
                    if (response.status === 'success') {
                        $.each(response.dados, function(i, animal) {
                            var texto = animal.nome_paciente + (animal.nome_dono ? ' (' + animal.nome_dono + ')' : '');
                            animalSelect.append($('<option>', { 
                                value: animal.id, 
                                text: texto, 
                                'data-cliente': animal.id_cliente 
                            }));
                        });
                    }
                    animalSelect.trigger('change.select2');
                }
            });
        }

        function setTipo(tipo) {
            $('#tipo_movimento').val(tipo);
            if(tipo === 'Venda') {
                $('#btnTipoVenda').addClass('active').removeClass('active-orcamento');
                $('#btnTipoOrcamento').removeClass('active').removeClass('active-orcamento');
                $('#col_tipo_venda').show();
                $('#col_validade').hide();
                $('#btn_receber').show();
            } else {
                $('#btnTipoOrcamento').addClass('active-orcamento').addClass('active');
                $('#btnTipoVenda').removeClass('active');
                $('#col_tipo_venda').hide();
                $('#col_validade').show();
                $('#btn_receber').hide();
            }
        }

        function alterarQtd(val) {
            let qtd = parseInt($('#qtd_item').val()) || 1;
            qtd += val;
            if(qtd < 1) qtd = 1;
            $('#qtd_item').val(qtd);
        }

        function adicionarItem() {
            const idProd = $('#sel_produto').val();
            const nomeProd = $('#sel_produto option:selected').text();
            const qtd = parseFloat($('#qtd_item').val());
            const precoStr = $('#preco_item').val();
            
            if(!idProd) { 
                Swal.fire({ 
                    title: 'Atenção', 
                    text: 'Selecione um produto ou serviço.', 
                    icon: 'warning', 
                    confirmButtonColor: '#1e40af' 
                });
                return; 
            }
            
            const preco = precoStr ? parseFloat(precoStr.replace('.','').replace(',','.')) : 0;
            const total = qtd * preco;

            itensVenda.push({ id: idProd, nome: nomeProd, qtd: qtd, valor: preco, total: total });
            renderizarCarrinho();
            
            $('#sel_produto').val('').trigger('change');
            $('#qtd_item').val(1);
            $('#preco_item').val('');
        }

        function renderizarCarrinho() {
            const tbody = document.getElementById('lista_itens_body');
            const tfoot = document.getElementById('cart_footer');
            
            if(itensVenda.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="empty-state"><i class="fas fa-shopping-basket"></i><br>Nenhum item adicionado</td></tr>';
                tfoot.style.display = 'none';
                return;
            }

            let html = '';
            let totalGeral = 0;

            itensVenda.forEach((item, index) => {
                totalGeral += item.total;
                html += `<tr>
                        <td>${item.nome}</td>
                        <td style="text-align:center; font-weight: 600;">${item.qtd}</td>
                        <td style="text-align:right;">R$ ${item.valor.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                        <td style="text-align:right; font-weight: 700; color: #1e40af;">R$ ${item.total.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                        <td style="text-align:center;">
                            <i class="fas fa-trash btn-remove-item" onclick="removerItem(${index})"></i>
                        </td>
                    </tr>`;
            });

            tbody.innerHTML = html;
            document.getElementById('txt_total_geral').innerText = 'R$ ' + totalGeral.toLocaleString('pt-BR', {minimumFractionDigits: 2});
            tfoot.style.display = 'table-footer-group';
        }

        function removerItem(index) {
            itensVenda.splice(index, 1);
            renderizarCarrinho();
        }

        function limparCliente() {
            $('#sel_cliente').val('').trigger('change');
            carregarTodosAnimais();
        }

        async function salvarVenda(acaoBtn, btnElement) {
            if(itensVenda.length === 0) { 
                Swal.fire({ 
                    title: 'Carrinho vazio', 
                    text: 'Adicione itens à venda antes de salvar.', 
                    icon: 'warning', 
                    confirmButtonColor: '#1e40af' 
                });
                return; 
            }

            if(acaoBtn === 'receber') {
                abrirModalFinalizar();
                return;
            }

            const originalText = $(btnElement).html();
            $(btnElement).html('<i class="fas fa-spinner fa-spin"></i> Processando...').prop('disabled', true);

            const dados = {
                acao_btn: acaoBtn,
                id_cliente: $('#sel_cliente').val(),
                id_paciente: $('#sel_animal').val(),
                data: $('#data_venda').val(),
                data_validade: $('#data_validade').val(),
                tipo: $('#tipo_movimento').val(),
                tipo_venda: $('#tipo_venda_select').val(),
                obs: $('#obs_venda').val(),
                itens: itensVenda,
                total_geral: itensVenda.reduce((acc, item) => acc + item.total, 0)
            };

            const formData = new FormData();
            formData.append('acao', 'salvar_venda');
            formData.append('dados_venda', JSON.stringify(dados));

            try {
                const res = await fetch('vendas.php', { method: 'POST', body: formData });
                const resposta = await res.json();
                
                if(resposta.status === 'success') {
                    Swal.fire({
                        title: 'Sucesso!',
                        text: resposta.message,
                        icon: 'success',
                        confirmButtonColor: '#1e40af',
                        confirmButtonText: 'OK',
                        allowOutsideClick: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire({
                        title: 'Atenção!',
                        text: resposta.message,
                        icon: 'warning',
                        confirmButtonColor: '#1e40af'
                    });
                    $(btnElement).html(originalText).prop('disabled', false);
                }
            } catch(e) {
                Swal.fire({
                    title: 'Erro!',
                    text: 'Ocorreu um erro de conexão.',
                    icon: 'error',
                    confirmButtonColor: '#1e40af'
                });
                $(btnElement).html(originalText).prop('disabled', false);
            }
        }
    </script>
</body>
</html>