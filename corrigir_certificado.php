<?php
require_once 'config/configuracoes.php';

echo "<h1>Ferramenta de Correção de Certificado (Tentativa Avançada)</h1>";

// 1. Buscar dados
$stmtCert = $pdo->query("SELECT * FROM config_certificados WHERE id = 1");
$certConfig = $stmtCert->fetch(PDO::FETCH_ASSOC);

if (!$certConfig) die("Nenhum certificado configurado.");

$arquivo = $certConfig['caminho_arquivo'];
$senha = $certConfig['senha_certificado'];
$caminhoOrigem = __DIR__ . '/uploads/certificados/' . $arquivo;
$caminhoConvertido = __DIR__ . '/uploads/certificados/convertido_' . $arquivo;
$caminhoPem = __DIR__ . '/uploads/certificados/temp.pem';

// 2. Localizar XAMPP e OpenSSL
$opensslBin = "";
$xamppBase = "";

$caminhosPossiveis = [
    "C:\\xampp", "D:\\xampp", "F:\\xampp"
];

foreach ($caminhosPossiveis as $base) {
    if (file_exists("$base\\apache\\bin\\openssl.exe")) {
        $xamppBase = $base;
        $opensslBin = "\"$base\\apache\\bin\\openssl.exe\"";
        break;
    }
}

if (!$opensslBin) {
    // Tentar fallback sistema
    $opensslBin = "openssl";
    // Tentar adivinhar base para módulos
    if (file_exists("C:\\xampp")) $xamppBase = "C:\\xampp";
    elseif (file_exists("F:\\xampp")) $xamppBase = "F:\\xampp";
}

echo "XAMPP Base: $xamppBase<br>";
echo "OpenSSL Bin: $opensslBin<br>";

// 3. Criar arquivo de configuração OpenSSL customizado
$modulesPath = str_replace("\\", "/", "$xamppBase/apache/bin"); // Onde estão legacy.dll
// Ou talvez em lib/ossl-modules?
if (is_dir("$xamppBase/apache/conf/ossl-modules")) {
    $modulesPath = str_replace("\\", "/", "$xamppBase/apache/conf/ossl-modules");
} elseif (is_dir("$xamppBase/apache/lib/ossl-modules")) {
     $modulesPath = str_replace("\\", "/", "$xamppBase/apache/lib/ossl-modules");
}

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

$configFile = __DIR__ . '/openssl_fix.cnf';
file_put_contents($configFile, $cnfContent);

echo "Modules Path Detectado: $modulesPath<br>";

// 4. Executar comando com variáveis de ambiente forçadas
echo "<h3>Iniciando conversão...</h3>";

// Definindo variáveis de ambiente para o comando
$envPrefix = "set OPENSSL_MODULES=$modulesPath&& set OPENSSL_CONF=$configFile&& ";

// Passo 1: Exportar para PEM
$cmd1 = "$envPrefix $opensslBin pkcs12 -provider legacy -provider default -in \"$caminhoOrigem\" -out \"$caminhoPem\" -nodes -passin pass:\"$senha\" 2>&1";

echo "Comando: $cmd1<br>";
echo "Executando exportação...<br>";

$output1 = shell_exec($cmd1);
echo "<pre>$output1</pre>";

if (file_exists($caminhoPem) && filesize($caminhoPem) > 0) {
    echo "<span style='color:green'>Passo 1 OK! PEM gerado.</span><br>";
    
    // Passo 2: Re-criar PFX (Agora sem legacy, usando padrão moderno)
    // Importante: No passo 2 NÃO usamos o config legacy para garantir que saia moderno
    $cmd2 = "$opensslBin pkcs12 -export -in \"$caminhoPem\" -out \"$caminhoConvertido\" -passout pass:\"$senha\" 2>&1";
    
    echo "Executando recriação...<br>";
    $output2 = shell_exec($cmd2);
    echo "<pre>$output2</pre>";
    
    if (file_exists($caminhoConvertido) && filesize($caminhoConvertido) > 0) {
        echo "<h2 style='color:green'>SUCESSO! Certificado convertido.</h2>";
        
        // Atualizar banco
        $novoNome = "convertido_" . $arquivo;
        $pdo->prepare("UPDATE config_certificados SET caminho_arquivo = ? WHERE id = 1")->execute([$novoNome]);
        $pdo->prepare("UPDATE configuracoes_fiscais SET certificado_arquivo = ? WHERE id_admin = 1")->execute([$novoNome]);
        
        echo "Banco atualizado: <b>$novoNome</b><br>";
        echo "<br><a href='nfe/verificar_configuracoes.php'>TESTAR EMISSÃO AGORA</a>";
        
        // Limpeza
        @unlink($caminhoPem);
        @unlink($configFile);
    } else {
        echo "<h2 style='color:red'>ERRO no Passo 2. Converter PEM para PFX falhou.</h2>";
    }
} else {
    echo "<h2 style='color:red'>ERRO no Passo 1. Ler arquivo original falhou.</h2>";
    echo "Dica: Verifique o caminho das DLLs do OpenSSL no log acima.";
}
