/**
 * scripts/wellness-insights.js
 * Live renderer for the Wellness Insights card (client + staff message pages).
 *
 * The chat controller calls window.ndRenderWellnessInsights(insights) after
 * every send and every 3s poll, so the card mirrors what the client is saying
 * RIGHT NOW: NLP-detected symptoms appear, reported-resolved ones disappear.
 *
 * The renderer only updates COUNTS, CHIPS and CARD CONTENT — the expand/
 * collapse toggle handlers stay untouched, and the user's current expansion
 * state is preserved across updates. When the symptom set changes, a subtle
 * refresh cue blinks on the count badge.
 *
 * All content is EDUCATIONAL ONLY — not medical advice.
 */
(function () {
    'use strict';

    let lastSignature = null;

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
                        <span class="ci-symptom-name">${esc(sym.keyword ? sym.keyword.charAt(0).toUpperCase() + sym.keyword.slice(1) : '')}</span>
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

    /**
     * Re-render the Wellness Insights card from a live payload.
     * Shape: { symptoms: [...], active_count: n }  (payload of
     * handlers/get_insights.php). Safe to call with null/empty.
     */
    function render(insights) {
        if (!insights || !Array.isArray(insights.symptoms)) return;

        const count = insights.active_count != null
            ? insights.active_count
            : insights.symptoms.length;
        const syms = insights.symptoms;

        // Signature = sorted keywords + count; skip DOM churn when unchanged.
        const signature = count + '|' + syms.map(s => s.keyword).sort().join(',');
        if (signature === lastSignature) return;
        const changed = lastSignature !== null && lastSignature !== signature;
        lastSignature = signature;

        const isUserView = !!document.getElementById('userIndicatorsCard');
        const card = document.getElementById(isUserView ? 'userIndicatorsCard' : 'clinicalIndicatorsCard');
        if (!card) return;

        // Zero active symptoms -> hide the whole card region cleanly.
        if (count === 0) {
            card.style.display = 'none';
            const disc = document.querySelector('.user-insight-disclaimer');
            if (disc && isUserView) disc.style.display = 'none';
            return;
        }
        card.style.display = '';

        // 1. Count badge
        const num = card.querySelector('.ci-count-num');
        if (num) num.textContent = count;

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

            body.innerHTML = syms.map(s => indicatorCard(s, isUserView)).join('');

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
                    ? `${count} personal wellness ${count === 1 ? 'insight' : 'insights'} — Expand for guidance`
                    : `${count} wellness ${count === 1 ? 'insight' : 'insights'} — click Expand for details`;
            }
            if (chips) {
                chips.innerHTML = syms.slice(0, 5).map(s =>
                    `<span class="ci-summary-chip"><span>${esc(s.icon)}</span><span>${esc(s.keyword ? s.keyword.charAt(0).toUpperCase() + s.keyword.slice(1) : '')}</span></span>`
                ).join('') + (count > 5 ? `<span class="ci-summary-chip ci-summary-more">+${count - 5}</span>` : '');
            }
        }

        // 4. Change cue on the badge
        if (changed) {
            const badge = card.querySelector('.ci-badge-count');
            if (badge) {
                badge.classList.remove('ci-badge-updated');
                void badge.offsetWidth; // restart animation
                badge.classList.add('ci-badge-updated');
            }
        }
    }

    window.ndRenderWellnessInsights = render;
})();
