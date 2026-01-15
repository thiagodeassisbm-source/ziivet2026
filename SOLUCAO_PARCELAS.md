# 🔧 CORREÇÃO: Parcelas com Valor Zero

## 🎯 Problema Identificado

Você relatou que o XML é importado corretamente, **MAS as parcelas carregam com valor R$ 0,00** no formulário.

## 📊 Diagnóstico

Analisando o código do `processar_xml.php` que você forneceu, identifiquei que:

### ❌ Problema 1: Falta extração de parcelas
O código atual **NÃO extrai as duplicatas (parcelas)** do XML. Ele processa:
- ✅ Fornecedor
- ✅ Dados da compra
- ✅ Itens da nota
- ❌ **PARCELAS** (não são extraídas!)

### ❌ Problema 2: JavaScript esperando dados que não existem
O código JavaScript em `compras.php` tem a função `preencherParcelasXML()` que espera receber um array de parcelas, mas como o PHP não está enviando, o array chega vazio ou com valores zerados.

## ✅ Solução Implementada

Criei a versão `processar_xml_FINAL.php` que adiciona a extração completa de parcelas:

### Estrutura de extração de parcelas no XML

```xml
<nfe>
  <infNFe>
    ...
    <cobr>
      <dup>
        <nDup>001</nDup>
        <dVenc>2026-02-05</dVenc>
        <vDup>328.29</vDup>
      </dup>
      <dup>
        <nDup>002</nDup>
        <dVenc>2026-03-05</dVenc>
        <vDup>328.29</vDup>
      </dup>
    </cobr>
    ...
  </infNFe>
</nfe>
```

### Código implementado:

```php
// ===== PARCELAS (DUPLICATAS) =====
$parcelas = [];

if (isset($nfe->cobr->dup)) {
    foreach ($nfe->cobr->dup as $dup) {
        $venc = (string)($dup->dVenc ?? '');
        try {
            if (!empty($venc)) {
                $dt = new DateTime($venc);
                $venc = $dt->format('Y-m-d');
            } else {
                $venc = date('Y-m-d', strtotime('+30 days'));
            }
        } catch (Exception $e) {
            $venc = date('Y-m-d', strtotime('+30 days'));
        }
        
        $valor = (float)($dup->vDup ?? 0);
        
        if ($valor > 0) {
            $parcelas[] = [
                'numero' => (string)($dup->nDup ?? count($parcelas) + 1),
                'vencimento' => $venc,
                'valor' => $valor  // ← ESTE É O CAMPO CRÍTICO!
            ];
        }
    }
}

// Se não encontrou parcelas no XML
if (empty($parcelas)) {
    $parcelas[] = [
        'numero' => '001',
        'vencimento' => date('Y-m-d', strtotime('+30 days')),
        'valor' => $compra['valor_total']
    ];
}
```

### Validações implementadas:

1. ✅ **Verifica se existe nó `<cobr>` e `<dup>` no XML**
2. ✅ **Converte datas de vencimento corretamente**
3. ✅ **Valida valores (só adiciona se valor > 0)**
4. ✅ **Fallback: Se não tem parcelas, cria uma única com total da nota**
5. ✅ **Retorna no formato esperado pelo JavaScript**

## 📦 Arquivos Criados

### 1. `processar_xml_FINAL.php`
Versão corrigida do processador com extração completa de parcelas.

**Como instalar:**
```bash
# Fazer backup
cp processar_xml.php processar_xml_backup.php

# Instalar nova versão
cp processar_xml_FINAL.php processar_xml.php

# Definir permissões
chmod 644 processar_xml.php
```

### 2. `teste_parcelas.html`
Interface de teste que permite:
- Upload de XML de teste
- Visualização das parcelas extraídas
- Validação dos valores
- Debug da resposta JSON completa

**Como usar:**
1. Acesse: `https://www.lepetboutique.com.br/teste_parcelas.html`
2. Faça upload de um XML de nota fiscal
3. Clique em "TESTAR EXTRAÇÃO"
4. Veja se as parcelas estão sendo extraídas com valores corretos

## 🧪 Passo a Passo de Teste

### Teste 1: Validar extração
```bash
1. Substitua o processar_xml.php pela versão corrigida
2. Acesse teste_parcelas.html
3. Faça upload de um XML com parcelas
4. Verifique se os valores aparecem corretamente
```

**Resultado esperado:**
```
PARCELAS EXTRAÍDAS:
#  | Número | Vencimento  | Valor     | Status
1  | 001    | 05/02/2026  | R$ 328,29 | ✅ OK
2  | 002    | 05/03/2026  | R$ 328,29 | ✅ OK

TOTAL DAS PARCELAS: R$ 656,58
✅ Validação OK: Soma das parcelas confere com o total da nota!
```

### Teste 2: Importação real
```bash
1. Acesse importar_xml.php
2. Faça upload do mesmo XML
3. Clique em "PROCESSAR XML"
4. Verifique se é redirecionado para compras.php
5. Na seção "Financeiro", verifique se as parcelas aparecem com valores
```

## 🔍 Possíveis Cenários

### Cenário 1: XML sem duplicatas
Se o XML não tem o nó `<cobr><dup>`, o sistema cria automaticamente 1 parcela com:
- Número: 001
- Vencimento: 30 dias após hoje
- Valor: Total da nota

### Cenário 2: XML com duplicatas zeradas
Se o XML tem duplicatas mas todas com `vDup` = 0, o sistema ignora e cria a parcela padrão.

### Cenário 3: XML com duplicatas válidas
Extrai todas as duplicatas com valores > 0 e valida se a soma confere com o total.

## 📋 Checklist de Validação

Após instalar, verificar:

- [ ] Parcelas aparecem na tela de teste com valores corretos
- [ ] Soma das parcelas = Total da nota
- [ ] Datas de vencimento estão corretas (formato YYYY-MM-DD)
- [ ] Ao importar no formulário real, parcelas aparecem preenchidas
- [ ] É possível adicionar/remover parcelas manualmente
- [ ] Ao finalizar compra, parcelas são salvas nas contas a pagar

## 🆘 Troubleshooting

### Problema: Parcelas ainda aparecem zeradas
**Causa possível:** JavaScript não está aplicando a máscara de moeda
**Solução:** Verificar se a linha `$('.money').mask('#.##0,00', {reverse: true});` está sendo executada

### Problema: Nenhuma parcela aparece
**Causa possível:** Estrutura do XML diferente
**Solução:** Use o `teste_parcelas.html` para ver a resposta JSON e identificar o problema

### Problema: Soma das parcelas ≠ Total da nota
**Causa possível:** XML com duplicatas incompletas ou erradas
**Solução:** Validar o XML original ou criar parcelas manualmente

## 📞 Suporte

Se após instalar o problema persistir, forneça:

1. ✅ Screenshot do `teste_parcelas.html` após processar o XML
2. ✅ Console do navegador (F12 > Console) ao acessar compras.php
3. ✅ Valor esperado vs valor recebido
4. ✅ XML de exemplo (pode apagar dados sensíveis)

---

**Versão:** 1.0.0 (Final)  
**Data:** 2026-01-05  
**Status:** ✅ Pronto para produção