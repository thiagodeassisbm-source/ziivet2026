<?php
require_once 'config/configuracoes.php';

// Check IDs 305 and 306
$stmt = $pdo->prepare("SELECT id, descricao, valor_total FROM contas WHERE id IN (305, 306)");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

print_r($rows);
