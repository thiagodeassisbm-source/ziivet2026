<?php
/**
 * SISTEMA DE BAIXA REAL VIA WHATSAPP - TESTE DE FLUXO
 * Este arquivo localiza a parcela correta e atualiza o status no banco.
 */

// 1. FORÇAR EXIBIÇÃO DE ERROS PARA DIAGNÓSTICO
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. CONEXÃO COM O BANCO DE DADOS
try {
    // Ajuste o caminho conforme sua estrutura
    if (!file_exists('config/configuracoes.php')) {
        throw new Exception("Arquivo de configuração não encontrado em: config/configuracoes.php");
    }
    require_once 'config/configuracoes.php';
    
    if (!isset($pdo)) {
        throw new Exception("Conexão \$pdo não definida. Verifique seu arquivo de configuração.");
    }
} catch (Exception $e) {
    die("<div style='color:red; border:2px solid red; padding:20px; font-family:sans-serif;'>
            <b>ERRO DE SISTEMA:</b> " . $e->getMessage() . "
         </div>");
}

// 3. FUNÇÃO DE INTELIGÊNCIA DE LEITURA
function interpretarMensagem($texto) {
    // Identifica o número do documento ou ID
    preg_match('/(?:boleto|nº|pedido|id|ref)?\s*(\d+)/i', $texto, $matches_id);
    
    // Identifica o valor informado (trata R$, ponto e vírgula)
    preg_match('/(?:R\$|valor de|paguei)\s*([\d.,]+)/i', $texto, $matches_valor);

    if (isset($matches_id[1]) && isset($matches_valor[1])) {
        $valor_limpo = str_replace(['R$', ' '], '', $matches_valor[1]);
        
        // Converte formato brasileiro (1.500,00) para decimal (1500.00)
        if (strpos($valor_limpo, ',') !== false) {
            $valor_limpo = str_replace('.', '', $valor_limpo);
            $valor_limpo = str_replace(',', '.', $valor_limpo);
        }
        
        return [
            'identificador' => $matches_id[1],
            'valor' => (float)$valor_limpo
        ];
    }
    return null;
}

$mensagem_enviada = $_POST['mensagem_zap'] ?? '';
$resultado_html = "";

// 4. PROCESSAMENTO DA BAIXA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($mensagem_enviada)) {
    $dados = interpretarMensagem($mensagem_enviada);

    if ($dados) {
        $busca = $dados['identificador'];
        $valor_informado = $dados['valor'];

        try {
            // Busca a parcela que mais se aproxima do valor informado
            $sql = "SELECT id, valor_parcela, status_baixa, descricao, documento 
                    FROM contas 
                    WHERE (id = :busca OR documento = :busca) 
                    AND status_baixa != 'PAGO'
                    ORDER BY ABS(valor_parcela - :valor) ASC 
                    LIMIT 1";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':busca' => $busca, ':valor' => $valor_informado]);
            $conta = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($conta) {
                $v_parcela = (float)$conta['valor_parcela'];

                // Validação com margem de 0.05 para arredondamentos
                if (abs($v_parcela - $valor_informado) <= 0.05) {
                    
                    // --- EXECUÇÃO DA BAIXA REAL ---
                    $update = $pdo->prepare("UPDATE contas SET 
                        status_baixa = 'PAGO', 
                        data_pagamento = NOW(), 
                        observacoes = CONCAT(IFNULL(observacoes,''), :obs) 
                        WHERE id = :id");
                    
                    $log = "\n[WhatsApp] Baixa automática via simulador em " . date('d/m/Y H:i');
                    $update->execute([':obs' => $log, ':id' => $conta['id']]);
                    // ------------------------------

                    $resultado_html = "
                    <div style='background:#d4edda; color:#155724; padding:20px; border-radius:8px; border:1px solid #c3e6cb;'>
                        <h3 style='margin:0 0 10px 0;'>✅ BAIXA REALIZADA COM SUCESSO!</h3>
                        <p>O registro <b>#{$conta['id']}</b> ({$conta['descricao']}) foi marcado como <b>PAGO</b>.</p>
                        <p><b>Documento:</b> {$conta['documento']} | <b>Valor:</b> R$ " . number_format($v_parcela, 2, ',', '.') . "</p>
                    </div>";
                } else {
                    $resultado_html = "
                    <div style='background:#fff3cd; color:#856404; padding:20px; border-radius:8px; border:1px solid #ffeeba;'>
                        <h3 style='margin:0 0 10px 0;'>⚠️ DIVERGÊNCIA DE VALOR</h3>
                        <p>Encontramos o documento <b>$busca</b>, mas os valores não batem:</p>
                        <ul>
                            <li>Valor na Parcela: R$ " . number_format($v_parcela, 2, ',', '.') . "</li>
                            <li>Valor Informado: R$ " . number_format($valor_informado, 2, ',', '.') . "</li>
                        </ul>
                        <p><i>A baixa não foi realizada.</i></p>
                    </div>";
                }
            } else {
                $resultado_html = "<div style='background:#f8d7da; color:#721c24; padding:20px; border-radius:8px;'>❌ <b>ERRO:</b> Nenhuma conta pendente encontrada para o número <b>$busca</b>.</div>";
            }
        } catch (PDOException $e) {
            $resultado_html = "<div style='color:red;'>Erro no Banco: " . $e->getMessage() . "</div>";
        }
    } else {
        $resultado_html = "<div style='color:red;'>❌ Formato de mensagem não reconhecido.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Baixa Real WhatsApp | ZiipVet</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; justify-content: center; padding: 40px; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 500px; }
        h2 { color: #075e54; text-align: center; margin-bottom: 25px; }
        textarea { width: 100%; height: 100px; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 15px; box-sizing: border-box; }
        button { width: 100%; background: #25d366; color: white; border: none; padding: 15px; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 15px; }
        button:hover { background: #128c7e; }
        .resultado { margin-top: 25px; }
    </style>
</head>
<body>
    <div class="card">
        <h2>📱 Baixa Financeira</h2>
        <form method="POST">
            <label style="display:block; margin-bottom:10px; font-weight:bold;">Mensagem do WhatsApp:</label>
            <textarea name="mensagem_zap" placeholder="Ex: Paguei o boleto 83 no valor de 538,63"><?= htmlspecialchars($mensagem_enviada) ?></textarea>
            <button type="submit">EFETUAR BAIXA NO SISTEMA</button>
        </form>
        <div class="resultado"><?= $resultado_html ?></div>
    </div>
</body>
</html>