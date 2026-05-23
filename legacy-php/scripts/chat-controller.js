/**
 * ChatController
 * Standard Elite Communication for NutriDeq with Reply and Unsend Features
 */
class ChatController {
    constructor(userId, userRole, contactId = null) {
        this.userId = userId;
        this.userRole = userRole;
        this.contactId = contactId;
        this.pollingInterval = null;
        this.replyToId = null;

        // Register globally for reference
        window.chatControllerInstance = this;

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

        // Bind event listeners for reply and unsend buttons inside chatMessages (Event Delegation)
        if (this.chatMessages) {
            this.chatMessages.addEventListener('click', (e) => {
                const replyBtn = e.target.closest('.reply-action-btn');
                if (replyBtn) {
                    const msgId = parseInt(replyBtn.getAttribute('data-id'));
                    const sender = replyBtn.getAttribute('data-sender');
                    const message = replyBtn.getAttribute('data-message');
                    this.setReply(msgId, message, sender);
                    return;
                }

                const unsendBtn = e.target.closest('.unsend-action-btn');
                if (unsendBtn) {
                    const msgId = parseInt(unsendBtn.getAttribute('data-id'));
                    this.unsend(msgId);
                    return;
                }
            });
        }

        // Global Lightbox
        this.initLightbox();
    }

    startPolling() {
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
            const existing = document.getElementById(`msg-${msg.id}`);
            
            if (existing) {
                // Real-time update of unsent status if it changed since last fetch
                if (msg.is_unsent) {
                    const bubble = existing.querySelector('.message-bubble');
                    if (bubble && !bubble.classList.contains('unsent')) {
                        bubble.classList.add('unsent');
                        
                        const replyBlock = bubble.querySelector('.reply-context-block');
                        if (replyBlock) replyBlock.remove();
                        
                        const textEl = bubble.querySelector('.message-text');
                        if (textEl) {
                            textEl.innerHTML = 'This message was unsent';
                        }
                        
                        const unsendBtn = existing.querySelector('.unsend-action-btn');
                        if (unsendBtn) unsendBtn.remove();
                        
                        const replyBtn = existing.querySelector('.reply-action-btn');
                        if (replyBtn) replyBtn.remove();
                    }
                }
                return;
            }

            const isMe = msg.type === 'sent';
            let content = `<div class="message-text">${this.escapeHtml(msg.message)}</div>`;

            if (msg.is_unsent) {
                content = `<div class="message-text">This message was unsent</div>`;
            } else if (msg.message_type === 'image' && msg.attachment_path) {
                content = `<div class="message-text"><img src="${msg.attachment_path}" class="chat-img-zoomable" alt="Attachment"></div>`;
            } else if (msg.message_type === 'file' && msg.attachment_path) {
                content = `<div class="message-text clinical-file-msg"><a href="${msg.attachment_path}" target="_blank"><i class="fas fa-file-pdf"></i> ${msg.file_name}</a></div>`;
            }

            let replyHtml = '';
            if (msg.reply_to_id && msg.reply_message) {
                replyHtml = `
                    <div class="reply-context-block">
                        <div class="reply-context-header">
                            <i class="fas fa-reply"></i> Replying to ${this.escapeHtml(msg.reply_sender_name)}
                        </div>
                        <div class="reply-context-text">${this.escapeHtml(msg.reply_message)}</div>
                    </div>
                `;
            }

            // Escape parameters for data attributes securely
            const attrMessage = this.escapeHtml(msg.message).replace(/"/g, '&quot;');
            const attrSender = this.escapeHtml(msg.sender_name).replace(/"/g, '&quot;');

            // Build action buttons group
            let actionsHtml = '';
            if (!msg.is_unsent) {
                const replyBtn = `<button type="button" class="reply-action-btn" title="Reply" data-id="${msg.id}" data-sender="${attrSender}" data-message="${attrMessage}"><i class="fas fa-reply"></i></button>`;
                const unsendBtn = isMe ? `<button type="button" class="unsend-action-btn" title="Unsend" data-id="${msg.id}"><i class="fas fa-undo"></i></button>` : '';
                actionsHtml = `<div class="msg-actions">${replyBtn}${unsendBtn}</div>`;
            }

            const html = `
                <div class="message-wrapper ${isMe ? 'sent' : 'received'}" id="msg-${msg.id}">
                    <div class="message-bubble-container">
                        <div class="message-bubble ${msg.is_unsent ? 'unsent' : ''}">
                            ${replyHtml}
                            ${content}
                            <div class="msg-time">${msg.pretty_time}</div>
                        </div>
                        ${actionsHtml}
                    </div>
                </div>
            `;
            this.chatMessages.insertAdjacentHTML('beforeend', html);
            addedNew = true;
        });
        
        if (addedNew) {
            this.scrollToBottom();
        }
    }

    setReply(msgId, messageText, senderName) {
        this.replyToId = msgId;
        
        let banner = document.getElementById('replyBanner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'replyBanner';
            banner.className = 'reply-banner';
            const form = document.getElementById('messageForm');
            const pill = form.querySelector('.input-pill-container');
            form.insertBefore(banner, pill);
        }
        
        // Use clean text decode to strip HTML tags inside preview (e.g. <br>)
        const displayMsgText = messageText.replace(/<br>/g, "\n");

        banner.innerHTML = `
            <div class="reply-content">
                <div class="reply-title">Replying to ${this.escapeHtml(senderName)}</div>
                <div class="reply-text">${this.escapeHtml(displayMsgText)}</div>
            </div>
            <button type="button" class="cancel-reply" onclick="window.chatControllerInstance.clearReply()"><i class="fas fa-times"></i></button>
        `;
        banner.style.display = 'flex';
        
        if (this.inputArea) {
            this.inputArea.focus();
        }
    }

    clearReply() {
        this.replyToId = null;
        const banner = document.getElementById('replyBanner');
        if (banner) {
            banner.style.display = 'none';
            banner.innerHTML = '';
        }
    }

    async unsend(msgId) {
        if (!confirm("Are you sure you want to unsend this message?")) return;

        const formData = new FormData();
        formData.append('message_id', msgId);

        try {
            const res = await fetch(`${BASE_URL}handlers/unsend_message.php`, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            
            if (data.success) {
                const wrapper = document.getElementById(`msg-${msgId}`);
                if (wrapper) {
                    const bubble = wrapper.querySelector('.message-bubble');
                    if (bubble) {
                        bubble.classList.add('unsent');
                        
                        const replyBlock = bubble.querySelector('.reply-context-block');
                        if (replyBlock) replyBlock.remove();
                        
                        const textEl = bubble.querySelector('.message-text');
                        if (textEl) {
                            textEl.innerHTML = 'This message was unsent';
                        }
                    }
                    
                    const unsendBtn = wrapper.querySelector('.unsend-action-btn');
                    if (unsendBtn) unsendBtn.remove();
                    
                    const replyBtn = wrapper.querySelector('.reply-action-btn');
                    if (replyBtn) replyBtn.remove();
                }
            } else {
                alert(data.error || "Failed to unsend message.");
            }
        } catch (e) {
            alert("Error unsending message.");
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
        if (this.replyToId) formData.append('reply_to_id', this.replyToId);

        this.inputArea.value = '';
        if (this.fileInput) this.fileInput.value = '';
        
        // Reset textarea height style
        this.inputArea.style.height = 'auto';
        
        this.clearReply();

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
            window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
        }
    }

    escapeHtml(t) {
        if (!t) return '';
        return t
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;")
            .replace(/\n/g, "<br>");
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
