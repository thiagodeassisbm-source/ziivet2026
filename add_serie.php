<?php
require_once 'config/configuracoes.php';

echo "<h1>Adicionando colunas de Série e Número</h1>";

$colunas = [
    "ALTER TABLE configuracoes_fiscais ADD COLUMN nfce_serie INT DEFAULT 1",
    "ALTER TABLE configuracoes_fiscais ADD COLUMN nfce_numero INT DEFAULT 1"
];

foreach ($colunas as $sql) {
    try {
        $pdo->exec($sql);
        echo "✓ Coluna adicionada!<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "⚠ Coluna já existe<br>";
        } else {
            echo "✗ Erro: " . $e->getMessage() . "<br>";
        }
    }
}

echo "<br><h2 style='color:green'>✅ CONCLUÍDO!</h2>";
echo "<a href='nfe/configuracoes_nfe.php?aba=serie'>Voltar para Configurações</a>";
