<?php
require_once 'config/configuracoes.php';
try {
    $stmt = $pdo->query("SHOW CREATE VIEW lancamentos");
    $view = $stmt->fetch();
    if ($view) {
        echo "View Definition:\n";
        echo $view['Create View']; 
    } else {
        echo "lancamentos is not a view. Checking if table:\n";
        $stmt = $pdo->query("SHOW CREATE TABLE lancamentos");
        $table = $stmt->fetch();
        print_r($table);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
