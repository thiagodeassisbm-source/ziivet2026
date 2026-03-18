<?php
/**
 * Script para emitir NFC-e via AJAX (botão na listagem de vendas)
 */
header('Content-Type: application/json');
require_once '../auth.php';
require_once '../config/configuracoes.php';
require_once '../vendor/autoload.php';

use App\Services\NFCeService;

// Verificar permissão
if (empty($_SESSION['id_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

// Recebe ID da venda enviado pelo front
$id_venda = isset($_POST['id_venda']) ? (int)$_POST['id_venda'] : 0;
if ($id_venda <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID da venda inválido.']);
    exit;
}

try {
    $nfcService = new NFCeService($pdo);

    // Contexto da venda para diagnósticos (não altera lógica de emissão)
    $stmtVenda = $pdo->prepare("SELECT id, nfce_status, nfce_chave, valor_total FROM vendas WHERE id = ?");
    $stmtVenda->execute([$id_venda]);
    $vendaInfo = $stmtVenda->fetch(PDO::FETCH_ASSOC) ?: null;

    $resultado = $nfcService->emitir($id_venda);

    if ($resultado['success']) {
        // Salvar dados na tabela vendas
        $chave = $resultado['chave'];
        $protocolo = $resultado['protocolo'];
        // URL Padrão de Consulta/Danfe SEFAZ GO (Homologação ou Produção - detectado pela chave)
        // Ambiente: 52(GO) + Ano + Mes + CNPJ + ...
        // TPAMB está no XML, mas vamos assumir Homologação se config diz homologacao.
        // O link QRCode vem dentro do XML na tag <qrCode>, mas é complexo extrair agora.
        // Vamos usar o link padrão de consulta por chave.
        
        // Link Genérico de Consulta (GO)
        // Homologação: https://nfewebhomolog.sefaz.go.gov.br/nfeweb/sites/nfce/danfeNFCe?p=
        // Produção: https://nfeweb.sefaz.go.gov.br/nfeweb/sites/nfce/danfeNFCe?p=
        
        // Melhor: Salvar o XML em arquivo para garantir
        $anoMes = date('Ym');
        $xmlDir = __DIR__ . "/xmls/$anoMes";
        if (!is_dir($xmlDir)) mkdir($xmlDir, 0777, true);
        
        $xmlFile = "$xmlDir/{$chave}-nfe.xml";
        file_put_contents($xmlFile, $resultado['xml']);
        
        // Extrair URL do QRCode (Link válido para visualização)
        $url_consulta = '';
        try {
            $dom = new DOMDocument;
            $dom->loadXML($resultado['xml']);
            $qrCodeTag = $dom->getElementsByTagName('qrCode')->item(0);
            if ($qrCodeTag) {
                $url_consulta = trim($qrCodeTag->nodeValue);
                // Se estiver envolto em CDATA, o nodeValue pega o conteúdo corretamente
            }
        } catch (Exception $e) {
            // Fallback se falhar
        }

        if (empty($url_consulta)) {
             // Fallback para URL de consulta genérica se não conseguir extrair
             $url_consulta = "https://nfewebhomolog.sefaz.go.gov.br/nfeweb/sites/nfce/consulta"; 
        }

        $stmt = $pdo->prepare("UPDATE vendas SET 
            nfce_chave = ?, 
            nfce_protocolo = ?, 
            nfce_status = 'AUTORIZADA', 
            nfce_data_emissao = NOW(),
            nfce_url = ? 
            WHERE id = ?");
        $stmt->execute([$chave, $protocolo, $url_consulta, $id_venda]);

        echo json_encode([
            'success' => true, 
            'message' => 'NFC-e emitida com sucesso!',
            'chave' => $chave,
            'url' => $url_consulta
        ]);
    } else {
        $extra = '';
        if ($vendaInfo) {
            $extra = " | venda_id={$vendaInfo['id']} status_nfce=" . ($vendaInfo['nfce_status'] ?? '') . " chave_nfce=" . ($vendaInfo['nfce_chave'] ? substr((string)$vendaInfo['nfce_chave'], 0, 20) . '...' : '');
        }
        echo json_encode([
            'success' => false,
            'message' => ($resultado['message'] ?? 'Erro desconhecido.') . $extra
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
