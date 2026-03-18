<?php
/**
 * Autoload mínimo para quando `composer install` não foi executado.
 *
 * Este arquivo substitui o `vendor/autoload.php` do Composer apenas o necessário
 * para carregar namespaces `App\` a partir de `src/`.
 *
 * Observação:
 * - Dependências externas (ex.: bibliotecas via composer) não serão carregadas aqui.
 * - Isso evita o erro fatal "failed opening required vendor/autoload.php".
 */

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = __DIR__ . '/../src/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
