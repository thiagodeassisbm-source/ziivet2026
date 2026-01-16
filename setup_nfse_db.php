<?php
// require_once 'auth.php'; // Removed to allow CLI execution
require_once 'config/configuracoes.php';

// 1. Adicionar colunas na tabela principal se não existirem
try {
    $pdo->exec("ALTER TABLE configuracoes_fiscais ADD COLUMN num_ultima_nfse INT DEFAULT 0");
    $pdo->exec("ALTER TABLE configuracoes_fiscais ADD COLUMN serie_nfse VARCHAR(10) DEFAULT '1'");
    $pdo->exec("ALTER TABLE configuracoes_fiscais ADD COLUMN cnae_principal VARCHAR(20) DEFAULT ''");
    $pdo->exec("ALTER TABLE configuracoes_fiscais ADD COLUMN codigo_nbs_padrao VARCHAR(20) DEFAULT ''");
} catch (PDOException $e) {
    // Colunas já existem, ignorar
}

// 2. Criar tabela de Serviços Predefinidos (para a lista da Imagem 2)
$sql = "CREATE TABLE IF NOT EXISTS nfse_servicos_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_admin INT NOT NULL,
    cnae VARCHAR(20),
    item_lc116 VARCHAR(20),
    codigo_nbs VARCHAR(20),
    aliquota_iss DECIMAL(5,2) DEFAULT 0.00,
    descricao VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

try {
    $pdo->exec($sql);
    echo "Tabelas e colunas atualizadas com sucesso!";
} catch (PDOException $e) {
    echo "Erro ao atualizar banco: " . $e->getMessage();
}
?>
