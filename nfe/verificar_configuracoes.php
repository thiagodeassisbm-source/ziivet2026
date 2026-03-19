<?php
/**
 * ZIIPVET - Verificação de Configurações NFC-e
 * Verifica se tudo está configurado para emissão
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';
require_once '../vendor/autoload.php';

use App\Services\NFCeService;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;

// Carregar configurações Fiscais (CSC, Certificado)
$stmt = $pdo->prepare("SELECT * FROM configuracoes_fiscais WHERE id_admin = ?");
$stmt->execute([$id_admin]);
$config = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Carregar dados da Empresa
$stmtEmp = $pdo->prepare("SELECT * FROM config_clinica WHERE id = 1");
$stmtEmp->execute();
$empresa = $stmtEmp->fetch(PDO::FETCH_ASSOC) ?: [];

// Verificação real do certificado no filesystem (mesma lógica do serviço/diagnóstico)
$stmtCertCfg = $pdo->query("SELECT caminho_arquivo FROM config_certificados WHERE id = 1");
$certCfg = $stmtCertCfg ? ($stmtCertCfg->fetch(PDO::FETCH_ASSOC) ?: []) : [];
$certArquivoPrimary = basename((string)($certCfg['caminho_arquivo'] ?? ''));
$certArquivoLegacy = basename((string)($config['certificado_arquivo'] ?? ''));
$certArquivoDb = $certArquivoPrimary !== '' ? $certArquivoPrimary : $certArquivoLegacy;
$certDirs = [
    __DIR__ . '/../uploads/certificados/',         // /public_html/app/uploads/certificados
    dirname(__DIR__) . '/uploads/certificados/',   // redundância segura
    dirname(__DIR__, 2) . '/uploads/certificados/' // /public_html/uploads/certificados
];
$certificadoArquivoExiste = false;
$certArquivoEncontrado = '';

$normalizeName = function (string $s): string {
    $s = trim($s);
    $s = trim($s, "\"'");
    $s = preg_replace('/\s+/', ' ', $s);
    return strtolower($s);
};

$nomesPrioritarios = array_values(array_unique(array_filter([$certArquivoPrimary, $certArquivoLegacy])));

// 1) Primeiro tenta nomes explícitos do banco (novo e legado)
foreach ($certDirs as $d) {
    foreach ($nomesPrioritarios as $nomeTry) {
        $p = $d . $nomeTry;
        if (is_file($p)) {
            $certificadoArquivoExiste = true;
            $certArquivoEncontrado = basename($p);
            break 2;
        }
    }
}

// 2) Comparação por nome normalizado
if (!$certificadoArquivoExiste && $certArquivoDb !== '') {
    $targetNorm = $normalizeName($certArquivoDb);
    foreach ($certDirs as $d) {
        if (!is_dir($d)) continue;
        $all = @scandir($d);
        if (!is_array($all)) continue;
        foreach ($all as $entry) {
            if ($entry === '.' || $entry === '..' || !is_string($entry)) continue;
            $full = $d . $entry;
            if (!is_file($full)) continue;
            if ($normalizeName($entry) === $targetNorm) {
                $certificadoArquivoExiste = true;
                $certArquivoEncontrado = $entry;
                break 2;
            }
        }
    }
}

// 3) Fallback final: usa o certificado .p12/.pfx mais recente em qualquer diretório permitido.
if (!$certificadoArquivoExiste) {
    $pks = [];
    foreach ($certDirs as $d) {
        if (!is_dir($d)) continue;
        $all = @scandir($d);
        if (!is_array($all)) continue;
        foreach ($all as $entry) {
            if ($entry === '.' || $entry === '..' || !is_string($entry)) continue;
            $full = $d . $entry;
            if (!is_file($full)) continue;
            $lower = strtolower($entry);
            if (str_ends_with($lower, '.p12') || str_ends_with($lower, '.pfx')) {
                $pks[] = $full;
            }
        }
    }
    if (count($pks) >= 1) {
        usort($pks, static function ($a, $b) {
            return (@filemtime($b) ?: 0) <=> (@filemtime($a) ?: 0);
        });
        $certificadoArquivoExiste = true;
        $certArquivoEncontrado = basename($pks[0]);
    }
}

// Diagnóstico: validar se o autoload e o pacote do NFePHP existem no diretório esperado
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
$autoloadExists = file_exists($autoloadPath);
$nfephpDir = __DIR__ . '/../vendor/nfephp-org';
$nfephpDirExists = is_dir($nfephpDir);

// Verifica se o autoload parece ser do Composer (evita falso positivo do autoload mínimo)
$autoloadIsComposer = false;
if ($autoloadExists && is_readable($autoloadPath)) {
    $chunk = @file_get_contents($autoloadPath, false, null, 0, 2500);
    $autoloadIsComposer = is_string($chunk) && str_contains($chunk, 'ComposerAutoloaderInit');
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Configurações NFC-e | ZiipVet</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/menu.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .check-card {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .check-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .check-item:last-child {
            border-bottom: none;
        }
        .check-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 18px;
        }
        .check-icon.ok {
            background: #d4edda;
            color: #28a745;
        }
        .check-icon.error {
            background: #f8d7da;
            color: #dc3545;
        }
        .check-icon.warning {
            background: #fff3cd;
            color: #856404;
        }
        .check-text h4 {
            margin: 0 0 5px;
            font-size: 16px;
        }
        .check-text p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
        .btn-testar {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: #fff;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .btn-testar:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <?php $path_prefix = '../'; ?>
    <aside class="sidebar-container"><?php include '../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <div class="form-header">
            <h1 class="form-title">
                <i class="fas fa-check-circle"></i>
                Verificar Configurações NFC-e
            </h1>
            <a href="configuracoes_nfe.php" class="btn-voltar">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
            <a href="verificar_configuracoes.php?refresh=<?= time() ?>" class="btn-voltar" style="margin-left:8px;">
                <i class="fas fa-rotate-right"></i> Atualizar Status
            </a>
        </div>

        <div class="check-card">
            <h3 style="margin:0 0 20px;"><i class="fas fa-cog"></i> Status das Configurações</h3>
            
            <!-- Empresa -->
            <div class="check-item">
                <div class="check-icon <?= !empty($empresa['cnpj']) ? 'ok' : 'error' ?>">
                    <i class="fas <?= !empty($empresa['cnpj']) ? 'fa-check' : 'fa-times' ?>"></i>
                </div>
                <div class="check-text">
                    <h4>Dados da Empresa</h4>
                    <p><?= !empty($empresa['cnpj']) ? 'Configurado: ' . $empresa['razao_social'] . ' (' . $empresa['cnpj'] . ')' : 'Não configurado' ?></p>
                </div>
            </div>

            <!-- CSC -->
            <div class="check-item">
                <div class="check-icon <?= !empty($config['csc_id']) && !empty($config['csc_producao']) ? 'ok' : 'error' ?>">
                    <i class="fas <?= !empty($config['csc_id']) ? 'fa-check' : 'fa-times' ?>"></i>
                </div>
                <div class="check-text">
                    <h4>Código CSC</h4>
                    <p><?= !empty($config['csc_id']) ? 'ID: ' . $config['csc_id'] . ' - Token configurado' : 'Não configurado' ?></p>
                </div>
            </div>

            <!-- Certificado -->
            <div class="check-item">
                <div class="check-icon <?= $certificadoArquivoExiste ? 'ok' : 'error' ?>">
                    <i class="fas <?= $certificadoArquivoExiste ? 'fa-check' : 'fa-times' ?>"></i>
                </div>
                <div class="check-text">
                    <h4>Certificado Digital</h4>
                    <p>
                        <?php if (!empty($config['certificado_arquivo'])): ?>
                            <?= $config['certificado_nome'] ?> - Válido até <?= date('d/m/Y', strtotime($config['certificado_validade'])) ?>
                            <?php if (!empty($certArquivoEncontrado)): ?>
                                <br><small style="color:#6b7280;">Arquivo físico: <?= htmlspecialchars($certArquivoEncontrado, ENT_QUOTES, 'UTF-8') ?></small>
                            <?php endif; ?>
                            <?php if (!$certificadoArquivoExiste): ?>
                                <br><small style="color:#dc3545;">Arquivo físico não encontrado no servidor. Reenvie o certificado.</small>
                            <?php endif; ?>
                        <?php else: ?>
                            Não enviado
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Série -->
            <div class="check-item">
                <div class="check-icon <?= !empty($config['nfce_serie']) ? 'ok' : 'warning' ?>">
                    <i class="fas <?= !empty($config['nfce_serie']) ? 'fa-check' : 'fa-exclamation' ?>"></i>
                </div>
                <div class="check-text">
                    <h4>Série e Número</h4>
                    <p>Série: <?= $config['nfce_serie'] ?? '1' ?> | Próximo número: <?= $config['nfce_numero'] ?? '1' ?></p>
                </div>
            </div>

            <!-- Biblioteca NFePHP -->
            <?php 
            $nfephpOk = class_exists('NFePHP\NFe\Tools');
            ?>
            <div class="check-item">
                <div class="check-icon <?= $nfephpOk ? 'ok' : 'error' ?>">
                    <i class="fas <?= $nfephpOk ? 'fa-check' : 'fa-times' ?>"></i>
                </div>
                <div class="check-text">
                    <h4>Biblioteca NFePHP</h4>
                    <p>
                        <?= $nfephpOk ? 'Instalada e funcionando' : 'Não instalada - execute: composer install' ?>
                        <br>
                        <small style="color:#6b7280;">
                            autoload.php: <?= $autoloadExists ? 'OK' : 'MISSING' ?>
                            <?= $autoloadExists ? '(composer: ' . ($autoloadIsComposer ? 'SIM' : 'NÃO') . ')' : '' ?>
                            <br>
                            pasta vendor/nfephp-org: <?= $nfephpDirExists ? 'OK' : 'MISSING' ?>
                        </small>
                    </p>
                </div>
            </div>
        </div>

        <?php
        $tudoOk = !empty($empresa['cnpj']) 
                  && !empty($config['csc_id']) 
                  && !empty($config['certificado_arquivo'])
                  && $certificadoArquivoExiste
                  && $nfephpOk;
        ?>

        <div class="check-card">
            <h3 style="margin:0 0 20px;"><i class="fas fa-rocket"></i> Teste de Emissão</h3>
            
            <?php if ($tudoOk): ?>
                <p style="color:#28a745; margin-bottom:20px;">
                    <i class="fas fa-check-circle"></i> 
                    <strong>Tudo configurado!</strong> Você pode testar a emissão em ambiente de homologação.
                </p>
                <button class="btn-testar" onclick="testarEmissao()">
                    <i class="fas fa-paper-plane"></i> Testar Emissão (Homologação)
                </button>
                <button class="btn-testar" style="margin-left:8px; background:linear-gradient(135deg,#6c757d 0%,#5a6268 100%);" onclick="executarDiagnosticoCertificado()">
                    <i class="fas fa-stethoscope"></i> Diagnóstico Completo
                </button>
            <?php else: ?>
                <p style="color:#dc3545; margin-bottom:20px;">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Configurações incompletas.</strong> Complete as configurações acima antes de testar.
                </p>
                <button class="btn-testar" disabled>
                    <i class="fas fa-paper-plane"></i> Testar Emissão (Homologação)
                </button>
                <button class="btn-testar" style="margin-left:8px; background:linear-gradient(135deg,#6c757d 0%,#5a6268 100%);" onclick="executarDiagnosticoCertificado()">
                    <i class="fas fa-stethoscope"></i> Diagnóstico Completo
                </button>
            <?php endif; ?>
        </div>

        <div class="check-card" style="background:#fff3cd; border:1px solid #ffeea7;">
            <h4 style="margin:0 0 10px; color:#856404;"><i class="fas fa-info-circle"></i> Ambiente de Homologação</h4>
            <p style="margin:0; color:#856404;">
                O teste será feito em ambiente de <strong>homologação</strong> (teste) da SEFAZ. 
                Nenhuma nota fiscal real será emitida. Ideal para validar se todas as configurações estão corretas.
            </p>
        </div>

    </main>

    <script>
        function testarEmissao() {
            const btn = document.querySelector('.btn-testar');
            const originalText = btn.innerHTML;
            
            // Adicionar estado de carregamento
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
            btn.disabled = true;
            
            fetch('testar_emissao.php?venda_id=55') // Teste com Venda 55
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success' && data.dados.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            html: `
                                <p><strong>Nota Emitida Corretamente!</strong></p>
                                <p>Chave: ${data.dados.chave}</p>
                                <p>Protocolo: ${data.dados.protocolo}</p>
                            `
                        });
                    } else if (data.status === 'success' && !data.dados.success) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro na Emissão',
                            text: data.dados.message || 'Erro desconhecido'
                        });
                    } else {
                        throw new Error(data.mensagem);
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro no Teste',
                        text: error.message
                    });
                })
                .finally(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
        }

        function executarDiagnosticoCertificado() {
            Swal.fire({
                title: 'Gerando diagnóstico...',
                text: 'Coletando dados técnicos do OpenSSL e do certificado',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch('diagnostico_certificado.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status !== 'success') {
                        throw new Error(data.mensagem || 'Falha ao gerar diagnóstico');
                    }

                    const d = data.diagnostico || {};
                    const resumo = [
                        `Causa raiz: ${d.root_cause || 'indefinida'}`,
                        `Ação recomendada: ${d.recommended_action || 'n/a'}`,
                        `Certificado resolvido: ${(d.certificado && d.certificado.resolved_path) ? d.certificado.resolved_path : 'nao encontrado'}`,
                        `OpenSSL: ${(d.openssl && d.openssl.OPENSSL_VERSION_TEXT) ? d.openssl.OPENSSL_VERSION_TEXT : 'n/a'}`
                    ].join('\n');

                    Swal.fire({
                        icon: 'info',
                        title: 'Diagnóstico Técnico',
                        html: `
                            <p style="text-align:left;white-space:pre-line">${resumo}</p>
                            <details style="text-align:left;margin-top:10px;">
                                <summary><strong>Ver detalhes técnicos completos</strong></summary>
                                <pre style="max-height:320px;overflow:auto;background:#111;color:#0f0;padding:10px;border-radius:8px;font-size:12px;">${JSON.stringify(d, null, 2)}</pre>
                            </details>
                        `,
                        width: 800
                    });
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro no Diagnóstico',
                        text: error.message
                    });
                });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
