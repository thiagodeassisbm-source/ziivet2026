# API REST v1 - Documentação

## 🌐 Endpoint: `/api/v1/clientes`

### Visão Geral
API RESTful para gerenciamento de clientes do sistema ZiipVet.

**Base URL:** `http://localhost:8000/api/v1/clientes`  
**Formato:** JSON  
**Autenticação:** Não implementada (TODO: JWT)

---

## 📋 Endpoints Disponíveis

### 1. Listar Clientes
**Método:** `GET`  
**URL:** `/api/v1/clientes`

**Query Parameters:**
| Parâmetro | Tipo | Obrigatório | Padrão | Descrição |
|-----------|------|-------------|--------|-----------|
| `busca` | string | Não | "" | Termo de busca (nome, CPF, email) |
| `pagina` | integer | Não | 1 | Número da página |
| `limite` | integer | Não | 20 | Itens por página (máx: 100) |

**Exemplo de Requisição:**
```bash
GET /api/v1/clientes?busca=silva&pagina=1&limite=10
```

**Resposta de Sucesso (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "nome": "João Silva",
      "cpf_cnpj": "123.456.789-00",
      "telefone": "(11) 98765-4321",
      "email": "joao@email.com",
      "lista_animais": "1:Rex|2:Mia"
    }
  ],
  "pagination": {
    "current_page": 1,
    "total_pages": 5,
    "total_records": 48,
    "per_page": 10
  }
}
```

---

### 2. Buscar Cliente por ID
**Método:** `GET`  
**URL:** `/api/v1/clientes?id={id}`

**Exemplo de Requisição:**
```bash
GET /api/v1/clientes?id=1
```

**Resposta de Sucesso (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "nome": "João Silva",
    "cpf_cnpj": "123.456.789-00",
    "telefone": "(11) 98765-4321",
    "email": "joao@email.com",
    "endereco": "Rua das Flores",
    "numero": "123",
    "bairro": "Centro",
    "cidade": "São Paulo",
    "estado": "SP",
    "cep": "01234-567"
  }
}
```

**Resposta de Erro (404):**
```json
{
  "error": "Cliente não encontrado"
}
```

---

### 3. Criar Cliente
**Método:** `POST`  
**URL:** `/api/v1/clientes`  
**Content-Type:** `application/json`

**Body:**
```json
{
  "nome": "Maria Santos",
  "cpf_cnpj": "987.654.321-00",
  "telefone": "(11) 91234-5678",
  "email": "maria@email.com",
  "endereco": "Av. Paulista",
  "numero": "1000",
  "bairro": "Bela Vista",
  "cidade": "São Paulo",
  "estado": "SP",
  "cep": "01310-100"
}
```

**Resposta de Sucesso (201):**
```json
{
  "success": true,
  "message": "Cliente criado com sucesso!",
  "data": {
    "id": 42
  }
}
```

**Resposta de Erro (400):**
```json
{
  "error": "Nome é obrigatório."
}
```

---

### 4. Atualizar Cliente
**Método:** `PUT`  
**URL:** `/api/v1/clientes?id={id}`  
**Content-Type:** `application/json`

**Body:**
```json
{
  "nome": "Maria Santos Silva",
  "telefone": "(11) 99999-9999",
  "email": "maria.silva@email.com"
}
```

**Resposta de Sucesso (200):**
```json
{
  "success": true,
  "message": "Cliente atualizado com sucesso!"
}
```

---

### 5. Excluir Cliente
**Método:** `DELETE`  
**URL:** `/api/v1/clientes?id={id}`

**Exemplo de Requisição:**
```bash
DELETE /api/v1/clientes?id=42
```

**Resposta de Sucesso (200):**
```json
{
  "success": true,
  "message": "Cliente excluído com sucesso!"
}
```

**Resposta de Erro (400):**
```json
{
  "error": "Erro: existem animais ou histórico clínico vinculados."
}
```

---

## 🔒 Headers CORS

A API está configurada com CORS aberto para desenvolvimento:

```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization
```

**⚠️ IMPORTANTE:** Em produção, restringir o `Access-Control-Allow-Origin` para domínios específicos.

---

## 📝 Códigos de Status HTTP

| Código | Significado | Quando Usar |
|--------|-------------|-------------|
| 200 | OK | Requisição bem-sucedida |
| 201 | Created | Recurso criado com sucesso |
| 400 | Bad Request | Dados inválidos ou faltando |
| 404 | Not Found | Recurso não encontrado |
| 405 | Method Not Allowed | Método HTTP não suportado |
| 500 | Internal Server Error | Erro no servidor |

---

## 🧪 Testando a API

### Usando cURL

**Listar clientes:**
```bash
curl -X GET "http://localhost:8000/api/v1/clientes?pagina=1&limite=5"
```

**Buscar cliente:**
```bash
curl -X GET "http://localhost:8000/api/v1/clientes?id=1"
```

**Criar cliente:**
```bash
curl -X POST "http://localhost:8000/api/v1/clientes" \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "Teste API",
    "cpf_cnpj": "111.222.333-44",
    "telefone": "(11) 99999-9999",
    "email": "teste@api.com"
  }'
```

**Atualizar cliente:**
```bash
curl -X PUT "http://localhost:8000/api/v1/clientes?id=1" \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "Nome Atualizado"
  }'
```

**Excluir cliente:**
```bash
curl -X DELETE "http://localhost:8000/api/v1/clientes?id=42"
```

---

### Usando Postman

1. **Importar Collection:**
   - Criar nova collection "ZiipVet API v1"
   - Adicionar requests para cada endpoint

2. **Configurar Environment:**
   ```json
   {
     "base_url": "http://localhost:8000/api/v1"
   }
   ```

3. **Testar Endpoints:**
   - GET: `{{base_url}}/clientes`
   - POST: `{{base_url}}/clientes`
   - PUT: `{{base_url}}/clientes?id=1`
   - DELETE: `{{base_url}}/clientes?id=1`

---

### Usando JavaScript (Fetch API)

```javascript
// Listar clientes
fetch('http://localhost:8000/api/v1/clientes?pagina=1&limite=10')
  .then(response => response.json())
  .then(data => console.log(data));

// Criar cliente
fetch('http://localhost:8000/api/v1/clientes', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    nome: 'Cliente Novo',
    email: 'novo@email.com'
  })
})
  .then(response => response.json())
  .then(data => console.log(data));

// Excluir cliente
fetch('http://localhost:8000/api/v1/clientes?id=42', {
  method: 'DELETE'
})
  .then(response => response.json())
  .then(data => console.log(data));
```

---

## 🏗️ Arquitetura

```
Request → API Controller → Service Layer → Repository → Database
                ↓
            Response (JSON)
```

**Camadas:**
1. **Controller** (`api/v1/clientes/index.php`): Roteamento HTTP
2. **Service** (`ClienteService`): Lógica de negócio
3. **Repository** (`ClienteRepository`): Acesso a dados
4. **Response** (`Response::json()`): Padronização de respostas

---

## 🔐 Autenticação (TODO)

### Implementação Futura com JWT

```php
// Verificar token
$headers = getallheaders();
$token = $headers['Authorization'] ?? '';

if (!verificarJWT($token)) {
    Response::json(['error' => 'Token inválido'], 401);
}
```

**Header de Autenticação:**
```
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

---

## 📊 Versionamento

A API usa versionamento por URL:
- **v1:** `/api/v1/clientes` (atual)
- **v2:** `/api/v2/clientes` (futuro)

**Benefícios:**
- Backward compatibility
- Migração gradual
- Múltiplas versões simultâneas

---

## 🚀 Próximos Passos

- [ ] Implementar autenticação JWT
- [ ] Adicionar rate limiting
- [ ] Criar endpoints para Pacientes
- [ ] Criar endpoints para Atendimentos
- [ ] Adicionar filtros avançados
- [ ] Implementar ordenação
- [ ] Adicionar documentação Swagger/OpenAPI
- [ ] Criar testes automatizados

---

**Versão:** 1.0.0  
**Data:** 2026-01-14  
**Padrão:** RESTful API
