# Segurança da API - AuthMiddleware

## 📁 Arquivo Criado: `src/Application/Auth/AuthMiddleware.php`

### 🔐 Proteção Implementada

A API REST agora está protegida por um middleware de autenticação que verifica se o usuário está autenticado antes de permitir acesso aos endpoints.

---

## 🎯 Implementação Atual (Sessão PHP)

### Como Funciona:

```php
// No início de cada endpoint da API
use App\Application\Auth\AuthMiddleware;

AuthMiddleware::verificar();
```

**Verificação:**
1. Inicia sessão PHP se necessário
2. Verifica se existe `$_SESSION['usuario_id']` (ou variantes)
3. Se **NÃO** autenticado → Retorna `401 Unauthorized` e encerra
4. Se **SIM** autenticado → Continua a execução

**Resposta 401:**
```json
{
  "error": "Não autorizado",
  "message": "Você precisa estar autenticado para acessar este recurso."
}
```

---

## 📋 Métodos Disponíveis

### 1. **`verificar()`** - Verificação Principal
```php
AuthMiddleware::verificar();
```
- Verifica autenticação
- Retorna 401 se não autenticado
- Encerra script automaticamente

### 2. **`getUsuarioId()`** - Obter ID do Usuário
```php
$userId = AuthMiddleware::getUsuarioId();
// Retorna: int|null
```

### 3. **`getAdminId()`** - Obter ID do Admin
```php
$adminId = AuthMiddleware::getAdminId();
// Retorna: int|null
```

### 4. **`temPermissao()`** - Verificar Permissão
```php
if (AuthMiddleware::temPermissao('editar_clientes')) {
    // Usuário tem permissão
}
```

### 5. **`aplicarCORS()`** - Configurar CORS
```php
AuthMiddleware::aplicarCORS(['http://localhost:3000', 'https://app.ziipvet.com']);
```

---

## 🚀 Migração Futura para JWT

### Roadmap de Implementação:

#### **Fase 1: Preparação** ✅ (Concluída)
- [x] Criar `AuthMiddleware`
- [x] Implementar verificação por sessão
- [x] Proteger endpoint `/api/v1/clientes`
- [x] Adicionar comentários TODO

#### **Fase 2: Implementação JWT** (Futuro)

**1. Instalar biblioteca JWT:**
```bash
composer require firebase/php-jwt
```

**2. Criar endpoint de login:**
```php
// api/v1/auth/login.php
POST /api/v1/auth/login
{
  "email": "usuario@email.com",
  "password": "senha123"
}

// Resposta:
{
  "success": true,
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "expires_in": 86400
}
```

**3. Atualizar `AuthMiddleware::verificar()`:**
```php
public static function verificar(): void
{
    // Extrair token do header
    $token = self::extrairTokenDoHeader();
    
    if (!$token) {
        Response::json(['error' => 'Token não fornecido'], 401);
    }
    
    // Validar JWT
    if (!self::validarJWT($token)) {
        Response::json(['error' => 'Token inválido ou expirado'], 401);
    }
}
```

**4. Frontend envia token:**
```javascript
fetch('http://localhost:8000/api/v1/clientes', {
  headers: {
    'Authorization': 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...',
    'Content-Type': 'application/json'
  }
})
```

---

## 📊 Comparação: Sessão vs JWT

| Aspecto | Sessão PHP (Atual) | JWT (Futuro) |
|---------|-------------------|--------------|
| **Armazenamento** | Servidor | Cliente |
| **Escalabilidade** | Limitada | Excelente |
| **Stateless** | Não | Sim |
| **Mobile/SPA** | Difícil | Fácil |
| **Segurança** | Boa | Excelente |
| **Complexidade** | Baixa | Média |

---

## 🔧 Uso nos Endpoints

### Endpoint Protegido:
```php
<?php
// api/v1/clientes/index.php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Application\Auth\AuthMiddleware;
use App\Utils\Response;

// ✅ PROTEÇÃO ATIVA
AuthMiddleware::verificar();

// Código do endpoint...
$clientes = $clienteService->listarPaginado($busca, $pagina, $limite);
Response::json(['success' => true, 'data' => $clientes]);
```

### Endpoint Público (sem proteção):
```php
<?php
// api/v1/public/status.php

use App\Utils\Response;

// ❌ SEM PROTEÇÃO (público)
Response::json([
    'status' => 'online',
    'version' => '1.0.0'
]);
```

---

## 🛡️ Boas Práticas de Segurança

### 1. **Sempre Validar Entrada**
```php
$id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if (!$id) {
    Response::json(['error' => 'ID inválido'], 400);
}
```

### 2. **Usar Prepared Statements**
```php
// ✅ CORRETO
$stmt = $conn->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$id]);

// ❌ ERRADO (SQL Injection)
$sql = "SELECT * FROM clientes WHERE id = $id";
```

### 3. **Limitar Taxa de Requisições (Rate Limiting)**
```php
// TODO: Implementar rate limiting
// Exemplo: Máximo 100 requisições por minuto por IP
```

### 4. **Validar Origem (CORS)**
```php
AuthMiddleware::aplicarCORS([
    'http://localhost:3000',      // Desenvolvimento
    'https://app.ziipvet.com'     // Produção
]);
```

### 5. **HTTPS em Produção**
```php
if ($_SERVER['HTTPS'] !== 'on' && $_ENV['APP_ENV'] === 'production') {
    Response::json(['error' => 'HTTPS obrigatório'], 403);
}
```

---

## 📝 Checklist de Segurança

### Implementado ✅
- [x] Middleware de autenticação
- [x] Verificação de sessão
- [x] Resposta 401 padronizada
- [x] CORS configurável
- [x] Prepared statements no Repository
- [x] Validação de entrada nos Services

### Pendente ⏳
- [ ] Autenticação JWT
- [ ] Rate limiting
- [ ] Logs de acesso
- [ ] Auditoria de ações
- [ ] Refresh tokens
- [ ] 2FA (Two-Factor Authentication)
- [ ] IP Whitelist/Blacklist

---

## 🎯 Exemplo Completo de Uso

```php
// api/v1/vendas/index.php
<?php
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Application\Auth\AuthMiddleware;
use App\Application\Service\VendaService;
use App\Utils\Response;

// Proteção
AuthMiddleware::verificar();

// Obter dados do usuário autenticado
$userId = AuthMiddleware::getUsuarioId();
$adminId = AuthMiddleware::getAdminId();

// Verificar permissão específica
if (!AuthMiddleware::temPermissao('criar_vendas')) {
    Response::json(['error' => 'Sem permissão'], 403);
}

// Processar requisição
$vendaService = new VendaService(/* ... */);
$resultado = $vendaService->fecharVenda([
    'id_admin' => $adminId,
    'usuario_vendedor' => $userId,
    // ...
]);

Response::json($resultado);
```

---

**Status:** ✅ API Protegida  
**Versão:** 1.0.0 (Sessão PHP)  
**Próxima Versão:** 2.0.0 (JWT)
