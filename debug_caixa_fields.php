<?php
require_once 'config/configuracoes.php';

// Check caixa 43 data with all fields
$stmt = $pdo->prepare("SELECT id, status, valor_inicial, data_abertura, hora_abertura, descricao, data_fechamento, valor_fechamento FROM caixas WHERE id = ?");
$stmt->execute([43]);
$caixa = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Caixa 43:\n";
echo "Status: " . $caixa['status'] . "\n";
echo "Valor Inicial: " . $caixa['valor_inicial'] . "\n";
echo "Abertura: " . $caixa['data_abertura'] . " " . $caixa['hora_abertura'] . "\n";
echo "Descricao: " . $caixa['descricao'] . "\n";
echo "Fechamento: " . $caixa['data_fechamento'] . "\n";
echo "Valor Fechamento: " . $caixa['valor_fechamento'] . "\n";
