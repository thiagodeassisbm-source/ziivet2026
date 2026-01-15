<?php
/**
 * =========================================================================================
 * ZIIPVET - TESTE DE SEGURANÇA DA API
 * ARQUIVO: test_api_security.php
 * DESCRIÇÃO: Verifica se a API está protegida contra acesso não autorizado
 * =========================================================================================
 */

echo "==========================================================\n";
echo "  TESTE DE SEGURANÇA DA API - ZiipVet\n";
echo "==========================================================\n\n";

// Configuração
$base_url = 'http://localhost:8000';
$api_endpoint = '/api/v1/clientes/index.php';
$full_url = $base_url . $api_endpoint;

echo "🔍 Testando endpoint: {$full_url}\n";
echo "📋 Método: GET (sem autenticação)\n\n";

// Inicializar cURL
$ch = curl_init();

// Configurar requisição SEM cookies de sessão ou tokens
curl_setopt_array($ch, [
    CURLOPT_URL => $full_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_NOBODY => false,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    // NÃO enviar cookies (simula acesso não autorizado)
    CURLOPT_COOKIEFILE => '',
    CURLOPT_COOKIEJAR => '',
]);

// Executar requisição
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$error = curl_error($ch);

curl_close($ch);

// Separar headers e body
$headers = substr($response, 0, $header_size);
$body = substr($response, $header_size);

// Exibir resultado
echo "📊 RESULTADO DO TESTE:\n";
echo "─────────────────────────────────────────────────────────\n";

if ($error) {
    echo "❌ ERRO DE CONEXÃO: {$error}\n";
    echo "─────────────────────────────────────────────────────────\n";
    exit(1);
}

echo "Status HTTP: {$http_code}\n\n";

// Verificar segurança
if ($http_code === 401) {
    echo "✅ SEGURANÇA APROVADA!\n";
    echo "─────────────────────────────────────────────────────────\n";
    echo "A API está corretamente protegida.\n";
    echo "Requisições não autenticadas são bloqueadas com 401.\n\n";
    
    // Tentar decodificar resposta JSON
    $json_response = json_decode($body, true);
    if ($json_response) {
        echo "📄 Resposta da API:\n";
        echo json_encode($json_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    
    echo "\n✓ Teste passou com sucesso!\n";
    exit(0);
    
} elseif ($http_code === 200) {
    echo "🚨 FALHA DE SEGURANÇA CRÍTICA!\n";
    echo "─────────────────────────────────────────────────────────\n";
    echo "A API retornou dados SEM autenticação!\n";
    echo "Qualquer pessoa pode acessar os dados dos clientes.\n\n";
    
    echo "📄 Dados retornados (primeiros 500 caracteres):\n";
    echo substr($body, 0, 500) . "...\n\n";
    
    echo "🔧 AÇÃO NECESSÁRIA:\n";
    echo "1. Verifique se AuthMiddleware::verificar() está sendo chamado\n";
    echo "2. Verifique se a sessão está sendo iniciada corretamente\n";
    echo "3. Revise o arquivo api/v1/clientes/index.php\n\n";
    
    echo "✗ Teste falhou - Corrija a segurança imediatamente!\n";
    exit(1);
    
} elseif ($http_code === 403) {
    echo "⚠️  ACESSO NEGADO (403 Forbidden)\n";
    echo "─────────────────────────────────────────────────────────\n";
    echo "A API está bloqueando o acesso, mas com código incorreto.\n";
    echo "Deveria retornar 401 (Unauthorized) em vez de 403.\n\n";
    
    echo "📄 Resposta:\n";
    echo $body . "\n\n";
    
    echo "✓ Segurança funcional, mas código HTTP incorreto.\n";
    exit(0);
    
} elseif ($http_code === 404) {
    echo "❌ ENDPOINT NÃO ENCONTRADO (404)\n";
    echo "─────────────────────────────────────────────────────────\n";
    echo "Verifique se o endpoint existe:\n";
    echo "{$full_url}\n\n";
    
    echo "🔧 Possíveis causas:\n";
    echo "1. Servidor não está rodando\n";
    echo "2. URL incorreta\n";
    echo "3. Arquivo não existe no caminho especificado\n\n";
    
    echo "✗ Teste falhou - Endpoint não encontrado!\n";
    exit(1);
    
} elseif ($http_code === 500) {
    echo "❌ ERRO INTERNO DO SERVIDOR (500)\n";
    echo "─────────────────────────────────────────────────────────\n";
    echo "Há um erro no código da API.\n\n";
    
    echo "📄 Resposta de erro:\n";
    echo $body . "\n\n";
    
    echo "🔧 Verifique os logs do servidor PHP.\n";
    echo "✗ Teste falhou - Erro no servidor!\n";
    exit(1);
    
} else {
    echo "⚠️  STATUS HTTP INESPERADO: {$http_code}\n";
    echo "─────────────────────────────────────────────────────────\n";
    echo "Código HTTP não reconhecido.\n\n";
    
    echo "📄 Headers:\n";
    echo $headers . "\n\n";
    
    echo "📄 Body:\n";
    echo $body . "\n\n";
    
    echo "✗ Teste inconclusivo!\n";
    exit(1);
}
