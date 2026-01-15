<?php
/**
 * TESTE DA API GEMINI - Lista modelos disponíveis
 * Acesse: seusite.com/app/consultas/testar_gemini.php
 */

require_once 'config_ia.php';

echo "<h1>Teste da API Gemini</h1>";

if (!iaConfigurada()) {
    echo "<p style='color:red;'>❌ API Key não configurada!</p>";
    exit;
}

echo "<p>✅ API Key configurada: " . substr(GEMINI_API_KEY, 0, 10) . "...</p>";

// Listar modelos disponíveis
$url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . GEMINI_API_KEY;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true
]);

$resposta = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h2>Modelos Disponíveis (HTTP $httpCode):</h2>";

if ($httpCode == 200) {
    $json = json_decode($resposta, true);
    
    if (isset($json['models'])) {
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>Modelo</th><th>Suporta generateContent</th></tr>";
        
        foreach ($json['models'] as $modelo) {
            $nome = $modelo['name'] ?? 'N/A';
            $metodos = $modelo['supportedGenerationMethods'] ?? [];
            $suporta = in_array('generateContent', $metodos) ? '✅ SIM' : '❌ NÃO';
            
            // Destacar modelos que funcionam
            $style = in_array('generateContent', $metodos) ? "background: #d4edda;" : "";
            
            echo "<tr style='$style'>";
            echo "<td><strong>$nome</strong></td>";
            echo "<td>$suporta</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h3>📋 Use um dos modelos verdes no config_ia.php</h3>";
        echo "<p>Copie o nome completo (ex: models/gemini-1.5-flash) e use assim:</p>";
        echo "<pre>define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/NOME_DO_MODELO:generateContent');</pre>";
        
    } else {
        echo "<pre>" . htmlspecialchars($resposta) . "</pre>";
    }
} else {
    echo "<p style='color:red;'>Erro na API:</p>";
    echo "<pre>" . htmlspecialchars($resposta) . "</pre>";
}
?>