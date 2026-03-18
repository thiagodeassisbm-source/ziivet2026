<?php
/**
 * Emissão de NFS-e (Nota Fiscal de Serviço Eletrônica)
 * Padrão Nacional NFS-e - Versão Visual Standardizada
 */

require_once '../../auth.php';
require_once '../../config/configuracoes.php';
require_once '../../vendor/autoload.php';

use App\Services\NFSeService;

// Forçar header se for AJAX, senão carregar HTML
$is_ajax = (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest'));

if ($is_ajax) {
    header('Content-Type: application/json');
}

$id_admin = $_SESSION['id_admin'] ?? 1;
$id_venda = $_REQUEST['id_venda'] ?? 0;
$msg_erro = "";
$resultado = null;

if ($id_venda) {
    try {
        // Buscar dados da venda
        $stmt = $pdo->prepare("
            SELECT v.*, c.nome as cliente_nome, c.cpf_cnpj, c.endereco, c.numero, 
                   c.bairro, c.cep, c.cidade, c.codigo_municipio
            FROM vendas v
            LEFT JOIN clientes c ON v.id_cliente = c.id
            WHERE v.id = ? AND v.id_admin = ?
        ");
        $stmt->execute([$id_venda, $id_admin]);
        $venda = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$venda) {
            throw new Exception('Venda não encontrada no sistema.');
        }
        
        // Buscar itens de serviço da venda
        $stmt = $pdo->prepare("
            SELECT vi.*, p.produto as descricao, p.tipo
            FROM vendas_itens vi
            INNER JOIN produtos p ON vi.id_produto = p.id
            WHERE vi.id_venda = ? AND p.tipo = 'servico'
        ");
        $stmt->execute([$id_venda]);
        $itensServico = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($itensServico)) {
            throw new Exception('Esta venda não possui itens de serviço para emissão de NFS-e.');
        }
        
        // Buscar configurações fiscais
        $stmt = $pdo->prepare("SELECT * FROM configuracoes_fiscais WHERE id_admin = ?");
        $stmt->execute([$id_admin]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config) {
            throw new Exception('Configurações fiscais não encontradas. Verifique o menu NFS-e > Configurações.');
        }
        
        // Buscar dados da empresa
        $stmt = $pdo->query("SELECT * FROM minha_empresa LIMIT 1");
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$empresa) {
            throw new Exception('Dados da empresa não encontrados.');
        }
        
        // Buscar configuração do serviço
        $stmt = $pdo->prepare("SELECT * FROM nfse_servicos_config WHERE id_admin = ? LIMIT 1");
        $stmt->execute([$id_admin]);
        $servicoConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$servicoConfig) {
            throw new Exception('Cofiguração de serviço para NFS-e não encontrada.');
        }
        
        // Simulação / Lógica de Emissão
        $valorTotal = 0;
        foreach ($itensServico as $item) {
            $valorTotal += $item['valor_total'];
        }
        
        // [SIMULAÇÃO DE SUCESSO PARA O LAYOUT]
        $resultado = [
            'success' => true,
            'message' => 'NFS-e enviada para processamento',
            'protocolo' => 'NFSE-' . date('YmdHis'),
            'valor' => $valorTotal
        ];

        if ($is_ajax) {
            echo json_encode($resultado);
            exit;
        }

    } catch (Exception $e) {
        $msg_erro = $e->getMessage();
        if ($is_ajax) {
            echo json_encode(['success' => false, 'message' => $msg_erro]);
            exit;
        }
    }
}

// Se chegamos aqui, renderizamos o HTML
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emissão de NFS-e | ZIIPVET</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/menu.css">
    <link rel="stylesheet" href="../../css/header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content { padding: 30px; background: #f4f7f6; min-height: 100vh; }
        .card-emissao { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: none; overflow: hidden; }
        .card-header-ziip { background: #1e40af; color: #fff; padding: 25px; text-align: center; }
        .card-header-ziip i { font-size: 40px; margin-bottom: 10px; display: block; }
        .card-body-ziip { padding: 35px; }
        .form-label { font-weight: 600; color: #374151; margin-bottom: 8px; display: block; }
        .form-control-ziip { width: 100%; padding: 12px 15px; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 16px; transition: all 0.3s; }
        .form-control-ziip:focus { border-color: #1e40af; outline: none; box-shadow: 0 0 0 4px rgba(30, 64, 175, 0.1); }
        .btn-emissao { background: #1e40af; color: #fff; border: none; padding: 15px; border-radius: 10px; width: 100%; font-size: 18px; font-weight: 600; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 20px; }
        .btn-emissao:hover { background: #1e3a8a; transform: translateY(-2px); }
        .alert-ziip { padding: 20px; border-radius: 10px; margin-bottom: 25px; display: flex; gap: 15px; align-items: flex-start; }
        .alert-ziip-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-ziip-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    </style>
</head>
<body>
    <?php $path_prefix = '../../'; ?>
    <aside class="sidebar-container">
        <?php include '../../menu/menulateral.php'; ?>
    </aside>
    <header class="top-header">
        <?php include '../../menu/faixa.php'; ?>
    </header>

    <main class="main-content">
        <div class="card-emissao">
            <div class="card-header-ziip">
                <i class="fas fa-file-invoice"></i>
                <h2 style="margin:0">Módulo de Emissão NFS-e</h2>
            </div>
            
            <div class="card-body-ziip">
                <?php if ($msg_erro): ?>
                    <div class="alert-ziip alert-ziip-danger">
                        <i class="fas fa-exclamation-circle" style="font-size:24px"></i>
                        <div>
                            <strong>Erro na Operação:</strong><br>
                            <?= $msg_erro ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($resultado && $resultado['success']): ?>
                    <div class="alert-ziip alert-ziip-success">
                        <i class="fas fa-check-circle" style="font-size:24px"></i>
                        <div>
                            <strong>Sucesso!</strong><br>
                            <?= $resultado['message'] ?><br>
                            <small>Protocolo: <?= $resultado['protocolo'] ?></small>
                        </div>
                    </div>
                    <div style="text-align:center; margin-top:20px;">
                        <a href="lista_nfse.php" class="btn-emissao" style="text-decoration:none">
                            <i class="fas fa-list"></i> Voltar para Lista
                        </a>
                    </div>
                <?php else: ?>
                    <p style="color: #6b7280; text-align:center; margin-bottom:30px;">
                        Insira o ID da venda que contém os serviços para gerar a Nota Fiscal de Serviço Eletrônica.
                    </p>

                    <form action="emitir_nfse.php" method="GET">
                        <div class="form-group">
                            <label class="form-label">ID da Venda (Controle Interno)</label>
                            <input type="text" name="id_venda" class="form-control-ziip" placeholder="Ex: 8542" value="<?= htmlspecialchars($id_venda ?: '') ?>" required autofocus>
                        </div>
                        
                        <button type="submit" class="btn-emissao">
                            <i class="fas fa-rocket"></i> Processar e Emitir NFS-e
                        </button>
                    </form>
                    
                    <div style="text-align:center; margin-top:25px;">
                        <a href="lista_nfse.php" style="color:#1e40af; text-decoration:none; font-weight:500;">
                            <i class="fas fa-arrow-left"></i> Cancelar e voltar
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
