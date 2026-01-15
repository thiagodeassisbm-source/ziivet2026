<?php

namespace App\Application\Service;

use App\Infrastructure\Repository\VendaRepository;
use App\Core\Database;
use Exception;
use PDO;
use App\Utils\Sanitizer;
use App\Application\Service\AuditService;

class VendaService
{
    private VendaRepository $vendaRepository;
    private Database $db;
    private AuditService $auditService;

    public function __construct(VendaRepository $vendaRepository, Database $db, AuditService $auditService)
    {
        $this->vendaRepository = $vendaRepository;
        $this->db = $db;
        $this->auditService = $auditService;
    }

    /**
     * Lista vendas com paginação
     *
     * @param int $idAdmin ID do administrador
     * @param array $filtros Filtros opcionais
     * @param int $pagina Página atual
     * @param int $itensPorPagina Itens por página
     * @return array
     */
    public function listarPaginado(
        int $idAdmin,
        array $filtros = [],
        int $pagina = 1,
        int $itensPorPagina = 20
    ): array {
        $offset = ($pagina - 1) * $itensPorPagina;
        
        $totalRegistros = $this->vendaRepository->contar($idAdmin, $filtros);
        $totalPaginas = ceil($totalRegistros / $itensPorPagina);
        $vendas = $this->vendaRepository->listar($idAdmin, $filtros, $offset, $itensPorPagina);

        return [
            'vendas' => $vendas,
            'total_registros' => $totalRegistros,
            'total_paginas' => $totalPaginas,
            'pagina_atual' => $pagina
        ];
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
        return $this->vendaRepository->buscarPorId($id, $idAdmin);
    }

    /**
     * Fecha uma venda (cria venda com itens e processa pagamento)
     *
     * @param array $dados Dados da venda
     * @return array ['success' => bool, 'message' => string, 'id' => int|null]
     */
    public function fecharVenda(array $dados): array
    {
        $dados = Sanitizer::clean($dados);
        $conn = $this->db->getConnection();
        
        try {
            $conn->beginTransaction();
            
            // Validações
            if (empty($dados['itens'])) {
                throw new Exception("Nenhum item adicionado à venda.");
            }

            if (empty($dados['id_admin'])) {
                throw new Exception("ID do administrador é obrigatório.");
            }

            $isOrcamento = ($dados['tipo'] === 'Orçamento');
            $statusPagamento = (!$isOrcamento && $dados['acao_btn'] === 'receber') ? 'PAGO' : 'PENDENTE';

            // 1. Criar venda
            $dadosVenda = [
                'id_admin' => $dados['id_admin'],
                'usuario_vendedor' => $dados['usuario_vendedor'] ?? 'Sistema',
                'id_cliente' => $dados['id_cliente'] ?? null,
                'id_paciente' => $dados['id_paciente'] ?? null,
                'data_venda' => $dados['data'] ?? date('Y-m-d'),
                'data_validade' => $isOrcamento ? ($dados['data_validade'] ?? null) : null,
                'tipo_movimento' => $dados['tipo'],
                'tipo_venda' => !$isOrcamento ? ($dados['tipo_venda'] ?? null) : null,
                'valor_total' => $dados['total_geral'],
                'observacoes' => $dados['obs'] ?? null,
                'status_pagamento' => $statusPagamento
            ];

            $idVenda = $this->vendaRepository->criar($dadosVenda);

            // 2. Adicionar itens e baixar estoque
            foreach ($dados['itens'] as $item) {
                $this->vendaRepository->adicionarItem($idVenda, [
                    'id_produto' => $item['id'],
                    'quantidade' => $item['qtd'],
                    'valor_unitario' => $item['valor'],
                    'valor_total' => $item['total']
                ]);

                // Baixar estoque (apenas se não for orçamento)
                if (!$isOrcamento) {
                    $this->baixarEstoque($item['id'], $item['qtd']);
                }
            }

            // 3. Processar pagamento (se for venda paga)
            if (!$isOrcamento && $statusPagamento === 'PAGO') {
                $this->processarPagamento($idVenda, $dados);
            }

            $mensagem = $isOrcamento ? 'Orçamento salvo com sucesso!' : 'Venda realizada com sucesso!';
            
            // 4. Registrar Logs de Auditoria
            try {
                $this->auditService->log(
                    $isOrcamento ? 'CREATE_ORCAMENTO' : 'CREATE_VENDA',
                    'VENDA',
                    $idVenda,
                    [
                        'valor_total' => $dados['total_geral'],
                        'id_cliente' => $dados['id_cliente'] ?? null,
                        'itens_count' => count($dados['itens'])
                    ]
                );
            } catch (Exception $e) {
                // Não bloqueia a transação se o log falhar
            }

            $conn->commit();

            return [
                'success' => true,
                'message' => $mensagem,
                'id' => $idVenda
            ];

        } catch (Exception $e) {
            $conn->rollBack();
            return [
                'success' => false,
                'message' => 'Erro ao processar venda: ' . $e->getMessage(),
                'id' => null
            ];
        }
    }

    /**
     * Calcula troco
     *
     * @param float $valorTotal Valor total da venda
     * @param float $valorPago Valor pago pelo cliente
     * @return array ['troco' => float, 'troco_formatado' => string]
     */
    public function calcularTroco(float $valorTotal, float $valorPago): array
    {
        $troco = $valorPago - $valorTotal;
        
        return [
            'troco' => $troco,
            'troco_formatado' => 'R$ ' . number_format($troco, 2, ',', '.'),
            'necessita_troco' => $troco > 0
        ];
    }

    /**
     * Baixa estoque de um produto
     *
     * @param int $idProduto ID do produto
     * @param float $quantidade Quantidade a baixar
     * @return bool
     */
    private function baixarEstoque(int $idProduto, float $quantidade): bool
    {
        $conn = $this->db->getConnection();
        
        $sql = "UPDATE produtos 
                SET estoque_inicial = estoque_inicial - ? 
                WHERE id = ? AND monitorar_estoque = 1";
        
        $stmt = $conn->prepare($sql);
        return $stmt->execute([$quantidade, $idProduto]);
    }

    /**
     * Processa pagamento da venda
     *
     * @param int $idVenda ID da venda
     * @param array $dados Dados do pagamento
     * @return void
     */
    private function processarPagamento(int $idVenda, array $dados): void
    {
        $conn = $this->db->getConnection();
        
        // Buscar nome do cliente
        $nomeCliente = 'Consumidor Final';
        if (!empty($dados['id_cliente'])) {
            $stmt = $conn->prepare("SELECT nome FROM clientes WHERE id = ?");
            $stmt->execute([$dados['id_cliente']]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cliente) {
                $nomeCliente = $cliente['nome'];
            }
        }

        // Calcular valores com taxa
        $valorBruto = $dados['total_geral'];
        $valorLiquido = $valorBruto;
        $valorTaxaDescontada = 0;
        
        $taxaAplicada = $dados['taxa_aplicada'] ?? '0%';
        $percentualTaxa = 0;
        
        if (preg_match('/(\d+(?:\.\d+)?)%/', $taxaAplicada, $matches)) {
            $percentualTaxa = floatval($matches[1]);
        }
        
        if ($percentualTaxa > 0) {
            $valorTaxaDescontada = ($valorBruto * $percentualTaxa) / 100;
            $valorLiquido = $valorBruto - $valorTaxaDescontada;
        }

        // Atualizar saldo da conta financeira
        $this->atualizarSaldoConta($dados, $valorLiquido);

        // Criar lançamento financeiro
        $qtdParcelas = $dados['qtd_parcelas'] ?? 1;
        $nomeFormaPagamento = $dados['nome_forma_pagamento'] ?? 'Não informada';
        
        $descricaoLancamento = "Venda PDV #$idVenda";
        if ($qtdParcelas > 1) {
            $descricaoLancamento .= " - {$qtdParcelas}x";
        }
        if ($percentualTaxa > 0) {
            $descricaoLancamento .= " | Taxa: {$taxaAplicada} (R$ " . number_format($valorTaxaDescontada, 2, ',', '.') . ")";
            $descricaoLancamento .= " | Bruto: R$ " . number_format($valorBruto, 2, ',', '.') . " → Líquido: R$ " . number_format($valorLiquido, 2, ',', '.');
        }

        $idContaFinanceira = $this->determinarContaDestino($dados);
        $idCaixa = $dados['caixa_ativo'] ?? null;

        $sqlLancamento = "INSERT INTO lancamentos (
            id_admin, tipo, categoria, descricao, documento, fornecedor_cliente, 
            id_venda, data_vencimento, data_pagamento, valor, parcela_atual, 
            total_parcelas, forma_pagamento, id_conta_financeira, status, 
            id_caixa_referencia, data_cadastro
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sqlLancamento);
        $stmt->execute([
            $dados['id_admin'],
            'ENTRADA',
            'VENDAS',
            $descricaoLancamento,
            (string)$idVenda,
            $nomeCliente,
            $idVenda,
            $dados['data'] ?? date('Y-m-d'),
            $valorLiquido,
            1,
            $qtdParcelas,
            $nomeFormaPagamento,
            $idContaFinanceira,
            'PAGO',
            $idCaixa
        ]);
    }

    /**
     * Atualiza saldo da conta financeira
     *
     * @param array $dados Dados do pagamento
     * @param float $valor Valor a adicionar
     * @return void
     */
    private function atualizarSaldoConta(array $dados, float $valor): void
    {
        $conn = $this->db->getConnection();
        
        $idContaDestino = $this->determinarContaDestino($dados);
        
        if ($idContaDestino) {
            $stmt = $conn->prepare("SELECT saldo_inicial FROM contas_financeiras WHERE id = ?");
            $stmt->execute([$idContaDestino]);
            $saldoAtual = $stmt->fetchColumn();
            
            $novoSaldo = $saldoAtual + $valor;
            
            $stmt = $conn->prepare("UPDATE contas_financeiras SET saldo_inicial = ?, data_saldo = ? WHERE id = ?");
            $stmt->execute([$novoSaldo, date('Y-m-d'), $idContaDestino]);
        }
    }

    /**
     * Determina conta financeira destino
     *
     * @param array $dados Dados do pagamento
     * @return int|null
     */
    private function determinarContaDestino(array $dados): ?int
    {
        $conn = $this->db->getConnection();
        
        $idFormaBase = $dados['forma_pagamento'] ?? null;
        
        if (!$idFormaBase) {
            return null;
        }

        $stmt = $conn->prepare("SELECT tipo, configuracoes FROM formas_pagamento WHERE id = ?");
        $stmt->execute([$idFormaBase]);
        $forma = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$forma) {
            return null;
        }

        // Se for espécie, buscar conta do caixa
        if ($forma['tipo'] === 'Espécie') {
            $idCaixa = $dados['caixa_ativo'] ?? null;
            if ($idCaixa) {
                $stmt = $conn->prepare("
                    SELECT u.id_conta_caixa 
                    FROM caixas c 
                    INNER JOIN usuarios u ON c.id_usuario = u.id 
                    WHERE c.id = ?
                ");
                $stmt->execute([$idCaixa]);
                return $stmt->fetchColumn() ?: null;
            }
        } else {
            // Buscar conta da configuração da forma de pagamento
            if (!empty($forma['configuracoes'])) {
                $config = json_decode($forma['configuracoes'], true);
                return $config['id_conta_destino'] ?? null;
            }
        }

        return null;
    }

    /**
     * Busca estatísticas de vendas
     *
     * @param int $idAdmin ID do administrador
     * @param array $filtros Filtros opcionais
     * @return array
     */
    public function buscarEstatisticas(int $idAdmin, array $filtros = []): array
    {
        return $this->vendaRepository->buscarEstatisticas($idAdmin, $filtros);
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
        return $this->vendaRepository->buscarProdutosMaisVendidos($idAdmin, $limit);
    }
}
