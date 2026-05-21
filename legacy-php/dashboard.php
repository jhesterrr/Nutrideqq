<?php
// Fix for XAMPP Permission Denied session error
$session_path = __DIR__ . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'sessions';
if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
session_save_path($session_path);
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
// Role-based navigation links
require_once 'navigation.php';
$nav_links_array = getNavigationLinks($user_role, 'dashboard.php');
// The loop further down (line 135) iterates via foreach ($nav_links as $link).
// getNavigationLinks returns an associative array where keys are filenames.
// The loop expects an array of arrays.
// The structure returned by getNavigationLinks is: ['filename' => ['href' => '...', ...], ...]
// PHP foreach ($associative_array as $item) iterates over VALUES.
// So $link will be the inner array ['href' => '...', ...].
// This matches the structure expected by the template.
$nav_links = $nav_links_array;

// Role-based dashboard content - UPDATED
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>NutriDeq - <?php echo getDashboardTitle($user_role); ?></title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
        <link rel="stylesheet" href="css/user-premium.css">
    <?php endif; ?>
    <link rel="stylesheet" href="css/responsive.css">
    <link rel="stylesheet" href="css/mobile-style.css">
    <link rel="manifest" href="manifest.json">
    <link rel="stylesheet" href="css/hydration-premium.css">
    <link rel="stylesheet" href="css/dashboard-premium.css">
    <link rel="stylesheet" href="css/info-modal.css">
    <script src="scripts/info-component.js" defer></script>
    <script src="scripts/premium-effects.js" defer></script>
    <script src="scripts/dashboard-store.js" defer></script>

    <style>
        .recovery-key-banner {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 20px;
            border-radius: 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.2);
            animation: slideInDown 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .recovery-key-banner i { font-size: 40px; opacity: 0.9; }
        .recovery-key-val {
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 12px;
            font-family: 'Courier New', monospace;
            font-weight: 800;
            font-size: 1.2rem;
            letter-spacing: 2px;
            border: 1px dashed rgba(255,255,255,0.5);
        }
        @keyframes slideInDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#2e8b57">
    <style>
        /* BMI Chart Container Styles */
        .bmi-visual-container {
            display: flex;
            align-items: stretch;
            gap: 30px;
            padding: 30px;
            background: white;
            border-radius: 20px;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        }
        
        .bmi-status-info {
            flex: 1;
            padding-right: 30px;
            border-right: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .bmi-chart-wrapper {
            flex: 2;
            position: relative;
            min-height: 250px;
        }

        .bmi-big-value {
            font-size: 3.5rem;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
            line-height: 1;
            margin-bottom: 10px;
            color: #1e293b;
        }
        
        .bmi-status-badge {
            font-size: 0.8rem;
            font-weight: 700;
            padding: 6px 16px;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: inline-block;
            margin-bottom: 15px;
        }
        
        .status-underweight { background: #eff6ff; color: #3b82f6; border: 1px solid #bfdbfe; }
        .status-normal { background: #ecfdf5; color: #10b981; border: 1px solid #a7f3d0; }
        .status-overweight { background: #fffbeb; color: #f59e0b; border: 1px solid #fde68a; }
        .status-obese { background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; }
        .status-unknown { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }

        .clinical-insight-card {
            background: #f8fafc;
            padding: 15px;
            border-radius: 12px;
            border-left: 4px solid var(--primary);
            margin-bottom: 15px;
        }

        @media (max-width: 992px) {
            .bmi-visual-container {
                flex-direction: column !important;
            }
            .bmi-status-info {
                padding-right: 0 !important;
                border-right: none !important;
                border-bottom: 1px solid #e2e8f0;
                padding-bottom: 30px;
                margin-bottom: 30px;
            }
            .wellness-pulse-grid {
                grid-template-columns: 1fr !important;
            }
        }
        
        @media (max-width: 576px) {
            .bmi-visual-container {
                padding: 20px !important;
                gap: 20px !important;
            }
            .bmi-status-info {
                padding-bottom: 20px;
                margin-bottom: 20px;
            }
            .bmi-big-value {
                font-size: 2.5rem !important;
            }
        }
        
        /* Dashboard-specific refined mobile metrics */
        @media screen and (max-width: 768px) {
            .main-content {
                padding-bottom: 90px !important; /* Prevents bottom app bar from covering content */
            }
            .dashboard-grid, .bento-grid {
                grid-template-columns: 1fr !important;
                gap: 12px !important;
            }
            .dash-row {
                grid-template-columns: 1fr !important;
                gap: 15px !important;
            }
            .charts-section {
                grid-template-columns: 1fr !important;
                gap: 15px !important;
            }
            .command-tiles {
                grid-template-columns: 1fr !important;
                gap: 12px !important;
            }
            .stat-card {
                padding: 15px !important;
                margin-bottom: 0 !important;
            }
            .dash-hero-content h1 {
                font-size: 1.5rem !important;
            }
            .dash-panel {
                padding: 15px !important;
                width: 100% !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }
            .table-responsive {
                width: 100%;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch;
                display: block;
            }
            .table-responsive table {
                white-space: nowrap;
            }
        }
    </style>
    <script>
        const BASE_URL = '';
    </script>
</head>

<body>
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content dash-premium">
            <!-- Modern Mesh Background Elements -->
            <div class="mesh-gradient-container dashboard-mesh">
                <div class="mesh-blob blob-1"></div>
                <div class="mesh-blob blob-2"></div>
                <div class="mesh-blob blob-3"></div>
                <div class="mesh-blob blob-4"></div>
            </div>

            <!-- Nutri-Glass Noise Texture -->
            <div class="glass-noise"></div>

            <!-- Spotlight & Custom Cursor -->
            <div class="spotlight" id="spotlight"></div>
            <div id="organicCursor"></div>
            <div class="glow-aura" id="cursorAura"></div>
            <div class="page-container">
            <!-- New Recovery Key Banner -->
            <?php if (isset($_SESSION['new_recovery_key'])): ?>
                <div class="recovery-key-banner">
                    <i class="fas fa-shield-check"></i>
                    <div style="flex: 1;">
                        <h3 style="margin: 0 0 5px 0; font-weight: 700;">Save Your Recovery Key!</h3>
                        <p style="margin: 0; font-size: 0.9rem; opacity: 0.9;">You will need this key if you ever forget your password. Write it down or save it somewhere safe.</p>
                    </div>
                    <div class="recovery-key-val"><?php echo $_SESSION['new_recovery_key']; ?></div>
                </div>
                <?php unset($_SESSION['new_recovery_key']); ?>
            <?php endif; ?>

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
                    $stmt = $pdo->prepare("
                        SELECT 
                            COUNT(*) as total_users, 
                            SUM(role='admin') as admin_count, 
                            SUM(role='staff') as staff_count, 
                            SUM(status='active') as active_users, 
                            SUM((role='staff' OR role='admin') AND online_status=1 AND last_active > DATE_SUB(NOW(), INTERVAL 5 MINUTE)) as staff_active 
                        FROM users
                    ");
                    $stmt->execute();
                    $row = $stmt->fetch();
                    if ($row) {
                        $total_users = (int) ($row['total_users'] ?? 0);
                        $active_users = (int) ($row['active_users'] ?? 0);
                        $admin_count = (int) ($row['admin_count'] ?? 0);
                        $staff_count = (int) ($row['staff_count'] ?? 0);
                        $staff_active_count = (int) ($row['staff_active'] ?? 0);
                    }
                } catch (Exception $e) {}

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
                    $staff_month_prev = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role='staff' AND YEAR(created_at)=YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(created_at)=MONTH(CURDATE())"); // Truncated original logic for safety
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

                <!-- ── Premium Hero Ribbon ── -->
                <div class="dash-hero-ribbon stagger d-1">
                    <div class="dash-hero-content">
                        <h1>System Oversight Terminal</h1>
                        <p>Real-time platform administration & clinical oversight hub.</p>
                    </div>
                    <div class="dash-hero-badge nutri-glass">
                        <i class="fas fa-shield-check"></i>
                        <span>System Secured & Active</span>
                    </div>
                </div>

                <!-- ── Bento Stat Grid ── -->
                <div class="bento-grid stagger d-2">
                    <div class="bento-stat stat-primary" onclick="location.href='admin-user-management.php'">
                        <div class="bento-stat-icon"><i class="fas fa-users"></i></div>
                        <div>
                            <div class="bento-stat-val"><?php echo $total_users; ?></div>
                            <div class="bento-stat-label">Total Platform Users</div>
                        </div>
                        <div class="metric-trend trend-up" style="margin-top: 10px;">
                            <i class="fas fa-arrow-up"></i>
                            <span><?php echo ($users_week_diff >= 0 ? '+' : '') . $users_week_diff; ?> this week</span>
                        </div>
                        <canvas class="bento-sparkline" id="spark-0" width="110" height="55" aria-hidden="true"></canvas>
                    </div>

                    <div class="bento-stat stat-secondary" onclick="location.href='admin-staff-management.php'">
                        <div class="bento-stat-icon"><i class="fas fa-user-tie"></i></div>
                        <div>
                            <div class="bento-stat-val"><?php echo $staff_active_count; ?></div>
                            <div class="bento-stat-label">Active Staff Members</div>
                        </div>
                        <div class="metric-trend trend-up" style="margin-top: 10px;">
                            <i class="fas fa-arrow-up"></i>
                            <span><?php echo ($staff_month_diff >= 0 ? '+' : '') . $staff_month_diff; ?> this month</span>
                        </div>
                        <canvas class="bento-sparkline" id="spark-1" width="110" height="55" aria-hidden="true"></canvas>
                    </div>

                    <div class="bento-stat stat-accent" onclick="location.href='admin-user-management.php'">
                        <div class="bento-stat-icon"><i class="fas fa-server"></i></div>
                        <div>
                            <div class="bento-stat-val"><?php echo $active_users; ?></div>
                            <div class="bento-stat-label">Verified Accounts</div>
                        </div>
                        <div class="metric-trend trend-stable" style="margin-top: 10px;">
                            <i class="fas fa-pulse"></i>
                            <span>Real-time Monitoring</span>
                        </div>
                        <canvas class="bento-sparkline" id="spark-2" width="110" height="55" aria-hidden="true"></canvas>
                    </div>

                    <div class="bento-stat stat-danger" onclick="location.href='admin-internal-messages.php'">
                        <div class="bento-stat-icon"><i class="fas fa-exclamation-circle"></i></div>
                        <div>
                            <div class="bento-stat-val"><?php 
                                try {
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM internal_messages WHERE status = 'pending'");
                                    $stmt->execute();
                                    echo $stmt->fetchColumn() ?: '0';
                                } catch (Exception $e) { echo '0'; }
                            ?></div>
                            <div class="bento-stat-label">Pending Reports</div>
                        </div>
                        <div class="metric-trend trend-alert" style="margin-top: 10px;">
                            <i class="fas fa-bolt"></i>
                            <span>Priority Tickets</span>
                        </div>
                        <canvas class="bento-sparkline" id="spark-3" width="110" height="55" aria-hidden="true"></canvas>
                    </div>
                </div>

                <!-- Command Center & Activity Row -->
                <div class="dash-row stagger d-3" style="grid-template-columns: 2fr 1fr;">
                    <div class="dash-panel">
                        <div class="dash-panel-header">
                            <h2 class="dash-panel-title"><i class="fas fa-chart-line"></i> Command Center</h2>
                            <div style="display: flex; gap: 12px;">
                                <select id="staffFilter" class="nutri-glass" style="padding: 8px 16px; border-radius: 12px; font-family: 'Outfit'; font-weight: 600; border: 1px solid var(--border-color); outline: none;">
                                    <option value="all">Global View</option>
                                    <?php foreach ($staff_members as $staff): ?>
                                        <option value="<?php echo $staff['id']; ?>"><?php echo htmlspecialchars($staff['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-primary" onclick="updateDashboard(document.getElementById('staffFilter').value)" style="border-radius: 12px; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="charts-section" style="grid-template-columns: 1fr 1.2fr; gap: 25px;">
                            <div class="chart-container nutri-glass" id="staffInfluenceContainer" style="box-shadow: none; border: none; background: rgba(0,0,0,0.01); padding: 20px;">
                                <div class="chart-header" style="margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                                    <h3 style="font-family: 'Outfit'; margin: 0; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;"><i class="fas fa-users-cog"></i> Performance Influence <info-button feature="staff_performance" role="<?php echo $_SESSION['role'] ?? 'regular'; ?>"></info-button></h3>
                                    <span id="deltaSelectedBadge" style="display: none; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700;">--</span>
                                </div>
                                <div id="staffInfluenceList" style="display: flex; flex-direction: column; gap: 12px; max-height: 280px; overflow-y: auto; padding-right: 8px;">
                                    <div style="color: #999; font-size: 0.85rem;"><i class="fas fa-spinner fa-spin"></i> Analyzing staff efficacy...</div>
                                </div>
                            </div>

                            <div class="chart-container nutri-glass" style="box-shadow: none; border: none; background: rgba(0,0,0,0.01); padding: 20px;">
                                <div class="chart-header" style="margin-bottom: 20px;">
                                    <h3 style="font-family: 'Outfit'; margin: 0; font-size: 1.1rem;"><i class="fas fa-tachometer-alt"></i> Efficiency & Load</h3>
                                </div>
                                
                                <div style="display: grid; grid-template-columns: 80px 1fr; gap: 15px; align-items: center;">
                                    <div style="height: 80px; position: relative;">
                                        <canvas id="efficiencyDoughnutChart"></canvas>
                                        <div id="efficiencyPctLabel" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 1rem; font-weight: 800; font-family: 'Outfit'; color: var(--primary);">0%</div>
                                    </div>
                                    <div>
                                        <div id="efficiencyTrendLine" style="font-size: 0.8rem; font-weight: 700; color: var(--primary);">Calculating...</div>
                                        <p id="efficiencyDescription" style="font-size: 0.75rem; color: #64748b; margin: 0;">Platform handling capacity.</p>
                                    </div>
                                </div>

                                <div style="margin-top: 20px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                        <span style="font-size: 0.8rem; font-weight: 700;">Active Workload</span>
                                        <span id="workloadText" style="font-size: 0.75rem; color: #64748b;">0 Active Clients</span>
                                    </div>
                                    <div style="height: 6px; background: rgba(0,0,0,0.05); border-radius: 3px; overflow: hidden;">
                                        <div id="workloadProgressBar" style="height: 100%; width: 0%; background: linear-gradient(90deg, var(--nd-primary), #4facfe);"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="dash-panel nutri-glass">
                        <div class="dash-panel-header">
                            <h2 class="dash-panel-title" style="display: flex; align-items: center; gap: 8px;"><i class="fas fa-bolt"></i> System Feed <info-button feature="recent_activity" role="<?php echo $_SESSION['role'] ?? 'regular'; ?>"></info-button></h2>
                            <button class="btn btn-outline" id="refreshSystemActivity" style="border-radius: 12px; font-size: 0.75rem; padding: 4px 8px;">Refresh</button>
                        </div>
                        <div class="activity-feed" id="recentActivityList" style="max-height: 280px; overflow-y: auto;">
                            <!-- Activity list auto-populated by admin-realtime.js -->
                            <div style="text-align: center; padding: 40px; color: #999;">
                                <i class="fas fa-spinner fa-spin" style="font-size: 1.5rem; margin-bottom: 10px;"></i>
                                <p>Syncing...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin Quick Actions -->
                <div class="management-section stagger d-4" style="margin-top: 40px;">
                    <div class="section-header">
                        <h2 style="font-family: 'Outfit'; font-weight: 800; font-size: 1.5rem; color: #1e293b;">Admin Command Tiles</h2>
                    </div>
                    <div class="command-tiles">
                        <a href="admin-staff-management.php" class="command-tile">
                            <div class="command-tile-icon"><i class="fas fa-user-plus"></i></div>
                            <div class="command-tile-info">
                                <h3>Manage Staff</h3>
                                <p>Onboard and oversee clinical practitioners.</p>
                            </div>
                        </a>
                        <a href="admin-user-management.php" class="command-tile">
                            <div class="command-tile-icon"><i class="fas fa-users-cog"></i></div>
                            <div class="command-tile-info">
                                <h3>User Accounts</h3>
                                <p>Control global authentication & permissions.</p>
                            </div>
                        </a>
                        <a href="admin-client-management.php" class="command-tile">
                            <div class="command-tile-icon"><i class="fas fa-users"></i></div>
                            <div class="command-tile-info">
                                <h3>Client Directory</h3>
                                <p>Central hub for all registered wellness clients.</p>
                            </div>
                        </a>
                        <a href="admin-internal-messages.php" class="command-tile">
                            <div class="command-tile-icon"><i class="fas fa-headset"></i></div>
                            <div class="command-tile-info">
                                <h3>System Reports</h3>
                                <p>Clinical tickets & internal communications.</p>
                            </div>
                        </a>
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

                <!-- Admin Summary Extension -->
                <div class="dash-row stagger d-4" style="grid-template-columns: 1fr 1fr; margin-top: 30px;">
                    <div class="dash-panel nutri-glass" style="min-height: auto; padding: 25px;">
                        <div class="dash-panel-header" style="margin-bottom: 20px;">
                            <h2 class="dash-panel-title"><i class="fas fa-user-plus"></i> Recent Client Registrations</h2>
                            <button class="btn btn-outline" onclick="location.href='admin-user-management.php'" style="font-size: 0.8rem; border-radius: 10px;">View All</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table" style="font-size: 0.85rem; width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="color: #64748b; text-align: left; border-bottom: 1px solid rgba(0,0,0,0.05);">
                                        <th style="padding: 10px;">Name</th>
                                        <th style="padding: 10px;">Status</th>
                                        <th style="padding: 10px;">Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                        try {
                                            $stmt = $pdo->prepare("SELECT name, status, created_at FROM users WHERE role = 'regular' ORDER BY created_at DESC LIMIT 5");
                                            $stmt->execute();
                                            while($row = $stmt->fetch()) {
                                                $badge = $row['status'] === 'verified' ? 'success' : 'warning';
                                                echo "<tr style='border-bottom: 1px solid rgba(0,0,0,0.02);'>
                                                    <td style='padding: 12px 10px; font-weight: 600; color: #1e293b;'>".htmlspecialchars($row['name'])."</td>
                                                    <td style='padding: 12px 10px;'><span style='font-size: 0.7rem; padding: 4px 10px; border-radius: 20px; background: ".($badge === 'success' ? '#e9f7ef' : '#fef9e7')."; color: ".($badge === 'success' ? '#27ae60' : '#f39c12')."; font-weight: 700;'>".ucfirst($row['status'])."</span></td>
                                                    <td style='padding: 12px 10px; color: #94a3b8;'>".date('M j', strtotime($row['created_at']))."</td>
                                                </tr>";
                                            }
                                        } catch(Exception $e) {}
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="dash-panel nutri-glass" style="min-height: auto; padding: 25px;">
                        <div class="dash-panel-header" style="margin-bottom: 20px;">
                            <h2 class="dash-panel-title"><i class="fas fa-user-tie"></i> Active Staff Members</h2>
                            <div class="status-legend" style="display: flex; gap: 15px; font-size: 0.75rem; font-weight: 700;">
                                <span><i class="fas fa-circle" style="color: #10b981; font-size: 8px;"></i> Online</span>
                                <span><i class="fas fa-circle" style="color: #94a3b8; font-size: 8px;"></i> Offline</span>
                            </div>
                        </div>
                        <div class="activity-feed">
                            <?php 
                                try {
                                    // Fetch staff and admins from users table
                                    $stmt = $pdo->prepare("SELECT id, name, role, last_active, online_status FROM users WHERE role IN ('staff', 'admin') ORDER BY last_active DESC LIMIT 6");
                                    $stmt->execute();
                                    
                                    while($row = $stmt->fetch()) {
                                        // Precise online check: within 5 minutes AND not explicitly logged out
                                        $last_active = strtotime($row['last_active'] ?? '0');
                                        $is_online = (time() - $last_active < 300) && ($row['online_status'] == 1);
                                        $status_color = $is_online ? '#10b981' : '#94a3b8';
                                        $status_pulse = $is_online ? 'online-pulse' : '';
                                        $role_badge = $row['role'] === 'admin' ? '#f59e0b' : '#3b82f6';
                                        
                                        echo "
                                        <div class='activity-item' style='padding: 12px 14px; border: 1.5px solid rgba(255,255,255,0.4); border-radius: 18px; margin-bottom: 12px; background: rgba(255,255,255,0.3); align-items: center; display: flex; gap: 15px;'>
                                            <div class='activity-icon' style='background: white; color: #1e293b; font-weight: 800; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); position: relative;'>
                                                ".strtoupper(($row['name'] ?? 'U')[0])."
                                                <span class='status-dot ".$status_pulse."' style='background: ".$status_color.";'></span>
                                            </div>
                                            <div class='activity-details' style='flex: 1;'>
                                                <div style='font-weight: 800; font-size: 0.9rem; color: #1e293b; display: flex; align-items: center; gap: 8px;'>
                                                    ".htmlspecialchars($row['name'])."
                                                    <span style='font-size: 0.65rem; padding: 2px 8px; border-radius: 6px; background: ".$role_badge."22; color: ".$role_badge."; border: 1px solid ".$role_badge."44; text-transform: uppercase;'>".htmlspecialchars($row['role'])."</span>
                                                </div>
                                                <div style='font-size: 0.72rem; color: #64748b; margin-top: 2px;'>
                                                    ".($is_online ? 'Active now' : 'Last seen ' . date('M j, H:i', $last_active))."
                                                </div>
                                            </div>
                                        </div>";
                                    }
                                } catch(Exception $e) {
                                    echo "<div class='dash-empty-alert'>Error loading staff activity.</div>";
                                }
                            ?>
                        </div>
                    </div>
                </div>

                <style>
                    .status-dot {
                        position: absolute;
                        bottom: -2px;
                        right: -2px;
                        width: 12px;
                        height: 12px;
                        border-radius: 50%;
                        border: 2.5px solid white;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    }
                    .online-pulse {
                        animation: pulse-green 2s infinite;
                    }
                    @keyframes pulse-green {
                        0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
                        70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
                        100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
                    }
                </style>

            <?php elseif ($user_role === 'staff'): ?>
                <?php
                // STAFF DASHBOARD - DATABASE CONNECTION & QUERIES
                $staff_id = $_SESSION['user_id'];

                // SAFE QUERIES - Using client-staff relationship (most reliable)
            
                // Today's Appointments
            

                // Yesterday's Appointments for comparison
            

                // Active Clients
                try {
                    $active_clients_stmt = $pdo->prepare("
                        SELECT id, name, weight, height 
                        FROM clients 
                        WHERE staff_id = ? AND status = 'active'
                        ORDER BY name ASC
                    ");
                    $active_clients_stmt->execute([$staff_id]);
                    $staff_clients = $active_clients_stmt->fetchAll(PDO::FETCH_ASSOC);
                    $clients_count = count($staff_clients);
                } catch (Exception $e) {
                    $staff_clients = [];
                    $clients_count = 0;
                }

                // Get selected client for BMI view (default to first one)
                $selected_bmi_client_id = $_GET['bmi_client_id'] ?? ($staff_clients[0]['id'] ?? null);
                $selected_bmi_client = null;
                $staff_bmi_history = [];
                
                if ($selected_bmi_client_id) {
                    foreach ($staff_clients as $sc) {
                        if ($sc['id'] == $selected_bmi_client_id) {
                            $selected_bmi_client = $sc;
                            break;
                        }
                    }
                }
                
                $staff_bmi = 0;
                $staff_bmi_status = 'Unknown';
                
                if ($selected_bmi_client) {
                    // Calculate from client table first
                    if ($selected_bmi_client['height'] > 0 && $selected_bmi_client['weight'] > 0) {
                        $h_m = $selected_bmi_client['height'] / 100;
                        $staff_bmi = round($selected_bmi_client['weight'] / ($h_m * $h_m), 1);
                    }
                    
                    // Fetch BMI History for selected client
                    try {
                        // Try by user_id first
                        $c_user_id_stmt = $pdo->prepare("SELECT user_id, email FROM clients WHERE id = ?");
                        $c_user_id_stmt->execute([$selected_bmi_client['id']]);
                        $client_meta = $c_user_id_stmt->fetch(PDO::FETCH_ASSOC);
                        $c_user_id = $client_meta['user_id'] ?? 0;
                        $c_email = $client_meta['email'] ?? '';
                        
                        if ($c_user_id > 0) {
                            $hist_stmt = $pdo->prepare("SELECT bmi, DATE_FORMAT(recorded_at, '%b %d') as date_label FROM client_bmi_history WHERE user_id = ? ORDER BY recorded_at ASC LIMIT 15");
                            $hist_stmt->execute([$c_user_id]);
                            $staff_bmi_history = $hist_stmt->fetchAll(PDO::FETCH_ASSOC);
                        } elseif (!empty($c_email)) {
                            // Try to find user by email to get their ID
                            $u_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                            $u_stmt->execute([$c_email]);
                            $found_uid = $u_stmt->fetchColumn();
                            if ($found_uid) {
                                $hist_stmt = $pdo->prepare("SELECT bmi, DATE_FORMAT(recorded_at, '%b %d') as date_label FROM client_bmi_history WHERE user_id = ? ORDER BY recorded_at ASC LIMIT 15");
                                $hist_stmt->execute([$found_uid]);
                                $staff_bmi_history = $hist_stmt->fetchAll(PDO::FETCH_ASSOC);
                                $c_user_id = $found_uid;
                            }
                        }
                        
                        // Fallback: If still no history, seed the first history point to make graph appear
                        if (empty($staff_bmi_history) && $staff_bmi > 0) {
                            // If we have a user_id, save it to DB
                            if ($c_user_id > 0) {
                                try {
                                    $pdo->prepare("INSERT INTO client_bmi_history (user_id, weight, height, bmi) VALUES (?, ?, ?, ?)")->execute([$c_user_id, $selected_bmi_client['weight'], $selected_bmi_client['height'], $staff_bmi]);
                                } catch (Exception $e) {}
                            }
                            // Always provide at least one point for the JS chart
                            $staff_bmi_history = [['bmi' => $staff_bmi, 'date_label' => date('M d')]];
                        }
                    } catch (Exception $e) {}
                    
                    // Set status based on the final staff_bmi
                    if ($staff_bmi > 0) {
                        if ($staff_bmi < 18.5) $staff_bmi_status = 'Underweight';
                        elseif ($staff_bmi < 25) $staff_bmi_status = 'Normal';
                        elseif ($staff_bmi < 30) $staff_bmi_status = 'Overweight';
                        else $staff_bmi_status = 'Obese';
                    }
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

                // Upcoming Appointments (next 7 days)
            
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

                <!-- ── Premium Hero Ribbon ── -->
                <div class="dash-hero-ribbon stagger d-1">
                    <div class="dash-hero-content">
                        <h1>Clinical Intelligence Terminal</h1>
                        <p>Overseeing <?php echo $clients_count; ?> active clients under your care.</p>
                    </div>
                    <div class="dash-hero-badge nutri-glass">
                        <i class="fas fa-stethoscope"></i>
                        <span>Clinical Status: Optimal</span>
                    </div>
                </div>

                <!-- ── Bento Stat Grid ── -->
                <div class="bento-grid stagger d-2">
                    <div class="bento-stat stat-primary" onclick="location.href='user-management-staff.php'">
                        <div class="bento-stat-icon"><i class="fas fa-users"></i></div>
                        <div>
                            <div class="bento-stat-val"><?php echo $clients_count; ?></div>
                            <div class="bento-stat-label">Active Clients</div>
                        </div>
                        <div class="metric-trend trend-neutral" style="margin-top:10px;">
                            <i class="fas fa-user-check"></i>
                            <span>Assigned to you</span>
                        </div>
                        <canvas class="bento-sparkline" id="spark-s0" width="110" height="55" aria-hidden="true"></canvas>
                    </div>

                    <div class="bento-stat stat-secondary" onclick="location.href='anthropometric-information.php'">
                        <div class="bento-stat-icon"><i class="fas fa-chart-line"></i></div>
                        <div>
                            <div class="bento-stat-val"><?php echo $progress_count; ?></div>
                            <div class="bento-stat-label">Weekly Progress</div>
                        </div>
                        <div class="metric-trend <?php echo $progress_diff >= 0 ? 'trend-up' : 'trend-down'; ?>" style="margin-top:10px;">
                            <i class="fas fa-arrow-<?php echo $progress_diff >= 0 ? 'up' : 'down'; ?>"></i>
                            <span><?php echo ($progress_diff >= 0 ? '+' : '') . $progress_diff; ?> from last week</span>
                        </div>
                        <canvas class="bento-sparkline" id="spark-s1" width="110" height="55" aria-hidden="true"></canvas>
                    </div>

                    <div class="bento-stat stat-danger" onclick="location.href='staff-messages.php'">
                        <div class="bento-stat-icon"><i class="fas fa-envelope"></i></div>
                        <div>
                            <div class="bento-stat-val"><?php echo $messages_count; ?></div>
                            <div class="bento-stat-label">Unread Messages</div>
                        </div>
                        <div class="metric-trend <?php echo $messages_count > 0 ? 'trend-alert' : 'trend-neutral'; ?>" style="margin-top:10px;">
                            <i class="fas fa-<?php echo $messages_count > 0 ? 'exclamation-circle' : 'check-circle'; ?>"></i>
                            <span><?php echo $messages_count > 0 ? 'Needs attention' : 'All caught up'; ?></span>
                        </div>
                        <canvas class="bento-sparkline" id="spark-s2" width="110" height="55" aria-hidden="true"></canvas>
                    </div>

                    <div class="bento-stat stat-accent" onclick="openModal('appointments')">
                        <div class="bento-stat-icon"><i class="fas fa-calendar-check"></i></div>
                        <div>
                            <div class="bento-stat-val"><?php 
                                try {
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE staff_id = ? AND appointment_date = CURDATE() AND status = 'scheduled'");
                                    $stmt->execute([$staff_id]);
                                    echo $stmt->fetchColumn();
                                } catch (Exception $e) { echo '0'; }
                            ?></div>
                            <div class="bento-stat-label">Today's Schedule</div>
                        </div>
                        <div class="metric-trend trend-stable" style="margin-top:10px;">
                            <i class="fas fa-clock"></i>
                            <span>Next: 2:00 PM</span>
                        </div>
                        <canvas class="bento-sparkline" id="spark-s3" width="110" height="55" aria-hidden="true"></canvas>
                    </div>
                </div>

                <!-- Staff Data Insights Row -->
                <div class="dash-row stagger d-3" style="grid-template-columns: 1.5fr 1fr;">
                    <div class="dash-panel nutri-glass" style="min-height: auto; padding: 25px;">
                        <div class="dash-panel-header" style="margin-bottom: 20px;">
                            <h2 class="dash-panel-title"><i class="fas fa-chart-area"></i> Weekly Health Analytics</h2>
                            <div class="chart-legend">
                                <span style="font-size: 0.75rem; color: #64748b;"><i class="fas fa-circle" style="color: var(--primary); font-size: 0.5rem; margin-right: 4px;"></i> Client Logs</span>
                            </div>
                        </div>
                        <div style="height: 250px; width: 100%;">
                            <canvas id="staffWeeklyChart"></canvas>
                        </div>
                    </div>

                    <div class="dash-panel" style="min-height: auto;">
                        <div class="dash-panel-header">
                            <h2 class="dash-panel-title" style="display: flex; align-items: center; gap: 8px;"><i class="fas fa-paper-plane"></i> Interactions <info-button feature="interactions" role="<?php echo $_SESSION['role'] ?? 'regular'; ?>"></info-button></h2>
                            <button class="btn btn-primary" style="font-size: 0.75rem; padding: 4px 10px; border-radius: 10px;" onclick="location.href='staff-messages.php'">View All</button>
                        </div>
                        <div class="activity-feed" style="max-height: 260px; overflow-y: auto;">
                            <?php if (count($messages) > 0): ?>
                                <?php foreach ($messages as $message): ?>
                                    <div class="activity-item nutri-glass" style="margin-bottom: 10px; border: none; padding: 12px; cursor: pointer; display: flex; align-items: center;" onclick="location.href='staff-messages.php?client_id=<?php echo $message['client_id']; ?>'">
                                        <div class="activity-icon <?php echo $message['read_at'] === null ? 'accent' : ''; ?>" style="background: var(--sb); color: var(--sc); width: 35px; height: 35px; min-width: 35px; font-size: 0.8rem;">
                                            <?php echo strtoupper(substr($message['client_name'], 0, 1)); ?>
                                        </div>
                                        <div class="activity-content" style="margin-left: 12px;">
                                            <p class="activity-text" style="font-size: 0.85rem; margin: 0;"><b><?php echo htmlspecialchars($message['client_name']); ?></b></p>
                                            <p class="activity-time" style="font-size: 0.7rem; margin: 2px 0;"><?php echo date('M j', strtotime($message['created_at'])); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="text-align: center; padding: 20px; color: #94a3b8;">
                                    <i class="fas fa-comments" style="font-size: 1.5rem; margin-bottom: 8px; opacity: 0.5;"></i>
                                    <p style="font-size: 0.8rem;">No messages.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ── Staff BMI Body Insight Row ── -->
                <div class="dash-row stagger d-3" style="grid-template-columns: 1fr; margin-top: 30px;">
                    <div class="dash-panel nutri-glass" style="padding: 30px;">
                        <div class="dash-panel-header" style="margin-bottom: 25px;">
                            <h2 class="dash-panel-title" style="display: flex; align-items: center; gap: 8px;"><i class="fas fa-child-reaching" style="color: #10b981;"></i> Body Composition Insight <info-button feature="body_composition" role="<?php echo $_SESSION['role'] ?? 'regular'; ?>"></info-button></h2>
                            <div style="display: flex; gap: 20px; align-items: center;">
                                <div style="font-size: 0.85rem; color: #64748b; font-weight: 600;">Based on your latest anthropometrics</div>
                                <div style="display: flex; gap: 10px; align-items: center; background: rgba(0,0,0,0.03); padding: 5px 15px; border-radius: 50px; border: 1px solid rgba(0,0,0,0.05);">
                                    <span style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Switch Client:</span>
                                    <select onchange="window.location.href='?bmi_client_id=' + this.value" style="background: transparent; border: none; font-family: 'Outfit'; font-weight: 700; color: #1e293b; outline: none; cursor: pointer; font-size: 0.85rem;">
                                        <?php foreach ($staff_clients as $sc): ?>
                                            <option value="<?php echo $sc['id']; ?>" <?php echo $sc['id'] == $selected_bmi_client_id ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($sc['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($selected_bmi_client): ?>
                        <div class="bmi-visual-container">
                            <div class="bmi-status-info">
                                <h4 style="margin: 0 0 5px 0; color: #64748b; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700;">Current BMI</h4>
                                <div class="bmi-big-value"><?php echo $staff_bmi ?: '--'; ?></div>
                                <div class="bmi-status-badge status-<?php echo strtolower($staff_bmi_status); ?>">
                                    <?php echo $staff_bmi_status; ?>
                                </div>
                                
                                <div class="clinical-insight-card">
                                    <h4 style="margin: 0 0 8px 0; color: #1e293b; font-weight: 700; font-size: 0.8rem; text-transform: uppercase;">Health Insight</h4>
                                    <p style="font-size: 0.88rem; color: #64748b; line-height: 1.5; margin: 0;">
                                        <?php 
                                            if ($staff_bmi_status === 'Underweight') echo "Your BMI is in the underweight category. Your dietician can help you create a sustainable plan for reaching your health goals.";
                                            elseif ($staff_bmi_status === 'Normal') echo "Your BMI is in the healthy range. Your dietician can help you create a sustainable plan for reaching your health goals.";
                                            elseif ($staff_bmi_status === 'Overweight') echo "Your BMI is in the overweight category. Your dietician can help you create a sustainable plan for reaching your health goals.";
                                            elseif ($staff_bmi_status === 'Obese') echo "Your BMI is in the obesity category. Your dietician can help you create a sustainable plan for reaching your health goals.";
                                            else echo "Record the client's height and weight to generate a BMI analysis and clinical insight.";
                                        ?>
                                    </p>
                                </div>

                                <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                                    <div style="background: #f8fafc; padding: 10px 15px; border-radius: 12px; flex: 1; text-align: center; border: 1px solid #e2e8f0;">
                                        <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; font-weight: 700; margin-bottom: 2px;">Weight</div>
                                        <div style="font-size: 0.95rem; font-weight: 800; color: #1e293b;"><?php echo number_format($selected_bmi_client['weight'] ?? 0, 2); ?> <small style="font-size: 0.7em; opacity: 0.6;">kg</small></div>
                                    </div>
                                    <div style="background: #f8fafc; padding: 10px 15px; border-radius: 12px; flex: 1; text-align: center; border: 1px solid #e2e8f0;">
                                        <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; font-weight: 700; margin-bottom: 2px;">Height</div>
                                        <div style="font-size: 0.95rem; font-weight: 800; color: #1e293b;"><?php echo number_format($selected_bmi_client['height'] ?? 0, 2); ?> <small style="font-size: 0.7em; opacity: 0.6;">cm</small></div>
                                    </div>
                                </div>

                                <div style="margin-top: 15px; display: flex; gap: 10px;">
                                    <button onclick="location.href='anthropometric-information.php?client_id=<?php echo $selected_bmi_client['id']; ?>&tab=body-stats'" 
                                            style="border-radius: 12px; padding: 12px 15px; font-weight: 700; font-size: 0.85rem; flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; transition: all 0.2s; background: transparent; color: #10b981; border: 1.5px solid #10b981; font-family: 'Outfit';">
                                        <i class="fas fa-history"></i> View Statistics
                                    </button>
                                </div>
                            </div>
                            
                            <div class="bmi-chart-wrapper">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                    <h3 style="font-family: 'Outfit'; font-size: 1rem; color: #1e293b; margin: 0; font-weight: 700;"><i class="fas fa-history" style="color: var(--primary); margin-right: 8px;"></i> Progress History</h3>
                                    <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 600;">Last 15 recordings</span>
                                </div>
                                <div style="height: 250px; position: relative; width: 100%;">
                                    <canvas id="staffBmiHistoryChart" style="width: 100%; height: 100%;"></canvas>
                                    <?php if (empty($staff_bmi_history)): ?>
                                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #94a3b8; font-size: 0.9rem; text-align: center; width: 100%;">
                                        <i class="fas fa-chart-line" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.3;"></i>
                                        <p>No BMI history data available yet.</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: #94a3b8;">
                                <i class="fas fa-users-slash" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i>
                                <p>No active clients found to display BMI data.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Staff Command Tiles -->
                <div class="management-section stagger d-3" style="margin-top: 30px; margin-bottom: 30px;">
                    <div class="section-header">
                        <h2 style="font-family: 'Outfit'; font-weight: 800; font-size: 1.5rem; color: #1e293b;">Clinical Command Tiles</h2>
                    </div>
                    <div class="command-tiles">
                        <a href="user-management-staff.php" class="command-tile nutri-glass" style="text-decoration: none;">
                            <div class="command-tile-icon" style="background: rgba(45, 138, 86, 0.1); color: var(--primary);"><i class="fas fa-id-card"></i></div>
                            <div class="command-tile-info">
                                <h3 style="color: #1e293b; font-weight: 700;">Client Roster</h3>
                                <p style="color: #64748b;">Manage profiles and health records.</p>
                            </div>
                        </a>
                        <a href="meal-planner.php" class="command-tile nutri-glass" style="text-decoration: none;">
                            <div class="command-tile-icon" style="background: rgba(79, 172, 254, 0.1); color: #4facfe; border-radius: 12px;"><i class="fas fa-utensils"></i></div>
                            <div class="command-tile-info">
                                <h3 style="color: #1e293b; font-weight: 700;">Meal Planner</h3>
                                <p style="color: #64748b;">Design and assign nutrition plans.</p>
                            </div>
                        </a>
                        <a href="anthropometric-information.php" class="command-tile nutri-glass" style="text-decoration: none;">
                            <div class="command-tile-icon" style="background: rgba(217, 119, 6, 0.1); color: #d97706;"><i class="fas fa-weight"></i></div>
                            <div class="command-tile-info">
                                <h3 style="color: #1e293b; font-weight: 700;">Body Stats</h3>
                                <p style="color: #64748b;">Track client physical progress.</p>
                            </div>
                        </a>
                        <a href="staff-help.php" class="command-tile nutri-glass" style="text-decoration: none;">
                            <div class="command-tile-icon" style="background: rgba(225, 29, 72, 0.1); color: #e11d48;"><i class="fas fa-headset"></i></div>
                            <div class="command-tile-info">
                                <h3 style="color: #1e293b; font-weight: 700;">Support Hub</h3>
                                <p style="color: #64748b;">Internal tickets & clinical support.</p>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Modal Structure -->
                <div id="modalOverlay" class="modal-overlay">
                    <div class="modal-container nutri-glass">
                        <div class="modal-header d-flex justify-content-between align-items-center" style="background: transparent; border-bottom: 1px solid var(--glass-border);">
                            <h3 id="modalTitle" style="font-family: 'Outfit'; font-weight: 700; margin: 0;">Appointments</h3>
                            <button class="modal-close" onclick="closeModal()" style="background: rgba(0,0,0,0.05); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="modal-content" id="modalContent" style="padding: 20px;">
                            <!-- AJAX Content -->
                        </div>
                    </div>
                </div>

            <script>
                function openModal(type) {
                    const modal = document.getElementById('modalOverlay');
                    modal.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                }
                function closeModal() {
                    const modal = document.getElementById('modalOverlay');
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
                
                function openQuickUpdateModal() {
                    const modal = document.getElementById('modalOverlay');
                    const title = document.getElementById('modalTitle');
                    const content = document.getElementById('modalContent');
                    
                    title.innerHTML = '<i class="fas fa-bolt" style="color:#10b981; margin-right:10px;"></i>Quick Body Update';
                    content.innerHTML = `
                        <form method="POST" id="quickUpdateForm" onsubmit="window.quickBodyUpdate(event)">
                            <input type="hidden" name="action" value="quick_body_update">
                            <div style="margin-bottom: 20px;">
                                <label style="display:block; margin-bottom:8px; font-weight:600; color:#475569;">Current Weight (kg)</label>
                                <div style="position:relative;">
                                    <i class="fas fa-weight" style="position:absolute; left:15px; top:50%; transform:translateY(-50%); color:#94a3b8;"></i>
                                    <input type="number" step="any" name="weight" id="quickWeight" value="<?php echo $client['weight'] ?? ''; ?>" 
                                        style="width:100%; padding:12px 12px 12px 40px; border-radius:12px; border:1.5px solid #e2e8f0; font-family:'Outfit'; font-weight:600; outline:none; transition:all 0.2s;"
                                        onfocus="this.style.borderColor='#10b981'; this.style.boxShadow='0 0 0 3px rgba(16,185,129,0.1)';"
                                        onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';"
                                        oninput="calculateLiveBMI()">
                                </div>
                            </div>
                            <div style="margin-bottom: 25px;">
                                <label style="display:block; margin-bottom:8px; font-weight:600; color:#475569;">Height (cm)</label>
                                <div style="position:relative;">
                                    <i class="fas fa-ruler-vertical" style="position:absolute; left:15px; top:50%; transform:translateY(-50%); color:#94a3b8;"></i>
                                    <input type="number" step="any" name="height" id="quickHeight" value="<?php echo $client['height'] ?? ''; ?>" 
                                        style="width:100%; padding:12px 12px 12px 40px; border-radius:12px; border:1.5px solid #e2e8f0; font-family:'Outfit'; font-weight:600; outline:none; transition:all 0.2s;"
                                        onfocus="this.style.borderColor='#10b981'; this.style.boxShadow='0 0 0 3px rgba(16,185,129,0.1)';"
                                        onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';"
                                        oninput="calculateLiveBMI()">
                                </div>
                            </div>
                            
                            <div id="liveBmiResult" style="background:#f8fafc; padding:15px; border-radius:15px; margin-bottom:25px; text-align:center; transition:all 0.3s ease;">
                                <div style="font-size:0.8rem; color:#64748b; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin-bottom:5px;">Estimated BMI</div>
                                <div id="liveBmiValue" style="font-size:2rem; font-weight:800; color:#1e293b;">--</div>
                                <div id="liveBmiStatus" style="font-size:0.85rem; font-weight:700; margin-top:5px; padding:4px 12px; border-radius:10px; display:inline-block;">UNKNOWN</div>
                            </div>
                            
                            <div style="display:flex; gap:12px;">
                                <button type="button" class="btn btn-outline" onclick="closeModal()" style="flex:1; border-radius:12px; padding:12px; font-weight:700;">Cancel</button>
                                <button type="submit" class="btn btn-primary" style="flex:2; border-radius:12px; padding:12px; font-weight:700; background:#10b981; border:none; box-shadow:0 4px 15px rgba(16,185,129,0.2);">Update Measurements</button>
                            </div>
                        </form>
                    `;
                    
                    modal.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                    calculateLiveBMI();
                }
                
                function calculateLiveBMI() {
                    const weight = parseFloat(document.getElementById('quickWeight').value);
                    const height = parseFloat(document.getElementById('quickHeight').value);
                    const valueDiv = document.getElementById('liveBmiValue');
                    const statusDiv = document.getElementById('liveBmiStatus');
                    
                    if (weight > 0 && height > 0) {
                        const hM = height / 100;
                        const bmi = (weight / (hM * hM)).toFixed(1);
                        valueDiv.textContent = bmi;
                        
                        let status = 'UNKNOWN';
                        let color = '#94a3b8';
                        let bgColor = '#f1f5f9';
                        
                        if (bmi < 18.5) { status = 'UNDERWEIGHT'; color = '#3b82f6'; bgColor = '#eff6ff'; }
                        else if (bmi < 25) { status = 'NORMAL'; color = '#10b981'; bgColor = '#ecfdf5'; }
                        else if (bmi < 30) { status = 'OVERWEIGHT'; color = '#f59e0b'; bgColor = '#fffbeb'; }
                        else { status = 'OBESE'; color = '#ef4444'; bgColor = '#fef2f2'; }
                        
                        statusDiv.textContent = status;
                        statusDiv.style.color = color;
                        statusDiv.style.backgroundColor = bgColor;
                        valueDiv.style.color = color;
                        
                        // PREVIEW TRANSFORMATION
                        // (Removed SVG Morph logic - simply update the DOM text)
                    } else {
                        valueDiv.textContent = '--';
                        statusDiv.textContent = 'UNKNOWN';
                        statusDiv.style.color = '#94a3b8';
                        statusDiv.style.backgroundColor = '#f1f5f9';
                        valueDiv.style.color = '#1e293b';
                    }
                }
                window.onclick = function(event) {
                    if (event.target == document.getElementById('modalOverlay')) closeModal();
                }
            </script>

        <?php else: ?>
            <?php
            // USER DASHBOARD - DATABASE CONNECTION & QUERIES
            require_once 'database.php';
            $database = new Database();
            $pdo = $database->getConnection();

            $user_id = $_SESSION['user_id'];
            $user_email = $_SESSION['user_email'] ?? '';

            // Create BMI History table if it doesn't exist
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS client_bmi_history (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    weight DECIMAL(5,2),
                    height DECIMAL(5,2),
                    bmi DECIMAL(5,1),
                    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
            } catch (Exception $e) {
                // Table might already exist or permission issue, proceed anyway
            }

            // HANDLE QUICK UPDATE
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_body_update') {
                $weight = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
                $height = !empty($_POST['height']) ? (float)$_POST['height'] : null;
                
                try {
                    // Update by user_id first
                    $upd = $pdo->prepare("UPDATE clients SET weight = ?, height = ?, updated_at = NOW() WHERE user_id = ?");
                    $upd->execute([$weight, $height, $user_id]);
                    
                    if ($upd->rowCount() === 0 && !empty($user_email)) {
                        $upd = $pdo->prepare("UPDATE clients SET weight = ?, height = ?, updated_at = NOW(), user_id = ? WHERE email = ? AND (user_id IS NULL OR user_id = 0)");
                        $upd->execute([$weight, $height, $user_id, $user_email]);
                        
                        if ($upd->rowCount() === 0) {
                            $user_name = $_SESSION['user_name'] ?? 'User';
                            $ins = $pdo->prepare("INSERT INTO clients (user_id, name, email, weight, height, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())");
                            $ins->execute([$user_id, $user_name, $user_email, $weight, $height]);
                        }
                    } elseif ($upd->rowCount() === 0) {
                        $user_name = $_SESSION['user_name'] ?? 'User';
                        $ins = $pdo->prepare("INSERT INTO clients (user_id, name, email, weight, height, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())");
                        $ins->execute([$user_id, $user_name, $user_email, $weight, $height]);
                    }
                    
                    // Record in BMI History
                    if ($weight > 0 && $height > 0) {
                        $height_m = $height / 100;
                        $bmi = round($weight / ($height_m * $height_m), 1);
                        $hist = $pdo->prepare("INSERT INTO client_bmi_history (user_id, weight, height, bmi) VALUES (?, ?, ?, ?)");
                        $hist->execute([$user_id, $weight, $height, $bmi]);
                    }
                    
                    $_SESSION['quick_update_success'] = true;
                    header("Location: dashboard.php");
                    exit();
                } catch (Exception $e) {}
            }

            // Get client data with fallback to email if user_id link is missing
            try {
                // Try searching by user_id
                $client_data = $pdo->prepare("
                    SELECT c.*, s.name as staff_name 
                    FROM clients c 
                    LEFT JOIN staff s ON c.staff_id = s.id 
                    WHERE c.user_id = ?
                    LIMIT 1
                ");
                $client_data->execute([$user_id]);
                $client = $client_data->fetch(PDO::FETCH_ASSOC);

                // Fallback to email if not found (some users were registered before being linked to clients)
                if (!$client && !empty($user_email)) {
                    $client_data = $pdo->prepare("
                        SELECT c.*, s.name as staff_name 
                        FROM clients c 
                        LEFT JOIN staff s ON c.staff_id = s.id 
                        WHERE c.email = ?
                        LIMIT 1
                    ");
                    $client_data->execute([$user_email]);
                    $client = $client_data->fetch(PDO::FETCH_ASSOC);
                }
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

            // Get client height/weight for BMI
            $user_bmi = 0;
            $user_bmi_status = 'Unknown';
            if ($client && $client['height'] > 0 && $client['weight'] > 0) {
                $height_m = $client['height'] / 100;
                $user_bmi = round($client['weight'] / ($height_m * $height_m), 1);
                
                if ($user_bmi < 18.5) $user_bmi_status = 'Underweight';
                elseif ($user_bmi < 25) $user_bmi_status = 'Normal';
                elseif ($user_bmi < 30) $user_bmi_status = 'Overweight';
                else $user_bmi_status = 'Obese';
            }

            // Fetch BMI history for chart
            $bmi_history_data = [];
            try {
                $hist_stmt = $pdo->prepare("SELECT bmi, DATE_FORMAT(recorded_at, '%b %d') as date_label FROM client_bmi_history WHERE user_id = ? ORDER BY recorded_at ASC LIMIT 15");
                $hist_stmt->execute([$user_id]);
                $bmi_history_data = $hist_stmt->fetchAll(PDO::FETCH_ASSOC);

                // If user_bmi is still 0 (maybe client profile not fully created), fallback to latest history record
                if ($user_bmi == 0 && !empty($bmi_history_data)) {
                    $latest_record = end($bmi_history_data);
                    $user_bmi = (float)$latest_record['bmi'];
                    if ($user_bmi < 18.5) $user_bmi_status = 'Underweight';
                    elseif ($user_bmi < 25) $user_bmi_status = 'Normal';
                    elseif ($user_bmi < 30) $user_bmi_status = 'Overweight';
                    else $user_bmi_status = 'Obese';
                }

                // If history is empty but we have a current BMI, seed the first point
                if (empty($bmi_history_data) && $user_bmi > 0) {
                    $pdo->prepare("INSERT INTO client_bmi_history (user_id, weight, height, bmi) VALUES (?, ?, ?, ?)")->execute([$user_id, $client['weight'], $client['height'], $user_bmi]);
                    $bmi_history_data = [['bmi' => $user_bmi, 'date_label' => date('M d')]];
                }
            } catch (Exception $e) {
                // Ignore if table doesn't exist yet
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
            } catch (Exception $e) { $messages = []; }
                // --- WELLNESS PULSE (HUMANIZED AI) ---
            require_once 'api/nutrient_analyzer.php';
            $analyzer = new NutrientAnalyzer();
            $user_ai_data = ['score' => 1, 'flag' => false, 'insights' => []];

            try {
                // Get Latest Resilience Score
                $stmt = $pdo->prepare("SELECT sentiment_score, burnout_flag FROM ai_burnout_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$user_id]);
                if ($row = $stmt->fetch()) {
                    $user_ai_data['score'] = $row['sentiment_score'];
                    $user_ai_data['flag']  = (bool)$row['burnout_flag'];
                }

                // Get Recent Symptom Markers and check for "Data-Verified Debts"
                $stmt = $pdo->prepare("SELECT symptom_keyword, mapped_nutrient FROM ai_extracted_symptoms WHERE user_id = ? AND mapped_nutrient IS NOT NULL GROUP BY symptom_keyword, mapped_nutrient ORDER BY MAX(created_at) DESC LIMIT 4");
                $stmt->execute([$user_id]);
                $raw_markers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($raw_markers as $m) {
                    $debt_status = $analyzer->getNutrientDebtStatus($user_id, $m['mapped_nutrient']);
                    $user_ai_data['insights'][] = [
                        'symptom' => $m['symptom_keyword'],
                        'nutrient' => $m['mapped_nutrient'],
                        'is_verified' => $debt_status['is_debt'] ?? false,
                        'avg_intake' => $debt_status['avg_daily'] ?? 0,
                        'foods' => $analyzer->getFoodSuggestion($m['mapped_nutrient'])
                    ];
                }
            } catch (Exception $e) {}
            ?>

            <!-- ── Premium Hero Ribbon ── -->
            <?php 
                // FETCH REAL-TIME CLIENT DATA
                $calories_today = 0; $protein_today = 0; $carbs_today = 0; $fats_today = 0;
                try {
                    $stmt = $pdo->prepare("SELECT SUM(calories) as cal, SUM(protein) as prot, SUM(carbs) as carb, SUM(fat) as fat FROM food_logs WHERE user_id = ? AND log_date = CURDATE()");
                    $stmt->execute([$user_id]);
                    $totals = $stmt->fetch();
                    $calories_today = (int)($totals['cal'] ?? 0);
                    $protein_today = (int)($totals['prot'] ?? 0);
                    $carbs_today = (int)($totals['carb'] ?? 0);
                    $fats_today = (int)($totals['fat'] ?? 0);
                    
                    $stmt = $pdo->prepare("SELECT goal_protein, goal_carbs, goal_fats FROM clients WHERE user_id = ? LIMIT 1");
                    $stmt->execute([$user_id]);
                    $cGoals = $stmt->fetch();
                    $gProt = (int)($cGoals['goal_protein'] ?? 150);
                    $gCarb = (int)($cGoals['goal_carbs'] ?? 200);
                    $gFat  = (int)($cGoals['goal_fats'] ?? 65);
                    
                    $getRO = function($val, $goal) {
                        $g = $goal > 0 ? $goal : 100;
                        return max(0, 213.6 - (min($val / $g, 1) * 213.6));
                    };
                    $p_off = $getRO($protein_today, $gProt);
                    $c_off = $getRO($carbs_today, $gCarb);
                    $f_off = $getRO($fats_today, $gFat);
                    
                } catch (Exception $e) {
                    $p_off = 213.6; $c_off = 213.6; $f_off = 213.6;
                }

                $water_today = 0;
                try {
                    $stmt = $pdo->prepare("SELECT SUM(glasses) FROM hydration_tracking WHERE user_id = ? AND tracking_date = CURDATE()");
                    $stmt->execute([$user_id]);
                    $water_today = (int)$stmt->fetchColumn();
                } catch (Exception $e) {}
            ?>
            <div class="dash-hero-ribbon stagger d-1">
                <?php if (isset($_SESSION['quick_update_success'])): ?>
                <div style="position: absolute; top: -20px; left: 50%; transform: translateX(-50%); background: #10b981; color: white; padding: 10px 25px; border-radius: 30px; font-weight: 700; font-size: 0.9rem; box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3); z-index: 100; display: flex; align-items: center; gap: 10px; animation: slideDown 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
                    <i class="fas fa-check-circle"></i>
                    Measurements updated!
                </div>
                <?php unset($_SESSION['quick_update_success']); ?>
                <style>
                    @keyframes slideDown {
                        from { transform: translate(-50%, -50px); opacity: 0; }
                        to { transform: translate(-50%, 0); opacity: 1; }
                    }
                </style>
                <script>setTimeout(() => { document.querySelector('[style*="slideDown"]').style.opacity = '0'; document.querySelector('[style*="slideDown"]').style.transition = 'all 0.5s ease'; }, 3000);</script>
                <?php endif; ?>
                <div class="dash-hero-content">
                    <h1>Personal Wellness Command</h1>
                    <p>You have consumed <b id="caloriesConsumedDisplay"><?php echo $calories_today; ?></b><b> kcal</b> today. Keep tracking!</p>
                </div>
                <div class="dash-hero-badge nutri-glass">
                    <i class="fas fa-microchip"></i>
                    <span id="healthScoreDisplay">System Status: 92%</span>
                </div>
            </div>
            <!-- ── Management Section (Quick Actions) ── -->
            <div class="management-section stagger d-2" style="margin-top: 30px; margin-bottom: 30px;">
                <div class="command-tiles">
                    <a href="Nutrition-Calculator.php" class="command-tile nutri-glass" style="--sc: #4f46e5; --sb: rgba(79, 70, 229, 0.08); --sc-rgb: 79, 70, 229;">
                        <div class="command-tile-icon"><i class="fas fa-calculator"></i></div>
                        <div class="command-tile-info">
                            <h3>Calculator</h3>
                            <p>Daily macro needs</p>
                        </div>
                        <canvas class="bento-sparkline" width="110" height="50"></canvas>
                    </a>
                    <a href="food-exchange.php" class="command-tile nutri-glass" style="--sc: #d946ef; --sb: rgba(217, 70, 239, 0.08); --sc-rgb: 217, 70, 239;">
                        <div class="command-tile-icon"><i class="fas fa-exchange-alt"></i></div>
                        <div class="command-tile-info">
                            <h3>Exchange</h3>
                            <p>Healthy alternatives</p>
                        </div>
                        <canvas class="bento-sparkline" width="110" height="50"></canvas>
                    </a>
                    <a href="user-health-tracker.php" class="command-tile nutri-glass" style="--sc: #0d9488; --sb: rgba(13, 148, 136, 0.08); --sc-rgb: 13, 148, 136;">
                        <div class="command-tile-icon"><i class="fas fa-book-medical"></i></div>
                        <div class="command-tile-info">
                            <h3>Log Food</h3>
                            <p>Daily Tracking</p>
                        </div>
                        <canvas class="bento-sparkline" width="110" height="50"></canvas>
                    </a>
                    <a href="user-messages.php" class="command-tile nutri-glass" style="--sc: #e11d48; --sb: rgba(225, 29, 72, 0.08); --sc-rgb: 225, 29, 72;">
                        <div class="command-tile-icon"><i class="fas fa-comment-dots"></i></div>
                        <div class="command-tile-info">
                            <h3>Messages</h3>
                            <p id="unreadMsgCount"><?php echo $unread_count; ?> unread</p>
                        </div>
                        <canvas class="bento-sparkline" width="110" height="50"></canvas>
                    </a>
                </div>
            </div>

            <!-- ── Performance Overview (Macro & Hydration) ── -->
            <div class="dash-row stagger d-3" style="grid-template-columns: 1fr 1fr; margin-bottom: 30px;">
                <div class="dash-panel nutri-glass" style="min-height: auto; padding: 25px;">
                    <div class="dash-panel-header" style="margin-bottom: 20px;">
                        <h2 class="dash-panel-title" style="display: flex; align-items: center; gap: 8px;"><i class="fas fa-bullseye" style="color: var(--primary);"></i> Nutritional Snap <info-button feature="nutritional_snap" role="<?php echo $_SESSION['role'] ?? 'regular'; ?>"></info-button></h2>
                    </div>
                    <div class="macro-rings-container" style="display: flex; justify-content: space-around; align-items: center; gap: 15px;">
                        <!-- Protein Ring -->
                        <div class="macro-ring-group" style="text-align: center;">
                            <div class="macro-ring" style="width: 70px; height: 70px; position: relative;">
                                <svg viewBox="0 0 80 80" style="transform: rotate(-90deg); filter: drop-shadow(0px 8px 12px rgba(79, 172, 254, 0.2));">
                                    <defs>
                                        <linearGradient id="pGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                            <stop offset="0%" stop-color="#00f2fe" />
                                            <stop offset="100%" stop-color="#4facfe" />
                                        </linearGradient>
                                    </defs>
                                    <circle cx="40" cy="40" r="34" fill="none" stroke="#f1f5f9" stroke-width="6"></circle>
                                    <circle id="p_bar" cx="40" cy="40" r="34" fill="none" stroke="url(#pGrad)" stroke-width="6" stroke-dasharray="213.6" stroke-dashoffset="<?php echo $p_off; ?>" stroke-linecap="round" style="transition: stroke-dashoffset 1.5s cubic-bezier(0.34, 1.56, 0.64, 1);"></circle>
                                </svg>
                            </div>
                            <div style="margin-top: 8px; font-weight: 800; font-size: 0.8rem; color: #1e293b;">Protein</div>
                            <div id="p_val" style="font-size: 0.85rem; font-weight: 600; color: #64748b; transition: all 0.3s;"><?php echo $protein_today; ?>g</div>
                        </div>
                        <!-- Carbs Ring -->
                        <div class="macro-ring-group" style="text-align: center;">
                            <div class="macro-ring" style="width: 70px; height: 70px; position: relative;">
                                <svg viewBox="0 0 80 80" style="transform: rotate(-90deg); filter: drop-shadow(0px 8px 12px rgba(67, 233, 123, 0.2));">
                                    <defs>
                                        <linearGradient id="cGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                            <stop offset="0%" stop-color="#38f9d7" />
                                            <stop offset="100%" stop-color="#43e97b" />
                                        </linearGradient>
                                    </defs>
                                    <circle cx="40" cy="40" r="34" fill="none" stroke="#f1f5f9" stroke-width="6"></circle>
                                    <circle id="c_bar" cx="40" cy="40" r="34" fill="none" stroke="url(#cGrad)" stroke-width="6" stroke-dasharray="213.6" stroke-dashoffset="<?php echo $c_off; ?>" stroke-linecap="round" style="transition: stroke-dashoffset 1.5s cubic-bezier(0.34, 1.56, 0.64, 1);"></circle>
                                </svg>
                            </div>
                            <div style="margin-top: 8px; font-weight: 800; font-size: 0.8rem; color: #1e293b;">Carbs</div>
                            <div id="c_val" style="font-size: 0.85rem; font-weight: 600; color: #64748b; transition: all 0.3s;"><?php echo $carbs_today; ?>g</div>
                        </div>
                        <!-- Fats Ring -->
                        <div class="macro-ring-group" style="text-align: center;">
                            <div class="macro-ring" style="width: 70px; height: 70px; position: relative;">
                                <svg viewBox="0 0 80 80" style="transform: rotate(-90deg); filter: drop-shadow(0px 8px 12px rgba(245, 87, 108, 0.2));">
                                    <defs>
                                        <linearGradient id="fGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                            <stop offset="0%" stop-color="#f093fb" />
                                            <stop offset="100%" stop-color="#f5576c" />
                                        </linearGradient>
                                    </defs>
                                    <circle cx="40" cy="40" r="34" fill="none" stroke="#f1f5f9" stroke-width="6"></circle>
                                    <circle id="f_bar" cx="40" cy="40" r="34" fill="none" stroke="url(#fGrad)" stroke-width="6" stroke-dasharray="213.6" stroke-dashoffset="<?php echo $f_off; ?>" stroke-linecap="round" style="transition: stroke-dashoffset 1.5s cubic-bezier(0.34, 1.56, 0.64, 1);"></circle>
                                </svg>
                            </div>
                            <div style="margin-top: 8px; font-weight: 800; font-size: 0.8rem; color: #1e293b;">Fats</div>
                            <div id="f_val" style="font-size: 0.85rem; font-weight: 600; color: #64748b; transition: all 0.3s;"><?php echo $fats_today; ?>g</div>
                        </div>
                    </div>
                </div>

                <div class="dash-panel nutri-glass" style="min-height: auto; padding: 25px; position: relative; overflow: visible;">
                    <!-- Masked Wave Container to allow info-button to pop out -->
                    <div class="hydration-wave-mask" style="position: absolute; inset: 0; border-radius: inherit; overflow: hidden; pointer-events: none; z-index: 0;">
                        <div class="hydration-wave-bg" id="waterWave" style="position: absolute; bottom: 0; left: 0; width: 100%; height: <?php echo min($water_today * 12.5, 100); ?>%; transition: height 1s cubic-bezier(0.16, 1, 0.3, 1);">
                            <!-- Front Wave -->
                            <div class="wave front-wave"></div>
                            <!-- Back Wave -->
                            <div class="wave back-wave"></div>
                        </div>
                    </div>
                    
                    <div class="dash-panel-header" style="position: relative; z-index: 2;">
                        <h2 class="dash-panel-title" style="display: flex; align-items: center; gap: 8px;"><i class="fas fa-tint" style="color: #3b82f6;"></i> Hydration Flow <info-button feature="hydration_flow" role="<?php echo $_SESSION['role'] ?? 'regular'; ?>"></info-button></h2>
                    </div>
                    <div style="position: relative; z-index: 2; text-align: center; margin-top: 15px;">
                        <div id="waterCount" style="font-size: 2.8rem; font-weight: 800; font-family: 'Outfit'; color: #1e293b; text-shadow: 0 4px 10px rgba(255,255,255,0.5);"><?php echo $water_today; ?></div>
                        <div style="font-size: 0.85rem; color: #475569; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Glasses Logged</div>
                        <div style="display: flex; justify-content: center; gap: 18px; margin-top: 18px;">
                            <button class="btn btn-outline" style="width: 44px; height: 44px; border-radius: 12px; padding: 0; border-color: rgba(59, 130, 246, 0.2);" onclick="updateWater('remove')"><i class="fas fa-minus" style="color: #3b82f6;"></i></button>
                            <button class="btn btn-primary" style="width: 44px; height: 44px; border-radius: 12px; padding: 0; background: #3b82f6; box-shadow: 0 4px 14px rgba(59, 130, 246, 0.3);" onclick="updateWater('add')"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ── BMI Body Insight Row ── -->
            <div class="dash-row stagger d-4" style="grid-template-columns: 1fr; margin-bottom: 30px;">
                <div class="dash-panel nutri-glass" style="padding: 30px;">
                        <div class="dash-panel-header">
                            <h2 class="dash-panel-title" style="display: flex; align-items: center; gap: 8px;"><i class="fas fa-child-reaching" style="color: #10b981;"></i> Body Composition Insight <info-button feature="body_composition" role="<?php echo $_SESSION['role'] ?? 'regular'; ?>"></info-button></h2>
                            <div style="font-size: 0.85rem; color: #64748b; font-weight: 600;">Based on your latest anthropometrics</div>
                        </div>
                    
                        <div class="bmi-visual-container">
                            <div class="bmi-status-info">
                                <h4 style="margin: 0 0 5px 0; color: #64748b; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px;">Current BMI</h4>
                                <div class="bmi-big-value" id="userBmiValueDisplay"><?php echo $user_bmi ?: '--'; ?></div>
                                <div class="bmi-status-badge status-<?php echo strtolower($user_bmi_status); ?>" id="userBmiStatusDisplay">
                                    <?php echo $user_bmi_status; ?>
                                </div>
                                
                                <div class="clinical-insight-card">
                                    <h4 style="margin: 0 0 8px 0; color: #1e293b; font-weight: 700; font-size: 0.85rem; text-transform: uppercase;">Health Insight</h4>
                                    <p style="font-size: 0.9rem; color: #64748b; line-height: 1.5; margin: 0;" id="userBmiInsightDisplay">
                                        <?php 
                                            if ($user_bmi_status === 'Underweight') echo "Your BMI indicates you may be underweight. We recommend consulting with your dietician to ensure you're meeting your nutritional needs.";
                                            elseif ($user_bmi_status === 'Normal') echo "Great job! Your BMI falls within the healthy range. Maintaining a balanced diet and regular physical activity will help keep you here.";
                                            elseif ($user_bmi_status === 'Overweight') echo "Your BMI is in the overweight category. Your dietician can help you create a sustainable plan for reaching your health goals.";
                                            elseif ($user_bmi_status === 'Obese') echo "Your BMI indicates obesity. This can increase health risks, but our team is here to support you with a personalized clinical nutrition plan.";
                                            else echo "Please update your height and weight in the Body Stats section to see your BMI analysis.";
                                        ?>
                                    </p>
                                </div>
                                
                                <div style="margin-top: 15px; display: flex; gap: 10px;">
                                    <button class="btn btn-outline" onclick="location.href='user-health-tracker.php?tab=body-stats'" style="border-radius: 10px; padding: 10px 15px; font-weight: 700; font-size: 0.85rem; flex: 1;">
                                        <i class="fas fa-history" style="margin-right: 6px;"></i> View Statistics
                                    </button>
                                </div>
                            </div>
                            
                            <div class="bmi-chart-wrapper">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                    <h3 style="font-family: 'Outfit'; font-size: 1rem; color: #1e293b; margin: 0; font-weight: 700;"><i class="fas fa-history" style="color: var(--primary); margin-right: 8px;"></i> Progress History</h3>
                                    <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 600;">Last 15 recordings</span>
                                </div>
                                <div style="height: 220px; position: relative;">
                                    <canvas id="bmiHistoryChart"></canvas>
                                    <?php if (empty($bmi_history_data)): ?>
                                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #94a3b8; font-size: 0.9rem; text-align: center; width: 100%;">
                                        <i class="fas fa-chart-line" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.3;"></i>
                                        <p>No BMI history data available yet.</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Wellness Insight Row ── -->
            <div class="dash-row stagger d-4" style="grid-template-columns: 1fr; margin-bottom: 30px;">
                <div class="dash-panel nutri-glass" style="padding: 25px; background: linear-gradient(135deg, rgba(255,255,255,0.4), rgba(255,255,255,0.1)); border: 1.5px solid rgba(255,255,255,0.5); position: relative; overflow: hidden;">
                    <!-- Ambient Glow -->
                    <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: radial-gradient(circle, rgba(129,140,248,0.2) 0%, transparent 70%); pointer-events: none;"></div>
                    
                    <div class="dash-panel-header" style="margin-bottom: 20px;">
                        <h2 class="dash-panel-title" style="display: flex; align-items: center; gap: 10px; color: #1e293b;">
                            <i class="fas fa-heart-pulse" style="color: #818cf8;"></i> 
                            Wellness Pulse
                            <span style="font-size: 0.7rem; background: #818cf8; color: white; padding: 2px 10px; border-radius: 20px; font-weight: 800; text-transform: uppercase; margin-left: 5px;">Personalized Wellness Feed</span>
                        </h2>
                    </div>

                    <div class="wellness-pulse-grid" style="display: grid; grid-template-columns: 1fr 2fr; gap: 25px; align-items: start;">
                        <!-- Left: Resilience Status -->
                        <div style="background: rgba(255,255,255,0.6); padding: 20px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.8); text-align: center;">
                            <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">Emotional Resilience</div>
                            <?php if ($user_ai_data['flag']): ?>
                                <div style="font-size: 2.2rem; font-weight: 800; color: #ef4444;"><?= $user_ai_data['score'] ?>/10</div>
                                <div style="background: #fee2e2; color: #ef4444; font-size: 0.75rem; font-weight: 800; padding: 4px 12px; border-radius: 10px; display: inline-block; margin-top: 5px;">LOW RESILIENCE DETECTED</div>
                                <p style="font-size: 0.8rem; color: #7f1d1d; margin-top: 10px; line-height: 1.4;">Our system detected signals of diet fatigue. We recommend taking a "rest day" and focusing on hydration.</p>
                            <?php else: ?>
                                <div style="font-size: 2.2rem; font-weight: 800; color: #10b981;"><?= $user_ai_data['score'] ?>/10</div>
                                <div style="background: #d1fae5; color: #059669; font-size: 0.75rem; font-weight: 800; padding: 4px 12px; border-radius: 10px; display: inline-block; margin-top: 5px;">STABLE & MOTIVATED</div>
                                <p style="font-size: 0.8rem; color: #064e3b; margin-top: 10px; line-height: 1.4;">Your recent interaction shows strong commitment. Keep up the great momentum!</p>
                            <?php endif; ?>
                        </div>

                        <!-- Right: Nutrient Markers -->
                        <div>
                            <div style="font-size: 0.85rem; font-weight: 700; color: #1e293b; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between;">
                                <span style="display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-stethoscope" style="color: #818cf8;"></i> Nutritional Markers
                                </span>
                                <span style="font-size: 0.65rem; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Linked to 30-day logs</span>
                            </div>
                            
                            <?php if (!empty($user_ai_data['insights'])): ?>
                                <div style="display: grid; grid-template-columns: 1fr; gap: 12px;">
                                    <?php foreach ($user_ai_data['insights'] as $insight): ?>
                                        <div style="background: rgba(255,255,255,0.5); padding: 12px 15px; border-radius: 15px; border: 1px solid <?= $insight['is_verified'] ? 'rgba(239,68,68,0.2)' : 'rgba(255,255,255,0.8)' ?>; display: flex; align-items: center; gap: 15px; position: relative;">
                                            
                                            <?php if ($insight['is_verified']): ?>
                                                <div style="position: absolute; top: -8px; right: 10px; background: #ef4444; color: white; font-size: 0.6rem; padding: 2px 8px; border-radius: 10px; font-weight: 900; box-shadow: 0 4px 10px rgba(239,68,68,0.3);">Verified Debt</div>
                                            <?php endif; ?>

                                            <div style="width: 40px; height: 40px; background: <?= $insight['is_verified'] ? '#fee2e2' : '#e0e7ff' ?>; color: <?= $insight['is_verified'] ? '#ef4444' : '#4338ca' ?>; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">
                                                <i class="fas <?= $insight['is_verified'] ? 'fa-triangle-exclamation' : 'fa-check-circle' ?>"></i>
                                            </div>
                                            <div style="flex: 1;">
                                                <div style="font-size: 0.85rem; font-weight: 800; color: #1e293b;">
                                                    <?= ucfirst($insight['symptom']) ?> 
                                                    <span style="color: #64748b; font-weight: 600; font-size: 0.75rem;">&bull; Linked to <?= $insight['nutrient'] ?></span>
                                                </div>
                                                <div style="font-size: 0.78rem; color: #64748b; margin-top: 2px;">
                                                    <b>Suggested:</b> <?= $insight['foods'] ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div style="background: rgba(0,0,0,0.03); border-radius: 15px; padding: 25px; text-align: center; color: #94a3b8; border: 1px dashed #cbd5e1;">
                                    <i class="fas fa-heartbeat" style="font-size: 1.5rem; margin-bottom: 10px; opacity: 0.5;"></i>
                                    <p style="font-size: 0.85rem; margin: 0;">No significant symptom markers detected. Your nutritional balance looks solid based on your recent activity!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Details Row ── -->
            <div class="dash-row stagger d-4" style="grid-template-columns: 2fr 1fr;">
                <div class="dash-panel nutri-glass">
                    <div class="dash-panel-header">
                        <h2 class="dash-panel-title" style="display: flex; align-items: center; gap: 8px;"><i class="fas fa-utensils"></i> Recommended Plan <info-button feature="recommended_plan" role="<?php echo $_SESSION['role'] ?? 'regular'; ?>"></info-button></h2>
                        <button class="btn btn-primary" onclick="location.href='user-health-tracker.php'" style="font-size: 0.8rem; border-radius: 10px;">Track Food</button>
                    </div>
                    <div class="meal-plans-list" id="recommendedPlansList">
                        <?php if (count($recommended_plans) > 0): ?>
                            <?php foreach ($recommended_plans as $plan): ?>
                                <div class="meal-plan-card" style="padding: 20px; border: none; margin-bottom: 15px; background: rgba(0,0,0,0.02); border-radius: 15px;">
                                    <div class="d-flex justify-content-between align-items-center" style="margin-bottom: 10px;">
                                        <h3 style="font-family: 'Outfit'; font-weight: 700; margin: 0; font-size: 1.1rem;"><?php echo htmlspecialchars($plan['plan_name']); ?></h3>
                                        <span style="background: var(--primary); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700;"><?php echo $plan['calories']; ?> kcal</span>
                                    </div>
                                    <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 12px;"><i class="fas fa-user-md" style="margin-right: 5px;"></i> Dr. <?php echo htmlspecialchars($plan['staff_name']); ?></div>
                                    <p style="font-size: 0.9rem; line-height: 1.5; color: #1e293b;"><?php echo htmlspecialchars(substr($plan['description'], 0, 120)) . '...'; ?></p>
                                    <div class="d-flex justify-content-between align-items-center" style="margin-top: 15px; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 15px;">
                                        <span style="font-size: 0.75rem; color: #94a3b8;">Created: <?php echo date('M j, Y', strtotime($plan['created_at'])); ?></span>
                                        <button class="btn btn-outline" style="border-radius: 8px; font-size: 0.8rem; padding: 6px 12px;" onclick="viewMealPlan(<?php echo $plan['id']; ?>)">View Full Plan</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: #94a3b8;">
                                <i class="fas fa-utensils" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i>
                                <p>Waiting for dietitian recommendations.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dash-panel nutri-glass">
                    <div class="dash-panel-header">
                        <h2 class="dash-panel-title" style="display: flex; align-items: center; gap: 8px;"><i class="fas fa-comment-dots"></i> Interactions <info-button feature="interactions" role="<?php echo $_SESSION['role'] ?? 'regular'; ?>"></info-button></h2>
                        <button class="btn btn-outline" style="font-size: 0.8rem; border-radius: 10px;" onclick="location.href='user-messages.php'">Chat</button>
                    </div>
                    <div class="activity-feed" id="messagesFeedContainer">
                        <?php if (count($messages) > 0): ?>
                            <?php foreach ($messages as $message): ?>
                                <div class="activity-item nutri-glass" style="margin-bottom: 12px; border: none; padding: 15px; cursor: pointer; transition: all 0.2s ease; background: rgba(0,0,0,0.015);" onclick="location.href='user-messages.php'">
                                    <div class="activity-icon" style="background: rgba(79, 172, 254, 0.1); color: #4facfe; width: 32px; height: 32px; min-width: 32px; font-size: 0.8rem;">
                                        <?php echo strtoupper(substr($message['staff_name'], 0, 1)); ?>
                                    </div>
                                    <div class="activity-details" style="margin-left: 12px;">
                                        <div style="font-weight: 700; font-size: 0.85rem; color: #1e293b;"><?php echo htmlspecialchars($message['staff_name']); ?></div>
                                        <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;"><?php echo htmlspecialchars(substr($message['message'], 0, 40)) . '...'; ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: #94a3b8;">
                                <i class="fas fa-comments" style="font-size: 1.5rem; margin-bottom: 8px; opacity: 0.4;"></i>
                                <p style="font-size: 0.8rem;">No recent chat.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>



                    <style>
                        /* Enhanced User Dashboard Styles */
                        .user-grid .stat-card {
                            cursor: pointer;
                            transition: transform 0.3s ease, box-shadow 0.3s ease;
                        }

                        .user-grid .stat-card:hover {
                            transform: translateY(-5px);
                            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
                        }

                        .meal-plans-list {
                            display: flex;
                            flex-direction: column;
                            gap: 15px;
                        }

                        .meal-plan-card {
                            background: white;
                            border-radius: 15px;
                            padding: 20px;
                            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
                            border-left: 4px solid var(--primary);
                            transition: transform 0.3s ease;
                        }

                        .meal-plan-card:hover {
                            transform: translateY(-2px);
                            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
                        }

                        .meal-plan-header {
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            margin-bottom: 10px;
                        }

                        .meal-plan-title {
                            font-weight: 600;
                            color: var(--dark);
                            font-size: 1.1rem;
                        }

                        .meal-plan-calories {
                            background: var(--gradient);
                            color: white;
                            padding: 4px 12px;
                            border-radius: 20px;
                            font-size: 0.8rem;
                            font-weight: 600;
                        }

                        .meal-plan-staff {
                            font-size: 0.9rem;
                            color: var(--gray);
                            margin-bottom: 10px;
                            display: flex;
                            align-items: center;
                            gap: 5px;
                        }

                        .meal-plan-description {
                            font-size: 0.9rem;
                            color: var(--dark);
                            line-height: 1.4;
                            margin-bottom: 15px;
                        }

                        .meal-plan-footer {
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                        }

                        .meal-plan-date {
                            font-size: 0.8rem;
                            color: var(--gray);
                        }

                        .view-plan-btn {
                            padding: 6px 15px;
                            font-size: 0.8rem;
                        }

                        .quick-actions-grid {
                            display: grid;
                            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                            gap: 20px;
                            margin-top: 20px;
                        }

                        .quick-action-card {
                            background: white;
                            border-radius: 15px;
                            padding: 25px;
                            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
                            cursor: pointer;
                            transition: all 0.3s ease;
                            border: 2px solid transparent;
                        }

                        .quick-action-card:hover {
                            transform: translateY(-5px);
                            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
                            border-color: var(--primary);
                        }

                        .quick-action-icon {
                            width: 60px;
                            height: 60px;
                            border-radius: 15px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin-bottom: 15px;
                            font-size: 1.5rem;
                            color: white;
                        }

                        .quick-action-icon.calculator {
                            background: linear-gradient(135deg, #667eea, #764ba2);
                        }

                        .quick-action-icon.exchange {
                            background: linear-gradient(135deg, #f093fb, #f5576c);
                        }

                        .quick-action-icon.messages {
                            background: linear-gradient(135deg, #4facfe, #00f2fe);
                        }

                        .quick-action-icon.appointments {
                            background: linear-gradient(135deg, #43e97b, #38f9d7);
                        }

                        .quick-action-icon.dietary {
                            background: linear-gradient(135deg, #11998e, #38ef7d);
                        }

                        .quick-action-content h3 {
                            font-size: 1.1rem;
                            margin-bottom: 8px;
                            color: var(--dark);
                        }

                        .quick-action-content p {
                            font-size: 0.85rem;
                            color: var(--gray);
                            line-height: 1.4;
                        }

                        .two-column-layout {
                            display: grid;
                            grid-template-columns: 1fr 1fr;
                            gap: 25px;
                            margin-bottom: 30px;
                        }

                        .column {
                            display: flex;
                            flex-direction: column;
                        }

                        /* Fix button sizes in user dashboard */
                        .user-dashboard .btn,
                        .user-dashboard .btn-primary,
                        .user-dashboard .btn-outline {
                            padding: 8px 16px;
                            font-size: 0.85rem;
                            height: auto;
                            min-height: 36px;
                        }

                        .user-dashboard .btn-primary {
                            padding: 8px 16px;
                            font-size: 0.85rem;
                        }

                        .user-dashboard .btn-outline {
                            padding: 6px 12px;
                            font-size: 0.8rem;
                        }

                        .user-dashboard .action-btn {
                            width: 32px;
                            height: 32px;
                            padding: 0;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        }

                        .user-dashboard .action-btn i {
                            font-size: 0.8rem;
                        }

                        /* Fix the "View Details" buttons in meal plans */
                        .user-dashboard .view-plan-btn {
                            padding: 6px 12px;
                            font-size: 0.8rem;
                            min-width: auto;
                        }

                        /* Fix buttons in no-data messages */
                        .user-dashboard .no-data-message .btn {
                            padding: 8px 16px;
                            font-size: 0.85rem;
                            margin-top: 10px;
                        }

                        /* Fix section header buttons */
                        .user-dashboard .section-header .btn {
                            padding: 8px 16px;
                            font-size: 0.85rem;
                            white-space: nowrap;
                        }

                        /* Make sure quick action cards have proper button sizing */
                        .user-dashboard .quick-action-card {
                            padding: 20px;
                        }

                        .user-dashboard .quick-action-icon {
                            width: 50px;
                            height: 50px;
                            font-size: 1.2rem;
                        }

                        .user-dashboard .quick-action-content h3 {
                            font-size: 1rem;
                            margin-bottom: 6px;
                        }

                        .user-dashboard .quick-action-content p {
                            font-size: 0.8rem;
                            line-height: 1.3;
                        }

                        @media (max-width: 768px) {
                            .two-column-layout {
                                grid-template-columns: 1fr;
                            }

                            .quick-actions-grid {
                                grid-template-columns: 1fr;
                            }
                        }
                    </style>
                <?php endif; ?>

            </div>

            <!-- Add Food Dialog Modal (Admin) -->
            <?php if ($user_role === 'admin'): ?>
                <div class="dialog-modal" id="foodDialog">
                    <div class="dialog-content">
                        <div class="dialog-header">
                            <h3>Add Nutritional Food</h3>
                        </div>
                        <div class="dialog-body">
                            <p>Select the food you'd like to add to your nutrition plan:</p>
                            <div class="food-options">
                                <div class="food-option">
                                    <input type="checkbox" id="food1">
                                    <label for="food1">Fresh Fruits Basket</label>
                                </div>
                                <div class="food-option">
                                    <input type="checkbox" id="food2">
                                    <label for="food2">Vegetable Medley</label>
                                </div>
                                <div class="food-option">
                                    <input type="checkbox" id="food3">
                                    <label for="food3">Lean Protein Pack</label>
                                </div>
                            </div>
                        </div>
                        <div class="dialog-footer">
                            <button class="btn btn-outline" id="cancelDialog">Cancel</button>
                            <button class="btn btn-primary" id="confirmDialog">Add Selected</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <script>
        // Initialize visuals on load
        document.addEventListener('DOMContentLoaded', () => {
            const userBmi = <?php echo floatval($user_bmi ?: 0); ?>;
            
            // Initialize Chart.js for User BMI History (if applicable)
            const userBmiHistoryData = <?php echo @json_encode($bmi_history_data ?? []); ?>;
            const userBmiCtx = document.getElementById('bmiHistoryChart');
            window.userBmiChart = null;
            
            if (userBmiCtx && userBmiHistoryData.length > 0) {
                const labels = userBmiHistoryData.map(item => item.date_label);
                const data = userBmiHistoryData.map(item => parseFloat(item.bmi));
                
                window.userBmiChart = new Chart(userBmiCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'BMI',
                            data: data,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 3,
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#10b981',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                backgroundColor: '#1e293b',
                                padding: 12,
                                titleFont: { family: 'Outfit', size: 13, weight: '700' },
                                bodyFont: { family: 'Inter', size: 12 },
                                cornerRadius: 10,
                                callbacks: {
                                    label: (context) => ' BMI: ' + context.parsed.y
                                }
                            }
                        },
                        scales: {
                            y: {
                                suggestedMin: 15,
                                suggestedMax: 35,
                                grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false },
                                ticks: { font: { family: 'Inter', size: 11, weight: '600' }, color: '#94a3b8' }
                            },
                            x: { 
                                grid: { display: false },
                                ticks: { font: { family: 'Inter', size: 11, weight: '600' }, color: '#94a3b8' }
                            }
                        }
                    }
                });
            }
            
            // Initialize Chart.js for Staff BMI History (if applicable)
            const staffBmiHistoryData = <?php echo @json_encode($staff_bmi_history ?? []); ?>;
            const staffBmiCtx = document.getElementById('staffBmiHistoryChart');
            
            if (staffBmiCtx && staffBmiHistoryData.length > 0) {
                const labels = staffBmiHistoryData.map(item => item.date_label);
                const data = staffBmiHistoryData.map(item => parseFloat(item.bmi));
                
                new Chart(staffBmiCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'BMI',
                            data: data,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 3,
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#10b981',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                backgroundColor: '#1e293b',
                                padding: 12,
                                titleFont: { family: 'Outfit', size: 13, weight: '700' },
                                bodyFont: { family: 'Inter', size: 12 },
                                cornerRadius: 10,
                                callbacks: {
                                    label: (context) => ' BMI: ' + context.parsed.y
                                }
                            }
                        },
                        scales: {
                            y: {
                                suggestedMin: 15,
                                suggestedMax: 35,
                                grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false },
                                ticks: { font: { family: 'Inter', size: 11, weight: '600' }, color: '#94a3b8' }
                            },
                            x: { 
                                grid: { display: false },
                                ticks: { font: { family: 'Inter', size: 11, weight: '600' }, color: '#94a3b8' }
                            }
                        }
                    }
                });
            }
        });
    </script>

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

                    // Open logout modal
                    if (logoutBtn && logoutModal) {
                        logoutBtn.addEventListener('click', function (e) {
                            e.preventDefault();
                            logoutModal.classList.add('active');
                        });
                    }

                    // Cancel logout
                    if (cancelLogout && logoutModal) {
                        cancelLogout.addEventListener('click', function () {
                            logoutModal.classList.remove('active');
                        });
                    }

                    // Confirm logout
                    if (confirmLogout) {
                        confirmLogout.addEventListener('click', function () {
                            window.location.href = 'login-logout/logout.php';
                        });
                    }

                    // Close modal when clicking outside
                    if (logoutModal) {
                        logoutModal.addEventListener('click', function (e) {
                            if (e.target === logoutModal) {
                                logoutModal.classList.remove('active');
                            }
                        });
                    }

                    // Close modal with Escape key
                    document.addEventListener('keydown', function (e) {
                        if (e.key === 'Escape' && logoutModal && logoutModal.classList.contains('active')) {
                            logoutModal.classList.remove('active');
                        }
                    });
                });
            </script>

            <!-- Realtime Support & Reports -->
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // 1. Staff Weekly Chart (Health Logs)
                    const weeklyCtx = document.getElementById('staffWeeklyChart');
                    if (weeklyCtx) {
                        new Chart(weeklyCtx, {
                            type: 'line',
                            data: {
                                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                                datasets: [{
                                    label: 'Health Logs',
                                    data: [12, 19, 15, 25, 22, 30, 28],
                                    borderColor: '#10b981',
                                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                    fill: true,
                                    tension: 0.4,
                                    borderWidth: 3,
                                    pointRadius: 4,
                                    pointBackgroundColor: '#fff',
                                    pointBorderColor: '#10b981'
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false } },
                                scales: {
                                    y: { beginAtZero: true, grid: { display: false } },
                                    x: { grid: { display: false } }
                                }
                            }
                        });
                    }

                    // 2. Admin Efficiency Chart
                    const effCtx = document.getElementById('efficiencyDoughnutChart');
                    if (effCtx) {
                        window.efficiencyChart = new Chart(effCtx, {
                            type: 'doughnut',
                            data: {
                                datasets: [{
                                    data: [85, 15],
                                    backgroundColor: ['#10b981', 'rgba(0,0,0,0.05)'],
                                    borderWidth: 0,
                                    borderRadius: 10
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

                    // 3. Client Macro Initialization
                    <?php if ($user_role === 'client'): ?>
                    setTimeout(() => {
                        updateMacroRings(<?php echo $protein_today; ?>, <?php echo $carbs_today; ?>, <?php echo $fats_today; ?>); 
                    }, 500);

                    function updateMacroRings(p, c, f) {
                        const setRing = (id, val) => {
                            const bar = document.getElementById(id);
                            if (bar) {
                                // Dynamic calculation based on a default 100g max for visualization 
                                const offset = 213.6 - (Math.min(val, 100) / 100 * 213.6);
                                bar.style.strokeDashoffset = offset;
                            }
                        };
                        setRing('p_bar', p);
                        setRing('c_bar', c);
                        setRing('f_bar', f);
                    }
                    <?php endif; ?>
                });
            </script>
            <script src="scripts/dashboard.js"></script>

            <script>
                if ('serviceWorker' in navigator) {
                    window.addEventListener('load', () => {
                        navigator.serviceWorker.register('service-worker.js?v=3').catch(() => {});
                    });
                }
            </script>
        </div> <!-- Close .page-container -->

        <!-- Real-time Synchronization Engines -->
        <?php if ($user_role === 'staff'): ?>
            <script src="scripts/staff-realtime.js"></script>
        <?php elseif ($user_role === 'regular'): ?>
            <script src="scripts/user-realtime.js"></script>
        <?php endif; ?>
    </main>
</div> <!-- Close .main-layout -->

</body>
</html>
