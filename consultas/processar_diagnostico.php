<?php
/**
 * =========================================================================================
 * ZIIPVET - PROCESSADOR DE DIAGNÓSTICO POR IA
 * ARQUIVO: processar_diagnostico.php
 * VERSÃO: 2.0.0 - COM SALVAMENTO NO PRONTUÁRIO
 * =========================================================================================
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';
require_once 'config_ia.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// ========================================================================================
// SALVAR DIAGNÓSTICO NO PRONTUÁRIO
// ========================================================================================
if (isset($_POST['salvar_diagnostico'])) {
    try {
        $id_paciente = (int)$_POST['id_paciente'];
        $diagnostico = $_POST['diagnostico'] ?? '';
        $sintomas = $_POST['sintomas'] ?? '';
        $tempo_sintomas = $_POST['tempo_sintomas'] ?? 'não informado';
        $alimentacao = $_POST['alimentacao'] ?? 'não informado';
        $descricao_adicional = $_POST['descricao_adicional'] ?? '';
        
        if (!$id_paciente || empty($diagnostico)) {
            echo json_encode(['sucesso' => false, 'erro' => 'Dados incompletos']);
            exit;
        }
        
        // Montar resumo
        $resumo = "Diagnóstico por IA - Sintomas: " . mb_substr($sintomas, 0, 100);
        
        // Montar descrição completa
        $descricao_completa = "=== DIAGNÓSTICO ASSISTIDO POR INTELIGÊNCIA ARTIFICIAL ===\n\n";
        $descricao_completa .= "Data/Hora: " . date('d/m/Y H:i:s') . "\n\n";
        $descricao_completa .= "SINTOMAS REPORTADOS:\n" . $sintomas . "\n\n";
        $descricao_completa .= "TEMPO DOS SINTOMAS: " . $tempo_sintomas . "\n";
        $descricao_completa .= "ALIMENTAÇÃO: " . $alimentacao . "\n\n";
        
        if (!empty($descricao_adicional)) {
            $descricao_completa .= "OBSERVAÇÕES ADICIONAIS:\n" . $descricao_adicional . "\n\n";
        }
        
        $descricao_completa .= "=== ANÁLISE DA IA ===\n\n";
        $descricao_completa .= strip_tags($diagnostico);
        $descricao_completa .= "\n\n=== AVISO IMPORTANTE ===\n";
        $descricao_completa .= "Este diagnóstico é uma sugestão baseada em inteligência artificial e serve ";
        $descricao_completa .= "apenas como apoio à decisão clínica. O diagnóstico definitivo deve ser feito ";
        $descricao_completa .= "pelo médico veterinário através de exame clínico presencial.";
        
        // Inserir no banco de dados
        $stmt = $pdo->prepare("
            INSERT INTO atendimentos (
                id_paciente, 
                tipo_atendimento, 
                resumo, 
                descricao, 
                data_atendimento
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $id_paciente,
            'Diagnóstico IA',
            $resumo,
            $descricao_completa
        ]);
        
        $id_atendimento = $pdo->lastInsertId();
        
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Diagnóstico salvo com sucesso',
            'id_atendimento' => $id_atendimento
        ]);
        
    } catch (PDOException $e) {
        error_log("Erro ao salvar diagnóstico IA: " . $e->getMessage());
        echo json_encode([
            'sucesso' => false, 
            'erro' => 'Erro ao salvar no banco de dados',
            'detalhes' => $e->getMessage() // Adicionar detalhes do erro
        ]);
    }
    exit;
}

// ========================================================================================
// REALIZAR DIAGNÓSTICO COM IA
// ========================================================================================
if (isset($_POST['sintomas']) && isset($_POST['id_paciente'])) {
    
    // Verificar se a API está configurada
    if (!iaConfigurada()) {
        echo json_encode([
            'sucesso' => false,
            'erro' => 'API do Google Gemini não configurada. Configure a chave em config_ia.php'
        ]);
        exit;
    }
    
    try {
        $id_paciente = (int)$_POST['id_paciente'];
        $paciente = $_POST['paciente'] ?? [];
        $historico = $_POST['historico'] ?? [];
        $sintomas = $_POST['sintomas'] ?? [];
        $tempo_sintomas = $_POST['tempo_sintomas'] ?? 'não informado';
        $alimentacao = $_POST['alimentacao'] ?? 'não informado';
        $descricao = $_POST['descricao'] ?? '';
        
        // Validações
        if (empty($sintomas)) {
            echo json_encode(['sucesso' => false, 'erro' => 'Nenhum sintoma selecionado']);
            exit;
        }
        
        // Traduzir sintomas técnicos para texto legível
        $sintomas_traducao = [
            'vomito' => 'Vômito',
            'vomito_sangue' => 'Vômito com sangue',
            'diarreia' => 'Diarréia',
            'diarreia_sangue' => 'Diarréia com sangue',
            'tosse' => 'Tosse',
            'letargia' => 'Letargia/Apatia',
            'coceira' => 'Coceira intensa',
            'febre' => 'Febre',
            // ... adicione mais conforme necessário
        ];
        
        $sintomas_texto = [];
        foreach ($sintomas as $s) {
            $sintomas_texto[] = $sintomas_traducao[$s] ?? $s;
        }
        
        // Montar prompt para a IA
        $prompt = construirPromptDiagnostico($paciente, $historico, $sintomas_texto, $tempo_sintomas, $alimentacao, $descricao);
        
        // Chamar API do Google Gemini
        $diagnostico = chamarGeminiAPI($prompt);
        
        if ($diagnostico) {
            echo json_encode([
                'sucesso' => true,
                'diagnostico' => $diagnostico,
                'sintomas_enviados' => implode(', ', $sintomas_texto)
            ]);
        } else {
            echo json_encode([
                'sucesso' => false,
                'erro' => 'A IA não conseguiu gerar um diagnóstico. Tente novamente.'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Erro no diagnóstico IA: " . $e->getMessage());
        echo json_encode([
            'sucesso' => false,
            'erro' => 'Erro ao processar diagnóstico: ' . $e->getMessage()
        ]);
    }
    exit;
}

// ========================================================================================
// FUNÇÕES AUXILIARES
// ========================================================================================

function construirPromptDiagnostico($paciente, $historico, $sintomas, $tempo, $alimentacao, $descricao) {
    $prompt = "Você é um assistente de diagnóstico veterinário especializado. Analise as informações do paciente e forneça um diagnóstico diferencial.\n\n";
    
    $prompt .= "=== DADOS DO PACIENTE ===\n";
    $prompt .= "Nome: " . ($paciente['nome_paciente'] ?? 'Não informado') . "\n";
    $prompt .= "Espécie: " . ($paciente['especie'] ?? 'Não informado') . "\n";
    $prompt .= "Raça: " . ($paciente['raca'] ?? 'SRD') . "\n";
    $prompt .= "Idade: " . ($paciente['idade_texto'] ?? 'Não informado') . "\n";
    $prompt .= "Peso: " . ($paciente['peso'] ?? 'Não informado') . " kg\n";
    $prompt .= "Sexo: " . ($paciente['sexo'] ?? 'Não informado') . "\n\n";
    
    $prompt .= "=== SINTOMAS APRESENTADOS ===\n";
    $prompt .= implode(", ", $sintomas) . "\n";
    $prompt .= "Tempo dos sintomas: " . $tempo . "\n";
    $prompt .= "Alimentação: " . $alimentacao . "\n\n";
    
    if (!empty($descricao)) {
        $prompt .= "=== DESCRIÇÃO ADICIONAL ===\n";
        $prompt .= $descricao . "\n\n";
    }
    
    if (!empty($historico['patologias'])) {
        $prompt .= "=== HISTÓRICO DE PATOLOGIAS ===\n";
        foreach ($historico['patologias'] as $p) {
            $prompt .= "- " . $p['nome_doenca'] . " (" . date('d/m/Y', strtotime($p['data_registro'])) . ")\n";
        }
        $prompt .= "\n";
    }
    
    if (!empty($historico['atendimentos'])) {
        $prompt .= "=== ÚLTIMOS ATENDIMENTOS ===\n";
        foreach (array_slice($historico['atendimentos'], 0, 3) as $a) {
            $prompt .= "- " . $a['tipo_atendimento'];
            if (!empty($a['resumo'])) $prompt .= ": " . $a['resumo'];
            $prompt .= " (" . date('d/m/Y', strtotime($a['data_atendimento'])) . ")\n";
        }
        $prompt .= "\n";
    }
    
    $prompt .= "=== INSTRUÇÕES ===\n";
    $prompt .= "Com base nas informações acima, forneça:\n\n";
    $prompt .= "🔍 DIAGNÓSTICOS PROVÁVEIS:\n";
    $prompt .= "Liste os 3 diagnósticos mais prováveis, do mais provável para o menos provável\n\n";
    $prompt .= "📋 EXAMES RECOMENDADOS:\n";
    $prompt .= "Sugira exames laboratoriais e de imagem necessários para confirmar o diagnóstico\n\n";
    $prompt .= "💊 CONDUTA SUGERIDA:\n";
    $prompt .= "Recomendações de tratamento inicial e cuidados\n\n";
    $prompt .= "⚠️ NÍVEL DE URGÊNCIA:\n";
    $prompt .= "Classifique como: Baixo, Médio, Alto ou Emergência\n\n";
    $prompt .= "📝 OBSERVAÇÕES:\n";
    $prompt .= "Informações importantes adicionais\n\n";
    $prompt .= "IMPORTANTE: Seja objetivo e use linguagem técnica veterinária apropriada.";
    
    return $prompt;
}

function chamarGeminiAPI($prompt) {
    $api_key = GEMINI_API_KEY;
    $url = GEMINI_API_URL;
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'topK' => 40,
            'topP' => 0.95,
            'maxOutputTokens' => 2048,
        ]
    ];
    
    $ch = curl_init($url . '?key=' . $api_key);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $result = json_decode($response, true);
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return $result['candidates'][0]['content']['parts'][0]['text'];
        }
    }
    
    error_log("Erro API Gemini - HTTP $http_code: $response");
    return false;
}