<?php
require_once 'config/configuracoes.php';

echo "<h1>Atualizando tabela configuracoes_fiscais</h1>";

try {
    // Verificar se colunas existem e adicionar se não existirem
    $colunas = [
        "ALTER TABLE configuracoes_fiscais ADD COLUMN csc_id VARCHAR(10) NULL",
        "ALTER TABLE configuracoes_fiscais ADD COLUMN csc_producao VARCHAR(255) NULL"
    ];
    
    foreach ($colunas as $sql) {
        try {
            $pdo->exec($sql);
            echo "✓ Coluna adicionada!<br>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "⚠ Coluna já existe (OK)<br>";
            } else {
                echo "✗ Erro: " . $e->getMessage() . "<br>";
            }
        }
    }
    
    echo "<br><h2 style='color:green'>✅ ATUALIZAÇÃO CONCLUÍDA!</h2>";
    echo "<p><a href='nfe/configuracoes_nfe.php'>Voltar para Configurações Fiscais</a></p>";
    
} catch (PDOException $e) {
    echo "ERRO: " . $e->getMessage();
}
