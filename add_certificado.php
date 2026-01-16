<?php
require_once 'config/configuracoes.php';

echo "<h1>Adicionando campos de Certificado Digital</h1>";

try {
    $colunas = [
        "ALTER TABLE configuracoes_fiscais ADD COLUMN certificado_nome VARCHAR(255) NULL",
        "ALTER TABLE configuracoes_fiscais ADD COLUMN certificado_validade DATE NULL",
        "ALTER TABLE configuracoes_fiscais ADD COLUMN certificado_arquivo VARCHAR(255) NULL"
    ];
    
    foreach ($colunas as $sql) {
        try {
            $pdo->exec($sql);
            echo "✓ Coluna adicionada!<br>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "⚠ Coluna já existe<br>";
            } else {
                echo "Erro: " . $e->getMessage() . "<br>";
            }
        }
    }
    
    // Criar pasta para certificados
    $pasta = __DIR__ . '/uploads/certificados';
    if (!is_dir($pasta)) {
        mkdir($pasta, 0755, true);
        echo "✓ Pasta de certificados criada!<br>";
    }
    
    echo "<br><h2 style='color:green'>✅ CONCLUÍDO!</h2>";
    echo "<a href='nfe/configuracoes_nfe.php'>Voltar para Configurações</a>";
    
} catch (PDOException $e) {
    echo "ERRO: " . $e->getMessage();
}
