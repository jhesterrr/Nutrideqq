// scripts/user-realtime.js
// Real-time synchronization for the Client Dashboard
(function() {
    'use strict';
    const POLL_INTERVAL = 30000; // 30 seconds

    function init() {
        console.log('Client Real-time Engine Started');
        updateUserStats();
    }

    function updateUserStats() {
        fetch('api/dashboard_stats.php')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.role === 'regular') {
                    // 1. Macros
                    const m = data.macros;
                    updateMacro('p', m.protein, 200); // Using hardcoded 200/400/80 targets for now
                    updateMacro('c', m.carbs, 400);
                    updateMacro('f', m.fats, 80);
                    
                    const heroMsg = document.querySelector('.dash-hero-content p b');
                    if (heroMsg) heroMsg.innerText = m.calories + ' kcal';

                    // 2. Hydration
                    const waterCount = document.getElementById('waterCount');
                    const waterWave = document.getElementById('waterWave');
                    if (waterCount) waterCount.innerText = data.water;
                    if (waterWave) waterWave.style.height = Math.min(data.water * 12.5, 100) + '%';

                    // 3. Unread Messages
                    const messageTile = document.querySelector('a[href="user-messages.php"] .command-tile-info p');
                    if (messageTile) messageTile.innerText = data.unread_messages + ' unread';

                    // 4. Recommended Plans
                    const plansList = document.querySelector('.meal-plans-list');
                    if (plansList && data.recommended_plans) {
                        if (data.recommended_plans.length > 0) {
                            let plansHtml = '';
                            data.recommended_plans.forEach(plan => {
                                plansHtml += `
                                    <div class="meal-plan-card" style="padding: 20px; border: none; margin-bottom: 15px; background: rgba(0,0,0,0.02); border-radius: 15px;">
                                        <div class="d-flex justify-content-between align-items-center" style="margin-bottom: 10px;">
                                            <h3 style="font-family: 'Outfit'; font-weight: 700; margin: 0; font-size: 1.1rem;">${escapeHtml(plan.plan_name)}</h3>
                                            <span style="background: var(--primary); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700;">${plan.calories} kcal</span>
                                        </div>
                                        <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 12px;"><i class="fas fa-user-md" style="margin-right: 5px;"></i> Dr. ${escapeHtml(plan.staff_name)}</div>
                                        <p style="font-size: 0.9rem; line-height: 1.5; color: #1e293b;">${escapeHtml(plan.description.substring(0, 120))}...</p>
                                        <div class="d-flex justify-content-between align-items-center" style="margin-top: 15px; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 15px;">
                                            <span style="font-size: 0.75rem; color: #94a3b8;">Created: ${formatDate(plan.created_at)}</span>
                                            <button class="btn btn-outline" style="border-radius: 8px; font-size: 0.8rem; padding: 6px 12px;" onclick="viewMealPlan(${plan.id})">View Full Plan</button>
                                        </div>
                                    </div>`;
                            });
                            plansList.innerHTML = plansHtml;
                        } else {
                            plansList.innerHTML = `
                                <div style="text-align: center; padding: 40px; color: #94a3b8;">
                                    <i class="fas fa-utensils" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i>
                                    <p>Waiting for dietitian recommendations.</p>
                                </div>`;
                        }
                    }

                    // 5. Recent Interactions
                    const activityFeed = document.querySelector('.activity-feed');
                    if (activityFeed && data.recent_interactions) {
                        if (data.recent_interactions.length > 0) {
                            let interactionsHtml = '';
                            data.recent_interactions.forEach(item => {
                                interactionsHtml += `
                                    <div class="activity-item nutri-glass" style="margin-bottom: 12px; border: none; padding: 15px; cursor: pointer; transition: all 0.2s ease; background: rgba(0,0,0,0.015);" onclick="location.href='user-messages.php'">
                                        <div class="activity-icon" style="background: rgba(79, 172, 254, 0.1); color: #4facfe; width: 32px; height: 32px; min-width: 32px; font-size: 0.8rem;">
                                            ${item.staff_name.substring(0, 1).toUpperCase()}
                                        </div>
                                        <div class="activity-details" style="margin-left: 12px;">
                                            <div style="font-weight: 700; font-size: 0.85rem; color: #1e293b;">${escapeHtml(item.staff_name)}</div>
                                            <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">${escapeHtml(item.message.substring(0, 40))}...</div>
                                        </div>
                                    </div>`;
                            });
                            activityFeed.innerHTML = interactionsHtml;
                        } else {
                            activityFeed.innerHTML = `
                                <div style="text-align: center; padding: 40px; color: #94a3b8;">
                                    <i class="fas fa-comments" style="font-size: 1.5rem; margin-bottom: 8px; opacity: 0.4;"></i>
                                    <p style="font-size: 0.8rem;">No recent chat.</p>
                                </div>`;
                        }
                    }

                    // 6. Body Composition Insight
                    if (data.bmi) {
                        const bmiVal = document.getElementById('userBmiValueDisplay');
                        const bmiStatus = document.getElementById('userBmiStatusDisplay');
                        const bmiInsight = document.getElementById('userBmiInsightDisplay');
                        
                        if (bmiVal) bmiVal.innerText = data.bmi.value || '--';
                        if (bmiStatus) {
                            bmiStatus.innerText = data.bmi.status;
                            bmiStatus.className = 'bmi-status-badge status-' + data.bmi.status.toLowerCase();
                        }
                        if (bmiInsight) {
                            const status = data.bmi.status;
                            let insightText = "Please update your height and weight in the Body Stats section to see your BMI analysis.";
                            if (status === 'Underweight') insightText = "Your BMI indicates you may be underweight. We recommend consulting with your dietician to ensure you're meeting your nutritional needs.";
                            else if (status === 'Normal') insightText = "Great job! Your BMI falls within the healthy range. Maintaining a balanced diet and regular physical activity will help keep you here.";
                            else if (status === 'Overweight') insightText = "Your BMI is in the overweight category. Your dietician can help you create a sustainable plan for reaching your health goals.";
                            else if (status === 'Obese') insightText = "Your BMI indicates obesity. This can increase health risks, but our team is here to support you with a personalized clinical nutrition plan.";
                            bmiInsight.innerText = insightText;
                        }

                        // Update Chart
                        if (window.userBmiChart && data.bmi_history && data.bmi_history.length > 0) {
                            const labels = data.bmi_history.map(h => h.date_label);
                            const bmiData = data.bmi_history.map(h => parseFloat(h.bmi));
                            
                            window.userBmiChart.data.labels = labels;
                            window.userBmiChart.data.datasets[0].data = bmiData;
                            window.userBmiChart.update('none');
                        }
                    }
                }
            })
            .catch(err => console.error('Realtime fetch failed:', err));
    }

    function updateMacro(type, current, target) {
        const bar = document.getElementById(type + '_bar');
        const val = document.getElementById(type + '_val');
        if (!bar || !val) return;

        const percent = Math.min((current / target) * 100, 100);
        const offset = 213.6 - (213.6 * percent) / 100;
        bar.style.strokeDashoffset = offset;
        val.innerText = current + 'g';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDate(dateString) {
        const options = { month: 'short', day: 'numeric', year: 'numeric' };
        return new Date(dateString).toLocaleDateString('en-US', options);
    }

    // Helper functions for quick actions
    window.viewMealPlan = function(planId) {
        window.location.href = 'user-meal-plans.php?view=' + planId;
    };

    window.viewAppointment = function(appointmentId) {
        window.location.href = 'user-appointments.php?view=' + appointmentId;
    };

    window.viewMessage = function(messageId) {
        window.location.href = 'user-messages.php?view=' + messageId;
    };

    window.updateWater = function(action) {
        fetch('api/water_tracker.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=' + action
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const count = document.getElementById('waterCount');
                const wave = document.getElementById('waterWave');
                if (count) count.innerText = data.glasses;
                if (wave) wave.style.height = (data.glasses * 12.5) + '%';
            }
        })
        .catch(err => console.error("Hydration update failed:", err));
    };

    document.addEventListener('DOMContentLoaded', () => {
        init();
        setInterval(updateUserStats, 5000);
    });
})();
