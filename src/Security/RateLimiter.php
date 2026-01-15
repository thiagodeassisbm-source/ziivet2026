<?php

namespace App\Security;

use App\Core\Database;
use PDO;

/**
 * =========================================================================================
 * ZIIPVET - RATE LIMITER (BRUTE FORCE PROTECTION)
 * CLASSE: RateLimiter
 * DESCRIÇÃO: Controla tentativas de login por IP
 * =========================================================================================
 */
class RateLimiter
{
    private PDO $pdo;
    private const MAX_ATTEMPTS = 5;
    private const LOCK_TIME_MINUTES = 15;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Verifica se o IP está bloqueado
     * 
     * @param string $ip
     * @return bool True se bloqueado, False caso contrário
     */
    public function check(string $ip): bool
    {
        $sql = "SELECT attempts, last_attempt_at FROM login_attempts WHERE ip_address = :ip";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':ip' => $ip]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        $attempts = (int)$row['attempts'];
        $lastAttempt = strtotime($row['last_attempt_at']);
        $isRecentlyActive = (time() - $lastAttempt) < (self::LOCK_TIME_MINUTES * 60);

        if ($attempts >= self::MAX_ATTEMPTS && $isRecentlyActive) {
            return true;
        }

        // Se o tempo de bloqueio já passou, resetar o contador na próxima falha ou limpar agora
        if (!$isRecentlyActive && $attempts >= self::MAX_ATTEMPTS) {
            $this->clear($ip);
            return false;
        }

        return false;
    }

    /**
     * Registra uma falha de tentativa de login
     * 
     * @param string $ip
     * @return void
     */
    public function registerFailure(string $ip): void
    {
        $sql = "INSERT INTO login_attempts (ip_address, attempts, last_attempt_at) 
                VALUES (:ip, 1, CURRENT_TIMESTAMP) 
                ON DUPLICATE KEY UPDATE 
                attempts = CASE 
                    WHEN TIMESTAMPDIFF(MINUTE, last_attempt_at, CURRENT_TIMESTAMP) >= :lock_time THEN 1 
                    ELSE attempts + 1 
                END,
                last_attempt_at = CURRENT_TIMESTAMP";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':ip' => $ip,
            ':lock_time' => self::LOCK_TIME_MINUTES
        ]);
    }

    /**
     * Limpa o registro de falhas de um IP (após sucesso)
     * 
     * @param string $ip
     * @return void
     */
    public function clear(string $ip): void
    {
        $sql = "DELETE FROM login_attempts WHERE ip_address = :ip";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':ip' => $ip]);
    }

    /**
     * Obtém o IP do cliente de forma segura
     * 
     * @return string
     */
    public static function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
    }
}
