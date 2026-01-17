<?php
require_once 'config/configuracoes.php';

echo "Tabelas relacionadas a empresa/admin:\n";
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach($tables as $t) {
    if(stripos($t, 'admin') !== false || stripos($t, 'empresa') !== false || stripos($t, 'usuario') !== false) {
        echo "- $t\n";
    }
}

echo "\nVerificando estrutura da tabela configuracoes_fiscais:\n";
$cols = $pdo->query('DESCRIBE configuracoes_fiscais')->fetchAll(PDO::FETCH_ASSOC);
foreach($cols as $c) {
    echo "- " . $c['Field'] . "\n";
}
?>
