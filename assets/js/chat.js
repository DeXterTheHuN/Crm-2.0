// Chat Scripts - chat.php
// userId és userName változókat a PHP adja át

let lastMessageId = 0;
let isLoadingMessages = false;

// Üzenetek betöltése
function loadMessages() {
    if (isLoadingMessages) return;
    isLoadingMessages = true;

    fetch('chat_api.php?action=get_messages&last_id=' + lastMessageId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.messages.length > 0) {
                const chatMessages = document.getElementById('chatMessages');
                const wasAtBottom = chatMessages.scrollHeight - chatMessages.scrollTop <= chatMessages.clientHeight + 100;

                data.messages.forEach(msg => {
                    const msgId = parseInt(msg.id);
                    if (msgId > lastMessageId) {
                        lastMessageId = msgId;
                        appendMessage(msg);
                    }
                });

                if (wasAtBottom) {
                    scrollToBottom();
                }

                markAsRead();
            }
            isLoadingMessages = false;
        })
        .catch(error => {
            console.error('Hiba az üzenetek betöltésekor:', error);
            isLoadingMessages = false;
        });
}

// Üzenet hozzáadása a chat-hez
function appendMessage(msg) {
    const chatMessages = document.getElementById('chatMessages');

    if (chatMessages.querySelector('.text-center')) {
        chatMessages.innerHTML = '';
    }

    const isOwn = parseInt(msg.user_id) === parseInt(userId);
    const messageDiv = document.createElement('div');
    messageDiv.className = 'message ' + (isOwn ? 'own' : 'other');

    const time = new Date(msg.created_at).toLocaleTimeString('hu-HU', { hour: '2-digit', minute: '2-digit' });

    messageDiv.innerHTML = `
        ${!isOwn ? `<div class="message-sender">${escapeHtml(msg.user_name)}</div>` : ''}
        <div class="message-bubble">
            ${escapeHtml(msg.message)}
            <div class="message-time">${time}</div>
        </div>
    `;

    chatMessages.appendChild(messageDiv);
}

// Üzenet küldése
function sendMessage() {
    const input = document.getElementById('messageInput');
    const message = input.value.trim();

    if (!message) return;

    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('message', message);

    fetch('chat_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            input.style.height = 'auto';
            loadMessages();
        } else {
            alert('Hiba az üzenet küldésekor: ' + (data.error || 'Ismeretlen hiba'));
        }
    })
    .catch(error => {
        console.error('Hiba:', error);
        alert('Hiba az üzenet küldésekor');
    });
}

// Görgetés az aljára
function scrollToBottom() {
    const chatMessages = document.getElementById('chatMessages');
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Üzenetek olvasottnak jelölése
function markAsRead() {
    if (lastMessageId > 0) {
        fetch('chat_api.php?action=mark_read&last_id=' + lastMessageId);
    }
}

// HTML escape
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Inicializálás
document.addEventListener('DOMContentLoaded', function() {
    const messageInput = document.getElementById('messageInput');
    
    // Auto-resize textarea
    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });

    loadMessages();
if (typeof EventSource !== 'undefined') {
    const evtSource = new EventSource('sse_notifications.php');
    evtSource.onmessage = (e) => {
        const data = JSON.parse(e.data);
        updateNotificationBadge(data.count);
    };
} else {
    setInterval(checkNotifications, 3000);
}

});

// Oldalról való távozáskor jelöljük olvasottnak
window.addEventListener('beforeunload', markAsRead);
