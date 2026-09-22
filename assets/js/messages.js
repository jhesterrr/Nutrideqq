// Simple polling + renderer for staff message list
(function () {
    const POLL_INTERVAL_MS = 5000; // adjust as needed
    const containerId = 'modal-messages-container'; // element where messages will be rendered
    const endpoint = 'get_messages.php?ajax=1'; // relative path — works on any host/subdirectory

    function renderMessages(messages) {
        const container = document.getElementById(containerId);
        if (!container) return;

        if (!messages || messages.length === 0) {
            container.innerHTML = `
                <div class="no-data-message">
                    <i class="fas fa-comments"></i>
                    <h3>No Messages Found</h3>
                    <p>You have no messages from clients.</p>
                </div>`;
            return;
        }

        // build HTML (replace, do not append)
        let html = '<div class="modal-messages-list">';
        messages.forEach(m => {
            const unreadClass = m.read_status == 0 ? 'unread' : '';
            const avatar = (m.client_name || '').substring(0,2).toUpperCase();
            const time = new Date(m.created_at).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
            html += `
                <div class="modal-message-item ${unreadClass}" data-message-id="${m.id}">
                    <div class="message-avatar">${avatar}</div>
                    <div class="message-details">
                        <div class="message-header">
                            <div class="message-client">${escapeHtml(m.client_name || '')}</div>
                            <div class="message-time">${time}</div>
                        </div>
                        <div class="message-preview">${escapeHtml(m.message || '')}</div>
                        ${m.read_status == 0 ? '<div class="message-status unread-badge">Unread</div>' : ''}
                    </div>
                </div>`;
        });
        html += '</div>';
        container.innerHTML = html;
    }

    function showError(msg) {
        const container = document.getElementById(containerId);
        if (!container) return;
        container.innerHTML = `
            <div class="no-data-message">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Could not load messages</h3>
                <p>${escapeHtml(msg || 'An error occurred.')}</p>
            </div>`;
    }

    function fetchAndRender() {
        fetch(endpoint, { credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('Server returned ' + res.status);
                }
                return res.json();
            })
            .then(function (data) {
                // Handle bare array (legacy) or {success, messages} object
                if (Array.isArray(data)) {
                    renderMessages(data);
                } else if (data && data.success === true) {
                    renderMessages(data.messages || []);
                } else {
                    // Server returned success:false — show friendly error instead of stuck loader
                    showError(data && data.message ? data.message : 'Unable to retrieve messages.');
                }
            })
            .catch(function (err) {
                showError('Network error. Retrying…');
            });
    }

    function escapeHtml(text) {
        return String(text)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#39;');
    }

    // initial fetch and interval
    document.addEventListener('DOMContentLoaded', function () {
        fetchAndRender();
        setInterval(fetchAndRender, POLL_INTERVAL_MS);
    });
})();