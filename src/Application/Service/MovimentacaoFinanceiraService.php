<?php

namespace App\Application\Service;

use App\Infrastructure\Repository\ContaFinanceiraRepository;
use Exception;
use PDOException;
use DateTime;

/**
 * Serviço para gerenciar movimentações financeiras (receitas e despesas)
 * Trabalha em conjunto com ContaFinanceiraService
 */
class MovimentacaoFinanceiraService
{
    private ContaFinanceiraRepository $contaRepository;

    public function __construct(ContaFinanceiraRepository $contaRepository)
    {
        $this->contaRepository = $contaRepository;
    }

    /**
     * Lista contas financeiras com regras de negócio aplicadas
     *
     * @param int $idAdmin ID do administrador
     * @param string $busca Termo de busca
     * @param int $pagina Página atual
     * @param int $itensPorPagina Itens por página
     * @return array
     */
    public function listarContas(
        int $idAdmin,
        string $busca = '',
        int $pagina = 1,
        int $itensPorPagina = 20
    ): array {
        $offset = ($pagina - 1) * $itensPorPagina;
        
        $totalRegistros = $this->contaRepository->contar($idAdmin, $busca);
        $totalPaginas = ceil($totalRegistros / $itensPorPagina);
        $contas = $this->contaRepository->listar($idAdmin, $busca, $offset, $itensPorPagina);

        // Aplicar regras de negócio
        foreach ($contas as &$conta) {
            // Calcular saldo atual considerando situação
            $saldoInicial = (float)$conta['saldo_inicial'];
            if ($conta['situacao_saldo'] === 'Negativo') {
                $saldoInicial = -$saldoInicial;
            }
            $conta['saldo_atual'] = $saldoInicial;
            
            // Verificar se conta está com saldo baixo (alerta)
            $conta['alerta_saldo_baixo'] = $saldoInicial < 100 && $saldoInicial > 0;
            
            // Verificar se conta está negativa (crítico)
            $conta['alerta_negativo'] = $saldoInicial < 0;
            
            // Formatar saldo
            $conta['saldo_formatado'] = $this->formatarMoeda($saldoInicial);
        }
        unset($conta);

        return [
            'contas' => $contas,
            'total_registros' => $totalRegistros,
            'total_paginas' => $totalPaginas,
            'pagina_atual' => $pagina
        ];
    }

    /**
     * Registra uma receita (entrada de dinheiro)
     *
     * @param array $dados Dados da receita
     * @return array ['success' => bool, 'message' => string, 'id' => int|null]
     */
    public function registrarReceita(array $dados): array
    {
        try {
            // Validações
            if (empty($dados['id_conta'])) {
                return [
                    'success' => false,
                    'message' => 'Conta de destino é obrigatória.',
                    'id' => null
                ];
            }

            if (empty($dados['valor']) || (float)$dados['valor'] <= 0) {
                return [
                    'success' => false,
                    'message' => 'Valor da receita deve ser maior que zero.',
                    'id' => null
                ];
            }

            if (empty($dados['descricao'])) {
                return [
                    'success' => false,
                    'message' => 'Descrição é obrigatória.',
                    'id' => null
                ];
            }

            // Processar valor
            $valor = $this->parseMoeda($dados['valor']);

            // Preparar dados da movimentação
            $movimentacao = [
                'id_conta' => (int)$dados['id_conta'],
                'id_admin' => (int)$dados['id_admin'],
                'tipo' => 'Receita',
                'descricao' => $dados['descricao'],
                'valor' => $valor,
                'data_movimentacao' => $dados['data'] ?? date('Y-m-d'),
                'categoria' => $dados['categoria'] ?? 'Outras Receitas',
                'observacoes' => $dados['observacoes'] ?? null
            ];

            // TODO: Aqui você criaria um MovimentacaoRepository para salvar
            // Por enquanto, retornamos sucesso simulado
            
            return [
                'success' => true,
                'message' => 'Receita registrada com sucesso!',
                'id' => 1 // TODO: Retornar ID real da movimentação
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao registrar receita: ' . $e->getMessage(),
                'id' => null
            ];
        }
    }

    /**
     * Registra uma despesa (saída de dinheiro)
     *
     * @param array $dados Dados da despesa
     * @return array ['success' => bool, 'message' => string, 'id' => int|null]
     */
    public function registrarDespesa(array $dados): array
    {
        try {
            // Validações
            if (empty($dados['id_conta'])) {
                return [
                    'success' => false,
                    'message' => 'Conta de origem é obrigatória.',
                    'id' => null
                ];
            }

            if (empty($dados['valor']) || (float)$dados['valor'] <= 0) {
                return [
                    'success' => false,
                    'message' => 'Valor da despesa deve ser maior que zero.',
                    'id' => null
                ];
            }

            if (empty($dados['descricao'])) {
                return [
                    'success' => false,
                    'message' => 'Descrição é obrigatória.',
                    'id' => null
                ];
            }

            // Processar valor
            $valor = $this->parseMoeda($dados['valor']);

            // Verificar se conta tem saldo suficiente (regra de negócio)
            $conta = $this->contaRepository->buscarPorId(
                (int)$dados['id_conta'],
                (int)$dados['id_admin']
            );

            if (!$conta) {
                return [
                    'success' => false,
                    'message' => 'Conta não encontrada.',
                    'id' => null
                ];
            }

            $saldoAtual = (float)$conta['saldo_inicial'];
            if ($conta['situacao_saldo'] === 'Negativo') {
                $saldoAtual = -$saldoAtual;
            }

            // Alerta se despesa deixará conta negativa
            $saldoAposDepesa = $saldoAtual - $valor;
            $alertaSaldoNegativo = $saldoAposDepesa < 0;

            // Preparar dados da movimentação
            $movimentacao = [
                'id_conta' => (int)$dados['id_conta'],
                'id_admin' => (int)$dados['id_admin'],
                'tipo' => 'Despesa',
                'descricao' => $dados['descricao'],
                'valor' => $valor,
                'data_movimentacao' => $dados['data'] ?? date('Y-m-d'),
                'categoria' => $dados['categoria'] ?? 'Outras Despesas',
                'observacoes' => $dados['observacoes'] ?? null,
                'alerta_saldo_negativo' => $alertaSaldoNegativo
            ];

            // TODO: Aqui você criaria um MovimentacaoRepository para salvar
            
            $mensagem = 'Despesa registrada com sucesso!';
            if ($alertaSaldoNegativo) {
                $mensagem .= ' Atenção: Esta despesa deixou a conta com saldo negativo.';
            }
            
            return [
                'success' => true,
                'message' => $mensagem,
                'id' => 1, // TODO: Retornar ID real
                'alerta_saldo_negativo' => $alertaSaldoNegativo,
                'saldo_apos_despesa' => $saldoAposDepesa
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao registrar despesa: ' . $e->getMessage(),
                'id' => null
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
    public function excluirConta(int $id, int $idAdmin): array
    {
        if ($id <= 0) {
            return [
                'success' => false,
                'message' => 'ID inválido.'
            ];
        }

        try {
            // Verificar se conta existe
            $conta = $this->contaRepository->buscarPorId($id, $idAdmin);
            
            if (!$conta) {
                return [
                    'success' => false,
                    'message' => 'Conta não encontrada.'
                ];
            }

            // TODO: Verificar se há movimentações vinculadas
            // $temMovimentacoes = $this->movimentacaoRepository->contarPorConta($id);
            // if ($temMovimentacoes > 0) {
            //     return ['success' => false, 'message' => 'Não é possível excluir conta com movimentações.'];
            // }

            $this->contaRepository->excluir($id, $idAdmin);
            
            return [
                'success' => true,
                'message' => 'Conta excluída com sucesso!'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erro: existem movimentações financeiras vinculadas a esta conta.'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao excluir conta.'
            ];
        }
    }

    /**
     * Busca contas disponíveis para lançamentos
     *
     * @param int $idAdmin ID do administrador
     * @return array
     */
    public function buscarContasParaLancamento(int $idAdmin): array
    {
        return $this->contaRepository->buscarContasParaLancamento($idAdmin);
    }

    /**
     * Gera relatório financeiro resumido
     *
     * @param int $idAdmin ID do administrador
     * @return array
     */
    public function gerarRelatorioResumo(int $idAdmin): array
    {
        $totais = $this->contaRepository->buscarTotais($idAdmin);
        
        // Adicionar análises de negócio
        $totais['situacao_financeira'] = $this->analisarSituacaoFinanceira($totais['saldo_total']);
        $totais['recomendacoes'] = $this->gerarRecomendacoes($totais);
        
        return $totais;
    }

    /**
     * Analisa a situação financeira baseada no saldo total
     *
     * @param float $saldoTotal
     * @return string
     */
    private function analisarSituacaoFinanceira(float $saldoTotal): string
    {
        if ($saldoTotal >= 10000) {
            return 'Excelente';
        } elseif ($saldoTotal >= 5000) {
            return 'Boa';
        } elseif ($saldoTotal >= 1000) {
            return 'Regular';
        } elseif ($saldoTotal >= 0) {
            return 'Atenção';
        } else {
            return 'Crítica';
        }
    }

    /**
     * Gera recomendações baseadas nos totais
     *
     * @param array $totais
     * @return array
     */
    private function gerarRecomendacoes(array $totais): array
    {
        $recomendacoes = [];
        
        if ($totais['saldo_total'] < 0) {
            $recomendacoes[] = 'Seu saldo total está negativo. Revise suas despesas urgentemente.';
        }
        
        if ($totais['contas_negativas'] > 0) {
            $recomendacoes[] = "Você tem {$totais['contas_negativas']} conta(s) com saldo negativo.";
        }
        
        if ($totais['saldo_total'] < 1000 && $totais['saldo_total'] >= 0) {
            $recomendacoes[] = 'Seu saldo está baixo. Considere reduzir despesas não essenciais.';
        }
        
        if (empty($recomendacoes)) {
            $recomendacoes[] = 'Sua situação financeira está saudável. Continue assim!';
        }
        
        return $recomendacoes;
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

    /**
     * Converte string de moeda para float
     *
     * @param string|float $valor
     * @return float
     */
    private function parseMoeda($valor): float
    {
        if (is_numeric($valor)) {
            return (float)$valor;
        }
        
        // Remove R$, espaços e pontos de milhar
        $valor = str_replace(['R$', ' ', '.'], '', $valor);
        // Substitui vírgula por ponto
        $valor = str_replace(',', '.', $valor);
        
        return (float)$valor;
    }
}
