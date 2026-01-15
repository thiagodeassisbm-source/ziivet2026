<?php
require_once 'config/configuracoes.php';

echo "Recent Contas Entries:\n";
$stmt = $pdo->query("SELECT id, data_cadastro, descricao, valor_total, id_forma_pgto, forma_pagamento_detalhe FROM contas ORDER BY id DESC LIMIT 5");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);

echo "\nFormas Pagamento Table:\n";
$stmt = $pdo->query("SELECT id, nome_forma FROM formas_pagamento");
$forms = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($forms);
