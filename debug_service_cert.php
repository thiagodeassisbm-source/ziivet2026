<?php
require_once 'config/configuracoes.php';
require_once 'vendor/autoload.php';

use NFePHP\Common\Certificate;

echo "<h1>Debug Profundo do Certificado no Serviço</h1>";

// 1. Simular o carregamento do NFCeService
$stmtCert = $pdo->query("SELECT * FROM config_certificados WHERE id = 1");
$certConfig = $stmtCert->fetch(PDO::FETCH_ASSOC);

$stmtFiscal = $pdo->query("SELECT * FROM configuracoes_fiscais WHERE id_admin = 1");
$configFiscal = $stmtFiscal->fetch(PDO::FETCH_ASSOC);

echo "<h3>1. Dados no Banco:</h3>";
echo "Senha (config_certificados): [" . ($certConfig['senha_certificado'] ?? 'NULA') . "]<br>";
echo "Arquivo (config_certificados): " . ($certConfig['caminho_arquivo'] ?? 'NULO') . "<br>";
echo "Arquivo (configuracoes_fiscais): " . ($configFiscal['certificado_arquivo'] ?? 'NULO') . "<br>";

$certFilename = basename($certConfig['caminho_arquivo'] ?? $configFiscal['certificado_arquivo'] ?? '');
$certPassword = $certConfig['senha_certificado'] ?? "";
$certPath = __DIR__ . '/uploads/certificados/' . $certFilename;
$legacyPath = __DIR__ . "/legacy_openssl.cnf";

echo "<h3>2. Caminhos:</h3>";
echo "Caminho do Certificado: $certPath<br>";
echo "Caminho do Legacy Config: $legacyPath<br>";

if (!file_exists($certPath)) {
    die("<h2 style='color:red'>ERRO FATAL: Arquivo não existe no disco!</h2>");
}

echo "Tamanho do arquivo: " . filesize($certPath) . " bytes<br>";
if (filesize($certPath) < 1000) {
    die("<h2 style='color:red'>ERRO FATAL: Arquivo muito pequeno, provavelmente upload falhou ou arquivo corrompido!</h2>");
}

echo "<h3>3. Teste de Leitura (Simulando Service):</h3>";

$certContent = file_get_contents($certPath);
$certificate = null;
$erroFinal = "";

// TENTATIVA 1: Normal
try {
    echo "Tentando modo Normal... ";
    $certificate = Certificate::readPfx($certContent, $certPassword);
    echo "<span style='color:green'>SUCESSO!</span><br>";
} catch (Exception $e) {
    echo "<span style='color:red'>FALHOU: " . $e->getMessage() . "</span><br>";
    $erroFinal = $e->getMessage();
    
    // TENTATIVA 2: Legacy
    echo "Tentando modo Legacy... ";
    $originalEnv = getenv('OPENSSL_CONF');
    putenv("OPENSSL_CONF=" . $legacyPath);
    
    try {
        $certificate = Certificate::readPfx($certContent, $certPassword);
        echo "<span style='color:green'>SUCESSO (com Legacy)!</span><br>";
    } catch (Exception $e2) {
        echo "<span style='color:red'>FALHOU TAMBÉM: " . $e2->getMessage() . "</span><br>";
        
        // Debug OpenSSL Errors
        echo "<br><b>Erros OpenSSL:</b><br>";
        while ($msg = openssl_error_string()) {
            echo $msg . "<br>";
        }
        
    } finally {
        if ($originalEnv !== false) putenv("OPENSSL_CONF=$originalEnv");
        else putenv("OPENSSL_CONF");
    }
}

if ($certificate) {
    echo "<h2 style='color:green'>DIAGNÓSTICO: O certificado está FUNCIONANDO!</h2>";
    echo "Se está dando erro no teste de emissão, verifique se a venda existe ou outros dados.";
} else {
    echo "<h2 style='color:red'>DIAGNÓSTICO: O certificado NÃO PÔDE ser lido.</h2>";
    echo "Verifique se a senha [" . $certPassword . "] está realmente correta para este arquivo.";
}
