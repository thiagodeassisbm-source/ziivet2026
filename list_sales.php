<?php
require_once 'config/configuracoes.php';

$stmt = $pdo->query("SELECT id, descricao, valor_total, id_forma_pgto, forma_pagamento_detalhe FROM contas ORDER BY id DESC LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $r) {
    if (strpos($r['descricao'], 'Venda') !== false || $r['valor_total'] > 100) {
        echo "ID: " . $r['id'] . " | Val: " . $r['valor_total'] . " | Detalhe: [" . $r['forma_pagamento_detalhe'] . "]\n";
    }
}
