<?php
require_once 'config/configuracoes.php';

try {
    echo "Updating 'contas' table structure...\n";
    
    // 1. Add forma_pagamento_detalhe column
    try {
        $pdo->query("SELECT forma_pagamento_detalhe FROM contas LIMIT 1");
        echo "Column 'forma_pagamento_detalhe' already exists.\n";
    } catch (Exception $e) {
        $pdo->query("ALTER TABLE contas ADD COLUMN forma_pagamento_detalhe VARCHAR(100) NULL AFTER id_forma_pgto");
        echo "Column 'forma_pagamento_detalhe' added.\n";
    }

    echo "Updating 'lancamentos' VIEW definition...\n";
    
    // 2. Re-Create View
    $pdo->query("DROP VIEW IF EXISTS lancamentos");
    
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
        -- Priority: Custom Detail -> Joined Name -> 'Outros'
        COALESCE(c.forma_pagamento_detalhe, f.nome_forma, 'OUTROS') as forma_pagamento,
        c.id_entidade,
        c.entidade_tipo,
        c.id_caixa_referencia,
        c.id_conta_origem as id_conta_financeira,
        c.id_venda, 
        1 as total_parcelas,
        1 as parcela_atual,
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
    
    // 3. Data Repair: Fix Suprimentos (Dinheiro value 200.00)
    // Suprimento usually has categoria 'Caixa' or 'Suprimento' and id_forma_pgto NULL
    $stmt = $pdo->query("UPDATE contas SET id_forma_pgto = 1, forma_pagamento_detalhe = 'Dinheiro' 
                         WHERE descricao LIKE '%SUPRIMENTO%' AND id_forma_pgto IS NULL");
    echo "Fixed Suprimentos: " . $stmt->rowCount() . " rows.\n";

    // 4. Data Repair: Fix the specific sale that went to Outros (id 303 in debug output?)
    // Debug output showed id 303 for 143.60.
    // Set it to "Cartão de Crédito Master"
    $stmt = $pdo->query("UPDATE contas SET forma_pagamento_detalhe = 'Cartão de Crédito Master' 
                         WHERE valor_total = 143.60 AND (forma_pagamento_detalhe IS NULL OR forma_pagamento_detalhe = '')");
    echo "Fixed Sale 143.60: " . $stmt->rowCount() . " rows.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
