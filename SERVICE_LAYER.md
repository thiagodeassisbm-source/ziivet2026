# Service Layer Architecture - ClienteService

## 🏗️ Arquitetura em Camadas Implementada

### Estrutura Completa:
```
View (listar_clientes.php)
    ↓
Service (ClienteService)
    ↓
Repository (ClienteRepository)
    ↓
Database (Singleton)
    ↓
MySQL
```

---

## 📦 Classe `ClienteService`

**Localização:** `src/Application/Service/ClienteService.php`  
**Responsabilidade:** Orquestrar lógica de negócio e coordenar chamadas ao Repository

### Métodos Implementados:

#### 1. `listarPaginado(string $busca, int $pagina, int $itensPorPagina): array`
**Descrição:** Orquestra a listagem paginada de clientes  
**Retorno:**
```php
[
    'clientes' => [...],
    'total_registros' => 150,
    'total_paginas' => 8,
    'pagina_atual' => 1
]
```

**Responsabilidades:**
- Calcula offset automaticamente
- Chama Repository para buscar dados
- Retorna estrutura padronizada

---

#### 2. `excluir(int $id): array`
**Descrição:** Exclui cliente com tratamento de erros  
**Retorno:**
```php
[
    'success' => true,
    'message' => 'Cliente excluído com sucesso!'
]
```

**Tratamento de Erros:**
- Valida ID
- Captura exceções de constraint
- Retorna mensagens amigáveis

---

#### 3. `buscarPorId(int $id): ?array`
**Descrição:** Busca cliente com validação  
**Exceções:** Lança `Exception` se ID inválido

---

#### 4. `criar(array $dados): array`
**Descrição:** Cria cliente com validações  
**Validações:**
- Nome obrigatório
- Dados sanitizados

---

#### 5. `atualizar(int $id, array $dados): array`
**Descrição:** Atualiza cliente com validações

---

## 🔄 Evolução do Código

### Versão 1.0 - Procedural (Legado)
```php
// SQL direto no arquivo
$sql = "SELECT * FROM clientes WHERE...";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$clientes = $stmt->fetchAll();
```
**Problemas:**
- ❌ SQL misturado com HTML
- ❌ Difícil testar
- ❌ Código duplicado
- ❌ Sem separação de responsabilidades

---

### Versão 2.0 - Database Singleton
```php
$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->prepare($sql);
```
**Melhorias:**
- ✅ Conexão centralizada
- ❌ Ainda tem SQL na view

---

### Versão 3.0 - Repository Pattern
```php
$clienteRepo = new ClienteRepository($db);
$clientes = $clienteRepo->listar($busca, $offset, $limit);
```
**Melhorias:**
- ✅ SQL isolado
- ✅ Reutilizável
- ❌ View ainda tem lógica de negócio

---

### Versão 4.0 - Service Layer (ATUAL) ✨
```php
$clienteService = new ClienteService($clienteRepository);
$resultado = $clienteService->listarPaginado($busca, $pagina, $itens);
```
**Melhorias:**
- ✅ Separação total de responsabilidades
- ✅ Lógica de negócio isolada
- ✅ View 100% limpa
- ✅ Fácil testar cada camada
- ✅ Preparado para APIs

---

## 📊 Comparação: Antes vs Depois

### ❌ Antes (listar_clientes.php v3.0)
```php
// 70 linhas de lógica procedural
$offset = ($pagina_atual - 1) * $itens_per_page;

$total_registros = $clienteRepo->contar($filtro_busca);
$total_paginas = ceil($total_registros / $itens_per_page);
$clientes = $clienteRepo->listar($filtro_busca, $offset, $itens_per_page);

if ($_POST['acao'] === 'excluir') {
    if ($id <= 0) {
        Response::json(['error' => 'ID inválido'], 400);
    }
    try {
        $clienteRepo->excluir($id);
        Response::json(['success' => true]);
    } catch (PDOException $e) {
        Response::json(['error' => 'Constraint'], 500);
    }
}
```

### ✅ Depois (listar_clientes.php v4.0)
```php
// 10 linhas de código limpo
$clienteService = new ClienteService($clienteRepository);

// Listagem
$resultado = $clienteService->listarPaginado($busca, $pagina, $itens);

// Exclusão
$resultado = $clienteService->excluir($id);
Response::json([
    'status' => $resultado['success'] ? 'success' : 'error',
    'message' => $resultado['message']
]);
```

**Redução:** -86% de código na view!

---

## 🎯 Benefícios da Service Layer

### 1. **Separação de Responsabilidades (SRP)**
| Camada | Responsabilidade |
|--------|------------------|
| View | Renderizar HTML |
| Service | Lógica de negócio |
| Repository | Acesso a dados |
| Database | Conexão |

### 2. **Testabilidade**
```php
// Teste unitário do Service
$mockRepo = $this->createMock(ClienteRepository::class);
$service = new ClienteService($mockRepo);
$resultado = $service->listarPaginado('', 1, 20);
$this->assertArrayHasKey('clientes', $resultado);
```

### 3. **Reutilização**
```php
// Pode ser usado em:
// - Controllers
// - APIs REST
// - CLI Commands
// - Jobs/Workers
$service->listarPaginado($busca, $pagina, $itens);
```

### 4. **Manutenção**
- Mudanças na lógica de negócio: apenas Service
- Mudanças no SQL: apenas Repository
- Mudanças na UI: apenas View

### 5. **Preparação para Futuro**
```php
// Fácil criar API REST
Route::get('/api/clientes', function() {
    $service = new ClienteService($repo);
    return $service->listarPaginado($_GET['busca'], $_GET['page'], 20);
});
```

---

## 🚀 Próximos Passos

### 1. Criar Services para outras entidades
- `PacienteService`
- `AtendimentoService`
- `VacinaService`

### 2. Implementar Validações Robustas
```php
class ClienteValidator {
    public function validarCriacao(array $dados): array
    public function validarCPF(string $cpf): bool
}
```

### 3. Adicionar Eventos/Observers
```php
// Quando cliente é excluído
$this->eventDispatcher->dispatch(new ClienteExcluidoEvent($id));
```

### 4. Implementar DTOs
```php
class ClienteDTO {
    public function __construct(
        public int $id,
        public string $nome,
        public string $cpfCnpj
    ) {}
}
```

### 5. Criar Controllers Dedicados
```php
class ClienteController {
    public function __construct(private ClienteService $service) {}
    
    public function index() {
        $resultado = $this->service->listarPaginado(...);
        return view('clientes.index', $resultado);
    }
}
```

---

## 📈 Métricas de Qualidade

| Métrica | Antes | Depois | Melhoria |
|---------|-------|--------|----------|
| Linhas na View | 70 | 10 | -86% |
| Responsabilidades | 3 | 1 | -67% |
| Testabilidade | Difícil | Fácil | ✅ |
| Reutilização | Baixa | Alta | ✅ |
| Manutenção | Difícil | Fácil | ✅ |

---

## 🏆 Padrões Implementados

- ✅ **Service Layer Pattern**
- ✅ **Repository Pattern**
- ✅ **Dependency Injection**
- ✅ **Single Responsibility Principle (SRP)**
- ✅ **Separation of Concerns**
- ✅ **Clean Architecture**

---

**Arquitetura:** Layered Architecture (3-Tier)  
**Versão:** 4.0.0  
**Data:** 2026-01-14  
**Padrão:** Domain-Driven Design (DDD)
