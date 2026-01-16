<?php
ob_start();
/**
 * =========================================================================================
 * ZIIPVET - LISTAGEM DE CONTAS FINANCEIRAS
 * ARQUIVO: listar_contas_financeiras.php
 * VERSÃO: 4.0.1 - CORRIGIDO (SEM DUPLA CONTAGEM)
 * =========================================================================================
 */
require_once 'auth.php';
require_once 'config/configuracoes.php';

use App\Utils\Csrf;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;

// ==========================================================
// LÓGICA DE EXCLUSÃO (AJAX)
// ==========================================================
if (isset($_POST['acao']) && $_POST['acao'] === 'excluir_conta') {
    ob_clean();
    header('Content-Type: application/json');
    $id_excluir = $_POST['id'] ?? null;
    
    try {
        $check = $pdo->prepare("SELECT COUNT(*) FROM contas WHERE id_conta_origem = ?");
        $check->execute([$id_excluir]);
        if ($check->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Não é possível excluir esta conta pois ela possui movimentações financeiras.']);
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM contas_financeiras WHERE id = ? AND id_admin = ?");
        $stmt->execute([$id_excluir, $id_admin]);
        echo json_encode(['status' => 'success', 'message' => 'Conta removida com sucesso!']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao excluir conta.']);
    }
    exit;
}

// ==========================================================
// CONFIGURAÇÃO DA PÁGINA - ✅ CORRIGIDO
// ==========================================================
$titulo_pagina = "Contas e Cartões";

try {
    $stmt = $pdo->prepare("SELECT * FROM contas_financeiras WHERE id_admin = ? ORDER BY nome_conta ASC");
    $stmt->execute([$id_admin]);
    $contas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ CORREÇÃO APLICADA: Usar apenas o saldo_inicial da tabela contas_financeiras
    // NÃO somar movimentações da tabela "contas" pois já estão refletidas no saldo_inicial
    // pelos códigos de abertura e encerramento de caixa
    foreach ($contas as &$conta) {
        $saldoInicial = (float)$conta['saldo_inicial'];
        
        // Aplicar situação do saldo (positivo/negativo)
        if ($conta['situacao_saldo'] === 'Negativo') {
            $saldoInicial = -$saldoInicial;
        }
        
        $conta['saldo_atual'] = $saldoInicial;
    }
    unset($conta);

} catch (PDOException $e) {
    $contas = [];
}
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
        /* ========================================
           ESTILOS ESPECÍFICOS DAS CONTAS FINANCEIRAS
        ======================================== */
        
        /* Container da Listagem */
        .list-container {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }
        
        /* Wrapper da Tabela */
        .table-wrapper {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Exo', sans-serif;
        }
        
        thead th {
            background: #f8f9fa;
            padding: 18px 16px;
            text-align: left;
            font-size: 14px;
            font-weight: 700;
            color: #495057;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        tbody td {
            padding: 16px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 15px;
            color: #2c3e50;
            vertical-align: middle;
        }
        
        tbody tr {
            transition: background 0.2s ease;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        /* Nome da Conta */
        .conta-nome {
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .conta-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #e8f0fe 0%, #d2e3fc 100%);
            color: #131c71;
            border-radius: 10px;
            font-size: 18px;
        }
        
        /* Tipo de Conta */
        .tipo-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            background: #f0f0f0;
            color: #6c757d;
        }
        
        /* Saldo */
        .saldo-positivo {
            color: #28a745;
            font-weight: 700;
            font-size: 16px;
        }
        
        .saldo-negativo {
            color: #dc3545;
            font-weight: 700;
            font-size: 16px;
        }
        
        .saldo-neutro {
            color: #6c757d;
            font-weight: 700;
            font-size: 16px;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            font-family: 'Exo', sans-serif;
        }
        
        .badge-ativo {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            color: #2e7d32;
        }
        
        .badge-inativo {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            color: #c62828;
        }
        
        /* Botões de Ação */
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }
        
        .btn-action {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .btn-action.edit {
            background: #17a2b8;
            color: #fff;
        }
        
        .btn-action.edit:hover {
            background: #138496;
            transform: scale(1.1);
        }
        
        .btn-action.delete {
            background: #dc3545;
            color: #fff;
        }
        
        .btn-action.delete:hover {
            background: #c82333;
            transform: scale(1.1);
        }
        
        /* Estado Vazio */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #adb5bd;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .empty-state h3 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
            font-family: 'Exo', sans-serif;
        }
        
        .empty-state p {
            font-size: 16px;
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            table {
                font-size: 13px;
            }
            
            thead th, tbody td {
                padding: 10px 8px;
            }
            
            .conta-nome {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include 'menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include 'menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <!-- HEADER: Título e Botão -->
        <div class="form-header">
            <h1 class="form-title">
                <i class="fas fa-wallet"></i>
                Contas e Cartões
            </h1>
            
            <a href="contas_financeiras.php" class="btn-voltar">
                <i class="fas fa-plus"></i>
                Nova Conta
            </a>
        </div>

        <!-- AVISO DE RECEBIMENTO ANTECIPADO -->
        <div style="background:#e3f2fd; color:#0d47a1; padding:15px; border-radius:10px; border-left:5px solid #1976d2; margin-bottom:20px; display:flex; align-items:center; gap:15px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
            <i class="fas fa-info-circle" style="font-size:24px; color:#1976d2;"></i>
            <div>
                <strong style="display:block; font-size:14px; margin-bottom:4px; font-weight:700;">Recebimento de Cartão de Crédito</strong>
                <span style="font-size:13px; line-height:1.4;">
                    De acordo com a configuração no formulário de "Recebimentos", se for selecionado <strong>recebimento antecipado</strong>, os valores de cartão de crédito estarão disponíveis na conta bancária em até <strong>2 dias úteis</strong>.
                </span>
            </div>
        </div>

        <!-- CONTAINER DA LISTAGEM -->
        <div class="list-container">
            
            <!-- TABELA -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Nome da Conta</th>
                            <th width="200">Tipo</th>
                            <th width="150">Saldo Atual</th>
                            <th width="130">Data Saldo</th>
                            <th width="120">Status</th>
                            <th width="120" style="text-align: right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($contas) > 0): ?>
                            <?php foreach ($contas as $c): 
                                $statusClass = ($c['status'] == 'Ativo') ? 'badge-ativo' : 'badge-inativo';
                                
                                $saldoValor = (float)$c['saldo_atual'];
                                
                                if ($saldoValor < 0) {
                                    $saldoFormatado = '- R$ ' . number_format(abs($saldoValor), 2, ',', '.');
                                    $classeSaldo = 'saldo-negativo';
                                } elseif ($saldoValor > 0) {
                                    $saldoFormatado = 'R$ ' . number_format($saldoValor, 2, ',', '.');
                                    $classeSaldo = 'saldo-positivo';
                                } else {
                                    $saldoFormatado = 'R$ 0,00';
                                    $classeSaldo = 'saldo-neutro';
                                }

                                $dataSaldo = !empty($c['data_saldo']) ? date('d/m/Y', strtotime($c['data_saldo'])) : '-';
                                
                                // Definir ícone baseado no tipo
                                $icone = 'fa-university';
                                if(stripos($c['tipo_conta'], 'cartão') !== false) {
                                    $icone = 'fa-credit-card';
                                } elseif(stripos($c['tipo_conta'], 'espécie') !== false || stripos($c['tipo_conta'], 'dinheiro') !== false) {
                                    $icone = 'fa-money-bill-wave';
                                } elseif(stripos($c['tipo_conta'], 'poupança') !== false) {
                                    $icone = 'fa-piggy-bank';
                                }
                            ?>
                            <tr id="row-<?= $c['id'] ?>">
                                <td>
                                    <div class="conta-nome">
                                        <div class="conta-icon">
                                            <i class="fas <?= $icone ?>"></i>
                                        </div>
                                        <span><?= htmlspecialchars($c['nome_conta']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="tipo-badge">
                                        <?= htmlspecialchars($c['tipo_conta']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="<?= $classeSaldo ?>">
                                        <?= $saldoFormatado ?>
                                    </span>
                                </td>
                                <td><?= $dataSaldo ?></td>
                                <td>
                                    <span class="status-badge <?= $statusClass ?>">
                                        <?= $c['status'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="contas_financeiras.php?id=<?= $c['id'] ?>" 
                                           class="btn-action edit" 
                                           title="Editar Conta">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="excluirConta(<?= $c['id'] ?>, '<?= htmlspecialchars($c['nome_conta']) ?>')" 
                                                class="btn-action delete" 
                                                title="Excluir Conta">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="fas fa-wallet"></i>
                                        <h3>Nenhuma conta financeira cadastrada</h3>
                                        <p>Clique em "Nova Conta" para adicionar sua primeira conta.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // ==========================================================
        // EXCLUIR CONTA FINANCEIRA
        // ==========================================================
        async function excluirConta(id, nome) {
            const result = await Swal.fire({
                title: 'Deseja excluir esta conta?',
                html: `<div style="text-align: left; padding: 10px;">
                    <p style="margin: 5px 0;"><strong>Conta:</strong> ${nome}</p>
                    <p style="margin: 10px 0 0 0; color: #dc3545;">
                        <strong>⚠️ Atenção:</strong> Esta ação não pode ser desfeita.
                    </p>
                </div>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar'
            });

            if (!result.isConfirmed) return;

            const fd = new FormData();
            fd.append('acao', 'excluir_conta');
            fd.append('id', id);
            fd.append('csrf_token', '<?= Csrf::getToken() ?>');

            try {
                const res = await fetch('listar_contas_financeiras.php', { 
                    method: 'POST', 
                    body: fd 
                });
                const data = await res.json();
                
                if (data.status === 'success') {
                    const row = document.getElementById('row-' + id);
                    row.style.transition = 'opacity 0.5s';
                    row.style.opacity = '0';
                    
                    setTimeout(() => {
                        row.remove();
                        
                        // Se não há mais linhas, recarregar para mostrar estado vazio
                        const tbody = document.querySelector('tbody');
                        if (tbody.querySelectorAll('tr').length === 0) {
                            location.reload();
                        }
                    }, 500);
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Conta excluída!',
                        text: data.message,
                        confirmButtonColor: '#28a745',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro ao excluir',
                        text: data.message,
                        confirmButtonColor: '#131c71'
                    });
                }
            } catch (e) {
                console.error('Erro:', e);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro de conexão',
                    text: 'Não foi possível excluir a conta.',
                    confirmButtonColor: '#131c71'
                });
            }
        }
    </script>
</body>
</html>