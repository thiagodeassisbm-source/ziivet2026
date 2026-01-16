<?php
require_once 'config/configuracoes.php';
$stmt = $pdo->query('SELECT id, valor_total FROM vendas ORDER BY id DESC LIMIT 1');
$venda = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Última venda: ID " . ($venda['id'] ?? 'Nenhuma') . " - Valor: " . ($venda['valor_total'] ?? '0.00');
