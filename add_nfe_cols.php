<?php
require_once 'config/configuracoes.php';

function addCol($pdo, $col, $def) {
    try {
        $pdo->query("ALTER TABLE vendas ADD COLUMN $col $def");
        echo "Coluna $col adicionada.\n";
    } catch (Exception $e) {
        // Ignora erro se já existe
        echo "Coluna $col ja existe ou erro: " . $e->getMessage() . "\n";
    }
}

addCol($pdo, 'nfce_chave', 'VARCHAR(44) NULL');
addCol($pdo, 'nfce_protocolo', 'VARCHAR(20) NULL');
addCol($pdo, 'nfce_status', "VARCHAR(20) DEFAULT 'PENDENTE'");
addCol($pdo, 'nfce_url', 'TEXT NULL');
addCol($pdo, 'nfce_data_emissao', 'DATETIME NULL');

echo "Concluido.";
