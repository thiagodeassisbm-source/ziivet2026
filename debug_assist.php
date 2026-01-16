<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = "preciso fazer a configuração fiscal";
$message_lower = mb_strtolower(trim($message));
$reply = "";

// 5.1 NOTAS FISCAIS (NFC-e e NFS-e) E CONFIGURAÇÕES
if (strpos($message_lower, 'nota') !== false || strpos($message_lower, 'fiscal') !== false || strpos($message_lower, 'nfe') !== false || strpos($message_lower, 'nfce') !== false || strpos($message_lower, 'nfse') !== false || strpos($message_lower, 'tribut') !== false || strpos($message_lower, 'csc') !== false) {
    $reply = "O módulo fiscal está completo! Aqui está o resumo: \n\n" .
             "🛒 **NFC-e (Consumidor):** Emitida direto pelo PDV após a venda. Você pode configurar o ambiente (Produção ou Homologação) no menu **'NFC-e > Configurações Fiscais'**. Lembre-se de configurar o **CSC** correto!\n\n" .
             "💼 **NFS-e (Serviços):** Para notas de serviço, acesse o menu **'NFS-e (Serviços)'**. Lá você configura sua Inscrição Municipal e credenciais da prefeitura para emitir notas de consultas e exames.\n\n" .
             "Precisa de ajuda com o Certificado Digital? Ele também é configurado em **Configurações Fiscais**.";
}

echo "Reply: $reply\n";
echo "JSON: " . json_encode(['reply' => $reply]);
echo "\nJSON Error: " . json_last_error_msg();
?>
