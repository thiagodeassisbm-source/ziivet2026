<?php
require_once 'config/configuracoes.php';

$stmt = $pdo->query("SHOW COLUMNS FROM contas");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo $col['Field'] . "\n";
}
