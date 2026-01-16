<?php
require_once 'config/configuracoes.php';

echo "<h1>Criando Tabelas NFC-e</h1>";

try {
    // Criar tabela perfis_tributarios
    $sql = "CREATE TABLE IF NOT EXISTS perfis_tributarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_admin INT NOT NULL DEFAULT 1,
        nome VARCHAR(255) NULL,
        tipo ENUM('COM_ST', 'SEM_ST') NOT NULL DEFAULT 'SEM_ST',
        inicio_vigencia DATE NOT NULL,
        fim_vigencia DATE NULL,
        operacao VARCHAR(50) DEFAULT 'Venda',
        ncm VARCHAR(8) NULL,
        cest VARCHAR(7) NULL,
        ex_tipi VARCHAR(3) NULL,
        forma_aquisicao VARCHAR(100) NULL,
        origem_mercadoria INT DEFAULT 0,
        csosn VARCHAR(4) NULL,
        cst_icms VARCHAR(3) NULL,
        cst_pis VARCHAR(2) NULL,
        cst_cofins VARCHAR(2) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql);
    echo "✓ Tabela perfis_tributarios criada!<br>";
    
    // Criar tabela configuracoes_fiscais
    $sql2 = "CREATE TABLE IF NOT EXISTS configuracoes_fiscais (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_admin INT NOT NULL DEFAULT 1,
        tipo_empresa ENUM('PRIVADA', 'PUBLICA', 'MEI') DEFAULT 'PRIVADA',
        regime_tributario ENUM('SIMPLES_NACIONAL', 'LUCRO_PRESUMIDO', 'LUCRO_REAL') DEFAULT 'SIMPLES_NACIONAL',
        percentual_icms DECIMAL(5,2) DEFAULT 0.00,
        inscricao_estadual VARCHAR(20) NULL,
        inscricao_municipal VARCHAR(20) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql2);
    echo "✓ Tabela configuracoes_fiscais criada!<br>";
    
    echo "<br><h2 style='color:green'>✅ TABELAS CRIADAS COM SUCESSO!</h2>";
    echo "<p><a href='nfe/perfil_tributario.php'>Acessar Perfis Tributários</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>ERRO: " . $e->getMessage() . "</p>";
}
