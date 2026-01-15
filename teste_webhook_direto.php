<?php
/**
 * TESTE DIRETO DO WEBHOOK (SERVER-SIDE)
 * Testa o webhook diretamente via PHP, sem JavaScript
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

// URL do webhook
$webhook_url = 'https://www.lepetboutique.com.br/app/webhook_whatsapp.php';

// Variáveis de teste
$resultado_teste = '';
$tipo_resultado = '';

// ============================================================
// PROCESSAR TESTE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['testar'])) {
    
    $numero = $_POST['numero'];
    $mensagem = $_POST['mensagem'];
    
    // Montar JSON igual ao que o WhatsApp envia
    $dados_webhook = [
        "entry" => [[
            "changes" => [[
                "value" => [
                    "messages" => [[
                        "from" => $numero,
                        "text" => [
                            "body" => $mensagem
                        ]
                    ]]
                ]
            ]]
        ]]
    ];
    
    $json_dados = json_encode($dados_webhook);
    
    // Fazer requisição cURL para o webhook
    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_dados);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($json_dados)
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Verificar resultado
    if ($curl_error) {
        $tipo_resultado = 'erro';
        $resultado_teste = "<h3>❌ Erro na Requisição</h3>";
        $resultado_teste .= "<p><strong>Erro cURL:</strong> $curl_error</p>";
    } else if ($http_code == 200) {
        $tipo_resultado = 'sucesso';
        $resultado_teste = "<h3>✅ Webhook Chamado com Sucesso!</h3>";
        $resultado_teste .= "<p><strong>Status HTTP:</strong> $http_code</p>";
        $resultado_teste .= "<p><strong>Número:</strong> $numero</p>";
        $resultado_teste .= "<p><strong>Mensagem:</strong> \"$mensagem\"</p>";
        $resultado_teste .= "<hr>";
        $resultado_teste .= "<h4>🔍 Verificações:</h4>";
        $resultado_teste .= "<ol>";
        $resultado_teste .= "<li>Acesse o banco de dados e verifique a tabela <code>log_baixas_whatsapp</code></li>";
        $resultado_teste .= "<li>Execute: <code>SELECT * FROM log_baixas_whatsapp ORDER BY data_hora DESC LIMIT 5</code></li>";
        $resultado_teste .= "<li>Verifique se a baixa foi processada (se o boleto existir)</li>";
        $resultado_teste .= "</ol>";
    } else {
        $tipo_resultado = 'aviso';
        $resultado_teste = "<h3>⚠️ Resposta Inesperada</h3>";
        $resultado_teste .= "<p><strong>Status HTTP:</strong> $http_code</p>";
        $resultado_teste .= "<p><strong>Resposta:</strong> " . htmlspecialchars($response) . "</p>";
    }
}

// ============================================================
// BUSCAR ÚLTIMOS LOGS DO BANCO
// ============================================================
$logs_recentes = '';
try {
    require_once 'config/configuracoes.php';
    
    if (isset($pdo)) {
        $stmt = $pdo->query("SELECT * FROM log_baixas_whatsapp ORDER BY data_hora DESC LIMIT 10");
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($logs)) {
            $logs_recentes .= "<table style='width:100%;border-collapse:collapse;margin-top:20px;'>";
            $logs_recentes .= "<thead><tr style='background:#667eea;color:white;'>";
            $logs_recentes .= "<th style='padding:10px;text-align:left;'>Data/Hora</th>";
            $logs_recentes .= "<th style='padding:10px;text-align:left;'>Número</th>";
            $logs_recentes .= "<th style='padding:10px;text-align:left;'>Mensagem</th>";
            $logs_recentes .= "<th style='padding:10px;text-align:left;'>Status</th>";
            $logs_recentes .= "<th style='padding:10px;text-align:left;'>ID Conta</th>";
            $logs_recentes .= "</tr></thead><tbody>";
            
            foreach ($logs as $log) {
                $cor_status = [
                    'SUCESSO' => '#d4edda',
                    'ERRO' => '#f8d7da',
                    'DIVERGENCIA' => '#fff3cd',
                    'PENDENTE' => '#e3f2fd'
                ];
                $cor = $cor_status[$log['status_processamento']] ?? '#f5f5f5';
                
                $logs_recentes .= "<tr style='background:$cor;border-bottom:1px solid #ddd;'>";
                $logs_recentes .= "<td style='padding:8px;'>" . date('d/m/Y H:i:s', strtotime($log['data_hora'])) . "</td>";
                $logs_recentes .= "<td style='padding:8px;'>" . htmlspecialchars($log['whatsapp_remetente']) . "</td>";
                $logs_recentes .= "<td style='padding:8px;'>" . htmlspecialchars(substr($log['mensagem_original'], 0, 40)) . "...</td>";
                $logs_recentes .= "<td style='padding:8px;'><strong>" . $log['status_processamento'] . "</strong></td>";
                $logs_recentes .= "<td style='padding:8px;'>" . ($log['id_conta_identificada'] ?? '-') . "</td>";
                $logs_recentes .= "</tr>";
            }
            
            $logs_recentes .= "</tbody></table>";
        } else {
            $logs_recentes = "<p style='text-align:center;color:#999;padding:20px;'>Nenhum log registrado ainda.</p>";
        }
    }
} catch (Exception $e) {
    $logs_recentes = "<p style='color:red;'>Erro ao buscar logs: " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste Direto do Webhook - ZiipVet</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
        }
        
        h1 {
            text-align: center;
            color: #667eea;
            margin-bottom: 10px;
            font-size: 32px;
        }
        
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 5px;
        }
        
        .form-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        input, textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        input:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
        }
        
        button {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .resultado {
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        
        .resultado.sucesso {
            background: #d4edda;
            border: 2px solid #c3e6cb;
            color: #155724;
        }
        
        .resultado.erro {
            background: #f8d7da;
            border: 2px solid #f5c6cb;
            color: #721c24;
        }
        
        .resultado.aviso {
            background: #fff3cd;
            border: 2px solid #ffc107;
            color: #856404;
        }
        
        .logs-section {
            margin-top: 30px;
        }
        
        .logs-section h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 22px;
        }
        
        .exemplo {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .exemplo code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            color: #d63384;
        }
        
        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Teste Direto do Webhook</h1>
        <p class="subtitle">Teste via PHP (Server-Side) - Sem JavaScript</p>
        
        <div class="info-box">
            <strong>ℹ️ Como funciona:</strong><br>
            Este teste faz uma requisição cURL diretamente do servidor PHP para o webhook, 
            simulando exatamente o que o WhatsApp faz quando envia uma mensagem.
        </div>
        
        <?php if ($resultado_teste): ?>
            <div class="resultado <?= $tipo_resultado ?>">
                <?= $resultado_teste ?>
            </div>
        <?php endif; ?>
        
        <div class="form-section">
            <h2 style="margin-bottom:15px;color:#333;">📝 Configurar Teste</h2>
            
            <div class="exemplo">
                <strong>💡 Exemplos de mensagens:</strong><br>
                • <code>Paguei o boleto 83 no valor de 538,63</code><br>
                • <code>Boleto 45 valor R$ 1.250,00</code><br>
                • <code>Parcela 12 R$ 850,50</code>
            </div>
            
            <form method="POST">
                <label>📞 Número Autorizado:</label>
                <input 
                    type="text" 
                    name="numero" 
                    value="5562982933585"
                    placeholder="Ex: 5562982933585"
                    required
                >
                
                <label>💬 Mensagem de Teste:</label>
                <textarea 
                    name="mensagem"
                    placeholder="Ex: Paguei o boleto 83 no valor de 538,63"
                    required
                ></textarea>
                
                <button type="submit" name="testar">🚀 EXECUTAR TESTE DO WEBHOOK</button>
            </form>
        </div>
        
        <div class="logs-section">
            <h2>📊 Últimos 10 Logs do Sistema</h2>
            <div style="overflow-x:auto;">
                <?= $logs_recentes ?>
            </div>
        </div>
        
        <div class="info-box" style="margin-top:25px;background:#d4edda;border-color:#28a745;">
            <strong style="color:#155724;">🎯 Após o teste:</strong><br>
            <span style="color:#155724;">
            1. Verifique se apareceu um novo registro na tabela de logs acima<br>
            2. Confira se o status é "SUCESSO" (se o boleto existir e o valor bater)<br>
            3. Consulte a tabela de contas para ver se a baixa foi efetuada
            </span>
        </div>
    </div>
</body>
</html>