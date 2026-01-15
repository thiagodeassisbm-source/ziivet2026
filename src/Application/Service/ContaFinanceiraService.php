<?php

namespace App\Application\Service;

use App\Infrastructure\Repository\ContaFinanceiraRepository;
use Exception;
use PDOException;

class ContaFinanceiraService
{
    private ContaFinanceiraRepository $contaRepository;

    public function __construct(ContaFinanceiraRepository $contaRepository)
    {
        $this->contaRepository = $contaRepository;
    }

    /**
     * Lista contas com paginação
     *
     * @param int $idAdmin ID do administrador
     * @param string $busca Termo de busca
     * @param int $pagina Página atual
     * @param int $itensPorPagina Itens por página
     * @return array
     */
    public function listarPaginado(
        int $idAdmin,
        string $busca = '',
        int $pagina = 1,
        int $itensPorPagina = 20
    ): array {
        $offset = ($pagina - 1) * $itensPorPagina;
        
        $totalRegistros = $this->contaRepository->contar($idAdmin, $busca);
        $totalPaginas = ceil($totalRegistros / $itensPorPagina);
        $contas = $this->contaRepository->listar($idAdmin, $busca, $offset, $itensPorPagina);

        return [
            'contas' => $contas,
            'total_registros' => $totalRegistros,
            'total_paginas' => $totalPaginas,
            'pagina_atual' => $pagina
        ];
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
        if ($id <= 0) {
            throw new Exception('ID inválido');
        }

        return $this->contaRepository->buscarPorId($id, $idAdmin);
    }

    /**
     * Cria uma nova conta financeira
     *
     * @param array $dados Dados da conta
     * @return array ['success' => bool, 'message' => string, 'id' => int|null]
     */
    public function criar(array $dados): array
    {
        try {
            // Validações
            if (empty($dados['nome_conta'])) {
                return [
                    'success' => false,
                    'message' => 'O nome da conta é obrigatório.',
                    'id' => null
                ];
            }

            if (empty($dados['id_admin'])) {
                return [
                    'success' => false,
                    'message' => 'ID do administrador é obrigatório.',
                    'id' => null
                ];
            }

            // Processar valores monetários
            if (isset($dados['saldo_inicial']) && is_string($dados['saldo_inicial'])) {
                $dados['saldo_inicial'] = $this->parseMoeda($dados['saldo_inicial']);
            }

            $id = $this->contaRepository->criar($dados);
            
            return [
                'success' => true,
                'message' => 'Conta cadastrada com sucesso!',
                'id' => $id
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao criar conta: ' . $e->getMessage(),
                'id' => null
            ];
        }
    }

    /**
     * Atualiza uma conta financeira
     *
     * @param int $id ID da conta
     * @param int $idAdmin ID do administrador
     * @param array $dados Dados a atualizar
     * @return array ['success' => bool, 'message' => string]
     */
    public function atualizar(int $id, int $idAdmin, array $dados): array
    {
        try {
            if ($id <= 0) {
                return [
                    'success' => false,
                    'message' => 'ID inválido.'
                ];
            }

            // Validações
            if (empty($dados['nome_conta'])) {
                return [
                    'success' => false,
                    'message' => 'O nome da conta é obrigatório.'
                ];
            }

            // Processar valores monetários
            if (isset($dados['saldo_inicial']) && is_string($dados['saldo_inicial'])) {
                $dados['saldo_inicial'] = $this->parseMoeda($dados['saldo_inicial']);
            }

            $this->contaRepository->atualizar($id, $idAdmin, $dados);
            
            return [
                'success' => true,
                'message' => 'Conta atualizada com sucesso!'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao atualizar conta: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Exclui uma conta financeira
     *
     * @param int $id ID da conta
     * @param int $idAdmin ID do administrador
     * @return array ['success' => bool, 'message' => string]
     */
    public function excluir(int $id, int $idAdmin): array
    {
        if ($id <= 0) {
            return [
                'success' => false,
                'message' => 'ID inválido.'
            ];
        }

        try {
            $this->contaRepository->excluir($id, $idAdmin);
            
            return [
                'success' => true,
                'message' => 'Conta excluída com sucesso!'
            ];
        } catch (PDOException $e) {
            // Erro de constraint (conta tem lançamentos vinculados)
            return [
                'success' => false,
                'message' => 'Erro: existem lançamentos financeiros vinculados a esta conta.'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao excluir conta.'
            ];
        }
    }

    /**
     * Busca contas disponíveis para lançamentos rápidos
     *
     * @param int $idAdmin ID do administrador
     * @return array
     */
    public function buscarContasParaLancamento(int $idAdmin): array
    {
        return $this->contaRepository->buscarContasParaLancamento($idAdmin);
    }

    /**
     * Calcula o saldo total de todas as contas ativas
     *
     * @param int $idAdmin ID do administrador
     * @return float
     */
    public function calcularSaldoTotal(int $idAdmin): float
    {
        return $this->contaRepository->calcularSaldoTotal($idAdmin);
    }

    /**
     * Formata o saldo total em reais
     *
     * @param int $idAdmin ID do administrador
     * @return string
     */
    public function getSaldoTotalFormatado(int $idAdmin): string
    {
        $saldo = $this->calcularSaldoTotal($idAdmin);
        $prefixo = $saldo >= 0 ? 'R$ ' : '-R$ ';
        return $prefixo . number_format(abs($saldo), 2, ',', '.');
    }

    /**
     * Salva uma conta (cria ou atualiza automaticamente)
     *
     * @param array $dados Dados da conta
     * @param int $idAdmin ID do administrador
     * @return array ['success' => bool, 'message' => string, 'id' => int|null]
     */
    public function salvar(array $dados, int $idAdmin): array
    {
        try {
            // Validações
            if (empty($dados['nome_conta'])) {
                return [
                    'success' => false,
                    'message' => 'O nome da conta é obrigatório.',
                    'id' => null
                ];
            }

            // Processar valores monetários
            if (isset($dados['saldo_inicial']) && is_string($dados['saldo_inicial'])) {
                $dados['saldo_inicial'] = $this->parseMoeda($dados['saldo_inicial']);
            }

            $id = $this->contaRepository->salvar($dados, $idAdmin);
            
            $isEdicao = !empty($dados['id']) && (int)$dados['id'] > 0;
            $mensagem = $isEdicao ? 'Conta atualizada com sucesso!' : 'Conta cadastrada com sucesso!';
            
            return [
                'success' => true,
                'message' => $mensagem,
                'id' => $id
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao salvar conta: ' . $e->getMessage(),
                'id' => null
            ];
        }
    }

    /**
     * Busca totais e estatísticas das contas
     *
     * @param int $idAdmin ID do administrador
     * @return array
     */
    public function buscarTotais(int $idAdmin): array
    {
        return $this->contaRepository->buscarTotais($idAdmin);
    }

    /**
     * Converte string de moeda para float
     *
     * @param string $valor Valor em formato brasileiro (R$ 1.234,56)
     * @return float
     */
    private function parseMoeda(string $valor): float
    {
        // Remove R$, espaços e pontos de milhar
        $valor = str_replace(['R$', ' ', '.'], '', $valor);
        // Substitui vírgula por ponto
        $valor = str_replace(',', '.', $valor);
        
        return (float)$valor;
    }
}
