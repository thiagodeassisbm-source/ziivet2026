<?php
require_once 'config/configuracoes.php';

$stmt = $pdo->prepare("SELECT id, status, data_abertura, data_fechamento FROM caixas WHERE id = 43");
$stmt->execute();
$caixa = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Caixa 43:\n";
print_r($caixa);
