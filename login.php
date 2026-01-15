<?php
require_once 'vendor/autoload.php';
require_once 'config/configuracoes.php'; // Mantendo por enquanto para constantes globais como URL_BASE

use App\Core\Database;
use App\Infrastructure\Repository\PDOUserRepository;
use App\Application\Service\AuthService;
use App\Security\RateLimiter;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica cookies de login
$email_cookie = $_COOKIE['lembrar_email'] ?? '';
$senha_cookie = isset($_COOKIE['lembrar_senha']) ? base64_decode($_COOKIE['lembrar_senha']) : '';

$erro = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    // Captura o checkbox
    $manter_conectado = isset($_POST['manter_conectado']);

    $ip = RateLimiter::getClientIp();
    $rateLimiter = new RateLimiter();

    if ($rateLimiter->check($ip)) {
        $erro = "Muitas tentativas de login. Por favor, aguarde 15 minutos.";
    } elseif (!empty($email) && !empty($senha)) {
        try {
            // Inicialização da Arquitetura
            $db = Database::getInstance();
            $userRepo = new PDOUserRepository($db);
            $authService = new AuthService($userRepo);

            // Logica de Login
            $user = $authService->login($email, $senha);
            
            // Sucesso: Limpar tentativas
            $rateLimiter->clear($ip);
            
            $authService->createSession($user);

            // Gerenciamento de Cookies (View/Controller Logic)
            if ($manter_conectado) {
                setcookie('lembrar_email', $email, time() + (86400 * 30), "/"); // 30 dias
                setcookie('lembrar_senha', base64_encode($senha), time() + (86400 * 30), "/"); 
            } else {
                setcookie('lembrar_email', '', time() - 3600, "/");
                setcookie('lembrar_senha', '', time() - 3600, "/");
            }
            
            header("Location: dashboard.php");
            exit;

        } catch (Exception $e) {
            // Falha: Registrar tentativa
            $rateLimiter->registerFailure($ip);
            $erro = "Erro: " . $e->getMessage();
        }
    } else {
        $erro = "Preencha todos os campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | ZIIPVET Sistema Veterinário</title>
    
    <!-- FONTES (Mesmas do Dashboard) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- ÍCONES -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="css/login.css">
</head>
<body>

    <div class="login-wrapper">
        <!-- LADO ESQUERDO: MARCA -->
        <div class="brand-side">
            <div class="circle c1"></div>
            <div class="circle c2"></div>
            
            <div class="brand-content">
                <i class="fas fa-paw brand-icon"></i>
                <h1 class="brand-title">ZIIPVET</h1>
                <p class="brand-subtitle">Gestão Veterinária Inteligente</p>
                <div style="margin-top: 30px; font-size: 14px; opacity: 0.7;">
                    <p><i class="fas fa-check"></i> Controle de Clientes</p>
                    <p><i class="fas fa-check"></i> Prontuário Eletrônico</p>
                    <p><i class="fas fa-check"></i> Gestão Financeira</p>
                </div>
            </div>
        </div>

        <!-- LADO DIREITO: LOGIN -->
        <div class="form-side">
            <div class="form-header">
                <h2>Seja bem-vindo</h2>
                <p>Acesse sua conta para continuar</p>
            </div>

            <?php if ($erro): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $erro ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="input-box">
                    <input type="email" name="email" value="<?= htmlspecialchars($email_cookie) ?>" placeholder="Seu e-mail" required autofocus>
                    <i class="fas fa-envelope"></i>
                </div>

                <div class="input-box">
                    <input type="password" name="senha" id="inputSenha" value="<?= htmlspecialchars($senha_cookie) ?>" placeholder="Sua senha" required>
                    <i class="fas fa-lock"></i>
                    <i class="far fa-eye toggle-pass" onclick="toggleSenha()"></i>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #555; font-size: 14px;">
                        <input type="checkbox" name="manter_conectado" <?= !empty($email_cookie) ? 'checked' : '' ?>>
                        Manter conectado
                    </label>

                    <div class="forgot-pass" style="margin-bottom: 0;">
                        <a href="#">Esqueceu a senha?</a>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    Acessar Sistema <i class="fas fa-arrow-right" style="margin-left: 8px;"></i>
                </button>
            </form>

            <div class="footer-copy">
                &copy; <?= date('Y') ?> ZiipVet v2.2<br>Todos os direitos reservados.
            </div>
        </div>
    </div>

    <script>
        function toggleSenha() {
            const input = document.getElementById('inputSenha');
            const icon = document.querySelector('.toggle-pass');
            if (input.type === "password") {
                input.type = "text";
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = "password";
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>