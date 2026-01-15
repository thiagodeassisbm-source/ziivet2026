<?php
require_once 'auth.php';
require_once 'config/configuracoes.php';
require_once __DIR__ . '/vendor/autoload.php';

use App\Application\Service\FileUploaderService;

header('Content-Type: application/json');

$id_admin = $_SESSION['id_admin'] ?? 1;

// Verificação de segurança: Apenas administradores podem realizar importações
if (!temPermissao('usuarios', 'listar')) {
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado: Apenas administradores podem realizar esta operação.']);
    exit;
}

try {
    $produtos = [];

    // Prioridade 1: Upload de Arquivo (CSV/Excel) conforme solicitado no prompt 1.2
    if (isset($_FILES['import_file'])) {
        $uploader = new FileUploaderService();
        $uploadDir = __DIR__ . '/uploads/temp_import';
        
        // Mimes para CSV e Excel
        $allowedTypes = [
            'text/csv', 
            'text/plain', 
            'application/vnd.ms-excel', 
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        
        $fileName = $uploader->upload($_FILES['import_file'], $uploadDir, $allowedTypes);
        $filePath = $uploadDir . '/' . $fileName;

        // Se for CSV, podemos processar aqui (exemplo simplificado)
        // Para uma solução completa de mapeamento dinâmico, o ideal seria o fluxo de importar_dados.php
        // Mas vamos manter a compatibilidade com o processamento de produtos
        
        if (pathinfo($fileName, PATHINFO_EXTENSION) === 'csv') {
            if (($handle = fopen($filePath, "r")) !== FALSE) {
                $header = fgetcsv($handle, 0, ";"); // Tenta ponto e vírgula
                if (count($header) < 2) {
                    rewind($handle);
                    $header = fgetcsv($handle, 0, ","); // Tenta vírgula
                }

                while (($row = fgetcsv($handle, 0, (strpos(file_get_contents($filePath), ';') !== false ? ';' : ','))) !== FALSE) {
                    // Mapeia conforme a estrutura esperada pela lógica abaixo
                    // Isso é um exemplo, o ideal é o frontend enviar o JSON já tratado ou um mapeamento
                    $produtos[] = [
                        'nome' => $row[0] ?? '',
                        'tipo' => $row[1] ?? 'Produto',
                        'sku' => $row[2] ?? '',
                        'gtin' => $row[3] ?? '',
                        'ncm' => $row[4] ?? '',
                        'custo' => $row[5] ?? '0',
                        'venda' => $row[6] ?? '0',
                        'estoque' => $row[7] ?? '0',
                        'grupo' => $row[8] ?? ''
                    ];
                }
                fclose($handle);
            }
        }
        
        unlink($filePath); // Sempre remover arquivo temporário
    } 
    // Prioridade 2: JSON direto (legado/atual)
    elseif (isset($_POST['json_data'])) {
        $produtos = json_decode($_POST['json_data'], true);
    }

    if (empty($produtos)) {
        throw new Exception("Nenhum produto para processar.");
    }

    $novos = 0;
    $atualizados = 0;

    $pdo->beginTransaction();

    foreach ($produtos as $p) {
        // Limpar strings de moeda: "30,90" -> 30.90
        $custo = (float)str_replace(',', '.', str_replace('.', '', (string)($p['custo'] ?? '0')));
        $venda = (float)str_replace(',', '.', str_replace('.', '', (string)($p['venda'] ?? '0')));
        $estoque = (float)str_replace(',', '.', str_replace('.', '', (string)($p['estoque'] ?? '0')));

        // 1. Tratar Categoria
        $id_cat = 0;
        if (!empty($p['grupo'])) {
            $st = $pdo->prepare("SELECT id FROM categorias_produtos WHERE nome_categoria = ?");
            $st->execute([$p['grupo']]);
            $id_cat = $st->fetchColumn();

            if (!$id_cat) {
                $ins = $pdo->prepare("INSERT INTO categorias_produtos (nome_categoria) VALUES (?)");
                $ins->execute([$p['grupo']]);
                $id_cat = $pdo->lastInsertId();
            }
        }

        // 2. Verificar se produto já existe
        $stCheck = $pdo->prepare("SELECT id FROM produtos WHERE (sku = ? OR nome = ?) AND id_admin = ?");
        $stCheck->execute([$p['sku'] ?? '', $p['nome'] ?? '', $id_admin]);
        $id_existente = $stCheck->fetchColumn();

        if ($id_existente) {
            $sql = "UPDATE produtos SET preco_custo = ?, preco_venda = ?, estoque_inicial = ?, id_categoria = ?, gtin = ?, ncm = ? 
                    WHERE id = ? AND id_admin = ?";
            $pdo->prepare($sql)->execute([
                $custo, $venda, $estoque, $id_cat, $p['gtin'] ?? '', $p['ncm'] ?? '', $id_existente, $id_admin
            ]);
            $atualizados++;
        } else {
            $sql = "INSERT INTO produtos (id_admin, nome, tipo, sku, gtin, ncm, id_categoria, preco_custo, preco_venda, estoque_inicial, id_comissao, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'ATIVO')";
            $pdo->prepare($sql)->execute([
                $id_admin, $p['nome'] ?? 'Sem Nome', $p['tipo'] ?? 'Produto', $p['sku'] ?? '', $p['gtin'] ?? '', $p['ncm'] ?? '', $id_cat, $custo, $venda, $estoque
            ]);
            $novos++;
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => "Processamento concluído: $novos cadastrados e $atualizados atualizados."]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => "Erro no processamento: " . $e->getMessage()]);
}