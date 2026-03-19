<?php
/**
 * Diagnostico tecnico de certificado NFC-e (JSON)
 * Objetivo: identificar causa raiz sem tentativa-e-erro de deploy.
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once __DIR__ . '/../config/configuracoes.php';
    require_once __DIR__ . '/../vendor/autoload.php';

    $idAdmin = (int)($_SESSION['id_admin'] ?? 1);

    $stmtFiscal = $pdo->prepare("SELECT * FROM configuracoes_fiscais WHERE id_admin = ?");
    $stmtFiscal->execute([$idAdmin]);
    $configFiscal = $stmtFiscal->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmtCert = $pdo->query("SELECT * FROM config_certificados WHERE id = 1");
    $configCert = $stmtCert ? ($stmtCert->fetch(PDO::FETCH_ASSOC) ?: []) : [];

    $certFilenamePrimary = basename((string)($configCert['caminho_arquivo'] ?? ''));
    $certFilenameLegacy = basename((string)($configFiscal['certificado_arquivo'] ?? ''));
    $certFilename = $certFilenamePrimary !== '' ? $certFilenamePrimary : $certFilenameLegacy;
    $certPassword = (string)($configCert['senha_certificado'] ?? '');
    $passwordCandidates = array_values(array_unique(array_filter([
        $certPassword,
        trim($certPassword),
    ], static fn($v) => $v !== '')));
    if (count($passwordCandidates) === 0) {
        $passwordCandidates = [''];
    }

    $certDirs = [
        __DIR__ . '/../uploads/certificados/',
        dirname(__DIR__) . '/uploads/certificados/',
        dirname(__DIR__, 2) . '/uploads/certificados/',
    ];

    $normalizeName = static function (string $s): string {
        $s = trim($s);
        $s = trim($s, "\"'");
        $s = preg_replace('/\s+/', ' ', $s);
        return strtolower($s);
    };

    $searchTrace = [];
    $foundPath = null;
    $allPkCandidates = [];

    foreach ($certDirs as $dir) {
        $searchTrace[] = [
            'dir' => $dir,
            'exists' => is_dir($dir),
            'readable' => is_dir($dir) ? is_readable($dir) : false,
        ];
    }

    // Busca exata pelos nomes do banco
    $nameCandidates = array_values(array_unique(array_filter([$certFilenamePrimary, $certFilenameLegacy, $certFilename])));
    foreach ($certDirs as $dir) {
        foreach ($nameCandidates as $name) {
            $p = $dir . $name;
            if (is_file($p)) {
                $foundPath = $p;
                break 2;
            }
        }
    }

    // Busca por nome normalizado
    if (!$foundPath && $certFilename !== '') {
        $target = $normalizeName($certFilename);
        foreach ($certDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $all = @scandir($dir);
            if (!is_array($all)) {
                continue;
            }
            foreach ($all as $entry) {
                if ($entry === '.' || $entry === '..' || !is_string($entry)) {
                    continue;
                }
                $full = $dir . $entry;
                if (!is_file($full)) {
                    continue;
                }
                $lower = strtolower($entry);
                if (str_ends_with($lower, '.p12') || str_ends_with($lower, '.pfx')) {
                    $allPkCandidates[] = $full;
                }
                if ($normalizeName($entry) === $target) {
                    $foundPath = $full;
                    break 2;
                }
            }
        }
    }

    // Se ainda nao encontrou, pega o mais recente .p12/.pfx
    if (!$foundPath) {
        foreach ($certDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $all = @scandir($dir);
            if (!is_array($all)) {
                continue;
            }
            foreach ($all as $entry) {
                if ($entry === '.' || $entry === '..' || !is_string($entry)) {
                    continue;
                }
                $full = $dir . $entry;
                if (!is_file($full)) {
                    continue;
                }
                $lower = strtolower($entry);
                if (str_ends_with($lower, '.p12') || str_ends_with($lower, '.pfx')) {
                    $allPkCandidates[] = $full;
                }
            }
        }
        if (count($allPkCandidates) > 0) {
            usort($allPkCandidates, static function ($a, $b) {
                return (@filemtime($b) ?: 0) <=> (@filemtime($a) ?: 0);
            });
            $foundPath = $allPkCandidates[0];
        }
    }

    $opensslLocations = function_exists('openssl_get_cert_locations') ? openssl_get_cert_locations() : [];
    $legacyConf = realpath(__DIR__ . '/../legacy_openssl.cnf');
    if (!$legacyConf) {
        $legacyConf = __DIR__ . '/../legacy_openssl.cnf';
    }

    $moduleCandidates = array_values(array_unique(array_filter([
        getenv('OPENSSL_MODULES') !== false ? (string)getenv('OPENSSL_MODULES') : '',
        '/opt/alt/php83/usr/lib64/ossl-modules',
        '/opt/alt/php82/usr/lib64/ossl-modules',
        '/opt/alt/php81/usr/lib64/ossl-modules',
        '/usr/lib64/ossl-modules',
        '/usr/lib/ossl-modules',
    ], static fn($v) => $v !== '')));

    $moduleChecks = [];
    foreach ($moduleCandidates as $path) {
        $legacySo = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . 'legacy.so';
        $moduleChecks[] = [
            'modules_path' => $path,
            'path_exists' => is_dir($path),
            'legacy_so_exists' => is_file($legacySo),
            'legacy_so' => $legacySo,
        ];
    }

    $readAttempts = [];
    $rootCause = 'indefinida';
    $recommendedAction = 'Executar diagnostico novamente apos novo upload do certificado.';

    if (!$foundPath || !is_file($foundPath)) {
        $rootCause = 'arquivo_fisico_nao_encontrado';
        $recommendedAction = 'Reenviar o certificado e confirmar pasta uploads/certificados com permissao de leitura.';
    } else {
        $content = file_get_contents($foundPath);
        if ($content === false) {
            $rootCause = 'arquivo_ilegivel';
            $recommendedAction = 'Corrigir permissao de leitura do arquivo do certificado no servidor.';
        } else {
            $originalConf = getenv('OPENSSL_CONF');
            $originalModules = getenv('OPENSSL_MODULES');

            $attemptPkcs12 = static function (string $bytes, string $pwd): array {
                if (!function_exists('openssl_pkcs12_read')) {
                    return ['ok' => false, 'err' => 'openssl_pkcs12_read indisponivel'];
                }
                $bags = [];
                if (@openssl_pkcs12_read($bytes, $bags, $pwd)) {
                    return ['ok' => true, 'err' => ''];
                }
                $msg = '';
                while ($line = openssl_error_string()) {
                    $msg .= trim($line) . ' ';
                }
                return ['ok' => false, 'err' => trim($msg)];
            };

            // A) tentativa direta
            foreach ($passwordCandidates as $pwd) {
                $res = $attemptPkcs12($content, $pwd);
                $readAttempts[] = [
                    'mode' => 'direct',
                    'password_len' => strlen($pwd),
                    'ok' => $res['ok'],
                    'err' => $res['err'],
                ];
                if ($res['ok']) {
                    $rootCause = 'ok';
                    $recommendedAction = 'Certificado lido com sucesso. Validar fluxo de emissao SEFAZ.';
                    break;
                }
            }

            // B) tentativa com conf/modules legacy
            if ($rootCause !== 'ok') {
                putenv("OPENSSL_CONF={$legacyConf}");
                foreach ($moduleCandidates as $modulesPath) {
                    putenv("OPENSSL_MODULES={$modulesPath}");
                    foreach ($passwordCandidates as $pwd) {
                        $res = $attemptPkcs12($content, $pwd);
                        $readAttempts[] = [
                            'mode' => 'legacy_env',
                            'modules_path' => $modulesPath,
                            'password_len' => strlen($pwd),
                            'ok' => $res['ok'],
                            'err' => $res['err'],
                        ];
                        if ($res['ok']) {
                            $rootCause = 'ok_legacy';
                            $recommendedAction = 'Leitura ok no modo legacy. Manter legacy_openssl.cnf e paths de modules.';
                            break 2;
                        }
                    }
                }
            }

            if ($originalConf !== false) {
                putenv("OPENSSL_CONF={$originalConf}");
            } else {
                putenv("OPENSSL_CONF");
            }
            if ($originalModules !== false) {
                putenv("OPENSSL_MODULES={$originalModules}");
            } else {
                putenv("OPENSSL_MODULES");
            }

            if ($rootCause !== 'ok' && $rootCause !== 'ok_legacy') {
                $allErrors = strtolower(implode(' | ', array_map(static fn($a) => (string)($a['err'] ?? ''), $readAttempts)));
                $unsupported = str_contains($allErrors, 'unsupported');
                $hasLegacySo = false;
                foreach ($moduleChecks as $m) {
                    if (!empty($m['legacy_so_exists'])) {
                        $hasLegacySo = true;
                        break;
                    }
                }

                if ($unsupported && !$hasLegacySo) {
                    $rootCause = 'openssl_sem_provider_legacy';
                    $recommendedAction = 'Servidor nao possui legacy.so acessivel. Solucao raiz: converter o certificado para PFX moderno (AES-256) fora da hospedagem e reenviar.';
                } elseif ($unsupported && $hasLegacySo) {
                    $rootCause = 'openssl_legacy_nao_carregado';
                    $recommendedAction = 'legacy.so existe, mas nao carrega no runtime PHP. Solucao raiz: converter o PFX para formato moderno e reenviar.';
                } else {
                    $rootCause = 'senha_ou_arquivo_invalido';
                    $recommendedAction = 'Validar senha digitada e integridade do arquivo .pfx/.p12 em outro sistema.';
                }
            }
        }
    }

    echo json_encode([
        'status' => 'success',
        'diagnostico' => [
            'root_cause' => $rootCause,
            'recommended_action' => $recommendedAction,
            'openssl' => [
                'OPENSSL_VERSION_TEXT' => defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'n/a',
                'OPENSSL_VERSION_NUMBER' => defined('OPENSSL_VERSION_NUMBER') ? OPENSSL_VERSION_NUMBER : 'n/a',
                'php_openssl_version' => phpversion('openssl') ?: 'n/a',
                'openssl_conf_env' => getenv('OPENSSL_CONF') ?: '',
                'openssl_modules_env' => getenv('OPENSSL_MODULES') ?: '',
                'locations' => $opensslLocations,
                'legacy_conf_path' => $legacyConf,
                'legacy_conf_exists' => is_file($legacyConf),
                'module_checks' => $moduleChecks,
            ],
            'certificado' => [
                'db_filename_primary' => $certFilenamePrimary,
                'db_filename_legacy' => $certFilenameLegacy,
                'resolved_path' => $foundPath ?: '',
                'resolved_exists' => $foundPath ? is_file($foundPath) : false,
                'resolved_size' => ($foundPath && is_file($foundPath)) ? filesize($foundPath) : 0,
                'resolved_mtime' => ($foundPath && is_file($foundPath)) ? date('c', filemtime($foundPath)) : '',
                'password_len_db' => strlen($certPassword),
                'password_candidate_lengths' => array_map('strlen', $passwordCandidates),
                'search_trace' => $searchTrace,
            ],
            'read_attempts' => $readAttempts,
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'mensagem' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

