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
    healthScore: 0
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

// Initial Sync and Poll Setup
document.addEventListener('DOMContentLoaded', () => {
    syncDashboardData();
    setInterval(syncDashboardData, 30000); // 30s polling
});
