<?php
/**
 * Localização: app/financeiro/importar_fornecedores.php
 */
$base_app = dirname(__DIR__); 
require_once $base_app . '/auth.php';
require_once $base_app . '/config/configuracoes.php';

$id_admin = $_SESSION['id_admin'] ?? 1;
$mensagem = "";

// Função para deixar Inicial Maiúscula e o resto minúscula
function formatarTexto($texto) {
    if (empty($texto)) return "";
    return mb_convert_case(mb_strtolower(trim($texto), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $arquivo_tmp = $_FILES['csv_file']['tmp_name'];

    if (file_exists($arquivo_tmp)) {
        try {
            // Converte ISO para UTF-8 para evitar erro de acentos
            $conteudo = file_get_contents($arquivo_tmp);
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'ISO-8859-1');
            $stream = fopen('php://memory', 'r+');
            fwrite($stream, $conteudo);
            rewind($stream);

            $importados = 0;
            $linha_cont = 0;

            while (($dados = fgetcsv($stream, 3000, ";")) !== FALSE) {
                $linha_cont++;
                if ($linha_cont == 1) continue; // Pula cabeçalho

                // Mapeamento conforme seu arquivo fornecedores.csv
                $razao_social  = formatarTexto($dados[0]); // Coluna 0
                $fantasia      = formatarTexto($dados[1]); // Coluna 1
                $tipo_raw      = trim($dados[2]);          // Coluna 2
                $documento     = trim($dados[3]);          // Coluna 3
                $telefone      = trim($dados[5]);          // Coluna 5
                $email         = mb_strtolower(trim($dados[8])); // Coluna 8 (email sempre minúsculo)
                $tipo_forn     = trim($dados[24] ?? 'Produtos e/ou serviços');

                // Lógica de Tipo Pessoa
                $tipo_pessoa = (stripos($tipo_raw, 'física') !== false) ? 'Fisica' : 'Juridica';

                $query = "INSERT INTO fornecedores (
                            id_admin, nome_completo, razao_social, nome_fantasia, 
                            tipo_pessoa, cnpj, cpf, tipo_fornecedor, telefone1, email, 
                            status, data_cadastro
                          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ATIVO', NOW())";
                
                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    $id_admin, 
                    $razao_social, 
                    $razao_social, 
                    $fantasia, 
                    $tipo_pessoa, 
                    ($tipo_pessoa == 'Juridica' ? $documento : NULL),
                    ($tipo_pessoa == 'Fisica' ? $documento : NULL),
                    $tipo_forn,
                    $telefone,
                    $email
                ]);
                $importados++;
            }
            fclose($stream);
            $mensagem = "<div class='alert success'><i class='fas fa-check-circle'></i> <b>Sucesso!</b> $importados fornecedores importados com nomes padronizados.</div>";
        } catch (Exception $e) {
            $mensagem = "<div class='alert error'>Erro: " . $e->getMessage() . "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Source Sans Pro', sans-serif; padding: 20px; background: #fff; font-size: 18px; color: #333; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
        input[type="file"] { width: 100%; padding: 12px; border: 1px solid #d2d6de; border-radius: 4px; box-sizing: border-box; background: #f9f9f9; }
        .btn-submit { background: #00a65a; color: white; border: none; width: 100%; padding: 15px; border-radius: 4px; cursor: pointer; font-weight: 700; font-size: 18px; text-transform: uppercase; margin-top: 10px; transition: 0.3s; }
        .btn-submit:hover { background: #008d4c; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; font-size: 16px; border: 1px solid transparent; }
        .success { background: #dff0d8; color: #3c763d; border-color: #d6e9c6; }
        .error { background: #f2dede; color: #a94442; border-color: #ebccd1; }
        .info-box { background: #e7f3ff; color: #31708f; padding: 12px; border-radius: 4px; border-left: 4px solid #337ab7; font-size: 14px; margin-top: 20px; }
    </style>
</head>
<body>
    <?= $mensagem ?>
    <form action="" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label><i class="fas fa-file-csv"></i> Selecione o arquivo de fornecedores:</label>
            <input type="file" name="csv_file" accept=".csv" required>
        </div>
        <button type="submit" class="btn-submit">Processar e Padronizar</button>
    </form>
    
    <div class="info-box">
        <i class="fas fa-magic"></i> <b>Padronização Automática:</b> Nomes em MAIÚSCULO serão convertidos para "Inicial Maiúscula e o resto minúscula" durante a importação.
    </div>
</body>
</html>