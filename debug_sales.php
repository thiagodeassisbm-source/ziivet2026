<?php
require_once 'config/configuracoes.php';

// List all recent sales to see what's going on
$stmt = $pdo->query("SELECT id, valor_total, forma_pagamento_detalhe FROM contas ORDER BY id DESC LIMIT 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
