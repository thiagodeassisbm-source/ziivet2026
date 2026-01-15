<?php
// Verificação de Webhook para a API Oficial da Meta
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['hub_challenge'])) {
    $token_verificacao = "ziipvet_123"; // Defina uma senha qualquer aqui
    if ($_GET['hub_verify_token'] === $token_verificacao) {
        echo $_GET['hub_challenge'];
        exit;
    }
}
/**
 * WEBHOOK WHATSAPP - PROCESSAMENTO DE BAIXAS AUTOMÁTICAS
 * Busca por Número de Documento (NF) e Valor.
 */

require_once 'config/configuracoes.php';

// 1. RECEBER DADOS
$json = file_get_contents('php://input');
$dados = json_decode($json, true);

if (!$dados) exit;

try {
    $mensagem = $dados['entry'][0]['changes'][0]['value']['messages'][0]['text']['body'] ?? '';
    $numero_remetente = $dados['entry'][0]['changes'][0]['value']['messages'][0]['from'] ?? '';

    if (empty($mensagem)) exit;

    // --- EXTRAÇÃO INTELIGENTE ---

    // A. Identificar o VALOR (ex: 336,54)
    $valor_identificado = 0;
    if (preg_match('/(?:R\$|valor de|de|pagamento de)\s*([0-9]{1,3}(?:\.[0-9]{3})*,[0-9]{2}|[0-9]+,[0-9]{2})/i', $mensagem, $matches_v)) {
        $valor_raw = $matches_v[1];
        $valor_identificado = (float)str_replace(['.', ','], ['', '.'], $valor_raw);
    }

    // B. Identificar o NÚMERO DO DOCUMENTO (ex: 61682)
    // Buscamos o número que NÃO é o valor identificado
    $documento_nfe = "";
    if (preg_match('/(?:boleto|nf|nota|documento|numero)\s*(\d+)/i', $mensagem, $matches_doc)) {
        $documento_nfe = $matches_doc[1];
    }

    // 3. VALIDAR E PROCESSAR
    $status_proc = 'MENSAGEM_INCONCLUSIVA';
    $id_conta_final = 0;

    if (!empty($documento_nfe) && $valor_identificado > 0) {
        
        // BUSCA PELO NÚMERO DO DOCUMENTO E VALOR (Pega a primeira parcela pendente encontrada)
        $stmt = $pdo->prepare("SELECT id, valor_parcela FROM contas WHERE documento = ? AND status_baixa = 'PENDENTE' ORDER BY vencimento ASC LIMIT 1");
        $stmt->execute([$documento_nfe]);
        $conta = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($conta) {
            $id_conta_final = $conta['id'];
            $valor_banco = (float)$conta['valor_parcela'];
            
            // Compara com tolerância de centavos
            if (abs($valor_identificado - $valor_banco) < 0.05) {
                
                // SUCESSO: Atualiza status
                $update = $pdo->prepare("UPDATE contas SET status_baixa = 'PAGO', data_pagamento = NOW() WHERE id = ?");
                $update->execute([$id_conta_final]);
                $status_proc = 'SUCESSO';
                
            } else {
                $status_proc = 'DIVERGENCIA';
            }
        } else {
            $status_proc = 'DOCUMENTO_NAO_ENCONTRADO';
        }
    }

    // 4. LOG PARA AUDITORIA
    $log = $pdo->prepare("INSERT INTO log_baixas_whatsapp (whatsapp_remetente, mensagem_original, id_conta_identificada, valor_identificado, status_processamento, data_hora) VALUES (?, ?, ?, ?, ?, NOW())");
    $log->execute([$numero_remetente, $mensagem, $id_conta_final, $valor_identificado, $status_proc]);

} catch (Exception $e) {
    error_log("Erro Webhook: " . $e->getMessage());
}

http_response_code(200);
echo json_encode(["status" => "ok"]);