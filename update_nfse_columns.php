<?php
// Script para adicionar colunas faltantes na tabela configuracoes_fiscais
require_once 'config/configuracoes.php';

$colunas = [
    "ALTER TABLE configuracoes_fiscais ADD COLUMN inscricao_municipal VARCHAR(50) DEFAULT ''",
    "ALTER TABLE configuracoes_fiscais ADD COLUMN login_prefeitura VARCHAR(50) DEFAULT ''",
    "ALTER TABLE configuracoes_fiscais ADD COLUMN senha_prefeitura VARCHAR(255) DEFAULT ''"
];

foreach ($colunas as $sql) {
    try {
        $pdo->exec($sql);
        echo "Sucesso: $sql <br>";
    } catch (PDOException $e) {
        // Se der erro (provavelmente porque a coluna já existe), apenas ignora e segue
        echo "Info: Coluna já existe ou erro: " . $e->getMessage() . "<br>";
    }
}

echo "Atualização de colunas NFS-e concluída.";
?>
