/**
 * dashboard-store.js
 * A lightweight reactive state manager (Vanilla JS equivalent to Zustand)
 * for NutriDeq's Dashboard components.
 */

class ReactiveStore {
    constructor(initialState = {}) {
        this.state = new Proxy(initialState, {
            set: (target, property, value) => {
                target[property] = value;
                this.notify(property, value);
                this.notify('*', target); // Notify global listeners
                return true;
            }
        });
        this.listeners = {};
    }

    // Subscribe to a specific property or '*' for global state changes
    subscribe(property, callback) {
        if (!this.listeners[property]) {
            this.listeners[property] = [];
        }
        this.listeners[property].push(callback);
    }

    notify(property, value) {
        if (this.listeners[property]) {
            this.listeners[property].forEach(callback => callback(value));
        }
    }

    // Update state partially
    update(partials) {
        for (const [key, value] of Object.entries(partials)) {
            this.state[key] = value;
        }
    }
}

// Initialize Global Dashboard Store 
window.DashboardStore = new ReactiveStore({
    macros: { calories: 0, protein: 0, carbs: 0, fats: 0 },
    macroGoals: { calories: 2000, protein: 150, carbs: 200, fats: 65 },
    hydration: { glasses: 0, target: 8 },
    bodyComp: { weight: 0, height: 0, bmi: 0, status: 'Unknown' },
    bmiHistory: [],
    recommendedPlans: [],
    messages: [],
    hasDietitian: false,
    healthScore: 0,
    micronutrients: {},
    micronutrientGoals: {
        'Iron': 7.0,
        'Potassium, K': 1500.0,
        'Calcium': 500.0,
        'Sodium': 500.0,
        'Fiber, total dietary': 10.0,
        'Vitamin C': 30.0
    },
    nutrientDebts: [],
    aiRecommendations: []
});

// Calculate Health Score Reactively
DashboardStore.subscribe('*', (state) => {
    // Score based on hydration (30%) + macros (70%) + baseline
    const hydroScore = Math.min((state.hydration.glasses / state.hydration.target) * 30, 30);
    
    let calRatio = 0;
    if (state.macroGoals.calories > 0 && state.macros.calories > 0) {
        calRatio = state.macros.calories / state.macroGoals.calories;
    }
    
    // Simplistic metric
    const macroScore = (calRatio > 0 && calRatio <= 1.2) ? 70 : (calRatio > 1.2 ? 40 : 20);
    
    let finalScore = Math.round(hydroScore + macroScore);
    if (isNaN(finalScore)) finalScore = '--';
    
    // Update Score Badge if exists
    const scoreEl = document.getElementById('healthScoreDisplay');
    if (scoreEl && scoreEl.innerText !== String(finalScore)) {
        scoreEl.innerText = `Score: ${finalScore}`;
        scoreEl.classList.add('pulse-update');
        setTimeout(() => scoreEl.classList.remove('pulse-update'), 500);
    }
    
    // Update Macros Banner Component 
    const calEl = document.getElementById('caloriesConsumedDisplay');
    if (calEl && calEl.innerText !== String(state.macros.calories)) {
        calEl.innerText = state.macros.calories;
    }
});

// Update Rings Reactively
DashboardStore.subscribe('macros', (macros) => {
    // Fallbacks to default goals just in case the DB returned 0
    let goals = window.DashboardStore.state.macroGoals;
    if (!goals || goals.calories <= 0) goals = { calories: 2000, protein: 150, carbs: 200, fats: 65 };
    
    const updateRing = (idPrefix, consumed, goal) => {
        const valEl = document.getElementById(`${idPrefix}_val`);
        const barEl = document.getElementById(`${idPrefix}_bar`);
        
        if (valEl) {
            // Only trigger pulse if the value actually changed
            if (valEl.innerText !== `${consumed}g`) {
                valEl.innerText = `${consumed}g`;
                valEl.style.transform = "scale(1.2)";
                valEl.style.color = "var(--primary)";
                setTimeout(() => {
                    valEl.style.transform = "scale(1)";
                    valEl.style.color = "#64748b";
                }, 400);
            }
        }
        
        if (barEl) {
            // Ensure goal is never 0 to avoid division by zero
            const safeGoal = goal > 0 ? goal : 100; 
            const ratio = Math.min(consumed / safeGoal, 1);
            const fullOffset = 213.6; 
            const newOffset = fullOffset - (ratio * fullOffset);
            
            // Set it natively via attribute to prevent CSS overrides
            barEl.style.transition = 'stroke-dashoffset 1.5s cubic-bezier(0.34, 1.56, 0.64, 1)';
            barEl.setAttribute('stroke-dashoffset', newOffset);
            
            // Interactive glowing effect if goal reached
            if (ratio >= 1 && consumed > 0) {
                barEl.style.filter = "drop-shadow(0 0 8px currentColor)";
            } else {
                barEl.style.filter = "none";
            }
        }
    };
    
    updateRing('p', macros.protein, goals.protein);
    updateRing('c', macros.carbs, goals.carbs);
    updateRing('f', macros.fats, goals.fats);
});

// Update Hydration Reactively
DashboardStore.subscribe('hydration', (hydration) => {
    const waterEl = document.getElementById('waterCount');
    const waveEl = document.getElementById('waterWave');
    
    if (waterEl) waterEl.innerText = hydration.glasses;
    if (waveEl) {
        const heightPct = Math.min((hydration.glasses / hydration.target) * 100, 100);
        waveEl.style.height = `${heightPct}%`;
    }
});

// Update Micronutrient Grid Reactively
DashboardStore.subscribe('micronutrients', (micros) => {
    const grid = document.getElementById('micronutrientGrid');
    if (!grid) return;
    const goals = DashboardStore.state.micronutrientGoals;
    const names = ['Iron', 'Potassium, K', 'Calcium', 'Sodium', 'Fiber, total dietary', 'Vitamin C'];
    const units = {
        'Iron': 'mg',
        'Potassium, K': 'mg',
        'Calcium': 'mg',
        'Sodium': 'mg',
        'Fiber, total dietary': 'g',
        'Vitamin C': 'mg'
    };
    let html = '';
    names.forEach(n => {
        let entry = (micros && micros[n]) ? micros[n] : null;
        let total = 0, pct = 0;
        if (entry && typeof entry === 'object') {
            total = entry.total || 0;
            pct = entry.percent_of_daily || 0;
        } else if (typeof entry === 'number') {
            total = entry;
            pct = goals[n] ? Math.min(100, (total / goals[n]) * 100) : 0;
        }
        const goal = goals[n] || 0;
        const below = (goal > 0 && total < goal);
        const color = below ? (pct < 40 ? 'danger' : 'warning') : 'success';
        const bar = Math.min(100, Math.max(0, pct));
        html += `
            <div class="nutri-glass nutrient-card" style="padding:14px;border-radius:12px;">
                <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:6px;">
                    <b style="font-size:0.82rem;color:#1e293b;">${n}</b>
                    <span style="font-size:0.7rem;color:${below ? '#dc2626':'#059669'};font-weight:600;">${pct.toFixed(0)}%</span>
                </div>
                <div style="font-size:0.9rem;font-weight:700;color:${below ? '#b91c1c':'#0f766e'};margin-bottom:8px;">
                    ${typeof total === 'number' ? total.toFixed(1) : total} ${units[n]} <span style="font-weight:400;color:#94a3b8;font-size:0.75rem;">/ ${goal}${units[n]}</span>
                </div>
                <div style="height:6px;background:#e2e8f0;border-radius:20px;overflow:hidden;">
                    <div style="width:${bar}%;height:100%;background:${below ? (pct<40?'#ef4444':'#f59e0b'):'#10b981'};transition:width .8s ease;"></div>
                </div>
            </div>`;
    });
    grid.innerHTML = html;
});

// Update Debt Badge List Reactively
DashboardStore.subscribe('nutrientDebts', (debts) => {
    const el = document.getElementById('debtBadgeList');
    if (!el) return;
    const list = Array.isArray(debts) ? debts.filter(d => d && d.is_debt) : [];
    if (list.length === 0) {
        el.innerHTML = `<div style="padding:18px;text-align:center;color:#059669;background:#ecfdf5;border-radius:12px;">
            <i class="fas fa-shield-heart" style="font-size:1.6rem;margin-bottom:6px;display:block;"></i>
            <b>No nutrient deficits detected across the last 14 days. Well-balanced!</b>
        </div>`;
        return;
    }
    el.innerHTML = list.map(d => `
        <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;margin-bottom:8px;">
            <div style="width:34px;height:34px;border-radius:50%;background:#fee2e2;color:#b91c1c;display:grid;place-items:center;">
                <i class="fas fa-triangle-exclamation"></i>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-weight:700;color:#7f1d1d;font-size:0.85rem;">${d.nutrient || 'Nutrient'}</div>
                <div style="font-size:0.72rem;color:#991b1b;">14-day avg <b>${(d.avg_daily||0).toFixed(1)}</b> · Threshold <b>${(d.threshold||0).toFixed(1)}</b></div>
            </div>
            <span style="padding:3px 10px;background:#ef4444;color:#fff;border-radius:20px;font-size:0.68rem;font-weight:700;">DEBT</span>
        </div>
    `).join('');
});

// Update AI Recommendation Card List Reactively
DashboardStore.subscribe('aiRecommendations', (recs) => {
    const el = document.getElementById('recommendationCardList');
    if (!el) return;
    const list = Array.isArray(recs) ? recs : [];
    if (list.length === 0) {
        el.innerHTML = `<div style="padding:24px;text-align:center;color:#94a3b8;background:#f8fafc;border-radius:14px;border:1px dashed #cbd5e1;">
            <i class="fas fa-lightbulb" style="font-size:1.8rem;margin-bottom:10px;opacity:0.5;"></i>
            <p style="margin:0;font-weight:600;">No recommendations generated yet.</p>
            <p style="margin:6px 0 0 0;font-size:0.78rem;">Click <b>"Smart Recommendations"</b> to generate your personalized report.</p>
        </div>`;
        return;
    }
    el.innerHTML = list.map((r, i) => `
        <div class="nutri-glass" style="padding:16px;border-radius:14px;margin-bottom:12px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                <div>
                    <span style="padding:3px 10px;background:var(--sb, #6366f1);color:var(--sc,#fff);border-radius:20px;font-size:0.7rem;font-weight:700;">
                        ${r.status || 'pending_staff_review'}
                    </span>
                    <span style="font-size:0.7rem;color:#94a3b8;margin-left:8px;">
                        ${r.created_at ? new Date(r.created_at).toLocaleString() : ''}
                    </span>
                </div>
                <span style="font-size:0.75rem;font-weight:700;color:var(--primary);">
                    <i class="fas fa-robot"></i> AI Generated
                </span>
            </div>
            <div class="recommendation-html-wrap" style="font-size:0.85rem;line-height:1.55;color:#334155;max-height:220px;overflow:auto;">
                ${r.recommendation_text || r.recommendation || '(content saved)'}
            </div>
        </div>
    `).join('');
});

// Bind UI actions to DB Endpoints
window.updateWater = async (actionType) => {
    const btnAdd = document.getElementById('btnWaterAdd');
    const btnRemove = document.getElementById('btnWaterSub');
    if(btnAdd) btnAdd.disabled = true;
    if(btnRemove) btnRemove.disabled = true;

    try {
        const res = await fetch(`api/water_tracker.php?action=${actionType}`);
        const data = await res.json();
        
        if (data.success) {
            DashboardStore.state.hydration = {
                glasses: data.glasses,
                target: data.target || 8
            };
        }
    } catch (e) {
        console.error('Hydration error:', e);
    } finally {
        if(btnAdd) btnAdd.disabled = false;
        if(btnRemove) btnRemove.disabled = false;
    }
};

window.quickBodyUpdate = async (e) => {
    e.preventDefault();
    const weight = document.getElementById('quickWeight').value;
    const height = document.getElementById('quickHeight').value;
    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.innerText;
    
    btn.innerText = 'Updating...';
    btn.disabled = true;
    
    try {
        const res = await fetch('api/save_anthropometrics.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ weight, height })
        });
        const data = await res.json();
        
        if (data.success) {
            DashboardStore.update({
                bodyComp: data.new_bmi,
                bmiHistory: data.updated_history
            });
            
            // Re-render chart if function exists
            if (typeof window.renderBmiChart === 'function') {
                window.renderBmiChart(data.updated_history);
            }
            
            // Close modal
            if(typeof closeModal === 'function') closeModal();
            
            // Show toast success
            alert('Measurements updated successfully!');
        } else {
            alert('Error updating measurements.');
        }
    } catch(e) {
        console.error(e);
        alert('An error occurred.');
    } finally {
        btn.innerText = originalText;
        btn.disabled = false;
    }
};

// Polling function for background dashboard Sync (like messages/dietitian updates)
async function syncDashboardData() {
    try {
        const res = await fetch('api/dashboard_stats.php');
        const data = await res.json();
        if (data && data.success && data.role === 'regular') {
            DashboardStore.update({
                hasDietitian: data.has_dietitian,
                recommendedPlans: data.recommended_plans,
                messages: data.recent_interactions,
                unreadMessages: data.unread_messages,
                macroGoals: data.macro_goals || { calories: 2000, protein: 150, carbs: 200, fats: 65 },
                macros: data.macros // sync daily macros in case food was logged elsewhere
            });
            
            // Re-render conditional Recommended Plans UI
            const plansContainer = document.getElementById('recommendedPlansList');
            if (plansContainer) {
                if (!data.has_dietitian) {
                    plansContainer.innerHTML = `<div style="text-align: center; padding: 30px; color: #94a3b8;">
                        <i class="fas fa-user-clock" style="font-size: 2rem; margin-bottom: 15px; opacity: 0.5;"></i>
                        <p style="font-weight: 600;">Awaiting Dietitian Assignment...</p>
                    </div>`;
                } else if (data.recommended_plans && data.recommended_plans.length === 0) {
                    plansContainer.innerHTML = `<div style="text-align: center; padding: 20px; color: #94a3b8;">
                        <p>No meal plans assigned yet.</p>
                    </div>`;
                }
            }
            
            // Update messages badge
            const msgBadge = document.getElementById('unreadMsgCount');
            if (msgBadge) {
                msgBadge.innerText = `${data.unread_messages} unread`;
            }
            
            // Update Chat Widget if empty
            const feedContainer = document.getElementById('messagesFeedContainer');
            if (feedContainer && data.recent_interactions && data.recent_interactions.length > 0) {
                // If it previously said 'No messages.' or is updating
                if(feedContainer.innerHTML.includes('No recent chat') || feedContainer.innerHTML.includes('Nutri-glass')) {
                    feedContainer.innerHTML = data.recent_interactions.map(m => `
                        <div class="activity-item nutri-glass" style="margin-bottom: 10px; border: none; padding: 12px; cursor: pointer; display: flex; align-items: center;" onclick="location.href='user-messages.php'">
                            <div class="activity-icon ${m.read_at === null ? 'danger' : ''}" style="background: var(--sb); color: var(--sc); width: 35px; height: 35px; min-width: 35px; font-size: 0.8rem;">
                                ${m.staff_name ? m.staff_name.charAt(0).toUpperCase() : 'S'}
                            </div>
                            <div class="activity-content" style="margin-left: 12px;">
                                <p class="activity-text" style="font-size: 0.85rem; margin: 0;"><b>Dr. ${m.staff_name}</b></p>
                                <p class="activity-time" style="font-size: 0.7rem; margin: 2px 0;">${new Date(m.created_at).toLocaleDateString()}</p>
                            </div>
                        </div>
                    `).join('');
                }
            }
        }
    } catch (e) {
        console.warn('Silent sync failed:', e);
    }
}

window.analyzeMicronutrients = async (opts = {}) => {
    const { user_id, silent = false, targetId = null } = opts;
    try {
        if (!silent) {
            const grid = targetId ? document.getElementById(targetId) : null;
            if (grid) grid.innerHTML = `<div style="padding:30px;text-align:center;color:#64748b;"><i class="fas fa-circle-notch fa-spin" style="font-size:1.8rem;"></i><p>Analyzing micronutrient status…</p></div>`;
        }
        const qs = user_id ? `?user_id=${encodeURIComponent(user_id)}` : '';
        const res = await fetch(`api/analyze_micronutrients.php${qs}`);
        const data = await res.json();
        if (data && data.success) {
            DashboardStore.update({
                nutrientDebts: data.nutrients || []
            });
            if (targetId) {
                const debtEl = document.getElementById(targetId);
                if (debtEl) {
                    const debts = (data.nutrients || []).filter(d => d && d.is_debt);
                    debtEl.innerHTML = (debts.length === 0) ?
                        `<div style="padding:18px;text-align:center;color:#059669;background:#ecfdf5;border-radius:12px;">
                            <i class="fas fa-shield-heart" style="font-size:1.6rem;margin-bottom:6px;display:block;"></i>
                            <b>No nutrient deficits detected across the last 14 days.</b>
                        </div>` :
                        debts.map(d => `
                            <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;margin-bottom:8px;">
                                <div style="width:34px;height:34px;border-radius:50%;background:#fee2e2;color:#b91c1c;display:grid;place-items:center;">
                                    <i class="fas fa-triangle-exclamation"></i>
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-weight:700;color:#7f1d1d;font-size:0.85rem;">${d.nutrient}</div>
                                    <div style="font-size:0.72rem;color:#991b1b;">14-day avg <b>${(d.avg_daily||0).toFixed(1)}</b> · Threshold <b>${(d.threshold||0).toFixed(1)}</b></div>
                                </div>
                            </div>`).join('');
                }
            }
            if (!silent && typeof window.showToast === 'function') window.showToast('Micronutrient analysis complete', 'success');
            return data;
        } else {
            if (!silent && typeof window.showToast === 'function') window.showToast(data.message || 'Analysis failed', 'error');
        }
    } catch (e) {
        console.error('analyzeMicronutrients error', e);
    }
    return null;
};

window.generateSmartRecommendations = async (opts = {}) => {
    const { outputId = null } = opts;
    try {
        if (outputId) {
            const out = document.getElementById(outputId);
            if (out) out.innerHTML = `<div style="padding:30px;text-align:center;color:#64748b;"><i class="fas fa-circle-notch fa-spin" style="font-size:1.8rem;"></i><p>Calling Groq AI engine. Generating personalized report…</p></div>`;
        }
        const res = await fetch(`api/generate_user_recommendation.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({})
        });
        const data = await res.json();
        if (data && data.success) {
            const newRec = {
                id: data.recommendation_id,
                recommendation_text: data.recommendation,
                status: 'pending_staff_review',
                created_at: new Date().toISOString()
            };
            const existing = DashboardStore.state.aiRecommendations || [];
            DashboardStore.state.aiRecommendations = [newRec, ...existing];
            if (outputId) {
                const out = document.getElementById(outputId);
                if (out) out.innerHTML = data.recommendation;
            }
            if (typeof window.showToast === 'function') window.showToast('Personalized recommendation generated', 'success');
            return data;
        } else {
            if (outputId) {
                const out = document.getElementById(outputId);
                if (out) out.innerHTML = `<div style="padding:20px;background:#fef2f2;border-radius:10px;color:#b91c1c;"><b>Error:</b> ${data.message || 'Failed to generate recommendation'}</div>`;
            }
            if (typeof window.showToast === 'function') window.showToast(data.message || 'Generation failed', 'error');
        }
    } catch (e) {
        console.error('generateSmartRecommendations error', e);
        if (outputId) {
            const out = document.getElementById(outputId);
            if (out) out.innerHTML = `<div style="padding:20px;background:#fef2f2;border-radius:10px;color:#b91c1c;"><b>Network error:</b> ${e.message}</div>`;
        }
    }
    return null;
};

window.getFoodSuggestionsForDebts = async (opts = {}) => {
    const { outputId = null, user_id = null } = opts;
    try {
        if (outputId) {
            const out = document.getElementById(outputId);
            if (out) out.innerHTML = `<div style="padding:30px;text-align:center;color:#64748b;"><i class="fas fa-circle-notch fa-spin" style="font-size:1.8rem;"></i><p>Matching debts to FCT food suggestions…</p></div>`;
        }
        let debts = DashboardStore.state.nutrientDebts || [];
        if (debts.length === 0) {
            const analysis = await window.analyzeMicronutrients({ user_id, silent: true });
            if (analysis && analysis.nutrients) debts = analysis.nutrients.filter(d => d && d.is_debt);
        } else {
            debts = debts.filter(d => d && d.is_debt);
        }
        const suggestions = {
            'Iron': ['Kangkong (water spinach)', 'Malunggay (moringa)', 'Lentils / mongo beans', 'Lean red meat (small servings)', 'Fortified iron rice', 'Spinach sautéed with calamansi'],
            'Potassium, K': ['Saging na saba (banana)', 'Sweet potato (kamote)', 'Avocado', 'Tomatoes', 'Spinach', 'Coconut water (young buko)'],
            'Calcium': ['Low-fat milk or fortified plant milk', 'Cheese (kesong puti)', 'Saluyot leaves', 'Sardines in tomato sauce with bones', 'Tofu (tokwa)', 'Almonds / almond butter'],
            'Sodium': ['Reduce: patis, bagoong, toyo, canned goods', 'Use herbs & calamansi for flavor instead of salt', 'Check labels for <120mg sodium/serving', 'Prefer fresh over processed ulam', 'Limit instant noodles & chips', 'Rinse canned legumes before cooking'],
            'Fiber, total dietary': ['Brown rice instead of white', 'Oatmeal / rolled oats', 'Mongo beans, chickpeas (garbanzos)', 'Whole-wheat pandesal', 'Vegetables: okra, sitaw, petchay', 'Fruit: mangga, pinya, dalandan'],
            'Vitamin C': ['Calamansi juice (warm, daily)', 'Dalandan / oranges', 'Pomelo (suha)', 'Guava (bayabas)', 'Bell peppers (green/red)', 'Strawberries or mango']
        };
        const nutrientShortUnit = {
            'Iron': 'mg',
            'Potassium, K': 'mg',
            'Calcium': 'mg',
            'Sodium': 'mg',
            'Fiber, total dietary': 'g',
            'Vitamin C': 'mg'
        };
        let html = '';
        if (debts.length === 0) {
            html = `<div style="padding:20px;background:#ecfdf5;border-radius:12px;color:#065f46;text-align:center;">
                <i class="fas fa-check-circle" style="font-size:1.8rem;margin-bottom:8px;"></i>
                <b>No deficits currently detected.</b>
                <p style="margin:6px 0 0 0;font-size:0.85rem;">Keep up the balanced variety across Go, Grow, Glow food groups.</p>
            </div>`;
        } else {
            html = debts.map(d => {
                const name = d.nutrient || '';
                const list = suggestions[name] || [];
                const u = nutrientShortUnit[name] || '';
                const gap = Math.max(0, (d.threshold || 0) - (d.avg_daily || 0));
                return `
                    <div style="padding:16px;border-radius:14px;background:#fff;border:1px solid #e2e8f0;margin-bottom:14px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                            <div>
                                <div style="font-size:0.75rem;color:#64748b;">Deficit Nutrient</div>
                                <div style="font-weight:700;font-size:1.05rem;color:#111827;">${name}</div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:0.7rem;color:#94a3b8;">14-day gap</div>
                                <div style="font-weight:700;color:#dc2626;">${gap.toFixed(1)} ${u}/day</div>
                            </div>
                        </div>
                        <div style="font-size:0.82rem;color:#334155;line-height:1.6;">
                            <b style="color:#0f766e;">FCT Food suggestions:</b>
                            <ul style="margin:6px 0 0 18px;padding:0;">
                                ${list.map(f => `<li style="margin-bottom:3px;">${f}</li>`).join('')}
                            </ul>
                        </div>
                    </div>`;
            }).join('');
        }
        if (outputId) {
            const out = document.getElementById(outputId);
            if (out) out.innerHTML = html;
        }
        return { success: true, html, debts };
    } catch (e) {
        console.error('getFoodSuggestionsForDebts error', e);
        return { success: false, error: e.message };
    }
};

window.analyzeTodayIntake = async (opts = {}) => {
    const { date, user_id, outputId = 'todayAnalysisResult' } = opts;
    try {
        const outEl = document.getElementById(outputId);
        if (outEl) {
            outEl.style.display = 'block';
            outEl.innerHTML = `<div style="padding:24px;text-align:center;color:#64748b;"><i class="fas fa-circle-notch fa-spin" style="font-size:1.8rem;"></i><p>Aggregating today's intake totals…</p></div>`;
        }
        let url = 'api/micronutrient_totals.php?grouped=true';
        if (date) url += `&date=${encodeURIComponent(date)}`;
        if (user_id) url += `&user_id=${encodeURIComponent(user_id)}`;
        const res = await fetch(url);
        const data = await res.json();
        if (data && data.success) {
            DashboardStore.update({ micronutrients: data.micronutrients || {} });
            if (outEl) {
                const macros = data.macros || { calories:0, protein:0, carbs:0, fat:0 };
                const goals = DashboardStore.state.macroGoals || { calories:2000, protein:150, carbs:200, fats:65 };
                const ringStyle = (name, v, g, color) => {
                    const r = Math.min(1, g>0 ? v/g : 0);
                    const off = 213.6 - (r*213.6);
                    return `
                        <div style="text-align:center;">
                            <div style="width:78px;height:78px;position:relative;display:inline-flex;">
                                <svg viewBox="0 0 100 100" style="width:100%;height:100%;transform:rotate(-90deg);">
                                    <circle cx="50" cy="50" r="34" stroke="#e2e8f0" stroke-width="12" fill="none"/>
                                    <circle cx="50" cy="50" r="34" stroke="${color}" stroke-width="12" fill="none"
                                            stroke-linecap="round" stroke-dasharray="213.6" stroke-dashoffset="${off}"
                                            style="transition:stroke-dashoffset 1.2s cubic-bezier(0.34,1.56,0.64,1);"/>
                                </svg>
                                <div style="position:absolute;inset:0;display:grid;place-items:center;font-weight:700;font-size:0.8rem;color:#1e293b;">
                                    ${Math.round(r*100)}%
                                </div>
                            </div>
                            <div style="font-size:0.78rem;margin-top:4px;font-weight:700;color:#334155;">${name}</div>
                            <div style="font-size:0.7rem;color:#64748b;">${v} / ${g}${name==='Calories'?'kcal':'g'}</div>
                        </div>`;
                };
                const microCards = Object.entries(data.micronutrients || {}).map(([n, entry]) => {
                    const t = entry.total || 0;
                    const th = entry.threshold || 0;
                    const p = entry.percent_of_daily || 0;
                    const below = entry.is_below_threshold;
                    const u = n==='Fiber, total dietary' ? 'g' : 'mg';
                    const bar = Math.min(100, p);
                    const col = below ? (p<40 ? '#ef4444' : '#f59e0b') : '#10b981';
                    return `
                        <div class="nutri-glass" style="padding:14px;border-radius:12px;">
                            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:6px;">
                                <b style="font-size:0.82rem;color:#1e293b;">${n}</b>
                                <span style="font-size:0.7rem;color:${col};font-weight:700;">${p}%</span>
                            </div>
                            <div style="font-size:0.9rem;font-weight:700;color:${col};margin-bottom:8px;">
                                ${typeof t === 'number' ? t.toFixed(1) : t} ${u}
                                <span style="font-weight:400;color:#94a3b8;font-size:0.72rem;">/ ${th}${u}</span>
                            </div>
                            <div style="height:6px;background:#e2e8f0;border-radius:20px;overflow:hidden;">
                                <div style="width:${bar}%;height:100%;background:${col};transition:width .8s ease;"></div>
                            </div>
                        </div>`;
                }).join('');
                outEl.innerHTML = `
                    <div class="nutri-glass" style="padding:20px;border-radius:16px;margin-bottom:16px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                            <h3 style="margin:0;font-family:Outfit;"><i class="fas fa-chart-pie" style="color:var(--primary);margin-right:8px;"></i>Today's Intake Summary</h3>
                            <span style="font-size:0.75rem;color:#64748b;">${data.date}</span>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;">
                            ${ringStyle('Calories', +macros.calories.toFixed(0), goals.calories, '#ef4444')}
                            ${ringStyle('Protein',  +macros.protein.toFixed(0),  goals.protein, '#0ea5e9')}
                            ${ringStyle('Carbs',    +macros.carbs.toFixed(0),    goals.carbs, '#f59e0b')}
                            ${ringStyle('Fat',      +macros.fat.toFixed(0),      goals.fats, '#a855f7')}
                        </div>
                    </div>
                    <div style="margin-bottom:14px;">
                        <h3 style="font-family:Outfit;margin:0 0 10px 0;"><i class="fas fa-flask-vial" style="color:#f59e0b;margin-right:8px;"></i> Micronutrient Totals (6 tracked)</h3>
                        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
                            ${microCards}
                        </div>
                    </div>
                `;
            }
            return data;
        } else if (outEl) {
            outEl.innerHTML = `<div style="padding:20px;background:#fef2f2;border-radius:12px;color:#b91c1c;"><b>Error:</b> ${data.message || 'Unable to aggregate today\'s intake'}</div>`;
        }
    } catch (e) {
        console.error('analyzeTodayIntake error', e);
    }
    return null;
};

// Initial Sync and Poll Setup
document.addEventListener('DOMContentLoaded', () => {
    syncDashboardData();
    setInterval(syncDashboardData, 30000); // 30s polling
});
