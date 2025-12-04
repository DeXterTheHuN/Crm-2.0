// CRM Main Scripts - index.php

let lastChatCheck = new Date().toISOString();
let shownPatchnotes = new Set();

// Toast értesítés megjelenítése
function showToast(title, message, type = 'info', link = null) {
    const toastId = 'toast-' + Date.now();
    const bgClass = {
        'info': 'bg-primary',
        'success': 'bg-success',
        'warning': 'bg-warning',
        'danger': 'bg-danger'
    }[type] || 'bg-primary';

    const toastHTML = `
        <div class="toast" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header ${bgClass} text-white">
                <i class="bi bi-bell-fill me-2"></i>
                <strong class="me-auto">${title}</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body ${link ? 'cursor-pointer' : ''}" ${link ? `onclick="window.location.href='${link}'"` : ''}>
                ${message}
                ${link ? '<div class="mt-2"><small class="text-muted"><i class="bi bi-hand-index"></i> Kattints a megnyitáshoz</small></div>' : ''}
            </div>
        </div>
    `;

    const container = document.getElementById('toastContainer');
    container.insertAdjacentHTML('beforeend', toastHTML);

    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 5000 });
    toast.show();

    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

// Értesítések ellenőrzése
function checkNotifications() {
    fetch('notifications_api.php?action=get_counts')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Chat badge
                const chatBadge = document.getElementById('chatBadge');
                if (data.chat_unread > 0) {
                    chatBadge.textContent = data.chat_unread;
                    chatBadge.style.display = 'inline-block';
                } else {
                    chatBadge.style.display = 'none';
                }

                // Approvals badge (admin only)
                const approvalsBadge = document.getElementById('approvalsBadge');
                if (approvalsBadge && data.approvals_pending > 0) {
                    approvalsBadge.textContent = data.approvals_pending;
                    approvalsBadge.style.display = 'inline-block';
                } else if (approvalsBadge) {
                    approvalsBadge.style.display = 'none';
                }

                // New clients by county
                data.new_clients_by_county.forEach(county => {
                    const badge = document.querySelector(`.new-client-badge[data-county-id="${county.county_id}"]`);
                    if (badge && county.new_count > 0) {
                        badge.textContent = county.new_count + ' új';
                        badge.style.display = 'inline-block';
                    }
                });
            }
        });

    // Legújabb chat üzenet ellenőrzése (toast-hoz)
    fetch(`notifications_api.php?action=get_latest_chat_message&last_check=${encodeURIComponent(lastChatCheck)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.has_new) {
                showToast(
                    'Új üzenet',
                    `${data.message.user_name}: ${data.message.message}`,
                    'info',
                    'chat.php'
                );
                lastChatCheck = data.message.created_at;
            }
        });

    // Approval notifications (ügyintézőknek)
    const myRequestsBadge = document.getElementById('myRequestsBadge');
    if (myRequestsBadge) {
        fetch('approval_notifications_api.php?action=get_unread')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.count > 0) {
                    myRequestsBadge.textContent = data.count;
                    myRequestsBadge.style.display = 'inline-block';

                    data.notifications.forEach(notif => {
                        if (notif.approval_status === 'approved') {
                            showToast(
                                'Ügyfél Elfogadva',
                                `Az ügyfél "${notif.client_name}" jóváhagyásra került!`,
                                'success',
                                'my_requests.php'
                            );
                        } else if (notif.approval_status === 'rejected') {
                            showToast(
                                'Ügyfél Elutasítva',
                                `Az ügyfél "${notif.client_name}" elutasításra került. Indok: ${notif.rejection_reason}`,
                                'danger',
                                'my_requests.php'
                            );
                        }

                        fetch('approval_notifications_api.php?action=mark_read', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: `notification_id=${notif.id}`
                        });
                    });
                } else if (myRequestsBadge) {
                    myRequestsBadge.style.display = 'none';
                }
            });
    }

    // Patchnotes olvasatlan bejegyzések
    fetch('patchnotes_api.php?action=get_unread_count')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.unread_count > 0) {
                const patchnotesLink = document.getElementById('patchnotesLink');
                if (!patchnotesLink.querySelector('.notification-dot')) {
                    const dot = document.createElement('span');
                    dot.className = 'notification-dot patchnotes';
                    patchnotesLink.appendChild(dot);
                }
            }
        });

    // Legújabb major patchnote popup
    fetch('patchnotes_api.php?action=get_latest_unread')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.patchnote && !shownPatchnotes.has(data.patchnote.id)) {
                shownPatchnotes.add(data.patchnote.id);
                showPatchnotePopup(data.patchnote);
            }
        });
}

// Patchnote popup megjelenítése
function showPatchnotePopup(patchnote) {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-megaphone-fill"></i> Új frissítés: v${escapeHtml(patchnote.version)}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h4>${escapeHtml(patchnote.title)}</h4>
                    <p style="white-space: pre-wrap;">${escapeHtml(patchnote.content)}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Bezárás</button>
                    <a href="patchnotes.php" class="btn btn-success">Tovább a változásnaplóhoz</a>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();

    modal.addEventListener('hidden.bs.modal', function() {
        fetch('patchnotes_api.php?action=mark_read&ids=' + patchnote.id);
        modal.remove();
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Inicializálás
document.addEventListener('DOMContentLoaded', function() {
    checkNotifications();
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
