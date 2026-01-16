<?php
require_once 'config/configuracoes.php';

try {
    echo "Criando tabelas NFC-e...\n\n";
    
    $sql = file_get_contents('sql/criar_tabelas_nfe_simples.sql');
    
    // Separar por ponto e vírgula
    $comandos = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($comandos as $comando) {
        if (empty($comando) || strpos($comando, '--') === 0) {
            continue;
        }
        
        try {
            $pdo->exec($comando);
            echo "✓ Comando executado\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "✗ Erro: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n✅ Tabelas criadas com sucesso!\n";
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
