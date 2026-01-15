<?php
/**
 * ZIIPVET - FAIXA SUPERIOR (SEM TÍTULO)
 * Versão: 2.1.0
 */
?>

<header class="faixa-superior">
    <!-- Visualizador de Áudio -->
    <div id="audio-visualizer-container" style="display:none;">
        <canvas id="audio-waves" width="300" height="40"></canvas>
        <span id="status-voz">🎤 Fale agora...</span>
    </div>

    <!-- Busca Global -->
    <div class="faixa-busca-global" id="container-busca-global">
        <div class="search-input-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Procurar no sistema...">
            <span class="kb-shortcut">Ctrl K</span>
        </div>
    </div>

    <!-- Ações -->
    <div class="faixa-acoes">
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

        <!-- Perfil -->
        <div class="usuario-perfil">
            <div class="user-text">
                <strong><?php echo $_SESSION['nome_usuario'] ?? 'Usuário'; ?></strong>
                <span>Administrador</span>
            </div>
            <div class="user-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
        </div>
    </div>
</header>

<!-- JavaScript do reconhecimento de voz mantido igual -->
<script src="js/voice-recognition.js"></script>