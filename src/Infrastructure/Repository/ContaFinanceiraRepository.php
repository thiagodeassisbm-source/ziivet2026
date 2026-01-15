<?php

namespace App\Infrastructure\Repository;

use App\Core\Database;
use App\Domain\Entity\ContaFinanceira;
use PDO;
use PDOException;

class ContaFinanceiraRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Lista contas financeiras com paginação e busca
     *
     * @param int $idAdmin ID do administrador
     * @param string $busca Termo de busca
     * @param int $offset Offset para paginação
     * @param int $limit Limite de registros
     * @return array Lista de contas
     */
    public function listar(int $idAdmin, string $busca = '', int $offset = 0, int $limit = 20): array
    {
        $conn = $this->db->getConnection();
        
        $offset = (int)$offset;
        $limit = (int)$limit;
        $searchTerm = "%$busca%";
        
        $sql = "SELECT * FROM contas_financeiras
                WHERE id_admin = ? 
                AND (nome_conta LIKE ? OR tipo_conta LIKE ?)
                ORDER BY nome_conta ASC 
                LIMIT $limit OFFSET $offset";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$idAdmin, $searchTerm, $searchTerm]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Conta total de contas financeiras
     *
     * @param int $idAdmin ID do administrador
     * @param string $busca Termo de busca
     * @return int Total de registros
     */
    public function contar(int $idAdmin, string $busca = ''): int
    {
        $conn = $this->db->getConnection();
        
        $searchTerm = "%$busca%";
        
        $sql = "SELECT COUNT(*) as total 
                FROM contas_financeiras 
                WHERE id_admin = ? 
                AND (nome_conta LIKE ? OR tipo_conta LIKE ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$idAdmin, $searchTerm, $searchTerm]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['total'];
    }

    /**
     * Busca uma conta por ID
     *
     * @param int $id ID da conta
     * @param int $idAdmin ID do administrador
     * @return array|null
     */
    public function buscarPorId(int $id, int $idAdmin): ?array
    {
        $conn = $this->db->getConnection();
        
        $sql = "SELECT * FROM contas_financeiras 
                WHERE id = ? AND id_admin = ? 
                LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id, $idAdmin]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Salva uma conta financeira (cria ou atualiza automaticamente)
     *
     * @param array $dados Dados da conta
     * @param int $idAdmin ID do administrador
     * @return int ID da conta (criada ou atualizada)
     */
    public function salvar(array $dados, int $idAdmin): int
    {
        $conn = $this->db->getConnection();
        
        // Se tem ID e é maior que 0, é atualização
        if (!empty($dados['id']) && (int)$dados['id'] > 0) {
            $id = (int)$dados['id'];
            
            $sql = "UPDATE contas_financeiras 
                    SET nome_conta = ?, tipo_conta = ?, status = ?, 
                        permitir_lancamentos = ?, saldo_inicial = ?, 
                        data_saldo = ?, situacao_saldo = ?
                    WHERE id = ? AND id_admin = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $dados['nome_conta'],
                $dados['tipo_conta'] ?? null,
                $dados['status'] ?? 'Ativo',
                $dados['permitir_lancamentos'] ?? 0,
                $dados['saldo_inicial'] ?? 0.00,
                $dados['data_saldo'] ?? null,
                $dados['situacao_saldo'] ?? 'Positivo',
                $id,
                $idAdmin
            ]);
            
            return $id;
        } else {
            // Criação
            $sql = "INSERT INTO contas_financeiras 
                    (id_admin, nome_conta, tipo_conta, status, permitir_lancamentos, 
                     saldo_inicial, data_saldo, situacao_saldo) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $idAdmin,
                $dados['nome_conta'],
                $dados['tipo_conta'] ?? null,
                $dados['status'] ?? 'Ativo',
                $dados['permitir_lancamentos'] ?? 0,
                $dados['saldo_inicial'] ?? 0.00,
                $dados['data_saldo'] ?? null,
                $dados['situacao_saldo'] ?? 'Positivo'
            ]);
            
            return (int)$conn->lastInsertId();
        }
    }

    /**
     * Busca totais e estatísticas das contas financeiras
     *
     * @param int $idAdmin ID do administrador
     * @return array Estatísticas das contas
     */
    public function buscarTotais(int $idAdmin): array
    {
        $conn = $this->db->getConnection();
        
        // Total de contas ativas
        $sqlContasAtivas = "SELECT COUNT(*) as total FROM contas_financeiras 
                            WHERE id_admin = ? AND status = 'Ativo'";
        $stmt = $conn->prepare($sqlContasAtivas);
        $stmt->execute([$idAdmin]);
        $totalAtivas = (int)$stmt->fetchColumn();
        
        // Saldo total (considerando situação positivo/negativo)
        $sqlSaldoTotal = "SELECT 
                            SUM(CASE 
                                WHEN situacao_saldo = 'Positivo' THEN saldo_inicial 
                                ELSE -saldo_inicial 
                            END) as saldo_total
                          FROM contas_financeiras 
                          WHERE id_admin = ? AND status = 'Ativo'";
        $stmt = $conn->prepare($sqlSaldoTotal);
        $stmt->execute([$idAdmin]);
        $saldoTotal = (float)($stmt->fetchColumn() ?? 0.00);
        
        // Contas com saldo positivo
        $sqlPositivas = "SELECT COUNT(*) as total FROM contas_financeiras 
                         WHERE id_admin = ? AND status = 'Ativo' 
                         AND situacao_saldo = 'Positivo' AND saldo_inicial > 0";
        $stmt = $conn->prepare($sqlPositivas);
        $stmt->execute([$idAdmin]);
        $contasPositivas = (int)$stmt->fetchColumn();
        
        // Contas com saldo negativo
        $sqlNegativas = "SELECT COUNT(*) as total FROM contas_financeiras 
                         WHERE id_admin = ? AND status = 'Ativo' 
                         AND (situacao_saldo = 'Negativo' OR saldo_inicial < 0)";
        $stmt = $conn->prepare($sqlNegativas);
        $stmt->execute([$idAdmin]);
        $contasNegativas = (int)$stmt->fetchColumn();
        
        // Contas por tipo
        $sqlPorTipo = "SELECT tipo_conta, COUNT(*) as total, 
                       SUM(CASE 
                           WHEN situacao_saldo = 'Positivo' THEN saldo_inicial 
                           ELSE -saldo_inicial 
                       END) as saldo_tipo
                       FROM contas_financeiras 
                       WHERE id_admin = ? AND status = 'Ativo'
                       GROUP BY tipo_conta";
        $stmt = $conn->prepare($sqlPorTipo);
        $stmt->execute([$idAdmin]);
        $contasPorTipo = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'total_contas_ativas' => $totalAtivas,
            'saldo_total' => $saldoTotal,
            'saldo_total_formatado' => $this->formatarMoeda($saldoTotal),
            'contas_positivas' => $contasPositivas,
            'contas_negativas' => $contasNegativas,
            'contas_por_tipo' => $contasPorTipo
        ];
    }

    /**
     * Formata valor monetário
     *
     * @param float $valor
     * @return string
     */
    private function formatarMoeda(float $valor): string
    {
        $prefixo = $valor >= 0 ? 'R$ ' : '-R$ ';
        return $prefixo . number_format(abs($valor), 2, ',', '.');
    }
}
