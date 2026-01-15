<?php

namespace App\Infrastructure\Repository;

use App\Core\Database;
use App\Domain\Entity\AuditLog;
use PDO;

/**
 * Repositório para persistência de Logs de Auditoria
 */
class AuditLogRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Registra um novo log de auditoria no banco de dados
     * 
     * @param AuditLog $log
     * @return bool
     */
    public function registrar(AuditLog $log): bool
    {
        $conn = $this->db->getConnection();
        $sql = "INSERT INTO audit_logs (user_id, action, entity, entity_id, details) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        return $stmt->execute([
            $log->getUserId(),
            $log->getAction(),
            $log->getEntity(),
            $log->getEntityId(),
            $log->getDetails() ? json_encode($log->getDetails(), JSON_UNESCAPED_UNICODE) : null
        ]);
    }
}
