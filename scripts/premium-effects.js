/* premium-effects.js - Global Clinical Interactivity Engine v3.5 */

(function() {
    'use strict';

    if (window.premiumEffectsInitialized) return;
    window.premiumEffectsInitialized = true;

    // 1. Organic Cursor & HUD Spotlight 2.0
    const initTracking = () => {
        const cursor = document.getElementById('organicCursor');
        const spotlight = document.getElementById('spotlight');
        const aura = document.getElementById('cursorAura');
        
        if (!cursor && !spotlight) return;

        // Custom Cursor Lerping
        const mouse = { x: 0, y: 0 };
        const pos = { x: 0, y: 0 };
        const ratio = 0.15;

        document.addEventListener('mousemove', (e) => {
            mouse.x = e.clientX;
            mouse.y = e.clientY;
            
            if (window.gsap) {
                // Real-time HUD tracking
                gsap.to(spotlight, { 
                    left: e.clientX, 
                    top: e.clientY, 
                    duration: 0.6, 
                    ease: "power2.out" 
                });
                gsap.to(aura, { 
                    left: e.clientX, 
                    top: e.clientY, 
                    duration: 0.3, 
                    ease: "expo.out" 
                });
            }
        });

        // Smooth cursor follow loop
        const loop = () => {
            pos.x += (mouse.x - pos.x) * ratio;
            pos.y += (mouse.y - pos.y) * ratio;
            if (cursor) cursor.style.transform = `translate(${pos.x}px, ${pos.y}px)`;
            requestAnimationFrame(loop);
        };
        loop();

        // Parallax for Mesh Blobs
        const blobs = document.querySelectorAll('.mesh-blob');
        if (blobs.length > 0 && window.gsap) {
            document.addEventListener('mousemove', (e) => {
                const xPos = (e.clientX / window.innerWidth) - 0.5;
                const yPos = (e.clientY / window.innerHeight) - 0.5;

                blobs.forEach((blob, i) => {
                    const depth = (i + 1) * 30;
                    gsap.to(blob, {
                        x: xPos * depth,
                        y: yPos * depth,
                        duration: 2,
                        ease: "power2.out"
                    });
                });
            });
        }

        const interactives = 'a, button, .nd-btn, .nd-card--hoverable, select, .bmi-big-value';
        document.addEventListener('mouseover', (e) => {
            const target = e.target.closest(interactives);
            if (target) {
                cursor?.classList.add('grow');
                spotlight?.classList.add('focus');
                if (target.classList.contains('nd-card--clinical')) {
                    aura?.classList.add('clinical-glow');
                }
            }
        });

        document.addEventListener('mouseout', (e) => {
            if (e.target.closest(interactives)) {
                cursor?.classList.remove('grow');
                spotlight?.classList.remove('focus');
                aura?.classList.remove('clinical-glow');
            }
        });
    };

    // 2. Surgical GSAP Reveal Engine
    const initAnimations = () => {
        if (!window.gsap) return;

        // Register ScrollTrigger
        if (window.ScrollTrigger) {
            gsap.registerPlugin(ScrollTrigger);
            
            // HUD Hero Entrance
            gsap.from('.nd-hero-ribbon .nd-hero-content > *', {
                y: 30,
                opacity: 0,
                duration: 1.2,
                stagger: 0.1,
                ease: "expo.out",
                delay: 0.2
            });

            // Bento Item Sequence
            gsap.from('.bento-grid > div', {
                scrollTrigger: {
                    trigger: '.bento-grid',
                    start: 'top 85%',
                },
                scale: 0.95,
                opacity: 0,
                y: 40,
                duration: 1,
                stagger: {
                    amount: 0.4,
                    grid: "auto",
                    from: "start"
                },
                ease: "power4.out"
            });

            // HUD Metric Pulse
            gsap.to('.hud-metric', {
                opacity: 0.7,
                repeat: -1,
                yoyo: true,
                duration: 1.5,
                stagger: 0.3,
                ease: "sine.inOut"
            });
        }
    };

    // 3. Clinical Smooth Scrolling & Feed Sync
    const initClinicalUtils = () => {
        // Enforce smooth scrolling
        document.documentElement.style.scrollBehavior = 'smooth';
        
        // Activity Feed Interaction
        const feeds = document.querySelectorAll('.activity-feed');
        feeds.forEach(feed => {
            feed.addEventListener('scroll', () => {
                if (feed.scrollTop > 10) {
                    feed.parentElement.classList.add('is-scrolling');
                } else {
                    feed.parentElement.classList.remove('is-scrolling');
                }
            });
        });
    };

    const init = () => {
        initTracking();
        initClinicalUtils();
        // Delay for full asset ready
        setTimeout(initAnimations, 150);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
