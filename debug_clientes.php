<?php
require_once 'config/configuracoes.php';
$stmt = $pdo->query("DESCRIBE clientes");
echo "<pre>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "</pre>";
