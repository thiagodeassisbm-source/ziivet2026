<?php
/**
 * =========================================================================================
 * ZIIPVET - GERENCIAMENTO DE NÚMEROS AUTORIZADOS WHATSAPP
 * ARQUIVO: gerenciar_numeros_autorizados.php
 * VERSÃO: 4.0.0 - PADRÃO MODERNO
 * =========================================================================================
 */
require_once 'auth.php';
require_once 'config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;

// ==========================================================
// CONFIGURAÇÃO DA PÁGINA
// ==========================================================
$titulo_pagina = "Números Autorizados WhatsApp";

$mensagem = '';
$tipo_mensagem = '';

// ==========================================================
// PROCESSAR AÇÕES (Adicionar, Editar, Excluir, Ativar/Desativar)
// ==========================================================

// ADICIONAR NOVO NÚMERO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'adicionar') {
    $numero = preg_replace('/[^0-9]/', '', $_POST['numero']);
    $nome = trim($_POST['nome_responsavel']);
    $cargo = trim($_POST['cargo']);
    $obs = trim($_POST['observacoes']);
    
    if (!empty($numero) && !empty($nome)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO numeros_autorizados_whatsapp 
                (numero, nome_responsavel, cargo, observacoes) 
                VALUES (?, ?, ?, ?)");
            $stmt->execute([$numero, $nome, $cargo, $obs]);
            
            $mensagem = "Número <strong>$numero</strong> adicionado com sucesso!";
            $tipo_mensagem = 'success';
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $mensagem = "Este número já está cadastrado!";
                $tipo_mensagem = 'warning';
            } else {
                $mensagem = "Erro ao adicionar: " . $e->getMessage();
                $tipo_mensagem = 'error';
            }
        }
    } else {
        $mensagem = "Preencha pelo menos o número e o nome do responsável!";
        $tipo_mensagem = 'warning';
    }
}

// ALTERAR STATUS (Ativar/Desativar)
if (isset($_GET['acao']) && $_GET['acao'] === 'toggle' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("UPDATE numeros_autorizados_whatsapp SET ativo = NOT ativo WHERE id = ?");
        $stmt->execute([$id]);
        $mensagem = "Status alterado com sucesso!";
        $tipo_mensagem = 'success';
    } catch (PDOException $e) {
        $mensagem = "Erro ao alterar status: " . $e->getMessage();
        $tipo_mensagem = 'error';
    }
}

// EXCLUIR NÚMERO (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excluir') {
    ob_clean();
    header('Content-Type: application/json');
    $id = (int)$_POST['id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM numeros_autorizados_whatsapp WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'Número excluído com sucesso!']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao excluir: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// BUSCAR NÚMEROS CADASTRADOS
// ==========================================================
try {
    $stmt = $pdo->query("SELECT * FROM numeros_autorizados_whatsapp ORDER BY ativo DESC, nome_responsavel ASC");
    $numeros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar números: " . $e->getMessage());
}

$total_ativos = count(array_filter($numeros, fn($n) => $n['ativo'] == 1));
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
           ESTILOS ESPECÍFICOS DE NÚMEROS AUTORIZADOS
        ======================================== */
        
        /* KPI Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: #fff;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 8px;
            font-family: 'Exo', sans-serif;
        }
        
        .stat-card p {
            font-size: 14px;
            opacity: 0.9;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card.total {
            background: linear-gradient(135deg, #131c71 0%, #0d1450 100%);
        }
        
        .stat-card.inativos {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        
        /* Alert Messages */
        .alert-message {
            padding: 18px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 15px;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .alert-message i {
            font-size: 20px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c8e6c9 100%);
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffe0b2 100%);
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #f8d7da 0%, #ffcdd2 100%);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        /* Form Section */
        .form-card {
            background: #fff;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 25px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }
        
        .form-card h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 20px;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-box {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-left: 4px solid #2196f3;
            padding: 18px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .info-box strong {
            color: #1565c0;
            font-weight: 700;
        }
        
        .info-box code {
            background: rgba(255, 255, 255, 0.8);
            padding: 3px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: #d32f2f;
            font-weight: 600;
        }
        
        /* Table Container */
        .table-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }
        
        .table-header {
            padding: 20px 25px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .table-header h2 {
            color: #2c3e50;
            font-size: 20px;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }
        
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
            font-size: 13px;
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
        
        tbody tr.inativo {
            opacity: 0.5;
            background: #fafafa;
        }
        
        .numero-cell {
            font-weight: 700;
            color: #131c71;
            font-size: 16px;
        }
        
        .responsavel-cell {
            font-weight: 600;
            color: #2c3e50;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .badge-ativo {
            background: linear-gradient(135deg, #d4edda 0%, #c8e6c9 100%);
            color: #155724;
        }
        
        .badge-inativo {
            background: linear-gradient(135deg, #f8d7da 0%, #ffcdd2 100%);
            color: #721c24;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-action {
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-toggle {
            background: #ffc107;
            color: #333;
        }
        
        .btn-toggle:hover {
            background: #ffb300;
            transform: translateY(-2px);
        }
        
        .btn-delete {
            background: #dc3545;
            color: #fff;
        }
        
        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        /* Submit Button */
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(40, 167, 69, 0.4);
        }
        
        /* Empty State */
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
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 13px;
            }
            
            thead th, tbody td {
                padding: 10px 8px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include 'menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include 'menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <!-- HEADER: Título -->
        <div class="form-header">
            <h1 class="form-title">
                <i class="fab fa-whatsapp"></i>
                Números Autorizados WhatsApp
            </h1>
        </div>

        <!-- ESTATÍSTICAS -->
        <div class="stats-grid">
            <div class="stat-card total">
                <h3><?= count($numeros) ?></h3>
                <p>Total de Números</p>
            </div>
            <div class="stat-card">
                <h3><?= $total_ativos ?></h3>
                <p>Números Ativos</p>
            </div>
            <div class="stat-card inativos">
                <h3><?= count($numeros) - $total_ativos ?></h3>
                <p>Números Inativos</p>
            </div>
        </div>

        <!-- MENSAGEM DE FEEDBACK -->
        <?php if (!empty($mensagem)): ?>
            <div class="alert-message alert-<?= $tipo_mensagem ?>">
                <i class="fas fa-<?= $tipo_mensagem == 'success' ? 'check-circle' : ($tipo_mensagem == 'warning' ? 'exclamation-triangle' : 'times-circle') ?>"></i>
                <span><?= $mensagem ?></span>
            </div>
        <?php endif; ?>

        <!-- FORMULÁRIO DE ADICIONAR -->
        <div class="form-card">
            <h2>
                <i class="fas fa-plus-circle"></i>
                Adicionar Novo Número
            </h2>
            
            <div class="info-box">
                <strong>📱 Formato do número:</strong> Digite no formato internacional sem espaços ou caracteres especiais.<br>
                Exemplo: <code>5562982933585</code> (55 = Brasil, 62 = DDD, resto = número)
            </div>
            
            <form method="POST">
                <input type="hidden" name="acao" value="adicionar">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="required">
                            <i class="fas fa-phone"></i>
                            Número do WhatsApp
                        </label>
                        <input 
                            type="text" 
                            name="numero" 
                            class="form-control"
                            placeholder="Ex: 5562999887766"
                            pattern="[0-9]{10,15}"
                            required
                        >
                    </div>
                    
                    <div class="form-group">
                        <label class="required">
                            <i class="fas fa-user"></i>
                            Nome do Responsável
                        </label>
                        <input 
                            type="text" 
                            name="nome_responsavel" 
                            class="form-control"
                            placeholder="Ex: João Silva"
                            required
                        >
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-briefcase"></i>
                            Cargo/Função
                        </label>
                        <input 
                            type="text" 
                            name="cargo" 
                            class="form-control"
                            placeholder="Ex: Gerente Financeiro"
                        >
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <i class="fas fa-sticky-note"></i>
                        Observações
                    </label>
                    <textarea 
                        name="observacoes" 
                        class="form-control"
                        rows="3"
                        placeholder="Ex: Autorizado pela diretoria em 05/01/2026"
                    ></textarea>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-plus-circle"></i>
                    Adicionar Número
                </button>
            </form>
        </div>

        <!-- LISTA DE NÚMEROS -->
        <div class="table-card">
            <div class="table-header">
                <h2>
                    <i class="fas fa-list"></i>
                    Números Cadastrados
                </h2>
            </div>
            
            <div class="table-wrapper">
                <?php if (empty($numeros)): ?>
                    <div class="empty-state">
                        <i class="fab fa-whatsapp"></i>
                        <h3>Nenhum número cadastrado</h3>
                        <p>Adicione o primeiro número usando o formulário acima</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th width="180">Número</th>
                                <th>Responsável</th>
                                <th width="200">Cargo</th>
                                <th width="120">Status</th>
                                <th width="150">Cadastrado em</th>
                                <th width="180" style="text-align: center;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($numeros as $num): ?>
                                <tr id="row-<?= $num['id'] ?>" class="<?= $num['ativo'] ? '' : 'inativo' ?>">
                                    <td class="numero-cell">
                                        <i class="fab fa-whatsapp" style="color: #25D366; margin-right: 5px;"></i>
                                        <?= htmlspecialchars($num['numero']) ?>
                                    </td>
                                    <td class="responsavel-cell">
                                        <?= htmlspecialchars($num['nome_responsavel']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($num['cargo'] ?: '-') ?></td>
                                    <td>
                                        <span class="status-badge <?= $num['ativo'] ? 'badge-ativo' : 'badge-inativo' ?>">
                                            <i class="fas fa-<?= $num['ativo'] ? 'check-circle' : 'times-circle' ?>"></i>
                                            <?= $num['ativo'] ? 'Ativo' : 'Inativo' ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($num['data_cadastro'])) ?></td>
                                    <td>
                                        <div class="action-buttons" style="justify-content: center;">
                                            <a href="?acao=toggle&id=<?= $num['id'] ?>" 
                                               class="btn-action btn-toggle"
                                               onclick="return confirm('Deseja alterar o status deste número?')">
                                                <i class="fas fa-<?= $num['ativo'] ? 'lock' : 'unlock' ?>"></i>
                                                <?= $num['ativo'] ? 'Desativar' : 'Ativar' ?>
                                            </a>
                                            <button onclick="excluirNumero(<?= $num['id'] ?>, '<?= htmlspecialchars($num['numero']) ?>', '<?= htmlspecialchars($num['nome_responsavel']) ?>')" 
                                                    class="btn-action btn-delete">
                                                <i class="fas fa-trash-alt"></i>
                                                Excluir
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // ==========================================================
        // EXCLUIR NÚMERO
        // ==========================================================
        async function excluirNumero(id, numero, nome) {
            const result = await Swal.fire({
                title: 'Deseja excluir este número?',
                html: `<div style="text-align: left; padding: 10px;">
                    <p style="margin: 5px 0;"><strong>📱 Número:</strong> ${numero}</p>
                    <p style="margin: 5px 0;"><strong>👤 Responsável:</strong> ${nome}</p>
                    <p style="margin: 15px 0 0 0; color: #dc3545;">
                        <strong>⚠️ ATENÇÃO:</strong> Esta ação não pode ser desfeita!
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
            fd.append('acao', 'excluir');
            fd.append('id', id);

            try {
                const res = await fetch('gerenciar_numeros_autorizados.php', { 
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
                    
                    await Swal.fire({
                        icon: 'success',
                        title: 'Número excluído!',
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
                    text: 'Não foi possível excluir o número.',
                    confirmButtonColor: '#131c71'
                });
            }
        }
    </script>
</body>
</html>