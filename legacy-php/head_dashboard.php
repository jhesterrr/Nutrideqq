<?php
if (strpos(strtolower(PHP_OS), 'win') !== false) {
    $session_path = 'c:\\xampp\\htdocs\\nutrideq\\api\\sessions';
    if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
    session_save_path($session_path);
} else {
    $session_path = '/tmp/sessions';
    if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
    session_save_path($session_path);
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// FORCE REFRESH - ANTI CACHE
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login-logout/NutriDeqN-Login.php");
    exit();
}

// Get user data from session
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];
$user_role = $_SESSION['user_role'];

// Generate initials for avatar
function getInitials($name)
{
    $names = explode(' ', $name);
    $initials = '';
    foreach ($names as $n) {
        $initials .= strtoupper($n[0]);
    }
    return substr($initials, 0, 2);
}

$user_initials = getInitials($user_name);

// Role-based navigation links
require_once 'navigation.php';
$nav_links_array = getNavigationLinks($user_role, 'dashboard.php');
$nav_links = $nav_links_array;

// Role-based dashboard content
function getDashboardTitle($role)
{
    switch ($role) {
        case 'admin':
            return 'Admin Dashboard';
        case 'staff':
            return 'Staff Dashboard';
        default:
            return 'Dashboard';
    }
}

function getDashboardDescription($role)
{
    switch ($role) {
        case 'admin':
            return 'System administration and user management';
        case 'staff':
            return 'Client management and nutrition monitoring';
        default:
            return 'Welcome to your nutrition dashboard';
    }
}
require_once 'database.php';
$database = new Database();
$pdo = $database->getConnection();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <script src="scripts/theme-toggle.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <title>NutriDeq - <?php echo getDashboardTitle($user_role); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Core Analytics & Animations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>

    <!-- Design System Architecture -->
    <link rel="stylesheet" href="css/premium-revamp.css">
    <link rel="stylesheet" href="css/components.css">

    <!-- External CSS Files -->
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/logout-modal.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <?php if ($user_role === 'admin'): ?>
        <link rel="stylesheet" href="css/admin.css">
    <?php elseif ($user_role === 'staff'): ?>
        <link rel="stylesheet" href="css/staff.css">
    <?php else: ?>
        <link rel="stylesheet" href="css/user.css">
    <?php endif; ?>

    <script>
        const BASE_URL = '';
        
        // Ghost Scrolling Preventer Framework
        document.addEventListener('DOMContentLoaded', () => {
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.attributeName === 'class' || mutation.attributeName === 'style') {
                        const hasActiveModal = document.querySelectorAll('.active, .modal-open, [style*="display: flex"].modal-overlay, [style*="display: flex"].info-modal-overlay').length > 0;
                        document.body.style.overflow = hasActiveModal ? 'hidden' : '';
                    }
                });
            });
            // Monitor common modal overlays dynamically
            const config = { attributes: true, attributeFilter: ['class', 'style'], subtree: true };
            observer.observe(document.body, config);
        });
    </script>
</head>

<body>
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-container">
                <div class="header">
                    <div class="page-title">
                        <h1><?php echo getDashboardTitle($user_role); ?></h1>
                        <p><?php echo getDashboardDescription($user_role); ?></p>
                    </div>

                    <div class="header-actions">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Search..." class="global-search"
                                data-target=".dashboard-grid .stat-card">
                        </div>
                    </div>
                </div>

            <!-- Role-specific Dashboard Content -->
            <?php if ($user_role === 'admin'): ?>
                <?php
                $total_users = 0;
                $active_users = 0;
                $admin_count = 0;
                $staff_count = 0;
                $staff_active_count = 0;
                $users_week_diff = 0;
                $staff_month_diff = 0;

                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as total_users, SUM(role='admin') as admin_count, SUM(role='staff') as staff_count, SUM(status='active') as active_users, SUM(role='staff' AND status='active') as staff_active FROM users");
                    $stmt->execute();
                    $row = $stmt->fetch();
                    if ($row) {
                        $total_users = (int) ($row['total_users'] ?? 0);
                        $active_users = (int) ($row['active_users'] ?? 0);
                        $admin_count = (int) ($row['admin_count'] ?? 0);
                        $staff_count = (int) ($row['staff_count'] ?? 0);
                        $staff_active_count = (int) ($row['staff_active'] ?? 0);
                    }
                } catch (Exception $e) {
                }

                try {
                    $week_curr = $pdo->prepare("SELECT COUNT(*) FROM users WHERE YEARWEEK(created_at,1)=YEARWEEK(CURDATE(),1)");
                    $week_prev = $pdo->prepare("SELECT COUNT(*) FROM users WHERE YEARWEEK(created_at,1)=YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK),1)");
                    $week_curr->execute();
                    $week_prev->execute();
                    $users_week_diff = (int) $week_curr->fetchColumn() - (int) $week_prev->fetchColumn();
                } catch (Exception $e) {
                    $users_week_diff = 0;
                }

                try {
                    $staff_month_curr = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role='staff' AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())");
                    $staff_month_prev = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role='staff' AND YEAR(created_at)=YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(created_at)=MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
                    $staff_month_curr->execute();
                    $staff_month_prev->execute();
                    $staff_month_diff = (int) $staff_month_curr->fetchColumn() - (int) $staff_month_prev->fetchColumn();
                } catch (Exception $e) {
                    $staff_month_diff = 0;
                }

                // Fetch all staff for the dropdown
                try {
                    $staff_stmt = $pdo->prepare("SELECT id, name FROM users WHERE role = 'staff' AND status = 'active' ORDER BY name ASC");
                    $staff_stmt->execute();
                    $staff_members = $staff_stmt->fetchAll();
                } catch (Exception $e) {
                    $staff_members = [];
                }
                ?>
                <!-- ADMIN DASHBOARD CONTENT - FOCUSED ON SYSTEM MANAGEMENT -->
                <div class="dashboard-grid admin-grid">
                    <div class="stat-card" onclick="location.href='admin-user-management.php'">
                        <div class="stat-icon users">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3 id="totalUsers"><?php echo $total_users; ?></h3>
                            <p>Total Users</p>
                            <div class="metric-trend trend-up">
                                <i class="fas fa-arrow-up"></i>
                                <span><?php echo ($users_week_diff >= 0 ? '+' : '') . $users_week_diff; ?> this week</span>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card" onclick="location.href='admin-staff-management.php'">
                        <div class="stat-icon staff">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="stat-info">
                            <h3 id="activeStaff"><?php echo $staff_active_count; ?></h3>
                            <p>Active Staff (<?php echo $staff_count; ?> total)</p>
                            <div class="metric-trend trend-up">
                                <i class="fas fa-arrow-up"></i>
                                <span><?php echo ($staff_month_diff >= 0 ? '+' : '') . $staff_month_diff; ?> this
                                    month</span>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card" onclick="location.href='admin-user-management.php'">
                        <div class="stat-icon system">
                            <i class="fas fa-server"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $active_users; ?></h3>
                            <p>Active Accounts</p>
                            <div class="metric-trend trend-stable">
                                <i class="fas fa-minus"></i>
                                <span>Real-time</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Command Center - Staff Filter Card -->
                <div class="management-section">
                    <div class="section-header">
                        <h2><i class="fas fa-chart-line"></i> Command Center</h2>
                        <div class="header-actions" style="display: flex; align-items: center; gap: 15px;">
                            <select id="staffFilter" class="form-control touch-ready"
                                style="min-width: 200px; padding: 10px 15px; border-radius: 10px; border: 2px solid #e9ecef; font-family: 'Poppins', sans-serif;">
                                <option value="all">All Staff (Global View)</option>
                                <?php foreach ($staff_members as $staff): ?>
                                    <option value="<?php echo $staff['id']; ?>"><?php echo htmlspecialchars($staff['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary touch-ready"
                                onclick="updateDashboard(document.getElementById('staffFilter').value)"
                                style="height: 48px; width: 48px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Charts Grid -->
                <div class="charts-section">
                    <!-- Staff Engagement Delta Card -->
                    <div class="chart-container" id="staffInfluenceContainer">
                        <div class="chart-header">
                            <h2>Staff Performance Influence <i class="fas fa-info-circle" onclick="showFeatureInfo('staff_performance')" title="About Staff Analysis" style="cursor: pointer; color: var(--primary);"></i></h2>
                            <span id="deltaSelectedBadge"
                                style="display: none; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">--</span>
                        </div>
                        <p style="color: var(--gray); font-size: 0.85rem; margin-bottom: 15px;">Client activity change 24h
                            before vs. after staff interaction</p>
                        <div id="staffInfluenceList" style="display: flex; flex-direction: column; gap: 10px;">
                            <!-- Populated by JS -->
                            <div style="color: #999; font-size: 0.85rem;">Loading staff data...</div>
                        </div>
                    </div>

                    <!-- Workload & Alerts Card -->
                    <div class="chart-container" id="workloadAlertsContainer">
                        <div class="chart-header">
                            <h2>Workload & Alerts</h2>
                        </div>
                        <div id="workloadInfo" style="margin-bottom: 20px;">
                            <div
                                style="height: 12px; background: #e9ecef; border-radius: 6px; overflow: hidden; margin-top: 8px;">
                                <div id="workloadProgressBar"
                                    style="width: 0%; height: 100%; background: var(--primary); transition: width 0.5s ease;">
                                </div>
                            </div>
                            <p id="workloadText" style="font-size: 0.85rem; color: var(--gray); margin-top: 8px;">Loading
                                metrics...</p>
                        </div>
                        <div id="alertsList"
                            style="display: flex; flex-direction: column; gap: 10px; max-height: 200px; overflow-y: auto;">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                </div>

                <!-- System Efficiency Card -->
                <div class="chart-container" style="margin-bottom: 30px;">
                    <div class="chart-header">
                        <h2>Platform Activity Overview <i class="fas fa-info-circle" onclick="showFeatureInfo('system_efficiency')" title="About System Efficiency" style="cursor: pointer; color: var(--primary);"></i></h2>
                    </div>
                    <div id="efficiencyMetrics" style="display: flex; align-items: center; gap: 30px; padding: 15px;">
                        <div class="efficiency-gauge" style="position: relative; width: 100px; height: 100px;">
                            <canvas id="efficiencyDoughnutChart"></canvas>
                            <div id="efficiencyPctLabel" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-weight: 700; font-size: 1.2rem;">--%</div>
                        </div>
                        <div class="efficiency-details" style="flex: 1;">
                            <p id="efficiencyDescription" style="margin: 0; color: var(--gray); font-size: 0.9rem; line-height: 1.4;">Crunching platform stats...</p>
                            <div id="efficiencyTrendLine" style="margin-top: 5px; font-weight: 600; font-size: 0.8rem; color: var(--primary);">Calculating trends...</div>
                        </div>
                    </div>
                    <canvas id="activityRatiosChart" height="120" style="display: none;"></canvas>
                </div>

                <!-- System Management Quick Actions -->
                <div class="management-section">
                    <div class="section-header">
                        <h2>Admin Actions</h2>

                    </div>

                    <div class="quick-actions">
                        <div class="action-card" onclick="location.href='admin-staff-management.php'">
                            <div class="action-icon">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <h4>Manage Staff</h4>
                            <p>Add, edit, or remove staff accounts</p>
                        </div>

                        <div class="action-card" onclick="location.href='admin-user-management.php'">
                            <div class="action-icon">
                                <i class="fas fa-users-cog"></i>
                            </div>
                            <h4>User Management</h4>
                            <p>Manage all user accounts and permissions</p>
                        </div>

                        <div class="action-card" onclick="location.href='admin-client-management.php'">
                            <div class="action-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h4>Client Management</h4>
                            <p>Add, edit, delete, and restore client records</p>
                        </div>

                        <div class="action-card" onclick="location.href='admin-internal-messages.php'">
                            <div class="action-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h4>Internal Messages</h4>
                            <p>Handle's problems regarding system performance and reports</p>
                        </div>
                    </div>
                </div>

                <!-- Recent System Activity -->
                <div class="management-section">
                    <div class="section-header">
                        <h2>Recent System Activity <i class="fas fa-info-circle" onclick="showFeatureInfo('recent_activity')" title="About System Feed" style="cursor: pointer; color: var(--primary); font-size: 1rem; margin-left: 5px;"></i></h2>
                        <button class="btn btn-outline" id="refreshSystemActivity">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>

                    <div class="system-activity-list" id="recentActivityList">
                        <!-- REAL-TIME DATA INJECTED HERE -->
                        <div style="text-align: center; padding: 40px; color: #999;">
                            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <p>Connecting to live system feed...</p>
                        </div>
                    </div>
                    <script src="scripts/admin-realtime.js" defer></script>
                    <div class="system-activity-list-obsolete" style="display:none;">
                        <div class="activity-item">
                            <div class="activity-icon success">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="activity-details">
                                <div class="activity-title">New Staff Account Created</div>
                                <div class="activity-description">Dr. Sarah Johnson registered as staff member</div>
                                <div class="activity-time">10 minutes ago</div>
                            </div>
                        </div>

                        <div class="activity-item">
                            <div class="activity-icon info">
                                <i class="fas fa-cog"></i>
                            </div>
                            <div class="activity-details">
                                <div class="activity-title">System Settings Updated</div>
                                <div class="activity-description">Email notification preferences changed</div>
                                <div class="activity-time">2 hours ago</div>
                            </div>
                        </div>

                        <div class="activity-item">
                            <div class="activity-icon warning">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="activity-details">
                                <div class="activity-title">High System Load</div>
                                <div class="activity-description">Server CPU usage peaked at 85%</div>
                                <div class="activity-time">4 hours ago</div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($user_role === 'staff'): ?>
                <?php
                // STAFF DASHBOARD - DATABASE CONNECTION & QUERIES
                $staff_id = $_SESSION['user_id'];

                // Active Clients
                try {
                    $active_clients = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM clients 
            WHERE staff_id = ? AND status = 'active'
        ");
                    $active_clients->execute([$staff_id]);
                    $clients_count = $active_clients->fetchColumn();
                } catch (Exception $e) {
                    $clients_count = 0;
                }

                // Weekly Progress Entries
                try {
                    $weekly_progress = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM progress_entries 
            WHERE staff_id = ? AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)
        ");
                    $weekly_progress->execute([$staff_id]);
                    $progress_count = $weekly_progress->fetchColumn();
                } catch (Exception $e) {
                    $progress_count = 0;
                }

                // Last week progress for comparison
                try {
                    $last_week_progress = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM progress_entries 
            WHERE staff_id = ? AND YEARWEEK(created_at, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)
        ");
                    $last_week_progress->execute([$staff_id]);
                    $last_week_count = $last_week_progress->fetchColumn();
                    $progress_diff = $progress_count - $last_week_count;
                } catch (Exception $e) {
                    $progress_diff = 0;
                }

                // Unread Messages (FIXED: Using wellness_messages)
                try {
                    $unread_messages = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM wellness_messages wm
            JOIN conversations c ON wm.conversation_id = c.id
            WHERE c.dietitian_id = ? AND wm.sender_type = 'client' AND wm.read_at IS NULL
        ");
                    $unread_messages->execute([$staff_id]);
                    $messages_count = $unread_messages->fetchColumn();
                } catch (Exception $e) {
                    $messages_count = 0;
                }

                // Recent Messages (last 5 unread or recent from wellness_messages)
                try {
                    $recent_messages = $pdo->prepare("
            SELECT 
                wm.id,
                c.name as client_name,
                c.id as client_id,
                wm.content as message,
                wm.created_at,
                wm.read_at,
                wm.message_type,
                TIMESTAMPDIFF(MINUTE, wm.created_at, NOW()) as minutes_ago
            FROM wellness_messages wm
            JOIN conversations conv ON wm.conversation_id = conv.id
            JOIN clients c ON conv.client_id = c.id
            WHERE conv.dietitian_id = ? AND wm.sender_type = 'client'
            ORDER BY wm.created_at DESC
            LIMIT 5
        ");
                    $recent_messages->execute([$staff_id]);
                    $messages = $recent_messages->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $messages = [];
                }
                ?>

                <!-- STAFF DASHBOARD CONTENT -->
                <div class="dashboard-grid staff-grid">
                    <div class="stat-card" onclick="location.href='user-management-staff.php'">
                        <div class="stat-icon trackers">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3 id="activeClients"><?php echo $clients_count; ?></h3>
                            <p>Active Clients</p>
                            <div class="metric-trend trend-neutral">
                                <i class="fas fa-user-check"></i>
                                <span>Assigned to you</span>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card" onclick="location.href='anthropometric-information.php'">
                        <div class="stat-icon calories">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-info">
                            <h3 id="weeklyProgress"><?php echo $progress_count; ?></h3>
                            <p>Weekly Progress Entries</p>
                            <div class="metric-trend <?php echo $progress_diff >= 0 ? 'trend-up' : 'trend-down'; ?>">
                                <i class="fas fa-arrow-<?php echo $progress_diff >= 0 ? 'up' : 'down'; ?>"></i>
                                <span><?php echo $progress_diff >= 0 ? '+' : '';
                                echo $progress_diff; ?> from last week</span>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card" onclick="location.href='staff-messages.php'">
                        <div class="stat-icon messages">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="stat-info">
                            <h3 id="unreadMessages"><?php echo $messages_count; ?></h3>
                            <p>Unread Messages</p>
                            <div class="metric-trend <?php echo $messages_count > 0 ? 'trend-alert' : 'trend-neutral'; ?>">
                                <i
                                    class="fas fa-<?php echo $messages_count > 0 ? 'exclamation-circle' : 'check-circle'; ?>"></i>
                                <span><?php echo $messages_count > 0 ? 'Needs attention' : 'All caught up'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Messages Column -->
                <div class="column">
                    <div class="management-section">
                        <div class="section-header">
                            <h2>Recent Client Messages</h2>
                        </div>

                        <!-- Modal Structure -->
                        <div id="modalOverlay" class="modal-overlay">
                            <div class="modal-container">
                                <div class="modal-header">
                                    <h3 id="modalTitle">All Appointments</h3>
                                    <button class="modal-close" onclick="closeModal()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div class="modal-content">
                                    <div id="modalContent">
                                        <!-- Content will be loaded here via AJAX -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="messages-list">
                            <?php if (count($messages) > 0): ?>
                                <?php foreach ($messages as $message): ?>
                                    <div class="message-item <?php echo $message['read_at'] === null ? 'unread' : ''; ?>">
                                        <div class="message-avatar">
                                            <?php echo strtoupper(substr($message['client_name'], 0, 2)); ?>
                                        </div>
                                        <div class="message-details">
                                            <div class="message-header">
                                                <div class="message-client"><?php echo htmlspecialchars($message['client_name']); ?>
                                                </div>
                                                <div class="message-time">
                                                    <?php
                                                    echo date('M j, g:i A', strtotime($message['created_at']));
                                                    ?>
                                                </div>
                                            </div>
                                            <div class="message-preview">
                                                <?php
                                                $preview = htmlspecialchars($message['message']);
                                                if (strlen($preview) > 80) {
                                                    $preview = substr($preview, 0, 80) . '...';
                                                }
                                                echo $preview;
                                                ?>
                                            </div>
                                            <?php if ($message['read_at'] === null): ?>
                                                <div class="message-status unread-badge">Unread</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="message-actions">
                                            <button class="action-btn view-btn" title="View Message"
                                                onclick="location.href='staff-messages.php?client_id=<?php echo $message['client_id']; ?>'">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-data-message">
                                    <i class="fas fa-comments"></i>
                                    <h3>No Messages</h3>
                                    <p>You have no messages from clients at this time.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php else: ?>
                <?php
                // USER DASHBOARD - DATABASE CONNECTION & QUERIES
                $user_id = $_SESSION['user_id'];

                // Get client data
                try {
                    $client_data = $pdo->prepare("
                SELECT c.*, s.name as staff_name 
                FROM clients c 
                LEFT JOIN staff s ON c.staff_id = s.id 
                WHERE c.user_id = ?
            ");
                    $client_data->execute([$user_id]);
                    $client = $client_data->fetch(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $client = null;
                }

                // Get recommended meal plans
                try {
                    $meal_plans = $pdo->prepare("
                SELECT mp.*, s.name as staff_name 
                FROM meal_plans mp 
                LEFT JOIN staff s ON mp.staff_id = s.id 
                WHERE mp.client_id = ? AND mp.status = 'active'
                ORDER BY mp.created_at DESC 
                LIMIT 3
            ");
                    $meal_plans->execute([$client ? $client['id'] : 0]);
                    $recommended_plans = $meal_plans->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $recommended_plans = [];
                }

                // Get upcoming appointments
                try {
                    $upcoming_appointments = $pdo->prepare("
                SELECT a.*, s.name as staff_name 
                FROM appointments a 
                LEFT JOIN staff s ON a.staff_id = s.id 
                WHERE a.client_id = ? AND a.appointment_date >= CURDATE() AND a.status = 'scheduled'
                ORDER BY a.appointment_date ASC 
                LIMIT 3
            ");
                    $upcoming_appointments->execute([$client ? $client['id'] : 0]);
                    $appointments = $upcoming_appointments->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $appointments = [];
                }

                // Get unread messages (FIXED: Using wellness_messages)
                try {
                    $unread_messages = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM wellness_messages wm
                JOIN conversations c ON wm.conversation_id = c.id
                WHERE c.client_id = (SELECT id FROM clients WHERE user_id = ? LIMIT 1) 
                AND wm.sender_type != 'client' 
                AND wm.read_at IS NULL
            ");
                    $unread_messages->execute([$user_id]);
                    $unread_count = $unread_messages->fetchColumn();
                } catch (Exception $e) {
                    $unread_count = 0;
                }

                // Get recent messages
                try {
                    $recent_messages = $pdo->prepare("
                SELECT m.id, m.content as message, m.sender_type, m.created_at, m.read_at, s.name as staff_name
                FROM wellness_messages m
                JOIN conversations c ON m.conversation_id = c.id
                LEFT JOIN staff s ON c.dietitian_id = s.id
                WHERE c.client_id = (SELECT id FROM clients WHERE user_id = ? LIMIT 1)
                ORDER BY m.created_at DESC
                LIMIT 3
            ");
                    $recent_messages->execute([$user_id]);
                    $messages = $recent_messages->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $messages = [];
                }
                ?>

                <!-- ENHANCED USER DASHBOARD CONTENT -->
                <div class="dashboard-grid user-grid">
                    <div class="stat-card" onclick="location.href='Nutrition-Calculator.php'">
                        <div class="stat-icon users">
                            <i class="fas fa-calculator"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Nutrition Calculator</h3>
                            <p>Calculate Your Needs</p>
                            <div class="metric-trend trend-up">
                                <i class="fas fa-bolt"></i>
                                <span>Personalized Results</span>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card" onclick="location.href='food-exchange.php'">
                        <div class="stat-icon trackers">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Food Exchange</h3>
                            <p>Smart Food Swaps</p>
                            <div class="metric-trend trend-up">
                                <i class="fas fa-lightbulb"></i>
                                <span>Healthy Alternatives</span>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card" onclick="location.href='dietary-information.php'">
                        <div class="stat-icon calories" style="background: rgba(46, 139, 87, 0.1); color: var(--primary);">
                            <i class="fas fa-file-medical-alt"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Dietary Info</h3>
                            <p>Nutrition Facts</p>
                            <div class="metric-trend trend-up">
                                <i class="fas fa-clipboard-list"></i>
                                <span>Tracked Metrics</span>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card" onclick="location.href='user-messages.php'">
                        <div class="stat-icon calories">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="stat-info">
                            <h3 id="unreadMessages"><?php echo $unread_count; ?></h3>
                            <p>Unread Messages</p>
                            <div class="metric-trend <?php echo $unread_count > 0 ? 'trend-alert' : 'trend-neutral'; ?>">
                                <i class="fas fa-<?php echo $unread_count > 0 ? 'exclamation-circle' : 'check-circle'; ?>"></i>
                                <span><?php echo $unread_count > 0 ? 'New messages' : 'All caught up'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Performance Overview -->
                <div class="performance-overview">
                    <div class="macro-snap-card">
                        <div class="section-header" style="margin-bottom: 20px;">
                            <h2><i class="fas fa-bullseye"></i> Nutritional Snap</h2>
                        </div>
                        <div class="macro-rings-container">
                            <!-- Rings Populated by JS -->
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <script src="scripts/dashboard.js"></script>
            <?php if ($user_role === 'admin'): ?>
                <script src="scripts/admin.js"></script>
            <?php elseif ($user_role === 'staff'): ?>
                <script src="scripts/staff.js"></script>
            <?php endif; ?>

            <!-- Logout Modal Functionality -->
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const logoutBtn = document.getElementById('logoutBtn');
                    const logoutModal = document.getElementById('logoutModal');
                    const cancelLogout = document.getElementById('cancelLogout');
                    const confirmLogout = document.getElementById('confirmLogout');

                    if (logoutBtn && logoutModal) {
                        logoutBtn.addEventListener('click', function (e) {
                            e.preventDefault();
                            logoutModal.classList.add('active');
                        });
                    }

                    if (cancelLogout && logoutModal) {
                        cancelLogout.addEventListener('click', function () {
                            logoutModal.classList.remove('active');
                        });
                    }

                    if (confirmLogout) {
                        confirmLogout.addEventListener('click', function () {
                            window.location.href = 'login-logout/logout.php';
                        });
                    }
                });
            </script>
        </div> <!-- Close .page-container -->
    </main>
</div> <!-- Close .main-layout -->
</body>
</html>
