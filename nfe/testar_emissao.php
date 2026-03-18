<?php
/**
 * ZIIPVET - Teste de Emissão NFC-e (AJAX)
 * Retorna JSON para o `fetch()` do arquivo `nfe/verificar_configuracoes.php`.
 */

header('Content-Type: application/json; charset=utf-8');

// Garantir sessão e carregar PDO/config sem redirects (evita HTML no fetch).
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once __DIR__ . '/../config/configuracoes.php';
    require_once __DIR__ . '/../vendor/autoload.php';

    $id_admin = $_SESSION['id_admin'] ?? 0;
    if (empty($id_admin)) {
        echo json_encode([
            'status' => 'error',
            'mensagem' => 'Sessão inválida. Faça login novamente.',
        ]);
        exit;
    }

    $vendaId = isset($_GET['venda_id']) ? (int)$_GET['venda_id'] : 0;
    if ($vendaId <= 0) {
        echo json_encode([
            'status' => 'success',
            'dados' => [
                'success' => false,
                'message' => 'venda_id inválido.'
            ]
        ]);
        exit;
    }

    $nfcService = new \App\Services\NFCeService($pdo);
    $resultado = $nfcService->emitir($vendaId);

    if (!empty($resultado['success'])) {
        echo json_encode([
            'status' => 'success',
            'dados' => [
                'success' => true,
                'chave' => $resultado['chave'] ?? '',
                'protocolo' => $resultado['protocolo'] ?? '',
            ]
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'dados' => [
            'success' => false,
            'message' => $resultado['message'] ?? 'Erro desconhecido no teste de emissão.'
        ]
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'mensagem' => $e->getMessage(),
    ]);
}

