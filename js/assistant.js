/* TATA ASSISTANT - CHAT WIDGET JS - VERSION 3.0 (PERSISTENCE & INACTIVITY) */
document.addEventListener('DOMContentLoaded', function () {
    const chatButton = document.getElementById('tata-chat-btn');
    const chatWindow = document.getElementById('tata-chat-window');
    const closeBtn = document.getElementById('tata-close-chat');
    const finishBtn = document.getElementById('tata-finish-chat');
    const restartBtn = document.getElementById('tata-restart-chat');
    const chatInput = document.getElementById('tata-input');
    const sendBtn = document.getElementById('tata-send');
    const messageArea = document.getElementById('tata-messages');

    let isTyping = false;
    let inactivityTimer = null;
    const INACTIVITY_LIMIT = 2 * 60 * 1000; // 2 minutos

    // Carregar histórico ao iniciar
    loadHistory();

    // Reset Inactivity on user action
    function resetInactivityTimer() {
        clearTimeout(inactivityTimer);
        inactivityTimer = setTimeout(checkInactivity, INACTIVITY_LIMIT);
    }

    function checkInactivity() {
        if (!chatWindow.classList.contains('active')) return;

        typeWriter("Olá? Tem alguém por aí? 👋 Se não tiver mais dúvidas no momento, vou fechar nosso chat para poupar energia. Qualquer coisa é só me chamar de novo!", 'bot');

        // Se após perguntar continuar inativo por mais 30s, fecha
        setTimeout(() => {
            if (isTyping) return;
            chatWindow.classList.remove('active');
        }, 30000);
    }

    // Toggle Chat
    chatButton.addEventListener('click', function () {
        chatWindow.classList.toggle('active');
        if (chatWindow.classList.contains('active')) {
            chatInput.focus();
            resetInactivityTimer();
            const badge = chatButton.querySelector('.badge');
            if (badge) badge.style.display = 'none';
        }
    });

    closeBtn.addEventListener('click', function () {
        chatWindow.classList.remove('active');
    });

    // Finalizar Chat
    finishBtn.addEventListener('click', function () {
        Swal.fire({
            title: 'Finalizar Atendimento?',
            text: 'O histórico atual será arquivado e você poderá iniciar uma nova conversa.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#622599',
            cancelButtonColor: '#ddd',
            confirmButtonText: '<i class="fas fa-check"></i> Sim, finalizar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('api/assistant.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'finish' })
                })
                    .then(() => {
                        messageArea.innerHTML = '';
                        chatWindow.classList.remove('active');
                        Swal.fire({
                            title: 'Concluído!',
                            text: 'Atendimento finalizado com sucesso.',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        setTimeout(() => addInitialMessage(), 500);
                    });
            }
        });
    });

    if (restartBtn) {
        restartBtn.addEventListener('click', function () {
            finishBtn.click();
        });
    }

    function addInitialMessage() {
        typeWriter("Olá! Eu sou a **Tata**, sua assistente virtual. Vamos recomeçar? O que você gostaria de saber sobre o ZiipVet hoje?", 'bot');
    }

    function loadHistory() {
        fetch('api/assistant.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_history' })
        })
            .then(response => response.json())
            .then(data => {
                messageArea.innerHTML = '';
                if (data.history && data.history.length > 0) {
                    data.history.forEach(msg => {
                        renderMessage(msg.text, msg.side, msg.time);
                    });
                } else {
                    renderWelcomeOptions(data.has_previous);
                }
            });
    }

    function renderWelcomeOptions(hasPrevious) {
        const div = document.createElement('div');
        div.className = 'tata-msg bot';

        let optionsHtml = '';
        if (hasPrevious) {
            optionsHtml = `
                <div style="margin-top: 15px; display: flex; flex-direction: column; gap: 10px;">
                    <button id="btn-restore" style="background: #622599; color: white; border: none; padding: 10px 15px; border-radius: 8px; cursor: pointer; font-weight: 600; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <i class="fas fa-history"></i> Continuar conversa anterior
                    </button>
                    <button id="btn-new" style="background: #f0f2ff; color: #622599; border: 1px solid #622599; padding: 10px 15px; border-radius: 8px; cursor: pointer; font-weight: 600; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <i class="fas fa-plus"></i> Iniciar nova conversa
                    </button>
                </div>
            `;
        } else {
            optionsHtml = `
                <div style="margin-top: 15px;">
                    <button id="btn-new-only" style="background: #622599; color: white; border: none; padding: 10px 15px; border-radius: 8px; cursor: pointer; font-weight: 600; width: 100%;">
                        Vamos começar!
                    </button>
                </div>
            `;
        }

        div.innerHTML = `
            <div class="msg-text">
                Olá! Eu sou a <strong>Tata</strong>, sua assistente virtual do ZiipVet. 🐾
                <br><br>
                Como você gostaria de ser atendido hoje?
                ${optionsHtml}
            </div>
            <span class="tata-msg-time">${new Date().getHours().toString().padStart(2, '0')}:${new Date().getMinutes().toString().padStart(2, '0')}</span>
        `;
        messageArea.appendChild(div);
        messageArea.scrollTop = messageArea.scrollHeight;

        if (hasPrevious) {
            document.getElementById('btn-restore').onclick = () => {
                fetch('api/assistant.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'restore_previous' })
                }).then(() => loadHistory());
            };
            document.getElementById('btn-new').onclick = () => {
                startNewChat();
            };
        } else {
            document.getElementById('btn-new-only').onclick = () => {
                startNewChat();
            };
        }
    }

    function startNewChat() {
        messageArea.innerHTML = '';
        typeWriter("Perfeito! Vamos começar do zero. O que você gostaria de saber sobre o ZiipVet agora? Estou pronta para te orientar sobre Clientes, Vendas, IA e muito mais!", 'bot');
    }

    // Send Message
    function sendMessage() {
        if (isTyping) return;
        resetInactivityTimer();

        const text = chatInput.value.trim();
        if (!text) return;

        renderMessage(text, 'user');
        chatInput.value = '';

        showTyping();
        isTyping = true;

        fetch('api/assistant.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'message', message: text })
        })
            .then(response => response.json())
            .then(data => {
                // Simular tempo de "pensamento" (2 segundos)
                setTimeout(() => {
                    hideTyping();
                    typeWriter(data.reply, 'bot');
                }, 2000);
            })
            .catch(() => {
                hideTyping();
                isTyping = false;
            });
    }

    sendBtn.addEventListener('click', sendMessage);
    chatInput.addEventListener('keypress', (e) => (e.key === 'Enter' && sendMessage()));

    function renderMessage(text, side, time = null) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `tata-msg ${side}`;
        if (!time) {
            const now = new Date();
            time = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
        }
        msgDiv.innerHTML = `<div class="msg-text">${text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')}</div><span class="tata-msg-time">${time}</span>`;
        messageArea.appendChild(msgDiv);
        messageArea.scrollTop = messageArea.scrollHeight;
    }

    function typeWriter(text, side) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `tata-msg ${side}`;
        const textCont = document.createElement('div');
        textCont.className = 'msg-text';
        msgDiv.appendChild(textCont);
        const timeSpan = document.createElement('span');
        timeSpan.className = 'tata-msg-time';
        timeSpan.innerText = new Date().getHours().toString().padStart(2, '0') + ':' + new Date().getMinutes().toString().padStart(2, '0');
        msgDiv.appendChild(timeSpan);
        messageArea.appendChild(msgDiv);

        let i = 0;
        const speed = 30; // Velocidade natural de digitação
        isTyping = true;
        function type() {
            if (i < text.length) {
                textCont.innerHTML = text.substring(0, i + 1).replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
                i++;
                messageArea.scrollTop = messageArea.scrollHeight;
                setTimeout(type, speed);
            } else { iTyping = false; isTyping = false; resetInactivityTimer(); }
        }
        type();
    }

    function showTyping() {
        const div = document.createElement('div');
        div.className = 'tata-msg bot typing-indicator';
        div.id = 'tata-typing';
        div.innerHTML = '<span class="typing">Tata está digitando...</span>';
        messageArea.appendChild(div);
        messageArea.scrollTop = messageArea.scrollHeight;
    }

    function hideTyping() {
        const t = document.getElementById('tata-typing');
        if (t) t.remove();
    }
});
