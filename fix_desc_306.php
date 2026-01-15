<?php
require_once 'config/configuracoes.php';

// Fix ID 306 description
// Current state: it has correct liquid value (34.97) but wrong description.
// Values: Bruto=35.90, Taxa=2.6%, Liq=34.97. TaxaVal=0.93.

$id = 306;
// Get current desc
$stmt = $pdo->prepare("SELECT descricao FROM contas WHERE id = ?");
$stmt->execute([$id]);
$desc = $stmt->fetchColumn();

if ($desc) {
    echo "Old Desc: $desc\n";
    // Check if already fixed
    if (strpos($desc, 'Taxa:') === false) {
        $extra = " | Taxa: 2.6% (R$ 0,93) | Bruto: R$ 35,90 → Líquido: R$ 34,97";
        $newDesc = $desc . $extra;
        
        $upd = $pdo->prepare("UPDATE contas SET descricao = ? WHERE id = ?");
        $upd->execute([$newDesc, $id]);
        echo "Updated Desc: $newDesc\n";
    } else {
        echo "Already has tax info.\n";
    }
} else {
    echo "ID 306 not found.\n";
}
