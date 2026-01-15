<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Core\Database;
use App\Infrastructure\Repository\PDOUserRepository;
use App\Application\Service\AuthService;
use App\Application\Auth\AuthMiddleware;
use App\Security\RateLimiter;
use App\Utils\Response;

// Configurar Headers para JSON e CORS
header('Content-Type: application/json; charset=utf-8');
AuthMiddleware::aplicarCORS();

// Aceitar apenas requisições POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::json(['error' => 'Método não permitido'], 405);
}

// Receber dados JSON
$json = file_get_contents('php://input');
$dados = json_decode($json, true);

$email = $dados['email'] ?? '';
$senha = $dados['senha'] ?? '';

if (empty($email) || empty($senha)) {
    Response::json(['error' => 'Email e senha são obrigatórios'], 400);
}

// Proteção contra Força Bruta
$ip = RateLimiter::getClientIp();
$rateLimiter = new RateLimiter();

if ($rateLimiter->check($ip)) {
    Response::json(['error' => 'Muitas tentativas de login. Bloqueado por 15 minutos.'], 429);
}

try {
    $db = Database::getInstance();
    $repository = new PDOUserRepository($db);
    $authService = new AuthService($repository);
    
    // Validar usuário e senha usando o Service centralizado
    $user = $authService->login($email, $senha);
    
    // Sucesso: Limpar tentativas
    $rateLimiter->clear($ip);

    // Gerar Payload do JWT com validade de 8 horas (conforme solicitado)
    $payload = [
        'user_id'  => $user->getId(),
        'admin_id' => $user->getIdAdmin(),
        'nome'     => $user->getNome(),
        'email'    => $user->getEmail(),
        'iat'      => time(),
        'exp'      => time() + (8 * 3600) 
    ];
    
    // Gerar Token (HS256 Nativo)
    $token = AuthMiddleware::gerarToken($payload);
    
    // Retornar Sucesso com dados do usuário e token
    Response::json([
        'success' => true,
        'message' => 'Login realizado com sucesso',
        'token'   => $token,
        'user'    => [
            'id'       => $user->getId(),
            'nome'     => $user->getNome(),
            'email'    => $user->getEmail(),
            'id_admin' => $user->getIdAdmin()
        ]
    ]);

} catch (Exception $e) {
    // Falha: Registrar tentativa
    $rateLimiter->registerFailure($ip);
    
    // Captura erros de credenciais inválidas ou acesso desativado do AuthService
    Response::json(['error' => $e->getMessage()], 401);
}
