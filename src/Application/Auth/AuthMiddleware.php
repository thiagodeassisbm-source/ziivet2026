<?php

namespace App\Application\Auth;

use App\Utils\Response;

/**
 * Middleware de autenticação para proteger endpoints da API
 */
class AuthMiddleware
{
    /**
     * Armazena os dados do usuário autenticado no contexto da requisição
     */
    private static ?array $currentUser = null;

    /**
     * Verifica se o usuário está autenticado
     * 
     * Suporta autenticação via Bearer Token (JWT) e Sessão PHP (Legacy).
     * 
     * @return void Encerra o script com 401 se não autenticado
     */
    public static function verificar(): void
    {
        // 1. Tentar Autenticação via JWT (Headers)
        $token = self::extrairTokenDoHeader();
        if ($token) {
            $payload = self::validarToken($token);
            if ($payload) {
                self::$currentUser = $payload;

                // Sincronizar dados do token com a sessão para compatibilidade legada
                if (session_status() === PHP_SESSION_NONE) session_start();
                if (isset($payload['user_id'])) $_SESSION['usuario_id'] = $payload['user_id'];
                if (isset($payload['admin_id'])) $_SESSION['id_admin'] = $payload['admin_id'];
                return; // Autenticado via JWT
            }
            
            // Se enviou token mas é inválido, bloqueia
            Response::json(['error' => 'Token inválido ou expirado'], 401);
        }

        // 2. Tentar Autenticação via Sessão (Legado/Browser)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (self::verificarSessao()) {
            // Popular currentUser a partir da sessão para consistência
            self::$currentUser = [
                'user_id' => self::getUsuarioId(),
                'admin_id' => self::getAdminId()
            ];
            return;
        }

        Response::json([
            'error' => 'Não autorizado',
            'message' => 'Você precisa estar autenticado para acessar este recurso.'
        ], 401);
    }

    /**
     * Retorna os dados do usuário autenticado
     * 
     * @return array|null
     */
    public static function getCurrentUser(): ?array
    {
        return self::$currentUser;
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
     * Chave secreta para assinatura do JWT
     * Em produção, deve ser uma variável de ambiente complexa.
     */
    private const JWT_SECRET = 'ziipvet-secret-key-2026-v1-!@#$';

    /**
     * Gera um token JWT para um usuário
     * 
     * @param array $payload Dados a serem incluídos no token
     * @return string
     */
    public static function gerarToken(array $payload): string
    {
        // Header padrão para JWT HS256
        $header = json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256'
        ]);

        // Codificar Header e Payload
        $headerEncoded = self::base64UrlEncode($header);
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        // Gerar Assinatura
        $signature = self::sign("$headerEncoded.$payloadEncoded");
        $signatureEncoded = self::base64UrlEncode($signature);

        // Retornar Token Completo
        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }

    /**
     * Valida um token JWT
     * 
     * @param string|null $token Token JWT string
     * @return array|bool Payload decodificado se válido, false caso contrário
     */
    public static function validarToken(?string $token): bool|array
    {
        if (!$token) return false;

        $partes = explode('.', $token);
        if (count($partes) !== 3) return false;

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $partes;

        // Verificar Assinatura
        $assinaturaEsperada = self::sign("$headerEncoded.$payloadEncoded");
        
        if (!hash_equals($assinaturaEsperada, self::base64UrlDecode($signatureEncoded))) {
            return false;
        }

        // Decodificar Payload
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);
        if (!$payload) return false;

        // Verificar Expiração (exp)
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }

        return $payload;
    }

    /**
     * Assina uma string usando HMAC SHA256
     * 
     * @param string $data Dados para assinar
     * @return string Assinatura binária
     */
    private static function sign(string $data): string
    {
        return hash_hmac('sha256', $data, self::JWT_SECRET, true);
    }

    /**
     * Codifica para Base64 compatível com URL (padrão JWT)
     * 
     * @param string $data
     * @return string
     */
    private static function base64UrlEncode(string $data): string
    {
        $base64 = base64_encode($data);
        $urlSafe = str_replace(['+', '/', '='], ['-', '_', ''], $base64);
        return $urlSafe;
    }

    /**
     * Decodifica Base64 compatível com URL
     * 
     * @param string $data
     * @return string
     */
    private static function base64UrlDecode(string $data): string
    {
        $padding = strlen($data) % 4;
        if ($padding) {
            $data .= str_repeat('=', 4 - $padding);
        }
        $base64 = str_replace(['-', '_'], ['+', '/'], $data);
        return base64_decode($base64);
    }

    /**
     * Extrai token Bearer do header Authorization
     * 
     * @return string|null
     */
    public static function extrairTokenDoHeader(): ?string
    {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        $authHeader = $headers['authorization'] ?? '';

        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
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
