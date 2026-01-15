<?php
/**
 * Localização: app/financeiro/importar_compras.php
 * CSV com estrutura: Código;Entrada;Fornecedor;NF;Emissão NF;Valor;...
 * ATUALIZAÇÃO: Agora com filtro de período para evitar importar dados antigos
 */

$base_app = dirname(__DIR__); 
require_once $base_app . '/auth.php';
require_once $base_app . '/config/configuracoes.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$id_admin = $_SESSION['id_admin'] ?? 1;
$mensagem = "";

// Função para buscar ou criar fornecedor
function buscarOuCriarFornecedor($pdo, $nome_fornecedor, $id_admin) {
    $nome_limpo = trim($nome_fornecedor);
    
    if (empty($nome_limpo)) {
        return 0;
    }
    
    try {
        // Buscar fornecedor
        $query = "SELECT id FROM fornecedores 
                  WHERE id_admin = :id_admin 
                  AND (
                      nome_fantasia LIKE :nome 
                      OR razao_social LIKE :nome 
                      OR nome_completo LIKE :nome
                  )
                  LIMIT 1";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':id_admin' => $id_admin,
            ':nome' => "%$nome_limpo%"
        ]);
        
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($resultado) {
            return (int)$resultado['id'];
        }
        
        // Criar novo fornecedor
        $query_insert = "INSERT INTO fornecedores (
                            id_admin, 
                            status, 
                            tipo_fornecedor, 
                            tipo_pessoa, 
                            razao_social, 
                            nome_fantasia,
                            data_cadastro
                         ) VALUES (
                            :id_admin, 
                            'ATIVO', 
                            'Produtos e/ou serviços', 
                            'Juridica', 
                            :razao_social, 
                            :nome_fantasia,
                            NOW()
                         )";
        
        $stmt_insert = $pdo->prepare($query_insert);
        $stmt_insert->execute([
            ':id_admin' => $id_admin,
            ':razao_social' => $nome_limpo,
            ':nome_fantasia' => $nome_limpo
        ]);
        
        return (int)$pdo->lastInsertId();
        
    } catch (Exception $e) {
        error_log("Erro ao buscar/criar fornecedor '$nome_limpo': " . $e->getMessage());
        return 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $arquivo_tmp = $_FILES['csv_file']['tmp_name'];
    
    // Capturar filtros de período
    $data_inicio_filtro = $_POST['data_inicio'] ?? null;
    $data_fim_filtro = $_POST['data_fim'] ?? null;

    if (file_exists($arquivo_tmp)) {
        try {
            // Converter de ISO-8859-1 para UTF-8
            $conteudo = file_get_contents($arquivo_tmp);
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'ISO-8859-1');
            $stream = fopen('php://memory', 'r+');
            fwrite($stream, $conteudo);
            rewind($stream);

            $importados = 0;
            $ignorados = 0;
            $linha_cont = 0;
            $fornecedores_criados = [];
            $erros = [];

            while (($dados = fgetcsv($stream, 3000, ";")) !== FALSE) {
                $linha_cont++;
                
                // Pular cabeçalho
                if ($linha_cont == 1) continue;
                
                // Pular linhas vazias
                if (empty($dados[0])) continue;

                try {
                    // MAPEAMENTO CORRETO DO CSV:
                    // [0] = Código
                    // [1] = Entrada (data da compra)
                    // [2] = Fornecedor
                    // [3] = NF
                    // [4] = Emissão NF
                    // [5] = Valor (formato: 1.430,20)
                    // [13] = Forma de Pagamento
                    // [14] = Conta de Pagamento
                    
                    $codigo = trim($dados[0]);
                    $data_entrada_raw = trim($dados[1]);
                    $nome_fornecedor = trim($dados[2]);
                    $nf_numero = trim($dados[3]);
                    $data_emissao_raw = trim($dados[4]);
                    $valor_raw = trim($dados[5]);
                    $forma_pgto = trim($dados[13] ?? '');
                    $conta_pgto = trim($dados[14] ?? '');
                    
                    // Validar campos obrigatórios
                    if (empty($data_entrada_raw) || empty($nome_fornecedor)) {
                        $erros[] = "Linha $linha_cont: dados incompletos";
                        $ignorados++;
                        continue;
                    }
                    
                    // Converter data de entrada
                    $date_entrada = DateTime::createFromFormat('d/m/Y', $data_entrada_raw);
                    if (!$date_entrada) {
                        $erros[] = "Linha $linha_cont: data de entrada inválida";
                        $ignorados++;
                        continue;
                    }
                    
                    $data_entrada_sql = $date_entrada->format('Y-m-d H:i:s');
                    $data_entrada_comparacao = $date_entrada->format('Y-m-d');
                    
                    // ===== APLICAR FILTRO DE PERÍODO =====
                    if (!empty($data_inicio_filtro) && $data_entrada_comparacao < $data_inicio_filtro) {
                        $ignorados++;
                        continue; // Data anterior ao período desejado
                    }
                    
                    if (!empty($data_fim_filtro) && $data_entrada_comparacao > $data_fim_filtro) {
                        $ignorados++;
                        continue; // Data posterior ao período desejado
                    }
                    // =====================================
                    
                    // Converter data de emissão
                    $data_emissao_sql = null;
                    if (!empty($data_emissao_raw)) {
                        $date_emissao = DateTime::createFromFormat('d/m/Y', $data_emissao_raw);
                        $data_emissao_sql = $date_emissao ? $date_emissao->format('Y-m-d') : null;
                    }
                    
                    // Converter valor (1.430,20 -> 1430.20)
                    $valor = 0.00;
                    if (!empty($valor_raw)) {
                        $valor = (float)str_replace(['.', ','], ['', '.'], $valor_raw);
                    }
                    
                    // Buscar ou criar fornecedor
                    $id_fornecedor = buscarOuCriarFornecedor($pdo, $nome_fornecedor, $id_admin);
                    
                    // Registrar fornecedor criado
                    if ($id_fornecedor > 0 && !isset($fornecedores_criados[$id_fornecedor])) {
                        $stmt_check = $pdo->prepare("SELECT id FROM fornecedores WHERE id = ? AND DATE(data_cadastro) = CURDATE()");
                        $stmt_check->execute([$id_fornecedor]);
                        if ($stmt_check->rowCount() > 0) {
                            $fornecedores_criados[$id_fornecedor] = $nome_fornecedor;
                        }
                    }
                    
                    // Buscar forma de pagamento
                    $id_forma_pgto = null;
                    if (!empty($forma_pgto)) {
                        $stmt_forma = $pdo->prepare("SELECT id FROM formas_pagamento WHERE nome_forma LIKE ? LIMIT 1");
                        $stmt_forma->execute(["%$forma_pgto%"]);
                        $forma_result = $stmt_forma->fetch(PDO::FETCH_ASSOC);
                        $id_forma_pgto = $forma_result ? $forma_result['id'] : null;
                    }
                    
                    // Buscar conta financeira
                    $id_conta_financeira = null;
                    if (!empty($conta_pgto)) {
                        $stmt_conta = $pdo->prepare("SELECT id FROM contas_financeiras WHERE nome_conta LIKE ? LIMIT 1");
                        $stmt_conta->execute(["%$conta_pgto%"]);
                        $conta_result = $stmt_conta->fetch(PDO::FETCH_ASSOC);
                        $id_conta_financeira = $conta_result ? $conta_result['id'] : null;
                    }
                    
                    // Verificar se já existe (evitar duplicatas)
                    $stmt_existe = $pdo->prepare("SELECT id FROM compras WHERE id_admin = ? AND id_fornecedor = ? AND nf_numero = ? AND DATE(data_cadastro) = ?");
                    $stmt_existe->execute([$id_admin, $id_fornecedor, $nf_numero, $data_entrada_comparacao]);
                    
                    if ($stmt_existe->rowCount() > 0) {
                        $ignorados++;
                        continue; // Já existe, não importar duplicata
                    }
                    
                    // ===== INICIAR TRANSAÇÃO =====
                    $pdo->beginTransaction();
                    
                    try {
                        // Inserir compra
                        $stmt = $pdo->prepare("INSERT INTO compras (
                                                id_admin, 
                                                id_fornecedor, 
                                                valor_total, 
                                                nf_numero, 
                                                data_emissao,
                                                data_cadastro, 
                                                status_pagamento,
                                                id_forma_pagamento,
                                                id_conta_financeira
                                              ) VALUES (?, ?, ?, ?, ?, ?, 'PENDENTE', ?, ?)");
                        
                        $stmt->execute([
                            $id_admin, 
                            $id_fornecedor, 
                            $valor, 
                            $nf_numero,
                            $data_emissao_sql,
                            $data_entrada_sql,
                            $id_forma_pgto,
                            $id_conta_financeira
                        ]);
                        
                        $id_compra = $pdo->lastInsertId();
                        
                        // ===== CRIAR CONTA A PAGAR (IGUAL AO compras.php) =====
                        // Buscar CNPJ do fornecedor
                        $stmt_cnpj = $pdo->prepare("SELECT cnpj FROM fornecedores WHERE id = ?");
                        $stmt_cnpj->execute([$id_fornecedor]);
                        $fornecedor_cnpj = $stmt_cnpj->fetchColumn() ?: '';
                        
                        // Inserir na tabela contas
                        $stmt_conta = $pdo->prepare("INSERT INTO contas (
                                                        id_admin, 
                                                        natureza, 
                                                        categoria, 
                                                        id_entidade, 
                                                        entidade_tipo, 
                                                        doc_entidade, 
                                                        descricao, 
                                                        documento, 
                                                        serie, 
                                                        competencia, 
                                                        vencimento, 
                                                        valor_total, 
                                                        valor_parcela, 
                                                        qtd_parcelas, 
                                                        status_baixa, 
                                                        data_cadastro
                                                      ) VALUES (?, 'Despesa', '1', ?, 'fornecedor', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDENTE', NOW())");
                        
                        // Descrição da conta
                        $descricao_conta = "COMPRA NF {$nf_numero}";
                        
                        // Vencimento: se não tiver data de emissão, usa data de entrada + 30 dias
                        $data_vencimento = $data_emissao_sql ? $data_emissao_sql : date('Y-m-d', strtotime($data_entrada_comparacao . ' +30 days'));
                        
                        $stmt_conta->execute([
                            $id_admin,
                            $id_fornecedor,
                            $fornecedor_cnpj,
                            $descricao_conta,
                            $nf_numero,
                            '', // série (não vem no CSV)
                            $data_entrada_comparacao, // competência
                            $data_vencimento, // vencimento
                            $valor, // valor total
                            $valor, // valor parcela (igual ao total, pois é parcela única)
                            1 // qtd_parcelas = 1
                        ]);
                        
                        // Commit da transação
                        $pdo->commit();
                        
                        $importados++;
                        
                    } catch (Exception $e_transacao) {
                        $pdo->rollBack();
                        throw $e_transacao;
                    }
                    
                } catch (Exception $e) {
                    $erros[] = "Linha $linha_cont: " . $e->getMessage();
                    $ignorados++;
                }
            }
            
            fclose($stream);
            
            // Mensagem de sucesso
            $msg_fornecedores = "";
            $total_fornecedores = count($fornecedores_criados);
            if ($total_fornecedores > 0) {
                $msg_fornecedores = "<br><i class='fas fa-user-plus'></i> <strong>$total_fornecedores</strong> novos fornecedores criados.";
            }
            
            $msg_ignorados = "";
            if ($ignorados > 0) {
                $msg_ignorados = "<br><i class='fas fa-filter' style='color:#f39c12;'></i> <strong>$ignorados</strong> registros ignorados (fora do período ou duplicados).";
            }
            
            $msg_erros = "";
            if (count($erros) > 0) {
                $msg_erros = "<br><i class='fas fa-exclamation-triangle' style='color:#dd4b39;'></i> <strong>" . count($erros) . "</strong> linhas com erro.";
            }
            
            $mensagem = "<div class='alert success'>
                            <i class='fas fa-check-circle'></i> 
                            <b>Importação Concluída!</b><br> 
                            ✓ <strong>$importados</strong> compras importadas com sucesso.$msg_fornecedores$msg_ignorados$msg_erros
                         </div>";
                         
        } catch (Exception $e) {
            $mensagem = "<div class='alert error'><i class='fas fa-exclamation-triangle'></i> <b>Erro:</b> " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        $mensagem = "<div class='alert error'><i class='fas fa-times-circle'></i> Arquivo não enviado corretamente.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Compras</title>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Source Sans Pro', sans-serif; 
            padding: 20px; 
            background: #fff; 
            font-size: 16px; 
            color: #333; 
        }
        .form-group { margin-bottom: 20px; }
        
        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            font-size: 15px;
            color: #555;
        }
        
        input[type="file"], input[type="date"] { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid #d2d6de; 
            border-radius: 4px; 
            box-sizing: border-box; 
            font-size: 15px;
            font-family: inherit;
        }
        
        input[type="file"] {
            background: #f9f9f9; 
            cursor: pointer;
        }
        
        .date-range-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }
        
        .date-range-container .form-group {
            margin-bottom: 0;
        }
        
        .date-range-title {
            grid-column: 1 / -1;
            font-size: 14px;
            font-weight: 700;
            color: #337ab7;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-submit { 
            background: #00a65a; 
            color: white; 
            border: none; 
            width: 100%; 
            padding: 15px; 
            border-radius: 4px; 
            cursor: pointer; 
            font-weight: 700; 
            font-size: 16px; 
            text-transform: uppercase; 
            margin-top: 10px;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-submit:hover {
            background: #008d4c;
        }
        
        .btn-submit:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .alert { 
            padding: 15px; 
            border-radius: 4px; 
            margin-bottom: 20px; 
            font-size: 15px; 
            border: 1px solid transparent;
            line-height: 1.6;
        }
        
        .success { 
            background: #dff0d8; 
            color: #3c763d; 
            border-color: #d6e9c6; 
        }
        
        .error { 
            background: #f2dede; 
            color: #a94442; 
            border-color: #ebccd1; 
        }
        
        .info { 
            font-size: 14px; 
            color: #666; 
            background: #e7f3ff; 
            padding: 15px; 
            border-radius: 4px; 
            margin-top: 20px; 
            border-left: 4px solid #337ab7;
            line-height: 1.6;
        }
        
        .info ul {
            margin: 10px 0 0 20px;
        }
        
        .info li {
            margin: 5px 0;
        }
        
        .preset-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .btn-preset {
            padding: 8px 15px;
            border: 1px solid #337ab7;
            background: #fff;
            color: #337ab7;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s;
        }
        
        .btn-preset:hover {
            background: #337ab7;
            color: #fff;
        }
    </style>
</head>
<body>
    <?= $mensagem ?>
    
    <form action="" method="POST" enctype="multipart/form-data" id="formImport">
        
        <div class="form-group">
            <label><i class="fas fa-calendar-alt"></i> Filtro de Período (Opcional)</label>
            
            <div class="preset-buttons">
                <button type="button" class="btn-preset" onclick="setPreset('mes_atual')">
                    <i class="fas fa-calendar-day"></i> Mês Atual
                </button>
                <button type="button" class="btn-preset" onclick="setPreset('ultimos_30')">
                    <i class="fas fa-calendar-week"></i> Últimos 30 dias
                </button>
                <button type="button" class="btn-preset" onclick="setPreset('ultimos_60')">
                    <i class="fas fa-calendar"></i> Últimos 60 dias
                </button>
                <button type="button" class="btn-preset" onclick="limparFiltro()">
                    <i class="fas fa-times"></i> Sem filtro
                </button>
            </div>
            
            <div class="date-range-container">
                <div class="date-range-title">
                    <i class="fas fa-filter"></i> 
                    Importar apenas compras neste período:
                </div>
                
                <div class="form-group">
                    <label>Data Inicial</label>
                    <input type="date" name="data_inicio" id="data_inicio" value="<?= date('Y-m-01') ?>">
                </div>
                
                <div class="form-group">
                    <label>Data Final</label>
                    <input type="date" name="data_fim" id="data_fim" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
        </div>
        
        <div class="form-group">
            <label><i class="fas fa-file-csv"></i> Arquivo CSV de Compras (Relatório_de_Compras.csv):</label>
            <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
        </div>
        
        <button type="submit" class="btn-submit" id="btnSubmit">
            <i class="fas fa-file-import"></i> PROCESSAR IMPORTAÇÃO
        </button>
    </form>
    
    <div class="info">
        <i class="fas fa-info-circle"></i> <strong>Como funciona a importação:</strong>
        <ul>
            <li><i class="fas fa-check" style="color:#00a65a;"></i> <strong>Filtro de Período:</strong> Define o intervalo de datas das compras que serão importadas</li>
            <li><i class="fas fa-check" style="color:#00a65a;"></i> Sistema busca fornecedores existentes pelo nome</li>
            <li><i class="fas fa-check" style="color:#00a65a;"></i> Se não encontrar, cria automaticamente</li>
            <li><i class="fas fa-check" style="color:#00a65a;"></i> Vincula formas de pagamento e contas financeiras</li>
            <li><i class="fas fa-check" style="color:#00a65a;"></i> <strong>Evita duplicatas:</strong> não importa compras já cadastradas</li>
            <li><i class="fas fa-check" style="color:#00a65a;"></i> Converte valores e datas automaticamente</li>
        </ul>
    </div>
    
    <script>
        // Presets de período
        function setPreset(tipo) {
            const hoje = new Date();
            let dataInicio, dataFim;
            
            switch(tipo) {
                case 'mes_atual':
                    dataInicio = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
                    dataFim = hoje;
                    break;
                    
                case 'ultimos_30':
                    dataInicio = new Date(hoje.getTime() - (30 * 24 * 60 * 60 * 1000));
                    dataFim = hoje;
                    break;
                    
                case 'ultimos_60':
                    dataInicio = new Date(hoje.getTime() - (60 * 24 * 60 * 60 * 1000));
                    dataFim = hoje;
                    break;
            }
            
            document.getElementById('data_inicio').value = formatDate(dataInicio);
            document.getElementById('data_fim').value = formatDate(dataFim);
        }
        
        function limparFiltro() {
            document.getElementById('data_inicio').value = '';
            document.getElementById('data_fim').value = '';
        }
        
        function formatDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
        
        // Validação antes de enviar
        document.getElementById('formImport').addEventListener('submit', function(e) {
            const dataInicio = document.getElementById('data_inicio').value;
            const dataFim = document.getElementById('data_fim').value;
            
            if (dataInicio && dataFim) {
                if (dataInicio > dataFim) {
                    e.preventDefault();
                    alert('⚠️ Atenção: A data inicial não pode ser maior que a data final!');
                    return false;
                }
            }
            
            // Desabilitar botão enquanto processa
            const btn = document.getElementById('btnSubmit');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> PROCESSANDO...';
        });
    </script>
</body>
</html>