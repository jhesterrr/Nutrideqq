// NutriDeq Dashboard & Global UI Logic
(function () {
    'use strict';

    // Prevent multiple initializations
    if (window.dashboardInitialized) return;
    window.dashboardInitialized = true;

    console.log('NutriDeq Dashboard JS Initialized');

    // Simple debounce helper
    function debounce(fn, wait) {
        let t;
        return function (...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), wait);
        };
    }

    // Initialize global functionality
    function init() {
        console.log('DOM Content Loaded - Initializing Dashboard Components');
        
        initSidebarCollapse();
        initGlobalSearch();
        initLogoutHandlers();
        initGlobalKeyHandlers();
    }

    // 1. Sidebar Collapse Logic (Desktop)
    function initSidebarCollapse() {
        const collapseBtn = document.getElementById('sidebarCollapseBtn');
        const sidebar = document.getElementById('mainSidebar');
        
        if (!collapseBtn || !sidebar) {
            console.warn('Dashboard JS: Sidebar elements not found, skipping collapse init.');
            return;
        }

        console.log('Wiring up desktop sidebar collapse component...');

        // Check local storage for initial state
        const isCollapsed = localStorage.getItem('nutrideq_sidebar_collapsed') === 'true';
        if (isCollapsed) {
            document.body.classList.add('sidebar-collapsed');
            sidebar.classList.add('collapsed');
        }

        collapseBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const willCollapse = !sidebar.classList.contains('collapsed');
            
            sidebar.classList.toggle('collapsed');
            document.body.classList.toggle('sidebar-collapsed');
            
            localStorage.setItem('nutrideq_sidebar_collapsed', willCollapse);
            
            // Trigger a resize event to quickly fix any charts (Chart.js) that might need re-rendering
            setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
            }, 300);
        });
    }

    // 2. Global Search Logic
    function initGlobalSearch() {
        const inputs = document.querySelectorAll('input.global-search[data-target], input#searchInput[data-target]');
        if (!inputs.length) return;

        inputs.forEach((inp) => {
            const targetSelector = inp.getAttribute('data-target');
            if (!targetSelector) return;

            const handler = debounce(function (e) {
                const term = (e.target.value || '').trim().toLowerCase();
                const rows = document.querySelectorAll(targetSelector);
                
                rows.forEach((row) => {
                    if (term === '') {
                        row.style.display = '';
                        return;
                    }
                    const text = (row.textContent || '').replace(/\s+/g, ' ').toLowerCase();
                    row.style.display = text.includes(term) ? '' : 'none';
                });
            }, 180);

            inp.addEventListener('input', handler);
            
            inp.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const visibleRows = Array.from(document.querySelectorAll(targetSelector))
                                           .filter(r => r.style.display !== 'none');
                    if (visibleRows.length > 0) {
                        visibleRows[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            });
        });
    }

    // 3. Logout Modal Utility
    function initLogoutHandlers() {
        const logoutBtn = document.getElementById('logoutBtn');
        const logoutModal = document.getElementById('logoutModal');
        const cancelLogout = document.getElementById('cancelLogout');
        const confirmLogout = document.getElementById('confirmLogout');

        if (logoutBtn && logoutModal) {
            logoutBtn.addEventListener('click', (e) => {
                e.preventDefault();
                logoutModal.style.display = 'flex';
                logoutModal.classList.add('active');
            });
        }

        if (cancelLogout && logoutModal) {
            cancelLogout.addEventListener('click', () => {
                logoutModal.style.display = 'none';
                logoutModal.classList.remove('active');
            });
        }

        if (confirmLogout) {
            confirmLogout.addEventListener('click', () => {
                window.location.href = 'login-logout/logout.php';
            });
        }

        if (logoutModal) {
            logoutModal.addEventListener('click', function (e) {
                if (e.target === this) {
                    this.style.display = 'none';
                    this.classList.remove('active');
                }
            });
        }
    }

    // 4. Global Key Handlers (ESC to close modals)
    function initGlobalKeyHandlers() {
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                // Close modals
                document.querySelectorAll('.modal-overlay, .logout-modal').forEach(el => {
                    el.style.display = 'none';
                    el.classList.remove('active');
                });
                document.body.style.overflow = '';
            }
        });
    }

    // Initialize everything
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Public API
    window.dashboardInit = init;
    window.toggleSidebar = function() {
        const toggleBtn = document.getElementById('mobileNavToggle');
        if (toggleBtn) toggleBtn.click();
    };
}());
