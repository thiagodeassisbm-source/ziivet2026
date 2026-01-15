<?php
/**
 * Localização: app/financeiro/importar_contas.php
 * Finalidade: Importação de CSV com vinculação REAL de fornecedores.
 * 
 * CORREÇÕES IMPLEMENTADAS:
 * - Busca fornecedor pelo nome no banco
 * - Se não existir, cria automaticamente
 * - Vincula o ID correto na tabela contas
 * - Categoria usa VARCHAR (nome), não INT (id)
 */

// 1. LÓGICA DE CAMINHOS
$base_app = dirname(__DIR__); 

$path_auth = $base_app . '/auth.php';
$path_config = $base_app . '/config/configuracoes.php';

if (!file_exists($path_auth)) {
    die("Erro Crítico: auth.php não encontrado em: " . $path_auth);
}

require_once $path_auth;
require_once $path_config;

// Ativação de erros para monitorar a importação
ini_set('display_errors', 1);
error_reporting(E_ALL);

$mensagem = "";
$id_admin = $_SESSION['id_admin'] ?? 1;

/**
 * Função auxiliar: Busca ou cria fornecedor
 * @return int ID do fornecedor
 */
function buscarOuCriarFornecedor($pdo, $nome_fornecedor, $id_admin) {
    $nome_limpo = trim($nome_fornecedor);
    
    if (empty($nome_limpo)) {
        return 0; // Sem fornecedor
    }
    
    try {
        // 1. TENTAR BUSCAR por nome_fantasia, razao_social ou nome_completo
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
            return (int)$resultado['id']; // Fornecedor encontrado
        }
        
        // 2. SE NÃO ENCONTROU, CRIAR NOVO FORNECEDOR
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

// 2. PROCESSAMENTO DO CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $data_corte = $_POST['data_inicio_importacao'];
    $arquivo_tmp = $_FILES['csv_file']['tmp_name'];

    if (file_exists($arquivo_tmp)) {
        try {
            // Trata codificação ISO (Excel) para UTF-8
            $conteudo = file_get_contents($arquivo_tmp);
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'ISO-8859-1');
            $stream = fopen('php://memory', 'r+');
            fwrite($stream, $conteudo);
            rewind($stream);

            $importados = 0;
            $pulados = 0;
            $linha_cont = 0;
            $fornecedores_criados = [];
            $erros_log = [];

            while (($dados = fgetcsv($stream, 2000, ";")) !== FALSE) {
                $linha_cont++;
                if ($linha_cont == 1) continue; // Pula cabeçalho

                try {
                    // Mapeamento do CSV:
                    // [0] = ID
                    // [1] = Natureza
                    // [2] = Categoria
                    // [3] = Descrição
                    // [4] = Fornecedor
                    // [7] = Valor
                    // [11] = Vencimento
                    // [15] = Documento
                    
                    $data_venc_raw = trim($dados[11] ?? '');
                    if (empty($data_venc_raw)) {
                        $pulados++;
                        continue;
                    }

                    // Converte data brasileira para MySQL
                    $date_obj = DateTime::createFromFormat('d/m/Y', $data_venc_raw);
                    $vencimento_sql = $date_obj ? $date_obj->format('Y-m-d') : null;

                    if (!$vencimento_sql || $vencimento_sql < $data_corte) {
                        $pulados++;
                        continue;
                    }
                    
                    // --- BUSCAR OU CRIAR FORNECEDOR ---
                    $nome_fornecedor = trim($dados[4] ?? '');
                    $id_fornecedor = buscarOuCriarFornecedor($pdo, $nome_fornecedor, $id_admin);
                    
                    // Registra fornecedor criado (sem duplicar)
                    if ($id_fornecedor > 0 && !empty($nome_fornecedor)) {
                        if (!isset($fornecedores_criados[$id_fornecedor])) {
                            $stmt_check = $pdo->prepare("SELECT id FROM fornecedores WHERE id = ? AND DATE(data_cadastro) = CURDATE()");
                            $stmt_check->execute([$id_fornecedor]);
                            if ($stmt_check->rowCount() > 0) {
                                $fornecedores_criados[$id_fornecedor] = $nome_fornecedor;
                            }
                        }
                    }
                    
                    // --- PADRONIZAÇÃO DA DESCRIÇÃO ---
                    $desc_original = trim($dados[3] ?? '');
                    
                    if (stripos($desc_original, 'Compra') !== false && !empty($nome_fornecedor)) {
                        $descricao_final = "Compra: " . $nome_fornecedor;
                    } else {
                        $descricao_final = mb_strimwidth($desc_original, 0, 50, "...");
                    }

                    $valor = (float)($dados[7] ?? 0);
                    
                    // --- CATEGORIA (NOME, NÃO ID) ---
                    $categoria_txt = trim($dados[2] ?? '');
                    if (empty($categoria_txt)) {
                        $categoria_txt = 'COMPRA DE PRODUTOS'; // Padrão
                    }
                    
                    $documento = trim($dados[15] ?? '');

                    // --- INSERIR CONTA ---
                    $query = "INSERT INTO contas (
                                id_admin, 
                                descricao, 
                                valor_parcela, 
                                valor_total, 
                                vencimento, 
                                status_baixa, 
                                entidade_tipo, 
                                id_entidade,
                                categoria, 
                                documento, 
                                natureza
                              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([
                        $id_admin, 
                        $descricao_final, 
                        $valor, 
                        $valor, 
                        $vencimento_sql, 
                        'PENDENTE', 
                        'fornecedor',
                        $id_fornecedor,
                        $categoria_txt,
                        $documento,
                        'Despesa'
                    ]);
                    
                    $importados++;
                    
                } catch (Exception $e) {
                    $erros_log[] = "Linha $linha_cont: " . $e->getMessage();
                    $pulados++;
                }
            }
            
            fclose($stream);
            
            // Mensagem de sucesso
            $msg_fornecedores = "";
            $total_fornecedores = count($fornecedores_criados);
            if ($total_fornecedores > 0) {
                $msg_fornecedores = "<br><i class='fas fa-user-plus'></i> <strong>$total_fornecedores</strong> novos fornecedores criados automaticamente.";
            }
            
            $msg_erros = "";
            if (count($erros_log) > 0) {
                $msg_erros = "<br><i class='fas fa-exclamation-triangle' style='color:#f39c12;'></i> <strong>" . count($erros_log) . "</strong> linhas com erro foram ignoradas.";
            }
            
            $mensagem = "<div class='alert success'>
                            <i class='fas fa-check-circle'></i> 
                            <b>Importação Concluída!</b><br> 
                            ✓ <strong>$importados</strong> contas importadas com fornecedores vinculados.$msg_fornecedores<br>
                            ✓ Descrições padronizadas para melhor visualização.$msg_erros
                         </div>";
                         
        } catch (Exception $e) {
            $mensagem = "<div class='alert error'><i class='fas fa-exclamation-triangle'></i> <b>Erro:</b> " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        $mensagem = "<div class='alert error'><i class='fas fa-times-circle'></i> Erro: Arquivo CSV não enviado corretamente.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Contas</title>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Source Sans Pro', sans-serif; 
            padding: 20px; 
            background: #fff; 
            color: #333; 
            font-size: 16px;
        }
        .form-group { margin-bottom: 18px; }
        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            color: #555; 
            font-size: 15px; 
        }
        input[type="date"], input[type="file"] { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid #d2d6de; 
            border-radius: 4px; 
            box-sizing: border-box; 
            font-size: 16px;
            font-family: inherit;
        }
        input[type="file"] {
            padding: 10px;
            cursor: pointer;
        }
        .btn-submit { 
            background: #00a65a; 
            color: white; 
            border: none; 
            width: 100%; 
            padding: 14px; 
            border-radius: 4px; 
            cursor: pointer; 
            font-weight: 700; 
            text-transform: uppercase; 
            margin-top: 15px; 
            font-size: 16px;
            transition: 0.3s;
        }
        .btn-submit:hover {
            background: #008d4c;
        }
        .alert { 
            padding: 15px; 
            border-radius: 4px; 
            margin-bottom: 20px; 
            font-size: 15px; 
            line-height: 1.6;
        }
        .success { 
            background: #dff0d8; 
            color: #3c763d; 
            border: 1px solid #d6e9c6; 
        }
        .error { 
            background: #f2dede; 
            color: #a94442; 
            border: 1px solid #ebccd1; 
        }
        .hint { 
            font-size: 14px; 
            color: #666; 
            background: #f9f9f9; 
            padding: 15px; 
            border-radius: 4px; 
            border-left: 4px solid #337ab7; 
            line-height: 1.6;
        }
        .hint strong {
            color: #337ab7;
        }
        .hint ul {
            margin: 10px 0 0 20px;
        }
        .hint li {
            margin: 5px 0;
        }
    </style>
</head>
<body>

    <?= $mensagem ?>

    <form action="" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label><i class="fas fa-calendar"></i> Importar vencimentos a partir de:</label>
            <input type="date" name="data_inicio_importacao" value="<?= date('Y-m-01') ?>" required>
        </div>

        <div class="form-group">
            <label><i class="fas fa-file-csv"></i> Arquivo CSV (contas-a-pagar.csv):</label>
            <input type="file" name="csv_file" accept=".csv" required>
        </div>

        <button type="submit" class="btn-submit">
            <i class="fas fa-file-import"></i> Processar e Importar
        </button>
    </form>

    <div class="hint" style="margin-top:25px;">
        <i class="fas fa-info-circle"></i> <strong>Como funciona a importação:</strong>
        <ul>
            <li><i class="fas fa-check" style="color:#00a65a;"></i> Sistema busca fornecedores existentes pelo nome</li>
            <li><i class="fas fa-check" style="color:#00a65a;"></i> Se não encontrar, cria automaticamente</li>
            <li><i class="fas fa-check" style="color:#00a65a;"></i> Vincula corretamente o ID do fornecedor na conta</li>
            <li><i class="fas fa-check" style="color:#00a65a;"></i> Categoria é preenchida automaticamente (COMPRA DE PRODUTOS)</li>
            <li><i class="fas fa-check" style="color:#00a65a;"></i> Padroniza descrições longas para melhor visualização</li>
        </ul>
    </div>

</body>
</html>