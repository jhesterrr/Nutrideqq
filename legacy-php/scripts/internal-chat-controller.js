/**
 * InternalChatController - CLARITY V103
 * Elite Standard for Staff-Admin communications
 */
class ChatController {
    constructor(userId, userRole, threadId = null) {
        this.userId = userId;
        this.userRole = userRole;
        this.currentThreadId = threadId;
        this.pollingInterval = null;
        this.init();
    }

    init() {
        this.bindUI();
        this.initForm();
        if (this.currentThreadId) {
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
                if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); this.sendMessage(); }
            };
        }

        if (this.attachBtn && this.fileInput) {
            this.attachBtn.onclick = () => this.fileInput.click();
            this.fileInput.onchange = () => { if (this.fileInput.files.length > 0) this.sendMessage(); };
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
            console.log("Fetching internal messages for thread:", this.currentThreadId);
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
                const isMe = msg.type === 'sent';
                let content = `<div class="message-text" style="color:${isMe ? 'white' : '#1a1a1a'} !important;">${this.escapeHtml(msg.message)}</div>`;

                if (msg.message_type === 'image' && msg.attachment_path) {
                    content = `<div class="message-text"><img src="${msg.attachment_path}" class="chat-img-zoomable" alt="Attachment" style="max-width:100%; border-radius:12px; cursor:zoom-in;"></div>`;
                } else if (msg.message_type === 'file' && msg.attachment_path) {
                    content = `<div class="message-text clinical-file-msg"><a href="${msg.attachment_path}" target="_blank" style="color:${isMe ? 'white' : '#2e8b57'}; font-weight:600;"><i class="fas fa-file-pdf"></i> View clinical report</a></div>`;
                }

                const html = `
                    <div class="message-wrapper ${isMe ? 'sent' : 'received'}" id="msg-${msg.id}" style="margin-bottom:15px; width:100%; display:flex; flex-direction:${isMe ? 'row-reverse' : 'row'};">
                        <div class="message-bubble" style="background:${isMe ? '#2E8B57' : '#f1f1f1'}; color:${isMe ? 'white' : '#1a1a1a'} !important; padding:12px 18px; border-radius:18px; max-width:80%; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                            <div style="font-size:0.75rem; color:${isMe ? 'rgba(255,255,255,0.8)' : '#2e8b57'}; margin-bottom:4px; font-weight:700;">${msg.sender_name}</div>
                            ${content}
                            <div class="msg-time" style="font-size:0.65rem; opacity:0.6; text-align:right; margin-top:5px; color:${isMe ? 'rgba(255,255,255,0.7)' : '#666'};">${msg.pretty_time}</div>
                        </div>
                    </div>
                `;
                this.chatMessages.insertAdjacentHTML('beforeend', html);
                added = true;
            }
        });

        if (added) { this.scrollToBottom(); this.bindUI(); }
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

        if (this.inputArea) this.inputArea.value = '';
        if (this.fileInput) this.fileInput.value = '';

        try {
            const endpoint = `handlers/send_internal_message.php`;
            const res = await fetch(endpoint, { method: 'POST', body: formData });
            if (!res.ok) throw new Error(`HTTP: ${res.status}`);
            const data = await res.json();
            if (data.success) { 
                this.renderMessages([data.message]); 
                if (this.inputArea) this.inputArea.value = '';
                if (this.fileInput) this.fileInput.value = '';
            } else {
                alert("Server Error: " + (data.message || "Unknown error"));
            }
        } catch (e) { 
            console.error('Send error:', e);
            alert("Communication Error: " + e.message); 
        }
    }

    scrollToBottom() { if (this.chatMessages) this.chatMessages.scrollTop = this.chatMessages.scrollHeight; }

    escapeHtml(t) {
        const d = document.createElement('div');
        d.textContent = t;
        const out = d.innerHTML.replace(/\n/g, '<br>');
        return out;
    }

    initLightbox() {
        if (!document.getElementById('lightboxOverlay')) {
            const lb = document.createElement('div');
            lb.id = 'lightboxOverlay';
            lb.className = 'lightbox-overlay';
            lb.style.cssText = "position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.9);z-index:9999;display:none;align-items:center;justify-content:center;backdrop-filter:blur(8px);cursor:zoom-out;";
            lb.innerHTML = '<img id="lightboxContentInternal" style="max-width:90%;max-height:90%;border-radius:12px;box-shadow:0 30px 60px rgba(0,0,0,0.5);transform:scale(0.9);transition:transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);" src="">';
            document.body.appendChild(lb);
            lb.onclick = () => { lb.style.display = 'none'; document.getElementById('lightboxContentInternal').style.transform = 'scale(0.9)'; };
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
