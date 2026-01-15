<?php

namespace App\Utils;

/**
 * =========================================================================================
 * ZIIPVET - CSRF PROTECTION
 * CLASSE: Csrf
 * DESCRIÇÃO: Proteção contra ataques Cross-Site Request Forgery
 * =========================================================================================
 */
class Csrf
{
    /**
     * Nome da chave na sessão
     */
    private const SESSION_KEY = 'csrf_token';

    /**
     * Tamanho do token em bytes (32 bytes = 64 caracteres hex)
     */
    private const TOKEN_LENGTH = 32;

    /**
     * Gera um token CSRF e armazena na sessão
     *
     * @return string Token gerado
     */
    public static function generate(): string
    {
        // Iniciar sessão se não estiver iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Se já existe um token válido, retorná-lo
        if (isset($_SESSION[self::SESSION_KEY]) && !empty($_SESSION[self::SESSION_KEY])) {
            return $_SESSION[self::SESSION_KEY];
        }

        // Gerar novo token aleatório
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        
        // Armazenar na sessão
        $_SESSION[self::SESSION_KEY] = $token;

        return $token;
    }

    /**
     * Valida um token CSRF
     *
     * @param string|null $token Token a ser validado
     * @return bool True se válido, False caso contrário
     */
    public static function validate(?string $token): bool
    {
        // Iniciar sessão se não estiver iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Verificar se o token foi fornecido
        if (empty($token)) {
            return false;
        }

        // Verificar se existe token na sessão
        if (!isset($_SESSION[self::SESSION_KEY]) || empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        // Comparação segura contra timing attacks
        return hash_equals($_SESSION[self::SESSION_KEY], $token);
    }

    /**
     * Retorna um input hidden HTML com o token CSRF
     *
     * @return string HTML do input hidden
     */
    public static function getInput(): string
    {
        $token = self::generate();
        return sprintf(
            '<input type="hidden" name="csrf_token" value="%s">',
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Retorna apenas o token (útil para AJAX)
     *
     * @return string Token CSRF
     */
    public static function getToken(): string
    {
        return self::generate();
    }

    /**
     * Regenera o token CSRF (útil após login/logout)
     *
     * @return string Novo token gerado
     */
    public static function regenerate(): string
    {
        // Iniciar sessão se não estiver iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Remover token antigo
        unset($_SESSION[self::SESSION_KEY]);

        // Gerar novo token
        return self::generate();
    }

    /**
     * Valida token de requisição POST
     * Lança exceção se inválido
     *
     * @throws \Exception Se o token for inválido
     * @return bool True se válido
     */
    public static function validatePost(): bool
    {
        $token = $_POST['csrf_token'] ?? null;

        if (!self::validate($token)) {
            throw new \Exception('Token CSRF inválido. Possível ataque CSRF detectado.');
        }

        return true;
    }

    /**
     * Valida token de requisição GET (menos comum, mas útil)
     *
     * @throws \Exception Se o token for inválido
     * @return bool True se válido
     */
    public static function validateGet(): bool
    {
        $token = $_GET['csrf_token'] ?? null;

        if (!self::validate($token)) {
            throw new \Exception('Token CSRF inválido. Possível ataque CSRF detectado.');
        }

        return true;
    }

    /**
     * Retorna meta tag para uso em AJAX
     *
     * @return string HTML da meta tag
     */
    public static function getMetaTag(): string
    {
        $token = self::generate();
        return sprintf(
            '<meta name="csrf-token" content="%s">',
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Retorna JavaScript para incluir token em requisições AJAX
     *
     * @return string Código JavaScript
     */
    public static function getAjaxScript(): string
    {
        $token = self::generate();
        return <<<HTML
<script>
// CSRF Token para requisições AJAX
const csrfToken = '{$token}';

// Adicionar token a todas as requisições fetch
const originalFetch = window.fetch;
window.fetch = function(url, options = {}) {
    options.headers = options.headers || {};
    options.headers['X-CSRF-Token'] = csrfToken;
    return originalFetch(url, options);
};

// Adicionar token a todas as requisições jQuery AJAX
if (typeof jQuery !== 'undefined') {
    jQuery.ajaxSetup({
        headers: {
            'X-CSRF-Token': csrfToken
        }
    });
}
</script>
HTML;
    }

    /**
     * Valida token do header HTTP (para AJAX)
     *
     * @return bool True se válido
     */
    public static function validateHeader(): bool
    {
        $headers = getallheaders();
        $token = $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? null;

        return self::validate($token);
    }

    /**
     * Middleware para validar CSRF em requisições POST
     * Uso: Csrf::middleware();
     *
     * @throws \Exception Se o token for inválido
     * @return void
     */
    public static function middleware(): void
    {
        // Apenas validar em requisições POST, PUT, DELETE
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            // Tentar validar do POST primeiro
            $token = $_POST['csrf_token'] ?? null;
            
            // Se não encontrou no POST, tentar no header (AJAX)
            if (!$token) {
                $headers = getallheaders();
                $token = $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? null;
            }

            if (!self::validate($token)) {
                http_response_code(403);
                die('Erro: Token CSRF inválido. Possível ataque CSRF detectado.');
            }
        }
    }
}
