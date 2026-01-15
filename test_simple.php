<?php
/**
 * TESTE SIMPLES DE SEGURANÇA DA API
 */

$url = 'http://localhost:8000/api/v1/clientes/index.php';

echo "Testando segurança da API...\n";
echo "URL: {$url}\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status HTTP: {$http_code}\n";

if ($http_code === 401) {
    echo "\n✅ SEGURANÇA APROVADA!\n";
    echo "API bloqueou acesso não autorizado.\n\n";
    
    $json = json_decode($response, true);
    if ($json) {
        echo "Resposta:\n";
        print_r($json);
    }
} elseif ($http_code === 200) {
    echo "\n🚨 FALHA DE SEGURANÇA!\n";
    echo "API retornou dados sem autenticação!\n";
    echo "Dados: " . substr($response, 0, 200) . "...\n";
} else {
    echo "\nStatus inesperado: {$http_code}\n";
    echo "Resposta: {$response}\n";
}
