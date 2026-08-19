<?php
// includes/sidebar.php

if (!function_exists('getSidebarInitials')) {
    function getSidebarInitials($name)
    {
        $names = explode(' ', $name);
        $initials = '';
        foreach ($names as $n) {
            if (!empty($n)) {
                $initials .= strtoupper($n[0]);
            }
        }
        return substr($initials, 0, 2);
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
$sidebar_user_role = $_SESSION['user_role'] ?? 'regular';
$sidebar_user_name = $_SESSION['user_name'] ?? 'User';
$sidebar_user_initials = getSidebarInitials($sidebar_user_name);

require_once __DIR__ . '/../navigation.php';
$sidebar_nav_links = getNavigationLinks($sidebar_user_role, $current_page);

if (isset($_SESSION['user_id'])) {
    try {
        if (!isset($pdo)) {
            require_once __DIR__ . '/../database.php';
            $sidebar_db = new Database();
            $pdo = $sidebar_db->getConnection();
        }
        $update_activity = $pdo->prepare("UPDATE users SET last_active = NOW(), online_status = 1 WHERE id = ?");
        $update_activity->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {
        error_log("Sidebar status update failed: " . $e->getMessage());
    }
}
?>
<!-- Mobile Header -->
<header class="nd-mobile-header">
    <a href="dashboard.php" class="nd-mobile-header__logo">
        <svg viewBox="0 0 240 240" class="nd-logo-svg">
            <path d="M120 40 L165 90 L120 135 L75 90 Z" fill="#10b981"/>
            <path d="M75 90 L120 135 L100 190 L55 135 Z" fill="#059669"/>
            <path d="M165 90 L185 135 L140 190 L120 135 Z" fill="#34d399"/>
        </svg>
        <div class="brand-text-wrapper" style="font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.5px;">
            <span style="font-weight: 800; color: #0F172A;">NUTRI</span><span style="font-weight: 200; color: #64748B;">DEQ</span>
        </div>
    </a>
    <div class="nd-mobile-header__actions">
        <a href="#" onclick="showLogoutConfirmation()" class="nd-mobile-header__btn" title="Logout">
            <i class="fas fa-sign-out-alt"></i>
        </a>
        <button class="nd-mobile-header__btn" id="ndMobileNavToggle" aria-label="Toggle Menu">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</header>

<!-- Sidebar Overlay -->
<div class="nd-sidebar-overlay" id="ndSidebarOverlay"></div>

<!-- Main Sidebar -->
<aside class="nd-sidebar" id="ndSidebar">
    <div class="nd-sidebar__header">
        <a href="dashboard.php" class="nd-sidebar__logo">
            <svg viewBox="0 0 240 240" class="nd-logo-svg">
                <path d="M120 40 L165 90 L120 135 L75 90 Z" fill="#10b981"/>
                <path d="M75 90 L120 135 L100 190 L55 135 Z" fill="#059669"/>
                <path d="M165 90 L185 135 L140 190 L120 135 Z" fill="#34d399"/>
            </svg>
            <div class="nd-sidebar__logo-text" style="font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.5px;">
                <span style="font-weight: 800; color: #0F172A;">NUTRI</span><span style="font-weight: 200; color: #64748B;">DEQ</span>
            </div>
        </a>
        <button class="nd-sidebar__toggle" id="ndSidebarCollapse" title="Toggle Sidebar">
            <i class="fas fa-angles-left"></i>
        </button>
    </div>

    <ul class="nd-sidebar__nav">
        <?php foreach ($sidebar_nav_links as $nav_item): 
            $item_text = $nav_item['text'] ?? '';
            $item_icon = $nav_item['icon'] ?? '';
            $item_href = $nav_item['href'] ?? '#';
            $is_header = isset($nav_item['type']) && $nav_item['type'] === 'header';
        ?>
            <?php if ($is_header): ?>
                <li class="nd-sidebar__nav-header"><?php echo htmlspecialchars($item_text); ?></li>
            <?php else: ?>
                <li class="nd-sidebar__nav-item">
                    <a href="<?php echo htmlspecialchars($item_href); ?>" class="nd-sidebar__nav-link <?php echo !empty($nav_item['active']) ? 'active' : ''; ?>">
                        <i class="<?php echo htmlspecialchars($item_icon); ?> nd-sidebar__nav-icon"></i>
                        <span class="nd-sidebar__nav-text"><?php echo htmlspecialchars($item_text); ?></span>
                    </a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <li class="nd-sidebar__nav-header">Account</li>
        <li class="nd-sidebar__nav-item">
            <a href="#" onclick="showLogoutConfirmation()" class="nd-sidebar__nav-link" style="color: var(--nd-danger);">
                <i class="fas fa-sign-out-alt nd-sidebar__nav-icon" style="color: var(--nd-danger);"></i>
                <span class="nd-sidebar__nav-text">Logout</span>
            </a>
        </li>
    </ul>


    <div class="nd-sidebar__profile">
        <div class="nd-sidebar__avatar"><?php echo $sidebar_user_initials; ?></div>
        <div class="nd-sidebar__profile-info">
            <div class="nd-sidebar__profile-name"><?php echo htmlspecialchars($sidebar_user_name); ?></div>
            <div class="nd-sidebar__profile-role"><?php echo getUserRoleText($sidebar_user_role); ?></div>
        </div>
    </div>
</aside>

<!-- Logout Confirmation Modal (Moved outside sidebar to escape CSS transform bounding context) -->
<div class="nd-logout-modal" id="logoutModal">
    <div class="nd-logout-modal__content">
        <div class="nd-logout-modal__icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <h3 class="nd-logout-modal__title">Confirm Logout</h3>
        <p class="nd-logout-modal__message">Are you sure you want to log out of your account?</p>
        <div class="nd-logout-modal__actions">
            <button class="nd-logout-modal__btn nd-logout-modal__btn--cancel" onclick="hideLogoutConfirmation()">Cancel</button>
            <a href="login-logout/logout.php" class="nd-logout-modal__btn nd-logout-modal__btn--confirm">Yes, Logout</a>
        </div>
    </div>
</div>

<style>
    /* Logout Confirmation Modal Styles */
    .nd-logout-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: var(--app-height-dvh);
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 999999;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .nd-logout-modal.active {
        opacity: 1;
        visibility: visible;
    }

    .nd-logout-modal__content {
        background: white;
        border-radius: 20px;
        padding: 32px;
        max-width: 400px;
        width: 90%;
        text-align: center;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        transform: scale(0.9);
        transition: transform 0.3s ease;
    }

    .nd-logout-modal.active .nd-logout-modal__content {
        transform: scale(1);
    }

    .nd-logout-modal__icon {
        width: 64px;
        height: 64px;
        background: rgba(239, 68, 68, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
    }

    .nd-logout-modal__icon i {
        font-size: 24px;
        color: #ef4444;
    }

    .nd-logout-modal__title {
        font-family: 'Outfit', sans-serif;
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0 0 12px;
    }

    .nd-logout-modal__message {
        font-size: 0.95rem;
        color: #64748b;
        margin: 0 0 24px;
        line-height: 1.5;
    }

    .nd-logout-modal__actions {
        display: flex;
        gap: 12px;
        justify-content: center;
    }

    .nd-logout-modal__btn {
        padding: 12px 24px;
        border-radius: 12px;
        font-family: 'Inter', sans-serif;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .nd-logout-modal__btn--cancel {
        background: #f1f5f9;
        color: #64748b;
        border: none;
    }

    .nd-logout-modal__btn--cancel:hover {
        background: #e2e8f0;
    }

    .nd-logout-modal__btn--confirm {
        background: #ef4444;
        color: white;
        border: none;
    }

    .nd-logout-modal__btn--confirm:hover {
        background: #dc2626;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }
    .nd-logo-svg {
        width: 32px;
        height: 32px;
        overflow: visible;
        margin-right: 12px;
    }
    .nd-sidebar.collapsed .nd-logo-svg {
        margin-right: 0;
    }
</style>

<script>
(function () {
    'use strict';

    const sidebar = document.getElementById('ndSidebar');
    const toggleDesktop = document.getElementById('ndSidebarCollapse');
    const toggleMobile = document.getElementById('ndMobileNavToggle');
    const overlay = document.getElementById('ndSidebarOverlay');
    const toggleIcon = toggleDesktop?.querySelector('i');
    const STORAGE_KEY = 'nd_sidebar_collapsed';
    const MOBILE_BP = 1024;

    if (!sidebar) return;

    /* ── Utility ── */
    const isMobile = () => window.innerWidth <= MOBILE_BP;

    /* ── Desktop: Restore collapse state ── */
    const initDesktopState = () => {
        if (isMobile()) return;
        const wasCollapsed = localStorage.getItem(STORAGE_KEY) === 'true';
        if (wasCollapsed) {
            sidebar.classList.add('collapsed');
            document.body.classList.add('sidebar-collapsed');
            if (toggleIcon) toggleIcon.className = 'fas fa-chevron-right';
        }
    };
    initDesktopState();

    /* ── Desktop: Toggle collapse ── */
    const toggleCollapse = () => {
        if (isMobile()) return;
        const isCollapsed = sidebar.classList.toggle('collapsed');
        document.body.classList.toggle('sidebar-collapsed', isCollapsed);
        if (toggleIcon) toggleIcon.className = isCollapsed ? 'fas fa-angles-right' : 'fas fa-angles-left';
        localStorage.setItem(STORAGE_KEY, isCollapsed);
    };
    if (toggleDesktop) toggleDesktop.addEventListener('click', toggleCollapse);

    /* ── Mobile: Open / Close drawer ── */
    const openMobile = () => {
        sidebar.classList.add('active');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        sidebar.setAttribute('aria-hidden', 'false');
        toggleMobile?.setAttribute('aria-expanded', 'true');
    };

    const closeMobile = () => {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
        sidebar.setAttribute('aria-hidden', 'true');
        toggleMobile?.setAttribute('aria-expanded', 'false');
    };

    if (toggleMobile) toggleMobile.addEventListener('click', openMobile);
    if (overlay) overlay.addEventListener('click', closeMobile);

    /* ── ESC key closes mobile drawer ── */
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && isMobile() && sidebar.classList.contains('active')) {
            closeMobile();
        }
    });

    /* ── Touch Swipe: Edge-swipe to open / swipe-left to close ── */
    let touchStartX = 0;
    let touchStartY = 0;
    let isSwiping = false;

    document.addEventListener('touchstart', (e) => {
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
        isSwiping = false;
    }, { passive: true });

    document.addEventListener('touchmove', (e) => {
        if (!isMobile()) return;
        const dx = e.touches[0].clientX - touchStartX;
        const dy = Math.abs(e.touches[0].clientY - touchStartY);
        // Only a clear horizontal swipe
        if (Math.abs(dx) > dy && Math.abs(dx) > 10) isSwiping = true;
    }, { passive: true });

    document.addEventListener('touchend', (e) => {
        if (!isMobile() || !isSwiping) return;
        const dx = e.changedTouches[0].clientX - touchStartX;

        // Swipe right from the left edge (within 40px) → open
        if (dx > 60 && touchStartX < 40 && !sidebar.classList.contains('active')) {
            openMobile();
        }
        // Swipe left while sidebar is open → close
        if (dx < -60 && sidebar.classList.contains('active')) {
            closeMobile();
        }
    }, { passive: true });

    /* ── Responsive: close drawer if resized to desktop ── */
    window.addEventListener('resize', () => {
        if (!isMobile() && sidebar.classList.contains('active')) {
            closeMobile();
        }
    });

    /* ── Stagger nav items on first paint ── */
    const navLinks = sidebar.querySelectorAll('.nd-sidebar__nav-item');
    navLinks.forEach((item, i) => {
        item.style.opacity = '0';
        item.style.transform = 'translateX(-12px)';
        setTimeout(() => {
            item.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateX(0)';
        }, 60 + i * 30);
    });

    /* ── Logout Confirmation Modal ── */
    window.showLogoutConfirmation = function() {
        const modal = document.getElementById('logoutModal');
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    };

    window.hideLogoutConfirmation = function() {
        const modal = document.getElementById('logoutModal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    };

    // Close modal on backdrop click
    const logoutModal = document.getElementById('logoutModal');
    if (logoutModal) {
        logoutModal.addEventListener('click', function(e) {
            if (e.target === logoutModal) {
                hideLogoutConfirmation();
            }
        });
    }

    // Close modal on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideLogoutConfirmation();
        }
    });

})();
</script>