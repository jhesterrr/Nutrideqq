/* interactive-effects.js - Global Mouse Interactivity & Animations */

(function () {
    'use strict';

    // Prevent multiple initializations
    if (window.interactiveEffectsInitialized) return;
    window.interactiveEffectsInitialized = true;

    console.log('Elite Interactive Effects Loaded');
    gsap.registerPlugin(MotionPathPlugin);

    // 1. Removed redundant initCursor (handled by landing-animations.js with quickSetter)
    // 2. Magnetic Buttons & Icons
    const initMagnetic = () => {
        const magnets = document.querySelectorAll('.btn, .social-links a, .logo-img, .benefit-icon, .nav-links a i, .ld-nav__links a, .ld-btn');

        magnets.forEach((mag) => {
            let rafId;
            mag.addEventListener('mousemove', (e) => {
                if (rafId) cancelAnimationFrame(rafId);
                rafId = requestAnimationFrame(() => {
                    const rect = mag.getBoundingClientRect();
                    const x = e.clientX - rect.left - rect.width / 2;
                    const y = e.clientY - rect.top - rect.height / 2;
                    
                    mag.style.transition = 'transform 0.1s ease-out';
                    mag.style.transform = `translate3d(${x * 0.25}px, ${y * 0.25}px, 0)`;
                });
            });

            mag.addEventListener('mouseleave', () => {
                if (rafId) cancelAnimationFrame(rafId);
                mag.style.transition = 'transform 0.5s cubic-bezier(0.23, 1, 0.32, 1)';
                mag.style.transform = 'translate3d(0, 0, 0)';
            });
        });
    };

    // 3. Parallax Card Tilt (Subtle)
    const initTilt = () => {
        const cards = document.querySelectorAll('.card, .benefit-card, .nutrition-card, .dashboard-section');

        cards.forEach((card) => {
            let tiltRafId;
            card.addEventListener('mousemove', (e) => {
                if (tiltRafId) cancelAnimationFrame(tiltRafId);
                tiltRafId = requestAnimationFrame(() => {
                    const rect = card.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    
                    const centerX = rect.width / 2;
                    const centerY = rect.height / 2;
                    
                    const rotateX = (y - centerY) / 20;
                    const rotateY = (centerX - x) / 20;
                    
                    card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translate3d(0, -5px, 0) scale(1.02)`;
                });
            });

            card.addEventListener('mouseleave', () => {
                if (tiltRafId) cancelAnimationFrame(tiltRafId);
                card.style.transform = '';
            });
        });
    };

    // 4. Reveal Animations on Scroll - Disabled to ensure stability and permanent placement
    const initScrollReveal = () => {
        // Elements are now visible by default
        const reveals = document.querySelectorAll('.panel, .bento-item, .mood-matrix');
        reveals.forEach(el => {
            el.style.opacity = '1';
            el.style.transform = 'none';
        });

        // Philosophy Deconstruction Scroll Animation
        const nutriSide = document.getElementById('nutri-side');
        const deqSide = document.getElementById('deq-side');
        const pCore = document.querySelector('.philosophy-core');

        if (nutriSide && deqSide) {
            gsap.from(nutriSide, {
                x: -100,
                opacity: 0,
                duration: 1.5,
                scrollTrigger: {
                    trigger: ".philosophy-deconstruction",
                    start: "top 80%",
                }
            });

            gsap.from(deqSide, {
                x: 100,
                opacity: 0,
                duration: 1.5,
                scrollTrigger: {
                    trigger: ".philosophy-deconstruction",
                    start: "top 80%",
                }
            });

            gsap.from(pCore, {
                scale: 0,
                opacity: 0,
                rotate: 180,
                duration: 2,
                ease: "elastic.out(1, 0.5)",
                scrollTrigger: {
                    trigger: ".philosophy-deconstruction",
                    start: "top 80%",
                }
            });
        }
    };

    // 5. Sentiment-Aware Psychometrics Engine (NLP Mood Tracking Logic)
    const initPsychometrics = () => {
        const psychSection = document.getElementById('psychometrics');
        if (!psychSection) return;

        // --- 1. Parallax Tokens ---
        const tokens = document.querySelectorAll('.emotion-token');
        tokens.forEach((token, index) => {
            const tz = parseFloat(token.style.getPropertyValue('--tz')) || 0;
            const speed = tz / 100; // Faster for further forward tokens
            
            gsap.to(token, {
                scrollTrigger: {
                    trigger: psychSection,
                    start: "top bottom",
                    end: "bottom top",
                    scrub: 1
                },
                y: -100 * speed,
                rotate: (index % 2 === 0 ? 5 : -5) * speed,
                ease: "none"
            });
        });

        // --- 2. Sentiment Spectrum Animation ---
        const indicator = document.querySelector('.spectrum-indicator');
        if (indicator) {
            gsap.to(indicator, {
                scrollTrigger: {
                    trigger: psychSection,
                    start: "top 60%",
                    end: "bottom 40%",
                    scrub: 1
                },
                left: "90%", // Animate towards Peak Readiness on scroll
                ease: "power2.inOut"
            });
        }

        // --- 3. Mood Matrix 3D Tilt ---
        const matrix = document.querySelector('.mood-matrix');
        if (matrix) {
            psychSection.addEventListener('mousemove', (e) => {
                const rect = matrix.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const xc = rect.width / 2;
                const yc = rect.height / 2;
                const dx = (x - xc) / xc;
                const dy = (y - yc) / yc;

                gsap.to(matrix, {
                    rotateY: dx * 8,
                    rotateX: -dy * 8,
                    duration: 0.6,
                    ease: "power2.out"
                });
            });

            psychSection.addEventListener('mouseleave', () => {
                gsap.to(matrix, {
                    rotateX: 0,
                    rotateY: 0,
                    duration: 1.2,
                    ease: "elastic.out(1, 0.5)"
                });
            });
        }

        // --- 4. Matrix Reveal ---
        gsap.from(".matrix-cell", {
            scrollTrigger: {
                trigger: ".matrix-grid",
                start: "top 80%",
            },
            scale: 0,
            opacity: 0,
            stagger: {
                each: 0.05,
                from: "center"
            },
            duration: 1,
            ease: "back.out(1.7)"
        });
    };

    // 6. Bento Grid Logic (Pulse Charts, Neural Web, Data Streams)
    const initBentoVisuals = () => {
        // Biometrics Pulse Chart & Scanline
        const pulsePath = document.querySelector('.pulse-path');
        if (pulsePath) {
            gsap.to(pulsePath, {
                strokeDashoffset: 0,
                duration: 4, // Slower, more clinical
                ease: "none",
                repeat: -1
            });

            // Animate tracker along path
            const tracker = document.querySelector('.pulse-tracker');
            if (tracker) {
                gsap.to(tracker, {
                    motionPath: {
                        path: pulsePath,
                        align: pulsePath,
                        alignOrigin: [0.5, 0.5]
                    },
                    duration: 4,
                    ease: "none",
                    repeat: -1
                });
            }

            // Monitor Scanline
            const scanline = document.querySelector('.monitor-scanline');
            if (scanline) {
                gsap.to(scanline, {
                    left: "110%",
                    duration: 4,
                    ease: "none",
                    repeat: -1
                });
            }
        }

        // AI Neural Web Visualizer
        const neuralContainer = document.getElementById('neural-visualizer');
        if (neuralContainer) {
            for (let i = 0; i < 12; i++) {
                const node = document.createElement('div');
                node.className = 'node';
                node.style.left = Math.random() * 100 + '%';
                node.style.top = Math.random() * 100 + '%';
                neuralContainer.appendChild(node);
                
                // Pulsing animation
                gsap.to(node, {
                    opacity: 0.2,
                    scale: 1.5,
                    duration: 1 + Math.random() * 2,
                    repeat: -1,
                    yoyo: true,
                    ease: "sine.inOut"
                });

                // Subtle movement
                gsap.to(node, {
                    x: (Math.random() - 0.5) * 30,
                    y: (Math.random() - 0.5) * 30,
                    duration: 2 + Math.random() * 2,
                    repeat: -1,
                    yoyo: true,
                    ease: "sine.inOut"
                });
            }
        }

        // Workflow Node Sequential Pulse
        const wfNodes = document.querySelectorAll('.wf-node');
        if (wfNodes.length) {
            gsap.to(wfNodes, {
                scale: 1.2,
                boxShadow: "0 0 20px rgba(80, 200, 120, 0.6)",
                borderColor: "#50C878",
                duration: 0.6,
                stagger: {
                    each: 0.4,
                    repeat: -1,
                    yoyo: true
                },
                ease: "power2.inOut"
            });
        }

        // Data Stream Particles
        const particles = document.querySelectorAll('.data-particle');
        particles.forEach((p, i) => {
            gsap.to(p, {
                top: "100%",
                duration: 0.8 + Math.random() * 0.5,
                repeat: -1,
                delay: i * 0.3,
                ease: "none"
            });
        });

        // Live HR Simulation
        const hrVal = document.querySelector('.pulse-val');
        if (hrVal) {
            setInterval(() => {
                const next = 70 + Math.floor(Math.random() * 5);
                hrVal.textContent = next;
            }, 2000);
        }
    };

    const init = () => {
        initMagnetic();
        initTilt();
        initScrollReveal();
        initPsychometrics();
        initBentoVisuals();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
