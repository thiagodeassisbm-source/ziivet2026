<?php
/**
 * ========================================================================
 * ZIIPVET - BUSCA DE DETALHES - VERSÃO DEFINITIVA
 * VERSÃO: FINAL 4.0 - SEM DEPENDÊNCIA DE COLUNAS EXTRAS
 * ========================================================================
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php_errors.log');

require_once '../auth.php';
require_once '../config/configuracoes.php';

header('Content-Type: application/json; charset=utf-8');

function debug_log($msg) {
    error_log("[BUSCAR DEFINIT] " . $msg);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erro' => 'Método inválido']);
    exit;
}

$tipo = $_POST['tipo'] ?? null;
$id = (int)($_POST['id'] ?? 0);
$id_paciente = (int)($_POST['id_paciente'] ?? 0);

debug_log("=== BUSCA INICIADA ===");
debug_log("Tipo: $tipo | ID: $id | Paciente: $id_paciente");

if (!$tipo || !$id || !$id_paciente) {
    debug_log("ERRO: Parâmetros inválidos");
    echo json_encode(['erro' => 'Parâmetros inválidos']);
    exit;
}

try {
    $dados = [];
    
    // ========================================================================================
    // ATENDIMENTO
    // ========================================================================================
    if ($tipo === 'atendimento') {
        debug_log("Buscando ATENDIMENTO");
        $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE id = ? AND id_paciente = ?");
        $stmt->execute([$id, $id_paciente]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($registro) {
            $dados = [
                'tipo' => 'atendimento',
                'id' => $registro['id'],
                'tipo_atendimento' => $registro['tipo_atendimento'] ?? '',
                'peso' => $registro['peso'] ?? '',
                'resumo' => $registro['resumo'] ?? '',
                'descricao' => $registro['descricao'] ?? '',
                'data_atendimento' => $registro['data_atendimento'] ?? '',
                'data_retorno' => $registro['data_retorno'] ?? '',
                'status' => $registro['status'] ?? ''
            ];
            debug_log("Atendimento encontrado");
        } else {
            debug_log("Atendimento NÃO encontrado");
        }
    }
    
    // ========================================================================================
    // PATOLOGIA
    // ========================================================================================
    else if ($tipo === 'patologia') {
        debug_log("Buscando PATOLOGIA");
        $stmt = $pdo->prepare("SELECT * FROM patologias WHERE id = ? AND id_paciente = ?");
        $stmt->execute([$id, $id_paciente]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($registro) {
            $dados = [
                'tipo' => 'patologia',
                'id' => $registro['id'],
                'nome_doenca' => $registro['nome_doenca'] ?? '',
                'protocolo_descricao' => $registro['protocolo_descricao'] ?? '',
                'data_registro' => $registro['data_registro'] ?? '',
                'usuario_responsavel' => $registro['usuario_responsavel'] ?? ''
            ];
            debug_log("Patologia encontrada: " . $registro['nome_doenca']);
        }
    }
    
    // ========================================================================================
    // EXAME - ADAPTADO PARA NÃO DEPENDER DE dados_json_campos
    // ========================================================================================
    else if ($tipo === 'exame') {
        debug_log("Buscando EXAME");
        $stmt = $pdo->prepare("SELECT * FROM exames WHERE id = ? AND id_paciente = ?");
        $stmt->execute([$id, $id_paciente]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($registro) {
            debug_log("Exame encontrado. Tipo: " . $registro['tipo_exame']);
            
            $dados_completos = [];
            
            // PRIORIDADE 1: dados_json (coluna principal)
            if (!empty($registro['dados_json'])) {
                debug_log("Usando dados_json");
                $json_decodificado = json_decode($registro['dados_json'], true);
                if (is_array($json_decodificado)) {
                    $dados_completos = $json_decodificado;
                    debug_log("JSON decoded OK: " . count($dados_completos) . " campos");
                } else {
                    debug_log("JSON inválido, tentando fallback");
                }
            }
            
            // PRIORIDADE 2: Parse do HTML (fallback)
            if (empty($dados_completos) && !empty($registro['resultados_detalhados'])) {
                debug_log("Fallback: Usando parse HTML");
                $html = $registro['resultados_detalhados'];
                
                // Tenta extrair de <table>
                if (preg_match_all('/<td[^>]*>([^<]+)<\/td>\s*<td[^>]*>([^<]+)<\/td>/i', $html, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $campo = trim(strip_tags($match[1]));
                        $valor = trim(strip_tags($match[2]));
                        $campo_normalizado = strtolower(str_replace([' ', '-'], '_', $campo));
                        $dados_completos[$campo_normalizado] = $valor;
                    }
                    debug_log("HTML parsed: " . count($dados_completos) . " campos extraídos");
                }
            }
            
            $dados = [
                'tipo' => 'exame',
                'id' => $registro['id'],
                'tipo_exame' => $registro['tipo_exame'] ?? '',
                'laboratorio' => $registro['laboratorio'] ?? '',
                'data_exame' => $registro['data_exame'] ?? '',
                'resultados_detalhados' => $registro['resultados_detalhados'] ?? '',
                'conclusoes_finais' => $registro['conclusoes_finais'] ?? '',
                'usuario_responsavel' => $registro['usuario_responsavel'] ?? '',
                'dados_completos' => $dados_completos
            ];
            
            debug_log("Exame montado. Dados completos: " . count($dados_completos) . " campos");
        } else {
            debug_log("Exame NÃO encontrado");
        }
    }
    
    // ========================================================================================
    // VACINA
    // ========================================================================================
    else if ($tipo === 'vacina') {
        debug_log("Buscando VACINA");
        $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE id = ? AND id_paciente = ? AND tipo_atendimento = 'Vacinação'");
        $stmt->execute([$id, $id_paciente]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($registro) {
            $lote = '';
            $resumo = $registro['resumo'] ?? '';
            
            // Extrair lote
            if (preg_match('/Lote:\s*([^\n\r\-]+)/i', $resumo, $matches)) {
                $lote = trim($matches[1]);
            }
            
            $dados = [
                'tipo' => 'vacina',
                'id' => $registro['id'],
                'resumo' => $resumo,
                'descricao' => $registro['descricao'] ?? '',
                'data_atendimento' => $registro['data_atendimento'] ?? '',
                'lote' => $lote,
                'status' => $registro['status'] ?? ''
            ];
            debug_log("Vacina encontrada");
        }
    }
    
    // ========================================================================================
    // RECEITA
    // ========================================================================================
    else if ($tipo === 'receita') {
        debug_log("Buscando RECEITA");
        $stmt = $pdo->prepare("SELECT * FROM receitas WHERE id = ? AND id_paciente = ?");
        $stmt->execute([$id, $id_paciente]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($registro) {
            $dados = [
                'tipo' => 'receita',
                'id' => $registro['id'],
                'conteudo' => $registro['conteudo'] ?? '',
                'data_emissao' => $registro['data_emissao'] ?? ''
            ];
            debug_log("Receita encontrada");
        }
    }
    
    // ========================================================================================
    // DOCUMENTO
    // ========================================================================================
    else if ($tipo === 'documento') {
        debug_log("Buscando DOCUMENTO");
        $stmt = $pdo->prepare("SELECT * FROM documentos_emitidos WHERE id = ? AND id_paciente = ?");
        $stmt->execute([$id, $id_paciente]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($registro) {
            $dados = [
                'tipo' => 'documento',
                'id' => $registro['id'],
                'tipo_documento' => $registro['tipo_documento'] ?? '',
                'conteudo_html' => $registro['conteudo_html'] ?? '',
                'usuario_emissor' => $registro['usuario_emissor'] ?? '',
                'data_emissao' => $registro['data_emissao'] ?? ''
            ];
            debug_log("Documento encontrado");
        }
    }
    
    // ========================================================================================
    // RESPOSTA FINAL
    // ========================================================================================
    if (!empty($dados)) {
        debug_log("SUCCESS - Retornando dados");
        echo json_encode(['sucesso' => true, 'dados' => $dados], JSON_UNESCAPED_UNICODE);
    } else {
        debug_log("ERRO - Registro não encontrado");
        echo json_encode(['erro' => 'Registro não encontrado']);
    }
    
} catch (PDOException $e) {
    debug_log("ERRO PDO: " . $e->getMessage());
    echo json_encode(['erro' => 'Erro: ' . $e->getMessage()]);
}
exit;
?>