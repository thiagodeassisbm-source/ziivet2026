<?php
require_once 'config/configuracoes.php';

try {
    echo "Atualizando categoria de lançamentos de SUPRIMENTO...\n";
    
    $sql = "UPDATE contas SET categoria = 'Caixa' WHERE documento = 'SUPRIMENTO' AND (categoria = '1' OR categoria IS NULL)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    echo "Registros atualizados: " . $stmt->rowCount() . "\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
