<?php

namespace App\Application\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use Exception;

class AuthService
{
    private UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Authenticates a user.
     * 
     * @param string $email
     * @param string $password
     * @throws Exception If authentication fails
     * @return User
     */
    public function login(string $email, string $password): User
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            throw new Exception("E-mail ou senha incorretos.");
        }

        if (!$user->verifyPassword($password)) {
            throw new Exception("E-mail ou senha incorretos.");
        }

        if (!$user->isAtivo() || !$user->temAcessoSistema()) {
            throw new Exception("Seu acesso está desativado.");
        }

        return $user;
    }

    /**
     * Sets session variables for the authenticated user.
     */
    public function createSession(User $user): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(0, '/');
            session_start();
        }

        $_SESSION['usuario_id'] = $user->getId();
        $_SESSION['usuario_nome'] = $user->getNome();
        $_SESSION['id_admin'] = $user->getIdAdmin();
    }
}
