<?php
require_once 'config/configuracoes.php';

echo "Checking Payment Configurations...\n";
$stmt = $pdo->query("SELECT id, nome_forma, tipo, configuracoes FROM formas_pagamento");
$formas = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($formas as $f) {
    echo "ID: " . $f['id'] . " | Nome: " . $f['nome_forma'] . " | Tipo: " . $f['tipo'] . "\n";
    if (!empty($f['configuracoes'])) {
        echo "Config: " . $f['configuracoes'] . "\n";
    }
    echo "--------------------------\n";
}
