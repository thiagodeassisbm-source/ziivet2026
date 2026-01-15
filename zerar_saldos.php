<?php
require_once 'config/configuracoes.php';

// Verificação de segurança simples
if (php_sapi_name() !== 'cli' && !isset($_GET['confirm'])) {
    die("Este script deve ser executado via linha de comando ou com ?confirm=1 na URL.");
}

try {
    echo "Iniciando zeramento de saldos das contas financeiras...\n";
    
    // Atualiza o saldo_inicial para 0 e situacao_saldo para Positivo
    $sql = "UPDATE contas_financeiras SET saldo_inicial = 0, situacao_saldo = 'Positivo'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $linhas = $stmt->rowCount();
    
    echo "Sucesso! $linhas contas tiveram seus saldos zerados.\n";
    echo "Agora a listagem deve exibir R$ 0,00.\n";
    
} catch (Exception $e) {
    echo "Erro ao zerar saldos: " . $e->getMessage() . "\n";
}
