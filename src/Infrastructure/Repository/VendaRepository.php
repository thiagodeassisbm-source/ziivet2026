<?php

namespace App\Infrastructure\Repository;

use App\Core\Database;
use PDO;
use PDOException;

class VendaRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Lista vendas com paginação e filtros
     *
     * @param int $idAdmin ID do administrador
     * @param array $filtros Filtros opcionais
     * @param int $offset Offset para paginação
     * @param int $limit Limite de registros
     * @return array
     */
    public function listar(int $idAdmin, array $filtros = [], int $offset = 0, int $limit = 20): array
    {
        $conn = $this->db->getConnection();
        
        $offset = (int)$offset;
        $limit = (int)$limit;
        
        $where = ["v.id_admin = ?"];
        $params = [$idAdmin];
        
        // Filtro por tipo de movimento
        if (!empty($filtros['tipo_movimento'])) {
            $where[] = "v.tipo_movimento = ?";
            $params[] = $filtros['tipo_movimento'];
        }
        
        // Filtro por status de pagamento
        if (!empty($filtros['status_pagamento'])) {
            $where[] = "v.status_pagamento = ?";
            $params[] = $filtros['status_pagamento'];
        }
        
        // Filtro por cliente
        if (!empty($filtros['id_cliente'])) {
            $where[] = "v.id_cliente = ?";
            $params[] = (int)$filtros['id_cliente'];
        }
        
        // Filtro por data
        if (!empty($filtros['data_inicio'])) {
            $where[] = "v.data_venda >= ?";
            $params[] = $filtros['data_inicio'];
        }
        
        if (!empty($filtros['data_fim'])) {
            $where[] = "v.data_venda <= ?";
            $params[] = $filtros['data_fim'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT v.*, c.nome as nome_cliente, p.nome_paciente
                FROM vendas v
                LEFT JOIN clientes c ON v.id_cliente = c.id
                LEFT JOIN pacientes p ON v.id_paciente = p.id
                WHERE $whereClause
                ORDER BY v.data_cadastro DESC
                LIMIT $limit OFFSET $offset";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Conta total de vendas
     *
     * @param int $idAdmin ID do administrador
     * @param array $filtros Filtros opcionais
     * @return int
     */
    public function contar(int $idAdmin, array $filtros = []): int
    {
        $conn = $this->db->getConnection();
        
        $where = ["id_admin = ?"];
        $params = [$idAdmin];
        
        if (!empty($filtros['tipo_movimento'])) {
            $where[] = "tipo_movimento = ?";
            $params[] = $filtros['tipo_movimento'];
        }
        
        if (!empty($filtros['status_pagamento'])) {
            $where[] = "status_pagamento = ?";
            $params[] = $filtros['status_pagamento'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT COUNT(*) as total FROM vendas WHERE $whereClause";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        return (int)$stmt->fetchColumn();
    }

    /**
     * Busca uma venda por ID
     *
     * @param int $id ID da venda
     * @param int $idAdmin ID do administrador
     * @return array|null
     */
    public function buscarPorId(int $id, int $idAdmin): ?array
    {
        $conn = $this->db->getConnection();
        
        $sql = "SELECT v.*, c.nome as nome_cliente, p.nome_paciente
                FROM vendas v
                LEFT JOIN clientes c ON v.id_cliente = c.id
                LEFT JOIN pacientes p ON v.id_paciente = p.id
                WHERE v.id = ? AND v.id_admin = ?
                LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id, $idAdmin]);
        
        $venda = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$venda) {
            return null;
        }
        
        // Buscar itens da venda
        $venda['itens'] = $this->buscarItensPorVenda($id);
        
        return $venda;
    }

    /**
     * Busca itens de uma venda
     *
     * @param int $idVenda ID da venda
     * @return array
     */
    public function buscarItensPorVenda(int $idVenda): array
    {
        $conn = $this->db->getConnection();
        
        $sql = "SELECT vi.*, p.nome as nome_produto
                FROM vendas_itens vi
                INNER JOIN produtos p ON vi.id_produto = p.id
                WHERE vi.id_venda = ?
                ORDER BY vi.id ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$idVenda]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cria uma nova venda
     *
     * @param array $dados Dados da venda
     * @return int ID da venda criada
     */
    public function criar(array $dados): int
    {
        $conn = $this->db->getConnection();
        
        $sql = "INSERT INTO vendas (
                    id_admin, usuario_vendedor, id_cliente, id_paciente, 
                    data_venda, data_validade, tipo_movimento, tipo_venda, 
                    valor_total, observacoes, status_pagamento
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $dados['id_admin'],
            $dados['usuario_vendedor'],
            $dados['id_cliente'] ?? null,
            $dados['id_paciente'] ?? null,
            $dados['data_venda'],
            $dados['data_validade'] ?? null,
            $dados['tipo_movimento'],
            $dados['tipo_venda'] ?? null,
            $dados['valor_total'],
            $dados['observacoes'] ?? null,
            $dados['status_pagamento'] ?? 'PENDENTE'
        ]);
        
        return (int)$conn->lastInsertId();
    }

    /**
     * Adiciona item à venda
     *
     * @param int $idVenda ID da venda
     * @param array $item Dados do item
     * @return int ID do item criado
     */
    public function adicionarItem(int $idVenda, array $item): int
    {
        $conn = $this->db->getConnection();
        
        $sql = "INSERT INTO vendas_itens (id_venda, id_produto, quantidade, valor_unitario, valor_total)
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $idVenda,
            $item['id_produto'],
            $item['quantidade'],
            $item['valor_unitario'],
            $item['valor_total']
        ]);
        
        return (int)$conn->lastInsertId();
    }

    /**
     * Atualiza status de pagamento
     *
     * @param int $id ID da venda
     * @param string $status Novo status
     * @return bool
     */
    public function atualizarStatusPagamento(int $id, string $status): bool
    {
        $conn = $this->db->getConnection();
        
        $sql = "UPDATE vendas SET status_pagamento = ? WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        return $stmt->execute([$status, $id]);
    }

    /**
     * Busca estatísticas de vendas
     *
     * @param int $idAdmin ID do administrador
     * @param array $filtros Filtros opcionais (data_inicio, data_fim)
     * @return array
     */
    public function buscarEstatisticas(int $idAdmin, array $filtros = []): array
    {
        $conn = $this->db->getConnection();
        
        $where = ["id_admin = ?", "tipo_movimento = 'Venda'"];
        $params = [$idAdmin];
        
        if (!empty($filtros['data_inicio'])) {
            $where[] = "data_venda >= ?";
            $params[] = $filtros['data_inicio'];
        }
        
        if (!empty($filtros['data_fim'])) {
            $where[] = "data_venda <= ?";
            $params[] = $filtros['data_fim'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Total de vendas e valor
        $sql = "SELECT 
                    COUNT(*) as total_vendas,
                    SUM(valor_total) as valor_total_vendas,
                    AVG(valor_total) as ticket_medio,
                    SUM(CASE WHEN status_pagamento = 'PAGO' THEN valor_total ELSE 0 END) as valor_recebido,
                    SUM(CASE WHEN status_pagamento = 'PENDENTE' THEN valor_total ELSE 0 END) as valor_pendente
                FROM vendas 
                WHERE $whereClause";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Busca produtos mais vendidos
     *
     * @param int $idAdmin ID do administrador
     * @param int $limit Limite de produtos
     * @return array
     */
    public function buscarProdutosMaisVendidos(int $idAdmin, int $limit = 10): array
    {
        $conn = $this->db->getConnection();
        
        $sql = "SELECT 
                    p.id, p.nome, 
                    SUM(vi.quantidade) as quantidade_vendida,
                    SUM(vi.valor_total) as valor_total_vendido
                FROM vendas_itens vi
                INNER JOIN produtos p ON vi.id_produto = p.id
                INNER JOIN vendas v ON vi.id_venda = v.id
                WHERE v.id_admin = ? AND v.tipo_movimento = 'Venda'
                GROUP BY p.id, p.nome
                ORDER BY quantidade_vendida DESC
                LIMIT ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$idAdmin, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
