<?php
require_once 'config/configuracoes.php';

try {
    echo "Fixing Debit Transaction Tax...\n";
    
    // Find the debit transaction (Gross 35.90)
    // We assume the one with NO ID specified yet, but we saw it was ID 306 in debug output (implied from context or I should check).
    // Let's search by value 35.90 and "Débito" name if possible, or just value.
    // Ideally use data from debug_debit_sale.php which found it.
    
    $stmt = $pdo->query("SELECT id, valor_total FROM contas WHERE valor_total = 35.90 ORDER BY id DESC LIMIT 1");
    $conta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($conta) {
        $id = $conta['id'];
        $valorBruto = 35.90;
        $taxa = 2.6; // 2.6% based on config debug
        
        $valorLiquido = $valorBruto * (1 - ($taxa / 100));
        $valorLiquido = number_format($valorLiquido, 2, '.', ''); // 34.97
        
        echo "Updating Conta #$id...\n";
        echo "Bruto: $valorBruto -> Liquido: $valorLiquido (Taxa $taxa%)\n";
        
        $upd = $pdo->prepare("UPDATE contas SET valor_total = ?, valor_parcela = ? WHERE id = ?");
        $upd->execute([$valorLiquido, $valorLiquido, $id]);
        
        echo "Updated successfully!\n";
    } else {
        echo "Transaction not found.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
