<?php

namespace App\Infrastructure\Repository;

use App\Core\Database;
use PDO;
use PDOException;

class ClienteRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Lista clientes com paginação e busca
     *
     * @param string $busca Termo de busca (nome, CPF ou email)
     * @param int $offset Offset para paginação
     * @param int $limit Limite de registros
     * @return array Lista de clientes com seus animais
     */
    public function listar(string $busca = '', int $offset = 0, int $limit = 20): array
    {
        $conn = $this->db->getConnection();
        
        // Garantir que offset e limit são inteiros (segurança)
        $offset = (int)$offset;
        $limit = (int)$limit;
        $searchTerm = "%$busca%";
        
        $sql = "SELECT c.id, c.nome, c.cpf_cnpj, c.telefone, c.email,
                (SELECT GROUP_CONCAT(DISTINCT CONCAT(id, ':', nome_paciente) ORDER BY nome_paciente ASC SEPARATOR '|') 
                 FROM pacientes WHERE id_cliente = c.id) as lista_animais
                FROM clientes c
                WHERE (c.nome LIKE ? OR c.cpf_cnpj LIKE ? OR c.email LIKE ?)
                ORDER BY c.nome ASC 
                LIMIT $limit OFFSET $offset";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Conta total de clientes que correspondem à busca
     *
     * @param string $busca Termo de busca
     * @return int Total de registros
     */
    public function contar(string $busca = ''): int
    {
        $conn = $this->db->getConnection();
        
        $searchTerm = "%$busca%";
        
        $sql = "SELECT COUNT(*) as total 
                FROM clientes 
                WHERE nome LIKE ? OR cpf_cnpj LIKE ? OR email LIKE ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['total'];
    }

    /**
     * Busca um cliente por ID
     *
     * @param int $id ID do cliente
     * @return array|null Dados do cliente ou null se não encontrado
     */
    public function buscarPorId(int $id): ?array
    {
        $conn = $this->db->getConnection();
        
        $sql = "SELECT * FROM clientes WHERE id = :id LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Exclui um cliente
     *
     * @param int $id ID do cliente
     * @return bool True se excluído com sucesso
     * @throws PDOException Se houver erro (ex: constraint de chave estrangeira)
     */
    public function excluir(int $id): bool
    {
        $conn = $this->db->getConnection();
        
        $sql = "DELETE FROM clientes WHERE id = :id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Cria um novo cliente
     *
     * @param array $dados Dados do cliente
     * @return int ID do cliente criado
     */
    public function criar(array $dados): int
    {
        $conn = $this->db->getConnection();
        
        $sql = "INSERT INTO clientes (nome, cpf_cnpj, telefone, email, endereco, numero, bairro, cidade, estado, cep) 
                VALUES (:nome, :cpf_cnpj, :telefone, :email, :endereco, :numero, :bairro, :cidade, :estado, :cep)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':nome', $dados['nome']);
        $stmt->bindValue(':cpf_cnpj', $dados['cpf_cnpj'] ?? null);
        $stmt->bindValue(':telefone', $dados['telefone'] ?? null);
        $stmt->bindValue(':email', $dados['email'] ?? null);
        $stmt->bindValue(':endereco', $dados['endereco'] ?? null);
        $stmt->bindValue(':numero', $dados['numero'] ?? null);
        $stmt->bindValue(':bairro', $dados['bairro'] ?? null);
        $stmt->bindValue(':cidade', $dados['cidade'] ?? null);
        $stmt->bindValue(':estado', $dados['estado'] ?? null);
        $stmt->bindValue(':cep', $dados['cep'] ?? null);
        $stmt->execute();
        
        return (int)$conn->lastInsertId();
    }

    /**
     * Atualiza um cliente existente
     *
     * @param int $id ID do cliente
     * @param array $dados Dados a atualizar
     * @return bool True se atualizado com sucesso
     */
    public function atualizar(int $id, array $dados): bool
    {
        $conn = $this->db->getConnection();
        
        $sql = "UPDATE clientes 
                SET nome = :nome, cpf_cnpj = :cpf_cnpj, telefone = :telefone, email = :email,
                    endereco = :endereco, numero = :numero, bairro = :bairro, 
                    cidade = :cidade, estado = :estado, cep = :cep
                WHERE id = :id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':nome', $dados['nome']);
        $stmt->bindValue(':cpf_cnpj', $dados['cpf_cnpj'] ?? null);
        $stmt->bindValue(':telefone', $dados['telefone'] ?? null);
        $stmt->bindValue(':email', $dados['email'] ?? null);
        $stmt->bindValue(':endereco', $dados['endereco'] ?? null);
        $stmt->bindValue(':numero', $dados['numero'] ?? null);
        $stmt->bindValue(':bairro', $dados['bairro'] ?? null);
        $stmt->bindValue(':cidade', $dados['cidade'] ?? null);
        $stmt->bindValue(':estado', $dados['estado'] ?? null);
        $stmt->bindValue(':cep', $dados['cep'] ?? null);
        
        return $stmt->execute();
    }
}
