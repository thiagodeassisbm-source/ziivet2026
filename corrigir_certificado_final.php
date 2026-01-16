<?php
require_once 'config/configuracoes.php';

echo "<h1>Busca e Conversão de Certificado</h1>";

// 1. Dados
$stmtCert = $pdo->query("SELECT * FROM config_certificados WHERE id = 1");
$certConfig = $stmtCert->fetch(PDO::FETCH_ASSOC);
$arquivo = $certConfig['caminho_arquivo'];
$senha = $certConfig['senha_certificado'];
$caminhoOrigem = __DIR__ . '/uploads/certificados/' . $arquivo;
$caminhoConvertido = __DIR__ . '/uploads/certificados/convertido_' . $arquivo;
$caminhoPem = __DIR__ . '/uploads/certificados/temp.pem';

// 2. Encontrar XAMPP e legacy.dll
$xamppBase = "C:\\xampp"; // Padrão
if (file_exists("F:\\xampp")) $xamppBase = "F:\\xampp";

$opensslBin = "$xamppBase\\apache\\bin\\openssl.exe";
if (!file_exists($opensslBin)) $opensslBin = "openssl";

// Procurar legacy.dll
$legacyDllPath = "";
$possiblePaths = [
    "$xamppBase\\apache\\bin\\legacy.dll",
    "$xamppBase\\apache\\lib\\ossl-modules\\legacy.dll",
    "$xamppBase\\apache\\modules\\legacy.dll",
    "$xamppBase\\php\\extras\\ssl\\legacy.dll"
];

foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $legacyDllPath = $path;
        break;
    }
}

if (!$legacyDllPath) {
    // Busca recursiva desesperada na pasta apache
    $legacyDllPath = trim(shell_exec("dir /b /s \"$xamppBase\\apache\\legacy.dll\" 2>nul"));
}

echo "OpenSSL EXE: $opensslBin<br>";
echo "Legacy DLL: " . ($legacyDllPath ?: "NÃO ENCONTRADA") . "<br>";

if ($legacyDllPath) {
    // Configurar módulos
    $modulesPath = dirname($legacyDllPath);
    $modulesPathForward = str_replace("\\", "/", $modulesPath); // Formato UNIX para config
    
    // Config Especial
    $cnfContent = <<<EOT
openssl_conf = openssl_init

[openssl_init]
providers = provider_sect

[provider_sect]
default = default_sect
legacy = legacy_sect

[default_sect]
activate = 1

[legacy_sect]
activate = 1
module = legacy.dll
EOT;
    $configFile = __DIR__ . '/openssl_fix_final.cnf';
    file_put_contents($configFile, $cnfContent);
    
    // Comando Mágico
    $cmd = "set OPENSSL_MODULES=$modulesPath&& set OPENSSL_CONF=$configFile&& \"$opensslBin\" pkcs12 -provider legacy -provider default -in \"$caminhoOrigem\" -out \"$caminhoPem\" -nodes -passin pass:\"$senha\" 2>&1";
    
    echo "<h3>Tentando Exportar...</h3>";
    $out = shell_exec($cmd);
    echo "<pre>$out</pre>";
    
    if (file_exists($caminhoPem) && filesize($caminhoPem) > 0) {
        // Recriar Moderno
        $cmd2 = "\"$opensslBin\" pkcs12 -export -in \"$caminhoPem\" -out \"$caminhoConvertido\" -passout pass:\"$senha\" 2>&1";
        shell_exec($cmd2);
        
        if (file_exists($caminhoConvertido)) {
             $novoNome = "convertido_" . $arquivo;
             echo "<h2 style='color:green'>SUCESSO TOTAL! Certificado modernizado.</h2>";
             $pdo->prepare("UPDATE config_certificados SET caminho_arquivo = ? WHERE id = 1")->execute([$novoNome]);
             $pdo->prepare("UPDATE configuracoes_fiscais SET certificado_arquivo = ? WHERE id_admin = 1")->execute([$novoNome]);
             echo "<a href='nfe/verificar_configuracoes.php'>IR PARA TESTE DE EMISSÃO</a>";
        }
    } else {
        echo "<h2 style='color:red'>Falha na exportação.</h2>";
        echo "O XAMPP local não consegue carregar o módulo Legacy.";
    }

} else {
    echo "<h2 style='color:red'>ERRO FATAL: legacy.dll não existe no seu XAMPP.</h2>";
    echo "Você precisa baixar uma versão completa do OpenSSL ou exportar o certificado manualmente.";
}
