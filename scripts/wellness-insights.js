/**
 * scripts/wellness-insights.js
 * Live renderer for the Wellness Insights card (client + staff message pages).
 *
 * REAL-TIME MODEL (accuracy-first):
 *   The chat controller calls window.ndRenderWellnessInsights(insights) after
 *   every send response AND every 3s poll. The card therefore mirrors what the
 *   client is saying RIGHT NOW: NLP-detected symptoms appear the moment they
 *   are parsed, reported-resolved ones disappear, counts stay exact.
 *
 * HIDDEN-FIRST UX:
 *   The card never pops up over the conversation on its own. Instead:
 *     - A compact alert pill ("N symptoms detected — tap to view") appears.
 *     - Tapping the pill REVEALS the card (collapsed, Expand available).
 *     - A Hide button in the card header tucks it away again.
 *     - Any NEW detection while hidden re-arms the pill with a pulse ring;
 *       resolutions only update the count quietly (no false alarm).
 *
 * The renderer only touches COUNTS, CHIPS, CARD CONTENT and visibility —
 * expand/collapse handlers keep working (server-rendered cards carry the
 * global toggle functions; dynamically created ones replicate the same IDs).
 *
 * All content is EDUCATIONAL ONLY — not medical advice.
 */
(function () {
    'use strict';

    let lastSignature = null;   // null until the first payload arrives
    let lastKeywords = new Set();
    let lastConfirmedSyms = []; // Full authoritative snapshot (server data only)
    let lastCount = 0;
    let revealed = false;       // user clicked the alert pill or auto-popped -> card shown
    let autoDismissTimer = null;
    let pendingLocalSymptoms = [];
    let pendingSetAt = 0;       // timestamp (ms) when pending symptoms were last set
    const AUTO_DISMISS_DELAY = 12000;
    const PENDING_GRACE_PERIOD_MS = 45000;

    function clearAutoDismiss() {
        if (autoDismissTimer) {
            clearTimeout(autoDismissTimer);
            autoDismissTimer = null;
        }
    }

    function scheduleAutoDismiss(card, isUserView) {
        clearAutoDismiss();
        autoDismissTimer = setTimeout(() => {
            revealed = false;
            animateOutCard(card, isUserView, () => {
                const pill = document.getElementById('ciAlertPill');
                if (pill && lastCount > 0) showPill(pill, lastCount, isUserView, false);
            });
        }, AUTO_DISMISS_DELAY);
    }

    // ── Fluid motion helpers ──────────────────────────────────────────
    // All animations are transform/opacity only (GPU-composited, smooth on
    // mid-range mobile) and fully disabled under prefers-reduced-motion.
    const REDUCED = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /** Replay a CSS animation class on an element (restart-safe). */
    function animateIn(el, cls, ms) {
        if (!el || REDUCED) return;
        el.classList.remove(cls);
        void el.offsetWidth; // force reflow so the animation restarts
        el.classList.add(cls);
        setTimeout(() => el.classList.remove(cls), ms);
    }

    function cancelCardExit(card) {
        if (!card) return;
        if (card.dataset.ciExitTimer) {
            clearTimeout(Number(card.dataset.ciExitTimer));
            delete card.dataset.ciExitTimer;
        }
        card.classList.remove('ci-card-exit');
        delete card.dataset.ciAnimating;
    }

    function minimizeCard(card, isUserView) {
        if (!card) return;
        card.classList.remove('ci-expanded');
        card.classList.add('ci-collapsed');
        const button = card.querySelector(isUserView ? '#userCiToggleBtn' : '#ciToggleBtn');
        if (button) {
            button.setAttribute('aria-expanded', 'false');
            button.title = 'Expand for details';
            button.classList.remove('is-open');
            const icon = button.querySelector('i');
            const label = button.querySelector('.ci-toggle-label');
            if (icon) icon.className = 'fas fa-chevron-down';
            if (label) label.textContent = 'Expand';
        }
        const body = document.getElementById(isUserView ? 'userCiCardBody' : 'ciCardBody');
        if (body) body.classList.remove('ci-body-exit');
    }

    /**
     * Hide the card with a quick fade/slide-out, then flip display.
     * `done` runs after the card is fully hidden (pill resurface hooks here).
     */
    function animateOutCard(card, isUserView, done) {
        const disc = document.querySelector('.user-insight-disclaimer');
        const finish = () => {
            card.style.display = 'none';
            card.classList.remove('ci-card-exit');
            delete card.dataset.ciAnimating;
            delete card.dataset.ciExitTimer;
            if (disc && isUserView) disc.style.display = 'none';
            if (done) done();
        };
        const isVisible = card.style.display !== 'none'
            && getComputedStyle(card).display !== 'none';
        if (!REDUCED && isVisible && card.dataset.ciAnimating !== 'out') {
            card.dataset.ciAnimating = 'out';
            card.classList.remove('ci-alert-entrance');
            void card.offsetWidth;
            card.classList.add('ci-card-exit');
            card.dataset.ciExitTimer = String(setTimeout(finish, 220)); // matches .ci-card-exit duration
        } else {
            finish();
        }
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function prettyDate(v) {
        if (!v) return '';
        const d = new Date(String(v).replace(' ', 'T'));
        if (isNaN(d)) return String(v);
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    }

    function cap(s) {
        return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
    }

    // Fast local preview keeps the alert responsive while the server NLP
    // request is running. The server payload remains authoritative afterward.
    // Keywords MUST equal the server's canonical labels so the optimistic
    // preview and the authoritative server payload reconcile without churn
    // (a case/name mismatch would briefly show two rows for one symptom).
    const LOCAL_SYMPTOMS = [
        { keyword: 'Headache', terms: ['headache', 'head hurts', 'head pain', 'sakit ng ulo', 'sakit ug ulo', 'migraine'], icon: '🤕', nutrient: 'Calcium' },
        { keyword: 'Bloating', terms: ['stomach ache', 'stomach pain', 'stomach hurts', 'stomach is hurting', 'my stomach hurts', 'my stomach is hurting', 'bloating', 'bloated', 'stomach issue', 'sakit ng tiyan', 'masakit tiyan', 'kabag'], icon: '🎈', nutrient: 'Fiber, total dietary' },
        { keyword: 'Fatigue', terms: ['fatigue', 'exhausted', 'tired', 'low energy', 'energy levels', 'weak', 'hina ng katawan', 'pagod', 'kapaguran', 'walang lakas', 'kapoy'], icon: '😴', nutrient: 'Iron' },
        { keyword: 'Dizziness', terms: ['dizzy', 'dizziness', 'lightheaded', 'feeling faint', 'nahihilo', 'hilo', 'lula'], icon: '🌀', nutrient: 'Sodium' },
        { keyword: 'Nausea', terms: ['nausea', 'nauseous', 'queasy', 'vomit', 'throw up', 'nanghihina sa sikmura', 'naiinit', 'masusuka', 'susuka', 'sukang-suka'], icon: '🤢', nutrient: 'Vitamin C' },
        { keyword: 'Muscle cramps', terms: ['cramp', 'cramps', 'spasm', 'muscle spasm', 'pulikat', 'kalampos', 'kabag sa kalamnan'], icon: '🦵', nutrient: 'Potassium, K' },
        { keyword: 'Chest pain', terms: ['chest pain', 'chest hurts', 'pain in my chest', 'sakit sa dibdib', 'masakit ang dibdib'], icon: '🫀', nutrient: 'Wellness review' },
        { keyword: 'Back pain', terms: ['back pain', 'back hurts', 'pain in my back', 'sakit ng likod', 'masakit ang likod'], icon: '🩻', nutrient: 'Wellness review' },
        { keyword: 'Neck pain', terms: ['neck pain', 'neck hurts', 'sakit ng leeg', 'masakit ang leeg'], icon: '🦴', nutrient: 'Wellness review' },
        { keyword: 'Joint pain', terms: ['joint pain', 'joints hurt', 'joint hurts', 'sakit ng kasukasuan', 'masakit ang kasukasuan'], icon: '🦴', nutrient: 'Wellness review' },
        { keyword: 'Muscle pain', terms: ['muscle pain', 'muscles hurt', 'body aches', 'body pain', 'sakit ng katawan', 'masakit ang katawan'], icon: '💪', nutrient: 'Wellness review' },
        { keyword: 'Leg pain', terms: ['leg pain', 'my leg hurts', 'pain in my leg', 'sakit ng binti', 'masakit ang binti'], icon: '🦵', nutrient: 'Wellness review' },
        { keyword: 'Shoulder pain', terms: ['shoulder pain', 'shoulder hurts', 'sakit ng balikat', 'masakit ang balikat'], icon: '💪', nutrient: 'Wellness review' },
        { keyword: 'Tooth pain', terms: ['toothache', 'tooth pain', 'tooth hurts', 'sakit ng ngipin', 'masakit ang ngipin'], icon: '🦷', nutrient: 'Wellness review' },
        { keyword: 'Sore throat', terms: ['sore throat', 'throat hurts', 'sakit ng lalamunan', 'masakit ang lalamunan'], icon: '🗣️', nutrient: 'Wellness review' },
        { keyword: 'Fever', terms: ['fever', 'high temperature', 'lagnat', 'nilalagnat', 'may lagnat'], icon: '🌡️', nutrient: 'Wellness review' },
        { keyword: 'Cough', terms: ['cough', 'coughing', 'ubo', 'inuubo'], icon: '🫁', nutrient: 'Wellness review' },
        { keyword: 'Shortness of breath', terms: ['shortness of breath', 'hard to breathe', 'difficulty breathing', 'cant breathe', "can't breathe", 'hirap huminga', 'nahihirapang huminga'], icon: '🫁', nutrient: 'Wellness review' },
        { keyword: 'Palpitations', terms: ['heart racing', 'heart pounding', 'palpitations', 'mabilis ang tibok', 'kumakabog ang dibdib'], icon: '💓', nutrient: 'Wellness review' },
        { keyword: 'Diarrhea', terms: ['diarrhea', 'loose stool', 'watery stool', 'pagtatae'], icon: '🚰', nutrient: 'Wellness review' },
        { keyword: 'Constipation', terms: ['constipation', 'constipated', 'cannot poop', "can't poop", 'hirap dumumi'], icon: '🧱', nutrient: 'Wellness review' },
        { keyword: 'Pain', terms: ['pain', 'ache', 'aching', 'hurts', 'hurt', 'sore', 'masakit', 'sakit'], icon: '⚠️', nutrient: 'Wellness review' }
    ];

    function localInsightItem(item) {
        return {
            keyword: item.keyword,
            icon: item.icon,
            category: 'Wellness symptom',
            short_desc: `${cap(item.keyword)} was mentioned in your message.`,
            full_desc: 'This is an educational pattern detected from your message. Your dietitian can help connect it with food, hydration, sleep, and lifestyle factors.',
            recommended: ['Note when this symptom occurs and whether it follows meals or activity.', 'Share the pattern with your dietitian.'],
            when_to_ask: 'Contact your dietitian if this persists, worsens, or concerns you.',
            nutrients: [{ icon: '⚛️', name: item.nutrient }],
            last_seen: new Date().toISOString(),
            occurrences: 1
        };
    }

    function localPreview(text) {
        const normalized = String(text || '').toLowerCase();
        const resolved = /\b(i('| a)m|im|i feel|feeling)\s+(alright|okay|ok|better|fine|good|well)\b|\b(alright|okay|ok|better|fine)\s+now\b/.test(normalized);
        if (resolved) return { resolved: true, symptoms: [] };

        const detected = LOCAL_SYMPTOMS.filter(item => item.terms.some(term => {
            if (!normalized.includes(term)) return false;
            const escaped = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return !new RegExp(`(?:no|not|without|hindi|wala|walang)\\s+(?:\\w+\\s+){0,3}${escaped}`, 'i').test(normalized);
        }));
        const existing = lastConfirmedSyms.length
            ? lastConfirmedSyms.map(s => ({
                keyword: s.keyword,
                icon: s.icon || '✨',
                nutrient: (s.nutrients && s.nutrients[0] && s.nutrients[0].name) || 'Wellness review',
                _keep: s
            }))
            : Array.from(lastKeywords)
                .map(keyword => LOCAL_SYMPTOMS.find(item => item.keyword === keyword) || {
                    keyword,
                    terms: [keyword],
                    icon: '✨',
                    nutrient: 'Wellness review'
                });
        const specificDetected = detected.filter(item => item.keyword !== 'pain');
        const kLower = s => String(s.keyword).toLowerCase();
        const hasSpecificPain = specificDetected.some(item => {
            const k = kLower(item);
            return k.includes('pain') || k === 'headache' || k === 'bloating' || k === 'migraine';
        });
        const filteredDetected = detected.filter(item => item.keyword !== 'pain' || !hasSpecificPain);
        const merged = [];
        const seen = new Map();
        for (const s of existing) {
            const key = kLower(s);
            if (!seen.has(key)) {
                seen.set(key, true);
                merged.push(s._keep || s);
            }
        }
        for (const s of filteredDetected) {
            const key = kLower(s);
            if (!seen.has(key)) {
                seen.set(key, true);
                merged.push(s);
            }
        }
        const symptoms = merged.map(s => s.short_desc ? s : localInsightItem(s));
        return { resolved: false, symptoms };
    }

    window.ndPreviewWellnessMessage = function (text) {
        const preview = localPreview(text);
        if (preview.resolved) {
            pendingLocalSymptoms = [];
            pendingSetAt = 0;
            window.ndClearWellnessInsights();
        } else if (preview.symptoms.length) {
            pendingLocalSymptoms = preview.symptoms;
            pendingSetAt = Date.now();
            window.ndRenderWellnessInsights({
                symptoms: preview.symptoms,
                active_count: preview.symptoms.length
            }, { immediate: true, force: true, localPreview: true, newSymptoms: preview.symptoms.map(s => s.keyword) });
        }
        return preview;
    };

    /** Build one indicator card — mirrors the server-side PHP markup. */
    function indicatorCard(sym, userView) {
        const nutrients = (sym.nutrients || [])
            .map(n => `<span class="ci-nutrient-tag"><span class="ci-nutrient-icon">${esc(n.icon)}</span><span class="ci-nutrient-name">${esc(n.name)}</span></span>`)
            .join('');

        const recs = (sym.recommended || [])
            .map(r => `<li><span class="ci-bullet">✓</span>${esc(r)}</li>`)
            .join('');

        const when = sym.last_seen ? ` title="Reported: ${esc(prettyDate(sym.last_seen))}${(sym.occurrences || 1) > 1 ? ` · mentioned ${sym.occurrences}×` : ''}"` : '';

        return `
        <div class="ci-indicator-card ci-indicator-rich"${when}>
            <div class="ci-indicator-top">
                <div class="ci-symptom-row">
                    <span class="ci-symptom-icon">${esc(sym.icon)}</span>
                    <div class="ci-symptom-info">
                        <span class="ci-symptom-name">${esc(cap(sym.keyword))}</span>
                        <span class="ci-symptom-cat">${esc(sym.category)}</span>
                    </div>
                </div>
                ${nutrients ? `
                <div class="ci-arrow-divider"><i class="fas fa-arrow-right"></i></div>
                <div class="ci-nutrient-row">${nutrients}</div>` : ''}
            </div>
            <div class="ci-indicator-short">
                <i class="fas fa-info-circle ci-detail-icon"></i>
                <span>${esc(sym.short_desc)}</span>
            </div>
            <details class="ci-details">
                <summary class="ci-details-summary">
                    <span>Learn more &amp; simple steps</span>
                    <i class="fas fa-angle-down"></i>
                </summary>
                <div class="ci-details-body">
                    <div class="ci-section">
                        <div class="ci-section-label"><i class="fas fa-book-open"></i> What this could mean</div>
                        <div class="ci-section-text">${esc(sym.full_desc)}</div>
                    </div>
                    <div class="ci-section">
                        <div class="ci-section-label"><i class="fas fa-seedling"></i> Simple steps to try at home</div>
                        <ul class="ci-action-list">${recs}</ul>
                    </div>
                    <div class="ci-section ci-cta">
                        <div class="ci-section-label"><i class="fas fa-user-md"></i> When to talk to your ${userView ? 'dietitian' : 'client'}</div>
                        <div class="ci-section-text">${esc(sym.when_to_ask)}</div>
                    </div>
                </div>
            </details>
        </div>`;
    }

    // ── Dynamic card shell ────────────────────────────────────────────────
    // Clients with ZERO historical symptoms have no server-rendered card
    // (PHP skips the block entirely). When the NLP detects a first symptom
    // mid-conversation, we build the exact same card structure here so the
    // update is still real time — no reload needed.
    function buildCardShell(isUserView) {
        const anchor = document.getElementById('chatMessages');
        if (!anchor) return null;

        const html = isUserView ? `
        <div class="clinical-indicators-card ci-user ci-collapsed" id="userIndicatorsCard" role="region" aria-label="My wellness insights">
            <div class="ci-card-header ci-user-header">
                <div class="ci-card-title">
                    <div class="ci-icon-badge"><i class="fas fa-spa" aria-hidden="true"></i></div>
                    <div class="ci-title-text">
                        <h3>My Wellness Insights</h3>
                        <p>Personal patterns from your records — educational only</p>
                    </div>
                </div>
                <div class="ci-header-actions">
                    <div class="ci-badge-count ci-user-badge" title="0 insights">
                        <span class="ci-count-num">0</span>
                        <span class="ci-count-label">insights</span>
                    </div>
                    <button type="button" class="ci-toggle-btn ci-user-toggle" id="userCiToggleBtn" aria-expanded="false" aria-controls="userCiCardBody" onclick="toggleUserIndicators()" title="Expand">
                        <i class="fas fa-chevron-down"></i>
                        <span class="ci-toggle-label">Expand</span>
                    </button>
                </div>
            </div>
            <div class="ci-card-body" id="userCiCardBody"></div>
            <div class="ci-collapsed-summary ci-user-summary" id="userCiCollapsedSummary" onclick="toggleUserIndicators()" role="button" tabindex="0" title="Click to expand">
                <div class="ci-summary-text"><i class="fas fa-lightbulb"></i><span></span></div>
                <div class="ci-summary-chips"></div>
            </div>
        </div>
        <div class="user-insight-disclaimer">
            <i class="fas fa-shield-halved"></i>
            <div><b>NOT MEDICAL ADVICE.</b> These AI-generated insights are for awareness &amp; education only. Always confirm with your dietitian before making changes.</div>
        </div>` : `
        <div class="clinical-indicators-card ci-collapsed" id="clinicalIndicatorsCard" role="region" aria-label="Clinical indicators">
            <div class="ci-card-header">
                <div class="ci-card-title">
                    <div class="ci-icon-badge"><i class="fas fa-dna" aria-hidden="true"></i></div>
                    <div class="ci-title-text">
                        <h3>Wellness Insights</h3>
                        <p>AI-detected patterns with dietitian-friendly explanations</p>
                    </div>
                </div>
                <div class="ci-header-actions">
                    <div class="ci-badge-count" title="0 detected">
                        <span class="ci-count-num">0</span>
                        <span class="ci-count-label">flags</span>
                    </div>
                    <button type="button" class="ci-toggle-btn" id="ciToggleBtn" aria-expanded="false" aria-controls="ciCardBody" onclick="toggleClinicalIndicators()" title="Expand for details">
                        <i class="fas fa-chevron-down"></i>
                        <span class="ci-toggle-label">Expand</span>
                    </button>
                </div>
            </div>
            <div class="ci-card-body" id="ciCardBody"></div>
            <div class="ci-collapsed-summary" id="ciCollapsedSummary" onclick="toggleClinicalIndicators()" role="button" tabindex="0" title="Click to expand">
                <div class="ci-summary-text"><i class="fas fa-lightbulb"></i><span></span></div>
                <div class="ci-summary-chips"></div>
            </div>
        </div>`;

        const frag = document.createElement('div');
        frag.innerHTML = html.trim();
        const nodes = Array.from(frag.children);
        nodes.forEach(n => anchor.parentNode.insertBefore(n, anchor));

        return document.getElementById(isUserView ? 'userIndicatorsCard' : 'clinicalIndicatorsCard');
    }

    // ── Alert pill (hidden-first notification) ────────────────────────────
    function ensurePill(isUserView, card) {
        let pill = document.getElementById('ciAlertPill');
        if (pill) return pill;
        pill = document.createElement('button');
        pill.type = 'button';
        pill.id = 'ciAlertPill';
        pill.className = 'ci-alert-pill' + (isUserView ? ' ci-user-pill' : '');
        pill.setAttribute('aria-live', 'polite');
        pill.innerHTML = `
            <span class="ci-alert-icon"><i class="fas ${isUserView ? 'fa-spa' : 'fa-dna'}"></i></span>
            <span class="ci-alert-copy">
                <b class="ci-alert-count">0</b>
                <span class="ci-alert-text"></span>
            </span>
            <span class="ci-alert-chevron"><i class="fas fa-chevron-down"></i></span>`;
        pill.addEventListener('click', () => {
            revealed = true;
            const c = document.getElementById(isUserView ? 'userIndicatorsCard' : 'clinicalIndicatorsCard');
            if (c) {
                c.style.display = '';
                const disc = document.querySelector('.user-insight-disclaimer');
                if (disc && isUserView) disc.style.display = '';
                animateIn(c, 'ci-alert-entrance', 420); // spring fade/slide-in on reveal
            }
            hidePillAnimated(pill);
        });
        card.parentNode.insertBefore(pill, card);
        return pill;
    }

    function setPill(pill, count, isUserView, pulse) {
        pill.style.display = 'inline-flex';
        const numEl = pill.querySelector('.ci-alert-count');
        const txtEl = pill.querySelector('.ci-alert-text');
        if (numEl) numEl.textContent = count;
        if (txtEl) {
            // The bold .ci-alert-count element already shows the number —
            // the copy must NOT repeat it. Shorter copy on narrow phones so
            // the pill never overflows.
            const narrow = window.matchMedia && window.matchMedia('(max-width: 480px)').matches;
            const noun = isUserView ? 'wellness insight' : 'symptom';
            txtEl.textContent = narrow
                ? `${noun}${count === 1 ? '' : 's'} — tap`
                : `${noun}${count === 1 ? '' : 's'} detected — tap to view`;
        }
        if (pulse) {
            pill.classList.remove('is-pulsing');
            void pill.offsetWidth; // restart the ring animation
            pill.classList.add('is-pulsing');
        } else {
            pill.classList.remove('is-pulsing'); // quiet updates never replay the ring
        }
    }

    /** Set pill content + show it; spring drop-in when it (re)appears. */
    function showPill(pill, count, isUserView, pulse) {
        const wasHidden = getComputedStyle(pill).display === 'none';
        const prevNum = pill.querySelector('.ci-alert-count')?.textContent;
        setPill(pill, count, isUserView, pulse);
        if (wasHidden) {
            animateIn(pill, 'ci-pill-enter', 620);
        } else if (prevNum !== String(count)) {
            // Bar already visible: pop the count chip so the change registers
            const numEl = pill.querySelector('.ci-alert-count');
            if (numEl && !REDUCED) animateIn(numEl, 'ci-count-pop', 360);
        }
    }

    /** Fade the pill out, then flip display (used when the card reveals). */
    function hidePillAnimated(pill) {
        if (!pill) return;
        if (REDUCED || getComputedStyle(pill).display === 'none') {
            pill.style.display = 'none';
            return;
        }
        pill.classList.add('ci-pill-exit');
        setTimeout(() => {
            pill.style.display = 'none';
            pill.classList.remove('ci-pill-exit');
        }, 170); // matches .ci-pill-exit duration
    }

    // ── Hide button (tuck the card back into the alert pill) ──────────────
    function ensureHideButton(card, isUserView) {
        const actions = card.querySelector('.ci-header-actions');
        if (!actions || document.getElementById(isUserView ? 'userCiHideBtn' : 'ciHideBtn')) return;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ci-hide-btn';
        btn.id = isUserView ? 'userCiHideBtn' : 'ciHideBtn';
        btn.title = 'Hide insights — you\'ll be alerted here when new symptoms are detected';
        btn.setAttribute('aria-label', 'Hide wellness insights');
        btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
        btn.addEventListener('click', () => {
            revealed = false;
            animateOutCard(card, isUserView, () => {
                const pill = document.getElementById('ciAlertPill');
                if (pill) showPill(pill, lastCount, isUserView, false); // quiet re-surface, no false alarm
            });
        });
        actions.appendChild(btn);
    }

    /**
     * Re-render the Wellness Insights card from a live payload.
     * Shape: { symptoms: [...], active_count: n } (payload of
     * handlers/get_insights.php). Safe to call with null/empty.
     */
    function render(insights, options = {}) {
        if (!insights || !Array.isArray(insights.symptoms)) return;

        const count = insights.active_count != null
            ? insights.active_count
            : insights.symptoms.length;
        const syms = insights.symptoms;

        const isUserView = (document.body && document.body.dataset.ciView === 'user');
        const cardId = isUserView ? 'userIndicatorsCard' : 'clinicalIndicatorsCard';
        let card = document.getElementById(cardId);

        // ── DEFENSE 1: Sync `revealed` state from the ACTUAL DOM.
        //    State drift happens when the PHP-rendered card exists on load,
        //    or when a pill click / hide-button click is the only modifier.
        //    Without this sync, an "unchanged signature" early return below
        //    can leave `revealed` as `false` even though the card is visible.
        if (card) {
            const visible = card.style.display !== 'none'
                && getComputedStyle(card).display !== 'none';
            if (visible) revealed = true;
        }

        // Signature = sorted keywords + count; skip DOM churn when unchanged.
        // WITHOUT this early return every 3s poll rebuilds the card HTML and
        // replays the entrance animations — the "blinking/refreshing" bug.
        const signature = count + '|' + syms
            .map(s => `${s.keyword}:${s.last_seen || ''}:${s.occurrences || 0}`)
            .sort()
            .join('|');
        const changed = lastSignature !== null && lastSignature !== signature;
        const first = lastSignature === null;
        const reportedSymptoms = new Set((options.newSymptoms || [])
            .map(s => String(s).trim().toLowerCase()));
        const newSymptomDetected = syms.some(s =>
            !lastKeywords.has(s.keyword)
            || reportedSymptoms.has(String(s.keyword).trim().toLowerCase())
        );

        // ── DEFENSE 2: Even on unchanged-signature early-return, if the
        //    user already revealed the card and it was somehow hidden by a
        //    previous stray class application, restore it so the card never
        //    disappears on a "no-op" poll cycle.
        if (!first && !changed && !options.force) {
            if (revealed && card) {
                const cardHidden = card.style.display === 'none'
                    || getComputedStyle(card).display === 'none';
                if (cardHidden) {
                    cancelCardExit(card);
                    card.style.display = '';
                    const disc = document.querySelector('.user-insight-disclaimer');
                    if (disc && isUserView) disc.style.display = '';
                    const pill = document.getElementById('ciAlertPill');
                    if (pill) hidePillAnimated(pill);
                }
            }
            return;
        }
        lastSignature = signature;
        lastKeywords = new Set(syms.map(s => s.keyword));

        // ── DEFENSE 3: Expire pending-local symptoms after a grace period so
        //    a transient detection that the server *never* picks up (e.g. the
        //    user typed a symptom then immediately unsent the message) does
        //    not stick around forever.
        const nowTs = Date.now();
        if (pendingLocalSymptoms.length
            && pendingSetAt
            && nowTs - pendingSetAt > PENDING_GRACE_PERIOD_MS) {
            pendingLocalSymptoms = [];
            pendingSetAt = 0;
        }

        // Nothing active: fade the card out (when visible) and reset so a
        // future detection raises a fresh alert.
        if (count === 0) {
            // A remote response can arrive before the NLP persistence finishes
            // or can miss a phrase that the local detector already recognized.
            // Do not erase that visible insight until the grace period lapses
            // or the user explicitly resolves it.
            if (pendingLocalSymptoms.length && !options.resolved) {
                render({
                    symptoms: pendingLocalSymptoms,
                    active_count: pendingLocalSymptoms.length
                }, { immediate: true, force: true, localPreview: true, newSymptoms: pendingLocalSymptoms.map(s => s.keyword) });
                return;
            }
            clearAutoDismiss();
            lastCount = 0;
            lastConfirmedSyms = [];
            revealed = false;
            const pill = document.getElementById('ciAlertPill');
            if (card) {
                animateOutCard(card, isUserView);
            } else {
                const disc = document.querySelector('.user-insight-disclaimer');
                if (disc && isUserView) disc.style.display = 'none';
            }
            if (pill) hidePillAnimated(pill);
            return;
        }

        if (!card) card = buildCardShell(isUserView);
        if (!card) return;

        // Only clear pending local symptoms when the SERVER CONFIRMS the same
        // symptoms (by keyword). If the NLP missed something the local detector
        // caught, keep those in the buffer so subsequent count=0 polls won't
        // wipe the insight before the user has a chance to read it.
        let confirmedSyms = syms;
        if (!options.localPreview && pendingLocalSymptoms.length) {
            const serverKeywords = new Set(syms.map(s => String(s.keyword).trim().toLowerCase()));
            const allConfirmed = pendingLocalSymptoms.every(ps =>
                serverKeywords.has(String(ps.keyword).trim().toLowerCase())
            );
            if (allConfirmed) {
                pendingLocalSymptoms = [];
                pendingSetAt = 0;
            } else {
                // ── STACKING FIX: Server payload confirmed SOME but not ALL
                //    pending symptoms. Merge the unconfirmed ones INTO the
                //    display list so the user sees BOTH persisted symptoms
                //    (e.g. Dizziness) AND the freshly-detected optimistic
                //    preview (e.g. Bloating) stacked together during the
                //    persistence lag window.
                const merged = [];
                const seen = new Set();
                for (const s of syms) {
                    const k = String(s.keyword).trim().toLowerCase();
                    if (!seen.has(k)) { seen.add(k); merged.push(s); }
                }
                for (const p of pendingLocalSymptoms) {
                    const k = String(p.keyword).trim().toLowerCase();
                    if (!seen.has(k)) { seen.add(k); merged.push(p); }
                }
                confirmedSyms = merged;
            }
        } else if (!options.localPreview) {
            pendingLocalSymptoms = [];
            pendingSetAt = 0;
        }

        // STACKING FIX: Normalize working set. `confirmedSyms` = server list
        // (if authoritative) OR server + unconfirmed pending (if partial
        // confirm). For pure localPreview renders, use the original syms.
        const displaySyms = options.localPreview ? syms : confirmedSyms;
        // Recount so badges, chips, and count all reflect the merged set.
        const displayCount = displaySyms.length;

        // Snapshot the authoritative server-confirmed list (after dedup,
        // before any pending merge) so the NEXT preview's merge base has
        // the REAL persisted symptom data (descriptions, nutrients, counts)
        // instead of placeholder LOCAL_SYMPTOMS stubs.
        if (!options.localPreview) {
            const snap = new Map();
            for (const s of syms) {
                const k = String(s.keyword).trim().toLowerCase();
                if (!snap.has(k)) snap.set(k, s);
            }
            lastConfirmedSyms = Array.from(snap.values());
        }
        cancelCardExit(card);

        const pill = ensurePill(isUserView, card);
        ensureHideButton(card, isUserView);
        const prevCount = lastCount;
        // Note: track lastCount against the DISPLAY count so the
        // count-changed pulse and newSymptomDetected heuristics fire when
        // either the server adds a symptom OR the local optimizer adds one.
        lastCount = displayCount;

        // A send response is authoritative for the message just entered. Open
        // the insight immediately, while ordinary initial loads remain quiet.
        const messageReportedActiveSymptom = options.immediate
            && displaySyms.some(s => reportedSymptoms.has(String(s.keyword).trim().toLowerCase()));
        const shouldAutoReveal = (options.immediate && reportedSymptoms.size > 0)
            || messageReportedActiveSymptom
            || (changed && newSymptomDetected)
            || (pendingLocalSymptoms.length && options.localPreview);

        // 1. Count badge
        const num = card.querySelector('.ci-count-num');
        if (num) num.textContent = displayCount;
        const badge = card.querySelector('.ci-badge-count');
        if (badge) badge.title = `${displayCount} ${isUserView ? 'insights' : 'detected'}`;

        // 2. Body indicator cards (preserve expansion state + open details)
        const body = document.getElementById(isUserView ? 'userCiCardBody' : 'ciCardBody');
        if (body) {
            const wasExpanded = card.classList.contains('ci-expanded');
            const openKeywords = new Set(
                [...body.querySelectorAll('.ci-indicator-card')].filter(c => {
                    const d = c.querySelector('.ci-details');
                    return d && d.open;
                }).map(c => (c.querySelector('.ci-symptom-name') || {}).textContent)
            );
            const scrollTop = body.scrollTop;

            body.innerHTML = displaySyms.map(s => indicatorCard(s, isUserView)).join('');

            body.querySelectorAll('.ci-indicator-card').forEach(c => {
                const name = (c.querySelector('.ci-symptom-name') || {}).textContent;
                const d = c.querySelector('.ci-details');
                if (d && name && openKeywords.has(name)) d.open = true;
            });
            body.scrollTop = scrollTop;
            if (wasExpanded && !card.classList.contains('ci-expanded')) {
                card.classList.add('ci-expanded');
            }
        }

        // 3. Collapsed summary chips
        const summary = document.getElementById(isUserView ? 'userCiCollapsedSummary' : 'ciCollapsedSummary');
        if (summary) {
            const chips = summary.querySelector('.ci-summary-chips');
            const text = summary.querySelector('.ci-summary-text span');
            if (text) {
                text.textContent = isUserView
                    ? `${displayCount} personal wellness ${displayCount === 1 ? 'insight' : 'insights'} — Expand for guidance`
                    : `${displayCount} wellness ${displayCount === 1 ? 'insight' : 'insights'} — click Expand for details`;
            }
            if (chips) {
                chips.innerHTML = displaySyms.slice(0, 5).map(s =>
                    `<span class="ci-summary-chip"><span>${esc(s.icon)}</span><span>${esc(cap(s.keyword))}</span></span>`
                ).join('') + (displayCount > 5 ? `<span class="ci-summary-chip ci-summary-more">+${displayCount - 5}</span>` : '');
            }
        }

        // DEFENSE 4: Before applying visibility below, re-check actual DOM
        // state so a previously-visible card (pill-click, auto-reveal, last
        // cycle) is NEVER mistakenly hidden just because the current payload
        // isn't flagged as "new detection" on this particular poll cycle.
        const cardCurrentlyVisible = card.style.display !== 'none'
            && getComputedStyle(card).display !== 'none';
        if (cardCurrentlyVisible) revealed = true;

        // 4. Visibility — open new detections immediately, then let the user
        // return to the conversation without leaving the card in the way.
        if (shouldAutoReveal) {
            revealed = true;
            minimizeCard(card, isUserView);
            card.style.display = '';
            const disc = document.querySelector('.user-insight-disclaimer');
            if (disc && isUserView) disc.style.display = '';
            animateIn(card, 'ci-alert-entrance', 420);
            hidePillAnimated(pill);
        } else if (revealed) {
            const wasHidden = getComputedStyle(card).display === 'none';
            card.style.display = '';
            const disc = document.querySelector('.user-insight-disclaimer');
            if (disc && isUserView) disc.style.display = '';
            if (wasHidden) animateIn(card, 'ci-alert-entrance', 420);
            hidePillAnimated(pill);

            if (changed && badge) {
                badge.classList.remove('ci-badge-updated');
                void badge.offsetWidth;
                badge.classList.add('ci-badge-updated');
            }
        } else {
            const pulse = first || (changed && displayCount > prevCount);
            showPill(pill, displayCount, isUserView, pulse);
            // DEFENSE 5: Only hide the card here if there are NO pending local
            // symptoms. If pending symptoms exist, the user is currently
            // looking at an optimistic preview and the server hasn't caught
            // up yet — hiding the card now is exactly the "momentarily then
            // disappears" bug.
            if (pendingLocalSymptoms.length === 0) {
                card.style.display = 'none';
                const disc = document.querySelector('.user-insight-disclaimer');
                if (disc && isUserView) disc.style.display = 'none';
            } else {
                // Pending preview is active: keep the card visible and make
                // sure the pill stays hidden so the UI doesn't flip-flop.
                card.style.display = '';
                const disc = document.querySelector('.user-insight-disclaimer');
                if (disc && isUserView) disc.style.display = '';
                hidePillAnimated(pill);
            }
        }
    }

    window.ndRenderWellnessInsights = render;
    window.ndClearWellnessInsights = function () {
        render({ symptoms: [], active_count: 0 }, { force: true, resolved: true });
    };
})();
