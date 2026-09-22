// scripts/realtime-dashboard.js
(function () {
    'use strict';

    // Poll every 10 seconds
    const POLL_INTERVAL = 10000;

    function fetchWellnessStats() {
        fetch(BASE_URL + 'api/get_wellness_stats.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateUnreadCount(data.unread_count);
                }
            })
            .catch(err => console.error('Stats polling error:', err));
    }

    function updateUnreadCount(count) {
        const countEl = document.getElementById('unreadMessages');
        if (!countEl) return; // Might not exist on all roles

        // Animate if changed
        const current = parseInt(countEl.innerText) || 0;
        if (current !== count) {
            countEl.innerText = count;

            // Visual flair
            if (count > 0) {
                countEl.parentElement.parentElement.querySelector('.metric-trend').className = 'metric-trend trend-alert';
                countEl.parentElement.parentElement.querySelector('.metric-trend span').innerText = 'Needs attention';
                countEl.parentElement.parentElement.querySelector('.metric-trend i').className = 'fas fa-exclamation-circle';
            } else {
                countEl.parentElement.parentElement.querySelector('.metric-trend').className = 'metric-trend trend-neutral';
                countEl.parentElement.parentElement.querySelector('.metric-trend span').innerText = 'All caught up';
                countEl.parentElement.parentElement.querySelector('.metric-trend i').className = 'fas fa-check-circle';
            }
        }
    }

    // Start Polling
    fetchWellnessStats(); // Initial check
    setInterval(fetchWellnessStats, POLL_INTERVAL);

})();
