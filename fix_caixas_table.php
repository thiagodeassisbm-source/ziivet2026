<?php
require_once 'config/configuracoes.php';

try {
    echo "Alterando coluna status da tabela caixas...\n";
    // Adicionar ENCERRADO às opções do ENUM
    $pdo->exec("ALTER TABLE caixas MODIFY COLUMN status ENUM('ABERTO', 'FECHADO', 'ENCERRADO') DEFAULT 'ABERTO'");
    echo "Coluna alterada com sucesso!\n";
    
    // Agora tentar atualizar o caixa 43 novamente
    echo "Atualizando caixa 43 para ENCERRADO...\n";
    $pdo->exec("UPDATE caixas SET status = 'ENCERRADO' WHERE id = 43");
    
    // Verificar resultado
    $stmt = $pdo->query("SELECT id, status FROM caixas WHERE id = 43");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Novo status do caixa 43: " . $result['status'] . "\n";
    
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
