<?php
require_once 'config/configuracoes.php';

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM caixas WHERE Field = 'status'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Estrutura da coluna 'status':\n";
    print_r($column);
    
    // Verificar o valor atual do caixa 43 novamente
    $stmt2 = $pdo->query("SELECT id, status FROM caixas WHERE id = 43");
    $caixa43 = $stmt2->fetch(PDO::FETCH_ASSOC);
    echo "\nValor atual do caixa 43:\n";
    print_r($caixa43);

} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
