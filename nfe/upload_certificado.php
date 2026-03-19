<?php
/**
 * ZIIPVET - Upload de Certificado Digital A1
 * VERSÃO ATUALIZADA - Salva em config_certificados
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: configuracoes_nfe.php?aba=certificado');
    exit;
}

try {
    // Verificar se arquivo foi enviado
    if (!isset($_FILES['certificado_arquivo']) || $_FILES['certificado_arquivo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Nenhum arquivo enviado ou erro no upload.');
    }
    
    $arquivo = $_FILES['certificado_arquivo'];
    $senha = $_POST['certificado_senha'] ?? '';
    $email = $_POST['email_responsavel'] ?? '';
    
    if (empty($senha)) {
        throw new Exception('A senha do certificado é obrigatória.');
    }
    
    // Verificar extensão
    $ext = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['pfx', 'p12'])) {
        throw new Exception('O arquivo deve ter extensão .pfx ou .p12');
    }
    
    // Tentar ler o certificado para validar senha e extrair validade
    $conteudo = file_get_contents($arquivo['tmp_name']);
    $certs = [];
    $validade = date('Y-m-d', strtotime('+1 year')); // Validade padrão
    $emissor = 'Desconhecido';
    
    if (function_exists('openssl_pkcs12_read')) {
        // Tentar ler com configuração Legacy se falhar
        $status = @openssl_pkcs12_read($conteudo, $certs, $senha);
        
        // Se falhar, tentar com variável de ambiente (truque)
        if (!$status) {
            $originalEnv = getenv('OPENSSL_CONF');
            putenv("OPENSSL_CONF=" . __DIR__ . "/legacy_openssl.cnf");
            $status = @openssl_pkcs12_read($conteudo, $certs, $senha);
            if ($originalEnv !== false) putenv("OPENSSL_CONF=$originalEnv");
            else putenv("OPENSSL_CONF");
        }

        if ($status) {
            // SUCESSO! Senha correta
            $certInfo = openssl_x509_parse($certs['cert']);
            if (isset($certInfo['validTo_time_t'])) {
                $validade = date('Y-m-d', $certInfo['validTo_time_t']);
            }
            if (isset($certInfo['issuer']['O'])) {
                $emissor = $certInfo['issuer']['O'];
            }
        } else {
            // Se usuário marcou para forçar, ignoramos o erro
            if (isset($_POST['forcar_salvamento'])) {
                // Segue o fluxo...
            } else {
                $erroOpenSSL = "";
                while ($msg = openssl_error_string()) {
                    $erroOpenSSL .= $msg . " ";
                }
                
                if (strpos($erroOpenSSL, 'mac verify failure') !== false || strpos($erroOpenSSL, 'unsupported') !== false) {
                     throw new Exception('Certificado com criptografia antiga (Legacy). Marque a opção "Forçar salvamento" para continuar.');
                }
                
                throw new Exception('Senha incorreta para este certificado ou arquivo inválido.');
            }
        }
    }
    
    $nome = $arquivo['name'];
    
    // Criar pasta em locais candidatos.
    // PRIORIDADE: app/uploads/certificados (visível no File Manager que você usa)
    // FALLBACK: public_html/uploads/certificados
    $pastasCandidatas = [
        dirname(__DIR__) . '/uploads/certificados',      // /public_html/app/uploads/certificados
        dirname(__DIR__, 2) . '/uploads/certificados',   // /public_html/uploads/certificados
    ];

    // Salvar arquivo
    $nomeArquivo = 'cert_' . $id_admin . '_' . time() . '.' . $ext;
    $caminhoCompleto = null;
    $ultimoErro = '';

    foreach ($pastasCandidatas as $pasta) {
        if (!is_dir($pasta)) {
            @mkdir($pasta, 0755, true);
        }

        if (!is_dir($pasta) || !is_writable($pasta)) {
            $ultimoErro = "Pasta sem permissão de escrita: {$pasta}";
            continue;
        }

        $destino = $pasta . '/' . $nomeArquivo;
        if (@move_uploaded_file($arquivo['tmp_name'], $destino)) {
            $caminhoCompleto = $destino;
            break;
        }

        $ultimoErro = "Falha ao mover upload para: {$destino}";
    }

    if (!$caminhoCompleto || !file_exists($caminhoCompleto)) {
        throw new Exception('Erro ao salvar o arquivo do certificado. ' . $ultimoErro);
    }
    
    // 1. Atualizar Tabela NOVA (config_certificados) - ESSENCIAL PARA O SERVIÇO DE EMISSÃO
    $sqlNew = "REPLACE INTO config_certificados (id, email_responsavel, senha_certificado, caminho_arquivo) VALUES (1, :email, :senha, :path)";
    $stmtNew = $pdo->prepare($sqlNew);
    $stmtNew->execute([
        ':email' => $email,
        ':senha' => $senha,
        ':path' => $nomeArquivo // Salvando apenas o nome do arquivo para consistência
    ]);
    
    // 2. Atualizar Tabela LEGADO (configuracoes_fiscais) - Para visualização
    $stmt_check = $pdo->prepare("SELECT id FROM configuracoes_fiscais WHERE id_admin = ?");
    $stmt_check->execute([$id_admin]);
    $existe = $stmt_check->fetch();
    
    if ($existe) {
        $sql = "UPDATE configuracoes_fiscais SET 
                certificado_nome = ?,
                certificado_validade = ?,
                certificado_arquivo = ?,
                updated_at = NOW()
                WHERE id_admin = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $validade, $nomeArquivo, $id_admin]);
    } else {
        $sql = "INSERT INTO configuracoes_fiscais 
                (id_admin, certificado_nome, certificado_validade, certificado_arquivo, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_admin, $nome, $validade, $nomeArquivo]);
    }
    
    // Redirecionar
    header('Location: configuracoes_nfe.php?aba=certificado&msg=Certificado validado e salvo com sucesso!');
    exit;
    
} catch (Exception $e) {
    header('Location: configuracoes_nfe.php?aba=certificado&erro=' . urlencode($e->getMessage()));
    exit;
}
