<?php
require_once 'config/configuracoes.php';

// Definir timezone para garantir
date_default_timezone_set('America/Sao_Paulo');

echo "<h1>Diagnóstico de Relógio e Data</h1>";

$timestamp = time();
$dataServidor = date("Y-m-d H:i:s P", $timestamp);
$anoServidor = date("Y", $timestamp);

echo "<div style='background:#f4f4f4; padding:20px; border:1px solid #ccc;'>";
echo "<h3>1. Horário do Servidor PHP (XAMPP)</h3>";
echo "Timestamp: <strong>$timestamp</strong><br>";
echo "Data Formatada: <strong style='font-size:18px; color:blue'>$dataServidor</strong><br>";
echo "TimeZone: <strong>" . date_default_timezone_get() . "</strong><br>";
echo "</div>";

// Simular Lógica do Service
echo "<br>";
echo "<div style='background:#ffeeba; padding:20px; border:1px solid #ffdf7e;'>";
echo "<h3>2. Lógica que está sendo aplicada no Service</h3>";

// Copiando a lógica exata do NFCeService
$dataGerada = "";
$modificacao = "";

if ($anoServidor >= 2026) {
    $timestampAdjusted = strtotime("-1 year", $timestamp);
    $dataGerada = date("Y-m-d\TH:i:sP", $timestampAdjusted + 3600);
    $modificacao = "Foi detectado ano 2026. Voltamos 1 ano e somamos 1 hora.";
} else {
    $dataGerada = date("Y-m-d\TH:i:sP", $timestamp); // Sem ajuste se for < 2026
    $modificacao = "Ano parece correto ($anoServidor). Nenhuma subtração de ano aplicada.";
}

echo "Ano Detectado: <b>$anoServidor</b><br>";
echo "Ação tomada: <i>$modificacao</i><br>";
echo "Data Final no XML: <strong style='font-size:18px; color:red'>$dataGerada</strong><br>";
echo "</div>";

echo "<br>";
echo "<h3>3. Conclusão</h3>";
if ($anoServidor == 2025) {
    echo "<p style='color:green; font-weight:bold;'>O SERVIDOR ESTÁ EM 2025! A minha correção (-1 ano) estava errada pois jogava para 2024.</p>";
} elseif ($anoServidor >= 2026) {
    echo "<p style='color:orange; font-weight:bold;'>O servidor realmente está em $anoServidor. A correção de ano é necessária.</p>";
} else {
    echo "<p>Ano inesperado.</p>";
}
