<?php
require_once 'config/configuracoes.php';
try {
    $stmt = $pdo->query("SELECT * FROM contas LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        print_r(array_keys($row));
    } else {
        echo "Table empty, using DESCRIBE\n";
        $stmt = $pdo->query("DESCRIBE contas");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        print_r($cols);
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
