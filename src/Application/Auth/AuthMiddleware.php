<?php

namespace App\Application\Auth;

use App\Utils\Response;

/**
 * Middleware de autenticação para proteger endpoints da API
 */
class AuthMiddleware
{
    /**
     * Verifica se o usuário está autenticado
     * 
     * Atualmente usa sessão PHP. Futuramente será migrado para JWT.
     * 
     * @return void Encerra o script com 401 se não autenticado
     */
    public static function verificar(): void
    {
        // Iniciar sessão se não estiver iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // TODO: Implementar validação de Bearer Token (JWT)
        // Exemplo futuro:
        // $token = self::extrairTokenDoHeader();
        // if (!self::validarJWT($token)) {
        //     Response::json(['error' => 'Token inválido ou expirado'], 401);
        // }

        // Verificação atual: Sessão PHP
        $usuarioAutenticado = self::verificarSessao();

        if (!$usuarioAutenticado) {
            Response::json([
                'error' => 'Não autorizado',
                'message' => 'Você precisa estar autenticado para acessar este recurso.'
            ], 401);
        }
    }

    /**
     * Verifica se existe sessão ativa
     * 
     * @return bool
     */
    private static function verificarSessao(): bool
    {
        // Verificar múltiplas possibilidades de ID de usuário na sessão
        return isset($_SESSION['usuario_id']) 
            || isset($_SESSION['id_usuario'])
            || isset($_SESSION['id'])
            || isset($_SESSION['user_id'])
            || isset($_SESSION['id_admin']);
    }

    /**
     * Obtém o ID do usuário autenticado
     * 
     * @return int|null
     */
    public static function getUsuarioId(): ?int
    {
        if (isset($_SESSION['usuario_id'])) {
            return (int)$_SESSION['usuario_id'];
        }
        
        if (isset($_SESSION['id_usuario'])) {
            return (int)$_SESSION['id_usuario'];
        }
        
        if (isset($_SESSION['id'])) {
            return (int)$_SESSION['id'];
        }
        
        if (isset($_SESSION['user_id'])) {
            return (int)$_SESSION['user_id'];
        }

        return null;
    }

    /**
     * Obtém o ID do admin autenticado
     * 
     * @return int|null
     */
    public static function getAdminId(): ?int
    {
        return isset($_SESSION['id_admin']) ? (int)$_SESSION['id_admin'] : null;
    }

    /**
     * Verifica se o usuário tem uma permissão específica
     * 
     * @param string $permissao Nome da permissão
     * @return bool
     */
    public static function temPermissao(string $permissao): bool
    {
        // TODO: Implementar verificação de permissões baseada em roles/ACL
        // Por enquanto, retorna true se estiver autenticado
        return self::verificarSessao();
    }

    /**
     * Extrai token Bearer do header Authorization
     * 
     * @return string|null
     */
    private static function extrairTokenDoHeader(): ?string
    {
        // TODO: Implementar extração de JWT do header
        // Exemplo:
        // $headers = getallheaders();
        // $authHeader = $headers['Authorization'] ?? '';
        // 
        // if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        //     return $matches[1];
        // }
        
        return null;
    }

    /**
     * Valida um token JWT
     * 
     * @param string|null $token
     * @return bool
     */
    private static function validarJWT(?string $token): bool
    {
        // TODO: Implementar validação de JWT
        // Usar biblioteca como firebase/php-jwt
        // 
        // try {
        //     $decoded = JWT::decode($token, $secretKey, ['HS256']);
        //     $_SESSION['usuario_id'] = $decoded->user_id;
        //     $_SESSION['id_admin'] = $decoded->admin_id;
        //     return true;
        // } catch (Exception $e) {
        //     return false;
        // }
        
        return false;
    }

    /**
     * Gera um token JWT para um usuário
     * 
     * @param int $usuarioId ID do usuário
     * @param int $adminId ID do admin
     * @param int $expiracaoHoras Horas até expiração (padrão: 24h)
     * @return string
     */
    public static function gerarToken(int $usuarioId, int $adminId, int $expiracaoHoras = 24): string
    {
        // TODO: Implementar geração de JWT
        // Exemplo:
        // $payload = [
        //     'user_id' => $usuarioId,
        //     'admin_id' => $adminId,
        //     'iat' => time(),
        //     'exp' => time() + ($expiracaoHoras * 3600)
        // ];
        // 
        // return JWT::encode($payload, $secretKey, 'HS256');
        
        return '';
    }

    /**
     * Verifica se a requisição é de uma origem permitida (CORS)
     * 
     * @param array $origensPermitidas Lista de origens permitidas
     * @return bool
     */
    public static function verificarOrigem(array $origensPermitidas = []): bool
    {
        $origem = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (empty($origensPermitidas)) {
            // Se não especificado, permite qualquer origem (desenvolvimento)
            return true;
        }

        return in_array($origem, $origensPermitidas);
    }

    /**
     * Aplica headers CORS
     * 
     * @param array $origensPermitidas Lista de origens permitidas
     * @return void
     */
    public static function aplicarCORS(array $origensPermitidas = ['*']): void
    {
        $origem = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array('*', $origensPermitidas) || in_array($origem, $origensPermitidas)) {
            header('Access-Control-Allow-Origin: ' . ($origem ?: '*'));
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            header('Access-Control-Allow-Credentials: true');
        }
    }
}
