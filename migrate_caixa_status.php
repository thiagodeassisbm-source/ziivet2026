<?php
require_once 'config/configuracoes.php';

try {
    echo "Iniciando migração de status 'FECHADO' para 'ENCERRADO'...\n";
    
    $sql = "UPDATE caixas SET status = 'ENCERRADO' WHERE status = 'FECHADO'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    echo "Linhas afetadas: " . $stmt->rowCount() . "\n";
    echo "Migração concluída com sucesso.\n";
    
} catch (Exception $e) {
    echo "Erro durante a migração: " . $e->getMessage() . "\n";
}
