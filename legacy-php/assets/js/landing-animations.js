document.addEventListener("DOMContentLoaded", () => {
    // Register ScrollTrigger
    gsap.registerPlugin(ScrollTrigger);

    // Initialize Lenis for smooth scrolling
    const lenis = new Lenis({
        duration: 1.2,
        easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
        smooth: true,
    });

    // Synchronize Lenis scrolling with GSAP's ScrollTrigger
    lenis.on('scroll', ScrollTrigger.update);

    // Add Lenis's requestAnimationFrame (raf) method to GSAP's ticker
    // This ensures Lenis's smooth scroll animation updates on every GSAP frame
    gsap.ticker.add((time) => {
        lenis.raf(time * 1000);
    });

    // Disable GSAP's lag smoothing to prevent conflicts with Lenis
    gsap.ticker.lagSmoothing(0);

    // SPLASH SCREEN — Emerald Facet Assembly
    const splashScreen = document.getElementById("splash-screen");
    const splashSvg = document.querySelector(".splash-logo-svg");

    // Set initial transform origins for geometric assembly
    if (splashSvg) {
        gsap.set(".facet", { transformOrigin: "center center" });

        const splashTl = gsap.timeline({
            onComplete: () => {
                // Brief glow on the finished logo
                gsap.to(splashSvg, {
                    filter: "drop-shadow(0 0 30px rgba(16, 185, 129, 0.4))",
                    duration: 0.6,
                    ease: "power2.in",
                    onComplete: () => {
                        // Fade out splash
                        gsap.to(splashScreen, {
                            opacity: 0,
                            duration: 0.8,
                            ease: "power2.inOut",
                            onComplete: () => {
                                splashScreen.style.display = "none";
                                document.body.classList.remove("loading");
                                initMainAnimations();
                            }
                        });
                    }
                });
            }
        });

        // 1. Facets Assembly (The 'Diamond Cut' reveal)
        splashTl.fromTo(".top-facet", 
            { scale: 0.2, y: -40, opacity: 0 },
            { scale: 1, y: 0, opacity: 1, duration: 1.2, ease: "expo.out" }
        )
        .fromTo(".left-facet",
            { x: -50, scale: 0.5, opacity: 0 },
            { x: 0, scale: 1, opacity: 1, duration: 1, ease: "power3.out" },
            "-=0.8"
        )
        .fromTo(".right-facet",
            { x: 50, scale: 0.5, opacity: 0 },
            { x: 0, scale: 1, opacity: 1, duration: 1, ease: "power3.out" },
            "-=1"
        )
        // 2. The Luxury Reflection (A subtle shine)
        .fromTo(".reflection-facet",
            { opacity: 0, scale: 0.8 },
            { opacity: 0.6, scale: 1, duration: 1.2, ease: "sine.inOut" },
            "-=0.5"
        )
        // 3. Reveal Elite Typography
        .fromTo(".brand-name-group", {
            opacity: 0,
            y: 20
        }, {
            opacity: 1,
            y: 0,
            duration: 1.2,
            ease: "power3.out"
        }, "-=0.6");
    } else {
        // Fallback if splash is missing
        document.body.classList.remove("loading");
        initMainAnimations();
    }

    // 5. Hold for a beat
    splashTl.to({}, { duration: 0.4 });

    // --- PREPARE ENTRANCE ANIMATIONS (PREVENT FOUC) ---
    const heroTitle = document.querySelector(".hero-title");
    if (heroTitle && !heroTitle.dataset.split) {
        heroTitle.dataset.split = "true";
        const words = heroTitle.textContent.trim().split(/\s+/);
        heroTitle.innerHTML = words.map(w => 
            `<span class="word"><span class="word-inner" style="display:inline-block; transform: translateY(100%); opacity: 0;">${w}</span></span>`
        ).join(' ');
    }

    gsap.set(".section-subtitle", { y: 30, opacity: 0 });
    gsap.set(".hero-actions", { y: 30, opacity: 0 });
    gsap.set(".hero-dashboard-wrapper", { z: -500, y: 100, opacity: 0 });
    gsap.set(".hero-dashboard .glass-layer", { z: -200, opacity: 0 });

    // MAIN ANIMATIONS
    function initMainAnimations() {
        // --- CUSTOM CURSOR ENGINE ---
        const cursor = document.querySelector(".custom-cursor");
        const dot = document.querySelector(".cursor-dot");
        const ring = document.querySelector(".cursor-ring");

        if (cursor && dot && ring) {
            // High-performance quickSetters
            const xDot = gsap.quickSetter(dot, "x", "px");
            const yDot = gsap.quickSetter(dot, "y", "px");
            const xRing = gsap.quickSetter(ring, "x", "px");
            const yRing = gsap.quickSetter(ring, "y", "px");

            window.addEventListener("mousemove", e => {
                // Dot follows instantly
                xDot(e.clientX);
                yDot(e.clientY);

                // Ring follows with a slight, smooth delay
                gsap.to({}, {
                    duration: 0,
                    onUpdate: () => {
                        gsap.to(ring, {
                            x: e.clientX,
                            y: e.clientY,
                            duration: 0.15,
                            ease: "power2.out"
                        });
                    }
                });
            });

            // Hover States
            const interactiveElements = document.querySelectorAll('a, button, .glass-card, .cta-btn, .logo');
            interactiveElements.forEach(el => {
                el.addEventListener("mouseenter", () => cursor.classList.add("hovering"));
                el.addEventListener("mouseleave", () => cursor.classList.remove("hovering"));
            });
        }

        // --- MAGNETIC INTERACTION ENGINE ---
        const magneticElements = document.querySelectorAll('.cta-btn, .btn-primary, .btn-secondary, .logo');
        
        magneticElements.forEach(item => {
            item.addEventListener('mousemove', (e) => {
                const rect = item.getBoundingClientRect();
                const x = e.clientX - rect.left - rect.width / 2;
                const y = e.clientY - rect.top - rect.height / 2;

                // Pull the element towards the mouse (0.2 factor for high-end subtlety)
                gsap.to(item, {
                    x: x * 0.2,
                    y: y * 0.2,
                    duration: 0.6,
                    ease: "power2.out"
                });
            });

            item.addEventListener('mouseleave', () => {
                // Snap back to original position
                gsap.to(item, {
                    x: 0,
                    y: 0,
                    duration: 1,
                    ease: "elastic.out(1, 0.5)"
                });
            });
        });

        // --- ADVANCED NAVBAR PHYSICS ---
        const nav = document.querySelector('.main-nav');
        let lastScrollY = window.scrollY;

        // Add Scroll Progress Indicator
        const progressContainer = document.createElement('div');
        progressContainer.className = 'nav-progress-container';
        const progressBar = document.createElement('div');
        progressBar.className = 'nav-progress-bar';
        
        progressContainer.appendChild(progressBar);
        nav.appendChild(progressContainer);

        let scrollRafId;
        window.addEventListener('scroll', () => {
            if (scrollRafId) cancelAnimationFrame(scrollRafId);
            scrollRafId = requestAnimationFrame(() => {
                const currentScrollY = window.scrollY;
                const docHeight = document.documentElement.scrollHeight - window.innerHeight;
                const progress = (currentScrollY / docHeight) * 100;

                // Update Progress Bar
                progressBar.style.width = `${progress}%`;

                // 1. Pill-to-Bar Morph (Floating Pill transition)
                if (currentScrollY > 50) {
                    nav.classList.add('scrolled');
                } else {
                    nav.classList.remove('scrolled');
                }

                lastScrollY = currentScrollY;
            });
        }, { passive: true });

        // --- MAGNETIC LINK SNAPPING ---
        const navLinks = document.querySelectorAll('.links a');
        navLinks.forEach(link => {
            link.addEventListener('mousemove', (e) => {
                const rect = link.getBoundingClientRect();
                const x = e.clientX - rect.left - rect.width / 2;
                const y = e.clientY - rect.top - rect.height / 2;

                // Liquid Tension magnetic pull for links
                gsap.to(link, {
                    x: x * 0.4,
                    y: y * 0.4,
                    duration: 0.8,
                    ease: "elastic.out(1, 0.75)"
                });

                // Expand cursor when over link
                const cursorFollower = document.querySelector('.cursor-follower');
                if (cursorFollower) {
                    gsap.to(cursorFollower, {
                        scale: 3,
                        borderColor: 'rgba(5, 150, 105, 0.4)',
                        backgroundColor: 'rgba(5, 150, 105, 0.05)',
                        duration: 0.3
                    });
                }
            });

            link.addEventListener('mouseleave', () => {
                gsap.to(link, {
                    x: 0,
                    y: 0,
                    duration: 1.2,
                    ease: "elastic.out(1, 0.4)"
                });

                const cursorFollower = document.querySelector('.cursor-follower');
                if (cursorFollower) {
                    gsap.to(cursorFollower, {
                        scale: 1,
                        borderColor: 'rgba(6, 78, 59, 0.2)',
                        backgroundColor: 'transparent',
                        duration: 0.3
                    });
                }
            });
        });

        // Hero Reveal (Animated from the prepared states)
        gsap.to(".hero-title .word-inner", {
            y: 0,
            opacity: 1,
            duration: 1.2,
            stagger: 0.1,
            ease: "expo.out",
            delay: 0.2,
            force3D: true
        });

        gsap.to(".section-subtitle", {
            y: 0,
            opacity: 1,
            duration: 1,
            delay: 0.4,
            ease: "power3.out"
        });

        gsap.to(".hero-actions", {
            y: 0,
            opacity: 1,
            duration: 1,
            delay: 0.6,
            ease: "power3.out"
        });

        gsap.to(".hero-dashboard-wrapper", {
            z: 0,
            y: 0,
            opacity: 1,
            duration: 1.8,
            delay: 0.8,
            ease: "expo.out",
            force3D: true
        });

        // Staggered Z-Axis boot for internal layers
        gsap.to(".hero-dashboard .glass-layer", {
            z: 0,
            opacity: 1,
            duration: 1.5,
            delay: 1.2,
            stagger: 0.1,
            ease: "power3.out",
            force3D: true
        });        // 3D Parallax for Hero Dashboard Stack
        const dashboardWrapper = document.querySelector(".hero-dashboard-wrapper");
        const dashboard = document.querySelector(".hero-dashboard");
        const layers = document.querySelectorAll(".glass-layer");

        if (dashboardWrapper && dashboard) {
            let dashRafId;
            window.addEventListener("mousemove", (e) => {
                if (dashRafId) cancelAnimationFrame(dashRafId);
                dashRafId = requestAnimationFrame(() => {
                    const rect = dashboardWrapper.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    const xc = rect.width / 2;
                    const yc = rect.height / 2;
                    const dx = (x - xc) / xc; // -1 to 1
                    const dy = (y - yc) / yc; // -1 to 1

                    // Tilt the main dashboard
                    gsap.to(dashboard, {
                        rotateY: dx * 8,
                        rotateX: 10 - (dy * 8),
                        duration: 0.8,
                        ease: "power2.out"
                    });

                    // Subtle parallax for each layer based on their --tz
                    layers.forEach((layer) => {
                        const tz = parseFloat(getComputedStyle(layer).getPropertyValue('--tz')) || 0;
                        const factor = tz / 100;
                        gsap.to(layer, {
                            x: dx * 20 * factor,
                            y: dy * 20 * factor,
                            duration: 1.2,
                            ease: "power3.out"
                        });
                    });
                });
            });

            dashboardWrapper.addEventListener("mouseleave", () => {
                gsap.to(dashboard, {
                    rotateX: 10,
                    rotateY: -5,
                    duration: 1.5,
                    ease: "elastic.out(1, 0.6)"
                });
                layers.forEach(layer => {
                    gsap.to(layer, { x: 0, y: 0, duration: 1.5, ease: "power3.out" });
                });
            });
        }

        // Marquee Animation
        gsap.to(".marquee-track", {
            x: "-50%",
            ease: "none",
            duration: 25,
            repeat: -1
        });

        // FCT Reactor Core Exploding Animation
        const fctTl = gsap.timeline({
            scrollTrigger: {
                trigger: "#data",
                start: "top 60%",
            }
        });

        // 1. Core drops in and pulses
        fctTl.from(".reactor-core-center", {
            scale: 0,
            opacity: 0,
            duration: 1,
            ease: "back.out(1.5)"
        }, 0);

        // 2. SVG Connectors flash in
        fctTl.from(".reactor-line", {
            opacity: 0,
            duration: 0.8,
            stagger: 0.1,
            ease: "power2.out"
        }, "-=0.5");

        // 3. Orbs explode outwards from the core to their orbits
        fctTl.from(".reactor-node", {
            left: "180px",
            top: "180px",
            scale: 0.1,
            opacity: 0,
            duration: 1.5,
            stagger: 0.1,
            ease: "elastic.out(1.2, 0.6)"
        }, "-=0.8");

        // 4. Spec items slide in alongside the explosion
        fctTl.from(".data-specs .spec-item", {
            x: 50,
            opacity: 0,
            duration: 0.8,
            stagger: 0.15,
            ease: "power3.out"
        }, "-=1.5");

        // Bento Grid Items Reveal
        gsap.from(".bento-item", {
            scrollTrigger: {
                trigger: ".bento-grid",
                start: "top 80%",
            },
            y: 100,
            z: -200,
            opacity: 0,
            scale: 0.9,
            stagger: 0.15,
            duration: 1.2,
            ease: "expo.out",
            force3D: true
        });

        // Bento Hover Physics (Liquid Tension)
        const bentoItems = document.querySelectorAll(".bento-item");
        bentoItems.forEach(item => {
            item.addEventListener("mouseenter", () => {
                gsap.to(item, { 
                    scale: 1.02, 
                    y: -5,
                    duration: 0.8, 
                    ease: "elastic.out(1, 0.75)" 
                });
            });
            item.addEventListener("mouseleave", () => {
                gsap.to(item, { 
                    scale: 1, 
                    y: 0,
                    duration: 0.8, 
                    ease: "elastic.out(1, 0.75)" 
                });
            });
        });

        // Footer CTA Reveal
        gsap.from(".cta-container", {
            scrollTrigger: {
                trigger: ".footer-cta",
                start: "top 80%",
            },
            scale: 0.9,
            opacity: 0,
            duration: 1,
            ease: "back.out(1.5)"
        });
    }
});
