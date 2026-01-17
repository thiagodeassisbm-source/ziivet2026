<?php
require_once 'config/configuracoes.php';

echo "Estrutura da tabela minha_empresa:\n";
$cols = $pdo->query('DESCRIBE minha_empresa')->fetchAll(PDO::FETCH_ASSOC);
foreach($cols as $c) {
    echo "- " . $c['Field'] . " (" . $c['Type'] . ")\n";
}

echo "\nDados da tabela:\n";
$dados = $pdo->query('SELECT * FROM minha_empresa LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if ($dados) {
    foreach($dados as $k => $v) {
        echo "$k: " . substr($v, 0, 50) . "\n";
    }
}
?>
