<?php
require_once 'config/configuracoes.php';
try {
    $stmt = $pdo->query("UPDATE contas SET forma_pagamento_detalhe = 'Cartão de Crédito Master' WHERE id = 305");
    echo "Updated ID 305. Rows affected: " . $stmt->rowCount() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
