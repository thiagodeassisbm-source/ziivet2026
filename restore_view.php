<?php
require_once 'config/configuracoes.php';

try {
    echo "Restoring lancamentos VIEW...\n";

    // 1. Drop existing table/view
    $pdo->query("DROP VIEW IF EXISTS lancamentos");
    $pdo->query("DROP TABLE IF EXISTS lancamentos");

    // 2. Create View
    $sql = "
    CREATE VIEW lancamentos AS
    SELECT 
        c.id,
        c.id_admin,
        CASE 
            WHEN c.natureza = 'Receita' THEN 'ENTRADA' 
            WHEN c.natureza = 'Despesa' THEN 'SAIDA'
            ELSE c.natureza 
        END as tipo,
        c.categoria,
        c.descricao,
        c.documento,
        c.vencimento as data_vencimento,
        c.data_pagamento as data_compensacao,
        c.data_cadastro,
        c.valor_total as valor,
        COALESCE(c.valor_parcela, c.valor_total) as valor_parcela,
        c.status_baixa, -- Maps to status? lancamentos.php uses 'status'? Yes: traduzirStatus(\$s['status'])
        c.status_baixa as status,
        c.id_forma_pgto as id_forma_pagamento,
        f.nome_forma as forma_pagamento,
        c.id_entidade,
        c.entidade_tipo,
        c.id_caixa_referencia,
        c.id_conta_origem as id_conta_financeira,
        -- Fields that might be missing in contas but needed by PHP:
        NULL as id_venda, 
        1 as total_parcelas,
        1 as parcela_atual,
        
        -- Virtual column for Human Name
        CASE 
            WHEN c.entidade_tipo = 'cliente' THEN (SELECT nome FROM clientes WHERE id = c.id_entidade LIMIT 1)
            WHEN c.entidade_tipo = 'fornecedor' THEN (SELECT nome_fantasia FROM fornecedores WHERE id = c.id_entidade LIMIT 1)
            WHEN c.entidade_tipo = 'usuario' THEN (SELECT nome FROM usuarios WHERE id = c.id_entidade LIMIT 1)
            ELSE NULL
        END as fornecedor_cliente

    FROM contas c
    LEFT JOIN formas_pagamento f ON c.id_forma_pgto = f.id
    ";

    $pdo->query($sql);
    echo "VIEW 'lancamentos' created successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    // Check if column error
    if (strpos($e->getMessage(), 'Unknown column') !== false) {
       echo "Columns in contas seem different. Please check 'inspect_contas_cols.php' output.\n";
    }
}
