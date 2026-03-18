<?php
/**
 * ZIIPVET - FAIXA SUPERIOR (SEM TÍTULO)
 * Versão: 2.1.0
 */
use App\Utils\Csrf;
echo Csrf::getMetaTag();
$path_prefix = $path_prefix ?? '';
?>
<script src="<?= $path_prefix ?>js/csrf_protection.js"></script>


<header class="faixa-superior">
    <!-- Visualizador de Áudio -->
    <div id="audio-visualizer-container" style="display:none;">
        <canvas id="audio-waves" width="300" height="40"></canvas>
        <span id="status-voz">🎤 Fale agora...</span>
    </div>



    <!-- Ações -->
    <div class="faixa-acoes" style="margin-left: auto;">
        <div class="action-buttons">
            <div class="mic-icon-wrapper">
                <i class="fas fa-microphone btn-mic-global" 
                   id="btnMicGlobal" 
                   title="Ativar Ditado por Voz"></i>
            </div>
            <i class="fas fa-sync-alt" title="Atualizar"></i>
            <div class="notificacao-icon">
                <i class="fas fa-bell" title="Notificações"></i>
                <span class="badge"></span>
            </div>
        </div>

        <!-- Perfil com Dropdown -->
        <div class="perfil-wrapper" style="position: relative;">
            <div class="usuario-perfil" id="btnPerfilDropdown">
                <div class="user-text">
                    <strong><?php echo $_SESSION['nome_usuario'] ?? 'Usuário'; ?></strong>
                    <span>Administrador</span>
                </div>
                <div class="user-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <i class="fas fa-chevron-down" style="font-size: 10px; margin-left: 5px; opacity: 0.7;"></i>
            </div>

            <div class="user-dropdown" id="userDropdownMenu">
                <a href="<?= URL_BASE ?>minha_empresa.php" class="user-dropdown-item">
                    <i class="fas fa-user-cog"></i> Meu Perfil
                </a>
                <div class="user-dropdown-divider"></div>
                <a href="<?= URL_BASE ?>sair.php" class="user-dropdown-item" style="color: #dc3545;">
                    <i class="fas fa-sign-out-alt"></i> Sair do Sistema
                </a>
            </div>
        </div>

        <script>
            document.getElementById('btnPerfilDropdown').addEventListener('click', function(e) {
                e.stopPropagation();
                document.getElementById('userDropdownMenu').classList.toggle('show');
            });

            document.addEventListener('click', function(e) {
                const menu = document.getElementById('userDropdownMenu');
                if (!menu.contains(e.target)) {
                    menu.classList.remove('show');
                }
            });
        </script>
    </div>
</header>

<!-- JavaScript do reconhecimento de voz mantido igual -->
<script src="<?= $path_prefix ?>js/voice-recognition.js"></script>

<!-- ASSISTENTE VIRTUAL TATA -->
<?php include_once __DIR__ . '/../includes/assistant_widget.php'; ?>