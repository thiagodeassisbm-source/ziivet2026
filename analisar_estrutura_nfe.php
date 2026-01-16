<?php
require_once 'config/configuracoes.php';

echo "=== ANÁLISE DE ESTRUTURA PARA NF-e ===\n\n";

// 1. Verificar tabela de produtos
echo "1. TABELA PRODUTOS:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM produtos");
$colunas_produtos = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Colunas disponíveis:\n";
foreach ($colunas_produtos as $col) {
    echo "  - $col\n";
}

// 2. Verificar tabela de vendas
echo "\n2. TABELA VENDAS:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM vendas");
$colunas_vendas = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Colunas disponíveis:\n";
foreach ($colunas_vendas as $col) {
    echo "  - $col\n";
}

// 3. Verificar tabela de clientes
echo "\n3. TABELA CLIENTES:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM clientes");
$colunas_clientes = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Colunas disponíveis:\n";
foreach ($colunas_clientes as $col) {
    echo "  - $col\n";
}

// 4. Verificar tabela de itens da venda
echo "\n4. TABELA ITENS_VENDA:\n";
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM itens_venda");
    $colunas_itens = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Colunas disponíveis:\n";
    foreach ($colunas_itens as $col) {
        echo "  - $col\n";
    }
} catch (Exception $e) {
    echo "Tabela não existe.\n";
}

// 5. Campos fiscais específicos
echo "\n5. VERIFICAÇÃO DE CAMPOS FISCAIS:\n";

$campos_necessarios = [
    'produtos' => ['ncm', 'cfop', 'cst', 'aliquota_icms', 'codigo_barras', 'unidade'],
    'clientes' => ['cpf', 'cnpj', 'ie', 'razao_social', 'endereco', 'numero', 'bairro', 'cidade', 'estado', 'cep'],
    'vendas' => ['numero_nota', 'serie', 'chave_acesso', 'xml_nfe', 'status_nfe']
];

foreach ($campos_necessarios as $tabela => $campos) {
    echo "\n$tabela:\n";
    foreach ($campos as $campo) {
        $campo_lower = strtolower($campo);
        if ($tabela == 'produtos') {
            $existe = in_array($campo_lower, array_map('strtolower', $colunas_produtos));
        } elseif ($tabela == 'clientes') {
            $existe = in_array($campo_lower, array_map('strtolower', $colunas_clientes));
        } else {
            $existe = in_array($campo_lower, array_map('strtolower', $colunas_vendas ?? []));
        }
        $status = $existe ? "✅ EXISTE" : "❌ FALTA";
        echo "  $status - $campo\n";
    }
}
