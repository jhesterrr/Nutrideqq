/**
 * particle-engine.js
 * A high-performance, surgical-grade neural particle system for the NutriDeq Landing Page.
 * Features: Proximity glow, neural connections, and reactive drift.
 */

class ParticleEngine {
    constructor(canvasId) {
        this.canvas = document.getElementById(canvasId);
        if (!this.canvas) return;
        
        this.ctx = this.canvas.getContext('2d');
        this.particles = [];
        this.mouse = { x: -1000, y: -1000 };
        this.config = {
            particleCount: window.innerWidth < 768 ? 40 : 80,
            connectionDist: 150,
            proximityDist: 200,
            baseSpeed: 0.4,
            particleSize: 1.5,
            color: '16, 185, 129' // Emerald RGB
        };

        this.init();
        this.animate();
        this.setupEventListeners();
    }

    init() {
        this.resize();
        this.particles = [];
        for (let i = 0; i < this.config.particleCount; i++) {
            this.particles.push(this.createParticle());
        }
    }

    createParticle() {
        return {
            x: Math.random() * this.canvas.width,
            y: Math.random() * this.canvas.height,
            vx: (Math.random() - 0.5) * this.config.baseSpeed,
            vy: (Math.random() - 0.5) * this.config.baseSpeed,
            radius: Math.random() * this.config.particleSize + 0.5,
            alpha: Math.random() * 0.5 + 0.2
        };
    }

    resize() {
        this.canvas.width = window.innerWidth;
        this.canvas.height = window.innerHeight;
    }

    setupEventListeners() {
        window.addEventListener('resize', () => {
            this.resize();
            this.config.particleCount = window.innerWidth < 768 ? 40 : 80;
        });

        window.addEventListener('mousemove', (e) => {
            this.mouse.x = e.clientX;
            this.mouse.y = e.clientY;
        });

        // Touch support
        window.addEventListener('touchmove', (e) => {
            if (e.touches.length > 0) {
                this.mouse.x = e.touches[0].clientX;
                this.mouse.y = e.touches[0].clientY;
            }
        });

        // Performance Hack: Pause rendering while scrolling
        let scrollTimeout;
        window.addEventListener('scroll', () => {
            this.isScrolling = true;
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                this.isScrolling = false;
            }, 150);
        }, { passive: true });
    }

    draw() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

        for (let i = 0; i < this.particles.length; i++) {
            const p = this.particles[i];

            // Movement
            p.x += p.vx;
            p.y += p.vy;

            // Boundary wrap
            if (p.x < 0) p.x = this.canvas.width;
            if (p.x > this.canvas.width) p.x = 0;
            if (p.y < 0) p.y = this.canvas.height;
            if (p.y > this.canvas.height) p.y = 0;

            // Proximity interaction
            const dx = p.x - this.mouse.x;
            const dy = p.y - this.mouse.y;
            const dist = Math.sqrt(dx * dx + dy * dy);

            let currentAlpha = p.alpha;
            if (dist < this.config.proximityDist) {
                currentAlpha = 0.8 * (1 - dist / this.config.proximityDist);
            }

            // Draw particle
            this.ctx.beginPath();
            this.ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
            this.ctx.fillStyle = `rgba(${this.config.color}, ${currentAlpha})`;
            this.ctx.fill();

            // Connections
            for (let j = i + 1; j < this.particles.length; j++) {
                const p2 = this.particles[j];
                const dx2 = p.x - p2.x;
                const dy2 = p.y - p2.y;
                const dist2 = Math.sqrt(dx2 * dx2 + dy2 * dy2);

                if (dist2 < this.config.connectionDist) {
                    const connAlpha = (1 - dist2 / this.config.connectionDist) * 0.15;
                    this.ctx.beginPath();
                    this.ctx.moveTo(p.x, p.y);
                    this.ctx.lineTo(p2.x, p2.y);
                    this.ctx.strokeStyle = `rgba(${this.config.color}, ${connAlpha})`;
                    this.ctx.stroke();
                }
            }
        }
    }

    animate() {
        if (!this.isScrolling) {
            this.draw();
        }
        requestAnimationFrame(() => this.animate());
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new ParticleEngine('bg-canvas');
});
