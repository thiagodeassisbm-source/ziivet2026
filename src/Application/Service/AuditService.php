<?php

namespace App\Application\Service;

use App\Domain\Entity\AuditLog;
use App\Infrastructure\Repository\AuditLogRepository;
use App\Application\Auth\AuthMiddleware;

/**
 * Serviço de Auditoria para registrar eventos do sistema
 */
class AuditService
{
    private AuditLogRepository $auditLogRepository;

    public function __construct(AuditLogRepository $auditLogRepository)
    {
        $this->auditLogRepository = $auditLogRepository;
    }

    /**
     * Atalho para registrar uma ação do usuário atual
     * 
     * @param string $action Ação realizada (ex: 'CREATE', 'UPDATE', 'DELETE')
     * @param string $entity Nome da entidade afetada (ex: 'VENDA', 'CLIENTE')
     * @param int|null $entityId ID da entidade afetada
     * @param array|null $details Detalhes adicionais em array
     * @return bool
     */
    public function log(string $action, string $entity, ?int $entityId = null, ?array $details = null): bool
    {
        $userId = AuthMiddleware::getUsuarioId();
        
        $log = new AuditLog(
            $userId,
            $action,
            $entity,
            $entityId,
            $details
        );

        return $this->auditLogRepository->registrar($log);
    }
}
