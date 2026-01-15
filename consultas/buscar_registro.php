<?php
/**
 * ========================================================================
 * ZIIPVET - BUSCADOR DE REGISTROS DO HISTÓRICO
 * Retorna dados de atendimentos, receitas, exames, etc para edição
 * ========================================================================
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$tipo = $_GET['tipo'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if (empty($tipo) || $id <= 0) {
    echo json_encode(['sucesso' => false, 'erro' => 'Parâmetros inválidos']);
    exit;
}

try {
    switch($tipo) {
        
        // ====================================================================
        // ATENDIMENTO
        // ====================================================================
        case 'atendimento':
            $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE id = ?");
            $stmt->execute([$id]);
            $dados = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($dados) {
                echo json_encode([
                    'sucesso' => true,
                    'tipo_atendimento' => $dados['tipo_atendimento'],
                    'peso' => $dados['peso'],
                    'data_retorno' => $dados['data_retorno'],
                    'resumo' => $dados['resumo'],
                    'descricao' => $dados['descricao']
                ]);
            } else {
                echo json_encode(['sucesso' => false, 'erro' => 'Atendimento não encontrado']);
            }
            break;
        
        // ====================================================================
        // VACINA
        // ====================================================================
        case 'vacina':
            $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE id = ? AND tipo_atendimento = 'Vacinação'");
            $stmt->execute([$id]);
            $dados = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($dados) {
                $resumo = $dados['resumo'];
                $descricao = $dados['descricao'];
                
                // DEBUG
                error_log("VACINA DEBUG - Resumo: " . $resumo);
                error_log("VACINA DEBUG - Descrição: " . $descricao);
                
                $vacina_nome = '';
                $vacina_dose = '';
                
                // FORMATO 1: "Vacina: V10 (Anual) (Reforço Anual)"
                // FORMATO 2: "V8 (Anual) - Dose: 1ª Dose"
                // FORMATO 3: "Antirrábica - Dose: Dose Única"
                
                if (strpos($resumo, 'Vacina:') !== false) {
                    // FORMATO 1
                    if (preg_match('/Vacina:\s*(.+?)\s*\(/', $resumo, $matches)) {
                        $vacina_nome = trim($matches[1]);
                    }
                    if (preg_match('/\(([^)]+)\)\s*$/', $resumo, $matches)) {
                        $vacina_dose = trim($matches[1]);
                    }
                } else if (strpos($resumo, ' - Dose:') !== false) {
                    // FORMATO 2 e 3
                    if (preg_match('/^(.+?)\s*-\s*Dose:\s*(.+)$/s', $resumo, $matches)) {
                        $vacina_nome = trim($matches[1]);
                        $vacina_dose = trim($matches[2]);
                    }
                }
                
                error_log("VACINA DEBUG - Nome extraído: " . $vacina_nome);
                error_log("VACINA DEBUG - Dose extraída: " . $vacina_dose);
                
                // Lote (campo não existe no banco atual, sempre vazio)
                $lote = '';
                
                // Protocolo é a descrição
                $protocolo = $descricao;
                $protocolo = str_replace('\\r\\n', "\n", $protocolo);
                $protocolo = trim($protocolo);
                
                // Data
                $data_aplicacao = date('Y-m-d', strtotime($dados['data_atendimento']));
                
                echo json_encode([
                    'sucesso' => true,
                    'resumo' => $resumo,
                    'descricao' => $descricao,
                    'data_atendimento' => $dados['data_atendimento'],
                    'vacina_nome' => $vacina_nome,
                    'vacina_dose' => $vacina_dose,
                    'vacina_lote' => $lote,
                    'protocolo_texto' => $protocolo,
                    'data_aplicacao' => $data_aplicacao
                ]);
            } else {
                echo json_encode(['sucesso' => false, 'erro' => 'Vacina não encontrada']);
            }
            break;
        
        // ====================================================================
        // PATOLOGIA
        // ====================================================================
        case 'patologia':
            $stmt = $pdo->prepare("SELECT * FROM patologias WHERE id = ?");
            $stmt->execute([$id]);
            $dados = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($dados) {
                echo json_encode([
                    'sucesso' => true,
                    'nome_doenca' => $dados['nome_doenca'],
                    'data_registro' => $dados['data_registro'],
                    'protocolo' => $dados['protocolo_descricao'] ?? $dados['protocolo'] ?? ''
                ]);
            } else {
                echo json_encode(['sucesso' => false, 'erro' => 'Patologia não encontrada']);
            }
            break;
        
        // ====================================================================
        // EXAME
        // ====================================================================
        case 'exame':
            $stmt = $pdo->prepare("SELECT * FROM exames WHERE id = ?");
            $stmt->execute([$id]);
            $dados = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($dados) {
                echo json_encode([
                    'sucesso' => true,
                    'tipo_exame' => $dados['tipo_exame'],
                    'conclusoes_finais' => $dados['conclusoes_finais'] ?? $dados['conclusoes'] ?? '',
                    'data_exame' => $dados['data_exame'],
                    'resultados' => $dados['dados_json_campos'] ?? $dados['dados_json'] ?? $dados['resultados'] ?? ''
                ]);
            } else {
                echo json_encode(['sucesso' => false, 'erro' => 'Exame não encontrado']);
            }
            break;
        
        // ====================================================================
        // RECEITA
        // ====================================================================
        case 'receita':
            $stmt = $pdo->prepare("SELECT * FROM receitas WHERE id = ?");
            $stmt->execute([$id]);
            $dados = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($dados) {
                // Tentar identificar o modelo pela correspondência de conteúdo
                $conteudo_receita = $dados['conteudo'] ?? $dados['conteudo_receita'] ?? '';
                $modelo_id = null;
                
                // Buscar se existe modelo correspondente
                try {
                    $stmt_modelos = $pdo->query("SELECT id, conteudo FROM modelos_receitas");
                    $modelos = $stmt_modelos->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($modelos as $modelo) {
                        // Comparação aproximada (remove espaços extras e tags HTML)
                        $conteudo_limpo = trim(strip_tags($conteudo_receita));
                        $modelo_limpo = trim(strip_tags($modelo['conteudo']));
                        
                        if ($conteudo_limpo === $modelo_limpo) {
                            $modelo_id = $modelo['id'];
                            break;
                        }
                    }
                } catch (Exception $e) {
                    // Tabela modelos_receitas pode não existir
                }
                
                echo json_encode([
                    'sucesso' => true,
                    'conteudo' => $conteudo_receita,
                    'data_emissao' => $dados['data_emissao'],
                    'modelo_id' => $modelo_id,
                    'titulo_modelo' => $dados['titulo_modelo'] ?? null
                ]);
            } else {
                echo json_encode(['sucesso' => false, 'erro' => 'Receita não encontrada']);
            }
            break;
        
        // ====================================================================
        // DOCUMENTO
        // ====================================================================
        case 'documento':
            $stmt = $pdo->prepare("SELECT * FROM documentos_emitidos WHERE id = ?");
            $stmt->execute([$id]);
            $dados = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($dados) {
                echo json_encode([
                    'sucesso' => true,
                    'tipo_documento' => $dados['tipo_documento'],
                    'conteudo' => $dados['conteudo'] ?? $dados['conteudo_documento'] ?? '',
                    'data_emissao' => $dados['data_emissao']
                ]);
            } else {
                echo json_encode(['sucesso' => false, 'erro' => 'Documento não encontrado']);
            }
            break;
        
        // ====================================================================
        // DIAGNÓSTICO IA
        // ====================================================================
        case 'diagnostico-ia':
            $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE id = ? AND tipo_atendimento = 'Diagnóstico IA'");
            $stmt->execute([$id]);
            $dados = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($dados) {
                // Formatar descrição para exibição
                $descricao_formatada = nl2br(htmlspecialchars($dados['descricao']));
                
                echo json_encode([
                    'sucesso' => true,
                    'descricao' => $descricao_formatada,
                    'resumo' => $dados['resumo'],
                    'data_atendimento' => $dados['data_atendimento']
                ]);
            } else {
                echo json_encode(['sucesso' => false, 'erro' => 'Diagnóstico não encontrado']);
            }
            break;
        
        default:
            echo json_encode(['sucesso' => false, 'erro' => 'Tipo de registro desconhecido']);
            break;
    }
    
} catch (PDOException $e) {
    error_log("Erro ao buscar registro: " . $e->getMessage());
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao buscar dados no banco']);
}