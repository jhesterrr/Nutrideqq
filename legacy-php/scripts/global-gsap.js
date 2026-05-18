/* ================================================================
   NUTRIDEQ — GLOBAL GSAP ENGINE v2
   Runs on EVERY dashboard page via sidebar.php.
   ================================================================ */

(function () {
    'use strict';

    if (typeof gsap === 'undefined') return;

    const isMobile = window.innerWidth < 768;
    const isTouch  = ('ontouchstart' in window) || navigator.maxTouchPoints > 0;

    /* ─── 1. SCROLL PROGRESS BAR ─── */
    const scrollBar = document.getElementById('nd-scroll-bar');
    if (scrollBar) {
        const tick = () => {
            const sTop  = window.scrollY || document.documentElement.scrollTop;
            const total = document.documentElement.scrollHeight - window.innerHeight;
            scrollBar.style.width = (total > 0 ? (sTop / total) * 100 : 0) + '%';
        };
        window.addEventListener('scroll', tick, { passive: true });
        tick();
    }

    /* ─── 2. HERO TITLE WORD CASCADE (Elite Animation) ─── */
    const heroH1 = document.querySelector('.dash-hero-content h1');
    if (heroH1 && !heroH1.dataset.cascaded) {
        heroH1.dataset.cascaded = '1';
        const words = heroH1.textContent.trim().split(/\s+/);
        heroH1.innerHTML = words.map(w =>
            `<span class="word"><span class="word-inner">${w}</span></span>`
        ).join(' ');

        gsap.to('.dash-hero-content h1 .word-inner', {
            y: 0, opacity: 1,
            duration: 0.65,
            stagger: 0.08,
            ease: 'expo.out',
            delay: 0.15
        });
    }

    /* ─── 3. UNIVERSAL STAGGER ENTRANCE ─── */
    const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });

    const seq = (sel, opts, pos = '-=0.4') => {
        const els = document.querySelectorAll(sel);
        if (!els.length) return;
        // Fix initial opacity for GSAP
        gsap.set(els, { visibility: 'visible' }); 
        tl.fromTo(els,
            { y: opts.y ?? 30, opacity: 0, scale: opts.sc ?? 1 },
            { y: 0, opacity: 1, scale: 1, stagger: opts.st ?? 0.08, duration: opts.dur ?? 0.65 },
            pos
        );
    };

    seq('.dash-hero-ribbon',   { y: -30, dur: 0.8, sc: 0.98 }, '0');
    seq('.bento-stat',         { y: 40,  dur: 0.65, sc: 0.95, st: 0.08 });
    seq('.command-tile',       { y: 25,  dur: 0.6, st: 0.07 });
    seq('.quick-action-card',  { y: 25,  dur: 0.6, st: 0.07 });
    seq('.dash-panel',         { y: 30,  dur: 0.7, st: 0.1 });
    seq('.dash-row',           { y: 25,  dur: 0.6, st: 0.1 });
    seq('.stat-card',          { y: 30,  dur: 0.6, sc: 0.96, st: 0.08 });
    seq('.management-section', { y: 20,  dur: 0.6 });

    /* ─── 4. 3D TILT EFFECT (Desktop) ─── */
    if (!isTouch && !isMobile) {
        const tiltTargets = '.bento-stat, .stat-card, .command-tile, .quick-action-card, .dash-panel.nutri-glass';
        document.querySelectorAll(tiltTargets).forEach(card => {
            const icon = card.querySelector('.bento-stat-icon, .stat-icon, .command-tile-icon, i');
            const qX = gsap.quickTo(card, 'rotationX', { duration: 0.5, ease: 'power2.out' });
            const qY = gsap.quickTo(card, 'rotationY', { duration: 0.5, ease: 'power2.out' });

            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const dx = (e.clientX - rect.left - rect.width / 2) / (rect.width / 2);
                const dy = (e.clientY - rect.top - rect.height / 2) / (rect.height / 2);
                qX(-dy * 5); // Tilt vertical
                qY( dx * 5); // Tilt horizontal
                gsap.to(card, { 
                    y: -4, 
                    scale: 1.01, 
                    rotation: 0.01, // Hack to force subpixel rendering (prevents blur)
                    duration: 0.3, 
                    ease: 'power2.out',
                    force3D: false // Prevent rasterization blur
                });
                if (icon && icon.tagName === 'I') gsap.to(icon, { scale: 1.15, duration: 0.3 });

            });

            card.addEventListener('mouseleave', () => {
                qX(0); qY(0);
                gsap.to(card, { y: 0, scale: 1, duration: 0.5, ease: 'expo.out' });
                if (icon && icon.tagName === 'I') gsap.to(icon, { scale: 1, duration: 0.4 });
            });
        });
    }

    /* ─── 5. UNIVERSAL CLINical SPARKLINE GENERATOR ─── */
    const sparkColors = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];
    const sparkCanvases = document.querySelectorAll('.bento-sparkline');

    const genSparkData = (base, length = 12) => {
        return Array.from({ length }, (_, i) => {
            const angle = (i / (length - 1)) * Math.PI * 1.5;
            const sine = Math.sin(angle) * 4;
            const trend = i * 0.3;
            const noise = Math.sin(i * 1.7) * 1.2 + Math.random() * 0.8;
            return base + sine + trend + noise;
        });
    };

    if (typeof Chart !== 'undefined' && sparkCanvases.length) {
        sparkCanvases.forEach((canvas, i) => {
            // Dynamically retrieve color token from card container CSS property
            const card = canvas.closest('.bento-stat, .command-tile');
            let color = '#10b981';
            if (card) {
                const style = getComputedStyle(card);
                color = style.getPropertyValue('--sc').trim() || color;
            } else {
                color = canvas.dataset.color || sparkColors[i % sparkColors.length];
            }

            const ctx = canvas.getContext('2d');
            const data = genSparkData(10 + i * 2, 12);
            
            const grad = ctx.createLinearGradient(0, 0, 0, canvas.height);
            grad.addColorStop(0, color + '22');
            grad.addColorStop(1, 'transparent');

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map((_, j) => j),
                    datasets: [{
                        data: data,
                        borderColor: color,
                        borderWidth: 2.5,
                        fill: true,
                        backgroundColor: grad,
                        tension: 0.4,
                        pointRadius: data.map((_, idx) => idx === data.length - 1 ? 4.5 : 0),
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: color,
                        pointBorderWidth: 2.5,
                        pointHoverRadius: 0
                    }]
                },
                options: {
                    responsive: false,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { display: false }, 
                        tooltip: { enabled: false } 
                    },
                    scales: { 
                        x: { display: false }, 
                        y: { display: false } 
                    },
                    animation: { 
                        duration: 1800, 
                        easing: 'easeOutQuart', 
                        delay: i * 150 
                    }
                }
            });
        });
    }

    /* ─── 6. NUMBER COUNTER ROLL-UP ─── */
    const counters = '.bento-stat-val, .stat-value, .metric-value, .bmi-big-value';
    document.querySelectorAll(counters).forEach(el => {
        const val = parseFloat(el.textContent.replace(/[^\d.]/g, '')) || 0;
        if (val === 0) return;
        const suffix = el.textContent.replace(/[\d.]/g, '');
        const obj = { n: 0 };
        gsap.to(obj, {
            n: val, duration: 2, ease: 'power2.out', delay: 0.4,
            onUpdate: () => el.textContent = Math.round(obj.n) + suffix
        });
    });

    /* ─── 7. SCROLL REVEAL ─── */
    const revealEls = document.querySelectorAll('.dash-panel:not(.stagger), .management-section:not(.stagger), .dash-row:not(.stagger)');
    if ('IntersectionObserver' in window) {
        const obs = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    gsap.fromTo(e.target, { y: 30, opacity: 0 }, { y: 0, opacity: 1, duration: 0.8, ease: 'expo.out' });
                    obs.unobserve(e.target);
                }
            });
        }, { threshold: 0.1 });
        revealEls.forEach(el => {
            if (el.getBoundingClientRect().top > window.innerHeight) {
                gsap.set(el, { opacity: 0 });
                obs.observe(el);
            }
        });
    }

    /* ─── 8. BUTTON TACTILE INTERACTION ─── */
    document.querySelectorAll('.btn, .command-tile, .bento-stat').forEach(btn => {
        btn.addEventListener('mousedown', () => gsap.to(btn, { scale: 0.96, duration: 0.1 }));
        btn.addEventListener('mouseup', () => gsap.to(btn, { scale: 1, duration: 0.4, ease: 'back.out(2)' }));
    });

    /* ─── 9. HERO BADGE BREATHING ─── */
    const badge = document.querySelector('.dash-hero-badge');
    if (badge) {
        gsap.to(badge, {
            boxShadow: '0 0 20px rgba(16, 185, 129, 0.4)',
            duration: 2, repeat: -1, yoyo: true, ease: 'sine.inOut'
        });
    }

})();
