/* ============================================================
   NUTRIDEQ — DASHBOARD GSAP ENGINE v3
   All 6 Level-Up Features:
     1. Entrance sequence
     2. Number counter roll-up
     3. 3D Tilt on bento cards
     4. Hero title word cascade
     5. Scroll progress bar
     6. Sparkline mini-charts
   ============================================================ */

(function () {
    'use strict';

    if (typeof gsap === 'undefined') {
        console.warn('[NutriDeq] GSAP not loaded.');
        return;
    }

    const isMobile = window.innerWidth < 768;
    const isTouchDevice = ('ontouchstart' in window) || navigator.maxTouchPoints > 0;

    /* ═══════════════════════════════════════════════════════════
       FEATURE 6 — SCROLL PROGRESS BAR (run first, no deps)
    ═══════════════════════════════════════════════════════════ */
    const scrollBar = document.getElementById('nd-scroll-bar');
    if (scrollBar) {
        const updateScrollBar = () => {
            const scrollTop  = window.scrollY || document.documentElement.scrollTop;
            const docHeight  = document.documentElement.scrollHeight - window.innerHeight;
            const pct        = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
            scrollBar.style.width = pct + '%';
        };
        window.addEventListener('scroll', updateScrollBar, { passive: true });
        updateScrollBar();
    }


    /* ═══════════════════════════════════════════════════════════
       FEATURE 5 — HERO TITLE WORD CASCADE
    ═══════════════════════════════════════════════════════════ */
    const heroH1 = document.querySelector('.dash-hero-content h1');
    if (heroH1) {
        const words = heroH1.textContent.trim().split(/\s+/);
        heroH1.innerHTML = words.map(w =>
            `<span class="word"><span class="word-inner">${w}</span></span>`
        ).join(' ');

        gsap.to('.dash-hero-content h1 .word-inner', {
            y: 0,
            opacity: 1,
            duration: 0.65,
            stagger: 0.08,
            ease: 'expo.out',
            delay: 0.15
        });
    }


    /* ═══════════════════════════════════════════════════════════
       FEATURE 1 — ENTRANCE SEQUENCE (fromTo = always resolves)
    ═══════════════════════════════════════════════════════════ */
    const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });

    const heroRibbon = document.querySelector('.dash-hero-ribbon');
    if (heroRibbon) {
        tl.fromTo(heroRibbon,
            { y: -28, opacity: 0, scale: 0.98 },
            { y: 0, opacity: 1, scale: 1, duration: 0.8, ease: 'expo.out' }
        );
    }

    const bentoStats = document.querySelectorAll('.bento-stat');
    if (bentoStats.length) {
        tl.fromTo(bentoStats,
            { y: 32, opacity: 0, scale: 0.95 },
            { y: 0, opacity: 1, scale: 1, stagger: 0.07, duration: 0.6 },
            '-=0.5'
        );
    }

    const dashPanels = document.querySelectorAll('.dash-panel');
    if (dashPanels.length) {
        tl.fromTo(dashPanels,
            { y: 24, opacity: 0 },
            { y: 0, opacity: 1, stagger: 0.08, duration: 0.65 },
            '-=0.4'
        );
    }

    const mgmtSections = document.querySelectorAll('.management-section');
    if (mgmtSections.length) {
        tl.fromTo(mgmtSections,
            { y: 24, opacity: 0 },
            { y: 0, opacity: 1, stagger: 0.07, duration: 0.6 },
            '-=0.3'
        );
    }

    const cmdTiles = document.querySelectorAll('.command-tile');
    if (cmdTiles.length) {
        tl.fromTo(cmdTiles,
            { y: 20, opacity: 0 },
            { y: 0, opacity: 1, stagger: 0.06, duration: 0.55 },
            '-=0.3'
        );
    }

    const dashRows = document.querySelectorAll('.dash-row');
    if (dashRows.length) {
        tl.fromTo(dashRows,
            { y: 20, opacity: 0 },
            { y: 0, opacity: 1, stagger: 0.08, duration: 0.6 },
            '-=0.5'
        );
    }


    /* ═══════════════════════════════════════════════════════════
       FEATURE 2 — NUMBER COUNTER ROLL-UP
    ═══════════════════════════════════════════════════════════ */
    document.querySelectorAll('.bento-stat-val').forEach(el => {
        const raw = el.textContent.trim();
        const num = parseFloat(raw.replace(/[^\d.]/g, ''));
        if (isNaN(num) || num === 0) return;

        const suffix  = raw.replace(/[\d.]/g, '');
        const isFloat = raw.includes('.');
        const decimals = isFloat ? (raw.split('.')[1]?.length || 1) : 0;
        const proxy = { val: 0 };

        el.classList.add('counting');

        gsap.to(proxy, {
            val: num,
            duration: 1.5,
            delay: 0.5,
            ease: 'power2.out',
            onUpdate: () => {
                el.textContent = isFloat
                    ? proxy.val.toFixed(decimals) + suffix
                    : Math.round(proxy.val) + suffix;
            },
            onComplete: () => {
                el.textContent = raw;
                el.classList.remove('counting');
            }
        });
    });


    /* ═══════════════════════════════════════════════════════════
       FEATURE 3 — 3D TILT ON BENTO STAT CARDS
       (Desktop only — skip touch devices)
    ═══════════════════════════════════════════════════════════ */
    if (!isTouchDevice && !isMobile) {
        bentoStats.forEach(card => {
            const icon = card.querySelector('.bento-stat-icon');

            // Use quickTo for silky-smooth tilt tracking
            const setRotX = gsap.quickTo(card, 'rotationX', { duration: 0.5, ease: 'power2.out' });
            const setRotY = gsap.quickTo(card, 'rotationY', { duration: 0.5, ease: 'power2.out' });

            card.addEventListener('mousemove', (e) => {
                const rect  = card.getBoundingClientRect();
                const cx    = rect.left + rect.width  / 2;
                const cy    = rect.top  + rect.height / 2;
                const dx    = (e.clientX - cx) / (rect.width  / 2);  // -1 to 1
                const dy    = (e.clientY - cy) / (rect.height / 2);  // -1 to 1

                setRotX(-dy * 6);   // max ±6°
                setRotY( dx * 6);

                // Lift on hover
                gsap.to(card, { y: -8, scale: 1.02, duration: 0.3, ease: 'power2.out' });
                if (icon) gsap.to(icon, { scale: 1.15, rotation: 5, duration: 0.3, ease: 'back.out(2)' });
            });

            card.addEventListener('mouseleave', () => {
                setRotX(0);
                setRotY(0);
                gsap.to(card, { y: 0, scale: 1, rotationX: 0, rotationY: 0, duration: 0.55, ease: 'expo.out' });
                if (icon) gsap.to(icon, { scale: 1, rotation: 0, duration: 0.35, ease: 'power2.out' });
            });
        });
    } else {
        // Mobile fallback: simple lift
        bentoStats.forEach(card => {
            card.addEventListener('touchstart', () => {
                gsap.to(card, { scale: 0.97, duration: 0.15, ease: 'power2.in' });
            }, { passive: true });
            card.addEventListener('touchend', () => {
                gsap.to(card, { scale: 1, duration: 0.3, ease: 'back.out(2)' });
            }, { passive: true });
        });
    }


    /* ─── Command Tiles Hover ─── */
    cmdTiles.forEach(tile => {
        const icon = tile.querySelector('.command-tile-icon');
        tile.addEventListener('mouseenter', () => {
            gsap.to(tile, { y: -10, scale: 1.02, duration: 0.3, ease: 'power2.out' });
            if (icon) gsap.to(icon, { scale: 1.1, duration: 0.28, ease: 'back.out(2)' });
        });
        tile.addEventListener('mouseleave', () => {
            gsap.to(tile, { y: 0, scale: 1, duration: 0.4, ease: 'expo.out' });
            if (icon) gsap.to(icon, { scale: 1, duration: 0.3, ease: 'power2.out' });
        });
    });

    /* ─── Dash Panels Hover ─── */
    dashPanels.forEach(panel => {
        panel.addEventListener('mouseenter', () => {
            gsap.to(panel, { y: -4, duration: 0.28, ease: 'power2.out' });
        });
        panel.addEventListener('mouseleave', () => {
            gsap.to(panel, { y: 0, duration: 0.35, ease: 'expo.out' });
        });
    });

    /* ─── Activity items ─── */
    document.querySelectorAll('.activity-item').forEach(item => {
        item.addEventListener('mouseenter', () => {
            gsap.to(item, { x: 6, duration: 0.22, ease: 'power2.out' });
        });
        item.addEventListener('mouseleave', () => {
            gsap.to(item, { x: 0, duration: 0.3, ease: 'expo.out' });
        });
    });


    /* ═══════════════════════════════════════════════════════════
       FEATURE 4 — SPARKLINE MINI-CHARTS
       Renders tiny decorative sparklines in each bento stat card
    ═══════════════════════════════════════════════════════════ */
    const sparklineConfigs = [
        { id: 'spark-0', color: '#059669' },
        { id: 'spark-1', color: '#4f46e5' },
        { id: 'spark-2', color: '#d97706' },
        { id: 'spark-3', color: '#e11d48' },
    ];

    // Generate a convincing upward-trend random dataset
    const genSparkData = (base, length = 10, trend = 1) => {
        const data = [];
        let v = base;
        for (let i = 0; i < length; i++) {
            v += (Math.random() - 0.35) * 3 * trend;
            v = Math.max(1, v);
            data.push(parseFloat(v.toFixed(1)));
        }
        return data;
    };

    if (typeof Chart !== 'undefined') {
        sparklineConfigs.forEach(({ id, color }, idx) => {
            const canvas = document.getElementById(id);
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            const data = genSparkData(5 + idx * 2, 10);

            // Gradient fill
            const grad = ctx.createLinearGradient(0, 0, 0, canvas.height);
            grad.addColorStop(0, color + '55');
            grad.addColorStop(1, color + '00');

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map((_, i) => i),
                    datasets: [{
                        data,
                        borderColor: color,
                        borderWidth: 2,
                        pointRadius: 0,
                        fill: true,
                        backgroundColor: grad,
                        tension: 0.45,
                    }]
                },
                options: {
                    animation: {
                        duration: 1200,
                        easing: 'easeInOutQuart',
                        delay: (ctx) => ctx.dataIndex * 80
                    },
                    responsive: false,
                    plugins: { legend: { display: false }, tooltip: { enabled: false } },
                    scales: {
                        x: { display: false },
                        y: { display: false }
                    },
                    elements: { line: { borderCapStyle: 'round' } }
                }
            });
        });
    }


    /* ─── Scroll-triggered reveals for off-screen sections ─── */
    const scrollRevealEls = document.querySelectorAll(
        '.management-section:not(.stagger), .charts-section > *:not(.stagger)'
    );

    if ('IntersectionObserver' in window && scrollRevealEls.length) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    gsap.fromTo(entry.target,
                        { opacity: 0, y: 24 },
                        { opacity: 1, y: 0, duration: 0.65, ease: 'power3.out' }
                    );
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });

        scrollRevealEls.forEach(el => {
            const rect = el.getBoundingClientRect();
            const inView = rect.top < window.innerHeight && rect.bottom > 0;
            if (!inView) {
                gsap.set(el, { opacity: 0, y: 24 });
                observer.observe(el);
            }
        });
    }


    /* ─── Button press tactile feedback ─── */
    document.querySelectorAll('.btn, .btn-primary, .btn-outline').forEach(btn => {
        btn.addEventListener('mousedown', () => {
            gsap.to(btn, { scale: 0.95, duration: 0.1, ease: 'power2.in' });
        });
        const reset = () => gsap.to(btn, { scale: 1, duration: 0.3, ease: 'back.out(2)' });
        btn.addEventListener('mouseup', reset);
        btn.addEventListener('mouseleave', reset);
    });


    /* ─── Hero badge breathing glow ─── */
    const heroBadge = document.querySelector('.dash-hero-badge');
    if (heroBadge) {
        gsap.to(heroBadge, {
            boxShadow: '0 0 22px rgba(52,211,153,0.38)',
            duration: 1.8,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut',
            delay: 1
        });
    }

})();
