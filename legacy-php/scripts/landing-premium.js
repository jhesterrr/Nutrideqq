document.addEventListener('DOMContentLoaded', () => {
    // --- Lenis Smooth Scroll Setup ---
    const lenis = new Lenis({
        duration: 1.2,
        easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
        smooth: true,
    });

    function raf(time) {
        lenis.raf(time);
        requestAnimationFrame(raf);
    }
    requestAnimationFrame(raf);

    // Sync GSAP ScrollTrigger with Lenis
    if (typeof gsap !== 'undefined' && typeof ScrollTrigger !== 'undefined') {
        gsap.registerPlugin(ScrollTrigger);
        lenis.on('scroll', ScrollTrigger.update);
        gsap.ticker.add((time) => { lenis.raf(time * 1000); });
        gsap.ticker.lagSmoothing(0);
    }

    // --- Splash Screen: Self-Drawing Logo ---
    const splash = document.getElementById('splash-screen');
    const drawPaths = document.querySelectorAll('.draw-path');
    const logoText = document.querySelector('.logo-text');
    const logoTagline = document.querySelector('.logo-tagline');
    const splashSvg = document.querySelector('.splash-logo-svg');

    if (splash && drawPaths.length && gsap) {
        // Measure each path and set dasharray to its real length
        drawPaths.forEach(path => {
            const len = path.getTotalLength();
            path.style.strokeDasharray = len;
            path.style.strokeDashoffset = len;
        });

        const splashTl = gsap.timeline({
            onComplete: () => {
                // Brief emerald glow on finished logo
                gsap.to(splashSvg, {
                    filter: "drop-shadow(0 0 25px rgba(16, 185, 129, 0.35))",
                    duration: 0.5,
                    ease: "power2.in",
                    onComplete: () => {
                        // Fade out splash — matching background = seamless
                        gsap.to(splash, {
                            opacity: 0,
                            duration: 0.8,
                            ease: "power2.inOut",
                            onComplete: () => splash.remove()
                        });
                    }
                });
            }
        });

        // 1. Draw the Hexagon
        splashTl.to(".hex-path", {
            strokeDashoffset: 0,
            duration: 1.2,
            ease: "power2.inOut"
        });

        // 2. Draw the Leaf + Vein (overlapped)
        splashTl.to(".leaf-path", {
            strokeDashoffset: 0,
            duration: 1.0,
            ease: "power2.inOut"
        }, "-=0.3");

        splashTl.to(".vein-path", {
            strokeDashoffset: 0,
            duration: 0.6,
            ease: "power2.inOut"
        }, "-=0.5");

        // 3. Draw the Heartbeat Pulse
        splashTl.to(".pulse-path", {
            strokeDashoffset: 0,
            duration: 0.8,
            ease: "power3.out"
        }, "-=0.2");

        // 4. Fade in text
        splashTl.to(logoText, {
            opacity: 1,
            duration: 0.6,
            ease: "power2.out"
        }, "-=0.2");

        splashTl.to(logoTagline, {
            opacity: 1,
            duration: 0.5,
            ease: "power2.out"
        }, "-=0.3");

        // 5. Hold for a beat
        splashTl.to({}, { duration: 0.4 });

    } else if (splash) {
        setTimeout(() => splash.remove(), 1000);
    }

    // --- Custom Cursor ---
    const cursor = document.querySelector('.custom-cursor');
    if (cursor) {
        let mouseX = window.innerWidth / 2;
        let mouseY = window.innerHeight / 2;
        let dotX = mouseX, dotY = mouseY;
        let ringX = mouseX, ringY = mouseY;

        window.addEventListener('mousemove', (e) => {
            mouseX = e.clientX;
            mouseY = e.clientY;
        });

        const animateCursor = () => {
            dotX += (mouseX - dotX) * 0.2;
            dotY += (mouseY - dotY) * 0.2;
            ringX += (mouseX - ringX) * 0.1;
            ringY += (mouseY - ringY) * 0.1;

            const dot = document.querySelector('.cursor-dot');
            const ring = document.querySelector('.cursor-ring');
            if (dot) dot.style.transform = `translate(${dotX}px, ${dotY}px) translate(-50%, -50%)`;
            if (ring) ring.style.transform = `translate(${ringX}px, ${ringY}px) translate(-50%, -50%)`;

            requestAnimationFrame(animateCursor);
        };
        requestAnimationFrame(animateCursor);

        const interactables = document.querySelectorAll('a, button, input[type="range"], .glass-card');
        interactables.forEach(el => {
            el.addEventListener('mouseenter', () => cursor.classList.add('hovering'));
            el.addEventListener('mouseleave', () => cursor.classList.remove('hovering'));
        });
    }

    // --- Background Canvas Particles ---
    const canvas = document.getElementById('bg-canvas');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        let width, height;
        let particles = [];

        const resize = () => {
            width = canvas.width = window.innerWidth;
            height = canvas.height = window.innerHeight;
        };
        window.addEventListener('resize', resize);
        resize();

        class Particle {
            constructor() {
                this.x = Math.random() * width;
                this.y = Math.random() * height;
                this.vx = (Math.random() - 0.5) * 0.5;
                this.vy = (Math.random() - 0.5) * 0.5;
                this.radius = Math.random() * 2 + 0.5;
            }
            update() {
                this.x += this.vx;
                this.y += this.vy;
                if (this.x < 0 || this.x > width) this.vx *= -1;
                if (this.y < 0 || this.y > height) this.vy *= -1;
            }
            draw() {
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
                ctx.fillStyle = 'rgba(80, 200, 120, 0.2)';
                ctx.fill();
            }
        }

        for (let i = 0; i < 50; i++) particles.push(new Particle());

        const drawParticles = () => {
            ctx.clearRect(0, 0, width, height);
            for (let i = 0; i < particles.length; i++) {
                particles[i].update();
                particles[i].draw();
                for (let j = i + 1; j < particles.length; j++) {
                    const dx = particles[i].x - particles[j].x;
                    const dy = particles[i].y - particles[j].y;
                    const dist = Math.sqrt(dx * dx + dy * dy);
                    if (dist < 150) {
                        ctx.beginPath();
                        ctx.moveTo(particles[i].x, particles[i].y);
                        ctx.lineTo(particles[j].x, particles[j].y);
                        ctx.strokeStyle = `rgba(80, 200, 120, ${0.1 - dist / 1500})`;
                        ctx.stroke();
                    }
                }
            }
            requestAnimationFrame(drawParticles);
        };
        drawParticles();
    }

    // --- Parallax 3D Tilt Effect on Hero Dashboard ---
    const dashWrapper = document.querySelector('.hero-dashboard-wrapper');
    const dash = document.querySelector('.hero-dashboard');
    const layers = dash?.querySelectorAll('.glass-layer');

    if (dashWrapper && dash) {
        dashWrapper.addEventListener('mousemove', (e) => {
            const rect = dashWrapper.getBoundingClientRect();
            const x = (e.clientX - rect.left) / rect.width - 0.5;
            const y = (e.clientY - rect.top) / rect.height - 0.5;
            
            if (window.innerWidth > 768) {
                // Main tilt for the container
                gsap.to(dash, {
                    rotateX: 10 + y * -15,
                    rotateY: -5 + x * 15,
                    duration: 0.6,
                    ease: "power2.out"
                });

                // Independent parallax for each card/layer
                layers.forEach(layer => {
                    const depth = parseFloat(layer.style.getPropertyValue('--tz')) || 20;
                    // Proportional movement based on Z-depth
                    const moveX = x * (depth * 0.8);
                    const moveY = y * (depth * 0.8);
                    
                    gsap.to(layer, {
                        x: moveX,
                        y: moveY,
                        duration: 0.8,
                        ease: "power2.out"
                    });
                });
            }
        });

        dashWrapper.addEventListener('mouseleave', () => {
            if (window.innerWidth > 768) {
                // Reset main container
                gsap.to(dash, { rotateX: 10, rotateY: -5, duration: 1.2, ease: "power3.out" });
                
                // Reset all layers
                layers.forEach(layer => {
                    gsap.to(layer, { x: 0, y: 0, duration: 1.2, ease: "power3.out" });
                });
            }
        });
    }

    // --- GSAP Scroll Animations ---
    if (typeof gsap !== 'undefined' && typeof ScrollTrigger !== 'undefined') {
        
        // Panel fading
        gsap.utils.toArray('.panel').forEach(panel => {
            gsap.from(panel, {
                opacity: 0,
                y: 50,
                duration: 1,
                scrollTrigger: {
                    trigger: panel,
                    start: "top 80%",
                }
            });
        });

        // DOST-FCT Engine Radar Animation
        const radar = document.querySelector('.radar-chart');
        if (radar) {
            ScrollTrigger.create({
                trigger: radar,
                start: "top 70%",
                onEnter: () => radar.classList.add('active')
            });
            
            // Stagger floating labels
            gsap.to('.floating-data-label', {
                opacity: 1,
                y: -10,
                stagger: 0.2,
                duration: 0.8,
                ease: "back.out(1.7)",
                scrollTrigger: {
                    trigger: radar,
                    start: "top 60%"
                }
            });
        }

        // Architecture Bento Grid Staggered Entrance
        const bentoGrid = document.querySelector('.bento-grid');
        if (bentoGrid) {
            gsap.from(".bento-item", {
                scrollTrigger: {
                    trigger: bentoGrid,
                    start: "top 85%",
                    toggleActions: "play none none none"
                },
                y: 60,
                opacity: 0,
                scale: 0.9,
                duration: 1.2,
                stagger: {
                    amount: 0.6,
                    from: "start"
                },
                ease: "expo.out",
                onStart: () => {
                    // Trigger the line animations for each item as they start animating
                    document.querySelectorAll('.bento-item').forEach(item => {
                        item.classList.add('in-view');
                    });
                }
            });
        }
    }

    // --- Equity Slider Logic ---
    const slider = document.getElementById('equity-slider');
    const eqResult = document.getElementById('equity-result-text');
    const trackFill = document.querySelector('.slider-track-fill');

    if (slider && eqResult && trackFill) {
        const scenarios = [
            "<strong>Standard Plan:</strong> Basic daily nutrition needs based on standard height, weight, and age targets.",
            "<strong>Budget-Friendly:</strong> Smartly swapping expensive ingredients for affordable, local alternatives using DOST-FCT data.",
            "<strong>Kidney Care:</strong> Carefully managing potassium and phosphorus while ensuring patients get enough energy.",
            "<strong>Diabetic Friendly:</strong> Adapting traditional rice-heavy meals with healthy, blood-sugar-friendly alternatives.",
            "<strong>Child Growth:</strong> Creating nutrient-packed, high-calorie meal plans to support healthy development in children."
        ];
        
        const updateSlider = () => {
            const val = parseInt(slider.value);
            const percentage = (val / slider.max) * 100;
            trackFill.style.width = percentage + '%';
            
            gsap.to(eqResult, { opacity: 0, y: 10, duration: 0.2, onComplete: () => {
                eqResult.innerHTML = scenarios[val];
                gsap.to(eqResult, { opacity: 1, y: 0, duration: 0.3 });
            }});
        };

        slider.addEventListener('input', updateSlider);
        // init track fill
        trackFill.style.width = '0%';
    }
});
