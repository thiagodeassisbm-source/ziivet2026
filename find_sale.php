<?php
require_once 'config/configuracoes.php';

$stmt = $pdo->prepare("SELECT id, descricao, valor_total, id_forma_pgto, forma_pagamento_detalhe FROM contas WHERE valor_total > 143 AND valor_total < 144");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($rows) . " rows matching approx 143.60:\n";
foreach ($rows as $r) {
    echo "ID: " . $r['id'] . "\n";
    echo "Desc: " . $r['descricao'] . "\n";
    echo "Valor: " . $r['valor_total'] . "\n";
    echo "ID Forma: " . var_export($r['id_forma_pgto'], true) . "\n";
    echo "Detalhe: " . var_export($r['forma_pagamento_detalhe'], true) . "\n";
    echo "-----------------\n";
}
