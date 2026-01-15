# 🚀 Frontend Demo - React Component

## 📁 Arquivo: `frontend_demo.jsx`

Componente React completo e funcional que demonstra como consumir a API REST do ZiipVet.

---

## ✨ Funcionalidades Implementadas

### 1. **Autenticação** 🔐
- Formulário de login
- Detecção automática de sessão
- Redirecionamento para login quando não autenticado
- Suporte a cookies de sessão PHP

### 2. **CRUD Completo** 📝
- ✅ **GET** - Listar todos os clientes
- ✅ **POST** - Criar novo cliente
- ✅ **PUT** - Atualizar cliente existente
- ✅ **DELETE** - Excluir cliente

### 3. **UI/UX Moderna** 🎨
- Design responsivo
- Loading states
- Error handling
- Empty states
- Confirmação de exclusão
- Estatísticas em tempo real

### 4. **Boas Práticas** ✅
- React Hooks (useState, useEffect)
- Async/Await
- Error boundaries
- Credentials: 'include' (cookies)
- Componentização
- Código limpo e comentado

---

## 🎯 Como Usar

### Opção 1: Integrar em Projeto React Existente

```bash
# 1. Copiar o arquivo
cp frontend_demo.jsx src/components/ClientesDemo.jsx

# 2. Importar no seu App.js
import ClientesDemo from './components/ClientesDemo';

function App() {
  return (
    <div className="App">
      <ClientesDemo />
    </div>
  );
}
```

### Opção 2: Criar Novo Projeto React

```bash
# Criar projeto
npx create-react-app ziipvet-frontend
cd ziipvet-frontend

# Copiar componente
cp ../frontend_demo.jsx src/components/ClientesDemo.jsx

# Editar src/App.js
# (importar e usar o componente)

# Rodar
npm start
```

### Opção 3: Usar com Next.js (Recomendado)

```bash
# Criar projeto Next.js
npx create-next-app@latest ziipvet-next
cd ziipvet-next

# Copiar componente
cp ../frontend_demo.jsx app/components/ClientesDemo.jsx

# Criar página
# app/clientes/page.jsx
```

---

## 📋 Estrutura do Componente

### Estados:
```javascript
const [clientes, setClientes] = useState([]);        // Lista de clientes
const [loading, setLoading] = useState(true);        // Estado de carregamento
const [error, setError] = useState(null);            // Mensagens de erro
const [authenticated, setAuthenticated] = useState(false); // Status de auth
```

### Funções Principais:

#### 1. **fetchClientes()** - Buscar Lista
```javascript
const fetchClientes = async () => {
  const response = await fetch(`${API_BASE_URL}/api/v1/clientes`, {
    method: 'GET',
    credentials: 'include', // ← Importante!
  });
  
  const data = await response.json();
  setClientes(data.data);
};
```

#### 2. **createCliente()** - Criar Novo
```javascript
const createCliente = async (novoCliente) => {
  const response = await fetch(`${API_BASE_URL}/api/v1/clientes`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify(novoCliente),
  });
};
```

#### 3. **updateCliente()** - Atualizar
```javascript
const updateCliente = async (id, dadosAtualizados) => {
  const response = await fetch(`${API_BASE_URL}/api/v1/clientes?id=${id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify(dadosAtualizados),
  });
};
```

#### 4. **deleteCliente()** - Excluir
```javascript
const deleteCliente = async (id) => {
  const response = await fetch(`${API_BASE_URL}/api/v1/clientes?id=${id}`, {
    method: 'DELETE',
    credentials: 'include',
  });
};
```

---

## 🔧 Configuração

### 1. **API Base URL**
```javascript
const API_BASE_URL = 'http://localhost:8000';
```

**Alterar para produção:**
```javascript
const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'https://api.ziipvet.com';
```

### 2. **CORS (Backend)**
Certifique-se de que o backend permite requisições do frontend:

```php
// api/v1/clientes/index.php
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Credentials: true');
```

### 3. **Cookies (Importante!)**
```javascript
credentials: 'include' // ← Sempre incluir para enviar cookies de sessão
```

---

## 📊 Exemplo de Resposta da API

### GET /api/v1/clientes
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "nome": "João Silva",
      "email": "joao@email.com",
      "telefone": "(11) 98765-4321",
      "endereco": "Rua das Flores, 123",
      "status": "ATIVO"
    },
    {
      "id": 2,
      "nome": "Maria Santos",
      "email": "maria@email.com",
      "telefone": "(11) 91234-5678",
      "endereco": "Av. Paulista, 456",
      "status": "ATIVO"
    }
  ],
  "pagination": {
    "current_page": 1,
    "total_pages": 5,
    "total_records": 48,
    "per_page": 20
  }
}
```

### POST /api/v1/clientes
```json
{
  "success": true,
  "message": "Cliente cadastrado com sucesso!",
  "data": {
    "id": 49
  }
}
```

### Erro 401 (Não Autorizado)
```json
{
  "error": "Não autorizado",
  "message": "Você precisa estar autenticado para acessar este recurso."
}
```

---

## 🎨 Estilos

O componente usa **estilos inline** para facilitar a demonstração.

### Para Produção, Migre para:

#### 1. **CSS Modules**
```javascript
import styles from './ClientesDemo.module.css';

<div className={styles.container}>
```

#### 2. **Styled Components**
```javascript
import styled from 'styled-components';

const Container = styled.div`
  max-width: 1200px;
  margin: 0 auto;
`;
```

#### 3. **Tailwind CSS** (Recomendado)
```javascript
<div className="max-w-7xl mx-auto p-6 bg-gray-50">
  <h1 className="text-3xl font-bold text-gray-900">
    Clientes
  </h1>
</div>
```

---

## 🚀 Próximos Passos

### 1. **Adicionar Paginação**
```javascript
const [page, setPage] = useState(1);

const fetchClientes = async () => {
  const response = await fetch(
    `${API_BASE_URL}/api/v1/clientes?pagina=${page}&limite=20`
  );
};
```

### 2. **Adicionar Busca**
```javascript
const [searchTerm, setSearchTerm] = useState('');

const fetchClientes = async () => {
  const response = await fetch(
    `${API_BASE_URL}/api/v1/clientes?busca=${searchTerm}`
  );
};
```

### 3. **Adicionar Formulário de Criação**
```javascript
const [showForm, setShowForm] = useState(false);
const [formData, setFormData] = useState({
  nome: '',
  email: '',
  telefone: '',
  endereco: ''
});

const handleSubmit = async (e) => {
  e.preventDefault();
  await createCliente(formData);
  setShowForm(false);
  setFormData({ nome: '', email: '', telefone: '', endereco: '' });
};
```

### 4. **Migrar para Next.js**
```bash
# Criar projeto
npx create-next-app@latest ziipvet-frontend

# Estrutura recomendada:
app/
├── layout.jsx
├── page.jsx
├── clientes/
│   ├── page.jsx          # Lista de clientes
│   ├── novo/
│   │   └── page.jsx      # Criar cliente
│   └── [id]/
│       └── page.jsx      # Editar cliente
└── components/
    ├── ClientesList.jsx
    ├── ClienteForm.jsx
    └── ClienteCard.jsx
```

### 5. **Implementar JWT**
```javascript
// Após migrar backend para JWT
const [token, setToken] = useState(localStorage.getItem('token'));

const fetchClientes = async () => {
  const response = await fetch(`${API_BASE_URL}/api/v1/clientes`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
  });
};
```

---

## 🧪 Testando o Componente

### 1. **Teste Manual**
```bash
# Rodar backend
npm run dev

# Rodar frontend (em outro terminal)
cd ziipvet-frontend
npm start

# Abrir navegador
http://localhost:3000
```

### 2. **Teste com DevTools**
```
1. Abrir DevTools (F12)
2. Ir para aba Network
3. Fazer login
4. Ver requisições para /api/v1/clientes
5. Verificar cookies sendo enviados
```

### 3. **Teste de Autenticação**
```
1. Abrir em aba anônima
2. Tentar acessar /clientes
3. Deve mostrar tela de login
4. Fazer login
5. Deve mostrar lista de clientes
```

---

## 📝 Notas Importantes

### ⚠️ **Cookies e CORS**
Para que os cookies funcionem entre domínios diferentes:

```javascript
// Frontend
credentials: 'include'

// Backend (PHP)
header('Access-Control-Allow-Origin: http://localhost:3000'); // URL exata
header('Access-Control-Allow-Credentials: true');
```

### ⚠️ **Sessão PHP vs JWT**
Atualmente usa **sessão PHP**:
- ✅ Simples de implementar
- ✅ Funciona imediatamente
- ❌ Não é stateless
- ❌ Dificulta escalabilidade

**Migrar para JWT:**
- ✅ Stateless
- ✅ Escalável
- ✅ Mobile-friendly
- ❌ Mais complexo

---

## 🎯 Exemplo de Uso Completo

```jsx
import React from 'react';
import ClientesDemo from './components/ClientesDemo';

function App() {
  return (
    <div className="App">
      {/* Componente pronto para uso */}
      <ClientesDemo />
    </div>
  );
}

export default App;
```

**Resultado:**
- ✅ Lista de clientes carregada da API
- ✅ Botões de editar/excluir funcionais
- ✅ Loading states
- ✅ Error handling
- ✅ UI moderna e responsiva

---

**Arquivo criado:** ✅ `frontend_demo.jsx`  
**Status:** Pronto para uso  
**Compatível com:** React 18+, Next.js 13+  
**API:** http://localhost:8000/api/v1/clientes
