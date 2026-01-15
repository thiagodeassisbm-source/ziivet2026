<?php
/**
 * BUSCAR VACINA - VERSÃO CORRIGIDA FINAL
 */

header('Content-Type: application/json');

require_once '../auth.php';
require_once '../config/configuracoes.php';

$id = $_GET['id'] ?? 0;

if (!$id) {
    echo json_encode(['sucesso' => false, 'erro' => 'ID não fornecido']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE id = ? AND tipo_atendimento = 'Vacinação'");
    $stmt->execute([$id]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dados) {
        echo json_encode(['sucesso' => false, 'erro' => 'Vacina não encontrada']);
        exit;
    }
    
    $resumo = $dados['resumo'];
    $descricao = $dados['descricao'];
    
    // ========================================================================
    // EXTRAIR NOME E DOSE
    // ========================================================================
    $vacina_nome = '';
    $vacina_dose = '';
    
    // FORMATO: "V8 (Anual) - Dose: 1ª Dose - Lote: ABC123"
    // ou: "Quádrupla Felina (V4) - Dose: Dose Única - Lote: 111"
    
    if (strpos($resumo, ' - Dose:') !== false) {
        $partes = explode(' - Dose:', $resumo);
        $vacina_nome = trim($partes[0]);
        
        // A dose pode ter " - Lote:" no final
        if (isset($partes[1])) {
            $dose_e_lote = trim($partes[1]);
            
            // Separar dose de lote
            if (strpos($dose_e_lote, ' - Lote:') !== false) {
                $subpartes = explode(' - Lote:', $dose_e_lote);
                $vacina_dose = trim($subpartes[0]); // SÓ A DOSE
            } else {
                $vacina_dose = $dose_e_lote;
            }
        }
    }
    
    // ========================================================================
    // EXTRAIR LOTE DA DESCRIÇÃO (HTML)
    // ========================================================================
    $lote = '';
    if (preg_match('/<strong>Lote\/Fabricante:<\/strong>\s*([^<\r\n]+)/i', $descricao, $matches)) {
        $lote = trim($matches[1]);
    }
    
    // Se não achou no HTML, tentar no resumo
    if (empty($lote) && strpos($resumo, ' - Lote:') !== false) {
        $partes_lote = explode(' - Lote:', $resumo);
        if (isset($partes_lote[1])) {
            $lote = trim($partes_lote[1]);
        }
    }
    
    // ========================================================================
    // PROTOCOLO
    // ========================================================================
    $protocolo = $descricao;
    if (preg_match('/<strong>Protocolo Aplicado:<\/strong>(.+)$/s', $protocolo, $matches)) {
        $protocolo = $matches[1];
    }
    $protocolo = str_replace(['<br />', '<br/>', '<br>', '\\r\\n'], "\n", $protocolo);
    $protocolo = strip_tags($protocolo);
    $protocolo = html_entity_decode($protocolo);
    $protocolo = trim($protocolo);
    
    // ========================================================================
    // DATA
    // ========================================================================
    $data_aplicacao = date('Y-m-d', strtotime($dados['data_atendimento']));
    
    // ========================================================================
    // RETORNO
    // ========================================================================
    echo json_encode([
        'sucesso' => true,
        'vacina_nome' => $vacina_nome,
        'vacina_dose' => $vacina_dose, // SÓ A DOSE, SEM LOTE
        'vacina_lote' => $lote,
        'protocolo_texto' => $protocolo,
        'data_aplicacao' => $data_aplicacao,
        'debug_resumo' => $resumo,
        'debug_descricao' => substr($descricao, 0, 200)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
?>