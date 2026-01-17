<?php
require_once 'config/configuracoes.php';

echo "=== ESTRUTURA DA TABELA PRODUTOS ===\n";
$stmt = $pdo->query('DESCRIBE produtos');
while($row = $stmt->fetch()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

echo "\n=== EXEMPLO DE PRODUTO/SERVIÇO (SKU 14848) ===\n";
$stmt = $pdo->prepare('SELECT id, sku, produto, tipo, categoria FROM produtos WHERE sku = ? LIMIT 1');
$stmt->execute(['14848']);
$item = $stmt->fetch();
if ($item) {
    print_r($item);
} else {
    echo "Item não encontrado\n";
}
?>
