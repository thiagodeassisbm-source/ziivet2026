<?php
require_once 'config/configuracoes.php';

$id_admin = 1;

echo "Verificando certificado no banco de dados:\n\n";

$stmt = $pdo->prepare("SELECT certificado_nome, certificado_validade, LENGTH(certificado_arquivo) as tamanho FROM configuracoes_fiscais WHERE id_admin = ?");
$stmt->execute([$id_admin]);
$cert = $stmt->fetch(PDO::FETCH_ASSOC);

if ($cert) {
    echo "Nome: " . ($cert['certificado_nome'] ?? 'N/A') . "\n";
    echo "Validade: " . ($cert['certificado_validade'] ?? 'N/A') . "\n";
    echo "Tamanho: " . ($cert['tamanho'] ?? 0) . " bytes\n";
    
    if ($cert['tamanho'] > 0) {
        echo "\n✅ Certificado ENCONTRADO no banco de dados!\n";
        echo "Tamanho: " . number_format($cert['tamanho'] / 1024, 2) . " KB\n";
    } else {
        echo "\n❌ Certificado NÃO encontrado no banco.\n";
    }
} else {
    echo "❌ Nenhum registro encontrado.\n";
}
?>
