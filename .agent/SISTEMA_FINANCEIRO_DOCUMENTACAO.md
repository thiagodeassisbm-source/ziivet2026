# DOCUMENTAÇÃO TÉCNICA - SISTEMA FINANCEIRO ZIIPVET

## 📋 VISÃO GERAL

O Sistema Financeiro do ZIIPVET gerencia o fluxo de caixa de uma clínica veterinária, controlando vendas, recebimentos, despesas e movimentações entre contas financeiras. O sistema é baseado em uma arquitetura de **caixas diários** com controle rigoroso de entrada/saída de valores.

---

## 🏗️ ESTRUTURA DO BANCO DE DADOS

### Tabela: `caixas`
Armazena informações sobre cada caixa aberto/fechado.

**Estrutura:**
- `id`: ID único do caixa
- `id_admin`: ID do administrador/empresa
- `id_usuario`: ID do usuário que abriu o caixa
- `status`: ENUM('ABERTO', 'FECHADO', 'ENCERRADO')
- `data_abertura`: Data de abertura
- `hora_abertura`: Hora de abertura
- `data_fechamento`: Data de fechamento (quando status = FECHADO ou ENCERRADO)
- `valor_inicial`: Valor de suprimento inicial (troco)
- `valor_fechamento`: Valor total ao fechar o caixa
- `id_conta_origem`: Conta financeira de onde veio o suprimento inicial
- `id_conta_fechamento`: Conta financeira para onde vai o dinheiro ao encerrar

**Status do Caixa:**
1. **ABERTO**: Caixa em operação, aceita vendas e movimentações
2. **FECHADO**: Caixa fechado pelo operador, não aceita mais vendas (status intermediário)
3. **ENCERRADO**: Caixa finalizado administrativamente, valores transferidos para conta destino

---

### Tabela: `contas` (Lançamentos Financeiros)
Registra TODAS as movimentações financeiras do sistema.

**Estrutura Principal:**
- `id`: ID único do lançamento
- `id_admin`: ID do administrador/empresa
- `natureza`: ENUM('Receita', 'Despesa')
- `categoria`: Categoria do lançamento (ex: 'VENDA', 'SUPRIMENTO', 'SANGRIA', 'DESPESA', 'TRANSFERENCIA', 'FECHAMENTO_CAIXA')
- `descricao`: Descrição textual
- `forma_pagamento_detalhe`: Forma de pagamento (ex: 'Dinheiro', 'Cartão de Crédito')
- `id_conta_origem`: ID da conta financeira de origem
- `valor_total`: Valor total da transação
- `valor_parcela`: Valor da parcela (se parcelado)
- `qtd_parcelas`: Quantidade de parcelas
- `status_baixa`: ENUM('PAGO', 'PENDENTE', 'CANCELADO')
- `data_pagamento`: Data do pagamento efetivo
- `vencimento`: Data de vencimento
- `id_caixa_referencia`: ID do caixa relacionado (se aplicável)
- `id_venda`: ID da venda relacionada (se aplicável)
- `observacoes`: Observações adicionais

**Categorias Especiais (NÃO contam como vendas):**
- `SUPRIMENTO`: Adição de dinheiro ao caixa para troco
- `ABERTURA_CAIXA`: Registro de abertura do caixa
- `Caixa`: Categoria genérica de movimentação de caixa
- `FECHAMENTO_CAIXA`: Transferência do valor do caixa fechado para conta destino

---

### VIEW: `lancamentos`
É uma **VIEW** (não uma tabela real), portanto **NÃO ACEITA INSERT**.
- Utilizada apenas para LEITURA
- Agrega informações das tabelas `contas`, `vendas`, `contas_financeiras`
- Para inserir dados, usar a tabela `contas` diretamente

---

### Tabela: `contas_financeiras`
Armazena contas bancárias, cartões e caixas da empresa.

**Estrutura:**
- `id`: ID único da conta
- `id_admin`: ID do administrador/empresa
- `nome_conta`: Nome identificador (ex: 'Banco Itaú', 'Caixa Geral')
- `tipo_conta`: Tipo (ex: 'Conta Corrente', 'Poupança', 'Cartão')
- `categoria`: Categoria (ex: 'Bancos', 'Cartões', 'Espécie')
- `saldo_inicial`: Saldo atual da conta
- `data_saldo`: Data da última atualização do saldo
- `situacao_saldo`: 'Positivo' ou 'Negativo'
- `status`: 'Ativo' ou 'Inativo'

**Observação Importante sobre Saldo:**
- O `saldo_inicial` é atualizado APENAS por operações de abertura/fechamento de caixa
- NÃO somar manualmente vendas/lançamentos ao saldo (já está refletido)

---

## 🔄 FLUXO OPERACIONAL COMPLETO

### 1️⃣ ABERTURA DE CAIXA

**Processo:**
1. Usuário acessa `movimentacao_caixa.php` e clica em "Abrir Novo Caixa"
2. Sistema solicita:
   - Valor inicial (suprimento/troco)
   - Conta de origem do suprimento
3. Sistema registra na tabela `caixas`:
   - `status = 'ABERTO'`
   - `valor_inicial = R$ X,XX`
   - `id_conta_origem = Y`

**SQL Exemplo:**
```sql
INSERT INTO caixas (id_admin, id_usuario, status, data_abertura, hora_abertura, valor_inicial, id_conta_origem)
VALUES (1, 5, 'ABERTO', '2026-01-15', '08:00:00', 200.00, 1);
```

---

### 2️⃣ VENDAS NO CAIXA

**Processo:**
1. Venda é registrada na tabela `vendas`
2. Sistema cria lançamentos na tabela `contas` com:
   - `natureza = 'Receita'`
   - `categoria = NULL` (ou categoria de venda específica, MAS NÃO 'SUPRIMENTO', etc.)
   - `forma_pagamento_detalhe = 'Dinheiro'/'Cartão de Crédito'`, etc.
   - `id_caixa_referencia = [ID do caixa]`
   - `status_baixa = 'PAGO'`

**Formas de Pagamento:**
- **Dinheiro**: Entra no caixa imediatamente
- **Cartão de Crédito**: 
  - Se configurado como "antecipado", entra na conta em até 2 dias úteis
  - Pode ter taxas descontadas
- **Cartão de Débito**: Similar ao dinheiro
- **PIX**: Entra imediatamente na conta bancária

**SQL Exemplo de Venda:**
```sql
INSERT INTO contas (id_admin, natureza, descricao, forma_pagamento_detalhe, id_conta_origem, 
                    valor_total, valor_parcela, qtd_parcelas, status_baixa, id_caixa_referencia, 
                    id_venda, vencimento, data_pagamento, data_cadastro)
VALUES (1, 'Receita', 'Venda PDV #55 - Abadia da Costa', 'Dinheiro', 1, 
        71.80, 71.80, 1, 'PAGO', 43, 55, CURDATE(), NOW(), NOW());
```

---

### 3️⃣ MOVIMENTAÇÕES NO CAIXA (DURANTE O DIA)

#### A) SUPRIMENTO (Adicionar Dinheiro ao Caixa)
**Quando usar:** Quando precisar adicionar mais troco ao caixa.

**Registro:**
```sql
INSERT INTO contas (id_admin, natureza, categoria, descricao, forma_pagamento_detalhe,
                    id_conta_origem, valor_total, status_baixa, id_caixa_referencia)
VALUES (1, 'Receita', 'SUPRIMENTO', 'Suprimento de caixa', 'Dinheiro',
        1, 100.00, 'PAGO', 43);
```

**⚠️ IMPORTANTE:** Categoria `SUPRIMENTO` **NÃO É CONTADA** como receita de vendas!

---

#### B) SANGRIA (Retirar Dinheiro do Caixa)
**Quando usar:** Quando retirar dinheiro do caixa para depósito ou segurança.

**Registro:**
```sql
INSERT INTO contas (id_admin, natureza, categoria, descricao, forma_pagamento_detalhe,
                    id_conta_origem, valor_total, status_baixa, id_caixa_referencia)
VALUES (1, 'Despesa', 'SANGRIA', 'Sangria de caixa - Depósito', 'Dinheiro',
        1, 500.00, 'PAGO', 43);
```

---

#### C) DESPESA (Pagar Despesa com Dinheiro do Caixa)
**Quando usar:** Pagar conta, fornecedor, etc. com dinheiro do caixa.

**Registro:**
```sql
INSERT INTO contas (id_admin, natureza, categoria, descricao, forma_pagamento_detalhe,
                    id_conta_origem, valor_total, status_baixa, id_caixa_referencia)
VALUES (1, 'Despesa', 'FORNECEDOR', 'Pagamento Fornecedor XYZ', 'Dinheiro',
        1, 150.00, 'PAGO', 43);
```

---

### 4️⃣ FECHAMENTO DO CAIXA (2 ETAPAS)

#### ETAPA 1: FECHAR CAIXA (Operador)
**Quando:** Fim do expediente, operador quer fechar o caixa.

**Processo:**
1. Usuário clica em "Revisar e Encerrar"
2. Se `status = 'ABERTO'`, sistema exibe popup: "Deseja fechar o caixa?"
3. Ao confirmar, sistema executa:

**SQL:**
```sql
UPDATE caixas SET status = 'FECHADO' WHERE id = 43;
```

**Resultado:** Caixa não aceita mais vendas, mas ainda não transferiu valores.

---

#### ETAPA 2: ENCERRAR CAIXA (Administrativo)
**Quando:** Após conferência, administrador encerra definitivamente.

**Processo:**
1. Se `status = 'FECHADO'`, sistema exibe modal de "Encerramento"
2. Solicita:
   - Conta de destino (para onde vai o dinheiro)
   - Data/hora de fechamento
   - Comentários
3. Sistema calcula `valor_fechamento` = (suprimento inicial + vendas em dinheiro - sangrias - despesas)
4. Atualiza `caixas`:

**SQL:**
```sql
UPDATE caixas SET 
    status = 'ENCERRADO',
    data_fechamento = '2026-01-15 20:26:00',
    valor_fechamento = 271.80,
    id_conta_fechamento = 2
WHERE id = 43;
```

5. Atualiza saldo da conta de origem (retorna suprimento):

**SQL:**
```sql
UPDATE contas_financeiras 
SET saldo_inicial = saldo_inicial + 200.00,  -- retorna suprimento
    data_saldo = '2026-01-15 20:26:00'
WHERE id = 1;  -- conta de origem
```

6. Atualiza saldo da conta de destino (recebe valor do caixa):

**SQL:**
```sql
UPDATE contas_financeiras 
SET saldo_inicial = saldo_inicial + 271.80,  -- recebe valor fechamento
    data_saldo = '2026-01-15 20:26:00'
WHERE id = 2;  -- conta de destino
```

7. Registra lançamento de FECHAMENTO_CAIXA (para histórico):

**SQL:**
```sql
INSERT INTO contas (id_admin, natureza, categoria, descricao, forma_pagamento_detalhe,
                    id_conta_origem, valor_total, valor_parcela, qtd_parcelas, 
                    status_baixa, id_caixa_referencia, observacoes, 
                    vencimento, data_pagamento, data_cadastro)
VALUES (1, 'Receita', 'FECHAMENTO_CAIXA', 'Fechamento do caixa #43 - Retorno de valores', 
        'Dinheiro', 2, 271.80, 271.80, 1, 'PAGO', 43, 'Caixa encerrado', 
        CURDATE(), NOW(), NOW());
```

**⚠️ IMPORTANTE:** Categoria `FECHAMENTO_CAIXA` **NÃO É CONTADA** como receita de vendas!

---

## 📊 LISTAGEM DE LANÇAMENTOS (lancamentos.php)

### Filtros Aplicados para Totais e Gráficos

**Lançamentos EXCLUÍDOS dos totais de vendas:**
```sql
WHERE id_admin = 1
AND (categoria IS NULL OR categoria NOT IN ('SUPRIMENTO', 'ABERTURA_CAIXA', 'Caixa', 'FECHAMENTO_CAIXA'))
```

**Motivo:** Essas categorias são movimentações internas, NÃO são vendas reais.

### Totais Calculados

**1. Receitas (Vendas Reais):**
```sql
SUM(CASE WHEN tipo = 'ENTRADA' AND status = 'PAGO' THEN valor ELSE 0 END)
```

**2. Despesas:**
```sql
SUM(CASE WHEN tipo = 'SAIDA' AND status = 'PAGO' THEN valor ELSE 0 END)
```

**3. Resultado:**
```sql
Receitas - Despesas
```

### Agrupamentos

**Por Forma de Pagamento:**
- Dinheiro
- Cartão de Crédito (Master Card, Visa, etc.)
- Cartão de Débito
- PIX
- Outros

**Por Status:**
- PAGO (já recebido)
- PENDENTE (a receber)
- CANCELADO

---

## 🔍 DETALHAMENTO DE MOVIMENTAÇÕES DO CAIXA (detalhes_movimentacao.php)

### Totais do Caixa

**Query SQL para Resumo:**
```sql
SELECT 
    -- Vendas em Dinheiro (EXCLUINDO SUPRIMENTO)
    SUM(CASE 
        WHEN forma_pagamento = 'Dinheiro' 
        AND tipo = 'ENTRADA' 
        AND categoria NOT IN ('SUPRIMENTO', 'Caixa')
        THEN valor_liquido 
        ELSE 0 
    END) as total_dinheiro,
    
    -- Suprimentos (mostrados separadamente)
    SUM(CASE 
        WHEN categoria IN ('SUPRIMENTO', 'Caixa')
        THEN valor_liquido 
        ELSE 0 
    END) as total_suprimentos,
    
    -- Vendas em Cartão de Crédito
    SUM(CASE 
        WHEN forma_pagamento LIKE '%Crédito%' 
        AND categoria NOT IN ('SUPRIMENTO', 'Caixa')
        THEN valor_liquido 
        ELSE 0 
    END) as total_credito,
    
    -- Vendas em Cartão de Débito
    SUM(CASE 
        WHEN forma_pagamento LIKE '%Débito%' 
        AND categoria NOT IN ('SUPRIMENTO', 'Caixa')
        THEN valor_liquido 
        ELSE 0 
    END) as total_debito

FROM lancamentos
WHERE id_caixa = 43
```

**Cálculo do Total em Caixa (Dinheiro Físico):**
```php
$total_em_caixa = $valor_inicial (suprimento)
                  + $vendas_dinheiro
                  + $suprimentos_adicionais
                  - $sangrias
                  - $despesas_em_dinheiro;
```

---

## ⚙️ CONFIGURAÇÕES IMPORTANTES

### Recebimento Antecipado de Cartão de Crédito

**Configuração:**
- Se configurado como "antecipado" no cadastro de formas de pagamento
- Valores de cartão de crédito disponíveis na conta bancária em até **2 dias úteis**
- Podem ter taxas administrativas da operadora

**Banner Informativo:**
Exibido em `lancamentos.php` e `listar_contas_financeiras.php`:
> "De acordo com a configuração no formulário de 'Recebimentos', se for selecionado recebimento antecipado, os valores de cartão de crédito estarão disponíveis na conta bancária em até 2 dias úteis."

---

## 🎯 REGRAS DE NEGÓCIO CRÍTICAS

### ✅ FAZER:
1. Sempre usar tabela `contas` para inserir novos lançamentos (não a VIEW `lancamentos`)
2. Excluir categorias especiais (`SUPRIMENTO`, `ABERTURA_CAIXA`, `Caixa`, `FECHAMENTO_CAIXA`) dos totais de vendas
3. Seguir fluxo de 2 etapas: ABERTO → FECHADO → ENCERRADO
4. Atualizar saldos das contas financeiras ao encerrar caixa
5. Registrar `id_caixa_referencia` em todos os lançamentos do caixa

### ❌ NÃO FAZER:
1. Não tentar fazer INSERT na VIEW `lancamentos`
2. Não contar SUPRIMENTO/FECHAMENTO_CAIXA como receita de vendas
3. Não pular etapas do fechamento (direto de ABERTO para ENCERRADO sem validação)
4. Não somar manualmente vendas ao saldo das contas (já refletido no fechamento)

---

## 📁 ARQUIVOS PRINCIPAIS

- `movimentacao_caixa.php` - Listagem e abertura de caixas
- `detalhes_movimentacao.php` - Detalhes e fechamento de um caixa específico
- `lancamentos.php` - Listagem geral de lançamentos financeiros
- `listar_contas_financeiras.php` - Listagem de contas bancárias/cartões
- `contas_financeiras.php` - Cadastro de nova conta

---

## 🗄️ ESTRUTURA DE DADOS RESUMIDA

```
caixas (tabela)
├── status: ABERTO → FECHADO → ENCERRADO
├── valor_inicial (suprimento)
└── valor_fechamento (total ao encerrar)

contas (tabela) - LANÇAMENTOS REAIS
├── natureza: Receita | Despesa
├── categoria: VENDA | SUPRIMENTO | SANGRIA | DESPESA | FECHAMENTO_CAIXA
├── forma_pagamento_detalhe: Dinheiro | Cartão | PIX
├── status_baixa: PAGO | PENDENTE | CANCELADO
└── id_caixa_referencia (link com caixa)

lancamentos (VIEW) - SOMENTE LEITURA
└── Agrega: contas + vendas + contas_financeiras

contas_financeiras (tabela)
├── nome_conta
├── tipo_conta
├── saldo_inicial (atualizado no fechamento de caixa)
└── status: Ativo | Inativo
```

---

## 📝 EXEMPLO COMPLETO DE FLUXO

### Cenário: Caixa do dia 15/01/2026

1. **08:00** - Abertura
   - Suprimento: R$ 200,00 (da Conta "Caixa Geral")
   - Status: ABERTO

2. **Durante o Dia**
   - Venda #54 (Cartão Crédito): R$ 19,90
   - Venda #55 (Dinheiro): R$ 71,80
   - Venda #53 (Cartão Débito): R$ 35,90
   - Suprimento adicional: R$ 50,00

3. **18:00** - Fechamento pelo Operador
   - Clica "Revisar e Encerrar"
   - Confirma fechamento
   - Status: FECHADO

4. **20:26** - Encerramento Administrativo
   - Total em dinheiro: R$ 271,80 (R$ 200 + R$ 71,80)
   - Destino: Conta "Banco Itaú"
   - Sistema:
     - Atualiza status: ENCERRADO
     - Devolve R$ 200,00 para "Caixa Geral"
     - Transfere R$ 271,80 para "Banco Itaú"
     - Registra lançamento de FECHAMENTO_CAIXA

5. **Resultado nos Relatórios**
   - Receitas (Vendas): R$ 127,60 (R$ 71,80 + R$ 19,90 + R$ 35,90)
   - Suprimentos: R$ 200,00 (inicial) + R$ 50,00 = R$ 250,00 (NÃO contam como venda)
   - Fechamento: R$ 271,80 (NÃO conta como venda)

---

## 🔐 SEGURANÇA E VALIDAÇÕES

- CSRF Token obrigatório em todas as requisições POST
- Validação de status antes de permitir operações
- Verificação de permissões por `id_admin`
- Logs de auditoria em todas as operações críticas

---

**Versão:** 1.0.0  
**Data:** 16/01/2026  
**Sistema:** ZIIPVET - Plataforma v3.0
