<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/configuracoes.php';

echo "<h1>Adicionando colunas Serie e Numero</h1>";

try {
    $pdo->exec("ALTER TABLE configuracoes_fiscais ADD COLUMN nfce_serie INT DEFAULT 1");
    echo "✓ nfce_serie adicionada!<br>";
} catch (PDOException $e) {
    echo "nfce_serie: " . $e->getMessage() . "<br>";
}

try {
    $pdo->exec("ALTER TABLE configuracoes_fiscais ADD COLUMN nfce_numero INT DEFAULT 1");
    echo "✓ nfce_numero adicionada!<br>";
} catch (PDOException $e) {
    echo "nfce_numero: " . $e->getMessage() . "<br>";
}

echo "<h2>Estrutura atual:</h2>";
$stmt = $pdo->query("DESCRIBE configuracoes_fiscais");
echo "<pre>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "</pre>";

echo "<br><a href='nfe/configuracoes_nfe.php?aba=serie'>Voltar para Configurações</a>";
