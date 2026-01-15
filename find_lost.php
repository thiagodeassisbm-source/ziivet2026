<?php
require_once 'config/configuracoes.php';

// Find the 19.90 sale specifically, maybe it has a stored tax value that changed the total in contas?
// Or maybe I missed it.
// Search by approximate value 18-21
$stmt = $pdo->query("SELECT * FROM contas WHERE valor_total BETWEEN 18 AND 21 ORDER BY id DESC LIMIT 5");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);

// Search for string "Master"
$stmt2 = $pdo->query("SELECT id, valor_total, forma_pagamento_detalhe FROM contas WHERE forma_pagamento_detalhe LIKE '%Master%' ORDER BY id DESC LIMIT 5");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
