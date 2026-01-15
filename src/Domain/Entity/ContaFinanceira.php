<?php

namespace App\Domain\Entity;

use DateTime;

class ContaFinanceira
{
    private int $id;
    private int $idAdmin;
    private string $nomeConta;
    private ?string $tipoConta;
    private string $status;
    private bool $permitirLancamentos;
    private float $saldoInicial;
    private ?DateTime $dataSaldo;
    private string $situacaoSaldo;
    private ?DateTime $createdAt;
    private ?DateTime $updatedAt;

    public function __construct(
        int $id,
        int $idAdmin,
        string $nomeConta,
        ?string $tipoConta = null,
        string $status = 'Ativo',
        bool $permitirLancamentos = false,
        float $saldoInicial = 0.00,
        ?DateTime $dataSaldo = null,
        string $situacaoSaldo = 'Positivo',
        ?DateTime $createdAt = null,
        ?DateTime $updatedAt = null
    ) {
        $this->id = $id;
        $this->idAdmin = $idAdmin;
        $this->nomeConta = $nomeConta;
        $this->tipoConta = $tipoConta;
        $this->status = $status;
        $this->permitirLancamentos = $permitirLancamentos;
        $this->saldoInicial = $saldoInicial;
        $this->dataSaldo = $dataSaldo;
        $this->situacaoSaldo = $situacaoSaldo;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
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

    public function getNomeConta(): string
    {
        return $this->nomeConta;
    }

    public function getTipoConta(): ?string
    {
        return $this->tipoConta;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isAtiva(): bool
    {
        return $this->status === 'Ativo';
    }

    public function permiteLancamentos(): bool
    {
        return $this->permitirLancamentos;
    }

    public function getSaldoInicial(): float
    {
        return $this->saldoInicial;
    }

    public function getDataSaldo(): ?DateTime
    {
        return $this->dataSaldo;
    }

    public function getSituacaoSaldo(): string
    {
        return $this->situacaoSaldo;
    }

    public function isSaldoPositivo(): bool
    {
        return $this->situacaoSaldo === 'Positivo';
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    /**
     * Cria uma instância de ContaFinanceira a partir de um array
     *
     * @param array $data Dados da conta financeira
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)($data['id'] ?? 0),
            idAdmin: (int)($data['id_admin'] ?? 0),
            nomeConta: $data['nome_conta'] ?? '',
            tipoConta: $data['tipo_conta'] ?? null,
            status: $data['status'] ?? 'Ativo',
            permitirLancamentos: (bool)($data['permitir_lancamentos'] ?? false),
            saldoInicial: (float)($data['saldo_inicial'] ?? 0.00),
            dataSaldo: !empty($data['data_saldo']) ? new DateTime($data['data_saldo']) : null,
            situacaoSaldo: $data['situacao_saldo'] ?? 'Positivo',
            createdAt: !empty($data['created_at']) ? new DateTime($data['created_at']) : null,
            updatedAt: !empty($data['updated_at']) ? new DateTime($data['updated_at']) : null
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
            'nome_conta' => $this->nomeConta,
            'tipo_conta' => $this->tipoConta,
            'status' => $this->status,
            'permitir_lancamentos' => $this->permitirLancamentos,
            'saldo_inicial' => $this->saldoInicial,
            'data_saldo' => $this->dataSaldo?->format('Y-m-d'),
            'situacao_saldo' => $this->situacaoSaldo,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Retorna o saldo formatado em reais
     *
     * @return string
     */
    public function getSaldoFormatado(): string
    {
        $prefixo = $this->isSaldoPositivo() ? 'R$ ' : '-R$ ';
        return $prefixo . number_format(abs($this->saldoInicial), 2, ',', '.');
    }

    /**
     * Retorna o tipo de conta formatado
     *
     * @return string
     */
    public function getTipoFormatado(): string
    {
        return $this->tipoConta ?? 'Não especificado';
    }
}
