<?php
require_once 'config/configuracoes.php';

echo "Criando tabela para armazenar NFS-e emitidas...\n";

try {
    $sql = "CREATE TABLE IF NOT EXISTS nfse_emitidas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_venda INT NOT NULL,
        numero VARCHAR(20),
        chave_acesso VARCHAR(50),
        xml LONGTEXT,
        data_emissao DATETIME,
        id_admin INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_venda (id_venda),
        INDEX idx_admin (id_admin)
    )";
    
    $pdo->exec($sql);
    echo "✅ Tabela 'nfse_emitidas' criada com sucesso!\n";
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
