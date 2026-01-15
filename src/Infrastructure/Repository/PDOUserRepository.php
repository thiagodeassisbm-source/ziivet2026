<?php

namespace App\Infrastructure\Repository;

use App\Core\Database;
use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use PDO;

class PDOUserRepository implements UserRepositoryInterface
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findByEmail(string $email): ?User
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new User(
            (int)$row['id'],
            $row['nome'],
            $row['email'],
            $row['senha'], // DB column is 'senha'
            (int)$row['id_admin'],
            (bool)$row['ativo'],
            (bool)$row['acesso_sistema']
        );
    }
}
