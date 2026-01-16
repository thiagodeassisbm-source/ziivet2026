<?php
require_once 'config/configuracoes.php';

function mostrarEstrutura($pdo, $tabela) {
    echo "<h3>Tabela: $tabela</h3>";
    try {
        $stmt = $pdo->query("DESCRIBE $tabela");
        echo "<table border='1' style='border-collapse:collapse; width:100%'>";
        echo "<tr><th>Campo</th><th>Tipo</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td></tr>";
        }
        echo "</table>";
    } catch (PDOException $e) {
        echo "Erro: " . $e->getMessage();
    }
}

echo "<h1>Estrutura de Vendas Itens</h1>";
mostrarEstrutura($pdo, 'vendas_itens');
