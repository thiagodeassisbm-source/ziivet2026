<?php
require_once 'config/configuracoes.php';

echo "<h2>Debug Lancamentos vs Contas</h2>";

try {
    // Check if lancamentos is view
    $stmt = $pdo->query("SHOW FULL TABLES LIKE 'lancamentos'");
    $row = $stmt->fetch();
    echo "Table Type of 'lancamentos': " . ($row ? $row[1] : 'NOT FOUND') . "<br><hr>";

    // Last 5 from contas
    echo "<h3>Last 5 from 'contas'</h3>";
    $stmt = $pdo->query("SELECT id, descricao, documento, valor_total FROM contas ORDER BY id DESC LIMIT 5");
    $contas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($contas, true) . "</pre><hr>";

    // Last 5 from lancamentos
    echo "<h3>Last 5 from 'lancamentos'</h3>";
    $stmt = $pdo->query("SELECT id, descricao, documento, valor FROM lancamentos ORDER BY id DESC LIMIT 5");
    $lancamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($lancamentos, true) . "</pre>";
    
    // Check view definition if it is a view
    if ($row && $row[1] == 'VIEW') {
        $stmt = $pdo->query("SHOW CREATE VIEW lancamentos");
        $viewDef = $stmt->fetch();
        echo "<hr><h3>View Definition</h3>";
        echo "<pre>" . htmlspecialchars($viewDef['Create View']) . "</pre>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
