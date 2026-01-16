<?php
require_once 'config/configuracoes.php';

// Check caixa 43 data
$stmt = $pdo->prepare("SELECT * FROM caixas WHERE id = ?");
$stmt->execute([43]);
$caixa = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Caixa 43 Data:\n";
print_r($caixa);
