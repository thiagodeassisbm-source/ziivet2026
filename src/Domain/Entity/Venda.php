<?php

namespace App\Domain\Entity;

use DateTime;

class Venda
{
    private int $id;
    private int $idAdmin;
    private string $usuarioVendedor;
    private ?int $idCliente;
    private ?int $idPaciente;
    private DateTime $dataVenda;
    private ?DateTime $dataValidade;
    private string $tipoMovimento; // 'Venda' ou 'Orçamento'
    private ?string $tipoVenda; // 'À vista', 'Parcelado', etc
    private float $valorTotal;
    private ?string $observacoes;
    private string $statusPagamento; // 'PAGO', 'PENDENTE'
    private array $itens; // Array de VendaItem
    private ?DateTime $dataCadastro;

    public function __construct(
        int $id,
        int $idAdmin,
        string $usuarioVendedor,
        ?int $idCliente,
        ?int $idPaciente,
        DateTime $dataVenda,
        ?DateTime $dataValidade,
        string $tipoMovimento,
        ?string $tipoVenda,
        float $valorTotal,
        ?string $observacoes = null,
        string $statusPagamento = 'PENDENTE',
        array $itens = [],
        ?DateTime $dataCadastro = null
    ) {
        $this->id = $id;
        $this->idAdmin = $idAdmin;
        $this->usuarioVendedor = $usuarioVendedor;
        $this->idCliente = $idCliente;
        $this->idPaciente = $idPaciente;
        $this->dataVenda = $dataVenda;
        $this->dataValidade = $dataValidade;
        $this->tipoMovimento = $tipoMovimento;
        $this->tipoVenda = $tipoVenda;
        $this->valorTotal = $valorTotal;
        $this->observacoes = $observacoes;
        $this->statusPagamento = $statusPagamento;
        $this->itens = $itens;
        $this->dataCadastro = $dataCadastro ?? new DateTime();
    }

    // Getters
    public function getId(): int
    {
        return $this->id;
    }

    public function getIdAdmin(): int
    {
        return $this->idAdmin;
    }

    public function getUsuarioVendedor(): string
    {
        return $this->usuarioVendedor;
    }

    public function getIdCliente(): ?int
    {
        return $this->idCliente;
    }

    public function getIdPaciente(): ?int
    {
        return $this->idPaciente;
    }

    public function getDataVenda(): DateTime
    {
        return $this->dataVenda;
    }

    public function getDataValidade(): ?DateTime
    {
        return $this->dataValidade;
    }

    public function getTipoMovimento(): string
    {
        return $this->tipoMovimento;
    }

    public function getTipoVenda(): ?string
    {
        return $this->tipoVenda;
    }

    public function getValorTotal(): float
    {
        return $this->valorTotal;
    }

    public function getObservacoes(): ?string
    {
        return $this->observacoes;
    }

    public function getStatusPagamento(): string
    {
        return $this->statusPagamento;
    }

    public function getItens(): array
    {
        return $this->itens;
    }

    public function getDataCadastro(): ?DateTime
    {
        return $this->dataCadastro;
    }

    // Métodos de negócio
    public function isOrcamento(): bool
    {
        return $this->tipoMovimento === 'Orçamento';
    }

    public function isVenda(): bool
    {
        return $this->tipoMovimento === 'Venda';
    }

    public function isPago(): bool
    {
        return $this->statusPagamento === 'PAGO';
    }

    public function isPendente(): bool
    {
        return $this->statusPagamento === 'PENDENTE';
    }

    public function isVencido(): bool
    {
        if (!$this->dataValidade) {
            return false;
        }

        $hoje = new DateTime();
        return $hoje > $this->dataValidade && $this->isPendente();
    }

    public function getQuantidadeItens(): int
    {
        return count($this->itens);
    }

    public function getValorFormatado(): string
    {
        return 'R$ ' . number_format($this->valorTotal, 2, ',', '.');
    }

    /**
     * Cria uma instância de Venda a partir de um array
     *
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)($data['id'] ?? 0),
            idAdmin: (int)($data['id_admin'] ?? 0),
            usuarioVendedor: $data['usuario_vendedor'] ?? '',
            idCliente: !empty($data['id_cliente']) ? (int)$data['id_cliente'] : null,
            idPaciente: !empty($data['id_paciente']) ? (int)$data['id_paciente'] : null,
            dataVenda: !empty($data['data_venda']) ? new DateTime($data['data_venda']) : new DateTime(),
            dataValidade: !empty($data['data_validade']) ? new DateTime($data['data_validade']) : null,
            tipoMovimento: $data['tipo_movimento'] ?? 'Venda',
            tipoVenda: $data['tipo_venda'] ?? null,
            valorTotal: (float)($data['valor_total'] ?? 0.00),
            observacoes: $data['observacoes'] ?? null,
            statusPagamento: $data['status_pagamento'] ?? 'PENDENTE',
            itens: $data['itens'] ?? [],
            dataCadastro: !empty($data['data_cadastro']) ? new DateTime($data['data_cadastro']) : null
        );
    }

    /**
     * Converte a entidade para array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'id_admin' => $this->idAdmin,
            'usuario_vendedor' => $this->usuarioVendedor,
            'id_cliente' => $this->idCliente,
            'id_paciente' => $this->idPaciente,
            'data_venda' => $this->dataVenda->format('Y-m-d'),
            'data_validade' => $this->dataValidade?->format('Y-m-d'),
            'tipo_movimento' => $this->tipoMovimento,
            'tipo_venda' => $this->tipoVenda,
            'valor_total' => $this->valorTotal,
            'observacoes' => $this->observacoes,
            'status_pagamento' => $this->statusPagamento,
            'quantidade_itens' => $this->getQuantidadeItens(),
            'data_cadastro' => $this->dataCadastro?->format('Y-m-d H:i:s'),
        ];
    }
}

/**
 * Classe auxiliar para representar um item da venda
 */
class VendaItem
{
    private int $id;
    private int $idVenda;
    private int $idProduto;
    private string $nomeProduto;
    private float $quantidade;
    private float $valorUnitario;
    private float $valorTotal;

    public function __construct(
        int $id,
        int $idVenda,
        int $idProduto,
        string $nomeProduto,
        float $quantidade,
        float $valorUnitario,
        float $valorTotal
    ) {
        $this->id = $id;
        $this->idVenda = $idVenda;
        $this->idProduto = $idProduto;
        $this->nomeProduto = $nomeProduto;
        $this->quantidade = $quantidade;
        $this->valorUnitario = $valorUnitario;
        $this->valorTotal = $valorTotal;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getIdVenda(): int
    {
        return $this->idVenda;
    }

    public function getIdProduto(): int
    {
        return $this->idProduto;
    }

    public function getNomeProduto(): string
    {
        return $this->nomeProduto;
    }

    public function getQuantidade(): float
    {
        return $this->quantidade;
    }

    public function getValorUnitario(): float
    {
        return $this->valorUnitario;
    }

    public function getValorTotal(): float
    {
        return $this->valorTotal;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)($data['id'] ?? 0),
            idVenda: (int)($data['id_venda'] ?? 0),
            idProduto: (int)($data['id_produto'] ?? 0),
            nomeProduto: $data['nome_produto'] ?? '',
            quantidade: (float)($data['quantidade'] ?? 0),
            valorUnitario: (float)($data['valor_unitario'] ?? 0.00),
            valorTotal: (float)($data['valor_total'] ?? 0.00)
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'id_venda' => $this->idVenda,
            'id_produto' => $this->idProduto,
            'nome_produto' => $this->nomeProduto,
            'quantidade' => $this->quantidade,
            'valor_unitario' => $this->valorUnitario,
            'valor_total' => $this->valorTotal,
        ];
    }
}
