<?php
require_once 'config/configuracoes.php';

try {
    echo "Executando criação de tabelas NF-e...\n\n";
    
    $sql = file_get_contents('sql/criar_tabelas_nfe.sql');
    
    // Separar comandos por ponto e vírgula
    $comandos = explode(';', $sql);
    
    $executados = 0;
    $erros = 0;
    
    foreach ($comandos as $comando) {
        $comando = trim($comando);
        if (empty($comando) || strpos($comando, '--') === 0) {
            continue;
        }
        
        try {
            $pdo->exec($comando);
            $executados++;
        } catch (PDOException $e) {
            // Ignorar erros de "já existe"
            if (strpos($e->getMessage(), 'already exists') === false && 
                strpos($e->getMessage(), 'Duplicate column') === false) {
                echo "ERRO: " . $e->getMessage() . "\n";
                $erros++;
            }
        }
    }
    
    echo "\n✅ Processo concluído!\n";
    echo "Comandos executados: $executados\n";
    echo "Erros: $erros\n";
    
} catch (Exception $e) {
    echo "ERRO FATAL: " . $e->getMessage() . "\n";
}
