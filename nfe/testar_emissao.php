<?php
/**
 * ZIIPVET - Teste de Emissão de NFC-e
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\NFCeService;

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // ID da venda para teste (padrão 55 ou via GET)
    $vendaId = isset($_GET['venda_id']) ? (int)$_GET['venda_id'] : 55;
    
    // Instanciar serviço
    $nfceService = new NFCeService($pdo);
    
    // Tentar emitir
    $resultado = $nfceService->emitir($vendaId);
    
    echo json_encode([
        'status' => 'success',
        'mensagem' => 'Processo concluído',
        'dados' => $resultado
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'mensagem' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
