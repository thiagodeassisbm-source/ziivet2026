<?php
require_once 'config/configuracoes.php';
// Check entries for Caixa 43
$stmt = $pdo->prepare("SELECT id, descricao, valor_total, id_forma_pgto FROM contas WHERE id_caixa_referencia = ?");
$stmt->execute([43]);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

// Check formas_pagamento
echo "Formas Pagamento:\n";
$stmt = $pdo->query("SELECT id, nome_forma FROM formas_pagamento");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
