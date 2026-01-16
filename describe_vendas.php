<?php
require_once 'config/configuracoes.php';
$stmt = $pdo->query("DESCRIBE vendas");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($cols as $c) { echo $c['Field'] . " (" . $c['Type'] . ")\n"; }
