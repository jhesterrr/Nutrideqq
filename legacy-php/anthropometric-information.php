<?php
require_once __DIR__ . '/session_bootstrap.php';

require_once 'database.php';

$database = new Database();
$pdo = $database->getConnection();

// Check if user is logged in
// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login-logout/NutriDeqN-Login.php");
    exit();
}

$user_role = $_SESSION['user_role'] ?? 'regular';
$is_admin = ($user_role === 'admin');
$is_staff = ($user_role === 'staff');

// Check if viewing a specific client's data (for staff/admin)
if (isset($_GET['client_id']) && ($is_staff || $is_admin)) {
    $client_id = $_GET['client_id'];

    // Verify this client belongs to the logged-in staff member OR user is admin
    $check_sql = "SELECT * FROM clients WHERE id = ?";
    $params = [$client_id];

    if (!$is_admin) {
        $check_sql .= " AND staff_id = ?";
        $params[] = $_SESSION['user_id'];
    }

    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute($params);
    $client = $check_stmt->fetch();

    if (!$client) {
        $error = "Client not found or you don't have permission to view this client.";
        unset($_SESSION['viewing_client']);
        unset($_SESSION['client_name']);
        $viewing_client_name = null;
    } else {
        // Set client data for viewing
        $_SESSION['viewing_client'] = $client_id;
        $_SESSION['client_name'] = $client['name'];
        $viewing_client_name = $client['name'];
    }
} else {
    // Regular user viewing their own data
    unset($_SESSION['viewing_client']);
    unset($_SESSION['client_name']);
    $viewing_client_name = null;
}

$staff_id = $_SESSION['user_id'];
$staff_name = $_SESSION['user_name'] ?? 'Staff User';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $client_id_post = $_POST['client_id'] ?? null;
    if ($client_id_post && ($is_staff || $is_admin)) {
        $verify_sql = "SELECT * FROM clients WHERE id = ?";
        $verify_params = [$client_id_post];
        if (!$is_admin) {
            $verify_sql .= " AND staff_id = ?";
            $verify_params[] = $staff_id;
        }
        $verify = $pdo->prepare($verify_sql);
        $verify->execute($verify_params);
        $clientRow = $verify->fetch();

        if ($clientRow) {
            $user_q = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $user_q->execute([$clientRow['email']]);
            $userRow = $user_q->fetch();
            $uid = $userRow['id'] ?? null;
            if ($_POST['action'] === 'add_food_entry' && $uid) {
                $meal_type = trim($_POST['meal_type'] ?? 'custom');
                $food_name = trim($_POST['food_name'] ?? '');
                $calories = (float) ($_POST['calories'] ?? 0);
                $protein = (float) ($_POST['protein'] ?? 0);
                $carbs = (float) ($_POST['carbs'] ?? 0);
                $fat = (float) ($_POST['fat'] ?? 0);
                if (!empty($food_name)) {
                    $ins = $pdo->prepare("INSERT INTO food_tracking (user_id, food_name, calories, protein, carbs, fat, meal_type, tracking_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), NOW())");
                    $ins->execute([$uid, $food_name, $calories, $protein, $carbs, $fat, $meal_type]);
                }
                header("Location: anthropometric-information.php?client_id=" . urlencode($client_id_post) . "&tab=food-tracker");
                exit();
            }
            if ($_POST['action'] === 'delete_food_entry' && $uid) {
                $entry_id = (int) ($_POST['entry_id'] ?? 0);
                if ($entry_id > 0) {
                    $del = $pdo->prepare("DELETE FROM food_tracking WHERE id = ? AND user_id = ?");
                    $del->execute([$entry_id, $uid]);
                }
                header("Location: anthropometric-information.php?client_id=" . urlencode($client_id_post) . "&tab=food-tracker");
                exit();
            }
            if ($_POST['action'] === 'update_body_stats') {
                $weight = $_POST['weight'] ?? null;
                $height = $_POST['height'] ?? null;
                $waist = $_POST['waist_circumference'] ?? null;
                $hip = $_POST['hip_circumference'] ?? null;

                $upd_sql = "UPDATE clients SET weight = ?, height = ?, waist_circumference = ?, hip_circumference = ?, updated_at = NOW() WHERE id = ?";
                $upd_params = [$weight, $height, $waist, $hip, $client_id_post];
                if (!$is_admin) {
                    $upd_sql .= " AND staff_id = ?";
                    $upd_params[] = $staff_id;
                }

                $upd = $pdo->prepare($upd_sql);
                $upd->execute($upd_params);
                
                // Add to bmi history if possible
                if ($weight > 0 && $height > 0) {
                    try {
                        $pdo->exec("CREATE TABLE IF NOT EXISTS client_bmi_history (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            user_id INT NOT NULL,
                            weight DECIMAL(5,2),
                            height DECIMAL(5,2),
                            bmi DECIMAL(5,1),
                            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )");
                        
                        // Find user_id
                        $u_stmt = $pdo->prepare("SELECT user_id FROM clients WHERE id = ?");
                        $u_stmt->execute([$client_id_post]);
                        $c_user_id = $u_stmt->fetchColumn();
                        
                        if ($c_user_id) {
                            $height_m = $height / 100;
                            $bmi = round($weight / ($height_m * $height_m), 1);
                            $pdo->prepare("INSERT INTO client_bmi_history (user_id, weight, height, bmi) VALUES (?, ?, ?, ?)")
                                ->execute([$c_user_id, $weight, $height, $bmi]);
                        }
                    } catch (Exception $e) { error_log('NutriDeq error: ' . $e->getMessage()); }
                }
                
                header("Location: anthropometric-information.php?client_id=" . urlencode($client_id_post) . "&tab=body-stats");
                exit();
            }
        }
    }
}
// Get staff's clients for the tracker (or all for admin)
$clients_sql = "
    SELECT id, name, email, phone, address, city, state, zip_code, age, date_of_birth, gender, weight, height, 
           waist_circumference, hip_circumference, health_conditions, dietary_restrictions, goals, notes, status, created_at
    FROM clients 
    WHERE status = 'active'
";
$clients_params = [];
if (!$is_admin && $is_staff) {
    $clients_sql .= " AND staff_id = ?";
    $clients_params[] = $staff_id;
}
$clients_sql .= " ORDER BY name";

$clients_query = $pdo->prepare($clients_sql);
$clients_query->execute($clients_params);
$clients = $clients_query->fetchAll(PDO::FETCH_ASSOC);

// Get selected client (if any)
$selected_client_id = $_GET['client_id'] ?? ($clients[0]['id'] ?? null);
$selected_client = null;

if ($selected_client_id) {
    $client_sql = "
        SELECT id, name, email, phone, address, city, state, zip_code, age, date_of_birth, gender, weight, height, 
               waist_circumference, hip_circumference, health_conditions, dietary_restrictions, goals, notes, status, created_at
        FROM clients 
        WHERE id = ?
    ";
    $client_params = [$selected_client_id];

    if (!$is_admin && $is_staff) {
        $client_sql .= " AND staff_id = ?";
        $client_params[] = $staff_id;
    }

    $client_query = $pdo->prepare($client_sql);
    $client_query->execute($client_params);
    $selected_client = $client_query->fetch(PDO::FETCH_ASSOC);
}

// Fetch food items from database using the correct table structure
$food_items = [];
try {
    $food_query = $pdo->prepare("
        SELECT 
            id,
            food_name,
            food_group as group_type,
            serving_size,
            calories as energy,
            protein,
            fat,
            carbs as carbohydrates,
            exchanges
        FROM food_items 
        ORDER BY food_group, food_name
    ");
    $food_query->execute();
    $food_items = $food_query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log error for debugging
    error_log("Food items query error: " . $e->getMessage());
    $food_items = [];
}

// Organize food items by group for easier access
$food_items_by_group = [];
foreach ($food_items as $item) {
    $group_type = $item['group_type'] ?? 'other';
    if (!isset($food_items_by_group[$group_type])) {
        $food_items_by_group[$group_type] = [];
    }
    $food_items_by_group[$group_type][] = $item;
}

// Initialize variables
$food_entries = [];
$calories_today = 0;
$client_user_id = null;

// Get client data
if ($selected_client) {
    // TEMPORARY FIX: Get user_id from users table using email
    $user_query = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $user_query->execute([$selected_client['email']]);
    $user_data = $user_query->fetch(PDO::FETCH_ASSOC);

    if ($user_data) {
        $client_user_id = $user_data['id'];

        // Get food tracking for today
        $food_today = $pdo->prepare("
            SELECT * FROM food_tracking 
            WHERE user_id = ? AND tracking_date = CURDATE()
            ORDER BY meal_type
        ");
        $food_today->execute([$client_user_id]);
        $food_entries = $food_today->fetchAll(PDO::FETCH_ASSOC);

        // Calculate total calories
        $calories_today = 0;
        foreach ($food_entries as $food) {
            $calories_today += $food['calories'];
        }
    }
}

// Generate initials for avatar
function getInitials($name)
{
    $names = explode(' ', trim($name ?? ''));
    $initials = '';
    foreach ($names as $n) {
        if ($n !== '') {
            $initials .= strtoupper($n[0]);
        }
    }
    return !empty($initials) ? substr($initials, 0, 2) : 'U';
}

$user_initials = getInitials($staff_name);

// Role-based navigation links
require_once 'navigation.php';
$nav_links_array = getNavigationLinks($_SESSION['user_role'], 'anthropometric-information.php');
// Convert to simple array for loop if necessary, but the loop below (lines 260+) expects an array of associative arrays.
// getNavigationLinks returns an associative array where keys are filenames.
// The loop does: foreach ($nav_links as $link)
// This works perfectly as it will iterate over the values of the associative array returned by getNavigationLinks.
$nav_links = $nav_links_array;

// Check for active tab in session or URL
if (isset($_GET['tab'])) {
    $active_tab = $_GET['tab'];
    $_SESSION['active_health_tracker_tab'] = $active_tab;
} else {
    $active_tab = $_SESSION['active_health_tracker_tab'] ?? 'personal-info';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="scripts/theme-toggle.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>NutriDeq – Anthropometric Information</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Choices.js for searchable dropdowns -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Core styles -->
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/logout-modal.css">
    <link rel="stylesheet" href="css/premium-management.css">
    <link rel="stylesheet" href="css/responsive.css">
    <link rel="stylesheet" href="css/mobile-style.css">
    <!-- Page-specific premium styles -->
    <link rel="stylesheet" href="css/anthropometric-premium.css">
    <link rel="stylesheet" href="css/food-exchange.css">
    <style>
        /* Save Meal Plan Button override fix */
        #saveMealPlan, .ai-btn-save-meal {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
            color: #ffffff !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            padding: 14px 32px !important;
            border-radius: 16px !important;
            font-family: 'Outfit', sans-serif !important;
            font-weight: 800 !important;
            font-size: 0.95rem !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 12px !important;
            cursor: pointer !important;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
            box-shadow: 
                0 10px 25px rgba(5, 150, 105, 0.3),
                0 4px 10px rgba(16, 185, 129, 0.15),
                inset 0 1px 1px rgba(255, 255, 255, 0.2) !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
        }
        #saveMealPlan:hover, .ai-btn-save-meal:hover {
            transform: translateY(-4px) scale(1.03) !important;
            box-shadow: 
                0 15px 35px rgba(5, 150, 105, 0.4),
                0 6px 15px rgba(16, 185, 129, 0.2),
                inset 0 1px 1px rgba(255, 255, 255, 0.3) !important;
            filter: brightness(1.05) !important;
            color: #ffffff !important;
        }
        #saveMealPlan i, .ai-btn-save-meal i {
            color: #ffffff !important;
        }

        .stats-input[readonly] {
            background-color: #f8fafc !important;
            border-color: #e2e8f0 !important;
            color: #475569 !important;
            cursor: default;
        }
        .stats-input:not([readonly]) {
            background-color: #ffffff !important;
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 3px rgba(16,185,129,0.1) !important;
        }
        
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
            margin-bottom: 30px;
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
            font-size: clamp(2.5rem, 8vw, 3.5rem);
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
                flex-direction: column;
            }
            .bmi-status-info {
                padding-right: 0;
                border-right: none;
                border-bottom: 1px solid #e2e8f0;
                padding-bottom: 30px;
                margin-bottom: 30px;
            }
        }
        
        @media (max-width: 576px) {
            .bmi-visual-container {
                padding: 20px;
                gap: 20px;
            }
            .bmi-status-info {
                padding-bottom: 20px;
                margin-bottom: 20px;
            }
        }

        /* Mobile-specific adjustments for Choices.js */
        @media (max-width: 768px) {
            .choices__list--dropdown {
                min-width: 100% !important;
                left: 0 !important;
                right: 0 !important;
            }
        }
    </style>
    <script>const BASE_URL = '';</script>
    <link rel="stylesheet" href="css/premium-revamp.css">
    <script src="scripts/csrf-fetch.js"></script>
</head>

<body>
<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-container ai-page">

            <?php
            // ── Pre-compute display values ──────────────────────────────
            $sc = $selected_client;
            $bmi_val   = 0;
            $bmi_label = 'N/A';
            $bmi_class = 'neutral';
            $whr_val   = 'N/A';
            $whr_risk  = 'N/A';
            if ($sc) {
                if (!empty($sc['weight']) && !empty($sc['height']) && $sc['height'] > 0) {
                    $hm = $sc['height'] / 100;
                    $bmi_val = round($sc['weight'] / ($hm * $hm), 1);
                    if ($bmi_val < 18.5)      { $bmi_label = 'Underweight'; $bmi_class = 'info'; }
                    elseif ($bmi_val < 25)    { $bmi_label = 'Normal';      $bmi_class = 'normal'; }
                    elseif ($bmi_val < 30)    { $bmi_label = 'Overweight';  $bmi_class = 'warn'; }
                    else                      { $bmi_label = 'Obese';       $bmi_class = 'danger'; }
                }
                if (!empty($sc['waist_circumference']) && !empty($sc['hip_circumference']) && $sc['hip_circumference'] > 0) {
                    $whr_val  = number_format(($sc['waist_circumference'] ?? 0) / ($sc['hip_circumference'] ?? 1), 2);
                    $gender   = $sc['gender'] ?? '';
                    $whr_risk = ($gender === 'male') ? (($sc['waist_circumference'] / $sc['hip_circumference'] <= 0.9) ? 'Low Risk' : 'High Risk')
                                                     : (($sc['waist_circumference'] / $sc['hip_circumference'] <= 0.85) ? 'Low Risk' : 'High Risk');
                }
                $client_initials = getInitials($sc['name']);
                $optimal_low = $optimal_high = 0;
                if (!empty($sc['height'])) {
                    $hm2 = $sc['height'] / 100;
                    $optimal_low  = number_format(18.5 * $hm2 * $hm2, 1);
                    $optimal_high = number_format(24.9 * $hm2 * $hm2, 1);
                }
                
                // Fetch BMI history for chart
                $bmi_history_data = [];
                try {
                    $user_q = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $user_q->execute([$sc['email']]);
                    $uid = $user_q->fetchColumn();
                    
                    if ($uid) {
                        $hist_stmt = $pdo->prepare("SELECT bmi, DATE_FORMAT(recorded_at, '%b %d') as date_label FROM client_bmi_history WHERE user_id = ? ORDER BY recorded_at ASC LIMIT 15");
                        $hist_stmt->execute([$uid]);
                        $bmi_history_data = $hist_stmt->fetchAll(PDO::FETCH_ASSOC);

                        // Seed history if empty but current BMI exists
                        if (empty($bmi_history_data) && $bmi_val > 0) {
                            $pdo->prepare("INSERT INTO client_bmi_history (user_id, weight, height, bmi) VALUES (?, ?, ?, ?)")
                                ->execute([$uid, $sc['weight'], $sc['height'], $bmi_val]);
                            $bmi_history_data = [['bmi' => $bmi_val, 'date_label' => date('M d')]];
                        }
                    }
                } catch (Exception $e) { error_log('NutriDeq error: ' . $e->getMessage()); }
            }
            ?>

            <!-- ═══ HERO BANNER ═══ -->
            <?php if ($selected_client): ?>
            <div class="ai-hero stagger d-1">
                <div class="ai-hero-avatar"><?= htmlspecialchars($client_initials) ?></div>
                <div class="ai-hero-info">
                    <h1 class="ai-hero-name"><?= htmlspecialchars($sc['name']) ?></h1>
                    <p class="ai-hero-subtitle">
                        <i class="fas fa-id-card"></i> <?= $sc['age'] ? $sc['age'].' years' : 'Age not set' ?>
                        <span class="ai-sep">·</span> 
                        <i class="fas fa-venus-mars"></i> <?= $sc['gender'] ? ucfirst($sc['gender']) : 'Gender unknown' ?>
                        <span class="ai-sep">·</span>
                        <i class="fas fa-location-dot"></i> <?= $sc['city'] ? htmlspecialchars($sc['city']) : 'Location not set' ?>
                    </p>
                    <div class="ai-hero-badges">
                        <div class="ai-badge"><i class="fas fa-weight-scale"></i> <?= $sc['weight'] ? $sc['weight'].' kg' : 'Weight pending' ?></div>
                        <div class="ai-badge"><i class="fas fa-ruler-vertical"></i> <?= $sc['height'] ? $sc['height'].' cm' : 'Height pending' ?></div>
                        <?php if ($bmi_val > 0): ?>
                        <div class="ai-badge ai-badge-bmi bmi-<?= $bmi_class ?>"><i class="fas fa-chart-simple"></i> BMI <?= $bmi_val ?> — <?= $bmi_label ?></div>
                        <?php endif; ?>
                        <div class="ai-badge" style="background:#dcfce7; color:#166534; border-color:#bbf7d0;"><i class="fas fa-circle-check"></i> Active Case</div>
                    </div>
                </div>
                <div class="ai-hero-actions">
                    <?php if ($is_staff || $is_admin): ?>
                    <a href="user-management-staff.php" class="ai-btn-back">
                        <i class="fas fa-chevron-left"></i> Client List
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="ai-hero ai-hero-empty stagger d-1">
                <div class="ai-hero-avatar" style="background:rgba(255,255,255,0.1); border: 2px dashed rgba(255,255,255,0.3);">
                    <i class="fas fa-users-medical" style="font-size:1.6rem; opacity:0.8;"></i>
                </div>
                <div class="ai-hero-info">
                    <h1 class="ai-hero-name">Anthropometric Hub</h1>
                    <p class="ai-hero-subtitle">Comprehensive clinical tracking, food exchange management, and health analytics.</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- ═══ ALERTS ═══ -->
            <?php if (isset($error)): ?>
            <div class="ai-alert ai-alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['success'])): ?>
            <div class="ai-alert ai-alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
            <?php endif; ?>

            <!-- ═══ CLIENT SELECTOR ═══ -->
            <div class="ai-selector-bar">
                <div class="ai-selector-icon"><i class="fas fa-user-friends"></i></div>
                <span class="ai-selector-label">Client</span>
                <select onchange="if(this.value) location.href='?client_id='+this.value" id="clientSelect">
                    <option value="">— Choose a Client —</option>
                    <?php foreach ($clients as $client): ?>
                    <option value="<?= $client['id'] ?>" <?= $selected_client_id == $client['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($client['name']) ?> (<?= htmlspecialchars($client['email']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (!$selected_client): ?>
            <!-- ═══ EMPTY STATE ═══ -->
            <div class="ai-empty">
                <div class="ai-empty-icon"><i class="fas fa-user-friends"></i></div>
                <h3>No Client Selected</h3>
                <p>Select a client from the dropdown above to view their health tracker.</p>
                <?php if (empty($clients)): ?>
                <p style="margin-top:8px;color:#d97706;font-size:0.85rem;"><i class="fas fa-info-circle"></i> You don't have any active clients assigned yet.</p>
                <?php endif; ?>
            </div>

            <?php else: ?>

            <!-- ═══ STAT RIBBON ═══ -->
            <div class="ai-stat-ribbon">
                <div class="ai-stat-card">
                    <div class="ai-stat-icon"><i class="fas fa-weight"></i></div>
                    <div class="ai-stat-val"><?= $sc['weight'] ? $sc['weight'] : '—' ?></div>
                    <div class="ai-stat-label">Weight (kg)</div>
                    <?php if ($bmi_val > 0): ?>
                    <span class="ai-stat-badge <?= $bmi_class ?>"><?= $bmi_label ?></span>
                    <?php else: ?><span class="ai-stat-badge neutral">Not recorded</span><?php endif; ?>
                </div>
                <div class="ai-stat-card">
                    <div class="ai-stat-icon"><i class="fas fa-ruler-vertical"></i></div>
                    <div class="ai-stat-val"><?= $sc['height'] ? $sc['height'] : '—' ?></div>
                    <div class="ai-stat-label">Height (cm)</div>
                    <span class="ai-stat-badge neutral"><?= ($optimal_low && $optimal_high) ? "Ideal: {$optimal_low}–{$optimal_high} kg" : 'Needs height' ?></span>
                </div>
                <div class="ai-stat-card">
                    <div class="ai-stat-icon"><i class="fas fa-chart-bar"></i></div>
                    <div class="ai-stat-val"><?= $bmi_val ?: '—' ?></div>
                    <div class="ai-stat-label">BMI</div>
                    <span class="ai-stat-badge <?= $bmi_class ?>"><?= $bmi_label ?></span>
                </div>
                <div class="ai-stat-card">
                    <div class="ai-stat-icon"><i class="fas fa-circle-dot"></i></div>
                    <div class="ai-stat-val"><?= $whr_val ?></div>
                    <div class="ai-stat-label">Waist-Hip Ratio</div>
                    <span class="ai-stat-badge <?= $whr_risk === 'High Risk' ? 'danger' : ($whr_risk === 'Low Risk' ? '' : 'neutral') ?>"><?= $whr_risk ?></span>
                </div>
            </div>

            <!-- ═══ TABS CONTAINER ═══ -->
            <!-- Keep original .tabs-container wrapper so switchTab() JS still works -->
            <div class="ai-tabs-wrap tabs-container">

                <!-- Tab Rail -->
                <div class="ai-tab-rail stagger d-3">
                    <button class="ai-tab-btn tab <?= $active_tab === 'personal-info' ? 'active' : '' ?>" data-tab="personal-info" onclick="switchTab('personal-info')">
                        <i class="fas fa-address-card"></i> <span>Client Info</span>
                    </button>
                    <button class="ai-tab-btn tab <?= $active_tab === 'food-tracker' ? 'active' : '' ?>" data-tab="food-tracker" onclick="switchTab('food-tracker')">
                        <i class="fas fa-clipboard-list"></i> <span>Food Tracker</span>
                    </button>
                    <button class="ai-tab-btn tab <?= $active_tab === 'body-stats' ? 'active' : '' ?>" data-tab="body-stats" onclick="switchTab('body-stats')">
                        <i class="fas fa-weight-scale"></i> <span>Body Statistics</span>
                    </button>
                    <button class="ai-tab-btn tab <?= $active_tab === 'progress' ? 'active' : '' ?>" data-tab="progress" onclick="switchTab('progress')">
                        <i class="fas fa-utensils-crossed"></i> <span>Meal Planner</span>
                    </button>
                </div>

                <!-- ── TAB 1: Client Info ── -->
                <div class="ai-tab-panel tab-content <?= $active_tab === 'personal-info' ? 'active' : '' ?>" id="personal-info">
                    <div class="ai-section-header">
                        <h2 class="ai-section-title"><i class="fas fa-user-circle"></i> Client Information</h2>
                    </div>

                    <p class="ai-group-label"><i class="fas fa-id-card"></i> Personal Details</p>
                    <div class="ai-form-grid">
                        <div class="ai-field">
                            <label>Full Name</label>
                            <div class="ai-field-inner"><div class="ai-field-icon"><i class="fas fa-user"></i></div>
                            <input type="text" value="<?= htmlspecialchars($sc['name']) ?>" readonly></div>
                        </div>
                        <div class="ai-field">
                            <label>Email Address</label>
                            <div class="ai-field-inner"><div class="ai-field-icon"><i class="fas fa-envelope"></i></div>
                            <input type="text" value="<?= htmlspecialchars($sc['email']) ?>" readonly></div>
                        </div>
                        <div class="ai-field">
                            <label>Phone Number</label>
                            <div class="ai-field-inner"><div class="ai-field-icon"><i class="fas fa-phone"></i></div>
                            <input type="text" value="<?= htmlspecialchars($sc['phone'] ?? 'Not provided') ?>" readonly></div>
                        </div>
                        <div class="ai-field">
                            <label>Age</label>
                            <div class="ai-field-inner"><div class="ai-field-icon"><i class="fas fa-birthday-cake"></i></div>
                            <input type="text" value="<?= htmlspecialchars($sc['age'] ?? 'Not provided') ?>" readonly></div>
                        </div>
                        <div class="ai-field">
                            <label>Gender</label>
                            <div class="ai-field-inner"><div class="ai-field-icon"><i class="fas fa-venus-mars"></i></div>
                            <input type="text" value="<?= htmlspecialchars(ucfirst($sc['gender'] ?? 'Not provided')) ?>" readonly></div>
                        </div>
                    </div>

                    <p class="ai-group-label"><i class="fas fa-map-marker-alt"></i> Address</p>
                    <div class="ai-form-grid">
                        <div class="ai-field ai-field-full">
                            <label>Street Address</label>
                            <div class="ai-field-inner"><div class="ai-field-icon"><i class="fas fa-road"></i></div>
                            <input type="text" value="<?= htmlspecialchars($sc['address'] ?? 'Not provided') ?>" readonly></div>
                        </div>
                        <div class="ai-field">
                            <label>City</label>
                            <div class="ai-field-inner"><div class="ai-field-icon"><i class="fas fa-city"></i></div>
                            <input type="text" value="<?= htmlspecialchars($sc['city'] ?? 'Not provided') ?>" readonly></div>
                        </div>
                        <div class="ai-field">
                            <label>State / Province</label>
                            <div class="ai-field-inner"><div class="ai-field-icon"><i class="fas fa-map"></i></div>
                            <input type="text" value="<?= htmlspecialchars($sc['state'] ?? 'Not provided') ?>" readonly></div>
                        </div>
                        <div class="ai-field">
                            <label>ZIP / Postal Code</label>
                            <div class="ai-field-inner"><div class="ai-field-icon"><i class="fas fa-mailbox"></i></div>
                            <input type="text" value="<?= htmlspecialchars($sc['zip_code'] ?? 'Not provided') ?>" readonly></div>
                        </div>
                    </div>

                    <p class="ai-group-label"><i class="fas fa-heart-pulse"></i> Health Profile</p>
                    <div class="ai-form-grid">
                        <div class="ai-field ai-field-full">
                            <label>Health Conditions</label>
                            <div class="ai-field-inner"><div class="ai-field-icon" style="align-self:flex-start;padding-top:12px;"><i class="fas fa-notes-medical"></i></div>
                            <textarea rows="3" readonly><?= htmlspecialchars($sc['health_conditions'] ?? 'No health conditions reported') ?></textarea></div>
                        </div>
                        <div class="ai-field ai-field-full">
                            <label>Dietary Restrictions</label>
                            <div class="ai-field-inner"><div class="ai-field-icon" style="align-self:flex-start;padding-top:12px;"><i class="fas fa-ban"></i></div>
                            <textarea rows="3" readonly><?= htmlspecialchars($sc['dietary_restrictions'] ?? 'No dietary restrictions') ?></textarea></div>
                        </div>
                        <div class="ai-field ai-field-full">
                            <label>Health / Nutrition Goals</label>
                            <div class="ai-field-inner"><div class="ai-field-icon" style="align-self:flex-start;padding-top:12px;"><i class="fas fa-bullseye"></i></div>
                            <textarea rows="3" readonly><?= htmlspecialchars($sc['goals'] ?? 'No goals set') ?></textarea></div>
                        </div>
                        <div class="ai-field ai-field-full">
                            <label>Dietician Notes</label>
                            <div class="ai-field-inner"><div class="ai-field-icon" style="align-self:flex-start;padding-top:12px;"><i class="fas fa-clipboard"></i></div>
                            <textarea rows="4" readonly><?= htmlspecialchars($sc['notes'] ?? 'No notes yet') ?></textarea></div>
                        </div>
                    </div>
                </div>

                <!-- ── TAB 2: Food Tracker ── -->
                <div class="ai-tab-panel tab-content <?= $active_tab === 'food-tracker' ? 'active' : '' ?>" id="food-tracker">
                    <div class="ai-section-header">
                        <h2 class="ai-section-title"><i class="fas fa-utensils"></i> Daily Food Intake — <?= date('F j, Y') ?></h2>
                    </div>

                    <!-- Food entries list -->
                    <?php if (empty($food_entries)): ?>
                    <div class="ai-empty" style="padding:48px;border-radius:16px;">
                        <div class="ai-empty-icon" style="width:56px;height:56px;font-size:1.4rem;border-radius:16px;"><i class="fas fa-utensils"></i></div>
                        <h3 style="font-size:1rem;">No entries today</h3>
                        <p>Add food items below to start tracking.</p>
                    </div>
                    <?php else: ?>
                    <div class="ai-food-list">
                        <?php foreach ($food_entries as $food):
                            $mealClass = 'meal-'.($food['meal_type'] ?? 'custom');
                            $mealIcon  = match(strtolower($food['meal_type'] ?? '')) { 
                                'breakfast'=>'fa-coffee',
                                'am snack'=>'fa-apple-alt',
                                'lunch'=>'fa-utensils',
                                'pm snack'=>'fa-cookie',
                                'dinner'=>'fa-moon',
                                'snack'=>'fa-apple-alt', 
                                default=>'fa-bowl-food' 
                            };
                        ?>
                        <div class="ai-food-entry">
                            <div class="ai-food-entry-icon <?= $mealClass ?>"><i class="fas <?= $mealIcon ?>"></i></div>
                            <div class="ai-food-entry-info">
                                <div class="ai-food-entry-name"><?= ucfirst($food['meal_type'] ?? 'Custom') ?>: <?= htmlspecialchars($food['food_name']) ?></div>
                                <div class="ai-food-entry-macros">
                                    <span><b>P</b> <?= $food['protein'] ?? 0 ?>g</span>
                                    <span><b>C</b> <?= $food['carbs'] ?? 0 ?>g</span>
                                    <span><b>F</b> <?= $food['fat'] ?? 0 ?>g</span>
                                </div>
                            </div>
                            <div class="ai-food-entry-kcal"><?= $food['calories'] ?><small> kcal</small></div>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete_food_entry">
                                <input type="hidden" name="client_id" value="<?= htmlspecialchars($sc['id']) ?>">
                                <input type="hidden" name="entry_id" value="<?= htmlspecialchars($food['id']) ?>">
                                <button type="submit" class="ai-food-del-btn" title="Remove"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Add food form -->
                    <div class="ai-add-food-form">
                        <h4><i class="fas fa-plus-circle"></i> Add Food Entry</h4>
                        <form method="POST">
                            <input type="hidden" name="action" value="add_food_entry">
                            <input type="hidden" name="client_id" value="<?= htmlspecialchars($sc['id']) ?>">
                            <div class="ai-add-food-inputs">
                                <select name="meal_type">
                                    <option value="breakfast">Breakfast</option>
                                    <option value="AM Snack">AM Snack</option>
                                    <option value="lunch">Lunch</option>
                                    <option value="PM Snack">PM Snack</option>
                                    <option value="dinner">Dinner</option>
                                    <option value="snack">Snack</option>
                                    <option value="custom" selected>Custom</option>
                                </select>
                                <input type="text" name="food_name" placeholder="Food name" required>
                                <input type="number" step="any" name="calories" placeholder="kcal">
                                <input type="number" step="any" name="protein" placeholder="Protein g">
                                <input type="number" step="any" name="carbs" placeholder="Carbs g">
                                <input type="number" step="any" name="fat" placeholder="Fat g">
                                <button type="submit" class="ai-btn-add"><i class="fas fa-plus"></i> Add</button>
                            </div>
                        </form>
                    </div>

                    <!-- Food mini-stats -->
                    <div class="ai-food-stats stagger d-3">
                        <div class="ai-food-stat">
                            <div class="ai-food-stat-icon" style="color:#f97316;"><i class="fas fa-fire-flame-curved"></i></div>
                            <div class="ai-food-stat-val"><?= $calories_today ?></div>
                            <div class="ai-food-stat-label">Calories Today</div>
                        </div>
                        <div class="ai-food-stat">
                            <div class="ai-food-stat-icon" style="color:#10b981;"><i class="fas fa-bowl-food"></i></div>
                            <div class="ai-food-stat-val"><?= count($food_entries) ?></div>
                            <div class="ai-food-stat-label">Meals Logged</div>
                        </div>
                        <div class="ai-food-stat">
                            <div class="ai-food-stat-icon" style="color:#6366f1;"><i class="fas fa-clock-rotate-left"></i></div>
                            <div class="ai-food-stat-val"><?= date('H:i') ?></div>
                            <div class="ai-food-stat-label">Last Update</div>
                        </div>
                        <div class="ai-food-stat">
                            <div class="ai-food-stat-icon" style="color:#d946ef;"><i class="fas fa-signal"></i></div>
                            <div class="ai-food-stat-val" style="color:#10b981;font-size:1rem;">Active</div>
                            <div class="ai-food-stat-label">Tracking Mode</div>
                        </div>
                    </div>
                </div>

                <!-- ── TAB 3: Body Statistics ── -->
                <div class="ai-tab-panel tab-content <?= $active_tab === 'body-stats' ? 'active' : '' ?>" id="body-stats">
                    <div class="ai-section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
                        <h2 class="ai-section-title" style="margin-bottom: 0;"><i class="fas fa-chart-line"></i> Body Measurements &amp; Statistics</h2>
                        <button class="ai-btn-save" id="editStatsBtn" onclick="toggleStatsEdit()" style="background: var(--accent-green, #10b981); color: white; border: none; padding: 12px 20px; border-radius: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 0.95rem; min-height: 44px; transition: all 0.2s ease;">
                            <i class="fas fa-edit"></i> Edit Measurements
                        </button>
                    </div>

                    <!-- BMI History Chart Section -->
                    <div class="bmi-visual-container">
                        <div class="bmi-status-info">
                            <h4 style="margin: 0 0 5px 0; color: #64748b; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700;">Current BMI</h4>
                            <div class="bmi-big-value"><?= $bmi_val ?: '--' ?></div>
                            <div class="bmi-status-badge status-<?= strtolower($bmi_label) ?>">
                                <?= $bmi_label ?>
                            </div>
                            
                            <div class="clinical-insight-card">
                                <h4 style="margin: 0 0 8px 0; color: #1e293b; font-weight: 700; font-size: 0.8rem; text-transform: uppercase;">Health Insight</h4>
                                <p style="font-size: 0.88rem; color: #64748b; line-height: 1.5; margin: 0;">
                                    <?php 
                                        if ($bmi_label === 'Underweight') echo "Client BMI indicates they may be underweight. Focus on nutrient-dense meals and consistent caloric surplus.";
                                        elseif ($bmi_label === 'Normal') echo "Client is in the healthy BMI range. Focus on weight maintenance and balanced clinical nutrition.";
                                        elseif ($bmi_label === 'Overweight') echo "Client is in the overweight category. Recommend a sustainable caloric deficit and increased physical activity.";
                                        elseif ($bmi_label === 'Obese') echo "Client indicates obesity. Requires a specialized clinical nutrition plan to manage associated health risks.";
                                        else echo "Record the client's height and weight to generate a BMI analysis and clinical insight.";
                                    ?>
                                </p>
                            </div>

                            <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                                <div style="background: #f8fafc; padding: 10px 15px; border-radius: 12px; flex: 1; text-align: center; border: 1px solid #e2e8f0;">
                                    <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; font-weight: 700; margin-bottom: 2px;">Weight</div>
                                    <div style="font-size: 0.95rem; font-weight: 800; color: #1e293b;"><?= $sc['weight'] ?: '--' ?> <small style="font-size: 0.7em; opacity: 0.6;">kg</small></div>
                                </div>
                                <div style="background: #f8fafc; padding: 10px 15px; border-radius: 12px; flex: 1; text-align: center; border: 1px solid #e2e8f0;">
                                    <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; font-weight: 700; margin-bottom: 2px;">Height</div>
                                    <div style="font-size: 0.95rem; font-weight: 800; color: #1e293b;"><?= $sc['height'] ?: '--' ?> <small style="font-size: 0.7em; opacity: 0.6;">cm</small></div>
                                </div>
                            </div>

                            <div style="margin-top: 15px; display: flex; gap: 10px;">
                                
                            </div>
                        </div>
                        
                        <div class="bmi-chart-wrapper">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <h3 style="font-family: 'Outfit'; font-size: 1rem; color: #1e293b; margin: 0; font-weight: 700;"><i class="fas fa-history" style="color: var(--primary); margin-right: 8px;"></i> Progress Statistics</h3>
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

                    <form id="statsForm" method="POST">
                        <input type="hidden" name="action" value="update_body_stats">
                        <input type="hidden" name="client_id" value="<?= htmlspecialchars($sc['id']) ?>">
                        
                        <div class="ai-form-grid">
                            <div class="ai-field">
                                <label>Current Weight (kg)</label>
                                <div class="ai-field-inner">
                                    <div class="ai-field-icon"><i class="fas fa-weight"></i></div>
                                    <input type="number" step="any" name="weight" class="stats-input" value="<?= htmlspecialchars($sc['weight'] ?? '') ?>" readonly>
                                </div>
                            </div>
                            <div class="ai-field">
                                <label>Height (cm)</label>
                                <div class="ai-field-inner">
                                    <div class="ai-field-icon"><i class="fas fa-ruler-vertical"></i></div>
                                    <input type="number" step="any" name="height" class="stats-input" value="<?= htmlspecialchars($sc['height'] ?? '') ?>" readonly>
                                </div>
                            </div>
                            <div class="ai-field">
                                <label>BMI</label>
                                <div class="ai-field-inner">
                                    <div class="ai-field-icon"><i class="fas fa-chart-bar"></i></div>
                                    <input type="text" value="<?= $bmi_val ?: 'Not calculated' ?>" readonly style="background: #f8fafc;">
                                </div>
                                <?php if ($bmi_val): ?><div class="ai-field-hint">Classification: <strong><?= $bmi_label ?></strong></div><?php endif; ?>
                            </div>
                            <div class="ai-field">
                                <label>Optimal Weight Range</label>
                                <div class="ai-field-inner">
                                    <div class="ai-field-icon"><i class="fas fa-bullseye"></i></div>
                                    <input type="text" value="<?= ($optimal_low && $optimal_high) ? "{$optimal_low} – {$optimal_high} kg" : 'Need height data' ?>" readonly style="background: #f8fafc;">
                                </div>
                                <div class="ai-field-hint">Healthy BMI range: 18.5 – 24.9</div>
                            </div>
                            <div class="ai-field">
                                <label>Waist Circumference (cm)</label>
                                <div class="ai-field-inner">
                                    <div class="ai-field-icon"><i class="fas fa-circle-dot"></i></div>
                                    <input type="number" step="any" name="waist_circumference" class="stats-input" value="<?= htmlspecialchars($sc['waist_circumference'] ?? '') ?>" readonly>
                                </div>
                            </div>
                            <div class="ai-field">
                                <label>Hip Circumference (cm)</label>
                                <div class="ai-field-inner">
                                    <div class="ai-field-icon"><i class="fas fa-circle-dot"></i></div>
                                    <input type="number" step="any" name="hip_circumference" class="stats-input" value="<?= htmlspecialchars($sc['hip_circumference'] ?? '') ?>" readonly>
                                </div>
                            </div>
                            <div class="ai-field">
                                <label>Waist-Hip Ratio</label>
                                <div class="ai-field-inner">
                                    <div class="ai-field-icon"><i class="fas fa-arrows-left-right"></i></div>
                                    <input type="text" value="<?= $whr_val ?>" readonly style="background: #f8fafc;">
                                </div>
                                <?php if ($whr_risk !== 'N/A'): ?><div class="ai-field-hint">Risk level: <strong><?= $whr_risk ?></strong></div><?php endif; ?>
                            </div>
                            <div class="ai-field">
                                <label>Client Since</label>
                                <div class="ai-field-inner">
                                    <div class="ai-field-icon"><i class="fas fa-calendar"></i></div>
                                    <input type="text" value="<?= !empty($sc['created_at']) ? date('M j, Y', strtotime($sc['created_at'])) : 'N/A' ?>" readonly style="background: #f8fafc;">
                                </div>
                            </div>
                        </div>

                        <div id="statsActions" style="display:none; margin-top: 25px; gap: 12px; justify-content: flex-end;">
                            <button type="button" class="ai-btn-save" onclick="toggleStatsEdit()" style="background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;">Cancel</button>
                            <button type="submit" class="ai-btn-save"><i class="fas fa-save"></i> Save Measurements</button>
                        </div>
                    </form>

                    <script>
                        function toggleStatsEdit() {
                            const inputs = document.querySelectorAll('.stats-input');
                            const actions = document.getElementById('statsActions');
                            const editBtn = document.getElementById('editStatsBtn');
                            const isReadonly = inputs[0].hasAttribute('readonly');

                            inputs.forEach(input => {
                                if (isReadonly) {
                                    input.removeAttribute('readonly');
                                    input.style.backgroundColor = '#fff';
                                    input.style.borderColor = 'var(--primary)';
                                    input.style.boxShadow = '0 0 0 3px rgba(16,185,129,0.1)';
                                } else {
                                    input.setAttribute('readonly', true);
                                    input.style.backgroundColor = '';
                                    input.style.borderColor = '';
                                    input.style.boxShadow = '';
                                }
                            });

                            actions.style.display = isReadonly ? 'flex' : 'none';
                            editBtn.style.display = isReadonly ? 'none' : 'flex';
                        }

                        // Initialize BMI Chart
                        document.addEventListener('DOMContentLoaded', () => {
                            const bmiData = <?= json_encode($bmi_history_data) ?>;
                            const ctx = document.getElementById('bmiHistoryChart');
                            
                            if (ctx && bmiData.length > 0) {
                                const labels = bmiData.map(item => item.date_label);
                                const data = bmiData.map(item => parseFloat(item.bmi));
                                
                                new Chart(ctx.getContext('2d'), {
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
                                                 suggestedMin: Math.min(...data) - 2,
                                                 suggestedMax: Math.max(...data) + 2,
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
                </div>

                <!-- ── TAB 4: Meal Planner (Custom Clinical NLP Meal Planner) ── -->
                <div class="ai-tab-panel tab-content <?= $active_tab === 'progress' ? 'active' : '' ?>" id="progress">

                    <div class="ai-meal-header" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1.5px solid rgba(226, 232, 240, 0.8); padding-bottom: 15px; flex-wrap: wrap; gap: 15px;">
                        <div>
                            <h2 class="ai-section-title" style="margin:0; font-family:'Outfit',sans-serif; font-size:1.4rem; font-weight:800; color:#1e293b; display:flex; align-items:center; gap:10px;"><i class="fas fa-stethoscope" style="color:#10b981;"></i> Clinical Meal Architect</h2>
                            <p style="margin: 3px 0 0 0; font-size: 0.82rem; color: #64748b; font-weight: 500;">Tailor personalized nutritional blueprints backed by smart clinical insights</p>
                        </div>
                        <button class="ai-btn-save-meal" id="saveMealPlan" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important; color: white !important; border: none !important; font-family:'Outfit',sans-serif !important; font-weight:800 !important; cursor:pointer !important; box-shadow: 0 4px 15px rgba(16,185,129,0.3) !important; padding: 12px 28px !important; border-radius: 14px !important; transition: all 0.3s !important; display: flex; align-items: center; gap: 8px;"><i class="fas fa-save"></i> Save Meal Plan</button>
                    </div>

                    <div class="clinical-meal-planner-container" style="display: flex; gap: 24px; flex-wrap: wrap;">
                        <!-- LEFT COLUMN: AI Recommendations -->
                        <div class="ai-recommendations-panel" style="flex: 1 1 350px; background: rgba(255, 255, 255, 0.65); backdrop-filter: blur(16px); border: 1.5px solid rgba(255, 255, 255, 0.8); border-radius: 24px; padding: 26px; box-shadow: 0 10px 40px rgba(15, 23, 42, 0.04); display: flex; flex-direction: column; gap: 20px; min-height: 480px; position: relative; overflow: hidden;">
                            <div style="position: absolute; top: -100px; right: -100px; width: 200px; height: 200px; background: radial-gradient(circle, rgba(16,185,129,0.06) 0%, transparent 70%); pointer-events: none;"></div>
                            
                            <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1.5px solid rgba(226, 232, 240, 0.8); padding-bottom: 15px; z-index: 1;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; width: 38px; height: 38px; border-radius: 12px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(16,185,129,0.3);"><i class="fas fa-brain"></i></div>
                                    <div>
                                        <h3 style="margin:0; font-family:'Outfit',sans-serif; font-size: 1.05rem; font-weight:800; color:#1e293b;">NutriDeq AI</h3>
                                        <span style="font-size:0.75rem; color:#64748b; font-weight: 600;">Clinical NLP Insights</span>
                                    </div>
                                </div>
                                <span class="ai-status-badge" style="background: rgba(16, 185, 129, 0.1); color: #059669; font-size: 0.7rem; padding: 4px 12px; border-radius: 20px; font-weight: 700; display: flex; align-items: center; gap: 5px;"><span style="width: 6px; height: 6px; background: #10b981; border-radius: 50%; display: inline-block; animation: pulse 1.5s infinite;"></span> Active</span>
                            </div>

                            <div id="ai-recommendation-content" style="flex: 1; overflow-y: auto; max-height: 480px; font-size: 0.85rem; line-height: 1.6; color: #334155; position: relative; z-index: 1; padding-right: 5px;">
                                <div style="text-align: center; padding: 40px 20px; display: flex; flex-direction: column; align-items: center; gap: 16px;">
                                    <div class="ai-pulse-icon" style="width: 64px; height: 64px; border-radius: 50%; background: rgba(16, 185, 129, 0.08); display: flex; align-items: center; justify-content: center; color: #10b981; font-size: 1.5rem;"><i class="fas fa-stethoscope"></i></div>
                                    <div style="font-family:'Outfit',sans-serif; font-size:0.95rem; font-weight:700; color:#1e293b;">Awaiting Assessment</div>
                                    <p style="color:#64748b; margin: 0; font-size:0.8rem; max-width: 250px; line-height: 1.5;">Generate intelligent clinical guidelines based on client metrics and health profiles.</p>
                                </div>
                            </div>

                            <button id="generateAiRecommendationBtn" onclick="generateAiRecommendation()" style="width: 100%; padding: 14px; border-radius: 12px; border: none; background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: white; font-family:'Outfit',sans-serif; font-size: 0.88rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 4px 15px rgba(15,23,42,0.15); transition: all 0.3s; z-index: 1;">
                                <i class="fas fa-sparkles" style="color: #10b981;"></i> Generate Clinical Guidance
                            </button>
                        </div>

                        <!-- RIGHT COLUMN: Interactive Custom Meal Planner Workspace -->
                        <div class="meal-planner-workspace" style="flex: 2 1 500px; background: white; border: 1px solid #e2e8f0; border-radius: 24px; padding: 26px; box-shadow: 0 10px 40px rgba(0,0,0,0.02); display: flex; flex-direction: column; gap: 20px; position: relative;">
                            <div>
                                <h3 style="margin:0; font-family:'Outfit',sans-serif; font-size: 1.15rem; font-weight:800; color:#1e293b;">Daily Meal Architect</h3>
                                <span style="font-size:0.78rem; color:#64748b; font-weight: 500;">Select target meal times to view, modify, or add curated food items</span>
                            </div>

                            <!-- Meal Type Selector Tabs -->
                            <div class="meal-tabs-wrapper" style="display: flex; border-bottom: 2px solid #f1f5f9; padding-bottom: 2px; gap: 4px; overflow-x: auto; scrollbar-width: none;">
                                <button class="meal-tab active" data-meal="breakfast" onclick="switchMealTab('breakfast')" style="padding: 10px 16px; background: none; border: none; font-family: 'Outfit', sans-serif; font-size: 0.85rem; font-weight: 800; color: #10b981; border-bottom: 2.5px solid #10b981; cursor: pointer; transition: all 0.2s; white-space: nowrap;"><i class="fas fa-egg" style="margin-right: 5px;"></i> Breakfast</button>
                                <button class="meal-tab" data-meal="am_snack" onclick="switchMealTab('am_snack')" style="padding: 10px 16px; background: none; border: none; font-family: 'Outfit', sans-serif; font-size: 0.85rem; font-weight: 700; color: #64748b; border-bottom: 2.5px solid transparent; cursor: pointer; transition: all 0.2s; white-space: nowrap;"><i class="fas fa-cookie" style="margin-right: 5px;"></i> AM Snack</button>
                                <button class="meal-tab" data-meal="lunch" onclick="switchMealTab('lunch')" style="padding: 10px 16px; background: none; border: none; font-family: 'Outfit', sans-serif; font-size: 0.85rem; font-weight: 700; color: #64748b; border-bottom: 2.5px solid transparent; cursor: pointer; transition: all 0.2s; white-space: nowrap;"><i class="fas fa-hamburger" style="margin-right: 5px;"></i> Lunch</button>
                                <button class="meal-tab" data-meal="pm_snack" onclick="switchMealTab('pm_snack')" style="padding: 10px 16px; background: none; border: none; font-family: 'Outfit', sans-serif; font-size: 0.85rem; font-weight: 700; color: #64748b; border-bottom: 2.5px solid transparent; cursor: pointer; transition: all 0.2s; white-space: nowrap;"><i class="fas fa-apple-alt" style="margin-right: 5px;"></i> PM Snack</button>
                                <button class="meal-tab" data-meal="dinner" onclick="switchMealTab('dinner')" style="padding: 10px 16px; background: none; border: none; font-family: 'Outfit', sans-serif; font-size: 0.85rem; font-weight: 700; color: #64748b; border-bottom: 2.5px solid transparent; cursor: pointer; transition: all 0.2s; white-space: nowrap;"><i class="fas fa-utensils" style="margin-right: 5px;"></i> Dinner</button>
                                <button class="meal-tab" data-meal="snack" onclick="switchMealTab('snack')" style="padding: 10px 16px; background: none; border: none; font-family: 'Outfit', sans-serif; font-size: 0.85rem; font-weight: 700; color: #64748b; border-bottom: 2.5px solid transparent; cursor: pointer; transition: all 0.2s; white-space: nowrap;"><i class="fas fa-drumstick-bite" style="margin-right: 5px;"></i> Snack</button>
                            </div>

                            <!-- Unified FCT Food Selector & Custom Food Action Grid -->
                            <div class="ai-planner-selector-grid" style="background: #f8fafc; border-radius: 18px; padding: 20px; display: grid; grid-template-columns: 2fr 1.2fr; gap: 16px; align-items: end; border: 1.5px dashed #cbd5e1; flex-wrap: wrap;">
                                <div style="position: relative;">
                                    <label style="display: block; font-size: 0.75rem; font-weight: 800; color: #475569; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;"><i class="fas fa-search" style="margin-right: 4px; color:#10b981;"></i> Select Food from FCT Database</label>
                                    <select id="fct-planner-select" class="form-control" style="width: 100%;">
                                        <option value="">Search food item by name...</option>
                                    </select>
                                </div>
                                <div>
                                    <button type="button" onclick="openCustomFoodModal()" style="width:100%; padding: 12.5px; border-radius: 12px; border: 1.5px solid #cbd5e1; background: white; color: #334155; font-family:'Outfit',sans-serif; font-weight: 700; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02);"><i class="fas fa-plus-circle" style="color:#10b981; font-size: 1rem;"></i> Create Custom Food</button>
                                </div>
                            </div>

                            <!-- Planned Foods Container for Selected Meal Time -->
                            <div style="display: flex; flex-direction: column; gap: 14px;">
                                <h4 style="margin: 0; font-family:'Outfit',sans-serif; font-size: 0.88rem; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 6px;"><i class="fas fa-utensils" style="color:#10b981;"></i> Foods Scheduled for <span id="current-meal-name-display" style="text-transform: capitalize; color:#10b981;">Breakfast</span></h4>
                                <div id="plannerMealItemsList" style="min-height: 120px; display: flex; flex-direction: column; gap: 10px; max-height: 320px; overflow-y: auto; padding-right: 5px;">
                                    <!-- Dynamically populated selected items specific to this meal time -->
                                </div>
                            </div>

                            <!-- Dynamic Daily/Meal Telemetry -->
                            <div style="margin-top: auto; border-top: 1.5px solid #f1f5f9; padding-top: 20px; display: flex; flex-direction: column; gap: 16px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="font-family:'Outfit',sans-serif; font-size: 0.85rem; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: 0.5px;">Planned Daily Macros Totals</span>
                                    <span id="global-telemetry-calories" style="font-family:'Outfit',sans-serif; font-size: 1rem; font-weight: 900; color: #ef4444; background: rgba(239, 68, 68, 0.08); padding: 5px 14px; border-radius: 8px;">0 kcal</span>
                                </div>
                                
                                <div class="ai-planner-telemetry-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
                                    <div style="background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.1); border-radius: 14px; padding: 12px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                        <div id="global-telemetry-protein" style="font-family:'Outfit',sans-serif; font-size: 1.1rem; font-weight: 850; color: #3b82f6; line-height: 1.2;">0.0g</div>
                                        <div style="font-size: 0.72rem; font-weight: 700; color: #64748b; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Protein</div>
                                    </div>
                                    <div style="background: rgba(245, 158, 11, 0.05); border: 1px solid rgba(245, 158, 11, 0.1); border-radius: 14px; padding: 12px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                        <div id="global-telemetry-carbs" style="font-family:'Outfit',sans-serif; font-size: 1.1rem; font-weight: 850; color: #f59e0b; line-height: 1.2;">0.0g</div>
                                        <div style="font-size: 0.72rem; font-weight: 700; color: #64748b; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Carbohydrates</div>
                                    </div>
                                    <div style="background: rgba(139, 92, 246, 0.05); border: 1px solid rgba(139, 92, 246, 0.1); border-radius: 14px; padding: 12px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                        <div id="global-telemetry-fat" style="font-family:'Outfit',sans-serif; font-size: 1.1rem; font-weight: 850; color: #8b5cf6; line-height: 1.2;">0.0g</div>
                                        <div style="font-size: 0.72rem; font-weight: 700; color: #64748b; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Dietary Fats</div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div><!-- /.ai-tabs-wrap -->

            <?php endif; ?>
        </div><!-- /.page-container -->
    </main>
</div><!-- /.main-layout -->
        <script>
            // Master selected items array
            let selectedItems = [];
            let activeMealTab = 'breakfast';
            let plannerChoicesInstance = null;

            // Dynamically inject custom premium animations
            const plannerStyles = document.createElement('style');
            plannerStyles.innerHTML = `
                @keyframes slideUp {
                    from { transform: translateY(12px); opacity: 0; }
                    to { transform: translateY(0); opacity: 1; }
                }
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                @keyframes pulseGlow {
                    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
                    70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
                    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
                }
                .planner-item-card:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05) !important;
                    border-color: #cbd5e1 !important;
                }
                .ai-loader-glow {
                    animation: spin 1s linear infinite, pulseGlow 2s infinite;
                }
            `;
            document.head.appendChild(plannerStyles);

            // Global Meal Tab Switcher
            function switchMealTab(mealType) {
                activeMealTab = mealType;
                
                // Update active styles on meal tabs
                document.querySelectorAll('.meal-tab').forEach(btn => {
                    if (btn.getAttribute('data-meal') === mealType) {
                        btn.classList.add('active');
                        btn.style.color = '#10b981';
                        btn.style.borderBottom = '2.5px solid #10b981';
                        btn.style.fontWeight = '800';
                    } else {
                        btn.classList.remove('active');
                        btn.style.color = '#64748b';
                        btn.style.borderBottom = '2.5px solid transparent';
                        btn.style.fontWeight = '700';
                    }
                });

                // Update display label
                const nameDisplay = document.getElementById('current-meal-name-display');
                if (nameDisplay) {
                    nameDisplay.textContent = mealType.replace('_', ' ');
                }

                // Refresh scheduled foods list for this tab
                updateSelectedItemsList();
            }

            // Custom Food Modal controls
            function openCustomFoodModal() {
                const modal = document.getElementById('customFoodModal');
                if (modal) {
                    modal.style.display = 'flex';
                }
            }

            function closeCustomFoodModal() {
                const modal = document.getElementById('customFoodModal');
                if (modal) {
                    modal.style.display = 'none';
                    document.getElementById('customFoodPlannerForm').reset();
                }
            }

            function handleCustomFoodSubmit(e) {
                e.preventDefault();
                const name = document.getElementById('planner_food_name').value.trim();
                const size = document.getElementById('planner_serving_size').value;
                const unit = document.getElementById('planner_serving_unit').value;
                const calories = parseFloat(document.getElementById('planner_calories').value || 0);
                const protein = parseFloat(document.getElementById('planner_protein').value || 0);
                const carbs = parseFloat(document.getElementById('planner_carbs').value || 0);
                const fat = parseFloat(document.getElementById('planner_fat').value || 0);

                if (!name) return;

                // Add to selected items array as a custom group food
                addSelectedItem('Custom Foods', name, {
                    exchange: `${size} ${unit}`,
                    weight: `${size} ${unit}`,
                    energy: calories,
                    protein: protein,
                    fat: fat,
                    cho: carbs
                }, activeMealTab);

                closeCustomFoodModal();
            }

            // Add selected food item to master list
            function addSelectedItem(group, name, data, mealType = activeMealTab) {
                const item = {
                    group: group,
                    name: name,
                    meal_type: mealType,
                    data: {
                        exchange: data.exchange || '1 exchange',
                        weight: data.weight || '-',
                        energy: data.energy || '0',
                        protein: data.protein || '0',
                        fat: data.fat || '0',
                        cho: data.cho || '0'
                    },
                    id: Date.now() + Math.random()
                };
                selectedItems.push(item);
                updateSelectedItemsList();
                updateNutritionSummary();
            }

            // Remove selected food item from master list
            function removeSelectedItem(id) {
                selectedItems = selectedItems.filter(item => item.id !== id);
                updateSelectedItemsList();
                updateNutritionSummary();
            }

            // Render selected food list for the ACTIVE meal tab
            function updateSelectedItemsList() {
                const container = document.getElementById('plannerMealItemsList');
                if (!container) return;

                // Filter items matching currently selected meal tab
                const currentMealItems = selectedItems.filter(item => item.meal_type === activeMealTab);

                if (currentMealItems.length === 0) {
                    container.innerHTML = `
                        <div class="no-selection-card" style="border: 2px dashed #e2e8f0; border-radius: 16px; padding: 30px; text-align: center; color: #94a3b8; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; background: #fafafa;">
                            <i class="fas fa-drumstick-bite" style="font-size: 1.8rem; color: #cbd5e1;"></i>
                            <span style="font-size: 0.82rem; font-weight: 600;">No food scheduled for this meal time</span>
                            <span style="font-size: 0.72rem; color: #cbd5e1;">Search the database or create a custom item to begin planning</span>
                        </div>
                    `;
                    return;
                }

                // Render list with absolute aesthetic premium layout
                container.innerHTML = currentMealItems.map(item => {
                    let icon = 'fa-utensils';
                    let badgeColor = 'rgba(16, 185, 129, 0.1)';
                    let textColor = '#059669';

                    if (item.group.toLowerCase().includes('vegetable')) {
                        icon = 'fa-carrot';
                        badgeColor = 'rgba(16, 185, 129, 0.1)';
                        textColor = '#059669';
                    } else if (item.group.toLowerCase().includes('fruit')) {
                        icon = 'fa-apple-alt';
                        badgeColor = 'rgba(239, 68, 68, 0.08)';
                        textColor = '#ef4444';
                    } else if (item.group.toLowerCase().includes('meat')) {
                        icon = 'fa-drumstick-bite';
                        badgeColor = 'rgba(139, 92, 246, 0.08)';
                        textColor = '#8b5cf6';
                    } else if (item.group.toLowerCase().includes('rice') || item.group.toLowerCase().includes('grain')) {
                        icon = 'fa-bread-slice';
                        badgeColor = 'rgba(245, 158, 11, 0.08)';
                        textColor = '#d97706';
                    } else if (item.group.toLowerCase().includes('milk') || item.group.toLowerCase().includes('dairy')) {
                        icon = 'fa-wine-bottle';
                        badgeColor = 'rgba(59, 130, 246, 0.08)';
                        textColor = '#2563eb';
                    } else if (item.group.toLowerCase().includes('custom')) {
                        icon = 'fa-plus-circle';
                        badgeColor = 'rgba(75, 85, 99, 0.08)';
                        textColor = '#4b5563';
                    }

                    return `
                        <div class="planner-item-card" style="background: white; border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.015); transition: all 0.2s; animation: slideUp 0.2s ease-out;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 38px; height: 38px; border-radius: 10px; background: ${badgeColor}; color: ${textColor}; display: flex; align-items: center; justify-content: center; font-size: 1rem;"><i class="fas ${icon}"></i></div>
                                <div>
                                    <div style="font-family:'Outfit',sans-serif; font-size: 0.85rem; font-weight: 700; color: #1e293b;">${item.name}</div>
                                    <div style="font-size: 0.72rem; color: #64748b; font-weight: 600; display: flex; align-items: center; gap: 6px; margin-top: 2px;">
                                        <span style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; text-transform: uppercase;">${item.group}</span>
                                        <span>•</span>
                                        <span>${item.data.exchange}</span>
                                        <span>•</span>
                                        <span style="color:#ef4444; font-weight:700;">${item.data.energy} kcal</span>
                                    </div>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="font-size: 0.75rem; text-align: right; color: #475569; font-weight: 700; display: flex; gap: 8px;">
                                    <span style="color: #3b82f6;">P: ${parseFloat(item.data.protein).toFixed(1)}g</span>
                                    <span style="color: #f59e0b;">C: ${parseFloat(item.data.cho).toFixed(1)}g</span>
                                    <span style="color: #8b5cf6;">F: ${parseFloat(item.data.fat).toFixed(1)}g</span>
                                </div>
                                <button onclick="removeSelectedItem(${item.id})" style="border: none; background: rgba(239, 68, 68, 0.08); color: #ef4444; width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;"><i class="fas fa-trash-alt" style="font-size: 0.82rem;"></i></button>
                            </div>
                        </div>
                    `;
                }).join('');
            }

            // Sum and render overall daily telemetry totals (Energy, Protein, Carbs, Fat)
            function updateNutritionSummary() {
                const totalEnergy = selectedItems.reduce((sum, item) => sum + Math.round(parseFloat(item.data.energy || 0)), 0);
                const totalProtein = selectedItems.reduce((sum, item) => sum + parseFloat(item.data.protein || 0), 0);
                const totalFat = selectedItems.reduce((sum, item) => sum + parseFloat(item.data.fat || 0), 0);
                const totalCHO = selectedItems.reduce((sum, item) => sum + parseFloat(item.data.cho || 0), 0);

                // Update planned daily macros UI display
                const calorieDisplay = document.getElementById('global-telemetry-calories');
                if (calorieDisplay) calorieDisplay.textContent = `${totalEnergy} kcal`;

                const proteinDisplay = document.getElementById('global-telemetry-protein');
                if (proteinDisplay) proteinDisplay.textContent = `${totalProtein.toFixed(1)}g`;

                const carbsDisplay = document.getElementById('global-telemetry-carbs');
                if (carbsDisplay) carbsDisplay.textContent = `${totalCHO.toFixed(1)}g`;

                const fatDisplay = document.getElementById('global-telemetry-fat');
                if (fatDisplay) fatDisplay.textContent = `${totalFat.toFixed(1)}g`;
            }

            // Trigger AI Clinical Recommendation NLP Insights
            function generateAiRecommendation() {
                const clientId = <?php echo $selected_client ? (int)$selected_client['id'] : 'null'; ?>;
                if (!clientId) {
                    alert('Error: Please select a client first.');
                    return;
                }

                const container = document.getElementById('ai-recommendation-content');
                if (!container) return;

                // Show dynamic glowing/pulsing state loader
                container.innerHTML = `
                    <div style="text-align: center; padding: 60px 20px; display: flex; flex-direction: column; align-items: center; gap: 20px;">
                        <div class="ai-loader-glow" style="width: 56px; height: 56px; border-radius: 50%; border: 4px solid rgba(16, 185, 129, 0.15); border-top-color: #10b981; animation: spin 1s linear infinite;"></div>
                        <div style="font-family:'Outfit',sans-serif; font-size: 0.9rem; font-weight: 800; color: #1e293b;">Analyzing Client Biomarkers...</div>
                        <p style="color: #64748b; margin: 0; font-size: 0.78rem; max-width: 250px; line-height: 1.5;">Our NLP intelligence engine is examining clinical thresholds, historical statistics, and metabolic guidelines...</p>
                    </div>
                `;

                fetch(BASE_URL + 'handlers/generate_ai_recommendation.php?client_id=' + clientId)
                    .then(r => r.json())
                    .then(res => {
                        if (res.success && res.recommendation) {
                            const reportDiv = document.createElement('div');
                            reportDiv.className = 'ai-report-container';
                            reportDiv.style.animation = 'fadeIn 0.4s ease-out';
                            reportDiv.style.fontFamily = "'Outfit', sans-serif";
                            reportDiv.innerHTML = res.recommendation;
                            container.innerHTML = '';
                            container.appendChild(reportDiv);
                        } else {
                            container.innerHTML = `
                                <div style="text-align: center; padding: 40px 20px; color: #ef4444; display: flex; flex-direction: column; align-items: center; gap: 12px;">
                                    <i class="fas fa-exclamation-triangle" style="font-size: 1.8rem;"></i>
                                    <div style="font-weight: 700; font-size:0.9rem;">Analysis Timeout</div>
                                    <p style="color: #64748b; margin: 0; font-size: 0.78rem; max-width: 260px; line-height: 1.5;">${res.message || res.error || 'The clinical engine could not gather sufficient client telemetry.'}</p>
                                </div>
                            `;
                        }
                    })
                    .catch(err => {
                        console.error('Error triggering clinical NLP engine:', err);
                        container.innerHTML = `
                            <div style="text-align: center; padding: 40px 20px; color: #ef4444; display: flex; flex-direction: column; align-items: center; gap: 12px;">
                                <i class="fas fa-exclamation-triangle" style="font-size: 1.8rem;"></i>
                                <div style="font-weight: 700; font-size:0.9rem;">Intelligence Pipeline Offline</div>
                                <p style="color: #64748b; margin: 0; font-size: 0.78rem; max-width: 260px; line-height: 1.5;">${err.message || 'Failed to reach clinical recommendations server. Please verify your connection.'}</p>
                            </div>
                        `;
                    });
            }

            // Global Tab Switching Function — uses both class AND inline display for reliability
            function switchTab(tabId) {
                const container = document.querySelector('.ai-tabs-wrap');
                if (!container) {
                    console.warn('switchTab: .ai-tabs-wrap container not found');
                    return;
                }
                const allPanels  = container.querySelectorAll('.tab-content');
                const allButtons = container.querySelectorAll('.tab[data-tab]');
                const target     = document.getElementById(tabId);
                const activeBtn  = container.querySelector(`.tab[data-tab="${tabId}"]`);

                if (!target || !activeBtn) {
                    console.warn('switchTab: panel or button not found for', tabId);
                    return;
                }

                // Hide all panels
                allPanels.forEach(p => {
                    p.classList.remove('active');
                    p.style.display = 'none';
                });

                // Deactivate all buttons
                allButtons.forEach(b => b.classList.remove('active'));

                // Show target panel
                target.classList.add('active');
                target.style.display = 'block';

                // Activate target button
                activeBtn.classList.add('active');

                // Persist
                sessionStorage.setItem('activeTab', tabId);
                const url = new URL(window.location);
                url.searchParams.set('tab', tabId);
                window.history.replaceState({}, '', url);
            }

            // SINGLE DOMContentLoaded - All functionality here
            document.addEventListener('DOMContentLoaded', function () {

                // Ensure all tab panels start hidden, then activate the right one
                const container = document.querySelector('.ai-tabs-wrap');
                if (container) {
                    const allPanels = container.querySelectorAll('.tab-content');
                    allPanels.forEach(p => { p.style.display = 'none'; p.classList.remove('active'); });
                }

                const urlParams  = new URLSearchParams(window.location.search);
                const urlTab     = urlParams.get('tab');
                const savedTab   = sessionStorage.getItem('activeTab');
                const phpTab     = '<?php echo htmlspecialchars($active_tab); ?>';
                const validTabs  = ['personal-info', 'food-tracker', 'body-stats', 'progress'];

                // Priority: URL param > sessionStorage > PHP default > first tab
                let targetTab = urlTab || savedTab || phpTab || 'personal-info';
                if (!validTabs.includes(targetTab)) targetTab = 'personal-info';

                switchTab(targetTab);

                // Save meal plan functionality
                const saveMealPlanBtn = document.getElementById('saveMealPlan');
                if (saveMealPlanBtn) {
                    saveMealPlanBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        if (selectedItems.length === 0) {
                            alert('Please select some food items before saving.');
                            return;
                        }

                        const clientId = <?php echo $selected_client ? (int)$selected_client['id'] : 'null'; ?>;
                        const staffId = <?php echo isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 'null'; ?>;

                        if (!clientId) {
                            alert('Error: No client selected.');
                            return;
                        }

                        const totalEnergy = selectedItems.reduce((sum, item) => sum + Math.round(parseFloat(item.data.energy || 0)), 0);
                        const totalProtein = selectedItems.reduce((sum, item) => sum + parseFloat(item.data.protein || 0), 0);
                        const totalFat = selectedItems.reduce((sum, item) => sum + parseFloat(item.data.fat || 0), 0);
                        const totalCHO = selectedItems.reduce((sum, item) => sum + parseFloat(item.data.cho || 0), 0);

                        const mealPlanData = {
                            client_id: clientId,
                            staff_id: staffId,
                            items: selectedItems,
                            total_energy: totalEnergy,
                            total_protein: totalProtein,
                            total_fat: totalFat,
                            total_cho: totalCHO
                        };


                        saveMealPlanBtn.disabled = true;
                        saveMealPlanBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving Blueprint...';

                        fetch(BASE_URL + 'handlers/save_meal_plan.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(mealPlanData)
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    alert(data.message);
                                    window.location.reload();
                                } else {
                                    alert('Error saving meal plan: ' + data.message);
                                    saveMealPlanBtn.disabled = false;
                                    saveMealPlanBtn.innerHTML = '<i class="fas fa-save"></i> Save Meal Plan';
                                }
                            })
                            .catch(error => {
                                console.error('Error saving meal plan:', error);
                                alert('Error saving meal plan.');
                                saveMealPlanBtn.disabled = false;
                                saveMealPlanBtn.innerHTML = '<i class="fas fa-save"></i> Save Meal Plan';
                            });
                    });
                }

                // Initialize FCT Choices.js Unified Dropdown
                const plannerSelectEl = document.getElementById('fct-planner-select');
                if (plannerSelectEl) {
                    plannerChoicesInstance = new Choices(plannerSelectEl, {
                        searchEnabled: true,
                        itemSelectText: '',
                        shouldSort: false,
                        placeholder: true,
                        position: 'auto'
                    });

                    // Fetch FCT data, flat map it to make it globally searchable by name
                    fetch(BASE_URL + 'handlers/get_fct_foods.php')
                        .then(r => r.json())
                        .then(res => {
                            if (res.success && res.data) {
                                const flatItems = [];
                                const groupNames = {
                                    vegetable: 'Vegetables',
                                    fruit: 'Fruits',
                                    milk: 'Milk',
                                    rice: 'Rice & Grains',
                                    meat: 'Meat & Fish',
                                    fat: 'Fats & Oils'
                                };

                                for (const [key, items] of Object.entries(res.data)) {
                                    const grpName = groupNames[key] || key;
                                    items.forEach(item => {
                                        flatItems.push({
                                            value: item.id.toString(),
                                            label: `[${grpName}] ${item.food_name}`,
                                            customProperties: {
                                                group: grpName,
                                                food_name: item.food_name,
                                                exchange: item.exchange || '1 exchange',
                                                weight: item.serving_size || '-',
                                                energy: item.energy || '0',
                                                protein: item.protein || '0',
                                                fat: item.fat || '0',
                                                cho: item.carbohydrates || '0'
                                            }
                                        });
                                    });
                                }

                                plannerChoicesInstance.clearChoices();
                                plannerChoicesInstance.setChoices([
                                    { value: '', label: 'Search food item by name...', disabled: true, selected: true },
                                    ...flatItems
                                ], 'value', 'label', false);
                            }
                        })
                        .catch(err => console.error("Error fetching unified FCT library:", err));

                    // Listen to choices selection event
                    plannerSelectEl.addEventListener('change', function (e) {
                        const choice = plannerChoicesInstance.getValue();
                        if (choice && choice.value !== '') {
                            const props = choice.customProperties;
                            if (props) {
                                addSelectedItem(
                                    props.group,
                                    props.food_name,
                                    {
                                        exchange: props.exchange,
                                        weight: props.weight,
                                        energy: props.energy,
                                        protein: props.protein,
                                        fat: props.fat,
                                        cho: props.cho
                                    },
                                    activeMealTab
                                );
                            }
                            // Reset Choices dropdown state immediately
                            plannerChoicesInstance.removeActiveItems();
                            plannerChoicesInstance.setChoiceByValue('');
                        }
                    });
                }

                // Render empty initial meal planned items list
                switchMealTab('breakfast');

                // Mobile sidebar toggle fallback
                if (window.innerWidth <= 576) {
                    const header = document.querySelector('.header');
                    if (header) {
                        const toggleBtn = document.createElement('button');
                        toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
                        toggleBtn.className = 'mobile-nav-toggle';
                        toggleBtn.style.cssText = 'width: 40px; height: 40px; border-radius: 50%; background: white; display: flex; align-items: center; justify-content: center; box-shadow: var(--shadow); cursor: pointer; border: none; margin-right: 15px;';
                        toggleBtn.addEventListener('click', function () {
                            const sidebar = document.querySelector('.sidebar');
                            if (sidebar) {
                                sidebar.classList.toggle('active');
                                if (sidebar.classList.contains('active')) {
                                    sidebar.style.transform = 'translateX(0)';
                                } else {
                                    sidebar.style.transform = 'translateX(-100%)';
                                }
                            }
                        });
                        header.prepend(toggleBtn);
                    }
                }

            });
        </script>
        <!-- ── CUSTOM FOOD OVERLAY MODAL ── -->
    <div id="customFoodModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; transition: all 0.3s ease;">
        <div class="custom-food-modal-card" style="background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1.5px solid rgba(255, 255, 255, 0.7); width: 100%; max-width: 520px; border-radius: 28px; box-shadow: 0 20px 50px rgba(15, 23, 42, 0.15); overflow: hidden; animation: zoomIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);">
            <div style="padding: 24px; border-bottom: 1.5px solid rgba(226, 232, 240, 0.8); display: flex; align-items: center; justify-content: space-between; background: linear-gradient(135deg, rgba(255, 255, 255, 0.4) 0%, rgba(255, 255, 255, 0.1) 100%);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; width: 36px; height: 36px; border-radius: 12px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(16,185,129,0.3);"><i class="fas fa-plus"></i></div>
                    <h3 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 800; color: #1e293b;">Create Custom Food</h3>
                </div>
                <button type="button" onclick="closeCustomFoodModal()" style="border: none; background: rgba(239, 68, 68, 0.08); color: #ef4444; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;"><i class="fas fa-times"></i></button>
            </div>
            
            <form id="customFoodPlannerForm" onsubmit="handleCustomFoodSubmit(event)" style="padding: 24px; display: flex; flex-direction: column; gap: 18px;">
                <div class="ai-field" style="margin-bottom: 0;">
                    <label style="font-size: 0.78rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 6px; display: block;">Food Name</label>
                    <div class="ai-field-inner" style="position: relative;">
                        <div class="ai-field-icon" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8;"><i class="fas fa-bowl-food"></i></div>
                        <input type="text" id="planner_food_name" required placeholder="e.g. Grilled Chicken Breast (No Oil)" style="width: 100%; padding: 12px 16px 12px 42px; border: 1.5px solid #e2e8f0; border-radius: 12px; font-family: inherit; font-size: 0.9rem; font-weight: 600; color: #1e293b; background: white; transition: all 0.2s; outline: none;">
                    </div>
                </div>

                <div style="display: flex; gap: 14px;">
                    <div class="ai-field" style="flex: 1; margin-bottom: 0;">
                        <label style="font-size: 0.78rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 6px; display: block;">Serving Size</label>
                        <div class="ai-field-inner">
                            <input type="number" step="any" id="planner_serving_size" required placeholder="e.g. 100" style="width: 100%; padding: 12px 16px; border: 1.5px solid #e2e8f0; border-radius: 12px; font-family: inherit; font-size: 0.9rem; font-weight: 600; color: #1e293b; background: white; transition: all 0.2s; outline: none;">
                        </div>
                    </div>
                    <div class="ai-field" style="flex: 1; margin-bottom: 0;">
                        <label style="font-size: 0.78rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 6px; display: block;">Serving Unit</label>
                        <div class="ai-field-inner">
                            <select id="planner_serving_unit" style="width: 100%; padding: 12px 16px; border: 1.5px solid #e2e8f0; border-radius: 12px; font-family: inherit; font-size: 0.9rem; font-weight: 600; color: #1e293b; background: white; transition: all 0.2s; outline: none; cursor: pointer;">
                                <option value="grams">grams (g)</option>
                                <option value="pcs">pieces (pcs)</option>
                                <option value="cup">cup</option>
                                <option value="tbsp">tbsp</option>
                                <option value="tsp">tsp</option>
                                <option value="ml">ml</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; background: rgba(248, 250, 252, 0.6); padding: 16px; border-radius: 18px; border: 1px solid rgba(226, 232, 240, 0.8);">
                    <div class="ai-field" style="margin-bottom: 0;">
                        <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">Calories (kcal)</label>
                        <input type="number" step="any" id="planner_calories" placeholder="0" style="width: 100%; padding: 10px 12px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-family: inherit; font-size: 0.88rem; font-weight: 600; color: #1e293b; background: white; transition: all 0.2s; outline: none;">
                    </div>
                    <div class="ai-field" style="margin-bottom: 0;">
                        <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">Protein (g)</label>
                        <input type="number" step="any" id="planner_protein" placeholder="0" style="width: 100%; padding: 10px 12px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-family: inherit; font-size: 0.88rem; font-weight: 600; color: #1e293b; background: white; transition: all 0.2s; outline: none;">
                    </div>
                    <div class="ai-field" style="margin-bottom: 0;">
                        <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">Carbs (g)</label>
                        <input type="number" step="any" id="planner_carbs" placeholder="0" style="width: 100%; padding: 10px 12px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-family: inherit; font-size: 0.88rem; font-weight: 600; color: #1e293b; background: white; transition: all 0.2s; outline: none;">
                    </div>
                    <div class="ai-field" style="margin-bottom: 0;">
                        <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">Fat (g)</label>
                        <input type="number" step="any" id="planner_fat" placeholder="0" style="width: 100%; padding: 10px 12px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-family: inherit; font-size: 0.88rem; font-weight: 600; color: #1e293b; background: white; transition: all 0.2s; outline: none;">
                    </div>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 6px;">
                    <button type="button" onclick="closeCustomFoodModal()" style="flex: 1; padding: 12px; border-radius: 12px; border: 1.5px solid #e2e8f0; background: white; color: #64748b; font-family: 'Outfit', sans-serif; font-size: 0.88rem; font-weight: 700; cursor: pointer; transition: all 0.2s; min-height: 44px;">Cancel</button>
                    <button type="submit" style="flex: 2; padding: 12px; border-radius: 12px; border: none; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; font-family: 'Outfit', sans-serif; font-size: 0.88rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 15px rgba(16,185,129,0.25); transition: all 0.2s; min-height: 44px;"><i class="fas fa-plus"></i> Add to Meal Plan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── CSS KEYFRAMES FOR TRANSITIONS ── -->
    <style>
        @keyframes zoomIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>

</body>
</html>
