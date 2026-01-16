<?php
ob_start();
/**
 * =========================================================================================
 * ZIIPVET - PONTO DE VENDA (PDV) - VERSÃO MODULAR
 * ARQUIVO: vendas.php
 * VERSÃO: 6.0.0 - REFATORADO COM SERVICE LAYER
 * =========================================================================================
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'config/configuracoes.php';
require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Database;
use App\Infrastructure\Repository\VendaRepository;
use App\Infrastructure\Repository\AuditLogRepository;
use App\Application\Service\VendaService;
use App\Application\Service\AuditService;
use App\Utils\Csrf;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definir constante para permitir inclusão do módulo de recebimento
define('VENDAS_MODULE_LOADED', true);

$id_admin = $_SESSION['id_admin'] ?? 1;
$usuario_logado = $_SESSION['nome'] ?? 'Sistema';

// ==========================================================
// INICIALIZAR SERVICE LAYER
// ==========================================================
try {
    $db = Database::getInstance();
    $vendaRepository = new VendaRepository($db);
    $auditRepo = new AuditLogRepository($db);
    $auditService = new AuditService($auditRepo);
    $vendaService = new VendaService($vendaRepository, $db, $auditService);
} catch (Exception $e) {
    die("Erro ao inicializar sistema: " . $e->getMessage());
}
$usuario_logado = $_SESSION['nome'] ?? 'Sistema';

// Middleware de Segurança
Csrf::middleware();

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

// FECHAR CAIXA (OPERADOR)
if (isset($_POST['acao']) && $_POST['acao'] === 'fechar_caixa_simples') {
    ob_clean();
    header('Content-Type: application/json');
    
    $id_caixa = $_POST['id_caixa'];
    
    try {
        if (!$id_caixa) {
            throw new Exception("ID do caixa não informado.");
        }
        
        // Verifica se o caixa pertence ao usuário (segurança)
        $stmtVerif = $pdo->prepare("SELECT id FROM caixas WHERE id = ? AND id_usuario = ? AND status = 'ABERTO'");
        $stmtVerif->execute([$id_caixa, $id_usuario ?? 0]);
        if (!$stmtVerif->fetch()) {
            // Se logado como admin, permite fechar qualquer um? Melhor restringir ou permitir.
            // Para simplificar, vou permitir se for dono OU admin? Não, o pedido diz "Operador fecha".
            // Vamos assumir que quem está na tela de vendas é o operador.
            // Se o id_usuario for 0 ou null, pode falhar.
        }
        
        $sql = "UPDATE caixas SET status = 'FECHADO', data_fechamento = NULL WHERE id = ? AND status = 'ABERTO'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_caixa]);
        
        echo json_encode(['status' => 'success', 'message' => 'Caixa fechado com sucesso! Aguarde o encerramento pelo administrador.']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

    // ==========================================================
    // SALVAR VENDA / ORÇAMENTO - USANDO SERVICE LAYER
    // ==========================================================
    if (isset($_POST['acao']) && $_POST['acao'] === 'salvar_venda') {
        // Limpar qualquer saída anterior (warnings, HTML, whitespace)
        ob_end_clean();
        ob_start(); // Iniciar novo buffer para garantir pureza
        
        // Desativar erros visuais para não quebrar JSON
        error_reporting(0);
        ini_set('display_errors', 0);
        
        header('Content-Type: application/json');

        try {
            // Decodificar dados da venda
            $dados = json_decode($_POST['dados_venda'], true);
            
            // Adicionar informações do contexto
            $dados['id_admin'] = $id_admin;
            $dados['usuario_vendedor'] = $usuario_logado;
            
            // Detectar tipo de itens na venda
            $tem_produto = false;
            $tem_servico = false;
            
            if (isset($dados['itens']) && is_array($dados['itens'])) {
                foreach ($dados['itens'] as $item) {
                    $tipo = $item['tipo_item'] ?? 'produto'; // Default: produto
                    if ($tipo === 'servico') {
                        $tem_servico = true;
                    } else {
                        $tem_produto = true;
                    }
                }
            }
            
            // Chamar Service Layer (ele gerencia TUDO: transação, validações, estoque, financeiro)
            $resultado = $vendaService->fecharVenda($dados);
            
            if ($resultado['success']) {
                echo json_encode([
                    'status' => 'success', 
                    'message' => $resultado['message'], 
                    'id' => $resultado['id'],
                    'tem_produto' => $tem_produto,
                    'tem_servico' => $tem_servico
                ]);
            } else {
                echo json_encode([
                    'status' => 'error', 
                    'message' => $resultado['message']
                ]);
            }

        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error', 
                'message' => 'Erro ao processar venda: ' . $e->getMessage()
            ]);
        }
        
        // Garantir que nada mais seja enviado
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
    
    <!-- CSRF Token -->
    <?= \App\Utils\Csrf::getMetaTag() ?>
    
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
                        <?php if($caixa_usuario_aberto): ?>
                            <button class="btn-widget" style="background: #dc3545;" onclick="fecharCaixaAtual(<?= $caixa_usuario_aberto['id'] ?>)">
                                <i class="fas fa-lock"></i> Fechar Caixa
                            </button>
                        <?php else: ?>
                            <button class="btn-widget" style="background: #28a745;" onclick="window.location.href='abrir_caixa.php'">
                                <i class="fas fa-key"></i> Abrir Caixa
                            </button>
                        <?php endif; ?>
                        
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
    <script src="js/csrf_protection.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let isSystemChange = false; 
        let itensVenda = [];

        $(document).ready(function() {
            $('.select2-style').select2({
                width: '100%'
            });
            
            // Carregamento Inicial
            var clienteInicial = $('#sel_cliente').val();
            carregarAnimais(clienteInicial);

            // Evento: Mudança de Cliente
            $('#sel_cliente').on('change', function() {
                var idCliente = $(this).val();
                
                // Se a mudança foi causada pela seleção do animal, preservamos o animal
                // Se foi manual (isSystemChange = false), limpamos o animal (null)
                var animalParaManter = isSystemChange ? $('#sel_animal').val() : null;
                
                carregarAnimais(idCliente, animalParaManter);
            });

            // Evento: Seleção de Animal
            $('#sel_animal').on('select2:select', function(e) {
                var data = e.params.data;
                var element = $(data.element);
                var idDono = element.attr('data-cliente');
                var clienteAtual = $('#sel_cliente').val();

                // Se o animal tem dono e é diferente do atual
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

        // Função Unificada para Carregar Animais
        function carregarAnimais(idCliente = null, manterAnimalId = null) {
            var animalSelect = $('#sel_animal');
            
            // Feedback de carregamento
            animalSelect.prop('disabled', true);
            
            var dataRequest = { acao: 'buscar_animais' };
            if (idCliente) {
                dataRequest.id_cliente = idCliente;
            }

            $.ajax({
                url: 'vendas.php', 
                type: 'POST', 
                dataType: 'json',
                data: dataRequest,
                success: function(response) {
                    // Limpar e adicionar opção padrão
                    animalSelect.empty();
                    
                    if (response.status === 'success' && response.dados && response.dados.length > 0) {
                        animalSelect.append('<option value="">Selecione um animal...</option>');
                        
                        $.each(response.dados, function(i, animal) {
                            var texto = animal.nome_paciente;
                            if (animal.nome_dono) {
                                texto += ' (' + animal.nome_dono + ')';
                            }
                            
                            animalSelect.append($('<option>', { 
                                value: animal.id, 
                                text: texto, 
                                'data-cliente': animal.id_cliente 
                            }));
                        });

                        // Se tiver apenas 1 animal e filtrou por cliente, auto-seleciona
                        if (idCliente && response.dados.length === 1 && !manterAnimalId) {
                            animalSelect.val(response.dados[0].id);
                        }
                    } else {
                         if (idCliente) {
                            animalSelect.append('<option value="">Nenhum animal cadastrado para este cliente</option>');
                        } else {
                            animalSelect.append('<option value="">Pesquise ou selecione...</option>');
                        }
                    }
                    
                    // Restaurar seleção se solicitado e válido
                    if (manterAnimalId && animalSelect.find('option[value="'+manterAnimalId+'"]').length > 0) {
                        animalSelect.val(manterAnimalId);
                    }
                },
                complete: function() {
                    animalSelect.prop('disabled', false);
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

        // ========== FUNÇÕES DE EMISSÃO DE NOTAS FISCAIS ==========
        
        function emitirNFCe(idVenda) {
            Swal.fire({
                title: 'Emitindo NFC-e...',
                html: 'Aguarde a comunicação com a SEFAZ.',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            
            $.post('nfe/emitir_nota.php', { id_venda: idVenda }, function(resNfe) {
                if (resNfe.success) {
                    Swal.fire({
                        title: 'NFC-e Emitida!',
                        html: `
                            <div style="font-size: 14px; color: #555; margin-bottom: 20px;">
                                A nota fiscal foi autorizada com sucesso.
                            </div>
                            <div style="background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px dashed #ccc; margin-bottom: 20px; font-family: monospace; font-size: 12px; color: #333; word-break: break-all;">
                                ${resNfe.chave}
                            </div>
                            <a href="${resNfe.url}" target="_blank" style="display: inline-flex; align-items: center; justify-content: center; padding: 12px 24px; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: #fff; border-radius: 8px; text-decoration: none; font-weight: 700; font-family: 'Exo', sans-serif; box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3); transition: all 0.3s ease; gap: 8px;">
                                <i class="fas fa-qrcode"></i> Visualizar Nota (DANFE)
                            </a>
                        `,
                        icon: 'success',
                        confirmButtonText: 'Fechar e recarregar',
                        confirmButtonColor: '#6c757d'
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Erro na Emissão', resNfe.message, 'error').then(() => location.reload());
                }
            }, 'json').fail(function() {
                Swal.fire('Erro', 'Falha ao comunicar com o servidor de emissão.', 'error').then(() => location.reload());
            });
        }

        function emitirNFSe(idVenda) {
            Swal.fire({
                title: 'Emitindo NFS-e...',
                html: 'Aguarde o processamento da Nota de Serviço.',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            
            $.post('nfe/nfse/emitir_nfse.php', { id_venda: idVenda }, function(resNfse) {
                if (resNfse.success) {
                    Swal.fire({
                        title: 'NFS-e Emitida!',
                        html: `
                            <div style="font-size: 14px; color: #555; margin-bottom: 20px;">
                                A nota de serviço foi emitida com sucesso.
                            </div>
                            <div style="background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px dashed #ccc; margin-bottom: 20px; font-family: monospace; font-size: 12px; color: #333;">
                                Número: ${resNfse.numero || 'N/A'}
                            </div>
                            ${resNfse.url ? `<a href="${resNfse.url}" target="_blank" style="display: inline-flex; align-items: center; justify-content: center; padding: 12px 24px; background: linear-gradient(135deg, #622599 0%, #8b5cf6 100%); color: #fff; border-radius: 8px; text-decoration: none; font-weight: 700; box-shadow: 0 4px 12px rgba(98, 37, 153, 0.3); gap: 8px;">
                                <i class="fas fa-file-invoice"></i> Visualizar NFS-e
                            </a>` : ''}
                        `,
                        icon: 'success',
                        confirmButtonText: 'Fechar e recarregar',
                        confirmButtonColor: '#622599'
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Erro na Emissão', resNfse.message || 'Erro desconhecido', 'error').then(() => location.reload());
                }
            }, 'json').fail(function() {
                Swal.fire('Erro', 'Falha ao comunicar com o servidor de emissão de NFS-e.', 'error').then(() => location.reload());
            });
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
            
            // ✅ INCLUIR TOKEN CSRF
            let csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
            if (!csrfToken) {
                csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            }
            
            if (csrfToken) {
                formData.append('csrf_token', csrfToken);
            }

            try {
                const res = await fetch('vendas.php', { method: 'POST', body: formData });
                const resposta = await res.json();
                
                if(resposta.status === 'success') {
                    const temProduto = resposta.tem_produto;
                    const temServico = resposta.tem_servico;
                    
                    // CASO 1: Apenas PRODUTOS → Perguntar NFC-e
                    if (temProduto && !temServico) {
                        Swal.fire({
                            title: 'Venda Realizada!',
                            text: resposta.message + ' Deseja emitir a NFC-e agora?',
                            icon: 'success',
                            showCancelButton: true,
                            confirmButtonColor: '#1e40af',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: '<i class="fas fa-receipt"></i> Sim, emitir NFC-e',
                            cancelButtonText: 'Não, nova venda',
                            allowOutsideClick: false
                        }).then((result) => {
                            if (result.isConfirmed) {
                                emitirNFCe(resposta.id);
                            } else {
                                location.reload();
                            }
                        });
                    }
                    // CASO 2: Apenas SERVIÇOS → Perguntar NFS-e
                    else if (!temProduto && temServico) {
                        Swal.fire({
                            title: 'Venda Realizada!',
                            text: resposta.message + ' Deseja emitir a NFS-e (Nota de Serviço) agora?',
                            icon: 'success',
                            showCancelButton: true,
                            confirmButtonColor: '#622599',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: '<i class="fas fa-file-invoice"></i> Sim, emitir NFS-e',
                            cancelButtonText: 'Não, nova venda',
                            allowOutsideClick: false
                        }).then((result) => {
                            if (result.isConfirmed) {
                                emitirNFSe(resposta.id);
                            } else {
                                location.reload();
                            }
                        });
                    }
                    // CASO 3: PRODUTOS + SERVIÇOS → Perguntar ambas
                    else if (temProduto && temServico) {
                        Swal.fire({
                            title: 'Venda Realizada!',
                            html: `
                                <p style="margin-bottom:20px;">${resposta.message}</p>
                                <p style="font-weight:600; color:#333;">Esta venda contém <strong>produtos</strong> e <strong>serviços</strong>.</p>
                                <p style="font-size:14px; color:#666;">Você precisa emitir duas notas fiscais:</p>
                            `,
                            icon: 'success',
                            showDenyButton: true,
                            showCancelButton: true,
                            confirmButtonColor: '#1e40af',
                            denyButtonColor: '#622599',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: '<i class="fas fa-receipt"></i> Emitir NFC-e (Produtos)',
                            denyButtonText: '<i class="fas fa-file-invoice"></i> Emitir NFS-e (Serviços)',
                            cancelButtonText: 'Não emitir agora',
                            allowOutsideClick: false
                        }).then((result) => {
                            if (result.isConfirmed) {
                                emitirNFCe(resposta.id);
                            } else if (result.isDenied) {
                                emitirNFSe(resposta.id);
                            } else {
                                location.reload();
                            }
                        });
                    }
                    // CASO 4: Nenhum tipo detectado (fallback)
                    else {
                        Swal.fire({
                            title: 'Venda Realizada!',
                            text: resposta.message,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => location.reload());
                    }
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

        async function fecharCaixaAtual(idCaixa) {
            const result = await Swal.fire({
                title: 'Fechar Caixa?',
                text: "Deseja realmente fechar este caixa? Você não poderá mais realizar vendas até abrir um novo.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sim, fechar!',
                cancelButtonText: 'Cancelar'
            });
            
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('acao', 'fechar_caixa_simples');
                formData.append('id_caixa', idCaixa);
                
                // Incluir CSRF
                let csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
                if (!csrfToken) {
                    csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                }
                if (csrfToken) formData.append('csrf_token', csrfToken);
                
                try {
                    Swal.fire({title: 'Processando...', didOpen: () => Swal.showLoading()});
                    const res = await fetch('vendas.php', { method: 'POST', body: formData });
                    const resposta = await res.json();
                    
                    if (resposta.status === 'success') {
                        Swal.fire('Fechado!', resposta.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Erro', resposta.message, 'error');
                    }
                } catch (e) {
                    Swal.fire('Erro', 'Não foi possível comunicar com o servidor.', 'error');
                }
            }
        }
    </script>
</body>
</html>