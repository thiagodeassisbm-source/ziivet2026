<?php
require_once 'config/configuracoes.php';

try {
    // Adicionar colunas 'ambiente' e 'tp_amb' (apenas para consistencia, usaremos 'ambiente' como INT 1 ou 2)
    // 1 = Produção, 2 = Homologação
    // Default 2 (Segurança)
    $pdo->query("ALTER TABLE configuracoes_fiscais ADD COLUMN ambiente INT(1) DEFAULT 2 COMMENT '1=Producao, 2=Homologacao'");
    echo "Coluna 'ambiente' adicionada com sucesso.\n";
} catch (Exception $e) {
    echo "Coluna 'ambiente' ja existe ou erro: " . $e->getMessage() . "\n";
}
