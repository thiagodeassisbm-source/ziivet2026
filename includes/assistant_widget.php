<!-- TATA ASSISTANT WIDGET -->
<?php $path_prefix = $path_prefix ?? ''; ?>
<link rel="stylesheet" href="<?= $path_prefix ?>css/assistant.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div id="tata-chat-widget">
    <!-- Botão Flutuante -->
    <button id="tata-chat-btn" class="tata-chat-button" title="Falar com Tata">
        <i class="fas fa-comment-dots"></i>
        <span class="badge">1</span>
    </button>

    <!-- Janela de Chat -->
    <div id="tata-chat-window" class="tata-chat-window">
        <!-- Header -->
        <div class="tata-header">
            <div class="tata-header-info">
                <div class="tata-avatar">
                   <i class="fas fa-robot"></i>
                </div>
                <div class="tata-name-box">
                    <h3>Tata</h3>
                    <div class="tata-status">Assistente ZiipVet</div>
                </div>
            </div>
            <div style="display: flex; gap: 15px; align-items: center;">
                <div class="tata-finish" id="tata-finish-chat" title="Finalizar Chat" style="cursor: pointer; font-size: 14px; opacity: 0.7; transition: 0.3s;">
                    <i class="fas fa-check-double"></i> Finalizar
                </div>
                <div class="tata-close" id="tata-close-chat">
                    <i class="fas fa-times"></i>
                </div>
            </div>
        </div>

        <!-- Mensagens -->
        <div class="tata-messages" id="tata-messages">
            <div class="tata-msg bot">
                <div class="msg-text">
                    Olá! Eu sou a <strong>Tata</strong>, sua assistente virtual do ZiipVet. 🐾 
                    <br><br>
                    Estou aqui para tirar todas as suas dúvidas sobre o sistema. Como posso te orientar hoje?
                </div>
                <span class="tata-msg-time"><?= date('H:i') ?></span>
            </div>
        </div>

        <!-- Input -->
        <div class="tata-input-area">
            <input type="text" id="tata-input" placeholder="Digite sua dúvida aqui..." autocomplete="off">
            <div style="display:flex; gap:5px;">
                <button id="tata-restart-chat" class="tata-send-btn" style="background:#ddd; color:#555;" title="Reiniciar Conversa">
                    <i class="fas fa-trash-alt"></i>
                </button>
                <button id="tata-send" class="tata-send-btn">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $path_prefix ?>js/assistant.js"></script>
