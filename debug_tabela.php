<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/configuracoes.php';

echo "<h1>Debug - Configurações Fiscais</h1>";

try {
    // Verificar estrutura da tabela
    echo "<h2>Estrutura da tabela configuracoes_fiscais:</h2>";
    $stmt = $pdo->query("DESCRIBE configuracoes_fiscais");
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th></tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Tentar adicionar colunas
    echo "<h2>Adicionando colunas CSC...</h2>";
    
    try {
        $pdo->exec("ALTER TABLE configuracoes_fiscais ADD COLUMN csc_id VARCHAR(10) NULL");
        echo "✓ csc_id adicionada!<br>";
    } catch (PDOException $e) {
        echo "csc_id: " . $e->getMessage() . "<br>";
    }
    
    try {
        $pdo->exec("ALTER TABLE configuracoes_fiscais ADD COLUMN csc_producao VARCHAR(255) NULL");
        echo "✓ csc_producao adicionada!<br>";
    } catch (PDOException $e) {
        echo "csc_producao: " . $e->getMessage() . "<br>";
    }
    
    // Mostrar estrutura atualizada
    echo "<h2>Estrutura APÓS atualização:</h2>";
    $stmt = $pdo->query("DESCRIBE configuracoes_fiscais");
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th></tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br><a href='nfe/configuracoes_nfe.php'>Voltar para Configurações</a>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>ERRO: " . $e->getMessage() . "</p>";
}
