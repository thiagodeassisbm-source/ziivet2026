<?php
require_once 'config/configuracoes.php';

try {
    // 1. Update Suprimento (if not fixed yet)
    // Find suprimento entries for caixa 43 (assuming user is on caixa 43 based on screenshots)
    // Or generally fix all suprimentos.
    $stmt = $pdo->query("UPDATE contas SET forma_pagamento_detalhe = 'Dinheiro' WHERE descricao LIKE '%SUPRIMENTO%' AND (forma_pagamento_detalhe IS NULL OR forma_pagamento_detalhe = '')");
    echo "Fixed Suprimentos: " . $stmt->rowCount() . "\n";
    
    // 2. Update Sale 143.60
    $stmt = $pdo->query("UPDATE contas SET forma_pagamento_detalhe = 'Cartão de Crédito Master' WHERE valor_total = 143.60 AND (forma_pagamento_detalhe IS NULL OR forma_pagamento_detalhe = '')");
    echo "Fixed Sale 143.60: " . $stmt->rowCount() . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
