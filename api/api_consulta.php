<?php
/**
 * ZIIPVET - API DE CONSULTAS
 * ARQUIVO: api/api_consulta.php
 * RESPONSABILIDADE: Processar requisições AJAX relacionadas a consultas
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config/configuracoes.php';

use App\Core\Database;
use App\Utils\Response;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Sao_Paulo');

// ========================================================================================
// ENDPOINT: DADOS DO ANIMAL
// ========================================================================================
if (isset($_GET['ajax_dados_animal']) && isset($_GET['id_paciente'])) {
    $id = (int)$_GET['id_paciente'];
    
    if ($id <= 0) {
        Response::json(['erro' => true, 'message' => 'ID inválido'], 400);
    }
    
    try {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare("SELECT p.*, c.nome as nome_tutor, c.cpf_cnpj, c.telefone, c.endereco, c.numero, c.bairro, c.cidade, c.estado, c.cep, c.email
                                FROM pacientes p 
                                INNER JOIN clientes c ON p.id_cliente = c.id 
                                WHERE p.id = ?");
        $stmt->execute([$id]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$dados) {
            Response::json(['erro' => true, 'message' => 'Paciente não encontrado'], 404);
        }
        
        if ($dados && $dados['data_nascimento']) {
            $nasc = new DateTime($dados['data_nascimento']);
            $hoje = new DateTime();
            $idade = $nasc->diff($hoje);
            $dados['idade_animal'] = $idade->y . ' anos ' . $idade->m . ' meses';
        } else {
            $dados['idade_animal'] = 'não informado';
        }
        
        Response::json($dados);
    } catch (Exception $e) { 
        Response::json(['erro' => true, 'message' => 'Erro ao buscar dados'], 500);
    }
}

// ========================================================================================
// ENDPOINT: HISTÓRICO DE VACINAS
// ========================================================================================
if (isset($_GET['ajax_historico']) && isset($_GET['id_paciente'])) {
    $id = (int)$_GET['id_paciente'];
    
    if ($id <= 0) {
        Response::json(['status' => 'error', 'msg' => 'ID inválido'], 400);
    }
    
    try {
        $pdo = Database::getInstance()->getConnection();
        
        $stmt = $pdo->prepare("SELECT resumo, data_atendimento 
                                FROM atendimentos 
                                WHERE id_paciente = ? AND tipo_atendimento = 'Vacinação' 
                                ORDER BY data_atendimento DESC LIMIT 5");
        $stmt->execute([$id]);
        $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt2 = $pdo->prepare("SELECT id, vacina_nome, dose_prevista, data_prevista 
                                 FROM lembretes_vacinas 
                                 WHERE id_paciente = ? AND status = 'Pendente' 
                                 ORDER BY data_prevista ASC");
        $stmt2->execute([$id]);
        $lembretes = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        Response::json([
            'status' => 'success',
            'historico' => $historico, 
            'lembretes' => $lembretes
        ]);
    } catch (Exception $e) { 
        Response::json(['status' => 'error', 'msg' => $e->getMessage()], 500);
    }
}

// ========================================================================================
// ENDPOINT: HISTÓRICO DE PESO
// ========================================================================================
if (isset($_GET['ajax_peso']) && isset($_GET['id_paciente'])) {
    $id_pac = (int)$_GET['id_paciente'];
    
    if ($id_pac <= 0) {
        echo '<div style="text-align:center; padding:20px; color:#999; font-style:italic; font-size:12px;">ID inválido</div>';
        exit;
    }
    
    try {
        $pdo = Database::getInstance()->getConnection();
        $stmt_ajax = $pdo->prepare("SELECT data_atendimento, peso FROM atendimentos 
                                    WHERE id_paciente = ? AND peso IS NOT NULL AND peso != '' 
                                    ORDER BY data_atendimento DESC LIMIT 10");
        $stmt_ajax->execute([$id_pac]);
        $lista_h = $stmt_ajax->fetchAll(PDO::FETCH_ASSOC);

        if (count($lista_h) > 0) {
            echo '<ul style="list-style:none; padding:0; margin:0;">';
            foreach ($lista_h as $hp) {
                echo '<li style="display:flex; justify-content:space-between; padding:12px 0; border-bottom:1px solid #f4f4f4; font-size:13px;">';
                echo '  <span style="color:#888; font-weight:600;">' . date('d/m/Y', strtotime($hp['data_atendimento'])) . '</span>';
                echo '  <span style="font-weight:700; color:#131c71;">' . htmlspecialchars($hp['peso']) . ' kg</span>';
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<div style="text-align:center; padding:20px; color:#999; font-style:italic; font-size:12px;">Sem registros de peso anteriores</div>';
        }
    } catch (PDOException $e) {
        echo '<span style="color:red; font-size:12px;">Erro ao consultar banco de dados</span>';
    }
    exit;
}

// Se nenhum endpoint foi chamado
Response::json(['erro' => true, 'message' => 'Endpoint não encontrado'], 404);
