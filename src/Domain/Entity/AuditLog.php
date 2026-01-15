<?php

namespace App\Domain\Entity;

/**
 * Entidade para Logs de Auditoria
 */
class AuditLog
{
    private ?int $id;
    private ?int $userId;
    private string $action;
    private string $entity;
    private ?int $entityId;
    private ?array $details;
    private string $createdAt;

    public function __construct(
        ?int $userId,
        string $action,
        string $entity,
        ?int $entityId = null,
        ?array $details = null,
        ?int $id = null,
        string $createdAt = ''
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->action = $action;
        $this->entity = $entity;
        $this->entityId = $entityId;
        $this->details = $details;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int { return $this->id; }
    public function getUserId(): ?int { return $this->userId; }
    public function getAction(): string { return $this->action; }
    public function getEntity(): string { return $this->entity; }
    public function getEntityId(): ?int { return $this->entityId; }
    public function getDetails(): ?array { return $this->details; }
    public function getCreatedAt(): string { return $this->createdAt; }
}
