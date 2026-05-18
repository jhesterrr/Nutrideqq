// scripts/staff-realtime.js
// Real-time synchronization for the Staff Dashboard
(function() {
    'use strict';
    const POLL_INTERVAL = 30000; // 30 seconds

    function init() {
        console.log('Staff Real-time Engine Started');
        updateStaffStats();
        setInterval(updateStaffStats, POLL_INTERVAL);
    }

    function updateStaffStats() {
        fetch('api/dashboard_stats.php')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.role === 'staff') {
                    updateUnreadCount(data.unread_count);
                    updateInteractions(data.interactions);
                    updateStaffChart(data.weekly_logs);
                }
            })
            .catch(err => console.error('Staff sync error:', err));
    }

    function updateUnreadCount(count) {
        const badge = document.querySelector('.badge-status');
        if (badge && count > 0) {
            badge.innerText = `${count} New`;
            badge.classList.add('online');
        } else if (badge) {
            badge.innerText = 'Online';
            badge.classList.remove('online');
        }
    }

    function updateInteractions(interactions) {
        const container = document.querySelector('.activity-feed');
        if (!container || !interactions) return;

        // Simplified interaction update: if count changed or first load, re-render
        // For now, let's just clear the "stuck" state if it were there (though Staff doesn't have a spinner currently)
    }

    function updateStaffChart(logData) {
        // Handle Chart.js update if available
        if (window.staffWeeklyChart && logData) {
            window.staffWeeklyChart.data.datasets[0].data = logData.counts;
            window.staffWeeklyChart.update();
        }
    }

    document.addEventListener('DOMContentLoaded', init);
})();
