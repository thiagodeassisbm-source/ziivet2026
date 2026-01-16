<?php
require_once 'config/configuracoes.php';

// Atualizar caixa 43 para ENCERRADO
$stmt = $pdo->prepare("UPDATE caixas SET status = 'ENCERRADO' WHERE id = 43");
$result = $stmt->execute();

if ($result) {
    echo "Caixa 43 atualizado para ENCERRADO com sucesso!";
} else {
    echo "Erro ao atualizar.";
}
