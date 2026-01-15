<?php
/**
 * =========================================================================================
 * ZIIPVET - GESTÃO DE SALDO DE CLIENTES
 * ARQUIVO: saldo-clientes.php
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
// FUNÇÃO AUXILIAR PARA LIMPAR VALORES MONETÁRIOS
// ==========================================================
function limparValorMonetario($valor) {
    $valor = trim($valor);
    if (empty($valor)) return 0.00;
    $valor = str_replace('.', '', $valor); // Remove ponto de milhar
    $valor = str_replace(',', '.', $valor); // Troca vírgula por ponto decimal
    return (float)$valor;
}

// ==========================================================
// LÓGICA DE IMPORTAÇÃO CSV
// ==========================================================
if (isset($_FILES['csv_file'])) {
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        $file = $_FILES['csv_file']['tmp_name'];
        
        // Detectar e converter encoding se necessário
        $conteudo = file_get_contents($file);
        if (!mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = utf8_encode($conteudo);
        }
        
        $linhas = explode("\n", str_replace("\r", "", $conteudo));
        $sucesso = 0;
        $nao_encontrados = [];

        foreach ($linhas as $index => $linha) {
            if ($index === 0 || empty(trim($linha))) continue; // Pula cabeçalho e linhas vazias

            $data = str_getcsv($linha, ";");
            if (count($data) < 6) continue;

            $nome = trim($data[0]);
            $vendas_aberto = limparValorMonetario($data[3]);
            $creditos      = limparValorMonetario($data[4]);
            $saldo         = limparValorMonetario($data[5]);
            $situacao      = trim($data[6]);
            
            // Tratar data (18/12/2025 -> 2025-12-18)
            $data_compra = null;
            if (!empty(trim($data[2]))) {
                $p = explode('/', trim($data[2]));
                if (count($p) == 3) $data_compra = "{$p[2]}-{$p[1]}-{$p[0]}";
            }

            // UPDATE utilizando TRIM para garantir o match do nome
            $stmt = $pdo->prepare("UPDATE clientes SET 
                ultima_compra = :dt, 
                vendas_aberto = :venda, 
                creditos_disponiveis = :cred, 
                saldo_atual = :saldo, 
                situacao_financeira = :sit 
                WHERE TRIM(nome) = :nome AND id_admin = :id_admin");
            
            $stmt->execute([
                ':dt'       => $data_compra,
                ':venda'    => $vendas_aberto,
                ':cred'     => $creditos,
                ':saldo'    => $saldo,
                ':sit'      => $situacao,
                ':nome'     => $nome,
                ':id_admin' => $id_admin
            ]);

            if ($stmt->rowCount() > 0) {
                $sucesso++;
            } else {
                $nao_encontrados[] = $nome;
            }
        }

        echo json_encode([
            'status' => 'success', 
            'message' => "$sucesso registros atualizados!",
            'detalhes' => count($nao_encontrados) > 0 ? "Clientes não localizados: " . implode(', ', $nao_encontrados) : ""
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// CONFIGURAÇÃO DA PÁGINA
// ==========================================================
$titulo_pagina = "Saldo de Clientes";

$busca = $_GET['busca'] ?? '';
$filtro_status = $_GET['situacao'] ?? '';

$pagina_atual = (isset($_GET['pagina']) && is_numeric($_GET['pagina'])) ? (int)$_GET['pagina'] : 1;
$itens_per_page = 20;
$offset = ($pagina_atual - 1) * $itens_per_page;

try {
    // Construir WHERE
    $where = "WHERE id_admin = :id_admin";
    $params = [':id_admin' => $id_admin];
    
    if (!empty($busca)) {
        $where .= " AND (nome LIKE :busca OR cpf_cnpj LIKE :busca)";
        $params[':busca'] = "%$busca%";
    }
    
    if (!empty($filtro_status)) {
        $where .= " AND situacao_financeira = :sit";
        $params[':sit'] = $filtro_status;
    }
    
    // Contagem total
    $sqlCount = "SELECT COUNT(*) as total FROM clientes $where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total_registros = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    $total_paginas = ceil($total_registros / $itens_per_page);

    // Listagem
    $sql = "SELECT * FROM clientes 
            $where
            ORDER BY nome ASC 
            LIMIT " . (int)$offset . ", " . (int)$itens_per_page;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao buscar clientes: " . $e->getMessage());
}

function gerar_link($pg) {
    global $busca, $filtro_status;
    return "saldo-clientes.php?pagina=$pg&busca=" . urlencode($busca) . "&situacao=" . urlencode($filtro_status);
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
           ESTILOS ESPECÍFICOS DO SALDO
        ======================================== */
        
        /* Container de Listagem */
        .list-container {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }
        
        /* Área de Filtros */
        .filters-box {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 15px;
            align-items: flex-end;
        }
        
        .filter-group {
            position: relative;
        }
        
        .filter-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #6c757d;
            margin-bottom: 8px;
            text-transform: uppercase;
            font-family: 'Exo', sans-serif;
        }
        
        .filter-group label i {
            margin-right: 5px;
        }
        
        .btn-filter {
            height: 45px;
            padding: 0 24px;
            background: #131c71;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            font-family: 'Exo', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-filter:hover {
            background: #4a1d75;
            transform: translateY(-2px);
        }
        
        /* Botão de Importar */
        .btn-import {
            background: linear-gradient(135deg, #28A745 0%, #28A745 100%);
            color: #fff;
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            font-family: 'Exo', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-import:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(111, 66, 193, 0.3);
        }
        
        /* Wrapper da Tabela */
        .table-wrapper {
            overflow-x: auto;
        }
        
        /* Tabela Moderna */
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
        
        /* Nome do Cliente */
        .cliente-name {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        /* Valores Monetários */
        .valor-positivo {
            color: #28a745;
            font-weight: 700;
            font-size: 16px;
        }
        
        .valor-negativo {
            color: #dc3545;
            font-weight: 700;
            font-size: 16px;
        }
        
        .valor-neutro {
            color: #6c757d;
            font-weight: 700;
            font-size: 16px;
        }
        
        /* Badge de Situação */
        .badge-situacao {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            font-family: 'Exo', sans-serif;
        }
        
        .badge-devedor {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            color: #c62828;
        }
        
        .badge-credor {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            color: #2e7d32;
        }
        
        .badge-neutro {
            background: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%);
            color: #616161;
        }
        
        .badge-situacao i {
            font-size: 11px;
        }
        
        /* Paginação */
        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #f8f9fa;
            border-top: 2px solid #e0e0e0;
        }
        
        .page-info {
            font-size: 14px;
            color: #6c757d;
            font-weight: 600;
            font-family: 'Exo', sans-serif;
        }
        
        .page-nav {
            display: flex;
            gap: 8px;
        }
        
        .page-link {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            color: #131c71;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            font-family: 'Exo', sans-serif;
        }
        
        .page-link:hover:not(.disabled) {
            background: #131c71;
            color: #fff;
            border-color: #131c71;
            transform: translateY(-2px);
        }
        
        .page-link.disabled {
            opacity: 0.4;
            cursor: not-allowed;
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
        @media (max-width: 1200px) {
            .filters-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .btn-filter {
                grid-column: 1 / -1;
            }
        }
        
        @media (max-width: 768px) {
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 13px;
            }
            
            thead th, tbody td {
                padding: 10px 8px;
            }
        }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include 'menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include 'menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <!-- HEADER: Título e Botão de Importar -->
        <div class="form-header">
            <h1 class="form-title">
                <i class="fas fa-wallet"></i>
                Saldo de Clientes
                <span style="font-size: 14px; color: #6c757d; font-weight: 400; margin-left: 10px;">
                    (<?= $total_registros ?> <?= $total_registros == 1 ? 'cliente' : 'clientes' ?>)
                </span>
            </h1>
            
            <div style="display: flex; gap: 10px;">
                <input type="file" id="upload_csv" style="display:none" accept=".csv" onchange="importarCSV(this)">
                <button class="btn-import" onclick="document.getElementById('upload_csv').click()">
                    <i class="fas fa-file-import"></i>
                    Importar CSV
                </button>
            </div>
        </div>

        <!-- CONTAINER DA LISTAGEM -->
        <div class="list-container">
            
            <!-- FILTROS -->
            <div class="filters-box">
                <form method="GET" class="filters-grid">
                    <div class="filter-group">
                        <label>
                            <i class="fas fa-search"></i>
                            Pesquisar Cliente
                        </label>
                        <input type="text" 
                               name="busca" 
                               class="form-control" 
                               placeholder="Nome ou CPF/CNPJ..."
                               value="<?= htmlspecialchars($busca) ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>
                            <i class="fas fa-filter"></i>
                            Situação Financeira
                        </label>
                        <select name="situacao" class="form-control">
                            <option value="">Todas as situações</option>
                            <option value="Saldo devedor" <?= $filtro_status == 'Saldo devedor' ? 'selected' : '' ?>>Saldo Devedor</option>
                            <option value="Saldo credor" <?= $filtro_status == 'Saldo credor' ? 'selected' : '' ?>>Saldo Credor</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-search"></i>
                        Filtrar
                    </button>
                </form>
            </div>

            <!-- TABELA -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-user"></i> Cliente</th>
                            <th width="130"><i class="fas fa-calendar"></i> Última Compra</th>
                            <th width="150"><i class="fas fa-receipt"></i> Vendas em Aberto</th>
                            <th width="130"><i class="fas fa-coins"></i> Créditos</th>
                            <th width="130"><i class="fas fa-dollar-sign"></i> Saldo</th>
                            <th width="150"><i class="fas fa-info-circle"></i> Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($clientes) > 0): ?>
                            <?php foreach($clientes as $c): 
                                $saldo = (float)$c['saldo_atual'];
                                
                                // Definir classe do saldo
                                if ($saldo < 0) {
                                    $classe_saldo = 'valor-negativo';
                                } elseif ($saldo > 0) {
                                    $classe_saldo = 'valor-positivo';
                                } else {
                                    $classe_saldo = 'valor-neutro';
                                }
                                
                                // Definir badge da situação
                                if (strpos(strtolower($c['situacao_financeira']), 'devedor') !== false) {
                                    $classe_badge = 'badge-devedor';
                                    $icone_badge = 'fa-exclamation-triangle';
                                } elseif (strpos(strtolower($c['situacao_financeira']), 'credor') !== false) {
                                    $classe_badge = 'badge-credor';
                                    $icone_badge = 'fa-check-circle';
                                } else {
                                    $classe_badge = 'badge-neutro';
                                    $icone_badge = 'fa-minus-circle';
                                }
                            ?>
                            <tr>
                                <td>
                                    <div class="cliente-name">
                                        <?= htmlspecialchars($c['nome']) ?>
                                    </div>
                                </td>
                                <td>
                                    <?= $c['ultima_compra'] ? date('d/m/Y', strtotime($c['ultima_compra'])) : '<span style="color: #adb5bd;">--</span>' ?>
                                </td>
                                <td>
                                    R$ <?= number_format($c['vendas_aberto'], 2, ',', '.') ?>
                                </td>
                                <td style="color: #28a745; font-weight: 600;">
                                    R$ <?= number_format($c['creditos_disponiveis'], 2, ',', '.') ?>
                                </td>
                                <td>
                                    <span class="<?= $classe_saldo ?>">
                                        R$ <?= number_format($saldo, 2, ',', '.') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-situacao <?= $classe_badge ?>">
                                        <i class="fas <?= $icone_badge ?>"></i>
                                        <?= htmlspecialchars($c['situacao_financeira']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <h3>Nenhum cliente encontrado</h3>
                                        <p>Não há clientes que correspondam aos filtros aplicados.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINAÇÃO -->
            <?php if($total_paginas > 1): ?>
            <div class="pagination-wrapper">
                <div class="page-info">
                    Mostrando <?= count($clientes) ?> de <?= $total_registros ?> clientes
                </div>
                <div class="page-nav">
                    <a href="<?= gerar_link(max(1, $pagina_atual-1)) ?>" 
                       class="page-link <?= $pagina_atual == 1 ? 'disabled' : '' ?>"
                       title="Página Anterior">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    
                    <a href="<?= gerar_link(min($total_paginas, $pagina_atual+1)) ?>" 
                       class="page-link <?= $pagina_atual == $total_paginas ? 'disabled' : '' ?>"
                       title="Próxima Página">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // ==========================================================
        // IMPORTAÇÃO DE CSV
        // ==========================================================
        function importarCSV(input) {
            if (!input.files[0]) return;
            
            const arquivo = input.files[0];
            
            // Validar tipo de arquivo
            if (!arquivo.name.toLowerCase().endsWith('.csv')) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Arquivo Inválido',
                    text: 'Por favor, selecione um arquivo CSV válido.',
                    confirmButtonColor: '#131c71'
                });
                input.value = '';
                return;
            }
            
            let formData = new FormData();
            formData.append('csv_file', arquivo);

            Swal.fire({
                title: 'Importando...',
                html: 'Processando saldos e verificando clientes.<br>Por favor, aguarde.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('saldo-clientes.php', { 
                method: 'POST', 
                body: formData 
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    let htmlMsg = `<strong>${data.message}</strong>`;
                    
                    if(data.detalhes) {
                        htmlMsg += `<br><br><div style="text-align: left; background: #fff3cd; padding: 15px; border-radius: 8px; margin-top: 15px;">
                            <strong style="color: #856404;">⚠️ Atenção:</strong><br>
                            <span style="color: #856404; font-size: 14px;">${data.detalhes}</span>
                        </div>`;
                    }
                    
                    Swal.fire({
                        title: 'Importação Concluída!',
                        html: htmlMsg,
                        icon: 'success',
                        confirmButtonColor: '#131c71',
                        confirmButtonText: 'OK'
                    }).then(() => location.reload());
                } else {
                    Swal.fire({
                        title: 'Erro na Importação',
                        text: data.message,
                        icon: 'error',
                        confirmButtonColor: '#131c71'
                    });
                }
                
                input.value = '';
            })
            .catch(error => {
                Swal.fire({
                    title: 'Erro de Conexão',
                    text: 'Não foi possível processar o arquivo.',
                    icon: 'error',
                    confirmButtonColor: '#131c71'
                });
                input.value = '';
            });
        }
    </script>
</body>
</html>