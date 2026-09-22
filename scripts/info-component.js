class InfoButton extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        
        // Define properties
        this.feature = this.getAttribute('feature') || '';
        this.role = this.getAttribute('role') || 'regular';
        
        // Styles specific to the shadow DOM
        const style = document.createElement('style');
        style.textContent = `
            :host {
                display: inline-block;
                position: relative;
                font-family: 'Poppins', sans-serif;
                z-index: 10;
            }
            :host(.active-host) {
                z-index: 9999;
            }
            .info-icon {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 20px;
                height: 20px;
                background-color: #10b981;
                color: white;
                border-radius: 50%;
                font-size: 12px;
                font-weight: 700;
                font-family: serif;
                font-style: italic;
                cursor: pointer;
                transition: transform 0.2s, box-shadow 0.2s;
                user-select: none;
            }
            .info-icon:hover {
                transform: scale(1.1);
                box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
            }
            .tooltip-modal {
                position: absolute;
                bottom: 100%;
                left: var(--tooltip-left, auto);
                right: var(--tooltip-right, 0);
                margin-bottom: 12px;
                width: 300px;
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
                padding: 20px;
                opacity: 0;
                visibility: hidden;
                transform: var(--tooltip-transform-start, translateY(10px));
                transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
                z-index: 30000; /* Absolute Top Layer */
                pointer-events: none;
            }
            .tooltip-modal.active {
                opacity: 1;
                visibility: visible;
                transform: var(--tooltip-transform-end, translateY(0));
                pointer-events: auto;
            }
            
            /* Responsive overrides */
            @media (max-width: 1024px) {
                .tooltip-modal {
                    position: fixed;
                    bottom: 0;
                    right: 0 !important;
                    left: 0 !important;
                    width: 100% !important;
                    margin: 0;
                    border-radius: 28px 28px 0 0;
                    padding: 30px 24px;
                    padding-bottom: calc(env(safe-area-inset-bottom, 20px) + 20px);
                    transform: translateY(100%) !important;
                    z-index: 30000;
                    box-shadow: 0 -10px 40px rgba(0,0,0,0.1);
                }
                .tooltip-modal.active {
                    transform: translateY(0) !important;
                }
                .tooltip-modal::after { display: none; }
                .mobile-overlay { z-index: 29999; }
                .mobile-overlay.active { display: block; opacity: 1; }
            }
        `;
        
        // Element Structure
        const container = document.createElement('div');
        this.icon = document.createElement('div');
        this.icon.className = 'info-icon';
        this.icon.innerHTML = 'i';
        
        this.overlay = document.createElement('div');
        this.overlay.className = 'mobile-overlay';
        
        this.tooltip = document.createElement('div');
        this.tooltip.className = 'tooltip-modal';
        this.tooltip.innerHTML = `
            <div class="tt-header">
                <i class="fas fa-info-circle" style="color: #10b981; margin-right: 8px; font-size: 1rem;"></i>
                <h4 class="tt-title">Loading...</h4>
            </div>
            <p class="tt-body">Fetching context...</p>
        `;
        
        container.appendChild(this.icon);
        container.appendChild(this.overlay);
        container.appendChild(this.tooltip);
        
        this.shadowRoot.appendChild(style);
        // Include font awesome in shadow DOM for the title icon
        const fa = document.createElement('link');
        fa.rel = 'stylesheet';
        fa.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
        this.shadowRoot.appendChild(fa);
        this.shadowRoot.appendChild(container);
        
        this.bindEvents();
    }
    
    async fetchContent() {
        if (this.contentLoaded) return;
        try {
            const response = await fetch('feature_knowledge.json');
            const data = await response.json();
            const featureData = data[this.feature];
            
            if (featureData) {
                this.shadowRoot.querySelector('.tt-title').innerText = featureData.title;
                const desc = featureData.description[this.role] || featureData.description['regular'];
                this.shadowRoot.querySelector('.tt-body').innerText = desc;
            } else {
                this.shadowRoot.querySelector('.tt-title').innerText = 'Information';
                this.shadowRoot.querySelector('.tt-body').innerText = 'Documentation unavailable for this feature.';
            }
            this.contentLoaded = true;
        } catch (e) {
            console.error('Failed to load InfoButton dictionary:', e);
            this.shadowRoot.querySelector('.tt-body').innerText = 'System error fetching knowledge database.';
        }
    }

    adjustPosition() {
        // Only run on desktop layout (since mobile uses position: fixed at bottom)
        if (window.innerWidth <= 1024) {
            this.style.setProperty('--tooltip-left', 'auto');
            this.style.setProperty('--tooltip-right', '0');
            this.style.setProperty('--tooltip-transform-start', 'translateY(10px)');
            this.style.setProperty('--tooltip-transform-end', 'translateY(0)');
            return;
        }

        const rect = this.icon.getBoundingClientRect();
        const tooltipWidth = 300;
        const halfWidth = tooltipWidth / 2;
        
        // Calculate spaces relative to screen boundaries
        const spaceLeft = rect.left + rect.width / 2;
        const spaceRight = window.innerWidth - (rect.left + rect.width / 2);
        
        if (spaceLeft < halfWidth + 24) {
            // Not enough space on the left -> Align left edge of tooltip with icon left
            this.style.setProperty('--tooltip-left', '0');
            this.style.setProperty('--tooltip-right', 'auto');
            this.style.setProperty('--tooltip-transform-start', 'translateY(10px)');
            this.style.setProperty('--tooltip-transform-end', 'translateY(0)');
        } else if (spaceRight < halfWidth + 24) {
            // Not enough space on the right -> Align right edge of tooltip with icon right
            this.style.setProperty('--tooltip-left', 'auto');
            this.style.setProperty('--tooltip-right', '0');
            this.style.setProperty('--tooltip-transform-start', 'translateY(10px)');
            this.style.setProperty('--tooltip-transform-end', 'translateY(0)');
        } else {
            // Perfect! Enough space on both sides -> Center the tooltip
            this.style.setProperty('--tooltip-left', '50%');
            this.style.setProperty('--tooltip-right', 'auto');
            this.style.setProperty('--tooltip-transform-start', 'translateX(-50%) translateY(10px)');
            this.style.setProperty('--tooltip-transform-end', 'translateX(-50%) translateY(0)');
        }
    }

    bindEvents() {
        const toggleMenu = (e) => {
            e.stopPropagation();
            const isActive = this.tooltip.classList.contains('active');
            
            // Close any open ones globally (dispatch event to others)
            document.dispatchEvent(new CustomEvent('closeAllInfoButtons'));
            
            if (!isActive) {
                this.fetchContent();
                this.adjustPosition();
                this.tooltip.classList.add('active');
                this.overlay.classList.add('active');
                this.classList.add('active-host');
            }
        };

        this.icon.addEventListener('click', toggleMenu);
        this.overlay.addEventListener('click', (e) => {
            e.stopPropagation();
            this.close();
        });

        // Close when clicking outside in the main document
        document.addEventListener('click', (e) => {
            this.close();
        });
        
        // Listen to global close event
        document.addEventListener('closeAllInfoButtons', () => {
            this.close();
        });
    }
    
    close() {
        this.tooltip.classList.remove('active');
        this.overlay.classList.remove('active');
        this.classList.remove('active-host');
    }
}

// Register the component
customElements.define('info-button', InfoButton);
