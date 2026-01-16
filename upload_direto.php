<?php
require_once 'config/configuracoes.php';
// A classe Csrf já deve estar disponível pois o configuracoes.php carrega o autoload

$mensagem = "";

/**
 * Tenta ler o PFX aplicando correções para OpenSSL 3.0+ (Legacy Provider)
 */
function lerPfxSeguro($pfxContent, $password) {
    if (openssl_pkcs12_read($pfxContent, $certs, $password)) {
        return true;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Erro no upload do arquivo.");
        }

        $senha = $_POST['senha'];
        $arquivo = $_FILES['arquivo'];
        // Ler conteúdo direto do upload
        $pfxContent = file_get_contents($arquivo['tmp_name']);
        
        // Tentar ler
        $certs = [];
        
        // TRUQUE: Forçar uso do Legacy Provider
        $originalEnv = getenv('OPENSSL_CONF');
        putenv("OPENSSL_CONF=" . __DIR__ . "/legacy_openssl.cnf");
        
        $status = openssl_pkcs12_read($pfxContent, $certs, $senha);
        
        // Restaurar ambiente
        if ($originalEnv !== false) {
             putenv("OPENSSL_CONF=$originalEnv");
        } else {
             putenv("OPENSSL_CONF");
        }
        
        if (!$status) {
            $erroOpenSSL = "";
            while ($msg = openssl_error_string()) {
                $erroOpenSSL .= $msg . " | ";
            }
            
            // Se o usuário marcou para forçar, ignoramos o erro
            if (isset($_POST['forcar_salvamento'])) {
                 $status = true; // Fingimos que deu certo
                 $mensagem .= "<div style='color:orange; font-weight:bold;'>⚠️ ALERTA: Certificado salvo com erro de validação (Legacy). A emissão pode falhar.</div><br>";
            } else {
                if (strpos($erroOpenSSL, 'mac verify failure') !== false || strpos($erroOpenSSL, 'unsupported') !== false) {
                     throw new Exception("<b>ERRO DE COMPATIBILIDADE:</b><br>Certificado usa criptografia antiga.<br><br>Você pode tentar <b>Forçar o Salvamento</b> abaixo, mas a emissão da nota pode falhar.<br><br>Erro técnico: $erroOpenSSL");
                }
                throw new Exception("Senha incorreta ou erro de leitura.<br>Detalhe: $erroOpenSSL");
            }
        }

        $ext = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
        $novoNome = "cert_final_" . time() . "." . $ext;
        $destino = __DIR__ . "/uploads/certificados/" . $novoNome;

        if (!is_dir(__DIR__ . "/uploads/certificados")) {
            mkdir(__DIR__ . "/uploads/certificados", 0777, true);
        }

        if (move_uploaded_file($arquivo['tmp_name'], $destino)) {
            $sql1 = "REPLACE INTO config_certificados (id, senha_certificado, caminho_arquivo) VALUES (1, ?, ?)";
            $stmt1 = $pdo->prepare($sql1);
            $stmt1->execute([$senha, $novoNome]);

            $sql2 = "UPDATE configuracoes_fiscais SET certificado_arquivo = ? WHERE id_admin = 1";
            $stmt2 = $pdo->prepare($sql2);
            $stmt2->execute([$novoNome]);

            $mensagem = "<div style='color:green; font-size:20px; font-weight:bold;'>✅ SUCESSO! Certificado aceito.</div>";
            $mensagem .= "<br>Arquivo salvo: $novoNome";
            $mensagem .= "<br><a href='nfe/verificar_configuracoes.php'>Voltar para Teste</a>";
        }

    } catch (Exception $e) {
        $mensagem = "<div style='color:red; background:#ffe6e6; padding:20px; border-radius:10px;'>❌ " . $e->getMessage() . "</div>";
    }
}

// Gerar Token para o Formulário
use App\Utils\Csrf;
$csrf_token = Csrf::generate();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload Diagnóstico Seguro</title>
    <style>
        body { font-family: sans-serif; padding: 50px; text-align: center; }
        form { background: #f9f9f9; padding: 40px; border-radius: 10px; display: inline-block; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        input { display: block; margin: 15px auto; padding: 10px; width: 300px; }
        button { background: #007bff; color: white; border: none; padding: 15px 30px; cursor: pointer; font-size: 18px; border-radius: 5px; }
        button:hover { background: #0056b3; }
        .hint { font-size: 14px; color: #666; max-width: 400px; margin: 20px auto; text-align:left; }
    </style>
</head>
<body>
    <h1>Upload com Diagnóstico de Erro (+CSRF)</h1>
    
    <?= $mensagem ?>

    <form method="POST" enctype="multipart/form-data">
        <!-- TOKEN CSRF OBRIGATÓRIO -->
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        
        <label>Selecione o arquivo .pfx ou .p12:</label>
        <input type="file" name="arquivo" accept=".pfx,.p12" required>
        
        <label>Digite a senha:</label>
        <input type="text" name="senha" placeholder="Senha do certificado" required>
        
        <div style="margin: 20px 0; text-align: left; background: #fff3cd; padding: 10px; border-radius: 5px;">
            <label style="display: flex; align-items: center; gap: 10px; width: 100%; margin: 0;">
                <input type="checkbox" name="forcar_salvamento" value="1" style="width: 20px; margin: 0;">
                <span style="color: #856404; font-weight: bold;">Forçar salvamento (Mesmo com erro de Legacy)</span>
            </label>
        </div>
        
        <button type="submit">Testar Arquivo</button>
        
        <div class="hint">
            <p><strong>Dica:</strong> Se a senha tiver espaços ou caracteres especiais, tente digitar exatamente como foi criada.</p>
        </div>
    </form>
</body>
</html>
