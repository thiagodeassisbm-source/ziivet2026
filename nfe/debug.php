<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug NFC-e</h1>";

try {
    require_once '../config/configuracoes.php';
    echo "✓ Conexão OK<br>";
    
    // Verificar se tabela existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'perfis_tributarios'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Tabela perfis_tributarios existe<br>";
        
        // Contar registros
        $stmt2 = $pdo->query("SELECT COUNT(*) FROM perfis_tributarios");
        $count = $stmt2->fetchColumn();
        echo "✓ Registros: $count<br>";
    } else {
        echo "<strong style='color:red'>✗ Tabela perfis_tributarios NÃO EXISTE!</strong><br>";
        echo "<p>Execute: <a href='../executar_nfe_simples.php'>Criar tabelas</a></p>";
    }
    
} catch (Exception $e) {
    echo "<strong style='color:red'>ERRO: " . $e->getMessage() . "</strong>";
}
