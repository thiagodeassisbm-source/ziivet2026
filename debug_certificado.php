<?php
require_once 'config/configuracoes.php';

echo "<h1>Diagnóstico de Certificado Digital</h1>";

// 1. Buscar configurações
$stmt = $pdo->query("SELECT * FROM config_certificados WHERE id = 1");
$configCert = $stmt->fetch(PDO::FETCH_ASSOC);

$stmtFiscal = $pdo->query("SELECT * FROM configuracoes_fiscais WHERE id_admin = 1");
$configFiscal = $stmtFiscal->fetch(PDO::FETCH_ASSOC);

echo "<h3>1. Configurações salvas:</h3>";
echo "<b>Tabela config_certificados:</b><br>";
if ($configCert) {
    echo "ID: " . $configCert['id'] . "<br>";
    echo "Arquivo: " . $configCert['caminho_arquivo'] . "<br>";
    echo "Senha: " . $configCert['senha_certificado'] . "<br>"; // Mostrando para debug
} else {
    echo "Não encontrada.<br>";
}

echo "<br><b>Tabela configuracoes_fiscais (Legado):</b><br>";
echo "Arquivo: " . ($configFiscal['certificado_arquivo'] ?? 'Nenhum') . "<br>";

// 2. Localizar arquivo
$arquivoNome = basename($configCert['caminho_arquivo'] ?? $configFiscal['certificado_arquivo'] ?? '');
$caminhoCompleto = __DIR__ . '/uploads/certificados/' . $arquivoNome;

echo "<h3>2. Verificação do Arquivo:</h3>";
echo "Procurando em: $caminhoCompleto<br>";

if (file_exists($caminhoCompleto)) {
    echo "Status: <span style='color:green'>Arquivo Encontrado!</span><br>";
    echo "Tamanho: " . filesize($caminhoCompleto) . " bytes<br>";
    
    // 3. Testar leitura com senha
    echo "<h3>3. Teste de Senha:</h3>";
    $pfxContent = file_get_contents($caminhoCompleto);
    $senha = $configCert['senha_certificado'] ?? '';
    
    $certs = [];
    $isVAlid = openssl_pkcs12_read($pfxContent, $certs, $senha);
    
    if ($isVAlid) {
        echo "<h2 style='color:green'>SUCESSO! O certificado abriu com a senha correta.</h2>";
        $dados = openssl_x509_parse($certs['cert']);
        echo "Emitido para: " . $dados['subject']['CN'] . "<br>";
        echo "Válido até: " . date('d/m/Y H:i:s', $dados['validTo_time_t']);
    } else {
        echo "<h2 style='color:red'>FALHA! A senha não confere com o arquivo.</h2>";
        echo "Erro OpenSSL: " . openssl_error_string();
    }
} else {
    echo "Status: <span style='color:red'>Arquivo NÃO encontrado!</span>";
}
