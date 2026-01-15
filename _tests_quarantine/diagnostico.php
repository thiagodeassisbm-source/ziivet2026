# 🔧 GUIA DE DIAGNÓSTICO - Erro ao Importar XML

## ❌ Erro Atual
```
Unexpected token '<', "<!DOCTYPE "... is not valid JSON
```

## 🎯 Causa do Problema
O PHP está retornando HTML/texto em vez de JSON puro. Isso acontece quando:
1. Há saída de texto antes do JSON (echo, print, espaços)
2. Erros PHP são exibidos na tela
3. Warnings ou notices são mostrados
4. Include/require de arquivos que têm saída

## ✅ SOLUÇÃO IMPLEMENTADA

### Arquivo: `processar_xml_corrigido.php`

**Principais correções:**
1. ✅ `ob_start()` no início para capturar qualquer saída
2. ✅ `error_reporting(0)` e `ini_set('display_errors', 0)` 
3. ✅ Função `retornarJSON()` que limpa buffers antes de enviar resposta
4. ✅ Tratamento de exceções completo
5. ✅ Validação de estrutura XML (múltiplos formatos)
6. ✅ Mensagens de erro detalhadas mas em JSON

## 📋 CHECKLIST DE INSTALAÇÃO

### 1. Substituir o arquivo atual
```bash
# Fazer backup do arquivo antigo
mv processar_xml.php processar_xml_old.php

# Copiar o novo arquivo
cp processar_xml_corrigido.php processar_xml.php
```

### 2. Verificar permissões
```bash
chmod 644 processar_xml.php
chown www-data:www-data processar_xml.php
```

### 3. Testar diretamente
Acesse no navegador:
```
https://www.lepetboutique.com.br/processar_xml.php
```

**Resposta esperada:**
```json
{
    "status": "error",
    "message": "Método inválido. Use POST."
}
```

## 🐛 SE O ERRO PERSISTIR

### Teste 1: Criar arquivo de diagnóstico

Crie um arquivo `teste_xml.php`:

```php
<?php
// Limpar tudo
while (ob_get_level()) ob_end_clean();

// Desabilitar erros
error_reporting(0);
ini_set('display_errors', 0);

// Header JSON
header('Content-Type: application/json');

// Testar resposta
echo json_encode([
    'status' => 'success',
    'message' => 'Teste OK',
    'servidor' => [
        'php_version' => PHP_VERSION,
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size')
    ]
]);
?>
```

Acesse `https://www.lepetboutique.com.br/teste_xml.php`

**Se retornar JSON = problema está no processar_xml.php**
**Se retornar HTML = problema de configuração do servidor**

### Teste 2: Verificar se há includes problemáticos

O erro pode estar vindo de arquivos incluídos. Verifique:

1. ❌ Remova temporariamente qualquer `require` ou `include`
2. ❌ Não inclua `auth.php` ou `config/configuracoes.php` 
3. ✅ Deixe apenas conexão direta ao banco

### Teste 3: Verificar .htaccess

Às vezes o `.htaccess` interfere. Adicione:

```apache
<Files "processar_xml.php">
    php_value display_errors Off
    php_flag display_startup_errors Off
</Files>
```

### Teste 4: Verificar logs de erro

```bash
# Ver erros do PHP
tail -f /var/log/apache2/error.log

# Ou logs do domínio específico
tail -f /home/u315410518/domains/lepetboutique.com.br/logs/error.log
```

## 🔍 EXEMPLO DE XML VÁLIDO

Estruturas aceitas:

**Formato 1 (mais comum):**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc>
  <NFe>
    <infNFe Id="NFe...">
      <ide>...</ide>
      <emit>...</emit>
      <det>...</det>
      <total>...</total>
    </infNFe>
  </NFe>
</nfeProc>
```

**Formato 2 (alternativo):**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<NFe>
  <infNFe Id="NFe...">
    <ide>...</ide>
    <emit>...</emit>
    <det>...</det>
    <total>...</total>
  </infNFe>
</NFe>
```

## 📞 SUPORTE ADICIONAL

Se o erro persistir, forneça:

1. ✅ Versão do PHP (`php -v`)
2. ✅ Conteúdo dos logs de erro
3. ✅ Screenshot do erro no console do navegador (F12)
4. ✅ Resultado do teste_xml.php
5. ✅ Primeiras linhas do XML que está tentando importar

## 🎉 VALIDAÇÃO FINAL

Após a correção, teste:

1. ✅ Importar XML com fornecedor cadastrado
2. ✅ Importar XML com fornecedor novo
3. ✅ Importar XML com produtos já cadastrados
4. ✅ Importar XML com produtos novos
5. ✅ Verificar se as parcelas são carregadas corretamente

---

**Arquivo criado em:** <?= date('Y-m-d H:i:s') ?>

**Autor:** Claude (Anthropic)