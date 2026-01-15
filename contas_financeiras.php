<?php
/**
 * =========================================================================================
 * ZIIPVET - CADASTRO DE CONTAS FINANCEIRAS
 * ARQUIVO: contas_financeiras.php
 * VERSÃO: 2.0.0 - REFATORADO COM SERVICE LAYER
 * =========================================================================================
 */

// ATIVAÇÃO DE ERROS
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'config/configuracoes.php';
require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Database;
use App\Infrastructure\Repository\ContaFinanceiraRepository;
use App\Application\Service\ContaFinanceiraService;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;
$id_edit = $_GET['id'] ?? null;
$dados = [];

// Inicializar Service Layer
try {
    $db = Database::getInstance();
    $contaRepository = new ContaFinanceiraRepository($db);
    $contaService = new ContaFinanceiraService($contaRepository);
} catch (Exception $e) {
    die("Erro ao inicializar sistema: " . $e->getMessage());
}

// ==========================================================
// PROCESSAMENTO POST (SALVAR) - USANDO SERVICE LAYER
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar') {
    try {
        // Preparar dados para o Service
        $dadosConta = [
            'id' => $_POST['id_edit'] ?? null,
            'nome_conta' => trim($_POST['nome']),
            'tipo_conta' => $_POST['tipo'],
            'status' => $_POST['status'],
            'permitir_lancamentos' => isset($_POST['permitir_lancamentos']) ? 1 : 0,
            'saldo_inicial' => $_POST['valor_saldo'] ?? 0.00,
            'data_saldo' => !empty($_POST['data_saldo']) ? $_POST['data_saldo'] : null,
            'situacao_saldo' => $_POST['situacao_saldo'] ?? 'Positivo'
        ];

        // Chamar Service (ele detecta automaticamente se é criação ou atualização)
        $resultado = $contaService->salvar($dadosConta, $id_admin);

        if ($resultado['success']) {
            echo "<script>alert('{$resultado['message']}'); window.location.href='listar_contas_financeiras.php';</script>";
            exit;
        } else {
            $erro = $resultado['message'];
            echo "<script>alert('Erro: $erro');</script>";
        }

    } catch (Exception $e) {
        $erro = $e->getMessage();
        echo "<script>alert('Erro: $erro');</script>";
    }
}

// ==========================================================
// CARREGAR DADOS PARA EDIÇÃO - USANDO SERVICE LAYER
// ==========================================================
if ($id_edit) {
    $dados = $contaService->buscarPorId((int)$id_edit, $id_admin);
    if (!$dados) {
        echo "<script>alert('Conta não encontrada!'); window.location.href='listar_contas_financeiras.php';</script>";
        exit;
    }
}

$titulo_pagina = $id_edit ? "Editar Conta" : "Contas e cartões";
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
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #444;
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
        
        .btn-back {
            background: white;
            color: #666;
            border: 2px solid #e0e0e0;
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
        
        .btn-back:hover {
            border-color: var(--roxo);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        /* CARD DO FORMULÁRIO */
        .card-main {
            background: #fff;
            border-radius: 16px;
            padding: 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .card-header-blue {
            background: linear-gradient(135deg, var(--roxo) 0%, #8e44ad 100%);
            color: #fff;
            padding: 20px 30px;
            font-size: 18px;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header-blue i {
            margin-right: 10px;
        }

        .card-body {
            padding: 35px;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 35px;
        }
        
        @media (max-width: 992px) {
            .card-body {
                grid-template-columns: 1fr;
            }
        }

        /* FORMULÁRIOS */
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            font-size: 14px;
            font-weight: 700;
            color: #444;
            display: block;
            margin-bottom: 10px;
            font-family: 'Exo', sans-serif;
        }
        
        label i {
            margin-right: 5px;
            color: var(--roxo);
        }
        
        input[type="text"],
        input[type="date"],
        select {
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
        
        input:focus,
        select:focus {
            border-color: var(--roxo);
            box-shadow: 0 0 0 4px rgba(98, 37, 153, 0.1);
        }

        /* STATUS TOGGLE */
        .status-toggle {
            display: inline-flex;
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid #e5e7eb;
        }
        
        .status-radio {
            display: none;
        }
        
        .status-label {
            padding: 12px 24px;
            font-size: 14px;
            cursor: pointer;
            background: white;
            color: #666;
            transition: all 0.3s;
            font-weight: 600;
            font-family: 'Exo', sans-serif;
            border-right: 2px solid #e5e7eb;
        }
        
        .status-label:last-child {
            border-right: none;
        }
        
        .status-radio:checked + .status-label.ativo {
            background: linear-gradient(135deg, var(--verde), #218838);
            color: #fff;
        }
        
        .status-radio:checked + .status-label.inativo {
            background: linear-gradient(135deg, var(--vermelho), #a01f21);
            color: #fff;
        }

        /* CHECKBOX */
        .check-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            font-size: 14px;
            cursor: pointer;
            font-family: 'Exo', sans-serif;
            font-weight: 600;
            color: #444;
        }
        
        .check-container input {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        /* PAINEL LATERAL (SALDO INICIAL) */
        .side-panel {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 25px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .side-title {
            font-size: 16px;
            color: #444;
            margin-bottom: 20px;
            border-bottom: 3px solid;
            border-image: linear-gradient(135deg, var(--roxo), #8e44ad) 1;
            padding-bottom: 12px;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .side-title i {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--roxo), #8e44ad);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }

        /* FOOTER */
        .card-footer {
            background: #f8f9fa;
            padding: 25px 35px;
            border-top: 2px solid #f0f0f0;
            display: flex;
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
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-save:hover {
            background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(40, 167, 69, 0.3);
        }
        
        .btn-cancel {
            background: white;
            color: #666;
            border: 2px solid #e0e0e0;
            padding: 14px 40px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 15px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-family: 'Exo', sans-serif;
            letter-spacing: 0.5px;
        }
        
        .btn-cancel:hover {
            background: #f8f9fa;
            border-color: var(--vermelho);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
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
        
        .card-main {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include 'menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include 'menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <div class="page-header">
            <h2 class="page-title">
                <i class="fas fa-university"></i>
                <?= $titulo_pagina ?>
            </h2>
            <a href="listar_contas_financeiras.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <form method="POST">
            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" name="id_edit" value="<?= $dados['id'] ?? '' ?>">

            <div class="card-main">
                <div class="card-header-blue">
                    <span>
                        <i class="fas fa-credit-card"></i>
                        <?= $id_edit ? 'Editar Conta Financeira' : 'Nova Conta Financeira' ?>
                    </span>
                </div>
                
                <div class="card-body">
                    <div class="left-col">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Nome da Conta *</label>
                            <input type="text" name="nome" value="<?= $dados['nome_conta'] ?? '' ?>" required placeholder="Ex: Banco Itaú - Conta Corrente">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-list"></i> Tipo de Conta</label>
                            <select name="tipo">
                                <option value="">Selecione o tipo...</option>
                                <option value="Conta corrente" <?= ($dados['tipo_conta'] ?? '') == 'Conta corrente' ? 'selected' : '' ?>>Conta corrente</option>
                                <option value="Conta poupança" <?= ($dados['tipo_conta'] ?? '') == 'Conta poupança' ? 'selected' : '' ?>>Conta poupança</option>
                                <option value="Espécie" <?= ($dados['tipo_conta'] ?? '') == 'Espécie' ? 'selected' : '' ?>>Espécie (Dinheiro)</option>
                                <option value="Investimento" <?= ($dados['tipo_conta'] ?? '') == 'Investimento' ? 'selected' : '' ?>>Investimento</option>
                                <option value="Cartão de crédito" <?= ($dados['tipo_conta'] ?? '') == 'Cartão de crédito' ? 'selected' : '' ?>>Cartão de crédito</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-toggle-on"></i> Status da Conta</label>
                            <div class="status-toggle">
                                <input type="radio" id="st_ativo" name="status" value="Ativo" class="status-radio" <?= ($dados['status'] ?? 'Ativo') == 'Ativo' ? 'checked' : '' ?>>
                                <label for="st_ativo" class="status-label ativo">
                                    <i class="fas fa-check-circle"></i> Ativo
                                </label>
                                
                                <input type="radio" id="st_inativo" name="status" value="Inativo" class="status-radio" <?= ($dados['status'] ?? '') == 'Inativo' ? 'checked' : '' ?>>
                                <label for="st_inativo" class="status-label inativo">
                                    <i class="fas fa-times-circle"></i> Inativo
                                </label>
                            </div>
                        </div>

                        <label class="check-container">
                            <input type="checkbox" name="permitir_lancamentos" <?= ($dados['permitir_lancamentos'] ?? 0) == 1 ? 'checked' : '' ?>>
                            <i class="fas fa-bolt"></i> Permitir lançamentos rápidos nesta conta
                        </label>
                    </div>

                    <div class="right-col">
                        <div class="side-panel">
                            <div class="side-title">
                                <i class="fas fa-dollar-sign"></i>
                                Saldo Inicial
                            </div>
                            
                            <div class="form-group">
                                <label><i class="far fa-calendar"></i> Data do Saldo</label>
                                <input type="date" name="data_saldo" value="<?= $dados['data_saldo'] ?? '' ?>" placeholder="dd/mm/aaaa">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-money-bill-wave"></i> Valor Inicial</label>
                                <input type="text" name="valor_saldo" id="valor_saldo" value="<?= !empty($dados['saldo_inicial']) ? number_format($dados['saldo_inicial'], 2, ',', '.') : '' ?>" placeholder="R$ 0,00">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-chart-line"></i> Situação do Saldo</label>
                                <select name="situacao_saldo">
                                    <option value="Positivo" <?= ($dados['situacao_saldo'] ?? '') == 'Positivo' ? 'selected' : '' ?>>
                                        Positivo (Crédito)
                                    </option>
                                    <option value="Negativo" <?= ($dados['situacao_saldo'] ?? '') == 'Negativo' ? 'selected' : '' ?>>
                                        Negativo (Débito)
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn-save">
                        <i class="fas fa-check-circle"></i> Salvar Conta
                    </button>
                    <a href="listar_contas_financeiras.php" class="btn-cancel">
                        <i class="fas fa-times-circle"></i> Cancelar
                    </a>
                </div>
            </div>
        </form>

    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script>
        $(document).ready(function(){
            // Máscara de dinheiro
            $('#valor_saldo').mask('#.##0,00', {reverse: true});
        });
    </script>
</body>
</html>