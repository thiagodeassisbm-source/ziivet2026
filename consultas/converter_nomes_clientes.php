<?php
/**
 * =========================================================================================
 * ZIIPVET - CONVERSOR DE NOMES DE CLIENTES
 * SCRIPT: converter_nomes_clientes.php
 * VERSÃO: 1.0.0
 * FUNÇÃO: Converte nomes de MAIÚSCULAS para Formato Próprio
 * =========================================================================================
 */

require_once '../config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// IMPORTANTE: Definir se é modo VISUALIZAÇÃO ou EXECUÇÃO
$MODO_TESTE = false; // Mudar para false para executar as alterações

/**
 * Função para converter nome para formato correto
 * Mantém preposições em minúsculo
 */
function formatarNomeProprio($nome) {
    // Converter tudo para minúsculo primeiro
    $nome = mb_strtolower($nome, 'UTF-8');
    
    // Palavras que devem permanecer minúsculas (preposições)
    $preposicoes = ['da', 'de', 'do', 'dos', 'das', 'e'];
    
    // Separar em palavras
    $palavras = explode(' ', $nome);
    
    // Processar cada palavra
    $resultado = [];
    foreach ($palavras as $index => $palavra) {
        // Se não for preposição OU for a primeira palavra, capitalizar
        if (!in_array($palavra, $preposicoes) || $index === 0) {
            $resultado[] = mb_convert_case($palavra, MB_CASE_TITLE, 'UTF-8');
        } else {
            $resultado[] = $palavra;
        }
    }
    
    return implode(' ', $resultado);
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Converter Nomes - ZiipVet</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Open Sans', sans-serif; background: #f5f5f5; padding: 40px; }
        
        .container { max-width: 1200px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden; }
        
        .header { background: linear-gradient(135deg, #1c329f 0%, #3258db 100%); color: #fff; padding: 30px; text-align: center; }
        .header h1 { font-size: 28px; margin-bottom: 10px; }
        .header p { opacity: 0.9; }
        
        .alert { padding: 20px; margin: 20px; border-radius: 8px; font-weight: 600; }
        .alert-warning { background: #fff3cd; color: #856404; border: 2px solid #ffc107; }
        .alert-success { background: #d4edda; color: #155724; border: 2px solid #28a745; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 2px solid #17a2b8; }
        
        .mode-selector { padding: 20px; margin: 20px; background: #f8f9fa; border-radius: 8px; text-align: center; }
        .mode-selector h3 { margin-bottom: 15px; color: #333; }
        .btn { padding: 12px 30px; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 16px; margin: 0 10px; text-transform: uppercase; }
        .btn-primary { background: #1c329f; color: #fff; }
        .btn-success { background: #28a745; color: #fff; }
        .btn-danger { background: #dc3545; color: #fff; }
        
        .preview { padding: 20px; }
        .preview h2 { margin-bottom: 20px; color: #333; border-bottom: 2px solid #1c329f; padding-bottom: 10px; }
        
        table { width: 100%; border-collapse: collapse; }
        th { background: #1c329f; color: #fff; padding: 15px; text-align: left; font-size: 13px; text-transform: uppercase; }
        td { padding: 12px 15px; border-bottom: 1px solid #eee; }
        .nome-antes { color: #dc3545; font-weight: 600; }
        .nome-depois { color: #28a745; font-weight: 600; }
        
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; padding: 20px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 20px; border-radius: 8px; text-align: center; }
        .stat-card .number { font-size: 36px; font-weight: 700; margin-bottom: 5px; }
        .stat-card .label { font-size: 14px; opacity: 0.9; text-transform: uppercase; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1><i class="fas fa-text-height"></i> Conversor de Nomes de Clientes</h1>
        <p>Sistema de Padronização Automática - ZiipVet</p>
    </div>

    <?php if ($MODO_TESTE): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> <strong>MODO VISUALIZAÇÃO ATIVO!</strong><br>
            Nenhuma alteração será feita no banco de dados. Revise os nomes abaixo e depois altere <code>$MODO_TESTE = false;</code> no código para executar.
        </div>
    <?php else: ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <strong>MODO EXECUÇÃO ATIVO!</strong><br>
            As alterações serão aplicadas permanentemente ao banco de dados.
        </div>
    <?php endif; ?>

    <?php
    try {
        // Buscar todos os clientes
        $stmt = $pdo->query("SELECT id, nome FROM clientes ORDER BY nome ASC");
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total = count($clientes);
        $alterados = 0;
        $nao_alterados = 0;
        
        echo "<div class='stats'>";
        echo "<div class='stat-card'><div class='number'>{$total}</div><div class='label'>Total de Clientes</div></div>";
        
        if (!$MODO_TESTE) {
            // MODO EXECUÇÃO - Atualizar banco de dados
            $pdo->beginTransaction();
            
            foreach ($clientes as $cliente) {
                $nome_original = $cliente['nome'];
                $nome_formatado = formatarNomeProprio($nome_original);
                
                if ($nome_original !== $nome_formatado) {
                    $stmt_update = $pdo->prepare("UPDATE clientes SET nome = ? WHERE id = ?");
                    $stmt_update->execute([$nome_formatado, $cliente['id']]);
                    $alterados++;
                } else {
                    $nao_alterados++;
                }
            }
            
            $pdo->commit();
            
            echo "<div class='stat-card' style='background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);'><div class='number'>{$alterados}</div><div class='label'>Nomes Alterados</div></div>";
            echo "<div class='stat-card' style='background: linear-gradient(135deg, #ee9ca7 0%, #ffdde1 100%);'><div class='number'>{$nao_alterados}</div><div class='label'>Já Corretos</div></div>";
            echo "</div>";
            
            echo "<div class='alert alert-success'>";
            echo "<i class='fas fa-check-circle'></i> <strong>CONVERSÃO CONCLUÍDA COM SUCESSO!</strong><br>";
            echo "Total de {$alterados} nomes foram atualizados no banco de dados.";
            echo "</div>";
            
        } else {
            // MODO TESTE - Apenas visualizar
            foreach ($clientes as $cliente) {
                $nome_original = $cliente['nome'];
                $nome_formatado = formatarNomeProprio($nome_original);
                
                if ($nome_original !== $nome_formatado) {
                    $alterados++;
                } else {
                    $nao_alterados++;
                }
            }
            
            echo "<div class='stat-card' style='background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);'><div class='number'>{$alterados}</div><div class='label'>Serão Alterados</div></div>";
            echo "<div class='stat-card' style='background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);'><div class='number'>{$nao_alterados}</div><div class='label'>Já Corretos</div></div>";
            echo "</div>";
        }
        
        // Mostrar preview
        echo "<div class='preview'>";
        echo "<h2><i class='fas fa-list'></i> " . ($MODO_TESTE ? "Preview das Alterações" : "Alterações Realizadas") . "</h2>";
        echo "<table>";
        echo "<thead><tr><th style='width: 50px;'>#</th><th>Nome Original</th><th>Nome Formatado</th><th style='width: 100px; text-align: center;'>Status</th></tr></thead>";
        echo "<tbody>";
        
        $count = 0;
        foreach ($clientes as $cliente) {
            $count++;
            $nome_original = $cliente['nome'];
            $nome_formatado = formatarNomeProprio($nome_original);
            
            $mudou = $nome_original !== $nome_formatado;
            $status = $mudou ? "<span style='color: #28a745;'><i class='fas fa-check'></i> Alterado</span>" : "<span style='color: #6c757d;'><i class='fas fa-minus'></i> Sem mudança</span>";
            
            echo "<tr>";
            echo "<td>{$count}</td>";
            echo "<td class='nome-antes'>{$nome_original}</td>";
            echo "<td class='nome-depois'>{$nome_formatado}</td>";
            echo "<td style='text-align: center;'>{$status}</td>";
            echo "</tr>";
        }
        
        echo "</tbody></table>";
        echo "</div>";
        
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>";
        echo "<i class='fas fa-exclamation-circle'></i> <strong>ERRO:</strong> {$e->getMessage()}";
        echo "</div>";
    }
    ?>

    <?php if ($MODO_TESTE): ?>
        <div class="mode-selector">
            <h3>Como Executar as Alterações?</h3>
            <p style="margin-bottom: 20px; color: #666;">
                1. Revise a lista acima cuidadosamente<br>
                2. Edite o arquivo <code>converter_nomes_clientes.php</code><br>
                3. Altere a linha <code>$MODO_TESTE = false;</code><br>
                4. Recarregue esta página
            </p>
            <div style="background: #fff3cd; padding: 15px; border-radius: 6px; margin-top: 15px;">
                <i class="fas fa-info-circle"></i> <strong>DICA:</strong> Faça um backup do banco de dados antes de executar!
            </div>
        </div>
    <?php endif; ?>

</div>

</body>
</html>