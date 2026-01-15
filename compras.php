<?php

// ==========================================================
// CONFIGURAÇÕES INICIAIS E DEBUG
// ==========================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php'; 
require_once 'config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;

$parcelas_xml = [];
if (isset($_SESSION['xml_import']['parcelas'])) {
    $parcelas_xml = $_SESSION['xml_import']['parcelas'];
}

// ==========================================================
// FUNÇÕES AUXILIARES
// ==========================================================
function limparParaSQL($valor) {
    if (empty($valor)) return 0.00;
    $valor = str_replace(['R$', ' ', '.'], '', $valor);
    $valor = str_replace(',', '.', $valor);
    return (float)$valor;
}

// ==========================================================
// PROCESSAMENTO AJAX (CADASTROS AUXILIARES)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalizar_compra_btn'])) {
    try {
        $pdo->beginTransaction();
        
        if (empty($_POST['forn'])) throw new Exception("Fornecedor não selecionado!");
        if (empty($_POST['nf'])) throw new Exception("Número da NF não informado!");

        $valor_total_real = 0;
        if (isset($_POST['parc_valor']) && is_array($_POST['parc_valor'])) {
            foreach ($_POST['parc_valor'] as $v_parc) {
                $valor_total_real += limparParaSQL($v_parc);
            }
        } else {
            $valor_total_real = limparParaSQL($_POST['total_final_hidden'] ?? '0');
        }

        if (!empty($_POST['chave_nfe_hidden'])) {
            $stmt_check = $pdo->prepare("SELECT id FROM compras WHERE chave_nfe = ? AND id_admin = ?");
            $stmt_check->execute([$_POST['chave_nfe_hidden'], $id_admin]);
            if ($stmt_check->fetch()) throw new Exception("Esta nota fiscal já foi importada anteriormente!");
        }

        $stmt = $pdo->prepare("INSERT INTO compras (id_admin, id_fornecedor, nf_numero, nf_serie, chave_nfe, data_emissao, valor_total, valor_frete, status_pagamento, data_cadastro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PENDENTE', NOW())");
        $stmt->execute([
            $id_admin, 
            $_POST['forn'], 
            $_POST['nf'], 
            $_POST['serie']??'', 
            $_POST['chave_nfe_hidden']??'', 
            $_POST['emissao_doc']??date('Y-m-d'), 
            $valor_total_real,
            limparParaSQL($_POST['frete']??'0')
        ]);
        $id_compra = $pdo->lastInsertId();

        if (isset($_POST['parc_valor']) && is_array($_POST['parc_valor'])) {
            $num_parcelas = count($_POST['parc_valor']);
            
            $stmt_f = $pdo->prepare("SELECT cnpj FROM fornecedores WHERE id = ?");
            $stmt_f->execute([$_POST['forn']]);
            $f_cnpj = $stmt_f->fetchColumn() ?: '';
            
            $stmt_c = $pdo->prepare("INSERT INTO contas (id_admin, natureza, categoria, id_entidade, entidade_tipo, doc_entidade, descricao, documento, serie, competencia, vencimento, valor_total, valor_parcela, qtd_parcelas, status_baixa, data_cadastro) VALUES (?, 'Despesa', '1', ?, 'fornecedor', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDENTE', NOW())");
            
            foreach ($_POST['parc_valor'] as $i => $valor_formatado) {
                $v_parcela_real = limparParaSQL($valor_formatado);
                $data_venc = $_POST['parc_venc'][$i] ?? date('Y-m-d');
                $indice_parc = $i + 1;
                $descricao_parc = "COMPRA NF {$_POST['nf']} ({$indice_parc}/{$num_parcelas})";
                
                $stmt_c->execute([
                    $id_admin,
                    $_POST['forn'],
                    $f_cnpj,
                    $descricao_parc,
                    $_POST['nf'],
                    $_POST['serie']??'',
                    date('Y-m-d'),
                    $data_venc,
                    $valor_total_real,
                    $v_parcela_real,
                    $num_parcelas
                ]);
            }
        }

        if (isset($_POST['p_id']) && is_array($_POST['p_id'])) {
            $stmt_item = $pdo->prepare("INSERT INTO compras_itens (id_compra, codigo_produto_fornecedor, descricao_produto, ncm, quantidade, valor_unitario, valor_total_item) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_stock = $pdo->prepare("UPDATE produtos SET estoque_inicial = estoque_inicial + ?, preco_custo = ? WHERE id = ? AND id_admin = ?");
            
            foreach ($_POST['p_id'] as $idx => $prod_id) {
                $qnt = (float)($_POST['p_q'][$idx]??0);
                $v_un = limparParaSQL($_POST['p_v'][$idx]??'0');
                
                $stmt_item->execute([
                    $id_compra,
                    $_POST['p_gtin_xml'][$idx]??'',
                    $_POST['p_nome_xml'][$idx]??'',
                    $_POST['p_ncm_xml'][$idx]??'',
                    $qnt,
                    $v_un,
                    ($qnt * $v_un)
                ]);
                
                if (!empty($prod_id) && $prod_id > 0) {
                    $stmt_stock->execute([$qnt, $v_un, $prod_id, $id_admin]);
                }
            }
        }

        $pdo->commit();
        echo "<script>alert('✅ Compra finalizada com sucesso! Valor Total: R$ " . number_format($valor_total_real, 2, ',', '.') . "'); window.location.href='listar_compras.php';</script>";
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('❌ Erro: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        exit;
    }
}

// CARREGAMENTO DE LISTAS
try {
    $fornecedores = $pdo->query("SELECT id, nome_fantasia FROM fornecedores WHERE id_admin = $id_admin ORDER BY nome_fantasia")->fetchAll(PDO::FETCH_ASSOC);
    $produtos_db = $pdo->query("SELECT id, nome, gtin FROM produtos WHERE id_admin = $id_admin ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
    $categorias = $pdo->query("SELECT id, nome_categoria FROM categorias_produtos ORDER BY nome_categoria")->fetchAll(PDO::FETCH_ASSOC);
    $unidades = $pdo->query("SELECT id, nome_unidade FROM unidades_medida ORDER BY nome_unidade")->fetchAll(PDO::FETCH_ASSOC);
    $contas_fin = $pdo->query("SELECT id, nome_conta FROM contas_financeiras WHERE id_admin = $id_admin ORDER BY nome_conta")->fetchAll(PDO::FETCH_ASSOC);
    $comissoes = $pdo->query("SELECT id, nome_grupo FROM comissoes_grupos ORDER BY nome_grupo")->fetchAll(PDO::FETCH_ASSOC);
    $marcas = $pdo->query("SELECT id, nome_marca FROM marcas ORDER BY nome_marca")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Compra | ZiipVet</title>
    
    <!-- CSS CENTRALIZADO -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/menu.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/formularios.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <style>
        :root { 
            --fundo: #f5f7fa;
            --texto-dark: #2d3748;
            --azul: #17a2b8;
            --verde: #28a745;
            --vermelho: #dc3545;
            --verde: #28A745;
            --laranja: #f59e0b;
            --cinza: #6c757d;
            --gradient-azul: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            --gradient-verde: linear-gradient(135deg, #28A745 0%, #28A745 100%);
            --gradient-vermelho: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            --gradient-roxo: linear-gradient(135deg, #b92426 0%, #b92426 100%);
            --gradient-laranja: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; }
        
        body { 
            font-family: 'Exo', 'Inter', -apple-system, sans-serif;
            background: var(--fundo);
            color: var(--texto-dark);
            min-height: 100vh;
            overflow-x: hidden;
            font-size: 15px;
            line-height: 1.6;
        }

        .card-secao { 
            background: white;
            padding: 35px;
            border-radius: 16px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        .card-secao:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .secao-titulo { 
            font-size: 20px;
            font-weight: 700;
            color: var(--texto-dark);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid;
            border-image: var(--gradient-roxo) 1;
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: 'Exo', sans-serif;
        }
        .secao-titulo i {
            width: 36px;
            height: 36px;
            background: var(--gradient-roxo);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }

        .label-float { 
            position: relative;
            width: 100%;
            margin-bottom: 24px;
        }
        .label-float label { 
            position: absolute;
            top: -11px;
            left: 14px;
            background: white;
            padding: 0 8px;
            font-size: 13px;
            font-weight: 600;
            color: #6b7280;
            z-index: 5;
            letter-spacing: 0.3px;
            font-family: 'Exo', sans-serif;
        }
        .label-float input, 
        .label-float select, 
        .label-float textarea { 
            width: 100%;
            height: 52px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 0 18px;
            font-size: 15px;
            color: var(--texto-dark);
            background: white;
            transition: all 0.3s ease;
            font-family: 'Exo', sans-serif;
        }
        .label-float textarea {
            height: 120px;
            padding-top: 16px;
            resize: none;
        }
        .label-float input:focus,
        .label-float select:focus,
        .label-float textarea:focus { 
            border-color: var(--roxo);
            box-shadow: 0 0 0 4px rgba(98, 37, 153, 0.1);
        }

        .select2-container--default .select2-selection--single {
            height: 52px !important;
            border: 2px solid #e5e7eb !important;
            border-radius: 12px !important;
            padding: 0 18px !important;
            display: flex !important;
            align-items: center !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 48px !important;
            padding-left: 0 !important;
            color: var(--texto-dark) !important;
            font-family: 'Exo', sans-serif !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 48px !important;
            right: 10px !important;
        }
        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: var(--roxo) !important;
            box-shadow: 0 0 0 4px rgba(98, 37, 153, 0.1) !important;
        }
        .select2-dropdown {
            border: 2px solid var(--roxo) !important;
            border-radius: 12px !important;
            box-shadow: var(--shadow-lg) !important;
        }
        .select2-results__option {
            font-family: 'Exo', sans-serif !important;
        }
        .select2-results__option--highlighted {
            background: var(--roxo) !important;
        }

        .item-xml-box { 
            background: linear-gradient(135deg, #f8fafc 0%, #fff 100%);
            padding: 25px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .item-xml-box:hover {
            border-color: var(--roxo);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        .item-xml-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--gradient-roxo);
        }

        .prod-title-xml {
            font-size: 17px;
            font-weight: 700;
            color: var(--texto-dark);
            display: block;
            margin-bottom: 12px;
            padding-left: 15px;
            font-family: 'Exo', sans-serif;
        }
        .prod-sub-title {
            font-size: 13px;
            font-weight: 600;
            color: #6b7280;
            display: block;
            margin-bottom: 6px;
            padding-left: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-family: 'Exo', sans-serif;
        }
        
        .select-row-orange { 
            border: 2px solid var(--laranja);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            align-items: center;
            margin-bottom: 18px;
            height: 52px;
            background: white;
            transition: all 0.3s ease;
        }
        .select-row-orange:hover {
            border-color: #d97706;
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.1);
        }
        .select-row-orange select {
            border: none;
            height: 100%;
            width: 100%;
            padding: 0 18px;
            font-size: 15px;
            font-weight: 500;
            font-family: 'Exo', sans-serif;
        }
        .select-row-orange .btn-plus-orange { 
            background: var(--gradient-laranja);
            border: none;
            width: 52px;
            height: 100%;
            color: white;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }
        .select-row-orange .btn-plus-orange:hover {
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
            transform: scale(1.05);
        }
        .select-row-orange .btn-stock { 
            border-left: 2px solid var(--laranja);
            width: 160px;
            height: 100%;
            font-size: 13px;
            color: #78350f;
            background: #fef3c7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            flex-shrink: 0;
            font-family: 'Exo', sans-serif;
        }

        .grid-values-xml {
            display: grid;
            grid-template-columns: 1fr 1fr 1.2fr 1.2fr;
            gap: 18px;
            align-items: flex-end;
            padding-left: 15px;
        }

        .btn-mini-plus { 
            background: var(--gradient-verde);
            border: none;
            color: white;
            border-radius: 12px;
            width: 52px;
            height: 52px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        .btn-mini-plus:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-finalizar { 
            background: var(--gradient-verde);
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            text-transform: uppercase;
            width: 100%;
            font-size: 16px;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-md);
            font-family: 'Exo', sans-serif;
        }
        .btn-finalizar:hover {
            background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
            transform: translateY(-3px);
            box-shadow: var(--shadow-xl);
        }
        
        .btn-cancelar { 
            background: white;
            color: #6b7280;
            border: 2px solid #e5e7eb;
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            display: block;
            width: 100%;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            font-family: 'Exo', sans-serif;
        }
        .btn-cancelar:hover {
            background: #f9fafb;
            border-color: #d1d5db;
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .btn-add-manual {
            background: white;
            border: 3px dashed #cbd5e1;
            color: #64748b;
            padding: 20px;
            width: 100%;
            border-radius: 16px;
            cursor: pointer;
            margin-bottom: 25px;
            font-weight: 700;
            font-size: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-family: 'Exo', sans-serif;
        }
        .btn-add-manual:hover {
            background: #f8fafc;
            border-color: var(--roxo);
            color: var(--roxo);
            transform: translateY(-2px);
        }
        .btn-add-manual i {
            font-size: 20px;
        }

        .drawer-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            z-index: 3000;
            backdrop-filter: blur(4px);
        }
        
        .side-drawer { 
            position: fixed;
            top: 0;
            right: -800px;
            width: 700px;
            height: 100%;
            background: white;
            z-index: 3001;
            transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: -10px 0 40px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
        }
        .side-drawer.active { right: 0; }
        
        .dr-header-box {
            padding: 28px 32px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #f8fafc 0%, #fff 100%);
        }
        .dr-header-box h3 {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
            color: var(--texto-dark);
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: 'Exo', sans-serif;
        }
        .dr-header-box h3 i {
            width: 42px;
            height: 42px;
            background: var(--gradient-azul);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        
        .dr-content-scroll {
            padding: 32px;
            overflow-y: auto;
            flex: 1;
            background: #fafafa;
        }
        
        .dr-footer {
            padding: 24px 32px;
            background: white;
            border-top: 2px solid #f1f5f9;
            display: flex;
            gap: 16px;
            justify-content: flex-end;
            box-shadow: 0 -4px 6px rgba(0,0,0,0.05);
        }
        .dr-footer .btn-cancelar,
        .dr-footer .btn-finalizar {
            width: auto;
            min-width: 140px;
        }
        
        .dr-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        .dr-full { grid-column: span 2; }
        .dr-flex-row {
            display: flex;
            gap: 12px;
            align-items: center;
            grid-column: span 2;
        }
        .dr-flex-row .label-float {
            margin-bottom: 0;
            flex: 1;
        }

        .btn-status-toggle {
            display: flex;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            background: white;
        }
        .btn-status-toggle button {
            border: none;
            padding: 10px 20px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            flex: 1;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-family: 'Exo', sans-serif;
        }
        .st-off {
            background: white;
            color: #9ca3af;
        }
        .st-on {
            background: var(--gradient-azul);
            color: white;
        }

        .check-group {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin: 18px 0;
            padding: 20px;
            background: white;
            border-radius: 12px;
            border: 2px solid #f1f5f9;
        }
        .check-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 15px;
            color: var(--texto-dark);
            cursor: pointer;
            padding: 12px;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-family: 'Exo', sans-serif;
        }
        .check-item:hover {
            background: #f8fafc;
        }
        .check-item input[type="checkbox"] {
            width: 22px;
            height: 22px;
            accent-color: var(--roxo);
            cursor: pointer;
        }

        .fiscal-header {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            border: 2px solid #cbd5e1;
            padding: 18px 24px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            font-weight: 700;
            color: var(--texto-dark);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-family: 'Exo', sans-serif;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 58px;
            height: 30px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #cbd5e1;
            transition: .3s;
            border-radius: 30px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 24px;
            width: 24px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        input:checked + .slider { background: var(--gradient-azul); }
        input:checked + .slider:before { transform: translateX(28px); }
        
        .fiscal-body {
            display: none;
            padding: 24px;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 18px;
        }

        .parcela-item {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid var(--roxo);
        }
        .parcela-row {
            display: grid;
            grid-template-columns: 100px 1fr 1fr 40px;
            gap: 15px;
            align-items: end;
        }
        .parcela-field label {
            font-size: 12px;
            font-weight: 600;
            color: #666;
            margin-bottom: 5px;
            display: block;
            font-family: 'Exo', sans-serif;
        }
        .btn-remove-parcela {
            background: var(--vermelho);
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 35px; 
            height: 35px;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-remove-parcela:hover {
            background: #c82333;
            transform: scale(1.1);
        }

        .grid-totais {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 18px;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 3px solid #e5e7eb;
        }
        .grid-totais .label-float input[readonly] {
            background: linear-gradient(135deg, #f8fafc 0%, #fff 100%);
            font-weight: 700;
        }
        #disp_total {
            color: var(--roxo) !important;
            font-size: 18px !important;
            font-weight: 800 !important;
            border: 3px solid var(--roxo) !important;
            background: linear-gradient(135deg, #eef2ff 0%, #fff 100%) !important;
        }

        .drawer-aux { width: 500px; z-index: 3005; }
        .overlay-aux { z-index: 3004; }

        .campo-erro {
            border-color: var(--vermelho) !important;
            background: #fef2f2 !important;
            animation: shake 0.3s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .notificacao-grande {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 10000;
            background: white;
            padding: 30px 40px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            max-width: 500px;
            min-width: 400px;
        }
        
        .notif-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
            backdrop-filter: blur(4px);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .card-secao {
            animation: fadeIn 0.5s ease-out;
        }

        @media (max-width: 768px) {
            .grid-values-xml {
                grid-template-columns: 1fr;
            }
            .dr-form-grid {
                grid-template-columns: 1fr;
            }
            .grid-totais {
                grid-template-columns: 1fr;
            }
            .side-drawer {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <aside class="sidebar-container"><?php include 'menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include 'menu/faixa.php'; ?></header>
    
    <main class="main-content">
        <form id="formCompraFinal" method="POST">
            <input type="hidden" name="total_final_hidden" id="total_final_hidden">
            <input type="hidden" name="chave_nfe_hidden" id="chave_nfe_hidden">

            <div class="card-secao">
                <div class="secao-titulo">
                    <i class="fas fa-building"></i>
                    Dados Principais da Compra
                </div>
                <div style="display:grid; grid-template-columns: 1fr 60px; gap:18px; margin-bottom:24px;">
                    <div class="label-float">
                        <label><i class="fas fa-truck"></i> Fornecedor *</label>
                        <select name="forn" id="fornecedor_id" required>
                            <option value="">Selecione um fornecedor...</option>
                            <?php foreach($fornecedores as $f) echo "<option value='{$f['id']}'>{$f['nome_fantasia']}</option>"; ?>
                        </select>
                    </div>
                    <button type="button" class="btn-mini-plus" onclick="toggleDrawer('forn')" title="Adicionar novo fornecedor">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:18px;">
                    <div class="label-float">
                        <label><i class="far fa-calendar"></i> Entrada</label>
                        <input type="datetime-local" name="emissao_entrada" value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                    <div class="label-float">
                        <label><i class="fas fa-file-invoice"></i> NF *</label>
                        <input type="text" name="nf" id="compra_nf" required placeholder="Número da NF">
                    </div>
                    <div class="label-float">
                        <label><i class="fas fa-hashtag"></i> Série</label>
                        <input type="text" name="serie" id="compra_serie" placeholder="Série">
                    </div>
                    <div class="label-float">
                        <label><i class="far fa-calendar-check"></i> Emissão Nota</label>
                        <input type="date" name="emissao_doc" id="compra_emissao">
                    </div>
                </div>
            </div>

            <div class="card-secao">
                <div class="secao-titulo">
                    <i class="fas fa-boxes"></i>
                    Itens da Compra
                </div>
                <div id="itens-container"></div>
                
                <button type="button" onclick="addLinhaManual()" class="btn-add-manual">
                    <i class="fas fa-plus-circle"></i>
                    Adicionar Produto Manualmente
                </button>
                
                <div class="grid-totais">
                    <div class="label-float">
                        <label><i class="fas fa-plus"></i> Soma Itens</label>
                        <input type="text" id="disp_sub" readonly value="R$ 0,00">
                    </div>
                    <div class="label-float">
                        <label><i class="fas fa-minus"></i> Desc. Itens</label>
                        <input type="text" id="disp_desc_prod" readonly value="R$ 0,00">
                    </div>
                    <div class="label-float">
                        <label><i class="fas fa-percentage"></i> Desc. Global</label>
                        <input type="text" id="in_desc_global" value="R$ 0,00" onkeyup="calc()">
                    </div>
                    <div class="label-float">
                        <label><i class="fas fa-shipping-fast"></i> Frete (+)</label>
                        <input type="text" name="frete" id="in_frete" value="R$ 0,00" onkeyup="calc()">
                    </div>
                    <div class="label-float">
                        <label><i class="fas fa-dollar-sign"></i> Total Geral</label>
                        <input type="text" id="disp_total" readonly value="R$ 0,00">
                    </div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:25px; align-items: start;">
                
                <div class="card-secao" style="margin-bottom:0;">
                    <div class="secao-titulo">
                        <i class="fas fa-wallet"></i> Financeiro
                    </div>
                    
                    <div class="label-float">
                        <label><i class="fas fa-university"></i> Conta Financeira *</label>
                        <select name="conta" required>
                            <option value="">Selecionar conta...</option>
                            <?php foreach($contas_fin as $c) echo "<option value='{$c['id']}'>".strtoupper($c['nome_conta'])."</option>"; ?>
                        </select>
                    </div>

                    <div class="parcelas-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h4 style="font-weight:700; margin:0; font-size:16px;">Parcelas (<span id="total-parcelas">0</span>)</h4>
                        <div style="display:flex; gap:10px;">
                            <button type="button" class="btn-cancelar" style="padding: 5px 10px; width:auto; font-size:11px; height:32px;" onclick="limparParcelas()">
                                <i class="fas fa-trash"></i> Limpar
                            </button>
                            <button type="button" class="btn-finalizar" style="padding: 5px 10px; width:auto; font-size:11px; height:32px;" onclick="addParcela()">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                    </div>

                    <div id="parcelas-container" style="margin-bottom: 20px; max-height: 300px; overflow-y: auto; padding-right:5px;"></div>
                </div>

                <div style="display:flex; flex-direction:column; gap:18px; padding-top: 20px;">
                    <a href="listar_compras.php" class="btn-cancelar">
                        <i class="fas fa-times-circle"></i> Cancelar Compra
                    </a>
                    <button type="submit" name="finalizar_compra_btn" class="btn-finalizar" style="height: 70px; font-size: 18px;">
                        <i class="fas fa-check-circle"></i> FINALIZAR AGORA
                    </button>
                </div>

            </div>
        </form>
    </main>

    <!-- DRAWER FORNECEDOR -->
    <div id="fornOverlay" class="drawer-overlay" onclick="toggleDrawer('forn')"></div>
    <div id="fornDrawer" class="side-drawer">
        <div class="dr-header-box">
            <h3><i class="fas fa-truck"></i> Adicionar Fornecedor</h3>
            <div class="btn-status-toggle">
                <input type="hidden" name="f_status" id="f_status" value="ATIVO">
                <button type="button" class="st-off" onclick="setSt('f','INATIVO', this)">Inativo</button>
                <button type="button" class="st-on" onclick="setSt('f','ATIVO', this)">Ativo</button>
            </div>
        </div>
        <div class="dr-content-scroll">
            <form id="formForn">
                <div class="dr-form-grid">
                    <div class="label-float"><label>Tipo de fornecedor *</label><select name="f_tipo_forn"><option>Produtos e/ou serviços</option></select></div>
                    <div class="label-float"><label>Tipo de pessoa *</label><select name="f_tipo_pessoa"><option>Jurídica</option><option>Física</option></select></div>
                    <div class="label-float dr-full"><label>CNPJ</label><input type="text" name="f_cnpj" id="f_cnpj"></div>
                    <div class="label-float dr-full"><label>Razão Social</label><input type="text" name="f_razao" id="f_razao"></div>
                    <div class="label-float dr-full"><label>Nome Fantasia</label><input type="text" name="f_fantasia" id="f_fantasia"></div>
                    <div class="label-float"><label>CEP</label><input type="text" name="f_cep" id="f_cep"></div>
                    <div class="label-float"><label>Endereço</label><input type="text" name="f_endereco" id="f_endereco"></div>
                    <div class="label-float"><label>Número</label><input type="text" name="f_numero"></div>
                    <div class="label-float"><label>Complemento</label><input type="text" name="f_complemento"></div>
                    <div class="label-float"><label>Bairro</label><input type="text" name="f_bairro" id="f_bairro"></div>
                    <div class="label-float"><label>Cidade</label><input type="text" name="f_cidade" id="f_cidade"></div>
                    <div class="label-float"><label>Estado</label><select name="f_estado" id="f_estado"><option value="GO">GO</option></select></div>
                    <div class="label-float"><label>Ponto de referência</label><input type="text" name="f_ponto_ref"></div>
                    <div class="label-float dr-full"><label>E-mail</label><input type="email" name="f_email"></div>
                    <div class="label-float dr-full"><label>Site</label><input type="text" name="f_site"></div>
                    <div class="label-float"><label>Telefone 1</label><input type="text" name="f_tel1" id="f_tel1"></div>
                    <div class="label-float"><label>Telefone 2</label><input type="text" name="f_tel2"></div>
                    <div class="label-float"><label>Telefone 3</label><input type="text" name="f_tel3"></div>
                    <div class="label-float dr-full"><label>Observações</label><textarea name="f_obs"></textarea></div>
                </div>
            </form>
        </div>
        <div class="dr-footer">
            <button type="button" class="btn-cancelar" onclick="toggleDrawer('forn')">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button type="button" class="btn-finalizar" onclick="salvarAjax('forn')">
                <i class="fas fa-save"></i> Salvar
            </button>
        </div>
    </div>

    <!-- DRAWER PRODUTO -->
    <div id="prodOverlay" class="drawer-overlay" onclick="toggleDrawer('prod')"></div>
    <div id="prodDrawer" class="side-drawer">
        <div class="dr-header-box">
            <h3><i class="fas fa-box"></i> Adicionar Produto</h3>
            <div class="btn-status-toggle">
                <input type="hidden" name="dr_status" id="dr_status" value="ATIVO">
                <button type="button" class="st-off" onclick="setSt('dr','INATIVO', this)">Inativo</button>
                <button type="button" class="st-on" onclick="setSt('dr','ATIVO', this)">Ativo</button>
            </div>
        </div>
        <div class="dr-content-scroll">
            <form id="formProd">
                <div class="dr-form-grid">
                    <div class="label-float dr-full"><label>Nome *</label><input type="text" name="dr_nome" id="dr_nome" required></div>
                    <div class="label-float"><label>Tipo</label><select name="dr_tipo"><option>Produto</option><option>Serviço</option></select></div>
                    
                    <div class="dr-flex-row" style="grid-column: span 1;">
                        <div class="label-float"><label>Und. de Medida</label><select name="dr_und" id="dr_und"><?php foreach($unidades as $u) echo "<option value='{$u['id']}'>{$u['nome_unidade']}</option>"; ?></select></div>
                        <button type="button" class="btn-mini-plus" onclick="toggleDrawer('unit')"><i class="fas fa-plus"></i></button>
                    </div>
                    
                    <div class="label-float dr-full"><label>Código de barras / GTIN</label><input type="text" name="dr_gtin" id="dr_gtin"></div>
                    <div class="label-float dr-full"><label>Código (SKU)</label><input type="text" name="dr_sku"></div>
                    
                    <div class="dr-flex-row">
                        <div class="label-float"><label>Marca</label><select name="dr_marca" id="dr_marca"><option value="">Selecione...</option><?php foreach($marcas as $m) echo "<option value='{$m['id']}'>{$m['nome_marca']}</option>"; ?></select></div>
                        <button type="button" class="btn-mini-plus" onclick="toggleDrawer('brand')"><i class="fas fa-plus"></i></button>
                    </div>
                    
                    <div class="dr-flex-row">
                        <div class="label-float"><label>Categoria *</label><select name="dr_cat" id="dr_cat" required><option value="">Selecione...</option><?php foreach($categorias as $c) echo "<option value='{$c['id']}'>{$c['nome_categoria']}</option>"; ?></select></div>
                        <button type="button" class="btn-mini-plus" onclick="toggleDrawer('cat')"><i class="fas fa-plus"></i></button>
                    </div>
                    
                    <div class="dr-flex-row">
                        <div class="label-float"><label>Grupo de Comissão *</label><select name="dr_comissao" id="dr_comissao" required><option value="">Selecione...</option><?php foreach($comissoes as $cm) echo "<option value='{$cm['id']}'>{$cm['nome_grupo']}</option>"; ?></select></div>
                        <button type="button" class="btn-mini-plus" onclick="toggleDrawer('comm')"><i class="fas fa-plus"></i></button>
                    </div>

                    <div style="grid-column: span 2; display:grid; grid-template-columns: 1fr 1fr 1fr; gap:18px; padding: 20px; background: linear-gradient(135deg, #eef2ff 0%, #fff 100%); border-radius: 12px; border: 2px solid var(--roxo);">
                        <div class="label-float">
                            <label><i class="fas fa-dollar-sign"></i> Preço de custo</label>
                            <input type="text" name="dr_custo" id="dr_custo" placeholder="R$ 0,00" onkeyup="calcularPrecoVenda()">
                        </div>
                        <div class="label-float">
                            <label><i class="fas fa-percentage"></i> Margem</label>
                            <input type="text" name="dr_margem" id="dr_margem" value="0 %" placeholder="0 %" onkeyup="calcularPrecoVenda()">
                        </div>
                        <div class="label-float">
                            <label><i class="fas fa-tags"></i> Preço de venda</label>
                            <input type="text" name="dr_venda" id="dr_venda" value="R$ 0,00" readonly style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); font-weight: 800; color: var(--verde); font-size: 17px;">
                        </div>
                    </div>
                    
                    <div class="check-group dr-full">
                        <label class="check-item"><input type="checkbox" name="dr_com_desconto"> Preço com desconto?</label>
                        <label class="check-item"><input type="checkbox" name="dr_estoque_ini"> Cadastrar estoque inicial?</label>
                        <label class="check-item"><input type="checkbox" name="dr_monitorar" checked> Monitorar Estoque</label>
                        <label class="check-item"><input type="checkbox" name="dr_bloquear"> Bloquear Comissão</label>
                    </div>
                    
                    <div class="label-float dr-full" style="text-align:right;">
                        <label>Data de Validade</label><input type="date" name="dr_validade" style="width:50%; margin-left:auto;">
                    </div>

                    <div class="dr-full">
                        <div class="fiscal-header">
                            <span><i class="fas fa-file-invoice"></i> Dados Fiscais</span>
                            <label class="switch"><input type="checkbox" id="checkFiscal" onchange="$('#fiscalPanel').slideToggle()"><span class="slider"></span></label>
                        </div>
                        <div id="fiscalPanel" class="fiscal-body">
                            <div class="dr-form-grid" style="grid-template-columns: 1fr;">
                                <div class="label-float"><label>NCM</label><input type="text" name="dr_ncm" id="dr_ncm"></div>
                                <div class="label-float"><label>CFOP</label><input type="text" name="dr_cfop" id="dr_cfop"></div>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:18px;">
                                    <div class="label-float"><label>Origem CSOSN</label><select name="dr_origem"><option value="0">0 - Nacional</option></select></div>
                                    <div class="label-float"><label>CSOSN</label><input type="text" name="dr_csosn"></div>
                                </div>
                                <div class="label-float"><label>CST PIS</label><input type="text" name="dr_pis"></div>
                                <div class="label-float"><label>CST COFINS</label><input type="text" name="dr_cofins"></div>
                                <div class="label-float"><label>CEST</label><input type="text" name="dr_cest" id="dr_cest"></div>
                                <div class="label-float"><label>CST IPI</label><input type="text" name="dr_ipi"></div>
                            </div>
                        </div>
                    </div>
                    <div class="label-float dr-full"><label>Observações</label><textarea name="dr_obs"></textarea></div>
                </div>
            </form>
        </div>
        <div class="dr-footer">
            <button type="button" class="btn-cancelar" onclick="toggleDrawer('prod')">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button type="button" class="btn-finalizar" onclick="salvarAjax('prod')">
                <i class="fas fa-save"></i> Salvar
            </button>
        </div>
    </div>

    <!-- DRAWERS AUXILIARES -->
    <div id="unitOverlay" class="drawer-overlay overlay-aux" onclick="toggleDrawer('unit')"></div>
    <div id="unitDrawer" class="side-drawer drawer-aux">
        <div class="dr-header-box">
            <h3><i class="fas fa-ruler"></i> Nova Unidade</h3>
            <i class="fas fa-times" onclick="toggleDrawer('unit')" style="cursor:pointer; font-size: 24px; color: #9ca3af;"></i>
        </div>
        <div class="dr-content-scroll">
            <form id="formUnit">
                <div class="label-float"><label>Nome da Unidade</label><input type="text" name="nome_unidade" required></div>
            </form>
        </div>
        <div class="dr-footer">
            <button type="button" class="btn-finalizar" onclick="salvarAux('unit', 'acao_nova_unidade', 'dr_und')">
                <i class="fas fa-save"></i> Salvar
            </button>
        </div>
    </div>

    <div id="brandOverlay" class="drawer-overlay overlay-aux" onclick="toggleDrawer('brand')"></div>
    <div id="brandDrawer" class="side-drawer drawer-aux">
        <div class="dr-header-box">
            <h3><i class="fas fa-tag"></i> Nova Marca</h3>
            <i class="fas fa-times" onclick="toggleDrawer('brand')" style="cursor:pointer; font-size: 24px; color: #9ca3af;"></i>
        </div>
        <div class="dr-content-scroll">
            <form id="formBrand">
                <div class="label-float"><label>Nome da Marca</label><input type="text" name="nome_marca" required></div>
            </form>
        </div>
        <div class="dr-footer">
            <button type="button" class="btn-finalizar" onclick="salvarAux('brand', 'acao_nova_marca', 'dr_marca')">
                <i class="fas fa-save"></i> Salvar
            </button>
        </div>
    </div>

    <div id="catOverlay" class="drawer-overlay overlay-aux" onclick="toggleDrawer('cat')"></div>
    <div id="catDrawer" class="side-drawer drawer-aux">
        <div class="dr-header-box">
            <h3><i class="fas fa-folder"></i> Nova Categoria</h3>
            <i class="fas fa-times" onclick="toggleDrawer('cat')" style="cursor:pointer; font-size: 24px; color: #9ca3af;"></i>
        </div>
        <div class="dr-content-scroll">
            <form id="formCat">
                <div class="label-float"><label>Nome da Categoria</label><input type="text" name="nome_categoria" required></div>
            </form>
        </div>
        <div class="dr-footer">
            <button type="button" class="btn-finalizar" onclick="salvarAux('cat', 'acao_nova_categoria', 'dr_cat')">
                <i class="fas fa-save"></i> Salvar
            </button>
        </div>
    </div>

    <div id="commOverlay" class="drawer-overlay overlay-aux" onclick="toggleDrawer('comm')"></div>
    <div id="commDrawer" class="side-drawer drawer-aux">
        <div class="dr-header-box">
            <h3><i class="fas fa-users"></i> Novo Grupo de Comissão</h3>
            <i class="fas fa-times" onclick="toggleDrawer('comm')" style="cursor:pointer; font-size: 24px; color: #9ca3af;"></i>
        </div>
        <div class="dr-content-scroll">
            <form id="formComm">
                <div class="label-float"><label>Nome do Grupo</label><input type="text" name="nome_grupo" required></div>
            </form>
        </div>
        <div class="dr-footer">
            <button type="button" class="btn-finalizar" onclick="salvarAux('comm', 'acao_nova_comissao', 'dr_comissao')">
                <i class="fas fa-save"></i> Salvar
            </button>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    
    <script>
        const parcelasXML = <?= json_encode($parcelas_xml) ?>;

        $(document).ready(function() {
            $('#fornecedor_id').select2({
                placeholder: 'Buscar fornecedor...',
                allowClear: true,
                width: '100%'
            });
            
            $(document).on('input change', 'input.campo-erro, select.campo-erro, textarea.campo-erro', function() {
                $(this).removeClass('campo-erro');
            });
            
            $('#formCompraFinal').on('submit', function(e) {
                calc();
                const totalHidden = parseFloat($('#total_final_hidden').val()) || 0;
                
                if (totalHidden <= 0) {
                    e.preventDefault();
                    mostrarNotificacaoGrande(
                        '<strong>Valor total da compra está zerado!</strong><br><br>' +
                        '<div style="padding: 15px; background: rgba(239, 68, 68, 0.1); border-radius: 8px; margin: 15px 0;">' +
                        '<i class="fas fa-exclamation-circle" style="color: #ef4444;"></i> O valor total é <strong>R$ 0,00</strong>' +
                        '</div>',
                        'warning'
                    );
                    return false;
                }
                return true;
            });
        });

        function formatarMoedaBR(valor) {
            return parseFloat(valor).toLocaleString('pt-br', {minimumFractionDigits: 2});
        }

        function addParcela() {
            const container = $('#parcelas-container');
            const i = container.find('.parcela-item').length;
            const html = `
            <div class="parcela-item" data-index="${i}">
                <div class="parcela-row">
                    <div class="parcela-field"><label>Parc</label><input type="text" value="${i+1}" readonly style="height:35px;"></div>
                    <div class="parcela-field"><label>Venc</label><input type="date" name="parc_venc[]" value="<?= date('Y-m-d') ?>" style="height:35px;" required></div>
                    <div class="parcela-field"><label>Valor</label><input type="text" name="parc_valor[]" class="money" value="R$ 0,00" style="height:35px;" required></div>
                    <button type="button" class="btn-remove-parcela" onclick="removerParcela(${i})"><i class="fas fa-times"></i></button>
                </div>
            </div>`;
            container.append(html);
            $('#total-parcelas').text(container.find('.parcela-item').length);
            if ($.fn.mask) $('.money').mask('#.##0,00', {reverse: true});
        }

        function removerParcela(index) {
            $(`.parcela-item[data-index="${index}"]`).fadeOut(300, function() {
                $(this).remove();
                $('#parcelas-container .parcela-item').each(function(i) {
                    $(this).attr('data-index', i);
                    $(this).find('input[type="text"]:first').val(i + 1);
                });
                $('#total-parcelas').text($('#parcelas-container .parcela-item').length);
            });
        }

        function limparParcelas() {
            if (confirm('Deseja realmente limpar todas as parcelas?')) {
                $('#parcelas-container').html('');
                $('#total-parcelas').text('0');
            }
        }

        function calcularPrecoVenda() {
            let custoStr = $('#dr_custo').val().replace(/[^\d,]/g, '').replace(',', '.');
            let custo = parseFloat(custoStr) || 0;
            let margemStr = $('#dr_margem').val().replace(/[^\d,]/g, '').replace(',', '.');
            let margem = parseFloat(margemStr) || 0;
            let precoVenda = custo * (1 + margem / 100);
            $('#dr_venda').val('R$ ' + precoVenda.toFixed(2).replace('.', ','));
            if (margem > 0 && !$('#dr_margem').val().includes('%')) {
                $('#dr_margem').val(margem.toFixed(0) + ' %');
            }
        }

        function toggleDrawer(type, idx = null) {
            if (idx !== null) {
                window.itemAtivo = idx;
                $('#dr_nome').val($(`#p_nome_xml_${idx}`).val());
                $('#dr_custo').val($(`#p_v_${idx}`).val());
                $('#dr_gtin').val($(`#p_gtin_xml_${idx}`).val());
                $('#dr_ncm').val($(`#p_ncm_xml_${idx}`).val()); 
                $('#dr_cfop').val($(`#p_cfop_xml_${idx}`).val()); 
                $('#dr_cest').val($(`#p_cest_xml_${idx}`).val());
                calcularPrecoVenda();
            }
            $(`#${type}Drawer`).toggleClass('active');
            $(`#${type}Overlay`).toggle();
        }

        function setSt(prefix, status, btn) {
            $(`#${prefix}_status`).val(status);
            $(btn).parent().find('button').removeClass('st-on').addClass('st-off');
            $(btn).removeClass('st-off').addClass('st-on');
        }

        function calc() {
            let sub = 0, descP = 0;

            $('.item-xml-box').each(function() {
                let vStr = $(this).find('.p_v_input').val() || '0';
                let qStr = $(this).find('.p_q_input').val() || '0';
                let dStr = $(this).find('.p_d_input').val() || '0';

                let limparValor = (str) => {
                    return parseFloat(str.replace(/R\$\s?/, '').replace(/\./g, '').replace(',', '.')) || 0;
                };

                let v = limparValor(vStr);
                let q = parseFloat(qStr.toString().replace(',', '.')) || 0;
                let d = limparValor(dStr);

                let subtotalItem = v * q;
                let totalItem = subtotalItem - d;

                sub += subtotalItem;
                descP += d;

                $(this).find('.item-total-txt').val("R$ " + totalItem.toLocaleString('pt-br', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }));
            });

            let fStr = $('#in_frete').val() || '0';
            let dgStr = $('#in_desc_global').val() || '0';

            let f = parseFloat(fStr.replace(/R\$\s?/, '').replace(/\./g, '').replace(',', '.')) || 0;
            let dg = parseFloat(dgStr.replace(/R\$\s?/, '').replace(/\./g, '').replace(',', '.')) || 0;

            let final = sub - descP + f - dg;
            if (final < 0) final = 0;

            $('#disp_sub').val("R$ " + sub.toLocaleString('pt-br', { minimumFractionDigits: 2 }));
            $('#disp_desc_prod').val("R$ " + descP.toLocaleString('pt-br', { minimumFractionDigits: 2 }));
            $('#disp_total').val("R$ " + final.toLocaleString('pt-br', { minimumFractionDigits: 2 }));
            $('#total_final_hidden').val(final.toFixed(2));

            return final;
        }

        function mostrarNotificacaoGrande(mensagem, tipo = 'warning') {
            const cores = {
                warning: { bg: '#fef3c7', border: '#f59e0b', icon: 'fa-exclamation-triangle', iconColor: '#f59e0b' },
                error: { bg: '#fee2e2', border: '#ef4444', icon: 'fa-times-circle', iconColor: '#ef4444' },
                success: { bg: '#d1fae5', border: '#10b981', icon: 'fa-check-circle', iconColor: '#10b981' }
            };
            
            const config = cores[tipo];
            
            const overlay = $('<div class="notif-overlay"></div>');
            const notif = $('<div class="notificacao-grande"></div>').css({
                background: config.bg,
                border: `3px solid ${config.border}`
            }).html(`
                <div style="text-align: center; margin-bottom: 20px;">
                    <i class="fas ${config.icon}" style="font-size: 48px; color: ${config.iconColor};"></i>
                </div>
                <div style="color: #1f2937; font-size: 15px; line-height: 1.6;">
                    ${mensagem}
                </div>
                <div style="margin-top: 25px; text-align: center;">
                    <button onclick="$('.notif-overlay, .notificacao-grande').remove()" style="
                        background: ${config.border};
                        color: white;
                        border: none;
                        padding: 12px 30px;
                        border-radius: 8px;
                        font-weight: 700;
                        cursor: pointer;
                        font-size: 15px;
                        font-family: 'Exo', sans-serif;
                    ">ENTENDI</button>
                </div>
            `);
            
            $('body').append(overlay).append(notif);
            overlay.on('click', function() {
                overlay.remove();
                notif.remove();
            });
        }

        function validarFormulario(type) {
            const formID = type === 'forn' ? '#formForn' : '#formProd';
            let camposVazios = [];
            let camposObrigatorios = [];
            
            $(formID).find('input, select, textarea').removeClass('campo-erro');
            
            if (type === 'forn') {
                if (!$('#f_fantasia').val() && !$('#f_razao').val()) {
                    camposVazios.push('Nome Fantasia ou Razão Social');
                    $('#f_fantasia, #f_razao').addClass('campo-erro');
                    camposObrigatorios.push('#f_fantasia');
                }
            } else {
                if (!$('#dr_nome').val() || $('#dr_nome').val().trim() === '') {
                    camposVazios.push('Nome do Produto');
                    $('#dr_nome').addClass('campo-erro');
                    camposObrigatorios.push('#dr_nome');
                }
                
                if (!$('#dr_cat').val()) {
                    camposVazios.push('Categoria');
                    $('#dr_cat').addClass('campo-erro');
                    camposObrigatorios.push('#dr_cat');
                }
                
                if (!$('#dr_comissao').val()) {
                    camposVazios.push('Grupo de Comissão');
                    $('#dr_comissao').addClass('campo-erro');
                    camposObrigatorios.push('#dr_comissao');
                }
            }
            
            if (camposVazios.length > 0) {
                let mensagem = '<div style="text-align: left;"><strong style="font-size: 17px;">Campos obrigatórios não preenchidos:</strong><br><br>';
                mensagem += '<ul style="margin: 10px 0 10px 20px; padding: 0;">';
                camposVazios.forEach(campo => {
                    mensagem += `<li style="margin: 8px 0; font-weight: 600;">${campo}</li>`;
                });
                mensagem += '</ul></div>';
                
                mostrarNotificacaoGrande(mensagem, 'warning');
                
                if (camposObrigatorios.length > 0) {
                    setTimeout(() => {
                        $(camposObrigatorios[0]).focus();
                    }, 500);
                }
                
                return false;
            }
            
            return true;
        }

        function salvarAjax(type) {
            if (!validarFormulario(type)) {
                return;
            }
            
            const formID = type === 'forn' ? '#formForn' : '#formProd';
            const action = type === 'forn' ? 'acao_novo_fornecedor' : 'acao_novo_produto';
            const formData = new FormData($(formID)[0]);
            formData.append(action, '1');

            const btn = $(formID).parent().parent().find('.btn-finalizar');
            const originalHTML = btn.html();
            btn.html('<i class="fas fa-spinner fa-spin"></i> Salvando...').prop('disabled', true);

            $.ajax({
                url: 'compras.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(res) {
                    if(res.status === 'success') {
                        if(type === 'forn') { 
                            const newOption = new Option(res.nome, res.id, true, true);
                            $('#fornecedor_id').append(newOption).trigger('change');
                        } else { 
                            const newOption = new Option(res.nome, res.id, true, true);
                            $(`#sel_prod_${window.itemAtivo}`).append(newOption);
                            $(`#sel_prod_${window.itemAtivo}`).select2({
                                placeholder: 'Buscar produto...',
                                allowClear: true,
                                width: '100%'
                            }).val(res.id).trigger('change');
                        }
                        toggleDrawer(type);
                        $(formID)[0].reset();
                        mostrarNotificacao('✅ Cadastrado com sucesso!', 'success');
                    } else {
                        mostrarNotificacaoGrande('❌ <strong>Erro ao salvar:</strong><br><br>' + res.message, 'error');
                    }
                },
                error: function(xhr, status, error) { 
                    console.error('Erro AJAX:', xhr.responseText);
                    mostrarNotificacaoGrande('❌ <strong>Erro de comunicação com o servidor</strong>', 'error');
                },
                complete: function() { 
                    btn.html(originalHTML).prop('disabled', false);
                }
            });
        }

        function salvarAux(drawer, action, targetSelect) {
            const formID = '#form' + drawer.charAt(0).toUpperCase() + drawer.slice(1);
            const input = $(formID).find('input[required]');
            
            if (input.length > 0 && !input.val()) {
                input.addClass('campo-erro');
                return;
            }
            
            $(formID).find('input').removeClass('campo-erro');
            const formData = new FormData($(formID)[0]);
            formData.append(action, '1');

            $.ajax({
                url: 'compras.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(res) {
                    if(res.status === 'success') {
                        $(`#${targetSelect}`).append(new Option(res.nome, res.id, true, true));
                        toggleDrawer(drawer);
                        $(formID)[0].reset();
                        mostrarNotificacao('✅ Cadastrado com sucesso!', 'success');
                    }
                }
            });
        }

        function addLinhaManual() {
            let i = $('.item-xml-box').length + 1000;
            let html = `
            <div class="item-xml-box" style="border-left-color: #f59e0b;">
                <div class="prod-title-xml" style="color: #f59e0b;">
                    <i class="fas fa-hand-pointer"></i> Item Manual
                </div>
                <input type="hidden" name="p_nome_xml[]" value="">
                <input type="hidden" name="p_ncm_xml[]" value="">
                <input type="hidden" name="p_gtin_xml[]" value="">
                <div class="select-row-orange">
                    <select name="p_id[]" id="sel_prod_${i}" class="select-produto">
                        <option value="">Buscar produto...</option>
                        <?php foreach($produtos_db as $p) {
                            $label = htmlspecialchars($p['nome']);
                            if (!empty($p['gtin'])) $label .= ' - ' . $p['gtin'];
                            echo "<option value='{$p['id']}'>{$label}</option>";
                        } ?>
                    </select>
                    <button type="button" class="btn-plus-orange" onclick="toggleDrawer('prod', ${i})">+</button>
                    <div class="btn-stock">Estoque Geral</div>
                </div>
                <div class="grid-values-xml">
                    <div class="label-float"><label>Valor</label><input type="text" name="p_v[]" class="p_v_input" value="R$ 0,00" onkeyup="calc()"></div>
                    <div class="label-float"><label>Desc.</label><input type="text" class="p_d_input" value="R$ 0,00" onkeyup="calc()"></div>
                    <div class="label-float"><label>Qtd</label><input type="number" name="p_q[]" class="p_q_input" value="1" step="0.01" onchange="calc()"></div>
                    <div class="label-float"><label>Total</label><input type="text" class="item-total-txt" readonly value="R$ 0,00"></div>
                </div>
            </div>`;
            $('#itens-container').append(html);
            
            $(`#sel_prod_${i}`).select2({
                placeholder: 'Buscar produto...',
                allowClear: true,
                width: '100%',
                dropdownParent: $('.item-xml-box').last()
            });
            
            calc();
        }

        function mostrarNotificacao(mensagem, tipo = 'info') {
            const cores = {
                success: 'linear-gradient(135deg, #28A745 0%, #28A745 100%)',
                error: 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)',
                info: 'linear-gradient(135deg, #17a2b8 0%, #138496 100%)'
            };
            
            const icones = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                info: 'fa-info-circle'
            };
            
            const notif = $('<div>').css({
                position: 'fixed',
                top: '20px',
                right: '20px',
                zIndex: '10000',
                background: cores[tipo],
                color: 'white',
                padding: '18px 28px',
                borderRadius: '12px',
                boxShadow: '0 10px 25px rgba(0,0,0,0.3)',
                fontWeight: '600',
                fontSize: '15px',
                display: 'flex',
                alignItems: 'center',
                gap: '12px',
                opacity: '0',
                transform: 'translateX(400px)',
                fontFamily: "'Exo', sans-serif"
            }).html(`<i class="fas ${icones[tipo]}"></i> ${mensagem}`);
            
            $('body').append(notif);
            
            notif.animate({
                opacity: 1,
                right: '20px'
            }, 300);
            
            setTimeout(() => {
                notif.animate({
                    opacity: 0,
                    transform: 'translateX(400px)'
                }, 300, function() {
                    $(this).remove();
                });
            }, 3000);
        }

        window.onload = function() {
            const dataXML = sessionStorage.getItem('xml_import_data');
            if (!dataXML) return;
            
            try {
                const data = JSON.parse(dataXML);

                $('#compra_nf').val(data.compra.nf_numero);
                $('#compra_serie').val(data.compra.nf_serie);
                $('#compra_emissao').val(data.compra.data_emissao.split('T')[0]);
                $('#chave_nfe_hidden').val(data.compra.chave_nfe);
                
                if (data.compra.valor_frete > 0) {
                    $('#in_frete').val('R$ ' + data.compra.valor_frete.toLocaleString('pt-br', {minimumFractionDigits: 2}));
                }

                if(data.fornecedor.id) { 
                    $('#fornecedor_id').val(data.fornecedor.id).trigger('change');
                } else {
                    toggleDrawer('forn');
                    $('#f_cnpj').val(data.fornecedor.cnpj);
                    $('#f_razao').val(data.fornecedor.razao_social);
                    $('#f_fantasia').val(data.fornecedor.nome_fantasia);
                    $('#f_cep').val(data.fornecedor.cep);
                    $('#f_endereco').val(data.fornecedor.endereco);
                    $('#f_bairro').val(data.fornecedor.bairro);
                    $('#f_cidade').val(data.fornecedor.cidade);
                    $('#f_tel1').val(data.fornecedor.telefone);
                }
                
                let htmlItens = '';
                data.itens.forEach((item, i) => {
                    let vUnit = parseFloat(item.valor_unitario) || 0;
                    let vTot = parseFloat(item.valor_total) || 0;
                    
                    htmlItens += `
                    <div class="item-xml-box">
                        <span class="prod-sub-title"><i class="fas fa-file-invoice"></i> Produto da nota:</span>
                        <span class="prod-title-xml">${item.nome}</span>
                        <input type="hidden" name="p_nome_xml[]" id="p_nome_xml_${i}" value="${item.nome}">
                        <input type="hidden" name="p_gtin_xml[]" id="p_gtin_xml_${i}" value="${item.ean || ''}">
                        <input type="hidden" name="p_ncm_xml[]" id="p_ncm_xml_${i}" value="${item.ncm || ''}">
                        <input type="hidden" name="p_cfop_xml[]" id="p_cfop_xml_${i}" value="${item.cfop || ''}">
                        <div class="select-row-orange">
                            <select name="p_id[]" id="sel_prod_${i}" class="select-produto">
                                <option value="">Vincular produto...</option>
                                <?php foreach($produtos_db as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?> <?= !empty($p['gtin']) ? ' - '.$p['gtin'] : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-plus-orange" onclick="toggleDrawer('prod', ${i})">+</button>
                            <div class="btn-stock">${item.produto_cadastrado ? '<i class="fas fa-check"></i> Cadastrado' : 'Estoque Geral'}</div>
                        </div>
                        <div class="grid-values-xml">
                            <div class="label-float"><label>Valor</label><input type="text" name="p_v[]" id="p_v_${i}" class="p_v_input" value="R$ ${vUnit.toLocaleString('pt-br', {minimumFractionDigits: 2})}" onkeyup="calc()"></div>
                            <div class="label-float"><label>Desc.</label><input type="text" class="p_d_input" value="R$ 0,00" onkeyup="calc()"></div>
                            <div class="label-float"><label>Qtd</label><input type="number" name="p_q[]" class="p_q_input" value="${item.quantidade}" step="0.01" onchange="calc()"></div>
                            <div class="label-float"><label>Total</label><input type="text" class="item-total-txt" readonly value="R$ ${vTot.toLocaleString('pt-br', {minimumFractionDigits: 2})}"></div>
                        </div>
                    </div>`;
                });
                
                $('#itens-container').html(htmlItens);
                
                $('.select-produto').each(function(index) {
                    const item = data.itens[index];
                    $(this).select2({ width: '100%' });
                    if (item && item.id_produto) {
                        $(this).val(item.id_produto).trigger('change');
                    }
                });

                if (data.parcelas && data.parcelas.length > 0) {
                    const containerParc = $('#parcelas-container');
                    containerParc.html('');
                    
                    data.parcelas.forEach((p, i) => {
                        const vParc = parseFloat(p.valor) || 0;
                        const htmlParc = `
                        <div class="parcela-item" data-index="${i}">
                            <div class="parcela-row">
                                <div class="parcela-field"><label>Parc</label><input type="text" value="${i+1}" readonly style="height:35px;"></div>
                                <div class="parcela-field"><label>Venc</label><input type="date" name="parc_venc[]" value="${p.vencimento}" style="height:35px;" required></div>
                                <div class="parcela-field"><label>Valor</label><input type="text" name="parc_valor[]" class="money" value="R$ ${vParc.toLocaleString('pt-br', {minimumFractionDigits: 2})}" style="height:35px;" required></div>
                                <button type="button" class="btn-remove-parcela" onclick="removerParcela(${i})"><i class="fas fa-times"></i></button>
                            </div>
                        </div>`;
                        containerParc.append(htmlParc);
                    });
                    
                    $('#total-parcelas').text(data.parcelas.length);
                    setTimeout(() => {
                        if ($.fn.mask) $('.money').mask('#.##0,00', {reverse: true});
                    }, 200);
                }
                
                calc();
                sessionStorage.removeItem('xml_import_data');
                mostrarNotificacao('📦 XML processado: Itens e Parcelas carregados!', 'success');
                
            } catch (e) {
                console.error('Erro ao processar dados do XML:', e);
                mostrarNotificacao('❌ Erro crítico ao ler dados do XML.', 'error');
            }
        };
    </script>
</body>
</html>