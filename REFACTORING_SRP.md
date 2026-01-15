# Refatoração - Princípio de Responsabilidade Única (SRP)

## Arquivo: `consultas/realizar_consulta.php`

### ❌ Problema Identificado
O arquivo `realizar_consulta.php` estava violando o **Princípio de Responsabilidade Única (SRP)** ao misturar:
- Lógica de processamento de requisições AJAX
- Lógica de carregamento de dados para a view
- Renderização HTML

### ✅ Solução Implementada

#### 1. **Nova API REST** (`api/api_consulta.php`)
Criado endpoint dedicado para processar requisições AJAX:

**Endpoints disponíveis:**
- `?ajax_dados_animal=1&id_paciente=X` - Retorna dados completos do animal
- `?ajax_historico=1&id_paciente=X` - Retorna histórico de vacinas e lembretes
- `?ajax_peso=1&id_paciente=X` - Retorna histórico de peso (HTML)

**Melhorias:**
- ✅ Uso da classe `Response::json()` para respostas padronizadas
- ✅ Uso do Singleton `Database::getInstance()`
- ✅ Validação de parâmetros
- ✅ Códigos HTTP apropriados (400, 404, 500)
- ✅ Tratamento robusto de exceções

#### 2. **View Refatorada** (`consultas/realizar_consulta.php`)
Mantém apenas:
- Carregamento de dados necessários para renderização inicial
- Estrutura HTML
- Inclusão de módulos

**Responsabilidade única:** Renderizar a interface de atendimento

#### 3. **API Client JavaScript** (`consultas/js/consulta-api-client.js`)
Biblioteca centralizada para chamadas AJAX:

```javascript
// Exemplo de uso:
ConsultaAPI.getDadosAnimal(idPaciente, function(data) {
    console.log(data);
});

ConsultaAPI.getHistoricoVacinas(idPaciente, function(res) {
    console.log(res.historico);
});

ConsultaAPI.getHistoricoPeso(idPaciente, function(html) {
    $('#container').html(html);
});
```

### 📊 Benefícios da Refatoração

1. **Separação de Responsabilidades**
   - API: Processa dados e retorna JSON
   - View: Renderiza interface
   - Client JS: Gerencia comunicação

2. **Manutenibilidade**
   - Mudanças na API não afetam a view
   - Fácil adicionar novos endpoints
   - Código mais limpo e organizado

3. **Testabilidade**
   - API pode ser testada independentemente
   - Endpoints podem ser chamados via Postman/cURL
   - Facilita criação de testes automatizados

4. **Preparação para Futuro**
   - Base sólida para migração React
   - API REST já estruturada
   - Padrão moderno e escalável

### 🔄 Migração de Código Existente

**Antes:**
```php
// Dentro de realizar_consulta.php
if (isset($_GET['ajax_dados_animal'])) {
    echo json_encode($dados);
    exit;
}
```

**Depois:**
```javascript
// No JavaScript
ConsultaAPI.getDadosAnimal(id, function(dados) {
    // Usar dados
});
```

### 📝 Próximos Passos Recomendados

1. Atualizar arquivos que ainda fazem chamadas diretas:
   - `consultas/atendimento.php`
   - `consultas/vacinas.php`
   - `consultas/modelo_documentos.php`

2. Criar mais endpoints na API conforme necessário

3. Implementar autenticação JWT para a API (preparação para React)

---
**Versão:** 11.0.0  
**Data:** 2026-01-14  
**Padrão:** Clean Architecture + SRP
