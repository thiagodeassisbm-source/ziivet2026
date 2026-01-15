<?php
require_once 'config/configuracoes.php';

try {
    echo "Updating 'contas' table structure...\n";

    // 1. Add id_venda column if not exists
    try {
        $pdo->query("SELECT id_venda FROM contas LIMIT 1");
        echo "Column 'id_venda' already exists.\n";
    } catch (Exception $e) {
        $pdo->query("ALTER TABLE contas ADD COLUMN id_venda INT NULL AFTER id_caixa_referencia");
        echo "Column 'id_venda' added.\n";
    }

    echo "Updating 'lancamentos' VIEW definition...\n";
    
    // 2. Drop existing View
    $pdo->query("DROP VIEW IF EXISTS lancamentos");

    // 3. Re-Create View with id_venda linked
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
        c.status_baixa,
        c.status_baixa as status,
        c.id_forma_pgto as id_forma_pagamento,
        f.nome_forma as forma_pagamento,
        c.id_entidade,
        c.entidade_tipo,
        c.id_caixa_referencia,
        c.id_conta_origem as id_conta_financeira,
        -- Use real id_venda from contas
        c.id_venda, 
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
    echo "VIEW 'lancamentos' updated successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
