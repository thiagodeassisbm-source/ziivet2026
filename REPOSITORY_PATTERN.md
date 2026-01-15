# Repository Pattern - ClienteRepository

## 📦 Implementação do Padrão Repository

### Classe Criada: `ClienteRepository`
**Localização:** `src/Infrastructure/Repository/ClienteRepository.php`

### 🎯 Responsabilidade
Centralizar toda a lógica de acesso a dados relacionada à entidade `Cliente`, isolando o código SQL do restante da aplicação.

---

## 📋 Métodos Implementados

### 1. `listar(string $busca, int $offset, int $limit): array`
**Descrição:** Retorna lista paginada de clientes com seus animais  
**Query SQL:** Usa `GROUP_CONCAT` para agregar os animais de cada cliente  
**Parâmetros:**
- `$busca` - Termo para buscar em nome, CPF ou email
- `$offset` - Posição inicial para paginação
- `$limit` - Quantidade de registros a retornar

**Retorno:** Array de clientes com campo `lista_animais` contendo IDs e nomes dos pets

---

### 2. `contar(string $busca): int`
**Descrição:** Conta total de clientes que correspondem à busca  
**Uso:** Calcular total de páginas na paginação

---

### 3. `buscarPorId(int $id): ?array`
**Descrição:** Busca um cliente específico por ID  
**Retorno:** Array com dados do cliente ou `null` se não encontrado

---

### 4. `excluir(int $id): bool`
**Descrição:** Remove um cliente do banco de dados  
**Exceções:** Lança `PDOException` se houver constraint (ex: cliente tem animais vinculados)

---

### 5. `criar(array $dados): int`
**Descrição:** Cria um novo cliente  
**Retorno:** ID do cliente criado

---

### 6. `atualizar(int $id, array $dados): bool`
**Descrição:** Atualiza dados de um cliente existente  
**Retorno:** `true` se atualizado com sucesso

---

## 🔄 Refatoração de `listar_clientes.php`

### ❌ Antes (Código Procedural)
```php
// SQL direto no arquivo de view
$sql = "SELECT c.id, c.nome, c.cpf_cnpj, c.telefone, c.email,
        (SELECT GROUP_CONCAT(...) FROM pacientes...) as lista_animais
        FROM clientes c
        WHERE (c.nome LIKE :busca OR c.cpf_cnpj LIKE :busca...)
        ORDER BY c.nome ASC 
        LIMIT " . (int)$offset . ", " . (int)$itens_per_page;

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':busca', "%$filtro_busca%");
$stmt->execute();
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

### ✅ Depois (Repository Pattern)
```php
$db = Database::getInstance();
$clienteRepo = new ClienteRepository($db);

$total_registros = $clienteRepo->contar($filtro_busca);
$clientes = $clienteRepo->listar($filtro_busca, $offset, $itens_per_page);
```

---

## 🎁 Benefícios

### 1. **Separação de Responsabilidades**
- View (listar_clientes.php): Apenas renderiza HTML
- Repository: Gerencia acesso aos dados
- Database: Gerencia conexão

### 2. **Reutilização de Código**
```php
// Pode ser usado em qualquer lugar do sistema
$clienteRepo = new ClienteRepository($db);
$cliente = $clienteRepo->buscarPorId(10);
```

### 3. **Facilidade de Teste**
```php
// Mock do Database para testes unitários
$mockDb = $this->createMock(Database::class);
$repo = new ClienteRepository($mockDb);
```

### 4. **Manutenção Simplificada**
- Mudanças no SQL afetam apenas o Repository
- Fácil adicionar novos métodos de busca
- Query complexa isolada em um único lugar

### 5. **Preparação para ORM**
- Estrutura pronta para migrar para Doctrine/Eloquent
- Interface consistente independente da implementação

---

## 📊 Comparação de Linhas de Código

| Arquivo | Antes | Depois | Redução |
|---------|-------|--------|---------|
| listar_clientes.php | 25 linhas SQL | 3 linhas | -88% |
| Código SQL | Espalhado | Centralizado | ✅ |
| Testabilidade | Difícil | Fácil | ✅ |

---

## 🚀 Próximos Passos Recomendados

1. **Criar Repositories para outras entidades:**
   - `PacienteRepository`
   - `AtendimentoRepository`
   - `VacinaRepository`

2. **Implementar DTOs (Data Transfer Objects):**
   ```php
   class ClienteDTO {
       public int $id;
       public string $nome;
       public string $cpfCnpj;
       // ...
   }
   ```

3. **Adicionar métodos específicos:**
   ```php
   public function buscarComAnimais(int $id): ?array
   public function buscarPorCpf(string $cpf): ?array
   public function buscarInativos(): array
   ```

4. **Implementar Interfaces:**
   ```php
   interface ClienteRepositoryInterface {
       public function listar(string $busca, int $offset, int $limit): array;
       public function buscarPorId(int $id): ?array;
       // ...
   }
   ```

---

**Padrão:** Repository Pattern (Domain-Driven Design)  
**Versão:** 11.0.0  
**Data:** 2026-01-14
