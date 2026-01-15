<?php

namespace App\Utils;

/**
 * =========================================================================================
 * ZIIPVET - ENVIRONMENT LOADER
 * CLASSE: Env
 * DESCRIÇÃO: Carrega e acessa variáveis de ambiente do arquivo .env
 * =========================================================================================
 */
class Env
{
    /**
     * Cache das variáveis carregadas
     */
    private static array $variables = [];

    /**
     * Carrega o arquivo .env
     * 
     * @param string $path Caminho completo do arquivo .env
     * @return bool
     */
    public static function load(string $path): bool
    {
        if (!file_exists($path)) {
            return false;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Ignorar comentários
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Dividir em chave e valor
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);

                // Remover aspas se existirem
                $value = trim($value, '"\'');

                // Armazenar no $_ENV, putenv e cache local
                $_ENV[$key] = $value;
                putenv("$key=$value");
                self::$variables[$key] = $value;
            }
        }

        return true;
    }

    /**
     * Obtém uma variável de ambiente
     * 
     * @param string $key Chave da variável
     * @param mixed $default Valor padrão caso não exista
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        // 1. Verificar cache local
        if (isset(self::$variables[$key])) {
            return self::$variables[$key];
        }

        // 2. Verificar $_ENV
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        // 3. Verificar getenv
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $default;
    }
}
