<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/configuracoes.php';
require_once '../vendor/autoload.php';

use App\Services\NFCeService;

echo "<h1>Debug Emissão NFC-e</h1>";

try {
    $vendaId = 55; // ID fixo que estamos testando
    echo "Iniciando serviço para Venda ID: $vendaId...<br>";
    
    $service = new NFCeService($pdo);
    
    echo "Tentando emitir...<br>";
    $resultado = $service->emitir($vendaId);
    
    echo "<h3>Resultado:</h3>";
    echo "<pre>";
    print_r($resultado);
    echo "</pre>";

} catch (Throwable $e) {
    echo "<h2 style='color:red'>ERRO FATAL:</h2>";
    echo "<b>Mensagem:</b> " . $e->getMessage() . "<br>";
    echo "<b>Arquivo:</b> " . $e->getFile() . " na linha " . $e->getLine() . "<br>";
    echo "<h3>Stack Trace:</h3>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
