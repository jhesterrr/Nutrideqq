/**
 * NutriDeq Diet Reference Engine
 * Handles dynamic calculation and rendering of food exchanges.
 */

console.log('DietEngine script loaded');

const DietEngine = {
    // Macronutrient values per exchange unit
    EXCHANGE_CONSTANTS: {
        'vegetable': { kcal: 16, carbs: 3, protein: 1, fat: 0 },
        'fruit': { kcal: 40, carbs: 10, protein: 0, fat: 0 },
        'milk_whole': { kcal: 170, carbs: 12, protein: 8, fat: 10 },
        'milk_lowfat': { kcal: 125, carbs: 12, protein: 8, fat: 5 },
        'milk_nonfat': { kcal: 80, carbs: 12, protein: 8, fat: 0 },
        'rice_low': { kcal: 92, carbs: 23, protein: 0, fat: 0 },
        'rice_med': { kcal: 100, carbs: 23, protein: 2, fat: 0 },
        'rice_high': { kcal: 108, carbs: 23, protein: 4, fat: 0 },
        'meat_low': { kcal: 41, carbs: 0, protein: 8, fat: 1 },
        'meat_med': { kcal: 86, carbs: 0, protein: 8, fat: 6 },
        'meat_high': { kcal: 122, carbs: 0, protein: 8, fat: 10 },
        'fat': { kcal: 45, carbs: 0, protein: 0, fat: 5 },
        'sugar': { kcal: 20, carbs: 5, protein: 0, fat: 0 }
    },

    // Nutritionist-approved reference values
    REFERENCE_DIETS: {
        1200: { vegetable: 3, fruit: 3, milk_lowfat: 1, rice_low: 2, rice_med: 3.5, meat_low: 3, meat_med: 1, fat: 3, sugar: 4 },
        1300: { vegetable: 3, fruit: 4, milk_lowfat: 1, rice_low: 2, rice_med: 4, meat_low: 2, meat_med: 2, fat: 2, sugar: 3 },
        1400: { vegetable: 3, fruit: 4, milk_whole: 1, rice_low: 1, rice_med: 4.5, rice_high: 1, meat_low: 2, meat_med: 2, fat: 2, sugar: 3 },
        1500: { vegetable: 3, fruit: 5, milk_whole: 1, rice_low: 1, rice_med: 5, rice_high: 1, meat_low: 2, meat_med: 2, fat: 2, sugar: 3 },
        1600: { vegetable: 3, fruit: 6, milk_whole: 1, rice_low: 2, rice_med: 3, rice_high: 2, meat_low: 4, meat_med: 1, fat: 3, sugar: 4 },
        1700: { vegetable: 3, fruit: 5, milk_whole: 1, rice_low: 3, rice_med: 4, rice_high: 1, meat_low: 3.5, meat_med: 2, fat: 3, sugar: 5 },
        1800: { vegetable: 3, fruit: 5, milk_whole: 1, rice_low: 2, rice_med: 5, rice_high: 2, meat_low: 4, meat_med: 1, fat: 4, sugar: 3 },
        1900: { vegetable: 3, fruit: 6, milk_whole: 1, rice_low: 2, rice_med: 5, rice_high: 2, meat_low: 3.5, meat_med: 2, fat: 4, sugar: 4 },
        2000: { vegetable: 3, fruit: 6, milk_whole: 1, rice_low: 2, rice_med: 6, rice_high: 2, meat_low: 4, meat_med: 2, fat: 4, sugar: 4 },
        2100: { vegetable: 3, fruit: 6, milk_whole: 1, rice_low: 2, rice_med: 6, rice_high: 2, meat_low: 4, meat_med: 2, fat: 5, sugar: 5 },
        2200: { vegetable: 3, fruit: 6.5, milk_whole: 1, rice_low: 2.5, rice_med: 6, rice_high: 2, meat_low: 5, meat_med: 2, fat: 5, sugar: 6 },
        2300: { vegetable: 3, fruit: 7, milk_whole: 1, rice_low: 3, rice_med: 6, rice_high: 2, meat_low: 5, meat_med: 2, fat: 5, sugar: 6 },
        2400: { vegetable: 3, fruit: 6, milk_whole: 2, rice_low: 2, rice_med: 8, rice_high: 2, meat_low: 5, meat_med: 1, fat: 4, sugar: 4 }
    },

    /**
     * Calculate macro distribution for a set of exchanges
     */
    calculateMacros(exchanges) {
        let totals = { kcal: 0, carbs: 0, protein: 0, fat: 0 };
        for (const [group, count] of Object.entries(exchanges)) {
            if (this.EXCHANGE_CONSTANTS[group]) {
                const c = this.EXCHANGE_CONSTANTS[group];
                totals.kcal += c.kcal * count;
                totals.carbs += c.carbs * count;
                totals.protein += c.protein * count;
                totals.fat += c.fat * count;
            }
        }
        return totals;
    },

    /**
     * Render the modern calorie reference interface
     */
    renderTable(containerId, activeCalories = 1600) {
        console.log('renderTable called with', containerId, activeCalories);
        const container = document.getElementById(containerId);
        if (!container) {
            console.error('Container not found:', containerId);
            return;
        }

        const calories = Object.keys(this.REFERENCE_DIETS).map(Number);
        const groups = this.getGroupData();
        const diet = this.REFERENCE_DIETS[activeCalories];
        const totals = this.calculateMacros(diet);

        let html = `
            <div class="diet-ref-modern">
                <!-- Calorie Overview Cards -->
                <div class="calorie-overview-bar">
                    <div class="calorie-main-display">
                        <div class="calorie-big-number" id="calorieDisplay">${activeCalories}</div>
                        <div class="calorie-label">CALORIES</div>
                    </div>
                    <div class="macro-mini-cards">
                        <div class="macro-mini-card carb">
                            <i class="fas fa-apple-alt"></i>
                            <span class="macro-mini-val">${totals.carbs}g</span>
                            <span class="macro-mini-label">Carbs</span>
                        </div>
                        <div class="macro-mini-card protein">
                            <i class="fas fa-drumstick-bite"></i>
                            <span class="macro-mini-val">${totals.protein}g</span>
                            <span class="macro-mini-label">Protein</span>
                        </div>
                        <div class="macro-mini-card fat">
                            <i class="fas fa-droplet"></i>
                            <span class="macro-mini-val">${totals.fat}g</span>
                            <span class="macro-mini-label">Fat</span>
                        </div>
                    </div>
                </div>

                <!-- Modern Slider Selector -->
                <div class="calorie-slider-wrap">
                    <input type="range" class="calorie-range-slider" id="calorieRangeSlider"
                           min="${Math.min(...calories)}" max="${Math.max(...calories)}"
                           step="100" value="${activeCalories}"
                           oninput="DietEngine.onSliderInput(this.value)">
                    <div class="slider-labels">
                        ${calories.filter((_, i) => i % 2 === 0).map(cal => `<span>${cal}</span>`).join('')}
                    </div>
                </div>

                <!-- Food Groups Grid -->
                <div class="food-groups-grid">
        `;

        groups.forEach(group => {
            const groupTotal = group.items.reduce((sum, item) => {
                return sum + (diet[item.id] || 0);
            }, 0);

            html += `
                <div class="food-group-card" style="--group-color: ${group.color};" data-group="${group.id}">
                    <div class="fg-card-header">
                        <div class="fg-icon" style="background: ${group.color}20; color: ${group.color};">
                            <i class="fas ${group.icon}"></i>
                        </div>
                        <div class="fg-title">${group.name}</div>
                        <div class="fg-total-badge" style="background: ${group.color}15; color: ${group.color};">
                            ${groupTotal.toFixed(1)} ex
                        </div>
                    </div>
                    <div class="fg-items">
            `;

            group.items.forEach(item => {
                const val = diet[item.id] || 0;
                if (val === 0) return;
                const pct = Math.min((val / 8) * 100, 100); // 8 exchanges = full bar
                html += `
                    <div class="fg-item-row">
                        <span class="fg-item-name">${item.name}</span>
                        <div class="fg-bar-track">
                            <div class="fg-bar-fill" style="width: ${pct}%; background: ${group.color};" data-target-width="${pct}"></div>
                        </div>
                        <span class="fg-item-val">${val}</span>
                    </div>
                `;
            });

            html += `
                    </div>
                </div>
            `;
        });

        html += `
                </div>
            </div>
        `;

        container.innerHTML = html;


        // Sync other views
        this.renderMobilePills('mobilePillContainer', activeCalories);
        this.renderMobileCards('mobileCalorieCards', activeCalories);
        this.updateMacroStats(activeCalories);
        this.initTableInteractions();
    },

    /**
     * Handle slider input with immediate display update and debounced heavy re-render
     */
    onSliderInput(val) {
        const num = parseInt(val);
        const calorieDisplay = document.getElementById('calorieDisplay');
        const slider = document.getElementById('calorieRangeSlider');

        // Immediate visual feedback: update calorie display instantly
        if (calorieDisplay) {
            if (typeof gsap !== 'undefined') {
                gsap.to(calorieDisplay, {
                    scale: 1.15,
                    duration: 0.1,
                    ease: "power2.out",
                    overwrite: "auto",
                    onComplete: () => {
                        calorieDisplay.textContent = num;
                        gsap.to(calorieDisplay, {
                            scale: 1,
                            duration: 0.25,
                            ease: "back.out(2)",
                            overwrite: "auto"
                        });
                    }
                });
            } else {
                calorieDisplay.textContent = num;
            }
        }

        // Sync selector immediately for consistency
        const selector = document.getElementById('calorieSelector');
        if (selector) selector.value = num;

        // Debounce the heavy re-render (reduced from 150ms to 60ms for snappier feel)
        clearTimeout(this._sliderTimeout);
        this._sliderTimeout = setTimeout(() => {
            this.selectCalorie(num, true); // true = skip sync (already done)
        }, 60);
    },

    /**
     * Render scrollable pills for mobile calorie selection
     */
    renderMobilePills(containerId, activeCalories) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const calories = Object.keys(this.REFERENCE_DIETS).map(Number);
        
        container.innerHTML = `
            <div class="pill-slider-label">Select Calorie Level</div>
            <div class="mobile-pill-slider scroll-hide">
                ${calories.map(cal => `
                    <div class="calorie-pill ${cal === activeCalories ? 'active' : ''}" 
                         data-value="${cal}" 
                         onclick="DietEngine.selectCalorie(${cal})">${cal}</div>
                `).join('')}
            </div>
        `;

        // Scroll active pill into view
        const activePill = container.querySelector('.calorie-pill.active');
        if (activePill) {
            activePill.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
        }
    },

    /**
     * Render detailed cards for mobile view
     */
    renderMobileCards(containerId, activeCalories) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const diet = this.REFERENCE_DIETS[activeCalories];
        const groups = this.getGroupData();

        let html = '';
        groups.forEach(group => {
            const activeItems = group.items.filter(item => diet[item.id] && diet[item.id] !== '-');
            if (activeItems.length === 0) return;

            html += `<div class="mcv-category-header"><span>${group.name}</span></div>`;
            activeItems.forEach(item => {
                html += `
                    <div class="mcv-row">
                        <span class="mcv-label">${item.name}</span>
                        <span class="mcv-value">${diet[item.id]}</span>
                    </div>
                `;
            });
        });

        container.innerHTML = html;
    },

    /**
     * Update macro stats with SVG donut chart visualization
     */
    updateMacroStats(calories) {
        const diet = this.REFERENCE_DIETS[calories];
        const totals = this.calculateMacros(diet);
        
        const carbCals = totals.carbs * 4;
        const proteinCals = totals.protein * 4;
        const fatCals = totals.fat * 9;
        const totalCals = carbCals + proteinCals + fatCals;

        const carbPct = Math.round((carbCals / totalCals) * 100);
        const proteinPct = Math.round((proteinCals / totalCals) * 100);
        const fatPct = 100 - carbPct - proteinPct; // Ensure they sum to 100

        // SVG Donut parameters
        const size = 180;
        const cx = size / 2;
        const cy = size / 2;
        const radius = 68;
        const strokeWidth = 18;
        const circumference = 2 * Math.PI * radius;

        const segments = [
            { name: 'Carbs', grams: totals.carbs, pct: carbPct, color: '#3b82f6', bgColor: 'rgba(59,130,246,0.08)' },
            { name: 'Protein', grams: totals.protein, pct: proteinPct, color: '#ef4444', bgColor: 'rgba(239,68,68,0.08)' },
            { name: 'Fat', grams: totals.fat, pct: fatPct, color: '#f59e0b', bgColor: 'rgba(245,158,11,0.08)' }
        ];

        let cumulativeOffset = 0;
        let arcsHtml = '';
        segments.forEach((seg, i) => {
            const segLen = (seg.pct / 100) * circumference;
            const gap = 4;
            const dash = Math.max(segLen - gap, 0);
            arcsHtml += `
                <circle class="donut-segment" data-index="${i}"
                    cx="${cx}" cy="${cy}" r="${radius}"
                    fill="none" stroke="${seg.color}" stroke-width="${strokeWidth}"
                    stroke-dasharray="${dash} ${circumference - dash}"
                    stroke-dashoffset="${-cumulativeOffset}"
                    stroke-linecap="round"
                    transform="rotate(-90 ${cx} ${cy})"
                    style="transition: stroke-dasharray 0.8s cubic-bezier(0.16, 1, 0.3, 1), stroke-dashoffset 0.8s cubic-bezier(0.16, 1, 0.3, 1);"
                />
            `;
            cumulativeOffset += segLen;
        });

        const donutSvg = `
            <svg class="macro-donut-svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
                <circle cx="${cx}" cy="${cy}" r="${radius}" fill="none" stroke="#f1f5f9" stroke-width="${strokeWidth}" />
                ${arcsHtml}
                <text x="${cx}" y="${cy - 6}" text-anchor="middle" class="macro-donut-center-label" font-size="22">${Math.round(totals.kcal)}</text>
                <text x="${cx}" y="${cy + 14}" text-anchor="middle" class="macro-donut-center-sub" font-size="11">kcal</text>
            </svg>
        `;

        const legendHtml = segments.map(seg => `
            <div class="macro-legend-item">
                <div class="macro-legend-dot" style="background:${seg.color};"></div>
                <div class="macro-legend-info">
                    <span class="macro-legend-name">${seg.name}</span>
                    <span class="macro-legend-value">${seg.grams}g</span>
                </div>
                <span class="macro-legend-pct" style="background:${seg.bgColor}; color:${seg.color};">${seg.pct}%</span>
            </div>
        `).join('');

        const statsContainer = document.getElementById('dietMacroStats');
        if (statsContainer) {
            statsContainer.innerHTML = `
                <div class="macro-donut-container">
                    ${donutSvg}
                    <div class="macro-donut-legend">
                        ${legendHtml}
                    </div>
                </div>
            `;

            // Animate donut segments entrance
            if (typeof gsap !== 'undefined') {
                gsap.fromTo(statsContainer.querySelectorAll('.donut-segment'), 
                    { strokeDasharray: `0 ${circumference}` },
                    { strokeDasharray: function(i, target) { return target.getAttribute('stroke-dasharray'); }, duration: 1.2, ease: "power3.out", stagger: 0.1, overwrite: "auto" }
                );
                gsap.fromTo(statsContainer.querySelectorAll('.macro-legend-item'), 
                    { opacity: 0, x: 20 },
                    { opacity: 1, x: 0, duration: 0.5, ease: "power2.out", stagger: 0.1, delay: 0.2, overwrite: true }
                );
            }
        }
    },

    /**
     * Select a new calorie level and re-render the whole UI.
     * Handles the native selector, the range slider, the big calorie number,
     * and all dependent cards/visuals with smooth GSAP animations.
     */
    selectCalorie(val, skipSync = false) {
        // If not skipping sync, update native <select> and slider values
        if (!skipSync) {
            const selector = document.getElementById('calorieSelector');
            if (selector) selector.value = val;

            const slider = document.getElementById('calorieRangeSlider');
            if (slider) slider.value = val;
        }

        // Update the big calorie number (with GSAP animation if available)
        const calorieDisplay = document.getElementById('calorieDisplay');
        if (calorieDisplay && typeof gsap !== 'undefined') {
            gsap.to(calorieDisplay, {
                scale: 1.2,
                duration: 0.15,
                ease: "power2.out",
                onComplete: () => {
                    calorieDisplay.textContent = val;
                    gsap.to(calorieDisplay, { scale: 1, duration: 0.3, ease: "back.out(2)" });
                }
            });
        } else if (calorieDisplay) {
            calorieDisplay.textContent = val;
        }

        // Re-render the main container (renderTable includes its own entrance animations)
        this.renderTable('dietTableContainer', val);

        // Sync other UI components (already called inside renderTable, but safe to call again)
        this.renderMobilePills('mobilePillContainer', val);
        this.renderMobileCards('mobileCalorieCards', val);
        this.updateMacroStats(val);
    },

    getGroupData() {
        return [
            { id: 'vegetables', name: 'VEGETABLES', icon: 'fa-carrot', color: '#10b981', items: [{ id: 'vegetable', name: 'Vegetable++' }] },
            { id: 'fruits', name: 'FRUITS', icon: 'fa-apple-alt', color: '#ef4444', items: [{ id: 'fruit', name: 'Reference Exchange' }] },
            { id: 'milk', name: 'MILK', icon: 'fa-glass-whiskey', color: '#3b82f6', items: [{ id: 'milk_whole', name: 'Whole Milk' }, { id: 'milk_lowfat', name: 'Low Fat' }, { id: 'milk_nonfat', name: 'Non-Fat Milk' }] },
            { id: 'rice', name: 'RICE', icon: 'fa-bread-slice', color: '#f59e0b', items: [{ id: 'rice_low', name: 'Low Protein' }, { id: 'rice_med', name: 'Medium Protein' }, { id: 'rice_high', name: 'High Protein' }] },
            { id: 'meat', name: 'MEAT', icon: 'fa-drumstick-bite', color: '#8b5cf6', items: [{ id: 'meat_low', name: 'Low Fat' }, { id: 'meat_med', name: 'Medium Fat' }, { id: 'meat_high', name: 'High Fat' }] },
            { id: 'fat', name: 'FAT', icon: 'fa-droplet', color: '#64748b', items: [{ id: 'fat', name: 'Reference Exchange' }] },
            { id: 'sugar', name: 'SUGAR', icon: 'fa-cubes-stacked', color: '#38bdf8', items: [{ id: 'sugar', name: 'Reference Exchange' }] }
        ];
    },

    /**
     * Initialize GSAP-powered modern card interactions
     */
    initTableInteractions() {
        if (typeof gsap === 'undefined') return;

        // Animate food group cards entrance
        const cards = document.querySelectorAll('#dietTableContainer .food-group-card');
        gsap.fromTo(cards, 
            { opacity: 0, y: 20, scale: 0.95 },
            { opacity: 1, y: 0, scale: 1, duration: 0.5, stagger: 0.04, ease: "power2.out", overwrite: true, clearProps: "transform" }
        );

        // Animate macro mini-cards
        const miniCards = document.querySelectorAll('.macro-mini-card');
        if (miniCards.length > 0) {
            gsap.fromTo(miniCards, 
                { opacity: 0, y: 15, scale: 0.9 },
                { opacity: 1, y: 0, scale: 1, duration: 0.4, stagger: 0.05, ease: "back.out(1.7)", delay: 0.1, overwrite: true, clearProps: "transform" }
            );
        }

        // Animate calorie overview bar
        const overviewBar = document.querySelector('.calorie-overview-bar');
        if (overviewBar) {
            gsap.fromTo(overviewBar, 
                { opacity: 0, y: -20 },
                { opacity: 1, y: 0, duration: 0.6, ease: "power2.out", overwrite: true, clearProps: "transform" }
            );
        }

        // Animate slider
        const sliderWrap = document.querySelector('.calorie-slider-wrap');
        if (sliderWrap) {
            gsap.fromTo(sliderWrap, 
                { opacity: 0, y: 10 },
                { opacity: 1, y: 0, duration: 0.4, delay: 0.1, ease: "power2.out", overwrite: true, clearProps: "transform" }
            );
        }

        // Card hover interactions
        cards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                gsap.to(card, {
                    y: -4,
                    scale: 1.01,
                    boxShadow: '0 15px 35px rgba(0,0,0,0.06)',
                    duration: 0.3,
                    overwrite: "auto"
                });
            });
            card.addEventListener('mouseleave', () => {
                gsap.to(card, {
                    y: 0,
                    scale: 1,
                    boxShadow: '0 10px 25px rgba(0,0,0,0.02)',
                    duration: 0.4,
                    overwrite: "auto"
                });
            });
        });

        // Initialize bar animations
        this.animateBars();
    },

    /**
     * Animate the progress bars with staggered timing
     */
    animateBars() {
        if (typeof gsap === 'undefined') {
            // Fallback: simple CSS animation
            document.querySelectorAll('.fg-bar-fill').forEach((bar, index) => {
                const target = bar.dataset.targetWidth;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.transition = 'width 0.8s cubic-bezier(0.16, 1, 0.3, 1)';
                    bar.style.width = target + '%';
                }, index * 50);
            });
            return;
        }

        // GSAP animated bars
        document.querySelectorAll('.fg-bar-fill').forEach((bar, index) => {
            const targetWidth = bar.dataset.targetWidth + '%';
            gsap.fromTo(bar, 
                { width: '0%' },
                { width: targetWidth, duration: 0.8, ease: "power2.out", delay: index * 0.05 + 0.2, overwrite: true }
            );
        });
    }
};

window.DietEngine = DietEngine;
