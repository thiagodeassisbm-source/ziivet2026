<?php
// Test persistence script
session_start();
$_SESSION['id_admin'] = 1;
$_SESSION['usuario_id'] = 1;
$_SESSION['usuario_nome'] = 'Admin Teste Persistence';

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/configuracoes.php';

use App\Application\Service\VendaService;
use App\Infrastructure\Repository\VendaRepository;
use App\Application\Service\AuditService;
use App\Infrastructure\Repository\AuditLogRepository;
use App\Core\Database;

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check count before
    $countBefore = $conn->query("SELECT COUNT(*) FROM vendas")->fetchColumn();
    
    $vendaRepo = new VendaRepository($db);
    $auditRepo = new AuditLogRepository($db);
    $auditService = new AuditService($auditRepo);
    $vendaService = new VendaService($vendaRepo, $db, $auditService);

    $payload = json_encode([
        'total_geral' => 50.00,
        'itens' => [
             ['id' => 197, 'qtd' => 1, 'valor' => 50.00, 'total' => 50.00, 'nome' => 'TESTE PERSISTENCIA']
        ],
        'tipo' => 'VENDA',
        'tipo_venda' => 'Presencial consumidor final',
        'id_cliente' => 2, 
        'id_paciente' => 45,
        'data' => date('Y-m-d'),
        'obs' => 'Teste Persistence Script',
        'acao_btn' => 'receber',
        'forma_pagamento' => 1,
        'valor_pago' => 50.00,
        'caixa_ativo' => 1,
        'taxa_aplicada' => '0%',
        'qtd_parcelas' => 1,
        'nome_forma_pagamento' => 'Dinheiro',
        'id_admin' => 1,
        'usuario_vendedor' => 'TestScript'
    ]);
    
    $dados = json_decode($payload, true);
    $res = $vendaService->fecharVenda($dados);
    
    echo "Service Result: " . json_encode($res) . "\n";
    
    // Check count after
    $countAfter = $conn->query("SELECT COUNT(*) FROM vendas")->fetchColumn();
    
    echo "Count Before: $countBefore\n";
    echo "Count After: $countAfter\n";
    
    if ($countAfter > $countBefore) {
        echo "[SUCCESS] Data persisted!\n";
        
        $lastId = $conn->lastInsertId(); // Might not be reliable due to transaction commit but let's query max id
        $lastVenda = $conn->query("SELECT * FROM vendas ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        echo "Last Venda ID: " . $lastVenda['id'] . "\n";
        
        $lancamento = $conn->query("SELECT * FROM lancamentos WHERE id_venda = " . $lastVenda['id'])->fetch(PDO::FETCH_ASSOC);
        if($lancamento) {
             echo "[SUCCESS] Lancamento found: ID " . $lancamento['id'] . "\n";
        } else {
             echo "[FAIL] Lancamento NOT found for venda " . $lastVenda['id'] . "\n";
        }
        
    } else {
        echo "[FAIL] Data NOT persisted. Commit likely missing.\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
