<?php
require_once 'config/configuracoes.php';
try {
    $stmt = $pdo->query("SELECT * FROM lancamentos ORDER BY id DESC LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($rows);
} catch (Exception $e) {
    echo $e->getMessage();
}
