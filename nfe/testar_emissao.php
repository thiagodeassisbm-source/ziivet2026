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
        // Escolhe automaticamente a venda mais recente com itens para teste.
        $stmtVenda = $pdo->query("
            SELECT v.id
            FROM vendas v
            WHERE EXISTS (
                SELECT 1
                FROM vendas_itens vi
                WHERE vi.id_venda = v.id
            )
            ORDER BY v.id DESC
            LIMIT 1
        ");
        $vendaAuto = $stmtVenda ? ($stmtVenda->fetch(PDO::FETCH_ASSOC) ?: []) : [];
        $vendaId = (int)($vendaAuto['id'] ?? 0);
    }

    if ($vendaId <= 0) {
        echo json_encode([
            'status' => 'success',
            'dados' => [
                'success' => false,
                'message' => 'Nenhuma venda com itens encontrada para teste de emissão.'
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
                'venda_id' => $vendaId,
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

