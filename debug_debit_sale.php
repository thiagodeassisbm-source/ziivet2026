<?php
require_once 'config/configuracoes.php';

// Find the debit sale approx 35.90
echo "Searching for Debit Sale ~35.90 in CONTAS...\n";
$stmt = $pdo->query("SELECT id, id_venda, descricao, valor_total, forma_pagamento_detalhe FROM contas WHERE valor_total > 35.8 AND valor_total < 36.0 ORDER BY id DESC LIMIT 1");
$conta = $stmt->fetch(PDO::FETCH_ASSOC);

if ($conta) {
    print_r($conta);
    $idVenda = $conta['id_venda'];
    
    if ($idVenda) {
        echo "\nFetching VENDA #$idVenda...\n";
        $stmtV = $pdo->prepare("SELECT id, valor_total, desconto FROM vendas WHERE id = ?");
        $stmtV->execute([$idVenda]);
        $venda = $stmtV->fetch(PDO::FETCH_ASSOC);
        print_r($venda);
        
        echo "\nAnalysis:\n";
        echo "Contas (Liquid): " . $conta['valor_total'] . "\n";
        echo "Vendas (Gross): " . $venda['valor_total'] . "\n";
        
        if ($venda['valor_total'] > $conta['valor_total']) {
            echo "Tax WAS applied. Diff: " . ($venda['valor_total'] - $conta['valor_total']) . "\n";
        } else {
            echo "Tax WAS NOT applied or Vendas table has liquid value.\n";
        }
    } else {
        echo "No linked Sale ID found in Contas.\n";
    }
} else {
    echo "No matching transaction found in Contas.\n";
}
