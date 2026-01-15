<?php
require_once 'config/configuracoes.php';
$stmt = $pdo->query("DESCRIBE contas");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
