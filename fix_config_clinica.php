<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/configuracoes.php';

echo "<h1>Atualizando tabela config_clinica</h1>";

// Primeiro, verificar estrutura atual
echo "<h2>Estrutura atual:</h2>";
try {
    $stmt = $pdo->query("DESCRIBE config_clinica");
    echo "<pre>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
    echo "</pre>";
} catch (PDOException $e) {
    echo "Tabela não existe, criando...<br>";
}

// Criar tabela se não existir
$sql_create = "CREATE TABLE IF NOT EXISTS config_clinica (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cnpj VARCHAR(20),
    razao_social VARCHAR(255),
    nome_fantasia VARCHAR(255),
    inscricao_estadual VARCHAR(20),
    inscricao_municipal VARCHAR(20),
    simples_nacional VARCHAR(10),
    regime_tributario VARCHAR(10),
    regime_especial VARCHAR(10),
    cep VARCHAR(10),
    tipo_logradouro VARCHAR(50),
    logradouro VARCHAR(255),
    complemento VARCHAR(100),
    tipo_bairro VARCHAR(50),
    bairro VARCHAR(100),
    municipio VARCHAR(255),
    numero VARCHAR(20),
    email_nf VARCHAR(255),
    ddd_nf VARCHAR(3),
    telefone_nf VARCHAR(20)
)";

try {
    $pdo->exec($sql_create);
    echo "✓ Tabela config_clinica verificada/criada<br>";
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage() . "<br>";
}

// Adicionar colunas que possam estar faltando
$colunas = [
    "ALTER TABLE config_clinica ADD COLUMN tipo_logradouro VARCHAR(50)",
    "ALTER TABLE config_clinica ADD COLUMN tipo_bairro VARCHAR(50)",
    "ALTER TABLE config_clinica ADD COLUMN regime_especial VARCHAR(10)",
    "ALTER TABLE config_clinica ADD COLUMN email_nf VARCHAR(255)",
    "ALTER TABLE config_clinica ADD COLUMN ddd_nf VARCHAR(3)",
    "ALTER TABLE config_clinica ADD COLUMN telefone_nf VARCHAR(20)"
];

foreach ($colunas as $sql) {
    try {
        $pdo->exec($sql);
        echo "✓ Executado: " . substr($sql, 40, 30) . "...<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "⚠ Coluna já existe<br>";
        } else {
            echo "Nota: " . $e->getMessage() . "<br>";
        }
    }
}

echo "<h2>Estrutura FINAL:</h2>";
$stmt = $pdo->query("DESCRIBE config_clinica");
echo "<pre>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "</pre>";

echo "<br><a href='nota-fiscal/configuracao-clinica.php'>Voltar para Dados da Empresa</a>";
