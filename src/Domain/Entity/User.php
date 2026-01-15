<?php

namespace App\Domain\Entity;

class User
{
    private int $id;
    private string $nome;
    private string $email;
    private string $passwordHash;
    private int $idAdmin;
    private bool $ativo;
    private bool $acessoSistema;

    public function __construct(
        int $id,
        string $nome,
        string $email,
        string $passwordHash,
        int $idAdmin,
        bool $ativo,
        bool $acessoSistema
    ) {
        $this->id = $id;
        $this->nome = $nome;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->idAdmin = $idAdmin;
        $this->ativo = $ativo;
        $this->acessoSistema = $acessoSistema;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    public function getIdAdmin(): int
    {
        return $this->idAdmin;
    }

    public function isAtivo(): bool
    {
        return $this->ativo;
    }

    public function temAcessoSistema(): bool
    {
        return $this->acessoSistema;
    }
}
