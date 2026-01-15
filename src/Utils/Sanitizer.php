<?php

namespace App\Utils;

/**
 * =========================================================================================
 * ZIIPVET - SECURITY SANITIZER
 * CLASSE: Sanitizer
 * DESCRIÇÃO: Prevenção contra ataques XSS (Cross-site Scripting) e limpeza de dados
 * =========================================================================================
 */
class Sanitizer
{
    /**
     * Limpa dados de entrada para prevenir XSS e remover espaços extras
     * 
     * @param mixed $input Dado a ser limpo (string ou array)
     * @param array $exclude Lista de chaves que não devem ser limpas (ex: 'senha')
     * @return mixed Dado sanitizado
     */
    public static function clean($input, array $exclude = [])
    {
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                // Se a chave estiver na lista de exclusão, mantém o valor original
                if (in_array($key, $exclude)) {
                    continue;
                }
                
                // Recursão para arrays multidimensionais
                $input[$key] = self::clean($value, $exclude);
            }
            return $input;
        }

        if (is_string($input)) {
            // Aplicar trim e converter caracteres especiais em entidades HTML
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }

        return $input;
    }

    /**
     * Versão específica para strings simples
     * 
     * @param string|null $input
     * @return string
     */
    public static function cleanString(?string $input): string
    {
        if ($input === null) return '';
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Reverte a sanitização (útil para campos que precisam mostrar HTML controlado)
     * 
     * @param string $input
     * @return string
     */
    public static function decode(string $input): string
    {
        return htmlspecialchars_decode($input, ENT_QUOTES);
    }
}
