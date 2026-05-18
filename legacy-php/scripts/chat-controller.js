/**
 * ChatController
 * Standard Elite Communication for NutriDeq
 */
class ChatController {
    constructor(userId, userRole, contactId = null) {
        this.userId = userId;
        this.userRole = userRole;
        this.contactId = contactId;
        this.pollingInterval = null;

        // DOM
        this.chatMessages = document.getElementById('chatMessages');
        this.inputArea = document.getElementById('messageInput') || document.querySelector('.chat-input');
        this.attachBtn = document.getElementById('attachBtn');
        this.fileInput = document.getElementById('fileInput');

        this.init();
    }

    init() {
        if (this.contactId) {
            this.fetchMessages();
            this.startPolling();
        }

        // Enter to Send (Keyboard)
        if (this.inputArea) {
            this.inputArea.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });
        }
        
        // Button to Send (Form Submission Prevent Default)
        const messageForm = document.getElementById('messageForm');
        if (messageForm) {
            messageForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.sendMessage();
            });
        }

        // Attachments
        if (this.attachBtn) this.attachBtn.onclick = () => this.fileInput.click();
        if (this.fileInput) {
            this.fileInput.onchange = () => {
                if (this.fileInput.files.length > 0) this.sendMessage();
            };
        }

        // Global Lightbox
        this.initLightbox();
    }

    startPolling() {
        // Change to a safer setTimeout to avoid stacking requests if the server is slow
        this.pollingTimeout = setTimeout(() => this.poll(), 3000);
    }

    async poll() {
        await this.fetchMessages();
        this.pollingTimeout = setTimeout(() => this.poll(), 3000);
    }

    async fetchMessages() {
        if (!this.contactId) return;
        try {
            console.log("Fetching messages for contact:", this.contactId);
            const controller = new AbortController();
            const id = setTimeout(() => controller.abort(), 8000); // 8 second timeout

            const res = await fetch(`${BASE_URL}handlers/get_messages.php?contact_id=${this.contactId}`, {
                signal: controller.signal
            });
            clearTimeout(id);

            const data = await res.json();
            console.log("Message data received:", data);
            
            // Clean up ANY loading indicators in the container
            if (this.chatMessages) {
                const spinners = this.chatMessages.querySelectorAll('.fa-spinner, .fa-spin');
                spinners.forEach(s => {
                    if (s.parentElement) s.parentElement.remove();
                });
            }

            if (data.success) {
                this.renderMessages(data.messages);
            } else {
                console.error("Server returned error:", data.error);
            }
        } catch (e) {
            console.error("Chat Network Error:", e);
        }
    }

    renderMessages(messages) {
        if (!this.chatMessages) return;

        let addedNew = false;
        messages.forEach(msg => {
            if (!document.getElementById(`msg-${msg.id}`)) {
                const isMe = msg.type === 'sent';
                let content = `<div class="message-text">${this.escapeHtml(msg.message)}</div>`;

                if (msg.message_type === 'image' && msg.attachment_path) {
                    content = `<div class="message-text"><img src="${msg.attachment_path}" class="chat-img-zoomable" alt="Attachment"></div>`;
                } else if (msg.message_type === 'file' && msg.attachment_path) {
                    content = `<div class="message-text clinical-file-msg"><a href="${msg.attachment_path}" target="_blank"><i class="fas fa-file-pdf"></i> ${msg.file_name}</a></div>`;
                }

                const html = `
                    <div class="message-wrapper ${isMe ? 'sent' : 'received'}" id="msg-${msg.id}">
                        <div class="message-bubble">
                            ${content}
                            <div class="msg-time">${msg.pretty_time}</div>
                        </div>

                    </div>
                `;
                this.chatMessages.insertAdjacentHTML('beforeend', html);
                addedNew = true;
            }
        });
        
        // Only trigger scroll if we actually painted a new message to prevent jitter
        if (addedNew) {
            this.scrollToBottom();
        }
    }

    async sendMessage() {
        const text = this.inputArea.value.trim();
        const file = this.fileInput ? this.fileInput.files[0] : null;
        if (!text && !file) return;

        const formData = new FormData();
        formData.append('recipient_id', this.contactId);
        formData.append('message', text);
        if (file) formData.append('attachment', file);

        this.inputArea.value = '';
        if (this.fileInput) this.fileInput.value = '';

        try {
            const res = await fetch(`${BASE_URL}handlers/send_message_ajax.php`, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                this.renderMessages([data.message]);
            } else {
                alert("Failed to send: " + data.error);
            }
        } catch (e) { alert("Communication error."); }
    }

    scrollToBottom() {
        if (this.chatMessages) {
            this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
            // Mobile fallback: If the container isn't locally scrolling, scroll the entire document to the bottom.
            window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
        }
    }

    escapeHtml(t) {
        const d = document.createElement('div');
        d.textContent = t;
        return d.innerHTML.replace(/\n/g, '<br>');
    }

    initLightbox() {
        if (!document.getElementById('lightboxOverlay')) {
            const lb = document.createElement('div');
            lb.id = 'lightboxOverlay';
            lb.className = 'lightbox-overlay';
            lb.style.cssText = "position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.9);z-index:9999;display:none;align-items:center;justify-content:center;backdrop-filter:blur(8px);cursor:zoom-out;";
            lb.innerHTML = '<img id="lightboxContent" style="max-width:90%;max-height:90%;border-radius:12px;box-shadow:0 30px 60px rgba(0,0,0,0.5);transform:scale(0.9);transition:transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);" src="">';
            document.body.appendChild(lb);
            lb.onclick = () => {
                 lb.style.display = 'none';
                 lb.querySelector('img').style.transform = 'scale(0.9)';
            };
        }

        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('chat-img-zoomable')) {
                const lb = document.getElementById('lightboxOverlay');
                const lbImg = document.getElementById('lightboxContent');
                lbImg.src = e.target.src;
                lb.style.display = 'flex';
                setTimeout(() => { lbImg.style.transform = 'scale(1)'; }, 10);
            }
        });
    }
}
