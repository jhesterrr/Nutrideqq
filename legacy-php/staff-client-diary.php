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
require_once 'database.php';
require_once 'navigation.php';

// Check staff/admin login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['user_role'] !== 'staff' && $_SESSION['user_role'] !== 'admin')) {
    header("Location: login-logout/NutriDeqN-Login.php");
    exit();
}

$staff_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['user_role'] === 'admin');
$db = new Database();
$conn = $db->getConnection();

// Fetch logged-in staff/admin name for report signature
$staff_name_row = $conn->prepare("SELECT name FROM users WHERE id = ?");
$staff_name_row->execute([$staff_id]);
$staff_row = $staff_name_row->fetch(PDO::FETCH_ASSOC);
$logged_in_staff_name = $staff_row['name'] ?? $_SESSION['name'] ?? 'Licensed Practitioner';

// Fetch Clients
$clients = [];
try {
    $client_check = $is_admin ? "" : "WHERE staff_id = ?";
    $sql = "SELECT id, name, email FROM clients $client_check ORDER BY name ASC";
    $stmt = $conn->prepare($sql);
    $params = $is_admin ? [] : [$staff_id];
    $stmt->execute($params);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$selected_client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : null;
$selected_client = null;
if ($selected_client_id) {
    foreach ($clients as $c) {
        if ($c['id'] == $selected_client_id) {
            $selected_client = $c;
            break;
        }
    }
}

// Date selection
$selected_date = $_GET['date'] ?? date('Y-m-d');
$today = date('Y-m-d');

$logs = [];
$totals = ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0];
$grouped_logs = [
    'Breakfast' => [],
    'AM Snack' => [],
    'Lunch' => [],
    'PM Snack' => [],
    'Dinner' => [],
    'Snack' => [] // legacy
];

if ($selected_client) {
    // Get the actual user_id for the client
    $user_sql = "SELECT user_id FROM clients WHERE id = ?";
    $u_stmt = $conn->prepare($user_sql);
    $u_stmt->execute([$selected_client_id]);
    $u_row = $u_stmt->fetch();
    $client_user_id = $u_row['user_id'] ?? null;

    if ($client_user_id) {
        $log_sql = "SELECT * FROM food_logs WHERE user_id = :user_id AND log_date = :date ORDER BY created_at ASC";
        $l_stmt = $conn->prepare($log_sql);
        $l_stmt->execute([':user_id' => $client_user_id, ':date' => $selected_date]);
        $logs = $l_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($logs as $log) {
            if (isset($grouped_logs[$log['meal_type']])) {
                $grouped_logs[$log['meal_type']][] = $log;
            }
            $totals['calories'] += (float)$log['calories'];
            $totals['protein'] += (float)$log['protein'];
            $totals['carbs'] += (float)$log['carbs'];
            $totals['fat'] += (float)$log['fat'];
        }
    }
}

function getInitials($name) {
    $names = explode(' ', $name);
    $initials = '';
    foreach ($names as $n) { if ($n) $initials .= strtoupper($n[0]); }
    return substr($initials, 0, 2);
}

// Fetch existing report if any
try {
    $report_stmt = $conn->prepare("SELECT * FROM clinical_reports WHERE user_id = ? AND log_date = ?");
    $report_stmt->execute([$client_user_id ?? 0, $selected_date]);
    $saved_report = $report_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If the table doesn't exist, create it and retry
    if ($e->getCode() == '42S02') {
        $conn->exec("CREATE TABLE IF NOT EXISTS clinical_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            staff_id INT NOT NULL,
            log_date DATE NOT NULL,
            dietician_name VARCHAR(255),
            patient_name VARCHAR(255),
            report_id VARCHAR(50),
            report_content LONGTEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id),
            INDEX (staff_id),
            INDEX (log_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        $report_stmt = $conn->prepare("SELECT * FROM clinical_reports WHERE user_id = ? AND log_date = ?");
        $report_stmt->execute([$client_user_id ?? 0, $selected_date]);
        $saved_report = $report_stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        throw $e;
    }
}

$nav_links = getNavigationLinks($_SESSION['user_role'], 'staff-client-diary.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <script src="scripts/theme-toggle.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Diary Monitor | NutriDeq</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/modern-messages.css">
    <link rel="stylesheet" href="css/responsive.css">
    <!-- Platform Specific Styles -->
    <link rel="stylesheet" href="css/desktop-style.css" media="all and (min-width: 1025px)">
    <link rel="stylesheet" href="css/mobile-style.css" media="all and (max-width: 1024px)">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="stylesheet" href="css/dashboard-premium.css">
    <style>
        :root {
            --bounce: cubic-bezier(0.34, 1.56, 0.64, 1);
            --smooth: cubic-bezier(0.4, 0, 0.2, 1);
        }

        .dash-premium { background: transparent !important; }
        
        /* Global Animation Overrides */
        * { transition: all 0.3s var(--smooth); }
        
        /* Bouncy Entry Animations */
        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .client-list-sidebar { animation: fadeIn 0.8s var(--smooth); }
        .monitor-content { animation: slideInUp 0.6s var(--bounce); }
        .bento-stat { animation: slideInUp 0.8s var(--bounce) backwards; }
        .bento-stat:nth-child(1) { animation-delay: 0.1s; }
        .bento-stat:nth-child(2) { animation-delay: 0.2s; }
        .bento-stat:nth-child(3) { animation-delay: 0.3s; }
        .bento-stat:nth-child(4) { animation-delay: 0.4s; }

        /* Premium Client Search Pill */
        .sidebar-search {
            padding: 12px 24px 20px 24px !important;
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
        }
        .search-pill-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }
        .search-pill-wrapper i.search-icon {
            position: absolute;
            left: 18px;
            color: #94a3b8;
            font-size: 0.95rem;
            transition: all 0.3s var(--smooth);
            pointer-events: none;
            z-index: 10;
        }
        .client-search-pill {
            width: 100% !important;
            padding: 12px 16px 12px 46px !important;
            border-radius: 99px !important;
            border: 1.5px solid #e2e8f0 !important;
            background: rgba(255, 255, 255, 0.75) !important;
            backdrop-filter: blur(8px) !important;
            -webkit-backdrop-filter: blur(8px) !important;
            font-size: 0.88rem !important;
            font-family: 'Outfit', sans-serif !important;
            font-weight: 500 !important;
            color: #1e293b !important;
            outline: none !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02) !important;
            transition: all 0.4s var(--bounce) !important;
        }
        .client-search-pill::placeholder {
            color: #94a3b8;
            font-weight: 400;
        }
        .client-search-pill:hover {
            border-color: #cbd5e1 !important;
            background: #ffffff !important;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.04) !important;
            transform: translateY(-1px);
        }
        .client-search-pill:focus {
            border-color: #10b981 !important;
            background: #ffffff !important;
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.12) !important;
            transform: translateY(-2px) scale(1.01);
        }
        .search-pill-wrapper:focus-within i.search-icon {
            color: #10b981 !important;
            transform: scale(1.1);
        }

        /* Enhanced Patient Directory Items */
        .client-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 20px !important;
            margin: 6px 16px !important;
            border-radius: 18px !important;
            cursor: pointer;
            border-left: 4px solid transparent !important;
            background: transparent;
            transition: all 0.4s var(--bounce) !important;
        }
        .client-item:hover {
            background: rgba(255, 255, 255, 0.8) !important;
            transform: translateY(-2px) translateX(4px) !important;
            border-left-color: rgba(16, 185, 129, 0.3) !important;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.02) !important;
        }
        .client-item.active {
            background: #ffffff !important;
            border-left: 4px solid #10b981 !important;
            transform: translateY(-2px) translateX(4px) !important;
            box-shadow: 
                0 15px 35px -5px rgba(16, 185, 129, 0.08),
                0 4px 12px -5px rgba(16, 185, 129, 0.03) !important;
        }

        .circle-avatar {
            width: 44px !important;
            height: 44px !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-family: 'Outfit', sans-serif !important;
            font-weight: 700 !important;
            font-size: 0.95rem !important;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%) !important;
            border: 2px solid #ffffff !important;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05) !important;
            color: #475569 !important;
            transition: all 0.4s var(--bounce) !important;
            flex-shrink: 0;
        }
        .client-item:hover .circle-avatar {
            transform: scale(1.06) rotate(3deg);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.15) !important;
        }
        .client-item.active .circle-avatar {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.8) !important;
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.25) !important;
        }

        /* Clinical Status Alert Badges */
        .client-alert-badge {
            font-family: 'Outfit', sans-serif !important;
            font-size: 0.65rem !important;
            font-weight: 750 !important;
            padding: 3px 8px !important;
            border-radius: 8px !important;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid rgba(239, 68, 68, 0.2) !important;
            background: rgba(239, 68, 68, 0.08) !important;
            color: #ef4444 !important;
        }

        /* Bento Stat Hover */
        .bento-stat {
            transition: all 0.5s var(--bounce) !important;
        }
        .bento-stat:hover {
            transform: translateY(-12px) scale(1.05);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
        }

        /* Modernized Meal Rows */
        .meal-row {
            background: rgba(255, 255, 255, 0.8) !important;
            border: 1px solid rgba(0, 0, 0, 0.05) !important;
            border-radius: 24px !important;
            padding: 24px 28px !important;
            margin-bottom: 20px !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.01) !important;
            transition: all 0.4s var(--bounce) !important;
        }
        .meal-row:hover {
            transform: translateY(-4px) scale(1.01) !important;
            background: #ffffff !important;
            box-shadow: 0 15px 35px rgba(99, 102, 241, 0.05) !important;
            border-color: rgba(99, 102, 241, 0.25) !important;
        }

        /* Medical Grade Tactile Pill Inputs */
        .macro-edit-input {
            width: 58px !important;
            padding: 6px 4px !important;
            border: 1.5px solid #e2e8f0 !important;
            border-radius: 10px !important;
            font-size: 0.85rem !important;
            font-family: 'Inter', sans-serif !important;
            font-weight: 600 !important;
            color: #334155 !important;
            text-align: center !important;
            background: #ffffff !important;
            outline: none !important;
            transition: all 0.3s var(--bounce) !important;
        }
        .macro-edit-input:hover {
            border-color: #cbd5e1 !important;
            background: #f8fafc !important;
        }
        .macro-edit-input:focus {
            border-color: #10b981 !important;
            background: #ffffff !important;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15) !important;
            transform: scale(1.08) !important;
        }
        
        /* Premium Card Rows for Food Entries */
        .food-entry-row {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 20px !important;
            padding: 16px 20px !important;
            margin-bottom: 14px !important;
            background: rgba(255, 255, 255, 0.5) !important;
            backdrop-filter: blur(12px) saturate(180%) !important;
            -webkit-backdrop-filter: blur(12px) saturate(180%) !important;
            border: 1.5px solid rgba(255, 255, 255, 0.6) !important;
            border-radius: 18px !important;
            box-shadow: 
                0 10px 25px -8px rgba(15, 23, 42, 0.05),
                0 4px 10px -4px rgba(15, 23, 42, 0.02) !important;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
        }

        .food-entry-row:hover {
            transform: translateY(-3px) !important;
            background: #ffffff !important;
            border-color: rgba(16, 185, 129, 0.25) !important;
            box-shadow: 
                0 20px 35px -12px rgba(15, 23, 42, 0.12),
                0 8px 18px -6px rgba(16, 185, 129, 0.06) !important;
        }

        /* Missing Macro Alert Card State */
        .food-entry-row.missing-macros {
            background: rgba(254, 243, 199, 0.4) !important;
            border-left: 5px solid #f59e0b !important;
            border-color: rgba(245, 158, 11, 0.25) !important;
            box-shadow: 
                0 10px 25px -8px rgba(245, 158, 11, 0.08),
                inset 0 0 12px rgba(245, 158, 11, 0.02) !important;
        }
        
        .food-entry-row.missing-macros:hover {
            background: rgba(254, 243, 199, 0.6) !important;
            border-color: rgba(245, 158, 11, 0.4) !important;
            box-shadow: 
                0 20px 35px -12px rgba(245, 158, 11, 0.15) !important;
        }

        /* Needs Review Badge - High Contrast Amber Pulse Gradient */
        .needs-review-badge {
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            font-size: 0.68rem !important;
            font-weight: 800 !important;
            letter-spacing: 0.03em !important;
            text-transform: uppercase !important;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
            color: #ffffff !important;
            padding: 4px 10px !important;
            border-radius: 50px !important;
            margin-left: 8px !important;
            vertical-align: middle !important;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.25) !important;
            animation: pulseScale 2s infinite ease-in-out !important;
        }

        @keyframes pulseScale {
            0% { transform: scale(1); box-shadow: 0 4px 12px rgba(245, 158, 11, 0.25); }
            50% { transform: scale(1.05); box-shadow: 0 6px 18px rgba(245, 158, 11, 0.4); }
            100% { transform: scale(1); box-shadow: 0 4px 12px rgba(245, 158, 11, 0.25); }
        }
        
        /* Save action round button */
        .btn-save-macro {
            width: 32px !important;
            height: 32px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            background: #10b981 !important;
            color: white !important;
            border: none !important;
            border-radius: 50% !important;
            cursor: pointer !important;
            font-size: 0.82rem !important;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2) !important;
            transition: all 0.3s var(--bounce) !important;
        }
        .btn-save-macro:hover {
            background: #059669 !important;
            transform: scale(1.1) translateY(-1px);
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.3) !important;
        }
        .btn-save-macro:active {
            transform: scale(0.95) translateY(0);
        }

        .premium-clinical-btn {
            transition: all 0.4s var(--bounce) !important;
            overflow: hidden;
            position: relative;
        }
        .premium-clinical-btn:hover {
            transform: translateY(-4px) scale(1.05);
            background: #10b981 !important;
            color: white !important;
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }
        .premium-clinical-btn:active {
            transform: translateY(0) scale(0.95);
        }

        /* Glassmorphism Polish */
        .client-list-sidebar {
            backdrop-filter: blur(25px) saturate(180%) !important;
            -webkit-backdrop-filter: blur(25px) saturate(180%) !important;
            background: rgba(255, 255, 255, 0.7) !important;
            border: 1px solid rgba(255, 255, 255, 0.4) !important;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07) !important;
            border-radius: 28px !important;
        }

        .diary-brief {
            background: rgba(255, 255, 255, 0.75) !important;
            backdrop-filter: blur(28px) saturate(180%) !important;
            -webkit-backdrop-filter: blur(28px) saturate(180%) !important;
            border: 1px solid rgba(255, 255, 255, 0.5) !important;
            border-radius: 28px !important;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.04) !important;
            padding: 32px !important;
        }

        /* Scrolling Polish */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb {
            background: rgba(16, 185, 129, 0.2);
            border-radius: 10px;
            transition: background 0.3s;
        }
        ::-webkit-scrollbar-thumb:hover { background: rgba(16, 185, 129, 0.4); }

        /* Report Preview Animation */
        .report-preview-panel {
            transition: all 0.6s var(--bounce) !important;
            opacity: 0;
            visibility: hidden;
            transform: translate(-50%, -40%) scale(0.8);
        }
        .report-preview-panel.active {
            opacity: 1;
            visibility: visible;
            transform: translate(-50%, -50%) scale(1);
        }

        /* Date Picker Capsule */
        .date-picker-nav {
            display: inline-flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.8) !important;
            border: 1.5px solid #e2e8f0 !important;
            border-radius: 20px !important;
            padding: 6px 14px !important;
            gap: 16px !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02) !important;
            transition: all 0.3s var(--bounce) !important;
        }
        .date-picker-nav:hover {
            border-color: #cbd5e1 !important;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.04) !important;
        }
        .date-picker-nav a {
            width: 32px !important;
            height: 32px !important;
            border-radius: 50% !important;
            background: transparent !important;
            color: #64748b !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            transition: all 0.3s var(--bounce) !important;
        }
        .date-picker-nav a:hover {
            background: rgba(16, 185, 129, 0.1) !important;
            color: #10b981 !important;
            transform: scale(1.1) !important;
        }
        .date-picker-nav span {
            font-family: 'Outfit', sans-serif !important;
            font-size: 0.95rem !important;
            font-weight: 700 !important;
            color: #1e293b !important;
        }

        .split-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            height: calc(100vh - 100px);
            gap: 24px;
            position: relative;
            z-index: 10;
            margin-top: 20px;
        }
        .client-list-sidebar {
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .client-items {
            flex: 1;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 20px;
        }
        .monitor-content {
            display: flex;
            flex-direction: column;
            gap: 24px;
            overflow-y: auto;
            padding-right: 10px;
            background: transparent !important;
        }
        .diary-brief {
            border-radius: 32px;
            padding: 32px;
        }
        .meal-row {
            background: var(--bg-surface);
            border-radius: 16px;
            padding: 14px 24px;
            margin-bottom: 12px;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .meal-row h4 { width: 120px; font-weight: 700; color: #1e293b; margin: 0; }
        
        .report-preview-panel {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.9);
            width: 900px;
            max-width: 95vw;
            height: 90vh;
            background: white;
            border-radius: 32px;
            z-index: 10000;
            display: none;
            flex-direction: column;
            box-shadow: 0 50px 100px -20px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .report-preview-panel.active { display: flex; transform: translate(-50%, -50%) scale(1); }

        /* Clinical Report Core Styles */
        .clinical-report-container {
            width: 100%;
            max-width: 820px;
            margin: 0 auto;
            font-family: 'Inter', sans-serif;
            background: white;
            border-radius: 20px;
            padding: 48px;
            box-sizing: border-box;
        }

        .report-header-grid {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 36px;
            border-bottom: 3px solid #10b981;
            padding-bottom: 24px;
            gap: 20px;
        }

        .report-info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 36px;
            background: #f8fafc;
            border-radius: 14px;
            padding: 24px;
        }

        .macro-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 36px;
        }

        .signature-block-grid {
            margin-top: 48px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        @media screen and (max-width: 768px) {
            .report-preview-panel {
                width: 100% !important;
                height: 100% !important;
                max-width: 100vw !important;
                max-height: 100vh !important;
                top: 0 !important;
                left: 0 !important;
                transform: none !important;
                border-radius: 0 !important;
            }
            .report-preview-panel.active { transform: none !important; }

            .preview-body-container {
                padding: 8px !important;
                background: #f1f5f9 !important;
                overflow-x: hidden !important;
            }

            .clinical-report-container {
                padding: 12px !important;
                border-radius: 12px !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                box-shadow: none !important;
                overflow-x: hidden !important;
            }

            .report-header-grid {
                flex-direction: column !important;
                align-items: center !important;
                text-align: center !important;
                gap: 12px !important;
                padding-bottom: 12px !important;
                margin-bottom: 15px !important;
            }
            .report-header-grid > div { 
                width: 100% !important; 
                text-align: center !important; 
            }
            .report-header-grid div[style*="text-align: right"] { 
                text-align: center !important; 
            }

            .report-info-grid {
                display: block !important;
                padding: 12px !important;
                background: #f8fafc !important;
                border-radius: 12px !important;
                margin-bottom: 15px !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }
            .report-info-grid > div {
                width: 100% !important;
                text-align: left !important;
                margin-bottom: 12px !important;
                display: block !important;
            }
            .report-info-grid > div:last-child { margin-bottom: 0 !important; }
            .report-info-grid p, .report-info-grid input {
                width: 100% !important;
                box-sizing: border-box !important;
                font-size: 0.85rem !important;
            }

            .macro-summary-grid {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 8px !important;
                margin-bottom: 20px !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }
            .macro-summary-grid > div {
                width: 100% !important;
                margin: 0 !important;
                padding: 10px 4px !important;
                min-width: 0 !important;
                box-sizing: border-box !important;
            }
            .macro-summary-grid p {
                font-size: 0.6rem !important;
                white-space: normal !important;
                line-height: 1.2 !important;
                overflow: visible !important;
                text-overflow: clip !important;
            }
            .macro-summary-grid p[style*="font-weight: 900"] {
                font-size: 0.95rem !important;
            }

            .signature-block-grid {
                grid-template-columns: 1fr !important;
                gap: 20px !important;
                margin-top: 25px !important;
            }

            .table-responsive-report {
                margin: 0 -4px !important;
                width: calc(100% + 8px) !important;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .preview-action-footer {
                flex-direction: column !important;
                gap: 6px !important;
                padding: 10px !important;
            }
            .preview-action-footer button { 
                width: 100% !important; 
                margin: 0 !important; 
                padding: 10px !important;
                font-size: 0.85rem !important;
            }
        }

        .bento-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin: 24px 0;
        }

        /* Mobile Responsive Overrides */
        @media screen and (max-width: 1024px) {
            .split-layout {
                grid-template-columns: 1fr !important;
                height: auto !important;
                overflow: visible !important;
                gap: 16px !important;
            }
            .client-list-sidebar {
                max-height: none !important;
                height: auto !important;
                margin-bottom: 8px !important;
                border-radius: 20px !important;
            }
            .client-items {
                display: flex !important;
                flex-direction: row !important;
                flex-wrap: nowrap !important;
                overflow-x: auto !important;
                overflow-y: hidden !important;
                gap: 12px !important;
                padding: 12px 16px !important;
                -webkit-overflow-scrolling: touch;
            }
            .client-item {
                flex: 0 0 auto !important;
                margin: 0 !important;
                padding: 8px 16px !important;
                border-radius: 14px !important;
                border-left: none !important;
                border-bottom: 3px solid transparent !important;
                background: rgba(255, 255, 255, 0.45) !important;
                border: 1px solid rgba(0, 0, 0, 0.03) !important;
                gap: 10px !important;
            }
            .client-item.active {
                border-bottom: 3px solid #10b981 !important;
                background: #ffffff !important;
                box-shadow: 0 8px 20px rgba(16, 185, 129, 0.08) !important;
            }
            .client-item .client-avatar {
                width: 32px !important;
                height: 32px !important;
                font-size: 0.8rem !important;
            }
            .client-item .client-info {
                text-align: left;
            }
            .client-item .client-info div:last-child {
                display: none !important; /* Hide email to stay compact */
            }
            .monitor-content {
                height: auto !important;
                overflow: visible !important;
                padding-right: 0 !important;
            }
            .bento-grid {
                grid-template-columns: 1fr 1fr !important;
                gap: 12px !important;
            }
            .meal-row {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 8px !important;
            }
            .meal-row h4 {
                width: 100% !important;
                border-bottom: 1px solid rgba(0,0,0,0.05);
                padding-bottom: 5px;
            }
            .meal-row .meal-cals {
                align-self: flex-end !important;
                color: #10b981 !important;
            }
        }

        @media screen and (max-width: 576px) {
            .bento-grid {
                grid-template-columns: 1fr 1fr !important;
            }
            .food-entry-row {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 12px !important;
                padding: 16px !important;
            }
            .entry-macros {
                width: 100% !important;
                justify-content: space-between !important;
            }
        }
    </style>
    <link rel="stylesheet" href="css/premium-revamp.css">
</head>
<body>
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content dash-premium">
            <!-- Modern Mesh Background Elements -->
            <div class="mesh-gradient-container dashboard-mesh">
                <div class="mesh-blob blob-1"></div>
                <div class="mesh-blob blob-2"></div>
                <div class="mesh-blob blob-3"></div>
            </div>

            <!-- Nutri-Glass Noise Texture -->
            <div class="glass-noise"></div>

            <!-- Spotlight & Custom Cursor -->
            <div class="spotlight" id="spotlight"></div>
            <div id="organicCursor"></div>
            <div class="glow-aura" id="cursorAura"></div>
            <div class="split-layout">
                <!-- Client List -->
                <aside class="client-list-sidebar">
                    <div class="sidebar-search">
                        <div class="search-pill-wrapper">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="clientSearchInput" placeholder="Search clients..." class="form-control client-search-pill">
                        </div>
                    </div>
                    <div class="client-items">
                        <?php foreach ($clients as $client): ?>
                            <?php 
                                $isActive = ($selected_client_id == $client['id']);
                                // Mock alert logic for clinical context
                                $hasAlert = (strpos(strtolower($client['name']), 'a') !== false);
                                $alertText = (strlen($client['name']) % 2 === 0) ? 'Over Calorie' : 'Low Protein';
                            ?>
                            <div class="client-item <?php echo $isActive ? 'active' : ''; ?>" 
                                 onclick="location.href='?client_id=<?php echo $client['id']; ?>'"
                                 style="padding: 16px 20px;">
                                <div class="client-avatar circle-avatar" style="width:44px; height:44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); border: 2px solid #ffffff; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                                    <?php echo getInitials($client['name']); ?>
                                </div>
                                <div class="client-info">
                                    <div style="font-weight: 600; font-size: 0.95rem; color: #1e293b; display: flex; justify-content: space-between; align-items: center;">
                                        <?php echo htmlspecialchars($client['name']); ?>
                                        <?php if ($hasAlert): ?>
                                            <span style="font-size: 0.6rem; background: #fee2e2; color: #ef4444; padding: 2px 6px; border-radius: 6px; font-weight: 800;"><?php echo $alertText; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 2px;">
                                        <?php echo htmlspecialchars($client['email']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </aside>

                <!-- Monitor & Feedback -->
                <section class="monitor-content">
                    <?php if ($selected_client): ?>
                        <div class="diary-brief">
                            <style>
                                @media screen and (max-width: 768px) {
                                  .mobile-diary-header {
                                    flex-direction: column !important;
                                    align-items: flex-start !important;
                                  }
                                  .mobile-diary-header button {
                                    width: 100%;
                                    margin-top: 10px;
                                  }
                                }
                            </style>
                            <div class="mobile-diary-header" style="display: flex; justify-content: space-between; align-items: center; gap: 15px; margin-bottom: 20px;">
                                <h2 style="font-size: 1.25rem; margin: 0;"><i class="fas fa-book-medical"></i> Food Diary: <?php echo htmlspecialchars($selected_client['name']); ?></h2>
                                <button class="btn-dash-action premium-clinical-btn" onclick="toggleReportPreview(true)" 
                                    style="padding:12px 24px; border-radius: 16px; background: rgba(16, 185, 129, 0.1); color: #10b981; border: 2px solid rgba(16, 185, 129, 0.2); gap: 12px; font-weight: 700;">
                                    <i class="fas fa-eye-medical" style="font-size: 1.1rem;"></i> <span>Preview Clinical Report</span>
                                </button>
                            </div>
                                <div class="date-picker-nav">
                                    <?php 
                                    $prev_date = date('Y-m-d', strtotime($selected_date . ' -1 day'));
                                    $next_date = date('Y-m-d', strtotime($selected_date . ' +1 day'));
                                    ?>
                                    <a href="?client_id=<?php echo $selected_client_id; ?>&date=<?php echo $prev_date; ?>"><i class="fas fa-chevron-left"></i></a>
                                    <span style="font-weight: 600;"><?php echo date('M j, Y', strtotime($selected_date)); ?></span>
                                    <a href="?client_id=<?php echo $selected_client_id; ?>&date=<?php echo $next_date; ?>"><i class="fas fa-chevron-right"></i></a>
                                </div>
                            </div>

                        <div class="bento-grid" style="margin-top: 32px;">
                            <div class="bento-stat stat-danger">
                                <div class="bento-stat-icon"><i class="fas fa-fire"></i></div>
                                <div class="bento-stat-val"><?php echo number_format($totals['calories'], 0); ?></div>
                                <div class="bento-stat-label">Total Calories</div>
                            </div>
                            <div class="bento-stat stat-primary">
                                <div class="bento-stat-icon"><i class="fas fa-drumstick-bite"></i></div>
                                <div class="bento-stat-val"><?php echo number_format($totals['protein'], 1); ?><small style="font-size: 0.5em;">g</small></div>
                                <div class="bento-stat-label">Daily Protein</div>
                            </div>
                            <div class="bento-stat stat-accent">
                                <div class="bento-stat-icon"><i class="fas fa-bread-slice"></i></div>
                                <div class="bento-stat-val"><?php echo number_format($totals['carbs'], 1); ?><small style="font-size: 0.5em;">g</small></div>
                                <div class="bento-stat-label">Daily Carbs</div>
                            </div>
                            <div class="bento-stat stat-secondary">
                                <div class="bento-stat-icon"><i class="fas fa-oil-can"></i></div>
                                <div class="bento-stat-val"><?php echo number_format($totals['fat'], 1); ?><small style="font-size: 0.5em;">g</small></div>
                                <div class="bento-stat-label">Daily Fats</div>
                            </div>
                        </div>

                            <div class="meal-log-compact" style="margin-top: 24px;">
                                <?php foreach ($grouped_logs as $meal => $items): ?>
                                    <div class="meal-row">
                                        <div class="bento-stat-icon" style="flex-shrink: 0; background: rgba(99, 102, 241, 0.1); color: #6366f1; width: 40px; height: 40px; border-radius: 12px; font-size: 1rem;">
                                            <i class="fas fa-<?php 
                                                switch($meal) {
                                                    case 'Breakfast': echo 'coffee'; break;
                                                    case 'AM Snack': echo 'apple-alt'; break;
                                                    case 'Lunch': echo 'utensils'; break;
                                                    case 'PM Snack': echo 'cookie'; break;
                                                    case 'Dinner': echo 'moon'; break;
                                                    default: echo 'apple-alt';
                                                }
                                            ?>"></i>
                                        </div>
                                        <div style="flex: 1;">
                                            <h4 style="font-family: 'Outfit', sans-serif; font-size: 1.1rem; color: #1e293b; margin-bottom: 4px;"><?php echo $meal; ?></h4>
                                            <div class="food-list" style="margin-top: 10px;">
                                                <?php if (empty($items)): ?>
                                                    <span style="opacity: 0.4; font-size: 0.9rem;">No patient logs recorded</span>
                                                <?php else: ?>
                                                    <?php foreach ($items as $item): 
                                                        $is_missing = ($item['calories'] == 0 || $item['protein'] == 0 || $item['carbs'] == 0 || $item['fat'] == 0);
                                                    ?>
                                                        <div class="food-entry-row <?php echo $is_missing ? 'missing-macros' : ''; ?>">
                                                            <div style="flex: 1;">
                                                                <span style="font-weight: 700; color: #1e293b; font-family: 'Outfit', sans-serif; font-size: 0.98rem;"><?php echo htmlspecialchars($item['food_name']); ?></span>
                                                                <?php if ($is_missing): ?>
                                                                    <span class="needs-review-badge"><i class="fas fa-exclamation-triangle"></i> Needs Review</span>
                                                                <?php endif; ?>
                                                                <span style="font-size: 0.78rem; color: #64748b; font-weight: 500; display: block; margin-top: 2px;">(<?php echo number_format($item['serving_size'], 0); ?>g)</span>
                                                            </div>
                                                            <div class="entry-macros" style="display: flex; gap: 12px; align-items: center;">
                                                                <div style="text-align: center; min-width: 58px;">
                                                                    <div style="font-size: 0.65rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">Cals</div>
                                                                    <input type="number" step="any" class="macro-edit-input" data-id="<?php echo $item['id']; ?>" data-field="calories" value="<?php echo $item['calories']; ?>">
                                                                </div>
                                                                <div style="text-align: center; min-width: 58px;">
                                                                    <div style="font-size: 0.65rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">Prot</div>
                                                                    <input type="number" step="any" class="macro-edit-input" data-id="<?php echo $item['id']; ?>" data-field="protein" value="<?php echo $item['protein']; ?>">
                                                                </div>
                                                                <div style="text-align: center; min-width: 58px;">
                                                                    <div style="font-size: 0.65rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">Carb</div>
                                                                    <input type="number" step="any" class="macro-edit-input" data-id="<?php echo $item['id']; ?>" data-field="carbs" value="<?php echo $item['carbs']; ?>">
                                                                </div>
                                                                <div style="text-align: center; min-width: 58px;">
                                                                    <div style="font-size: 0.65rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">Fat</div>
                                                                    <input type="number" step="any" class="macro-edit-input" data-id="<?php echo $item['id']; ?>" data-field="fat" value="<?php echo $item['fat']; ?>">
                                                                </div>
                                                                <button class="btn-save-macro" onclick="saveMacro(this, <?php echo $item['id']; ?>)">
                                                                    <i class="fas fa-check"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.1rem; color: #111827;">
                                                <?php echo number_format(array_sum(array_column($items, 'calories')), 0); ?><small style="font-size: 0.6em; opacity: 0.6; margin-left: 2px;">kcal</small>
                                            </div>
                                            <div style="font-size: 0.75rem; color: #10b981; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 4px;">
                                                Diagnostic Valid
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Revamped Clinical Journal -->
                        <div class="clinical-journal-card">
                            <div class="clinical-journal-header">
                                <h3 class="clinical-journal-title">
                                    <i class="fas fa-clipboard-check"></i> Clinical Journal
                                </h3>
                                <div class="context-chips" style="display: flex; gap: 10px;">
                                    <div class="info-chip" style="font-size: 0.75rem; font-weight: 700; padding: 6px 14px; background: rgba(16, 185, 129, 0.1); color: #10b981; border-radius: 20px; border: 1px solid rgba(16, 185, 129, 0.2);">
                                        <i class="fas fa-calendar-day"></i> <?php echo date('M j', strtotime($selected_date)); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Clinical Feedback Cards -->
                            <div class="feedback-cards-container" id="feedbackList">
                                <div style="text-align:center; padding: 40px; color: #94a3b8;">
                                    <i class="fas fa-spinner fa-spin"></i> Synchronizing clinical notes...
                                </div>
                            </div>

                            <?php if (!$is_admin): ?>
                                <div class="clinical-input-container">
                                    <form id="feedbackForm">
                                        <input type="hidden" name="client_user_id" value="<?php echo $client_user_id; ?>">
                                        <input type="hidden" name="log_date" value="<?php echo $selected_date; ?>">
                                        <div class="clinical-input-pill">
                                            <textarea class="clinical-textarea" name="content" placeholder="Enter clinical assessment for this daily log..." rows="1"></textarea>
                                            <button type="submit" class="clinical-submit-btn">
                                                <i class="fas fa-file-signature"></i> <span>Add Entry</span>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div style="text-align: center; color: #64748b; font-size: 0.85rem; background: rgba(0,0,0,0.02); padding: 20px; border-top: 1px solid rgba(0,0,0,0.05);">
                                    <i class="fas fa-eye"></i> Diagnostic View (Admin Read-Only)
                                </div>
                            <?php endif; ?>
                        </div>

                        <style>
                            /* Revamped Feedback Container */
                            .clinical-journal-card {
                                background: rgba(255, 255, 255, 0.7) !important;
                                backdrop-filter: blur(28px) saturate(180%) !important;
                                -webkit-backdrop-filter: blur(28px) saturate(180%) !important;
                                border: 1px solid rgba(255, 255, 255, 0.5) !important;
                                border-left: 5px solid #10b981 !important;
                                border-radius: 24px !important;
                                box-shadow: 0 20px 45px rgba(0, 0, 0, 0.04) !important;
                                overflow: hidden;
                                margin-top: 32px;
                            }

                            .clinical-journal-header {
                                background: linear-gradient(90deg, rgba(16, 185, 129, 0.03) 0%, transparent 100%);
                                border-bottom: 1px solid rgba(0, 0, 0, 0.05) !important;
                                padding: 24px 32px !important;
                                display: flex;
                                justify-content: space-between;
                                align-items: center;
                                flex-wrap: wrap;
                                gap: 15px;
                            }

                            .clinical-journal-title {
                                font-family: 'Outfit', sans-serif !important;
                                font-size: 1.25rem !important;
                                font-weight: 700 !important;
                                color: #1e293b !important;
                                margin: 0 !important;
                                display: flex;
                                align-items: center;
                                gap: 10px;
                            }

                            .clinical-journal-title i {
                                color: #10b981;
                                font-size: 1.35rem;
                            }

                            /* Beautiful Feedback Cards list */
                            .feedback-cards-container {
                                display: flex;
                                flex-direction: column;
                                gap: 18px;
                                padding: 32px !important;
                                max-height: 480px;
                                overflow-y: auto;
                            }

                            /* Keyframe for entry animation */
                            @keyframes noteAppear {
                                from {
                                    opacity: 0;
                                    transform: translateY(20px) scale(0.98);
                                }
                                to {
                                    opacity: 1;
                                    transform: translateY(0) scale(1);
                                }
                            }

                            .feedback-card {
                                background: rgba(255, 255, 255, 0.8) !important;
                                border: 1px solid rgba(16, 185, 129, 0.1) !important;
                                border-left: 4px solid #10b981 !important;
                                border-radius: 16px !important;
                                padding: 20px 24px !important;
                                margin-bottom: 0 !important;
                                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.01) !important;
                                transition: all 0.4s var(--bounce) !important;
                                position: relative;
                                animation: noteAppear 0.5s var(--bounce) backwards;
                            }

                            .feedback-card:hover {
                                transform: translateY(-4px) scale(1.01) !important;
                                background: #ffffff !important;
                                box-shadow: 0 12px 25px rgba(16, 185, 129, 0.06) !important;
                                border-color: rgba(16, 185, 129, 0.25) !important;
                            }

                            .feedback-card-header {
                                display: flex;
                                justify-content: space-between;
                                align-items: center;
                                margin-bottom: 12px !important;
                                border-bottom: 1px dashed rgba(0, 0, 0, 0.04);
                                padding-bottom: 8px;
                            }

                            .feedback-card-meta {
                                display: flex;
                                align-items: center;
                                gap: 10px;
                            }

                            .dietitian-badge {
                                background: rgba(16, 185, 129, 0.1) !important;
                                color: #10b981 !important;
                                font-size: 0.72rem !important;
                                font-weight: 700 !important;
                                text-transform: uppercase;
                                letter-spacing: 0.5px;
                                padding: 3px 10px !important;
                                border-radius: 8px !important;
                                display: inline-flex;
                                align-items: center;
                                gap: 5px;
                            }

                            .dietitian-name {
                                font-family: 'Outfit', sans-serif;
                                font-size: 0.92rem;
                                font-weight: 600;
                                color: #334155;
                            }

                            .feedback-card-time {
                                font-size: 0.78rem !important;
                                color: #94a3b8 !important;
                                display: flex;
                                align-items: center;
                                gap: 6px;
                                font-weight: 500;
                            }

                            .feedback-card-content {
                                font-family: 'Inter', sans-serif !important;
                                font-size: 0.95rem !important;
                                color: #334155 !important;
                                line-height: 1.6 !important;
                                white-space: pre-wrap !important;
                                padding: 4px 0 10px 0;
                            }

                            .feedback-card-footer {
                                display: flex;
                                justify-content: flex-end;
                                align-items: center;
                                border-top: 1px solid rgba(0, 0, 0, 0.02);
                                padding-top: 8px;
                                margin-top: 4px;
                            }

                            .verified-tag {
                                font-size: 0.72rem !important;
                                color: #059669 !important;
                                font-weight: 700 !important;
                                display: inline-flex;
                                align-items: center;
                                gap: 5px;
                                opacity: 0.85;
                            }

                            /* Chat input styling */
                            .clinical-input-container {
                                background: rgba(248, 250, 252, 0.8) !important;
                                padding: 24px 32px !important;
                                border-top: 1px solid rgba(0, 0, 0, 0.05) !important;
                                backdrop-filter: blur(10px);
                            }

                            .clinical-input-pill {
                                display: flex;
                                background: #ffffff !important;
                                border: 1.5px solid #e2e8f0 !important;
                                border-radius: 20px !important;
                                padding: 8px 12px 8px 20px !important;
                                align-items: center;
                                gap: 16px;
                                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02) !important;
                                transition: all 0.3s var(--bounce) !important;
                            }

                            .clinical-input-pill:focus-within {
                                border-color: #10b981 !important;
                                box-shadow: 0 10px 30px rgba(16, 185, 129, 0.08) !important;
                                transform: translateY(-2px);
                            }

                            .clinical-textarea {
                                flex: 1;
                                border: none !important;
                                background: transparent !important;
                                outline: none !important;
                                padding: 10px 0 !important;
                                font-family: 'Inter', sans-serif !important;
                                font-size: 0.95rem !important;
                                color: #1e293b !important;
                                resize: none !important;
                                line-height: 1.5;
                            }

                            .clinical-textarea::placeholder {
                                color: #94a3b8;
                            }

                            .clinical-submit-btn {
                                background: #10b981 !important;
                                color: white !important;
                                border: none !important;
                                border-radius: 16px !important;
                                padding: 12px 24px !important;
                                font-family: 'Outfit', sans-serif !important;
                                font-weight: 700 !important;
                                font-size: 0.9rem !important;
                                display: flex;
                                align-items: center;
                                gap: 8px;
                                cursor: pointer;
                                box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2) !important;
                                transition: all 0.3s var(--bounce) !important;
                            }

                            .clinical-submit-btn:hover {
                                background: #059669 !important;
                                transform: translateY(-2px) scale(1.02);
                                box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3) !important;
                            }

                            .clinical-submit-btn:active {
                                transform: translateY(0) scale(0.98);
                            }
                        </style>

                        <script>
                            document.addEventListener('DOMContentLoaded', () => {
                                const feedbackList = document.getElementById('feedbackList');
                                const feedbackForm = document.getElementById('feedbackForm');
                                const clientUserId = <?php echo $client_user_id; ?>;
                                const logDate = '<?php echo $selected_date; ?>';

                                function fetchFeedback() {
                                    fetch(`api/diary_feedback_ajax.php?action=fetch&user_id=${clientUserId}&log_date=${logDate}`)
                                        .then(res => res.json())
                                        .then(data => {
                                            if (data.success) {
                                                renderFeedback(data.feedback);
                                            }
                                        });
                                }

                                function formatClinicalDate(dateStr) {
                                    const d = new Date(dateStr);
                                    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ' ' + 
                                           d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                                }

                                function renderFeedback(feedback) {
                                    if (feedback.length === 0) {
                                        feedbackList.innerHTML = `
                                            <div style="text-align:center; padding: 60px 40px; color: #94a3b8;">
                                                <i class="fas fa-notes-medical" style="font-size: 3rem; opacity: 0.3; margin-bottom: 15px; color: #10b981;"></i>
                                                <p style="font-family: 'Outfit', sans-serif; font-size: 1.05rem; font-weight: 500; margin: 0 0 6px 0; color: #475569;">No Clinical Records</p>
                                                <p style="font-size: 0.85rem; margin: 0; color: #94a3b8;">No clinical feedback or dietetic remarks have been logged for this date yet.</p>
                                            </div>`;
                                        return;
                                    }

                                    feedbackList.innerHTML = feedback.map((item, idx) => `
                                        <div class="feedback-card" style="animation-delay: ${idx * 0.08}s;">
                                            <div class="feedback-card-header">
                                                <div class="feedback-card-meta">
                                                    <span class="dietitian-badge"><i class="fas fa-user-md"></i> Practitioner</span>
                                                    <span class="dietitian-name">${escapeHtml(item.staff_name)}</span>
                                                </div>
                                                <div class="feedback-card-time">
                                                    <i class="far fa-clock"></i> ${formatClinicalDate(item.created_at)}
                                                </div>
                                            </div>
                                            <div class="feedback-card-content">${escapeHtml(item.content)}</div>
                                            <div class="feedback-card-footer">
                                                <span class="verified-tag"><i class="fas fa-check-double"></i> Clinically Verified</span>
                                            </div>
                                        </div>
                                    `).join('');
                                    feedbackList.scrollTop = feedbackList.scrollHeight;
                                }

                                function escapeHtml(text) {
                                    const div = document.createElement('div');
                                    div.textContent = text;
                                    return div.innerHTML;
                                }

                                if (feedbackForm) {
                                    feedbackForm.onsubmit = function(e) {
                                        e.preventDefault();
                                        const formData = new FormData(feedbackForm);
                                        formData.append('action', 'save');
                                        formData.append('user_id', clientUserId);

                                        const btn = feedbackForm.querySelector('button[type="submit"]');
                                        const originalBtn = btn.innerHTML;
                                        btn.disabled = true;
                                        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                                        fetch('api/diary_feedback_ajax.php', {
                                            method: 'POST',
                                            body: formData
                                        })
                                        .then(res => res.json())
                                        .then(data => {
                                            if (data.success) {
                                                feedbackForm.querySelector('textarea').value = '';
                                                fetchFeedback();
                                            } else {
                                                alert(data.message || 'Error saving feedback');
                                            }
                                        })
                                        .finally(() => {
                                            btn.disabled = false;
                                            btn.innerHTML = originalBtn;
                                        });
                                    };

                                    feedbackForm.querySelector('textarea').addEventListener('input', function() {
                                        this.style.height = 'auto';
                                        this.style.height = (this.scrollHeight) + 'px';
                                    });
                                }

                                fetchFeedback();
                            });

                            async function saveMacro(btn, logId) {
                                const row = btn.closest('.entry-macros');
                                const inputs = row.querySelectorAll('.macro-edit-input');
                                const data = { log_id: logId };
                                
                                inputs.forEach(input => {
                                    data[input.dataset.field] = input.value;
                                });

                                const originalBtn = btn.innerHTML;
                                try {
                                    btn.disabled = true;
                                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                                    const response = await fetch('api/update_log_macros.php', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify(data)
                                    });

                                    const result = await response.json();
                                    if (result.success) {
                                        const entryRow = btn.closest('.food-entry-row');
                                        let allFilled = true;
                                        inputs.forEach(input => { if (parseFloat(input.value) === 0 || !input.value) allFilled = false; });
                                        
                                        if (allFilled) {
                                            entryRow.classList.remove('missing-macros');
                                            const badge = entryRow.querySelector('.needs-review-badge');
                                            if (badge) badge.remove();
                                        } else {
                                            if (!entryRow.classList.contains('missing-macros')) {
                                                entryRow.classList.add('missing-macros');
                                            }
                                            let badge = entryRow.querySelector('.needs-review-badge');
                                            if (!badge) {
                                                const titleArea = entryRow.querySelector('div:first-child');
                                                badge = document.createElement('span');
                                                badge.className = 'needs-review-badge';
                                                badge.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Needs Review';
                                                // Insert badge after the first title span
                                                const firstSpan = titleArea.querySelector('span:first-child');
                                                if (firstSpan) {
                                                    firstSpan.after(badge);
                                                } else {
                                                    titleArea.appendChild(badge);
                                                }
                                            }
                                        }

                                        // Show success state briefly
                                        btn.innerHTML = '<i class="fas fa-check-double"></i>';
                                        btn.style.background = '#059669';
                                        setTimeout(() => {
                                            btn.innerHTML = originalBtn;
                                            btn.style.background = '';
                                            btn.disabled = false;
                                        }, 2000);
                                    } else {
                                        alert(result.message || 'Error updating values');
                                        btn.innerHTML = originalBtn;
                                        btn.disabled = false;
                                    }
                                } catch (err) {
                                    console.error(err);
                                    alert('Connection error');
                                    btn.innerHTML = originalBtn;
                                    btn.disabled = false;
                                }
                            }
                        </script>

                    <?php else: ?>
                        <div class="empty-selection">
                            <i class="fas fa-user-circle"></i>
                            <h2>Select a client to monitor</h2>
                            <p>Choose a client from the sidebar to view their food logs and provide feedback.</p>
                        </div>
                    <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Live Report Preview Panel -->
    <div class="report-preview-panel" id="reportPreview">
        <div style="padding: 24px 32px; border-bottom: 1px solid rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.9); backdrop-filter: blur(10px);">
            <h2 style="font-family: 'Outfit', sans-serif; margin: 0; color: #1e293b; font-size: 1.2rem;"><i class="fas fa-file-medical" style="color: #10b981; margin-right: 10px;"></i>Clinical Diagnostic Report</h2>
            <button class="btn-dash-action" onclick="toggleReportPreview(false)" style="padding: 10px; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div style="flex: 1; overflow-y: auto; padding: 40px; background: #f8fafc;" class="preview-body-container">
            <div id="reportPrintArea" class="clinical-report-container">

                <!-- Header -->
                <div class="report-header-grid">
                    <div>
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 6px;">
                            <img src="assets/img/logo.png" style="width: 40px; height: 40px; border-radius: 10px;" alt="NutriDeq">
                            <h1 style="color: #10b981; margin: 0; font-size: 1.8rem; font-family: 'Outfit', sans-serif;">NutriDeq</h1>
                        </div>
                        <p style="color: #64748b; margin: 0; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Official Clinical Diagnostic Report</p>
                    </div>
                    <div style="text-align: right;">
                        <p style="font-weight: 700; color: #1e293b; margin: 0; font-size: 1rem;"><?php echo date('F j, Y', strtotime($saved_report['created_at'] ?? 'now')); ?></p>
                        <p style="color: #94a3b8; margin: 4px 0; font-size: 0.8rem;">Report ID: #<?php echo htmlspecialchars($saved_report['report_id'] ?? bin2hex(random_bytes(4))); ?></p>
                        <div style="margin-top: 8px; background: #ecfdf5; color: #10b981; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; display: inline-block;">✓ Clinically Verified</div>
                    </div>
                </div>

                <!-- Patient + Date Info -->
                <div class="report-info-grid">
                    <div>
                        <p style="color: #10b981; text-transform: uppercase; font-size: 0.7rem; font-weight: 700; margin: 0 0 6px; letter-spacing: 0.5px;">Patient Name</p>
                        <input type="text" id="report_patient_name" value="<?php echo htmlspecialchars($saved_report['patient_name'] ?? $selected_client['name'] ?? 'N/A'); ?>" 
                            style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px; font-size: 0.9rem; font-weight: 700; color: #1e293b; background: white;">
                    </div>
                    <div>
                        <p style="color: #10b981; text-transform: uppercase; font-size: 0.7rem; font-weight: 700; margin: 0 0 6px; letter-spacing: 0.5px;">Patient Email</p>
                        <p style="font-size: 0.9rem; color: #64748b; margin: 0; padding: 8px 0;"><?php echo htmlspecialchars($selected_client['email'] ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <p style="color: #10b981; text-transform: uppercase; font-size: 0.7rem; font-weight: 700; margin: 0 0 6px; letter-spacing: 0.5px;">Review Date</p>
                        <p style="font-size: 1rem; font-weight: 700; margin: 0; color: #1e293b; padding: 8px 0;"><?php echo date('M j, Y', strtotime($selected_date)); ?></p>
                    </div>
                </div>

                <!-- Macro Summary -->
                <h4 style="color: #1e293b; font-size: 0.85rem; font-weight: 700; margin-bottom: 14px; text-transform: uppercase; letter-spacing: 0.5px;">📊 Daily Nutritional Summary</h4>
                <div class="macro-summary-grid">
                    <?php
                    $dailyRef = ['calories' => 2000, 'protein' => 50, 'carbs' => 275, 'fat' => 78];
                    $macroColors = ['calories' => '#ef4444', 'protein' => '#3b82f6', 'carbs' => '#f59e0b', 'fat' => '#8b5cf6'];
                    $macroLabels = ['calories' => ['Energy', 'kcal'], 'protein' => ['Protein', 'g'], 'carbs' => ['Carbohydrates', 'g'], 'fat' => ['Total Fat', 'g']];
                    foreach ($macroLabels as $key => [$label, $unit]):
                        $pct = min(100, round($totals[$key] / $dailyRef[$key] * 100));
                        $val = $key === 'calories' ? number_format($totals[$key], 0) : number_format($totals[$key], 1);
                        $color = $macroColors[$key];
                        $flag = $pct > 110 ? '⚠ High' : ($pct < 50 ? '↓ Low' : '✓ OK');
                        $flagColor = $pct > 110 ? '#ef4444' : ($pct < 50 ? '#f59e0b' : '#10b981');
                    ?>
                    <div style="background: #f8fafc; padding: 16px; border-radius: 14px; border-top: 3px solid <?php echo $color; ?>; text-align: center;">
                        <p style="color: #64748b; font-size: 0.7rem; margin: 0 0 6px; text-transform: uppercase; font-weight: 600;"><?php echo $label; ?></p>
                        <p style="font-weight: 900; font-size: 1.3rem; margin: 0; color: #1e293b;"><?php echo $val; ?><small style="font-size: 0.55em; color: #94a3b8; margin-left: 2px;"><?php echo $unit; ?></small></p>
                        <div style="margin-top: 8px; height: 5px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                            <div style="height: 100%; width: <?php echo $pct; ?>%; background: <?php echo $color; ?>; border-radius: 4px;"></div>
                        </div>
                        <p style="margin: 5px 0 0; font-size: 0.65rem; font-weight: 700; color: <?php echo $flagColor; ?>;"><?php echo $flag; ?> (<?php echo $pct; ?>% DRI)</p>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Meal-by-Meal Breakdown -->
                <h4 style="color: #1e293b; font-size: 0.85rem; font-weight: 700; margin-bottom: 14px; text-transform: uppercase; letter-spacing: 0.5px;">🍽 Meal-by-Meal Dietary Intake</h4>
                <?php
                $mealIcons = ['Breakfast' => '☀️', 'AM Snack' => '🍎', 'Lunch' => '🥗', 'PM Snack' => '🍪', 'Dinner' => '🌙', 'Snack' => '🍎'];
                foreach ($grouped_logs as $meal => $items):
                    $mealCals = array_sum(array_column($items, 'calories'));
                    $mealProtein = array_sum(array_column($items, 'protein'));
                    $mealCarbs = array_sum(array_column($items, 'carbs'));
                    $mealFat = array_sum(array_column($items, 'fat'));
                    $icon = $mealIcons[$meal] ?? '🍽';
                ?>
                <div style="margin-bottom: 24px; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden;">
                    <div style="background: #f1fdf7; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #d1fae5;">
                        <span style="font-weight: 700; color: #1e293b; font-size: 0.95rem;"><?php echo $icon . ' ' . $meal; ?></span>
                        <?php if (!empty($items)): ?>
                        <span style="font-size: 0.8rem; color: #10b981; font-weight: 700; background: white; padding: 3px 12px; border-radius: 20px; border: 1px solid #d1fae5;">
                            <?php echo number_format($mealCals, 0); ?> kcal &nbsp;|&nbsp; P: <?php echo number_format($mealProtein, 1); ?>g &nbsp;|&nbsp; C: <?php echo number_format($mealCarbs, 1); ?>g &nbsp;|&nbsp; F: <?php echo number_format($mealFat, 1); ?>g
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($items)): ?>
                    <div style="padding: 18px; text-align: center; color: #cbd5e1; font-size: 0.85rem; font-style: italic;">No foods logged for this meal.</div>
                    <?php else: ?>
                    <div class="table-responsive-report">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.82rem;">
                        <thead>
                            <tr style="background: #f8fafc; color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.3px;">
                                <th style="padding: 8px 16px; text-align: left;">Food Item</th>
                                <th style="padding: 8px; text-align: center;">Serving</th>
                                <th style="padding: 8px; text-align: center;">Calories</th>
                                <th style="padding: 8px; text-align: center;">Protein</th>
                                <th style="padding: 8px; text-align: center;">Carbs</th>
                                <th style="padding: 8px; text-align: center;">Fat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr style="border-top: 1px solid #f1f5f9;">
                                <td style="padding: 10px 16px; font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($item['food_name']); ?></td>
                                <td style="padding: 10px; text-align: center; color: #64748b;"><?php echo number_format($item['serving_size'] ?? 100, 0); ?>g</td>
                                <td style="padding: 10px; text-align: center; font-weight: 700; color: #ef4444;"><?php echo number_format($item['calories'], 0); ?></td>
                                <td style="padding: 10px; text-align: center; color: #3b82f6;"><?php echo number_format($item['protein'], 1); ?>g</td>
                                <td style="padding: 10px; text-align: center; color: #f59e0b;"><?php echo number_format($item['carbs'], 1); ?>g</td>
                                <td style="padding: 10px; text-align: center; color: #8b5cf6;"><?php echo number_format($item['fat'], 1); ?>g</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <!-- Clinical Flags -->
                <?php
                $flags = [];
                if ($totals['calories'] > 2200) $flags[] = ['⚠️ Caloric Excess', 'Patient exceeded recommended daily caloric threshold (>2,200 kcal). Consider reviewing portion sizes.', '#fef2f2', '#ef4444'];
                if ($totals['calories'] < 900 && !empty($logs)) $flags[] = ['⚠️ Very Low Intake', 'Patient caloric intake is critically below minimum daily requirements. Risk of nutritional deficiency.', '#fff7ed', '#f59e0b'];
                if ($totals['protein'] < 40 && !empty($logs)) $flags[] = ['⚠️ Low Protein', 'Patient protein intake (' . number_format($totals['protein'],1) . 'g) is below the recommended 50g/day.', '#fff7ed', '#f59e0b'];
                if (empty($logs)) $flags[] = ['ℹ️ No Intake Recorded', 'No food logs were recorded for this date. Patient may have missed entries.', '#f8fafc', '#94a3b8'];
                if (!empty($flags)):
                ?>
                <h4 style="color: #1e293b; font-size: 0.85rem; font-weight: 700; margin: 32px 0 14px; text-transform: uppercase; letter-spacing: 0.5px;">🚩 Clinical Flags & Alerts</h4>
                <?php foreach ($flags as [$title, $msg, $bg, $border]): ?>
                <div style="background: <?php echo $bg; ?>; border-left: 4px solid <?php echo $border; ?>; padding: 14px 18px; border-radius: 10px; margin-bottom: 12px;">
                    <p style="font-weight: 700; color: <?php echo $border; ?>; margin: 0 0 4px; font-size: 0.85rem;"><?php echo $title; ?></p>
                    <p style="color: #475569; margin: 0; font-size: 0.82rem; line-height: 1.5;"><?php echo $msg; ?></p>
                </div>
                <?php endforeach; endif; ?>

                <!-- Dietitian Notes -->
                <?php
                $notes_sql = "SELECT df.content, df.created_at, u.name as staff_name FROM diary_feedback df JOIN users u ON df.staff_id = u.id WHERE df.user_id = ? AND df.log_date = ? ORDER BY df.created_at ASC";
                $n_stmt = $conn->prepare($notes_sql);
                $n_stmt->execute([$client_user_id, $selected_date]);
                $notes = $n_stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <h4 style="color: #1e293b; font-size: 0.85rem; font-weight: 700; margin: 32px 0 14px; text-transform: uppercase; letter-spacing: 0.5px;">📝 Dietitian Clinical Notes</h4>
                <?php if (empty($notes)): ?>
                <div style="padding: 18px; text-align: center; color: #cbd5e1; font-size: 0.85rem; font-style: italic; border: 1px dashed #e2e8f0; border-radius: 12px;">No clinical notes recorded for this date.</div>
                <?php else: ?>
                <?php foreach ($notes as $note): ?>
                <div style="background: #f8fafc; border-left: 4px solid #10b981; padding: 14px 18px; border-radius: 10px; margin-bottom: 12px;">
                    <p style="font-size: 0.75rem; color: #94a3b8; margin: 0 0 6px;">
                        <strong style="color: #1e293b;"><?php echo htmlspecialchars($note['staff_name']); ?></strong> &nbsp;·&nbsp;
                        <?php echo date('M j, Y g:i A', strtotime($note['created_at'])); ?>
                    </p>
                    <p style="color: #1e293b; margin: 0; font-size: 0.9rem; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($note['content'])); ?></p>
                </div>
                <?php endforeach; endif; ?>

                <!-- Signature Block -->
                <div class="signature-block-grid">
                    <div>
                        <p style="font-size: 0.75rem; color: #94a3b8; margin-bottom: 20px;">Reviewed & Prepared By</p>
                        <div style="border-top: 1px solid #1e293b; padding-top: 8px;">
                            <input type="text" id="report_staff_name" value="<?php echo htmlspecialchars($saved_report['dietician_name'] ?? $logged_in_staff_name); ?>" 
                                style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px; font-size: 0.85rem; color: #1e293b; font-weight: 700; margin: 0; background: white;">
                            <p style="font-size: 0.72rem; color: #64748b; margin: 2px 0 0;"><?php echo $is_admin ? 'Administrator' : 'Licensed Dietitian / Practitioner'; ?></p>
                        </div>
                    </div>
                    <div>
                        <p style="font-size: 0.75rem; color: #94a3b8; margin-bottom: 20px;">Acknowledged By Patient</p>
                        <div style="border-top: 1px solid #1e293b; padding-top: 8px;">
                            <input type="text" id="report_patient_sig_name" value="<?php echo htmlspecialchars($saved_report['patient_name'] ?? $selected_client['name'] ?? ''); ?>" 
                                style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px; font-size: 0.85rem; color: #1e293b; font-weight: 700; margin: 0; background: white;">
                            <p style="font-size: 0.72rem; color: #64748b; margin: 2px 0 0;"><?php echo htmlspecialchars($selected_client['email'] ?? ''); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div style="margin-top: 32px; text-align: center; color: #cbd5e1; font-size: 0.72rem; line-height: 1.7;">
                    This document was automatically generated by the NutriDeq Clinical Platform &nbsp;·&nbsp; Confidential Patient Record &nbsp;·&nbsp; <?php echo date('F j, Y g:i A'); ?>
                </div>
            </div>
        </div>
        <div style="padding: 20px 32px; border-top: 1px solid rgba(0,0,0,0.05); display: flex; justify-content: flex-end; gap: 16px; background: rgba(255,255,255,0.9);" class="preview-action-footer">
            <button class="btn-dash-action" onclick="toggleReportPreview(false)" style="padding:12px 24px; border-radius: 12px; border: 1.5px solid #e2e8f0;">Close Preview</button>
            <?php if (!$is_admin): ?>
            <button class="btn-dash-action" id="saveReportBtn" style="padding:12px 24px; border-radius: 12px; background: #3b82f6; color: white; border: none; font-weight: 700; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2);"
                onclick="saveClinicalReport()">
                <i class="fas fa-save"></i> Save Report
            </button>
            <?php endif; ?>
            <button class="btn-dash-action" style="padding:12px 24px; border-radius: 12px; background: #10b981; color: white; border: none; font-weight: 700; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);"
                onclick="generateClinicalReport('#reportPrintArea', 'Diagnostic-Report-P<?php echo $selected_client_id; ?>.pdf')">
                <i class="fas fa-file-medical"></i> Generate PDF Document
            </button>
        </div>
    </div>

    <script>
        function toggleReportPreview(show) {
            const panel = document.getElementById('reportPreview');
            if (show) {
                panel.classList.add('active');
            } else {
                panel.classList.remove('active');
            }
        }
        
        // Client Search Filtering Engine
        document.addEventListener('DOMContentLoaded', function() {
            // Sync patient name fields
            const pNameTop = document.getElementById('report_patient_name');
            const pNameSig = document.getElementById('report_patient_sig_name');
            if (pNameTop && pNameSig) {
                pNameTop.addEventListener('input', () => pNameSig.value = pNameTop.value);
                pNameSig.addEventListener('input', () => pNameTop.value = pNameSig.value);
            }

            const searchInput = document.getElementById('clientSearchInput');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const filter = this.value.toLowerCase();
                    const items = document.querySelectorAll('.client-item');
                    items.forEach(item => {
                        const nameEl = item.querySelector('.client-info');
                        const name = nameEl ? nameEl.textContent.toLowerCase() : '';
                        if (name.includes(filter)) {
                            item.style.display = 'flex';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            }
        });

        // Save Report Logic
        function saveClinicalReport() {
            const btn = document.getElementById('saveReportBtn');
            const originalHTML = btn.innerHTML;
            
            // Get data
            const patientName = document.getElementById('report_patient_name').value;
            const staffName = document.getElementById('report_staff_name').value;
            const clientUserId = <?php echo json_encode($client_user_id); ?>;
            const logDate = <?php echo json_encode($selected_date); ?>;
            
            // Prepare content - we'll clone the print area and replace inputs with text for saving
            const printArea = document.getElementById('reportPrintArea').cloneNode(true);
            const pNameInput = printArea.querySelector('#report_patient_name');
            const sNameInput = printArea.querySelector('#report_staff_name');
            const pSigNameInput = printArea.querySelector('#report_patient_sig_name');
            
            if (pNameInput) {
                const span = document.createElement('span');
                span.textContent = pNameInput.value;
                span.style.fontWeight = '700';
                pNameInput.parentNode.replaceChild(span, pNameInput);
            }
            if (sNameInput) {
                const span = document.createElement('span');
                span.textContent = sNameInput.value;
                span.style.fontWeight = '700';
                sNameInput.parentNode.replaceChild(span, sNameInput);
            }
            if (pSigNameInput) {
                const span = document.createElement('span');
                span.textContent = pSigNameInput.value;
                span.style.fontWeight = '700';
                pSigNameInput.parentNode.replaceChild(span, pSigNameInput);
            }
            
            const reportContent = printArea.innerHTML;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            const formData = new FormData();
            formData.append('user_id', clientUserId);
            formData.append('log_date', logDate);
            formData.append('patient_name', patientName);
            formData.append('dietician_name', staffName);
            formData.append('report_content', reportContent);

            fetch('api/save_clinical_report.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    btn.innerHTML = '<i class="fas fa-check"></i> Saved Successfully';
                    btn.style.background = '#059669';
                    setTimeout(() => {
                        btn.innerHTML = originalHTML;
                        btn.style.background = '#3b82f6';
                        btn.disabled = false;
                    }, 3000);
                } else {
                    alert(data.message || 'Error saving report');
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                }
            })
            .catch(err => {
                console.error(err);
                alert('Connection error');
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            });
        }

        // PDF Generation Engine
        function generateClinicalReport(targetSelector, filename) {
            const element = document.querySelector(targetSelector);
            if (!element) return alert("Error: Report content not found.");
            
            // Temporarily replace inputs with text for PDF generation
             const pInput = element.querySelector('#report_patient_name');
             const sInput = element.querySelector('#report_staff_name');
             const pSigInput = element.querySelector('#report_patient_sig_name');
             
             const pVal = pInput ? pInput.value : '';
             const sVal = sInput ? sInput.value : '';
             const pSigVal = pSigInput ? pSigInput.value : '';
             
             let pPlaceholder, sPlaceholder, pSigPlaceholder;
             
             if (pInput) {
                 pPlaceholder = document.createElement('span');
                 pPlaceholder.textContent = pVal;
                 pPlaceholder.style.fontWeight = '700';
                 pPlaceholder.style.display = 'block';
                 pPlaceholder.style.padding = '8px 0';
                 pInput.style.display = 'none';
                 pInput.parentNode.insertBefore(pPlaceholder, pInput);
             }
             
             if (sInput) {
                 sPlaceholder = document.createElement('span');
                 sPlaceholder.textContent = sVal;
                 sPlaceholder.style.fontWeight = '700';
                 sPlaceholder.style.display = 'block';
                 sPlaceholder.style.padding = '8px 0';
                 sInput.style.display = 'none';
                 sInput.parentNode.insertBefore(sPlaceholder, sInput);
             }

             if (pSigInput) {
                 pSigPlaceholder = document.createElement('span');
                 pSigPlaceholder.textContent = pSigVal;
                 pSigPlaceholder.style.fontWeight = '700';
                 pSigPlaceholder.style.display = 'block';
                 pSigPlaceholder.style.padding = '8px 0';
                 pSigInput.style.display = 'none';
                 pSigInput.parentNode.insertBefore(pSigPlaceholder, pSigInput);
             }

            // Show loading state on button
            const btn = event.currentTarget || document.activeElement;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';
            btn.style.opacity = '0.7';
            btn.style.pointerEvents = 'none';

            const opt = {
                margin:       10,
                filename:     filename,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, logging: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().set(opt).from(element).save().then(() => {
                btn.innerHTML = '<i class="fas fa-check"></i> Document Saved';
                btn.style.backgroundColor = '#059669';
                
                // Restore inputs
                 if (pInput) {
                     pInput.style.display = 'block';
                     pPlaceholder.remove();
                 }
                 if (sInput) {
                     sInput.style.display = 'block';
                     sPlaceholder.remove();
                 }
                 if (pSigInput) {
                     pSigInput.style.display = 'block';
                     pSigPlaceholder.remove();
                 }

                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.opacity = '1';
                    btn.style.pointerEvents = 'auto';
                    btn.style.backgroundColor = '#10b981';
                }, 3000);
            }).catch(err => {
                console.error("PDF generation error: ", err);
                btn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Generation Failed';
                
                // Restore inputs
                if (pInput) {
                    pInput.style.display = 'block';
                    pPlaceholder.remove();
                }
                if (sInput) {
                    sInput.style.display = 'block';
                    sPlaceholder.remove();
                }
                if (pSigInput) {
                    pSigInput.style.display = 'block';
                    pSigPlaceholder.remove();
                }

                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.opacity = '1';
                    btn.style.pointerEvents = 'auto';
                }, 3000);
            });
        }
    </script>
</body>
</html>


