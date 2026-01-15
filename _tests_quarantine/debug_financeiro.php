<?php
// debug_financeiro.php
// ATIVAÇÃO DE ERROS PARA DEBUG
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/configuracoes.php'; // Garanta que o caminho está correto

// ID da conta "Fundo Fixo" (baseado no seu SQL é o 3)
$id_conta_analisar = 3; 

echo "<div style='font-family: Arial; padding: 20px;'>";
echo "<h1>🕵️ Debug Financeiro - Fundo Fixo (ID: $id_conta_analisar)</h1>";

try {
    // 1. BUSCAR DADOS DA CONTA (SALDO INICIAL)
    $stmt = $pdo->prepare("SELECT * FROM contas_financeiras WHERE id = ?");
    $stmt->execute([$id_conta_analisar]);
    $conta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$conta) { die("<h2 style='color:red'>Erro: Conta ID $id_conta_analisar não encontrada!</h2>"); }

    $saldoInicialDB = (float)$conta['saldo_inicial'];
    $situacao = $conta['situacao_saldo'];
    
    // Ajusta sinal se estiver marcado como Negativo no cadastro
    $saldoInicialReal = ($situacao === 'Negativo') ? -$saldoInicialDB : $saldoInicialDB;

    echo "<div style='background:#f4f4f4; padding:15px; border-radius:5px; margin-bottom:20px;'>";
    echo "<h3>1. Configuração no Banco de Dados</h3>";
    echo "<strong>Nome:</strong> " . $conta['nome_conta'] . "<br>";
    echo "<strong>Saldo Inicial Cadastrado:</strong> R$ " . number_format($saldoInicialDB, 2, ',', '.') . "<br>";
    echo "<strong>Situação:</strong> " . $situacao . "<br>";
    echo "<strong>Valor considerado no cálculo:</strong> <span style='color:blue; font-weight:bold;'>R$ " . number_format($saldoInicialReal, 2, ',', '.') . "</span>";
    echo "</div>";

    // 2. BUSCAR RECEITAS (ENTRADAS)
    $stmtRec = $pdo->prepare("SELECT id, descricao, valor_total FROM contas WHERE id_conta_origem = ? AND natureza = 'Receita' AND status_baixa = 'PAGO'");
    $stmtRec->execute([$id_conta_analisar]);
    $receitas = $stmtRec->fetchAll(PDO::FETCH_ASSOC);
    $totalReceitas = 0;

    echo "<h3>2. Entradas (Receitas vinculadas)</h3>";
    if (count($receitas) == 0) echo "<i>Nenhuma entrada encontrada.</i><br>";
    foreach($receitas as $r) {
        echo "➕ ID {$r['id']} - {$r['descricao']}: R$ " . number_format($r['valor_total'], 2, ',', '.') . "<br>";
        $totalReceitas += $r['valor_total'];
    }
    echo "<strong>Total Entradas: R$ " . number_format($totalReceitas, 2, ',', '.') . "</strong><br><hr>";

    // 3. BUSCAR DESPESAS (SAÍDAS)
    $stmtDesp = $pdo->prepare("SELECT id, descricao, valor_total FROM contas WHERE id_conta_origem = ? AND natureza = 'Despesa' AND status_baixa = 'PAGO'");
    $stmtDesp->execute([$id_conta_analisar]);
    $despesas = $stmtDesp->fetchAll(PDO::FETCH_ASSOC);
    $totalDespesas = 0;

    echo "<h3>3. Saídas (Despesas vinculadas)</h3>";
    if (count($despesas) == 0) echo "<i>Nenhuma saída encontrada.</i><br>";
    foreach($despesas as $d) {
        echo "➖ ID {$d['id']} - {$d['descricao']}: R$ " . number_format($d['valor_total'], 2, ',', '.') . "<br>";
        $totalDespesas += $d['valor_total'];
    }
    echo "<strong>Total Saídas: R$ " . number_format($totalDespesas, 2, ',', '.') . "</strong><br><hr>";

    // 4. CÁLCULO FINAL
    $saldoFinal = $saldoInicialReal + $totalReceitas - $totalDespesas;

    echo "<h1>🧮 A Prova Real</h1>";
    echo "Saldo Inicial: <b style='color:blue'> " . number_format($saldoInicialReal, 2, ',', '.') . "</b><br>";
    echo "+ Entradas: <b style='color:green'> " . number_format($totalReceitas, 2, ',', '.') . "</b><br>";
    echo "- Saídas: <b style='color:red'> " . number_format($totalDespesas, 2, ',', '.') . "</b><br>";
    echo "------------------------------------<br>";
    
    $corFinal = ($saldoFinal < 0) ? 'red' : 'black';
    echo "RESULTADO: <h2 style='color:$corFinal'>R$ " . number_format($saldoFinal, 2, ',', '.') . "</h2>";

    if ($saldoFinal == 0 && $totalDespesas > 0) {
        echo "<div style='background:#ffdddd; border:1px solid red; padding:15px; color:red;'>";
        echo "<h3>⚠️ POR QUE ESTÁ ZERADO E NÃO NEGATIVO?</h3>";
        echo "O sistema zerou porque você tem um <b>Saldo Inicial de R$ " . number_format($saldoInicialReal, 2, ',', '.') . "</b> cadastrado no banco.<br>";
        echo "A conta foi: <b>" . number_format($saldoInicialReal, 2, ',', '.') . " (O que tinha) - " . number_format($totalDespesas, 2, ',', '.') . " (O que saiu) = 0,00</b>.<br><br>";
        echo "<b>COMO CONSERTAR:</b><br>";
        echo "Você precisa alterar o Saldo Inicial dessa conta para <b>0,00</b> no banco de dados.";
        echo "</div>";
        
        // Botão para corrigir automaticamente
        echo "<br><form method='POST'><button type='submit' name='corrigir_saldo' style='padding:15px; cursor:pointer; background:red; color:white; font-weight:bold; border:none; border-radius:5px;'>CLIQUE AQUI PARA ZERAR O SALDO INICIAL E CORRIGIR AGORA</button></form>";
    }

} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}

// ROTINA DE CORREÇÃO AUTOMÁTICA
if (isset($_POST['corrigir_saldo'])) {
    $pdo->query("UPDATE contas_financeiras SET saldo_inicial = 0.00 WHERE id = $id_conta_analisar");
    echo "<script>alert('Saldo Inicial corrigido para 0! A página será recarregada.'); window.location.href='debug_financeiro.php';</script>";
}

echo "</div>";
?>