<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/configuracoes.php';
require_once 'vendor/autoload.php';

use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Common\Standardize;

// Patch de constantes
if (!defined('SOAP_1_1')) define('SOAP_1_1', 1);
if (!defined('SOAP_1_2')) define('SOAP_1_2', 2);

echo "<h1>Teste de Conexão SEFAZ (Consulta Status)</h1>";

try {
    // 1. Configuração Manual (Copiada do Service)
    $stmtFiscal = $pdo->query("SELECT * FROM configuracoes_fiscais WHERE id_admin = 1");
    $configFiscal = $stmtFiscal->fetch(PDO::FETCH_ASSOC);

    $stmtEmpresa = $pdo->query("SELECT * FROM config_clinica WHERE id = 1");
    $configEmpresa = $stmtEmpresa->fetch(PDO::FETCH_ASSOC);
    
    $stmtCert = $pdo->query("SELECT * FROM config_certificados WHERE id = 1");
    $certConfig = $stmtCert->fetch(PDO::FETCH_ASSOC);

    $cnpj = preg_replace('/[^0-9]/', '', $configEmpresa['cnpj']);
    
    $configJson = json_encode([
        "atualizacao" => date("Y-m-d H:i:s"),
        "tpAmb" => 2,
        "razaosocial" => $configEmpresa['razao_social'],
        "siglaUF" => "GO",
        "cnpj" => $cnpj,
        "schemes" => "PL_009_V4",
        "versao" => "4.00",
        "tokenIBPT" => "",
        "CSC" => $configFiscal['csc_producao'] ?? "",
        "CSCid" => $configFiscal['csc_id'] ?? ""
    ]);

    // 2. Certificado
    $certFilename = basename($certConfig['caminho_arquivo'] ?? '');
    $certPassword = $certConfig['senha_certificado'] ?? "";
    $certPath = __DIR__ . '/uploads/certificados/' . $certFilename;
    $certContent = file_get_contents($certPath);
    
    $certificate = Certificate::readPfx($certContent, $certPassword);
    
    // 3. Tools
    $tools = new Tools($configJson, $certificate);
    $tools->model('65');

    echo "<h3>Tentando consultar Status do Serviço...</h3>";
    $resp = $tools->sefazStatus();
    
    $st = new Standardize();
    $std = $st->toStd($resp);

    echo "<pre>";
    print_r($std);
    echo "</pre>";

    if (!empty($std->dhRecbto)) {
        echo "<h2 style='color:green'>SUCESSO! Hora SEFAZ: {$std->dhRecbto}</h2>";
    } else {
        echo "<h2 style='color:red'>FALHA: Sem data de recebimento.</h2>";
    }

} catch (Exception $e) {
    echo "<h2 style='color:red'>ERRO DE COMUNICAÇÃO:</h2>";
    echo $e->getMessage();
    echo "<br><br>Trace:<pre>" . $e->getTraceAsString() . "</pre>";
}
