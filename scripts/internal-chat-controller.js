/**
 * InternalChatController
 * Elite Standard for Staff-Admin communications
 */
class ChatController {
    constructor(userId, userRole, threadId = null) {
        this.userId = userId;
        this.userRole = userRole;
        this.currentThreadId = threadId;
        this.pollingTimeout = null;
        this.isFirstLoad = true;

        this.init();
    }

    init() {
        this.bindUI();
        this.initForm();
        if (this.currentThreadId) {
            // First jump to bottom if messages exist from PHP
            this.scrollToBottom(true);
            this.fetchMessages();
            this.startPolling();
        }
        this.initLightbox();
    }

    initForm() {
        const form = document.getElementById('messageForm');
        if (form) {
            form.onsubmit = (e) => {
                e.preventDefault();
                this.sendMessage();
            };
        }
    }

    bindUI() {
        this.chatMessages = document.getElementById('chatMessages');
        this.inputArea = document.getElementById('messageInput');
        this.attachBtn = document.getElementById('attachBtn');
        this.fileInput = document.getElementById('fileInput');

        if (this.inputArea) {
            this.inputArea.onkeypress = (e) => {
                if (e.key === 'Enter' && !e.shiftKey) { 
                    e.preventDefault(); 
                    this.sendMessage(); 
                }
            };
        }

        if (this.attachBtn && this.fileInput) {
            this.attachBtn.onclick = () => this.fileInput.click();
            this.fileInput.onchange = () => { 
                if (this.fileInput.files.length > 0) this.sendMessage(); 
            };
        }
    }

    startPolling() {
        if (this.pollingTimeout) clearTimeout(this.pollingTimeout);
        this.pollingTimeout = setTimeout(() => this.poll(), 3000);
    }

    async poll() {
        await this.fetchMessages();
        this.pollingTimeout = setTimeout(() => this.poll(), 3000);
    }

    async fetchMessages() {
        if (!this.currentThreadId) return;
        try {
            const endpoint = `handlers/get_internal_messages.php?thread_id=${this.currentThreadId}`;
            const res = await fetch(endpoint);
            if (!res.ok) throw new Error(`HTTP: ${res.status}`);
            const data = await res.json();
            if (data.success) { 
                this.renderMessages(data.messages); 
            } else {
                console.error("Internal Fetch Error:", data.error);
            }
        } catch (e) {
            console.error('Fetch error:', e);
        }
    }

    renderMessages(messages) {
        if (!this.chatMessages) this.chatMessages = document.getElementById('chatMessages');
        if (!this.chatMessages) return;

        let added = false;
        messages.forEach(msg => {
            if (!document.getElementById(`msg-${msg.id}`)) {
                const isMe = msg.type === 'sent' || (msg.sender_id == this.userId);
                let content = '';

                if (msg.message_type === 'image' && msg.attachment_path) {
                    content += `<div class="message-text" style="margin-bottom:6px;"><img src="${this.escapeAttr(msg.attachment_path)}" class="chat-img-zoomable" alt="Attachment" style="max-width:100%; border-radius:12px; cursor:zoom-in;"></div>`;
                } else if (msg.message_type === 'file' && msg.attachment_path) {
                    content += `<div class="message-text clinical-file-msg" style="margin-bottom:6px;"><a href="${this.escapeAttr(msg.attachment_path)}" target="_blank" style="color:${isMe ? '#ffffff' : '#059669'}; font-weight:600;"><i class="fas fa-file-pdf"></i> View clinical report</a></div>`;
                }

                if (msg.message) {
                    content += `<div>${this.escapeHtml(msg.message)}</div>`;
                }

                const senderHeader = !isMe ? `
                    <div style="font-size:0.75rem; font-weight:700; color:#10b981; margin-bottom:4px; display:flex; align-items:center; gap:6px;">
                        <i class="fas fa-user-md"></i> ${this.escapeHtml(msg.sender_name || 'Staff')}
                    </div>
                ` : '';

                const html = `
                    <div class="msg-row ${isMe ? 'me' : 'them'}" id="msg-${msg.id}">
                        <div class="msg-bubble">
                            ${senderHeader}
                            ${content}
                            <div class="msg-meta">${msg.pretty_time}</div>
                        </div>
                    </div>
                `;
                this.chatMessages.insertAdjacentHTML('beforeend', html);
                added = true;
            }
        });

        if (added || this.isFirstLoad) { 
            this.scrollToBottom(this.isFirstLoad); 
        }
    }

    async sendMessage() {
        if (!this.inputArea) this.bindUI();
        const text = this.inputArea ? this.inputArea.value.trim() : '';
        const file = this.fileInput ? this.fileInput.files[0] : null;
        if (!text && !file) return;

        const formData = new FormData();
        formData.append('thread_id', this.currentThreadId);
        formData.append('message', text);
        if (file) formData.append('attachment', file);

        if (this.inputArea) {
            this.inputArea.value = '';
            this.inputArea.style.height = 'auto';
        }
        if (this.fileInput) this.fileInput.value = '';

        try {
            const endpoint = `handlers/send_internal_message.php`;
            const res = await fetch(endpoint, { method: 'POST', body: formData });
            if (!res.ok) throw new Error(`HTTP: ${res.status}`);
            const data = await res.json();
            if (data.success) { 
                this.renderMessages([data.message]); 
                this.scrollToBottom(true);
            } else {
                alert("Server Error: " + (data.error || data.message || "Unknown error"));
            }
        } catch (e) { 
            console.error('Send error:', e);
            alert("Communication Error: " + e.message); 
        }
    }

    scrollToBottom(force = false) { 
        if (!this.chatMessages) this.chatMessages = document.getElementById('chatMessages');
        if (!this.chatMessages) return;

        const isNearBottom = (this.chatMessages.scrollHeight - this.chatMessages.scrollTop - this.chatMessages.clientHeight) < 120;

        if (force || isNearBottom || this.isFirstLoad) {
            // Immediate jump
            const prevBehavior = this.chatMessages.style.scrollBehavior;
            if (this.isFirstLoad || force) {
                this.chatMessages.style.scrollBehavior = 'auto';
            }
            
            this.chatMessages.scrollTop = this.chatMessages.scrollHeight;

            requestAnimationFrame(() => {
                if (this.chatMessages) this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
            });

            setTimeout(() => {
                if (this.chatMessages) {
                    this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
                    if (this.isFirstLoad || force) {
                        this.chatMessages.style.scrollBehavior = prevBehavior || '';
                    }
                }
            }, 60);

            setTimeout(() => {
                if (this.chatMessages) {
                    this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
                }
            }, 200);
        }

        this.isFirstLoad = false;
    }

    escapeHtml(t) {
        if (!t) return '';
        const d = document.createElement('div');
        d.textContent = t;
        return d.innerHTML.replace(/\n/g, '<br>');
    }

    escapeAttr(t) {
        if (!t) return '';
        return t.replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    initLightbox() {
        if (!document.getElementById('lightboxOverlay')) {
            const lb = document.createElement('div');
            lb.id = 'lightboxOverlay';
            lb.className = 'lightbox-overlay';
            lb.style.cssText = "position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.9);z-index:999999;display:none;align-items:center;justify-content:center;backdrop-filter:blur(8px);cursor:zoom-out;";
            lb.innerHTML = '<img id="lightboxContentInternal" style="max-width:90%;max-height:90%;border-radius:12px;box-shadow:0 30px 60px rgba(0,0,0,0.5);transform:scale(0.9);transition:transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);" src="">';
            document.body.appendChild(lb);
            lb.onclick = () => { 
                lb.style.display = 'none'; 
                document.getElementById('lightboxContentInternal').style.transform = 'scale(0.9)'; 
            };
        }
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('chat-img-zoomable')) {
                const lb = document.getElementById('lightboxOverlay');
                const lbImg = document.getElementById('lightboxContentInternal');
                lbImg.src = e.target.src;
                lb.style.display = 'flex';
                setTimeout(() => { lbImg.style.transform = 'scale(1)'; }, 10);
            }
        });
    }
}
