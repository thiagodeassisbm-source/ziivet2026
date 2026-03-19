<?php
/**
 * ZIIPVET - Meu Perfil (Usuário logado)
 */
require_once 'auth.php';
require_once 'config/configuracoes.php';

use App\Utils\Csrf;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;
$id_usuario = $_SESSION['usuario_id'] ?? 0;

$msg_feedback = '';
$status_feedback = '';
$dados = [
    'nome' => '',
    'email' => '',
];

if (!$id_usuario) {
    die("<script>alert('Sessão inválida. Faça login novamente.'); window.location.href='login.php';</script>");
}

// ==============================================================
// POST: atualizar dados do usuário logado
// ==============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nome = trim((string)($_POST['nome'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $nova_senha = (string)($_POST['senha_nova'] ?? '');
        $senha_confirmacao = (string)($_POST['senha_confirmacao'] ?? '');

        if ($nome === '') {
            throw new Exception('Nome é obrigatório.');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('E-mail inválido.');
        }

        $params = [
            ':nome' => $nome,
            ':email' => $email,
            ':id' => (int)$id_usuario,
            ':id_admin' => (int)$id_admin,
        ];

        $sql = "UPDATE usuarios SET nome = :nome, email = :email";

        if (trim($nova_senha) !== '') {
            if ($nova_senha !== $senha_confirmacao) {
                throw new Exception('As senhas não conferem.');
            }
            if (mb_strlen($nova_senha) < 6) {
                throw new Exception('A senha deve ter pelo menos 6 caracteres.');
            }
            $params[':senha'] = password_hash($nova_senha, PASSWORD_DEFAULT);
            $sql .= ", senha = :senha";
        }

        $sql .= " WHERE id = :id AND id_admin = :id_admin";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $msg_feedback = 'Dados atualizados com sucesso!';
        $status_feedback = 'success';

        // Recarrega dados
        $stmt2 = $pdo->prepare("SELECT nome, email FROM usuarios WHERE id = ? AND id_admin = ? LIMIT 1");
        $stmt2->execute([(int)$id_usuario, (int)$id_admin]);
        $dados = $stmt2->fetch(PDO::FETCH_ASSOC) ?: $dados;
    } catch (Exception $e) {
        $msg_feedback = $e->getMessage();
        $status_feedback = 'error';
    }
}

// ==============================================================
// GET: carregar dados atuais
// ==============================================================
try {
    $stmt = $pdo->prepare("SELECT nome, email FROM usuarios WHERE id = ? AND id_admin = ? LIMIT 1");
    $stmt->execute([(int)$id_usuario, (int)$id_admin]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC) ?: $dados;
} catch (Throwable $e) {
    // Mantém valores default
}

$titulo_pagina = 'Meu Perfil';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?> | ZiipVet</title>

    <link href="https://fonts.googleapis.com/css2?family=Exo:wght@300;400;600;700;800&family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/menu.css">
    <link rel="stylesheet" href="css/header.css">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .card-perfil {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-top: 3px solid var(--cor-principal);
            margin-bottom: 30px;
        }
        .page-header-title {
            font-size: 26px;
            margin-bottom: 25px;
            color: #444;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header-title i {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--cor-principal), #8e44ad);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-size: 13px; font-weight: 700; color: #2c3e50; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-group input { padding: 12px 16px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; outline: none; background: #fff; transition: all 0.3s ease; }
        .form-group input:focus { border-color: var(--cor-principal); box-shadow: 0 0 0 3px rgba(98, 37, 153, 0.1); }
        .btn-salvar { background: var(--cor-principal); color: #fff; border: none; padding: 15px 35px; border-radius: 8px; font-weight: 700; font-size: 14px; cursor: pointer; text-transform: uppercase; margin-top: 20px; }
        .alert-inline { padding: 12px 14px; border-radius: 10px; margin-bottom: 18px; font-weight: 700; }
        .alert-success { background: #e7f3ff; color: #1f4a7a; border-left: 4px solid #2f80ed; }
        .alert-error { background: #ffe9e9; color: #8a1f1f; border-left: 4px solid #dc3545; }
        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <aside class="sidebar-container">
        <?php include 'menu/menulateral.php'; ?>
    </aside>
    <header class="top-header">
        <?php include 'menu/faixa.php'; ?>
    </header>

    <main class="main-content">
        <h2 class="page-header-title">
            <i class="fas fa-user-cog"></i>
            <?= htmlspecialchars($titulo_pagina) ?>
        </h2>

        <div class="card-perfil">
            <?php if ($msg_feedback !== ''): ?>
                <div class="alert-inline <?= $status_feedback === 'success' ? 'alert-success' : 'alert-error' ?>">
                    <?= htmlspecialchars($msg_feedback) ?>
                </div>
            <?php endif; ?>

            <form action="meu_perfil.php" method="POST">
                <?= Csrf::getInput(); ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Nome</label>
                        <input type="text" name="nome" value="<?= htmlspecialchars($dados['nome'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>E-mail</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($dados['email'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Nova senha (opcional)</label>
                        <input type="password" name="senha_nova" placeholder="Deixe em branco para não alterar">
                    </div>
                    <div class="form-group">
                        <label>Confirmar senha</label>
                        <input type="password" name="senha_confirmacao" placeholder="Confirme a nova senha">
                    </div>
                </div>

                <button class="btn-salvar" type="submit">
                    <i class="fas fa-save" style="margin-right:8px;"></i> Salvar
                </button>
            </form>
        </div>
    </main>
</body>
</html>

