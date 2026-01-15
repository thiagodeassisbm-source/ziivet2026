import React, { useState, useEffect } from 'react';

/**
 * =========================================================================================
 * ZIIPVET - DEMO FRONTEND REACT
 * ARQUIVO: frontend_demo.jsx
 * DESCRIÇÃO: Componente de exemplo para consumir a API REST de clientes
 * =========================================================================================
 */

const ClientesDemo = () => {
    // Estados
    const [clientes, setClientes] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [authenticated, setAuthenticated] = useState(false);
    const [loginData, setLoginData] = useState({ email: '', password: '' });

    // Configuração da API
    const API_BASE_URL = 'http://localhost:8000';

    /**
     * Fazer login (se necessário)
     * Nota: Atualmente a API usa sessão PHP, então o login deve ser feito
     * através do formulário HTML tradicional em /login.php
     */
    const handleLogin = async (e) => {
        e.preventDefault();

        try {
            // TODO: Implementar endpoint de login na API
            // Por enquanto, assumimos que o usuário já está logado via sessão PHP

            const response = await fetch(`${API_BASE_URL}/login.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    email: loginData.email,
                    password: loginData.password,
                }),
                credentials: 'include', // Importante: envia e recebe cookies
            });

            if (response.ok) {
                setAuthenticated(true);
                fetchClientes();
            } else {
                setError('Falha no login. Verifique suas credenciais.');
            }
        } catch (err) {
            setError('Erro ao fazer login: ' + err.message);
        }
    };

    /**
     * Buscar lista de clientes da API
     */
    const fetchClientes = async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await fetch(`${API_BASE_URL}/api/v1/clientes`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include', // Importante: envia cookies de sessão
            });

            if (response.status === 401) {
                setError('Não autorizado. Faça login primeiro.');
                setAuthenticated(false);
                return;
            }

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.success) {
                setClientes(data.data);
                setAuthenticated(true);
            } else {
                setError(data.message || 'Erro ao buscar clientes');
            }
        } catch (err) {
            setError('Erro ao buscar clientes: ' + err.message);
        } finally {
            setLoading(false);
        }
    };

    /**
     * Criar novo cliente
     */
    const createCliente = async (novoCliente) => {
        try {
            const response = await fetch(`${API_BASE_URL}/api/v1/clientes`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify(novoCliente),
            });

            const data = await response.json();

            if (data.success) {
                // Recarregar lista
                fetchClientes();
                return { success: true, id: data.data.id };
            } else {
                return { success: false, error: data.error };
            }
        } catch (err) {
            return { success: false, error: err.message };
        }
    };

    /**
     * Atualizar cliente
     */
    const updateCliente = async (id, dadosAtualizados) => {
        try {
            const response = await fetch(`${API_BASE_URL}/api/v1/clientes?id=${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify(dadosAtualizados),
            });

            const data = await response.json();

            if (data.success) {
                fetchClientes();
                return { success: true };
            } else {
                return { success: false, error: data.error };
            }
        } catch (err) {
            return { success: false, error: err.message };
        }
    };

    /**
     * Deletar cliente
     */
    const deleteCliente = async (id) => {
        if (!window.confirm('Deseja realmente excluir este cliente?')) {
            return;
        }

        try {
            const response = await fetch(`${API_BASE_URL}/api/v1/clientes?id=${id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            const data = await response.json();

            if (data.success) {
                fetchClientes();
            } else {
                alert('Erro ao excluir: ' + data.error);
            }
        } catch (err) {
            alert('Erro ao excluir: ' + err.message);
        }
    };

    /**
     * Carregar clientes ao montar o componente
     */
    useEffect(() => {
        fetchClientes();
    }, []);

    /**
     * Renderizar formulário de login
     */
    if (!authenticated && error?.includes('Não autorizado')) {
        return (
            <div style={styles.container}>
                <div style={styles.loginCard}>
                    <h2 style={styles.title}>Login - ZiipVet</h2>
                    <p style={styles.subtitle}>Faça login para acessar a lista de clientes</p>

                    <form onSubmit={handleLogin} style={styles.form}>
                        <div style={styles.formGroup}>
                            <label style={styles.label}>Email:</label>
                            <input
                                type="email"
                                value={loginData.email}
                                onChange={(e) => setLoginData({ ...loginData, email: e.target.value })}
                                style={styles.input}
                                placeholder="seu@email.com"
                                required
                            />
                        </div>

                        <div style={styles.formGroup}>
                            <label style={styles.label}>Senha:</label>
                            <input
                                type="password"
                                value={loginData.password}
                                onChange={(e) => setLoginData({ ...loginData, password: e.target.value })}
                                style={styles.input}
                                placeholder="••••••••"
                                required
                            />
                        </div>

                        <button type="submit" style={styles.button}>
                            Entrar
                        </button>
                    </form>

                    {error && <p style={styles.error}>{error}</p>}

                    <p style={styles.note}>
                        <strong>Nota:</strong> Por enquanto, faça login através de{' '}
                        <a href={`${API_BASE_URL}/login.php`} target="_blank" rel="noopener noreferrer">
                            login.php
                        </a>
                        {' '}e depois recarregue esta página.
                    </p>
                </div>
            </div>
        );
    }

    /**
     * Renderizar lista de clientes
     */
    return (
        <div style={styles.container}>
            <div style={styles.header}>
                <h1 style={styles.title}>
                    <span style={styles.icon}>👥</span> Clientes - ZiipVet
                </h1>
                <button onClick={fetchClientes} style={styles.refreshButton}>
                    🔄 Atualizar
                </button>
            </div>

            {loading && (
                <div style={styles.loading}>
                    <div style={styles.spinner}></div>
                    <p>Carregando clientes...</p>
                </div>
            )}

            {error && !loading && (
                <div style={styles.errorBox}>
                    <strong>❌ Erro:</strong> {error}
                </div>
            )}

            {!loading && !error && clientes.length === 0 && (
                <div style={styles.emptyState}>
                    <p style={styles.emptyIcon}>📭</p>
                    <h3>Nenhum cliente cadastrado</h3>
                    <p>Comece adicionando seu primeiro cliente!</p>
                </div>
            )}

            {!loading && !error && clientes.length > 0 && (
                <>
                    <div style={styles.stats}>
                        <div style={styles.statCard}>
                            <div style={styles.statNumber}>{clientes.length}</div>
                            <div style={styles.statLabel}>Total de Clientes</div>
                        </div>
                    </div>

                    <ul style={styles.list}>
                        {clientes.map((cliente) => (
                            <li key={cliente.id} style={styles.listItem}>
                                <div style={styles.clienteInfo}>
                                    <div style={styles.clienteAvatar}>
                                        {cliente.nome?.charAt(0).toUpperCase() || '?'}
                                    </div>
                                    <div style={styles.clienteDetails}>
                                        <h3 style={styles.clienteNome}>{cliente.nome}</h3>
                                        <p style={styles.clienteEmail}>
                                            📧 {cliente.email || 'Sem email'}
                                        </p>
                                        <p style={styles.clienteTelefone}>
                                            📱 {cliente.telefone || 'Sem telefone'}
                                        </p>
                                        {cliente.endereco && (
                                            <p style={styles.clienteEndereco}>
                                                📍 {cliente.endereco}
                                            </p>
                                        )}
                                    </div>
                                </div>
                                <div style={styles.actions}>
                                    <button
                                        onClick={() => alert(`Editar cliente ${cliente.id}`)}
                                        style={styles.editButton}
                                        title="Editar"
                                    >
                                        ✏️
                                    </button>
                                    <button
                                        onClick={() => deleteCliente(cliente.id)}
                                        style={styles.deleteButton}
                                        title="Excluir"
                                    >
                                        🗑️
                                    </button>
                                </div>
                            </li>
                        ))}
                    </ul>
                </>
            )}

            <div style={styles.footer}>
                <p>
                    <strong>API Base URL:</strong> {API_BASE_URL}
                </p>
                <p>
                    <strong>Endpoint:</strong> GET /api/v1/clientes
                </p>
                <p style={styles.footerNote}>
                    💡 <strong>Dica:</strong> Abra o DevTools (F12) → Network para ver as requisições
                </p>
            </div>
        </div>
    );
};

/**
 * Estilos inline (para demo)
 * Em produção, use CSS Modules, Styled Components ou Tailwind
 */
const styles = {
    container: {
        maxWidth: '1200px',
        margin: '0 auto',
        padding: '20px',
        fontFamily: "'Inter', 'Segoe UI', sans-serif",
        backgroundColor: '#f5f7fa',
        minHeight: '100vh',
    },
    header: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: '30px',
        padding: '20px',
        backgroundColor: '#fff',
        borderRadius: '12px',
        boxShadow: '0 2px 8px rgba(0,0,0,0.1)',
    },
    title: {
        fontSize: '28px',
        fontWeight: '700',
        color: '#1e293b',
        margin: 0,
        display: 'flex',
        alignItems: 'center',
        gap: '10px',
    },
    subtitle: {
        fontSize: '16px',
        color: '#64748b',
        marginBottom: '20px',
    },
    icon: {
        fontSize: '32px',
    },
    refreshButton: {
        padding: '10px 20px',
        backgroundColor: '#3b82f6',
        color: '#fff',
        border: 'none',
        borderRadius: '8px',
        cursor: 'pointer',
        fontSize: '14px',
        fontWeight: '600',
        transition: 'all 0.3s',
    },
    loading: {
        textAlign: 'center',
        padding: '60px 20px',
        backgroundColor: '#fff',
        borderRadius: '12px',
    },
    spinner: {
        width: '50px',
        height: '50px',
        border: '4px solid #e2e8f0',
        borderTop: '4px solid #3b82f6',
        borderRadius: '50%',
        margin: '0 auto 20px',
        animation: 'spin 1s linear infinite',
    },
    errorBox: {
        padding: '20px',
        backgroundColor: '#fee2e2',
        color: '#991b1b',
        borderRadius: '8px',
        marginBottom: '20px',
        border: '1px solid #fecaca',
    },
    emptyState: {
        textAlign: 'center',
        padding: '60px 20px',
        backgroundColor: '#fff',
        borderRadius: '12px',
    },
    emptyIcon: {
        fontSize: '64px',
        margin: '0 0 20px 0',
    },
    stats: {
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
        gap: '20px',
        marginBottom: '30px',
    },
    statCard: {
        backgroundColor: '#fff',
        padding: '24px',
        borderRadius: '12px',
        boxShadow: '0 2px 8px rgba(0,0,0,0.1)',
        textAlign: 'center',
    },
    statNumber: {
        fontSize: '36px',
        fontWeight: '700',
        color: '#3b82f6',
        marginBottom: '8px',
    },
    statLabel: {
        fontSize: '14px',
        color: '#64748b',
        textTransform: 'uppercase',
        letterSpacing: '0.5px',
    },
    list: {
        listStyle: 'none',
        padding: 0,
        margin: 0,
    },
    listItem: {
        backgroundColor: '#fff',
        padding: '20px',
        marginBottom: '12px',
        borderRadius: '12px',
        boxShadow: '0 2px 8px rgba(0,0,0,0.1)',
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        transition: 'all 0.3s',
    },
    clienteInfo: {
        display: 'flex',
        alignItems: 'center',
        gap: '16px',
        flex: 1,
    },
    clienteAvatar: {
        width: '60px',
        height: '60px',
        borderRadius: '50%',
        backgroundColor: '#3b82f6',
        color: '#fff',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        fontSize: '24px',
        fontWeight: '700',
        flexShrink: 0,
    },
    clienteDetails: {
        flex: 1,
    },
    clienteNome: {
        fontSize: '18px',
        fontWeight: '600',
        color: '#1e293b',
        margin: '0 0 8px 0',
    },
    clienteEmail: {
        fontSize: '14px',
        color: '#64748b',
        margin: '4px 0',
    },
    clienteTelefone: {
        fontSize: '14px',
        color: '#64748b',
        margin: '4px 0',
    },
    clienteEndereco: {
        fontSize: '14px',
        color: '#64748b',
        margin: '4px 0',
    },
    actions: {
        display: 'flex',
        gap: '8px',
    },
    editButton: {
        padding: '8px 16px',
        backgroundColor: '#f59e0b',
        color: '#fff',
        border: 'none',
        borderRadius: '6px',
        cursor: 'pointer',
        fontSize: '16px',
        transition: 'all 0.3s',
    },
    deleteButton: {
        padding: '8px 16px',
        backgroundColor: '#ef4444',
        color: '#fff',
        border: 'none',
        borderRadius: '6px',
        cursor: 'pointer',
        fontSize: '16px',
        transition: 'all 0.3s',
    },
    footer: {
        marginTop: '40px',
        padding: '20px',
        backgroundColor: '#fff',
        borderRadius: '12px',
        fontSize: '14px',
        color: '#64748b',
    },
    footerNote: {
        marginTop: '12px',
        padding: '12px',
        backgroundColor: '#f0f9ff',
        borderLeft: '4px solid #3b82f6',
        borderRadius: '4px',
    },
    loginCard: {
        maxWidth: '400px',
        margin: '100px auto',
        padding: '40px',
        backgroundColor: '#fff',
        borderRadius: '12px',
        boxShadow: '0 4px 16px rgba(0,0,0,0.1)',
    },
    form: {
        marginBottom: '20px',
    },
    formGroup: {
        marginBottom: '20px',
    },
    label: {
        display: 'block',
        marginBottom: '8px',
        fontSize: '14px',
        fontWeight: '600',
        color: '#1e293b',
    },
    input: {
        width: '100%',
        padding: '12px',
        fontSize: '14px',
        border: '1px solid #e2e8f0',
        borderRadius: '8px',
        boxSizing: 'border-box',
    },
    button: {
        width: '100%',
        padding: '12px',
        backgroundColor: '#3b82f6',
        color: '#fff',
        border: 'none',
        borderRadius: '8px',
        fontSize: '16px',
        fontWeight: '600',
        cursor: 'pointer',
        transition: 'all 0.3s',
    },
    error: {
        color: '#ef4444',
        fontSize: '14px',
        marginTop: '12px',
    },
    note: {
        marginTop: '20px',
        padding: '12px',
        backgroundColor: '#f0f9ff',
        borderRadius: '8px',
        fontSize: '13px',
        color: '#1e40af',
    },
};

export default ClientesDemo;
