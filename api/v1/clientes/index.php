<?php
/**
 * ZIIPVET - API REST v1
 * ENDPOINT: /api/v1/clientes
 * DESCRIÇÃO: Controller REST para gerenciamento de clientes
 */

// Headers CORS e JSON
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Responder a requisições OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/configuracoes.php';

use App\Core\Database;
use App\Utils\Response;
use App\Infrastructure\Repository\ClienteRepository;
use App\Application\Service\ClienteService;

// Inicializar sessão se necessário (para autenticação futura)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// TODO: Adicionar autenticação JWT aqui
// if (!verificarToken()) {
//     Response::json(['error' => 'Não autorizado'], 401);
// }

// Inicializar dependências
try {
    $db = Database::getInstance();
    $clienteRepository = new ClienteRepository($db);
    $clienteService = new ClienteService($clienteRepository);
} catch (Exception $e) {
    Response::json([
        'error' => 'Erro ao inicializar serviço',
        'message' => $e->getMessage()
    ], 500);
}

// Roteamento baseado no método HTTP
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet($clienteService);
        break;
    
    case 'POST':
        handlePost($clienteService);
        break;
    
    case 'PUT':
        handlePut($clienteService);
        break;
    
    case 'DELETE':
        handleDelete($clienteService);
        break;
    
    default:
        Response::json([
            'error' => 'Método não permitido',
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE']
        ], 405);
}

/**
 * GET /api/v1/clientes
 * Lista clientes com paginação
 */
function handleGet(ClienteService $service): void
{
    $busca = $_GET['busca'] ?? '';
    $pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    $limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? (int)$_GET['limite'] : 20;
    
    // Validações
    if ($pagina < 1) {
        Response::json(['error' => 'Página deve ser maior que 0'], 400);
    }
    
    if ($limite < 1 || $limite > 100) {
        Response::json(['error' => 'Limite deve estar entre 1 e 100'], 400);
    }
    
    try {
        // Buscar por ID específico
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $cliente = $service->buscarPorId($id);
            
            if (!$cliente) {
                Response::json(['error' => 'Cliente não encontrado'], 404);
            }
            
            Response::json([
                'success' => true,
                'data' => $cliente
            ]);
        }
        
        // Listar com paginação
        $resultado = $service->listarPaginado($busca, $pagina, $limite);
        
        Response::json([
            'success' => true,
            'data' => $resultado['clientes'],
            'pagination' => [
                'current_page' => $resultado['pagina_atual'],
                'total_pages' => $resultado['total_paginas'],
                'total_records' => $resultado['total_registros'],
                'per_page' => $limite
            ]
        ]);
    } catch (Exception $e) {
        Response::json([
            'error' => 'Erro ao buscar clientes',
            'message' => $e->getMessage()
        ], 500);
    }
}

/**
 * POST /api/v1/clientes
 * Cria um novo cliente
 */
function handlePost(ClienteService $service): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        Response::json(['error' => 'Dados inválidos'], 400);
    }
    
    try {
        $resultado = $service->criar($data);
        
        if (!$resultado['success']) {
            Response::json([
                'error' => $resultado['message']
            ], 400);
        }
        
        Response::json([
            'success' => true,
            'message' => $resultado['message'],
            'data' => [
                'id' => $resultado['id']
            ]
        ], 201);
    } catch (Exception $e) {
        Response::json([
            'error' => 'Erro ao criar cliente',
            'message' => $e->getMessage()
        ], 500);
    }
}

/**
 * PUT /api/v1/clientes?id=X
 * Atualiza um cliente existente
 */
function handlePut(ClienteService $service): void
{
    if (!isset($_GET['id'])) {
        Response::json(['error' => 'ID não fornecido'], 400);
    }
    
    $id = (int)$_GET['id'];
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        Response::json(['error' => 'Dados inválidos'], 400);
    }
    
    try {
        $resultado = $service->atualizar($id, $data);
        
        if (!$resultado['success']) {
            Response::json([
                'error' => $resultado['message']
            ], 400);
        }
        
        Response::json([
            'success' => true,
            'message' => $resultado['message']
        ]);
    } catch (Exception $e) {
        Response::json([
            'error' => 'Erro ao atualizar cliente',
            'message' => $e->getMessage()
        ], 500);
    }
}

/**
 * DELETE /api/v1/clientes?id=X
 * Exclui um cliente
 */
function handleDelete(ClienteService $service): void
{
    if (!isset($_GET['id'])) {
        Response::json(['error' => 'ID não fornecido'], 400);
    }
    
    $id = (int)$_GET['id'];
    
    try {
        $resultado = $service->excluir($id);
        
        if (!$resultado['success']) {
            Response::json([
                'error' => $resultado['message']
            ], 400);
        }
        
        Response::json([
            'success' => true,
            'message' => $resultado['message']
        ]);
    } catch (Exception $e) {
        Response::json([
            'error' => 'Erro ao excluir cliente',
            'message' => $e->getMessage()
        ], 500);
    }
}
