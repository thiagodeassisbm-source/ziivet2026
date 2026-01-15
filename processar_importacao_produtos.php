<?php
require_once 'auth.php';
require_once 'config/configuracoes.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;

if (!isset($_POST['json_data'])) {
    echo json_encode(['status' => 'error', 'message' => 'Nenhum dado recebido para processamento.']);
    exit;
}

$produtos = json_decode($_POST['json_data'], true);
$novos = 0;
$atualizados = 0;

try {
    $pdo->beginTransaction();

    foreach ($produtos as $p) {
        // Limpar strings de moeda: "30,90" -> 30.90
        $custo = (float)str_replace(',', '.', str_replace('.', '', $p['custo']));
        $venda = (float)str_replace(',', '.', str_replace('.', '', $p['venda']));
        $estoque = (float)str_replace(',', '.', str_replace('.', '', $p['estoque']));

        // 1. Tratar Categoria (nome_categoria na tabela categorias_produtos)
        $id_cat = 0;
        if (!empty($p['grupo'])) {
            // No seu banco a tabela categorias_produtos NÃO tem id_admin, apenas id e nome_categoria
            $st = $pdo->prepare("SELECT id FROM categorias_produtos WHERE nome_categoria = ?");
            $st->execute([$p['grupo']]);
            $id_cat = $st->fetchColumn();

            if (!$id_cat) {
                $ins = $pdo->prepare("INSERT INTO categorias_produtos (nome_categoria) VALUES (?)");
                $ins->execute([$p['grupo']]);
                $id_cat = $pdo->lastInsertId();
            }
        }

        // 2. Verificar se produto já existe para este id_admin (pelo SKU ou Nome)
        $stCheck = $pdo->prepare("SELECT id FROM produtos WHERE (sku = ? OR nome = ?) AND id_admin = ?");
        $stCheck->execute([$p['sku'], $p['nome'], $id_admin]);
        $id_existente = $stCheck->fetchColumn();

        if ($id_existente) {
            // Atualizar existente
            $sql = "UPDATE produtos SET 
                        preco_custo = ?, 
                        preco_venda = ?, 
                        estoque_inicial = ?, 
                        id_categoria = ?, 
                        gtin = ?, 
                        ncm = ? 
                    WHERE id = ? AND id_admin = ?";
            $pdo->prepare($sql)->execute([
                $custo, $venda, $estoque, $id_cat, $p['gtin'], $p['ncm'], $id_existente, $id_admin
            ]);
            $atualizados++;
        } else {
            // Inserir novo (coluna correta: id_admin)
            $sql = "INSERT INTO produtos (
                        id_admin, nome, tipo, sku, gtin, ncm, id_categoria, 
                        preco_custo, preco_venda, estoque_inicial, id_comissao, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'ATIVO')";
            $pdo->prepare($sql)->execute([
                $id_admin, $p['nome'], $p['tipo'], $p['sku'], $p['gtin'], $p['ncm'], $id_cat, 
                $custo, $venda, $estoque
            ]);
            $novos++;
        }
    }

    $pdo->commit();
    echo json_encode([
        'status' => 'success', 
        'message' => "Importação concluída: $novos cadastrados e $atualizados atualizados."
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => "Erro no processamento: " . $e->getMessage()]);
}