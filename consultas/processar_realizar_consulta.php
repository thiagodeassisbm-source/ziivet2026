<?php
/**
 * ========================================================================
 * ZIIPVET - PROCESSAMENTO DE FORMULÁRIOS
 * VERSÃO: 8.2.0 - CORRIGIDO PARA EXAMES
 * ========================================================================
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php_errors.log');

require_once '../auth.php';
require_once '../config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Sao_Paulo');

function debug_log($msg) {
    error_log("[ZIIPVET PROC] " . $msg);
}

// TIMEZONE MySQL
try {
    $pdo->exec("SET SESSION time_zone = '-03:00'");
} catch (Exception $e) {
    debug_log("Erro timezone: " . $e->getMessage());
}

// ========================================================================================
// SALVAR EXAME
// ========================================================================================
if (isset($_POST['salvar_exame'])) {
    debug_log("=== SALVANDO EXAME ===");
    
    $id_paciente = (int)$_POST['id_paciente'];
    $tipo_exame = $_POST['tipo_exame'] ?? '';
    $conclusoes = $_POST['conclusoes'] ?? '';
    $res = $_POST['res'] ?? [];
    $id_registro_edicao = (int)($_POST['id_registro_edicao'] ?? 0);
    
    debug_log("ID Paciente: $id_paciente");
    debug_log("Tipo Exame: $tipo_exame");
    debug_log("Conclusões recebidas: " . (strlen($conclusoes) > 0 ? 'SIM (' . strlen($conclusoes) . ' chars)' : 'NÃO'));
    debug_log("Modo Edição: " . ($id_registro_edicao > 0 ? "SIM (ID: $id_registro_edicao)" : "NÃO"));
    
    if (!$id_paciente || !$tipo_exame) {
        debug_log("ERRO: Campos obrigatórios faltando");
        $_SESSION['msg_erro'] = 'Preencha o tipo de exame';
        header('Location: realizar_consulta.php?id_paciente=' . $id_paciente);
        exit;
    }
    
    // Se conclusões estiver vazio, usar um placeholder
    if (empty(trim(strip_tags($conclusoes)))) {
        $conclusoes = '<p>Aguardando laudo</p>';
        debug_log("Conclusões vazias, usando placeholder");
    }
    
    try {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $pdo->beginTransaction();
        debug_log("Transaction iniciada");
        
        // MONTAR JSON COM TODOS OS DADOS
        $dados_json = json_encode($res, JSON_UNESCAPED_UNICODE);
        debug_log("JSON gerado: " . strlen($dados_json) . " bytes");
        
        // MONTAR HTML PARA COMPATIBILIDADE
        $resultados_html = '<h3>' . htmlspecialchars($tipo_exame) . '</h3>';
        if (!empty($res)) {
            $resultados_html .= '<table border="1" style="width:100%; border-collapse: collapse;">';
            $resultados_html .= '<tr style="background:#f4f4f4;"><th>Parâmetro</th><th>Resultado</th></tr>';
            
            foreach ($res as $campo => $valor) {
                if (!empty($valor)) {
                    $label = ucwords(str_replace('_', ' ', $campo));
                    $resultados_html .= '<tr>';
                    $resultados_html .= '<td style="padding:5px;">' . htmlspecialchars($label) . '</td>';
                    $resultados_html .= '<td style="padding:5px;">' . htmlspecialchars($valor) . '</td>';
                    $resultados_html .= '</tr>';
                }
            }
            $resultados_html .= '</table>';
        }
        
        // Laboratório - buscar em qualquer campo lab_*
        $laboratorio = '';
        $lab_fields = ['lab_bio', 'lab_hemo', 'lab_urina', 'lab_parasito', 'lab_hemo_para', 
                       'lab_radio', 'lab_ultra', 'lab_eco', 'lab_eletro', 'lab_raspado', 
                       'lab_cito', 'lab_leish', 'lab_ehrlichia', 'lab_fivfelv', 'lab_outros'];
        
        foreach ($lab_fields as $field) {
            if (!empty($res[$field])) {
                $laboratorio = $res[$field];
                debug_log("Laboratório encontrado em $field: $laboratorio");
                break;
            }
        }
        
        // Data do exame - buscar em qualquer campo data_*
        $data_exame = date('Y-m-d H:i:s');
        $date_fields = ['data_geral', 'data_bio', 'data_hemo', 'data_urina', 'data_parasito', 'data_hemo_para',
                        'data_radio', 'data_ultra', 'data_eco', 'data_eletro', 'data_raspado',
                        'data_cito', 'data_leish', 'data_ehrlichia', 'data_fivfelv', 'data_outros'];
        
        foreach ($date_fields as $field) {
            if (!empty($res[$field])) {
                $data_exame = str_replace('T', ' ', $res[$field]);
                if (strlen($data_exame) == 16) {
                    $data_exame .= ':00';
                }
                debug_log("Data encontrada em $field: $data_exame");
                break;
            }
        }
        
        $usuario = $_SESSION['usuario_nome'] ?? 'Sistema';
        
        if ($id_registro_edicao > 0) {
            // MODO EDIÇÃO
            debug_log("UPDATE no registro ID: $id_registro_edicao");
            
            $stmt = $pdo->prepare("UPDATE exames SET 
                                  tipo_exame = ?,
                                  laboratorio = ?,
                                  data_exame = ?,
                                  resultados_detalhados = ?,
                                  conclusoes_finais = ?,
                                  dados_json = ?,
                                  usuario_responsavel = ?,
                                  data_atualizacao = NOW()
                                  WHERE id = ? AND id_paciente = ?");
            
            $stmt->execute([
                $tipo_exame,
                $laboratorio,
                $data_exame,
                $resultados_html,
                $conclusoes,
                $dados_json,
                $usuario,
                $id_registro_edicao,
                $id_paciente
            ]);
            
            debug_log("UPDATE executado. Linhas afetadas: " . $stmt->rowCount());
            $_SESSION['msg_sucesso'] = 'Exame atualizado com sucesso!';
            
        } else {
            // MODO NOVO
            debug_log("INSERT de novo exame");
            
            $stmt = $pdo->prepare("INSERT INTO exames 
                                  (id_paciente, tipo_exame, laboratorio, data_exame, resultados_detalhados, 
                                   conclusoes_finais, dados_json, usuario_responsavel, data_registro)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $stmt->execute([
                $id_paciente,
                $tipo_exame,
                $laboratorio,
                $data_exame,
                $resultados_html,
                $conclusoes,
                $dados_json,
                $usuario
            ]);
            
            $novo_id = $pdo->lastInsertId();
            debug_log("INSERT executado. Novo ID: $novo_id");
            $_SESSION['msg_sucesso'] = 'Exame registrado com sucesso! ID: ' . $novo_id;
        }
        
        $pdo->commit();
        debug_log("Transaction commit OK");
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        debug_log("ERRO FATAL: " . $e->getMessage());
        debug_log("Stack trace: " . $e->getTraceAsString());
        $_SESSION['msg_erro'] = 'Erro: ' . $e->getMessage();
    }
    
    header('Location: realizar_consulta.php?id_paciente=' . $id_paciente);
    exit;
}

// ========================================================================================
// SALVAR ATENDIMENTO
// ========================================================================================
if (isset($_POST['salvar_atendimento'])) {
    debug_log("=== SALVANDO ATENDIMENTO ===");
    
    $id_paciente = (int)$_POST['id_paciente'];
    $tipo_atendimento = $_POST['tipo_atendimento'] ?? '';
    $peso = $_POST['peso'] ?? null;
    $resumo = $_POST['resumo'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $data_retorno = $_POST['data_retorno'] ?? null;
    $id_registro_edicao = (int)($_POST['id_registro_edicao'] ?? 0);
    
    if (!$id_paciente || !$tipo_atendimento || !$descricao) {
        $_SESSION['msg_erro'] = 'Preencha os campos obrigatórios';
        header('Location: realizar_consulta.php?id_paciente=' . $id_paciente);
        exit;
    }
    
    try {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $pdo->beginTransaction();
        
        if ($id_registro_edicao > 0) {
            $stmt = $pdo->prepare("UPDATE atendimentos SET 
                                  tipo_atendimento = ?,
                                  peso = ?,
                                  resumo = ?,
                                  descricao = ?,
                                  data_retorno = ?
                                  WHERE id = ? AND id_paciente = ?");
            
            $stmt->execute([
                $tipo_atendimento,
                $peso ?: null,
                $resumo,
                $descricao,
                $data_retorno ?: null,
                $id_registro_edicao,
                $id_paciente
            ]);
            
            $_SESSION['msg_sucesso'] = 'Atendimento atualizado!';
        } else {
            $stmt = $pdo->prepare("INSERT INTO atendimentos 
                                  (id_paciente, tipo_atendimento, peso, resumo, descricao, data_atendimento, data_retorno, status)
                                  VALUES (?, ?, ?, ?, ?, NOW(), ?, 'Ativo')");
            
            $stmt->execute([
                $id_paciente,
                $tipo_atendimento,
                $peso ?: null,
                $resumo,
                $descricao,
                $data_retorno ?: null
            ]);
            
            $_SESSION['msg_sucesso'] = 'Atendimento registrado!';
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        debug_log("ERRO Atendimento: " . $e->getMessage());
        $_SESSION['msg_erro'] = 'Erro: ' . $e->getMessage();
    }
    
    header('Location: realizar_consulta.php?id_paciente=' . $id_paciente);
    exit;
}

// ========================================================================================
// SALVAR PATOLOGIA
// ========================================================================================
if (isset($_POST['salvar_patologia'])) {
    debug_log("=== SALVANDO PATOLOGIA ===");
    
    $id_paciente = (int)$_POST['id_paciente'];
    $nome_doenca = $_POST['patologia_nome'] ?? '';
    $protocolo_descricao = $_POST['protocolo_descricao'] ?? '';
    $data_registro_raw = $_POST['data_registro'] ?? '';
    $id_registro_edicao = (int)($_POST['id_registro_edicao'] ?? 0);
    
    debug_log("Data recebida: '$data_registro_raw'");
    
    if (!$id_paciente || !$nome_doenca) {
        $_SESSION['msg_erro'] = 'Preencha os campos obrigatórios';
        header('Location: realizar_consulta.php?id_paciente=' . $id_paciente);
        exit;
    }
    
    // CORREÇÃO: Montar data/hora corretamente
    $hora_atual = date('H:i:s');
    if (empty($data_registro_raw)) {
        $data_hora_final = date('Y-m-d H:i:s');
    } else {
        // Se já tem hora (datetime-local), usar como está
        if (strpos($data_registro_raw, 'T') !== false) {
            $data_hora_final = str_replace('T', ' ', $data_registro_raw);
            if (strlen($data_hora_final) == 16) {
                $data_hora_final .= ':00';
            }
        } else {
            // Se só tem data, adicionar hora atual
            $data_hora_final = $data_registro_raw . ' ' . $hora_atual;
        }
    }
    
    debug_log("Data/hora final montada: '$data_hora_final'");
    
    try {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $pdo->beginTransaction();
        
        $usuario = $_SESSION['usuario_nome'] ?? 'Sistema';
        
        if ($id_registro_edicao > 0) {
            $stmt = $pdo->prepare("UPDATE patologias SET 
                                  nome_doenca = ?,
                                  protocolo_descricao = ?,
                                  data_registro = ?,
                                  usuario_responsavel = ?
                                  WHERE id = ? AND id_paciente = ?");
            
            $stmt->execute([
                $nome_doenca,
                $protocolo_descricao,
                $data_hora_final,
                $usuario,
                $id_registro_edicao,
                $id_paciente
            ]);
            
            debug_log("UPDATE patologia executado com data: $data_hora_final");
            $_SESSION['msg_sucesso'] = 'Patologia atualizada!';
        } else {
            $stmt = $pdo->prepare("INSERT INTO patologias 
                                  (id_paciente, nome_doenca, protocolo_descricao, data_registro, usuario_responsavel)
                                  VALUES (?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $id_paciente,
                $nome_doenca,
                $protocolo_descricao,
                $data_hora_final,
                $usuario
            ]);
            
            debug_log("INSERT patologia executado com data: $data_hora_final");
            $_SESSION['msg_sucesso'] = 'Patologia registrada!';
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        debug_log("ERRO Patologia: " . $e->getMessage());
        $_SESSION['msg_erro'] = 'Erro: ' . $e->getMessage();
    }
    
    header('Location: realizar_consulta.php?id_paciente=' . $id_paciente);
    exit;
}

// ========================================================================================
// SALVAR VACINA
// ========================================================================================
if (isset($_POST['salvar_vacina'])) {
    debug_log("=== SALVANDO VACINA ===");
    
    $id_paciente = (int)$_POST['id_paciente'];
    $vacina_nome = $_POST['vacina_nome'] ?? '';
    $vacina_dose = $_POST['vacina_dose'] ?? '';
    $data_aplicacao_raw = $_POST['data_aplicacao'] ?? '';
    $vacina_lote = $_POST['vacina_lote'] ?? '';
    $protocolo_texto = $_POST['protocolo_texto'] ?? '';
    $id_registro_edicao = (int)($_POST['id_registro_edicao'] ?? 0);
    
    debug_log("Data recebida: '$data_aplicacao_raw'");
    
    if (!$id_paciente || !$vacina_nome || !$vacina_dose) {
        $_SESSION['msg_erro'] = 'Preencha os campos obrigatórios';
        header('Location: realizar_consulta.php?id_paciente=' . $id_paciente);
        exit;
    }
    
    // CORREÇÃO: Montar data/hora corretamente
    $hora_atual = date('H:i:s');
    if (empty($data_aplicacao_raw)) {
        $data_hora_final = date('Y-m-d H:i:s');
    } else {
        // Se já tem hora (datetime-local), usar como está
        if (strpos($data_aplicacao_raw, 'T') !== false) {
            $data_hora_final = str_replace('T', ' ', $data_aplicacao_raw);
            if (strlen($data_hora_final) == 16) {
                $data_hora_final .= ':00';
            }
        } else {
            // Se só tem data, adicionar hora atual
            $data_hora_final = $data_aplicacao_raw . ' ' . $hora_atual;
        }
    }
    
    debug_log("Data/hora final montada: '$data_hora_final'");
    
    try {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $pdo->beginTransaction();
        
        $resumo = $vacina_nome . ' - Dose: ' . $vacina_dose;
        if ($vacina_lote) {
            $resumo .= ' - Lote: ' . $vacina_lote;
        }
        
        if ($id_registro_edicao > 0) {
            $stmt = $pdo->prepare("UPDATE atendimentos SET 
                                  resumo = ?,
                                  descricao = ?,
                                  data_atendimento = ?
                                  WHERE id = ? AND id_paciente = ? AND tipo_atendimento = 'Vacinação'");
            
            $stmt->execute([
                $resumo,
                $protocolo_texto,
                $data_hora_final,
                $id_registro_edicao,
                $id_paciente
            ]);
            
            debug_log("UPDATE vacina executado com data: $data_hora_final");
            $_SESSION['msg_sucesso'] = 'Vacinação atualizada!';
        } else {
            $stmt = $pdo->prepare("INSERT INTO atendimentos 
                                  (id_paciente, tipo_atendimento, resumo, descricao, data_atendimento, status)
                                  VALUES (?, 'Vacinação', ?, ?, ?, 'Ativo')");
            
            $stmt->execute([
                $id_paciente,
                $resumo,
                $protocolo_texto,
                $data_hora_final
            ]);
            
            debug_log("INSERT vacina executado com data: $data_hora_final");
            
            // Atualizar lembrete se existir
            try {
                $stmt_update = $pdo->prepare("UPDATE lembretes_vacinas SET status = 'Aplicada', data_aplicacao = NOW() 
                                              WHERE id_paciente = ? AND vacina_nome = ? AND status = 'Pendente' LIMIT 1");
                $stmt_update->execute([$id_paciente, $vacina_nome]);
            } catch (Exception $e) {}
            
            $_SESSION['msg_sucesso'] = 'Vacinação registrada!';
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        debug_log("ERRO Vacina: " . $e->getMessage());
        $_SESSION['msg_erro'] = 'Erro: ' . $e->getMessage();
    }
    
    header('Location: realizar_consulta.php?id_paciente=' . $id_paciente);
    exit;
}

// ========================================================================================
// SALVAR RECEITA
// ========================================================================================
if (isset($_POST['salvar_receita'])) {
    debug_log("=== SALVANDO RECEITA ===");
    
    $id_paciente = (int)$_POST['id_paciente'];
    $conteudo = $_POST['conteudo_receita'] ?? '';
    $id_registro_edicao = (int)($_POST['id_registro_edicao'] ?? 0);
    
    if (!$id_paciente || !$conteudo) {
        $_SESSION['msg_erro'] = 'Preencha os campos obrigatórios';
        header('Location: realizar_consulta.php?id_paciente=' . $id_paciente);
        exit;
    }
    
    try {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $pdo->beginTransaction();
        
        if ($id_registro_edicao > 0) {
            $stmt = $pdo->prepare("UPDATE receitas SET conteudo = ? WHERE id = ? AND id_paciente = ?");
            $stmt->execute([$conteudo, $id_registro_edicao, $id_paciente]);
            $_SESSION['msg_sucesso'] = 'Receita atualizada!';
        } else {
            $stmt = $pdo->prepare("INSERT INTO receitas (id_paciente, conteudo, data_emissao) VALUES (?, ?, NOW())");
            $stmt->execute([$id_paciente, $conteudo]);
            $_SESSION['msg_sucesso'] = 'Receita emitida!';
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['msg_erro'] = 'Erro: ' . $e->getMessage();
    }
    
    header('Location: realizar_consulta.php?id_paciente=' . $id_paciente);
    exit;
}

// ========================================================================================
// SALVAR DOCUMENTO
// ========================================================================================
if (isset($_POST['salvar_documento'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $id_paciente = (int)($_POST['id_paciente'] ?? 0);
    $tipo_doc = $_POST['tipo_documento'] ?? '';
    $conteudo = $_POST['conteudo_documento'] ?? '';
    $usuario = $_SESSION['usuario_nome'] ?? 'Veterinário';
    $id_registro_edicao = (int)($_POST['id_registro_edicao'] ?? 0);

    debug_log("=== SALVANDO DOCUMENTO ===");
    debug_log("Paciente: $id_paciente | Tipo: $tipo_doc");

    if ($id_paciente && $conteudo) {
        try {
            if ($id_registro_edicao > 0) {
                $stmt = $pdo->prepare("UPDATE documentos_emitidos SET tipo_documento = ?, conteudo_html = ?, usuario_emissor = ? WHERE id = ? AND id_paciente = ?");
                $stmt->execute([$tipo_doc, $conteudo, $usuario, $id_registro_edicao, $id_paciente]);
                echo json_encode(['sucesso' => true, 'id' => $id_registro_edicao, 'acao' => 'atualizado']);
            } else {
                $stmt = $pdo->prepare("INSERT INTO documentos_emitidos (id_paciente, tipo_documento, conteudo_html, usuario_emissor, data_emissao) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$id_paciente, $tipo_doc, $conteudo, $usuario]);
                echo json_encode(['sucesso' => true, 'id' => $pdo->lastInsertId(), 'acao' => 'criado']);
            }
        } catch (Exception $e) {
            debug_log("ERRO Documento: " . $e->getMessage());
            echo json_encode(['erro' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['erro' => 'Dados incompletos']);
    }
    exit;
}

// ========================================================================================
// SALVAR MODELO RECEITA
// ========================================================================================
if (isset($_POST['salvar_modelo'])) {
    $novo_titulo = $_POST['novo_titulo'] ?? '';
    $novo_conteudo = $_POST['novo_conteudo'] ?? '';
    $id_paciente = (int)($_POST['id_paciente'] ?? 0);
    
    if (!$novo_titulo || !$novo_conteudo) {
        $_SESSION['msg_erro'] = 'Preencha os campos obrigatórios';
        header('Location: realizar_consulta.php' . ($id_paciente ? '?id_paciente=' . $id_paciente : ''));
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO modelos_receitas (titulo, conteudo, data_criacao) VALUES (?, ?, NOW())");
        $stmt->execute([$novo_titulo, $novo_conteudo]);
        $_SESSION['msg_sucesso'] = 'Modelo criado com sucesso!';
    } catch (Exception $e) {
        $_SESSION['msg_erro'] = 'Erro: ' . $e->getMessage();
    }
    
    header('Location: realizar_consulta.php' . ($id_paciente ? '?id_paciente=' . $id_paciente : ''));
    exit;
}

// Fallback
header('Location: realizar_consulta.php');
exit;
?>