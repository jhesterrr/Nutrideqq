// scripts/admin-realtime.js
// Real-time Admin Dashboard Controller
(function() {
    'use strict';

    const POLL_INTERVAL = 5000; // 5 seconds
    let efficiencyChart = null;

    function init() {
        console.log('Admin Real-time Engine Started');
        updateAllStats();
        setInterval(updateAllStats, POLL_INTERVAL);

        // Manual Refresh Button
        const refreshBtn = document.getElementById('refreshSystemActivity');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function() {
                const icon = this.querySelector('i');
                if (icon) icon.classList.add('fa-spin');
                updateAllStats();
                setTimeout(() => { if (icon) icon.classList.remove('fa-spin'); }, 1000);
            });
        }
    }

    function updateAllStats() {
        fetch('api/admin_stats.php', { cache: 'no-store' })
            .then(async res => {
                const text = await res.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('[NutriDeq] API Response Parse Error:', text.substring(0, 200));
                    throw new Error('API returned invalid format (see console).');
                }
            })
            .then(data => {
                if (data && data.success) {
                    renderInfluenceList(data.staff_influence || []);
                    renderEfficiency(data.efficiency || { percentage: 0, today: 0, avg: 0 });
                    renderActivityFeed(data.recent_activity || []);
                    
                    if (data.workload) {
                        const pct = data.workload.max_capacity ? Math.round((data.workload.total_clients / data.workload.max_capacity) * 100) : 0;
                        const progressBar = document.getElementById('workloadProgressBar');
                        const workloadText = document.getElementById('workloadText');
                        if (progressBar) progressBar.style.width = pct + '%';
                        if (workloadText) workloadText.innerText = `${data.workload.total_clients} Active Clients (${pct}% Capacity)`;
                    }
                } else {
                    const msg = data ? data.error : 'Unknown Sync Error';
                    showFeedError(`<i class="fas fa-exclamation-triangle"></i> Sync Error: ${msg}`);
                }
            })
            .catch(err => {
                console.error('[NutriDeq] Feed Fetch Error:', err);
                showFeedError(`<i class="fas fa-wifi"></i> Sync Offline: Connection or API Failure`);
            });
    }

    function showFeedError(message) {
        const list = document.getElementById('recentActivityList');
        if (list) {
            // Only overwrite if it's currently attempting to load or already empty, to prevent clearing good data on a momentary hiccup
            if (list.innerHTML.includes('fa-spinner') || list.children.length === 0 || list.innerHTML.includes('Sync')) {
                list.innerHTML = `<div style="text-align: center; padding: 30px; color: #ef4444; font-weight: 600;">${message}</div>`;
            }
        }
    }

    function renderInfluenceList(staff) {
        const list = document.getElementById('staffInfluenceList');
        if (!list) return;

        list.innerHTML = staff.map(s => `
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: rgba(0,0,0,0.02); border-radius: 8px;">
                <div style="font-weight: 600; font-size: 0.9rem;">${s.name}</div>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <span style="font-size: 0.75rem; padding: 2px 8px; background: ${s.score > 5 ? '#e9f7ef' : '#fef9e7'}; color: ${s.score > 5 ? '#27ae60' : '#f39c12'}; border-radius: 10px;">${s.label}</span>
                    <strong style="color: var(--primary);">${s.score}</strong>
                </div>
            </div>
        `).join('') || '<div style="color: #999;">No staff activity detected yet.</div>';
    }

    function renderEfficiency(eff) {
        if (!eff) return;
        
        try {
            const pctLabel = document.getElementById('efficiencyPctLabel');
            const desc = document.getElementById('efficiencyDescription');
            const trend = document.getElementById('efficiencyTrendLine');
            
            if (pctLabel) pctLabel.innerText = eff.percentage + '%';
            if (desc) desc.innerText = `Platform handling ${eff.today} logs today compared to ${eff.avg} weekly avg.`;
            if (trend) {
                const isGood = eff.percentage >= 100;
                trend.innerHTML = `<i class="fas fa-arrow-${isGood ? 'up' : 'down'}"></i> Efficiency is ${isGood ? 'Higher' : 'Lower'} than average`;
                trend.style.color = isGood ? 'var(--primary)' : '#e74c3c';
            }

            renderDoughnut(eff.percentage);
        } catch (e) {
            console.warn('[NutriDeq] Efficiency render failed:', e.message);
        }
    }

    function renderDoughnut(pct) {
        const ctx = document.getElementById('efficiencyDoughnutChart');
        if (!ctx) return;

        try {
            // Check if Chart.js has already bound this canvas (dashboard.php does this on load)
            let existingChart = Chart.getChart(ctx);
            
            if (existingChart) {
                existingChart.data.datasets[0].data = [pct, 100 - pct];
                existingChart.update();
            } else {
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        datasets: [{
                            data: [pct, 100 - pct],
                            backgroundColor: ['#2E8B57', '#e9ecef'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        cutout: '80%',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false }, tooltip: { enabled: false } }
                    }
                });
            }
        } catch (e) {
            console.warn('[NutriDeq] Doughnut chart skipped:', e.message);
        }
    }

    function renderActivityFeed(activities) {
        const list = document.getElementById('recentActivityList');
        if (!list) return;

        if (activities.length === 0) {
            list.innerHTML = '<div style="text-align center; padding: 20px; color: #999;">No recent activity found.</div>';
            return;
        }

        list.innerHTML = activities.map(act => `
            <div class="activity-item" style="animation: fadeIn 0.5s ease forwards;">
                <div class="activity-icon ${act.status}">
                    <i class="fas ${getIcon(act.type)}"></i>
                </div>
                <div class="activity-details">
                    <div class="activity-title">${act.title}</div>
                    <div class="activity-description">${act.description}</div>
                    <div class="activity-time">${formatTime(act.created_at)}</div>
                </div>
            </div>
        `).join('');
    }

    function getIcon(type) {
        switch(type) {
            case 'user': return 'fa-user-check';
            case 'food': return 'fa-utensils';
            case 'message': return 'fa-comment-alt';
            default: return 'fa-cog';
        }
    }

    function formatTime(dateStr) {
        const date = new Date(dateStr);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.round(diffMs / 60000);
        
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins} mins ago`;
        if (diffMins < 1440) return `${Math.round(diffMins/60)} hours ago`;
        return date.toLocaleDateString();
    }

    document.addEventListener('DOMContentLoaded', init);
})();
