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

<!-- Logout Confirmation Modal (Self-contained, viewport-centered) -->
<div class="nd-logout-modal" id="logoutModal" role="dialog" aria-modal="true" aria-labelledby="ndLogoutTitle">
    <div class="nd-logout-modal__content">
        <button type="button" class="nd-logout-modal__close" onclick="hideLogoutConfirmation()" aria-label="Close dialog">
            <i class="fas fa-times"></i>
        </button>
        <div class="nd-logout-modal__icon">
            <div class="nd-logout-modal__icon-inner">
                <i class="fas fa-arrow-right-from-bracket"></i>
            </div>
        </div>
        <h3 class="nd-logout-modal__title" id="ndLogoutTitle">Confirm Logout</h3>
        <p class="nd-logout-modal__message">Are you sure you want to end your active session? You will need to sign back in to access your dashboard.</p>
        <div class="nd-logout-modal__actions">
            <button type="button" id="cancelLogout" class="nd-logout-modal__btn nd-logout-modal__btn--cancel" onclick="hideLogoutConfirmation()">Cancel</button>
            <a href="login-logout/logout.php" id="confirmLogout" class="nd-logout-modal__btn nd-logout-modal__btn--confirm">
                <span>Yes, Logout</span>
                <i class="fas fa-arrow-right" style="font-size: 11px; margin-left: 6px;"></i>
            </a>
        </div>
    </div>
</div>

<style>
    /* Logout Confirmation Modal Styles */
    .nd-logout-modal,
    .logout-modal {
        position: fixed !important;
        inset: 0 !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        height: 100dvh !important;
        min-height: 100vh !important;
        min-height: 100dvh !important;
        background: rgba(15, 23, 42, 0.65) !important;
        backdrop-filter: blur(8px) !important;
        -webkit-backdrop-filter: blur(8px) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 20px !important;
        box-sizing: border-box !important;
        z-index: 9999999 !important;
        opacity: 0 !important;
        visibility: hidden !important;
        pointer-events: none !important;
        transition: opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.25s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }

    .nd-logout-modal.active,
    .logout-modal.active {
        opacity: 1 !important;
        visibility: visible !important;
        pointer-events: auto !important;
    }

    .nd-logout-modal__content,
    .logout-modal-content {
        background: #ffffff !important;
        border: 1px solid rgba(226, 232, 240, 0.9) !important;
        border-radius: 24px !important;
        padding: 36px 28px 28px !important;
        max-width: 420px !important;
        width: 100% !important;
        text-align: center !important;
        position: relative !important;
        box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25), 0 0 0 1px rgba(0, 0, 0, 0.02) !important;
        transform: scale(0.92) translateY(12px) !important;
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
        box-sizing: border-box !important;
        margin: auto !important;
    }

    .nd-logout-modal.active .nd-logout-modal__content,
    .logout-modal.active .logout-modal-content {
        transform: scale(1) translateY(0) !important;
    }

    .nd-logout-modal__close {
        position: absolute;
        top: 16px;
        right: 16px;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: none;
        background: #f1f5f9;
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 13px;
        transition: all 0.2s ease;
        padding: 0;
        z-index: 10;
    }

    .nd-logout-modal__close:hover {
        background: #e2e8f0;
        color: #0f172a;
        transform: scale(1.05);
    }

    .nd-logout-modal__icon,
    .logout-icon {
        width: 68px !important;
        height: 68px !important;
        border-radius: 50% !important;
        background: #fef2f2 !important;
        border: 6px solid #fee2e2 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        margin: 0 auto 20px !important;
        box-shadow: 0 8px 16px -4px rgba(239, 68, 68, 0.2) !important;
    }

    .nd-logout-modal__icon-inner {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .nd-logout-modal__icon i,
    .logout-icon i {
        font-size: 24px !important;
        color: #ef4444 !important;
        margin-left: 2px;
    }

    .nd-logout-modal__title,
    .logout-modal-content h3 {
        font-family: 'Plus Jakarta Sans', 'Inter', system-ui, sans-serif !important;
        font-size: 1.35rem !important;
        font-weight: 700 !important;
        color: #0f172a !important;
        margin: 0 0 10px !important;
        letter-spacing: -0.02em !important;
    }

    .nd-logout-modal__message,
    .logout-modal-content p {
        font-family: 'Inter', system-ui, sans-serif !important;
        font-size: 0.925rem !important;
        color: #64748b !important;
        margin: 0 0 26px !important;
        line-height: 1.55 !important;
        padding: 0 6px !important;
    }

    .nd-logout-modal__actions,
    .logout-modal-actions {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 12px !important;
        justify-content: center !important;
        align-items: center !important;
    }

    .nd-logout-modal__btn,
    .logout-modal-actions .btn {
        padding: 13px 18px !important;
        border-radius: 12px !important;
        font-family: 'Inter', system-ui, sans-serif !important;
        font-weight: 600 !important;
        font-size: 0.925rem !important;
        cursor: pointer !important;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
        text-decoration: none !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        box-sizing: border-box !important;
        width: 100% !important;
        min-width: 0 !important;
    }

    .nd-logout-modal__btn--cancel,
    .logout-modal-actions .btn-secondary {
        background: #f8fafc !important;
        color: #475569 !important;
        border: 1px solid #e2e8f0 !important;
        box-shadow: none !important;
    }

    .nd-logout-modal__btn--cancel:hover,
    .logout-modal-actions .btn-secondary:hover {
        background: #f1f5f9 !important;
        color: #1e293b !important;
        border-color: #cbd5e1 !important;
        transform: translateY(-1px) !important;
    }

    .nd-logout-modal__btn--confirm,
    .logout-modal-actions .btn-primary {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
        color: #ffffff !important;
        border: 1px solid transparent !important;
        box-shadow: 0 4px 14px rgba(239, 68, 68, 0.35) !important;
    }

    .nd-logout-modal__btn--confirm:hover,
    .logout-modal-actions .btn-primary:hover {
        background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%) !important;
        box-shadow: 0 6px 20px rgba(239, 68, 68, 0.45) !important;
        transform: translateY(-1px) !important;
        color: #ffffff !important;
    }

    .nd-logout-modal__btn--confirm:active,
    .logout-modal-actions .btn-primary:active {
        transform: translateY(0) !important;
    }

    @media (max-width: 480px) {
        .nd-logout-modal__content,
        .logout-modal-content {
            padding: 28px 20px 24px !important;
            border-radius: 20px !important;
        }
        .nd-logout-modal__icon,
        .logout-icon {
            width: 60px !important;
            height: 60px !important;
            border-width: 4px !important;
            margin-bottom: 16px !important;
        }
        .nd-logout-modal__icon i,
        .logout-icon i {
            font-size: 20px !important;
        }
        .nd-logout-modal__title,
        .logout-modal-content h3 {
            font-size: 1.2rem !important;
        }
        .nd-logout-modal__actions,
        .logout-modal-actions {
            grid-template-columns: 1fr !important;
            gap: 10px !important;
        }
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
    function ensureModalInBody() {
        const modal = document.getElementById('logoutModal');
        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        return modal;
    }

    window.showLogoutConfirmation = function() {
        const modal = ensureModalInBody();
        if (modal) {
            modal.style.display = 'flex';
            void modal.offsetWidth; // force reflow
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    };

    window.hideLogoutConfirmation = function() {
        const modal = document.getElementById('logoutModal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
            setTimeout(() => {
                if (!modal.classList.contains('active')) {
                    modal.style.display = '';
                }
            }, 250);
        }
    };

    // Auto-reparent to body as early as possible
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ensureModalInBody);
    } else {
        ensureModalInBody();
    }

    // Close modal on backdrop click
    document.addEventListener('click', function(e) {
        const modal = document.getElementById('logoutModal');
        if (modal && e.target === modal) {
            hideLogoutConfirmation();
        }
    });

    // Close modal on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('logoutModal');
            if (modal && modal.classList.contains('active')) {
                hideLogoutConfirmation();
            }
        }
    });

})();
</script>