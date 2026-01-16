<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/configuracoes.php';

echo "<h1>Adicionando colunas de Certificado</h1>";

$colunas = [
    "ALTER TABLE configuracoes_fiscais ADD COLUMN certificado_nome VARCHAR(255) NULL",
    "ALTER TABLE configuracoes_fiscais ADD COLUMN certificado_validade DATE NULL",
    "ALTER TABLE configuracoes_fiscais ADD COLUMN certificado_arquivo VARCHAR(255) NULL"
];

foreach ($colunas as $sql) {
    try {
        $pdo->exec($sql);
        echo "✓ Executado: " . substr($sql, 0, 60) . "...<br>";
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Duplicate column') !== false) {
            echo "⚠ Coluna já existe (OK)<br>";
        } else {
            echo "✗ ERRO: $msg<br>";
        }
    }
}

echo "<h2>Estrutura atual da tabela:</h2>";
$stmt = $pdo->query("DESCRIBE configuracoes_fiscais");
echo "<table border='1' cellpadding='5'><tr><th>Campo</th><th>Tipo</th></tr>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td></tr>";
}
echo "</table>";

echo "<br><a href='nfe/configuracoes_nfe.php?aba=certificado'>Voltar para Configurações</a>";
