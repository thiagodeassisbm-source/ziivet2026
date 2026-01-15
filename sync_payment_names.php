<?php
require_once 'config/configuracoes.php';

// Find the two sales
$stmt = $pdo->query("SELECT id, forma_pagamento_detalhe, valor_total FROM contas WHERE valor_total IN (19.90, 143.60) ORDER BY id DESC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

print_r($rows);

// If found, update the 143.60 one to match the 19.90 one
$targetName = null;
foreach ($rows as $r) {
    if (abs($r['valor_total'] - 19.90) < 0.1) {
        $targetName = $r['forma_pagamento_detalhe'];
        break;
    }
}

if ($targetName) {
    echo "Target Name found: '$targetName'. Updating 143.60 sale...\n";
    $upd = $pdo->prepare("UPDATE contas SET forma_pagamento_detalhe = ? WHERE valor_total = 143.60 AND id_caixa_referencia = 43");
    $upd->execute([$targetName]);
    echo "Updated rows: " . $upd->rowCount() . "\n";
} else {
    echo "Target name (19.90 sale) not found.\n";
}
