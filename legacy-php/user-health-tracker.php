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

$database = new Database();
$pdo = $database->getConnection();

// Basic auth check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    header("Location: login-logout/NutriDeqN-Login.php");
    exit();
}

// Get logged-in user's information
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$user_email = $_SESSION['user_email'] ?? '';
$user_role = $_SESSION['user_role'] ?? 'regular';

// Regular users must not be allowed to view other clients via client_id param
// Staff pages that allow viewing clients should use anthropometric-information.php
if ($user_role !== 'staff' && isset($_GET['client_id'])) {
    // ignore client_id for regular users
    unset($_GET['client_id']);
}

try {
    // Try to find a client profile associated with this user (by user_id)
    $user_client_query = $pdo->prepare("
        SELECT id, user_id, name, email, phone, address, city, state, zip_code, age, date_of_birth, gender, weight, height, 
               waist_circumference, hip_circumference, health_conditions, dietary_restrictions, goals, notes, status, created_at
        FROM clients 
        WHERE user_id = ?
        LIMIT 1
    ");
    $user_client_query->execute([$user_id]);
    $user_client = $user_client_query->fetch(PDO::FETCH_ASSOC);

    // If no client record linked by user_id, try to find by email (some records were created without user_id)
    if (!$user_client && !empty($user_email)) {
        $by_email_query = $pdo->prepare("
            SELECT id, user_id, name, email, phone, address, city, state, zip_code, age, date_of_birth, gender, weight, height, 
                   waist_circumference, hip_circumference, health_conditions, dietary_restrictions, goals, notes, status, created_at
            FROM clients
            WHERE email = ?
            LIMIT 1
        ");
        $by_email_query->execute([$user_email]);
        $user_client = $by_email_query->fetch(PDO::FETCH_ASSOC);
    }

    // If still no client record, use basic user info as a fallback
    if (!$user_client) {
        $user_client = [
            'id' => null,
            'user_id' => $user_id,
            'name' => $user_name,
            'email' => $user_email,
            'phone' => '',
            'address' => '',
            'city' => '',
            'state' => '',
            'zip_code' => '',
            'age' => '',
            'date_of_birth' => '',
            'gender' => '',
            'weight' => '',
            'height' => '',
            'waist_circumference' => '',
            'hip_circumference' => '',
            'health_conditions' => '',
            'dietary_restrictions' => '',
            'goals' => '',
            'notes' => '',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    // Fetch food items (safe mapping)
    $food_items = [];
    $food_query = $pdo->prepare("
        SELECT 
            id,
            food_name,
            food_group as group_type,
            serving_size,
            calories as energy,
            protein,
            fat,
            carbs,
            exchanges
        FROM food_items 
        ORDER BY food_group, food_name
    ");
    $food_query->execute();
    $food_items = $food_query->fetchAll(PDO::FETCH_ASSOC);

    // Organize food items by group
    $food_items_by_group = [];
    foreach ($food_items as $item) {
        $group_type = $item['group_type'] ?? 'other';
        if (!isset($food_items_by_group[$group_type])) {
            $food_items_by_group[$group_type] = [];
        }
        $food_items_by_group[$group_type][] = $item;
    }

    // Determine which user_id to use for tracking queries:
    // prefer clients.user_id if present, otherwise fallback to current logged-in user_id
    $tracking_user_id = $user_client['user_id'] ?? $user_id;
    if (empty($tracking_user_id)) {
        $tracking_user_id = $user_id;
    }

    // Get today's food tracking for this user (safe prepared statement)
    $food_today = $pdo->prepare("
        SELECT * FROM food_tracking 
        WHERE user_id = ? AND tracking_date = CURDATE()
        ORDER BY meal_type
    ");
    $food_today->execute([$tracking_user_id]);
    $food_entries = $food_today->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total calories (guard numeric)
    $calories_today = 0;
    foreach ($food_entries as $food) {
        $calories_today += isset($food['calories']) ? (int) $food['calories'] : 0;
    }

} catch (PDOException $e) {
    // Log and recover gracefully
    error_log("user-health-tracker error: " . $e->getMessage());
    $food_items_by_group = $food_items_by_group ?? [];
    $food_entries = $food_entries ?? [];
    $calories_today = $calories_today ?? 0;
}

// Generate initials for avatar
function getInitials($name)
{
    $names = explode(' ', trim($name));
    $initials = '';
    foreach ($names as $n) {
        if ($n !== '') {
            $initials .= strtoupper($n[0]);
        }
    }
    return substr($initials, 0, 2);
}

$user_initials = getInitials($user_name);

// Regular user navigation links
$nav_links = [
    ['href' => 'dashboard.php', 'icon' => 'fas fa-home', 'text' => 'Dashboard'],
    ['href' => 'anthropometric-information.php', 'icon' => 'fas fa-heartbeat', 'text' => 'Anthropometric Information', 'active' => true],
    ['href' => 'user-messages.php', 'icon' => 'fas fa-comments', 'text' => 'Messages'],
    ['href' => 'Nutrition-Calculator.php', 'icon' => 'fas fa-calculator', 'text' => 'Nutrition Calculator'],
    ['href' => 'food-exchange.php', 'icon' => 'fas fa-exchange-alt', 'text' => 'Food Exchange List']
];

// Check for active tab in session
// Tab handling
$active_tab = $_GET['tab'] ?? ($_SESSION['active_health_tracker_tab'] ?? 'personal-info');
$_SESSION['active_health_tracker_tab'] = $active_tab;

// Handle POST actions for updating body stats
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_body_stats') {
    $weight = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
    $height = !empty($_POST['height']) ? (float)$_POST['height'] : null;
    $waist = !empty($_POST['waist']) ? (float)$_POST['waist'] : null;
    $hip = !empty($_POST['hip']) ? (float)$_POST['hip'] : null;
    
    try {
        // Try updating by user_id first
        $update_stmt = $pdo->prepare("
            UPDATE clients 
            SET weight = ?, height = ?, waist_circumference = ?, hip_circumference = ?, updated_at = NOW() 
            WHERE user_id = ?
        ");
        $update_stmt->execute([$weight, $height, $waist, $hip, $user_id]);
        
        // If no rows were updated by user_id, try updating by email
        if ($update_stmt->rowCount() === 0 && !empty($user_email)) {
            $update_stmt = $pdo->prepare("
                UPDATE clients 
                SET weight = ?, height = ?, waist_circumference = ?, hip_circumference = ?, user_id = ?, updated_at = NOW() 
                WHERE email = ? AND (user_id IS NULL OR user_id = 0)
            ");
            $update_stmt->execute([$weight, $height, $waist, $hip, $user_id, $user_email]);
            
            // If STILL no rows, it means the user doesn't have a client profile at all. Let's create one.
            if ($update_stmt->rowCount() === 0) {
                $insert_stmt = $pdo->prepare("
                    INSERT INTO clients (user_id, name, email, weight, height, waist_circumference, hip_circumference, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
                ");
                $insert_stmt->execute([$user_id, $user_name, $user_email, $weight, $height, $waist, $hip]);
            }
        } elseif ($update_stmt->rowCount() === 0) {
            // If email is empty, just insert
            $insert_stmt = $pdo->prepare("
                INSERT INTO clients (user_id, name, email, weight, height, waist_circumference, hip_circumference, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
            ");
            $insert_stmt->execute([$user_id, $user_name, $user_email, $weight, $height, $waist, $hip]);
        }
        
        // Also add entry to client_bmi_history table if possible
        if ($weight > 0 && $height > 0) {
            try {
                // Ensure table exists
                $pdo->exec("CREATE TABLE IF NOT EXISTS client_bmi_history (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    weight DECIMAL(5,2),
                    height DECIMAL(5,2),
                    bmi DECIMAL(5,1),
                    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                
                $height_m = $height / 100;
                $bmi = round($weight / ($height_m * $height_m), 1);
                $hist_stmt = $pdo->prepare("INSERT INTO client_bmi_history (user_id, weight, height, bmi) VALUES (?, ?, ?, ?)");
                $hist_stmt->execute([$user_id, $weight, $height, $bmi]);
            } catch (Exception $e) {
                // Ignore if permission issue
            }
        }
        
        header("Location: user-health-tracker.php?tab=body-stats&success=1");
        exit();
    } catch (PDOException $e) {
        $error_msg = "Error updating statistics: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="scripts/theme-toggle.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>NutriDeq - My Health Tracker</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/help-tracker.css">
    <link rel="stylesheet" href="css/messages.css">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/nutrifacts.css">
    <link rel="stylesheet" href="css/logout-modal.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/layout.css">
    <!-- Platform Specific Styles -->
    <link rel="stylesheet" href="css/desktop-style.css" media="all and (min-width: 1025px)">
    <link rel="stylesheet" href="css/mobile-style.css" media="all and (max-width: 1024px)">
    <style>
        @media screen and (max-width: 768px) {
            .tabs-header {
                overflow-x: auto;
                white-space: nowrap;
                justify-content: flex-start !important;
                padding-bottom: 10px;
                -webkit-overflow-scrolling: touch;
            }
            .tab {
                flex: 0 0 auto;
            }
            .stats-grid {
                grid-template-columns: 1fr 1fr !important;
                gap: 10px !important;
            }
            .stat-card {
                padding: 15px !important;
            }
        }
    </style>
    <script>const BASE_URL = '';</script>
    <link rel="stylesheet" href="css/premium-revamp.css">
</head>

<body>
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content" style="padding: 20px 32px;">
            <div class="page-container">
                <div class="header" style="background: transparent; backdrop-filter: none; box-shadow: none; border: none; padding: 0; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start;">
                    <div class="page-title">
                        <h1 style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 2.1rem; letter-spacing: -0.02em; color: #1e293b; margin: 0;">My Health Tracker</h1>
                        <p style="font-weight: 600; color: #64748b; font-size: 0.95rem; margin-top: 4px;">Track your health metrics and progress</p>
                    </div>

                    <div class="header-actions" style="margin-top: 8px;">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Search...">
                        </div>
                    </div>
                </div>

                <!-- Display success messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="success-message"
                        style="background: #d1fae5; color: #065f46; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #a7f3d0;">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($_SESSION['success']);
                        unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <!-- Premium Tab Container -->
                <div class="tabs-container">
                    <div class="tabs-header">
                        <button class="tab <?php echo $active_tab === 'personal-info' ? 'active' : ''; ?>"
                            data-tab="personal-info">
                            <i class="fas fa-user-circle tab-icon"></i>
                            <span>My Profile</span>
                        </button>
                        <button class="tab <?php echo $active_tab === 'food-tracker' ? 'active' : ''; ?>"
                            data-tab="food-tracker">
                            <i class="fas fa-utensils tab-icon"></i>
                            <span>Food Tracker</span>
                        </button>
                        <button class="tab <?php echo $active_tab === 'body-stats' ? 'active' : ''; ?>"
                            data-tab="body-stats">
                            <i class="fas fa-chart-line tab-icon"></i>
                            <span>Body Statistics</span>
                        </button>
                    </div>

                    <!-- Personal Info Tab -->
                    <div class="tab-content <?php echo $active_tab === 'personal-info' ? 'active' : ''; ?>"
                        id="personal-info">
                        <div class="section-header">
                            <h2>My Information</h2>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="fullName"><i class="fas fa-user-circle"></i> Full Name</label>
                                <input type="text" class="form-control" id="fullName"
                                    value="<?php echo htmlspecialchars($user_client['name'] ?? ''); ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                                <input type="email" class="form-control" id="email"
                                    value="<?php echo htmlspecialchars($user_client['email'] ?? ''); ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label for="phone"><i class="fas fa-phone-alt"></i> Phone Number</label>
                                <input type="tel" class="form-control" id="phone"
                                    value="<?php echo htmlspecialchars($user_client['phone'] ?? 'Not provided'); ?>"
                                    readonly>
                            </div>

                            <div class="form-group">
                                <label for="age"><i class="fas fa-hourglass-half"></i> Age</label>
                                <input type="text" class="form-control" id="age"
                                    value="<?php echo htmlspecialchars($user_client['age'] ?? 'Not provided'); ?>"
                                    readonly>
                            </div>

                            <div class="form-group">
                                <label for="gender"><i class="fas fa-venus-mars"></i> Gender</label>
                                <input type="text" class="form-control" id="gender"
                                    value="<?php echo htmlspecialchars(ucfirst($user_client['gender'] ?? 'Not provided')); ?>"
                                    readonly>
                            </div>

                            <!-- Address Fields -->
                            <div class="form-group">
                                <label for="address"><i class="fas fa-map-marked-alt"></i> Street Address</label>
                                <input type="text" class="form-control" id="address"
                                    value="<?php echo htmlspecialchars($user_client['address'] ?? 'Not provided'); ?>"
                                    readonly>
                            </div>

                            <div class="form-group">
                                <label for="city"><i class="fas fa-city"></i> City</label>
                                <input type="text" class="form-control" id="city"
                                    value="<?php echo htmlspecialchars($user_client['city'] ?? 'Not provided'); ?>"
                                    readonly>
                            </div>

                            <div class="form-group">
                                <label for="state"><i class="fas fa-map"></i> State/Province</label>
                                <input type="text" class="form-control" id="state"
                                    value="<?php echo htmlspecialchars($user_client['state'] ?? 'Not provided'); ?>"
                                    readonly>
                            </div>

                            <div class="form-group">
                                <label for="zip_code"><i class="fas fa-mail-bulk"></i> ZIP/Postal Code</label>
                                <input type="text" class="form-control" id="zip_code"
                                    value="<?php echo htmlspecialchars($user_client['zip_code'] ?? 'Not provided'); ?>"
                                    readonly>
                            </div>

                            <div class="form-group full-width">
                                <label for="health_conditions"><i class="fas fa-heartbeat"></i> Health Conditions</label>
                                <textarea class="form-control" id="health_conditions" rows="3"
                                    readonly><?php echo htmlspecialchars($user_client['health_conditions'] ?? 'No health conditions reported'); ?></textarea>
                            </div>

                            <div class="form-group full-width">
                                <label for="dietary_restrictions"><i class="fas fa-ban"></i> Dietary Restrictions</label>
                                <textarea class="form-control" id="dietary_restrictions" rows="3"
                                    readonly><?php echo htmlspecialchars($user_client['dietary_restrictions'] ?? 'No dietary restrictions'); ?></textarea>
                            </div>

                            <div class="form-group full-width">
                                <label for="goals"><i class="fas fa-bullseye"></i> My Health/Nutrition Goals</label>
                                <textarea class="form-control" id="goals" rows="3"
                                    readonly><?php echo htmlspecialchars($user_client['goals'] ?? 'No goals set'); ?></textarea>
                            </div>

                            <div class="form-group full-width">
                                <label for="notes"><i class="fas fa-sticky-note"></i> Personal Notes</label>
                                <textarea class="form-control" id="notes" rows="4"
                                    readonly><?php echo htmlspecialchars($user_client['notes'] ?? 'No notes yet'); ?></textarea>
                            </div>
                        </div>

                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon-wrapper age"><i class="fas fa-calendar-alt"></i></div>
                                <div class="stat-value"><?php echo $user_client['age'] ?? 'N/A'; ?></div>
                                <div class="stat-label">Age</div>
                                <div class="stat-trend">Active Member</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon-wrapper weight"><i class="fas fa-weight"></i></div>
                                <div class="stat-value"><?php echo $user_client['weight'] ?? 'N/A'; ?>kg</div>
                                <div class="stat-label">Current Weight</div>
                                <div class="stat-trend">Latest</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon-wrapper height"><i class="fas fa-ruler-vertical"></i></div>
                                <div class="stat-value"><?php echo $user_client['height'] ?? 'N/A'; ?>cm</div>
                                <div class="stat-label">Height</div>
                                <div class="stat-trend">Recorded</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon-wrapper location"><i class="fas fa-map-marker-alt"></i></div>
                                <div class="stat-value">
                                    <?php if ($user_client['city']): ?>
                                        <?php echo htmlspecialchars($user_client['city']); ?>
                                    <?php else: ?>
                                        Location N/A
                                    <?php endif; ?>
                                </div>
                                <div class="stat-label">Location</div>
                                <div class="stat-trend">My Area</div>
                            </div>
                        </div>
                    </div>

                    <!-- Food Tracker Tab -->
                    <div class="tab-content <?php echo $active_tab === 'food-tracker' ? 'active' : ''; ?>"
                        id="food-tracker">
                        <div class="section-header">
                            <h2>My Daily Food Intake</h2>
                            <div class="section-actions"></div>
                        </div>

                        <div class="form-group">
                            <label>Today's Food Intake - <?php echo date('F j, Y'); ?></label>
                            <div class="food-entries">
                                <?php if (empty($food_entries)): ?>
                                    <div class="no-food-message" style="padding: 30px; text-align: center;">
                                        <i class="fas fa-utensils"
                                            style="font-size: 3rem; color: #ccc; margin-bottom: 15px;"></i>
                                        <p>No food entries recorded for today.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($food_entries as $food): ?>
                                        <div class="food-entry" style="background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(0,0,0,0.05); border-radius: 20px; padding: 20px; margin-bottom: 16px; display: flex; align-items: center; gap: 20px; transition: all 0.3s ease;">
                                            <div class="bento-stat-icon" style="flex-shrink: 0; background: rgba(16, 185, 129, 0.1); color: #10b981; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 12px; font-size: 1.25rem;">
                                                <i class="fas fa-<?php
                                                switch ($food['meal_type']) {
                                                    case 'breakfast': echo 'coffee'; break;
                                                    case 'AM Snack': echo 'apple-alt'; break;
                                                    case 'lunch': echo 'utensils'; break;
                                                    case 'PM Snack': echo 'cookie'; break;
                                                    case 'dinner': echo 'moon'; break;
                                                    default: echo 'apple-alt';
                                                }
                                                ?>"></i>
                                            </div>
                                            <div style="flex: 1;">
                                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px;">
                                                    <h4 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 1.1rem; color: #1e293b;">
                                                        <?php echo htmlspecialchars($food['food_name']); ?>
                                                    </h4>
                                                    <?php if ($food['meal_type'] === 'custom'): ?>
                                                        <span style="font-size: 0.8rem; font-weight: 700; color: #3b82f6; text-transform: uppercase; letter-spacing: 0.05em; background: #eff6ff; padding: 2px 8px; border-radius: 6px; border: 1px solid #bfdbfe;">
                                                            <i class="fas fa-clipboard-list"></i> Planned
                                                        </span>
                                                    <?php else: ?>
                                                        <span style="font-size: 0.8rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; background: #f1f5f9; padding: 2px 8px; border-radius: 6px;">
                                                            <?php echo ucfirst($food['meal_type']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="display: flex; gap: 12px; font-size: 0.85rem; color: #64748b; font-weight: 500;">
                                                    <span><b style="color: #10b981;">P:</b> <?php echo htmlspecialchars($food['protein'] ?? '0'); ?>g</span>
                                                    <span><b style="color: #6366f1;">C:</b> <?php echo htmlspecialchars($food['carbs'] ?? '0'); ?>g</span>
                                                    <span><b style="color: #f59e0b;">F:</b> <?php echo htmlspecialchars($food['fat'] ?? '0'); ?>g</span>
                                                </div>
                                            </div>
                                            <div style="text-align: right;">
                                                <div style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: #111827;">
                                                    <?php echo htmlspecialchars($food['calories'] ?? '0'); ?><small style="font-size: 0.6em; opacity: 0.6; margin-left: 2px;">kcal</small>
                                                </div>
                                                <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 8px;">
                                                    <?php if ($food['meal_type'] === 'custom'): ?>
                                                        <button class="btn-dash-action" style="padding: 8px 16px; border-radius: 8px; background: #10b981; color: white; border: none; font-weight: 600; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2); cursor: pointer;" onclick="showConsumeModal(<?php echo (int) $food['id']; ?>, '<?php echo htmlspecialchars(addslashes($food['food_name'])); ?>')">
                                                            <i class="fas fa-check"></i> Consume
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn-dash-action" style="padding: 6px; border-radius: 8px; background: #f1f5f9; border: 1px solid #e2e8f0; cursor: pointer;" onclick="editFoodEntry(<?php echo (int) $food['id']; ?>)">
                                                            <i class="fas fa-edit" style="font-size: 0.8rem; color: #64748b;"></i>
                                                        </button>
                                                        <button class="btn-dash-action" style="padding: 6px; border-radius: 8px; background: #fff1f2; border: 1px solid #ffe4e6; color: #f43f5e; cursor: pointer;" onclick="deleteFoodEntry(<?php echo (int) $food['id']; ?>)">
                                                            <i class="fas fa-trash" style="font-size: 0.8rem;"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $calories_today; ?></div>
                                <div class="stat-label">Calories Today</div>
                                <div class="stat-trend">Total intake</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo count($food_entries); ?></div>
                                <div class="stat-label">Meals Logged</div>
                                <div class="stat-trend">Today</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo date('H:i'); ?></div>
                                <div class="stat-label">Last Update</div>
                                <div class="stat-trend">Current time</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value">Active</div>
                                <div class="stat-label">Tracking</div>
                                <div class="stat-trend trend-up">Ongoing</div>
                            </div>
                        </div>
                    </div>

                    <!-- Body Statistics Tab -->
                    <div class="tab-content <?php echo $active_tab === 'body-stats' ? 'active' : ''; ?>"
                        id="body-stats">
                        <div class="section-header">
                            <h2>My Body Measurements</h2>
                        </div>

                        <?php if (isset($_GET['success'])): ?>
                        <div style="background: #ecfdf5; color: #065f46; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 0.9rem; border: 1px solid #10b98133;">
                            <i class="fas fa-check-circle"></i> Measurements updated successfully!
                        </div>
                        <?php endif; ?>

                        <form id="statsForm" method="POST">
                            <input type="hidden" name="action" value="update_body_stats">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="weight_input"><i class="fas fa-weight" style="color:var(--primary);margin-right:8px;"></i>Current Weight (kg)</label>
                                    <input type="number" step="any" class="form-control stats-input" name="weight" id="weight_input"
                                        value="<?php echo htmlspecialchars($user_client['weight'] ?? ''); ?>" readonly>
                                </div>

                                <div class="form-group">
                                    <label for="targetWeight"><i class="fas fa-bullseye" style="color:#f5a623;margin-right:8px;"></i>Optimal Weight Range (kg)</label>
                                    <?php
                                    $optimal_weight = 'Need height data';
                                    if (isset($user_client['height']) && $user_client['height'] > 0) {
                                        $height = (float) $user_client['height'];
                                        $height_in_meters = $height / 100;

                                        // Calculate healthy BMI range (18.5 - 24.9)
                                        $bmi_low = 18.5 * ($height_in_meters * $height_in_meters);
                                        $bmi_high = 24.9 * ($height_in_meters * $height_in_meters);

                                        $optimal_weight = number_format($bmi_low, 1) . " - " . number_format($bmi_high, 1) . " kg";
                                    }
                                    ?>
                                    <input type="text" class="form-control" id="targetWeight"
                                        value="<?php echo $optimal_weight; ?>" readonly>
                                    <small class="form-text text-muted">Healthy BMI Range (18.5 - 24.9)</small>
                                </div>

                                <div class="form-group">
                                    <label for="height_input"><i class="fas fa-ruler-vertical" style="color:var(--secondary);margin-right:8px;"></i>Height (cm)</label>
                                    <input type="number" step="any" class="form-control stats-input" name="height" id="height_input"
                                        value="<?php echo htmlspecialchars($user_client['height'] ?? ''); ?>" readonly>
                                </div>

                                <div class="form-group">
                                    <label for="bmi"><i class="fas fa-percentage" style="color:#d0021b;margin-right:8px;"></i>My BMI</label>
                                    <input type="text" class="form-control" id="bmi" value="<?php
                                    if (!empty($user_client['weight']) && !empty($user_client['height'])) {
                                        $height_in_meters = (float) $user_client['height'] / 100;
                                        $weight = (float) $user_client['weight'];
                                        $bmi = $weight / ($height_in_meters * $height_in_meters);
                                        echo number_format($bmi, 1);
                                    } else {
                                        echo 'Not calculated';
                                    }
                                    ?>" readonly>
                                </div>

                                <div class="form-group">
                                    <label for="waist_input">Waist Circumference (cm)</label>
                                    <input type="number" step="any" class="form-control stats-input" name="waist" id="waist_input"
                                        value="<?php echo htmlspecialchars($user_client['waist_circumference'] ?? ''); ?>" readonly>
                                </div>

                                <div class="form-group">
                                    <label for="hip_input">Hip Circumference (cm)</label>
                                    <input type="number" step="any" class="form-control stats-input" name="hip" id="hip_input"
                                        value="<?php echo htmlspecialchars($user_client['hip_circumference'] ?? ''); ?>" readonly>
                                </div>
                            </div>

                        </form>

                        <div class="stats-grid">
                            <?php
                            $bmi_value = null;
                            $weight_diff = null;
                            if (isset($user_client['weight']) && isset($user_client['height'])) {
                                $height_in_meters = (float) $user_client['height'] / 100;
                                $weight = (float) $user_client['weight'];
                                $bmi_value = $weight / ($height_in_meters * $height_in_meters);
                                $optimal_weight_mid = 21.7 * ($height_in_meters * $height_in_meters);
                                $weight_diff = $weight - $optimal_weight_mid;
                            }
                            ?>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $bmi_value ? number_format($bmi_value, 1) : 'N/A'; ?>
                                </div>
                                <div class="stat-label">Current BMI</div>
                                <div class="stat-trend">
                                    <?php
                                    if ($bmi_value !== null) {
                                        if ($bmi_value < 18.5)
                                            echo 'Underweight';
                                        elseif ($bmi_value < 25)
                                            echo 'Normal';
                                        elseif ($bmi_value < 30)
                                            echo 'Overweight';
                                        else
                                            echo 'Obese';
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-value">
                                    <?php
                                    if ($weight_diff !== null) {
                                        echo number_format(abs($weight_diff), 1) . 'kg';
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </div>
                                <div class="stat-label">To Optimal Weight</div>
                                <div class="stat-trend <?php
                                if ($weight_diff !== null) {
                                    echo $weight_diff > 0 ? 'trend-down' : ($weight_diff < 0 ? 'trend-up' : 'trend-neutral');
                                } else {
                                    echo 'trend-neutral';
                                }
                                ?>">
                                    <?php
                                    if ($weight_diff !== null) {
                                        echo $weight_diff > 0 ? 'To lose' : ($weight_diff < 0 ? 'To gain' : 'Ideal weight');
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-value">
                                    <?php
                                    if (isset($user_client['waist_circumference']) && isset($user_client['hip_circumference'])) {
                                        $waist = (float) $user_client['waist_circumference'];
                                        $hip = (float) $user_client['hip_circumference'];
                                        if ($hip > 0) {
                                            echo number_format($waist / $hip, 2);
                                        } else {
                                            echo 'N/A';
                                        }
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </div>
                                <div class="stat-label">Waist-Hip Ratio</div>
                                <div class="stat-trend">
                                    <?php
                                    if (isset($user_client['waist_circumference']) && isset($user_client['hip_circumference'])) {
                                        $waist = (float) $user_client['waist_circumference'];
                                        $hip = (float) $user_client['hip_circumference'];
                                        if ($hip > 0) {
                                            $wh_ratio = $waist / $hip;
                                            $gender = $user_client['gender'] ?? '';
                                            if ($gender === 'male') {
                                                echo ($wh_ratio <= 0.9) ? 'Low risk' : 'High risk';
                                            } else {
                                                echo ($wh_ratio <= 0.85) ? 'Low risk' : 'High risk';
                                            }
                                        } else {
                                            echo 'N/A';
                                        }
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-value">
                                    <?php
                                    if (isset($user_client['created_at'])) {
                                        echo date('M j', strtotime($user_client['created_at']));
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </div>
                                <div class="stat-label">Profile Created</div>
                                <div class="stat-trend">
                                    <?php
                                    if (isset($user_client['created_at'])) {
                                        $days_ago = floor((time() - strtotime($user_client['created_at'])) / (60 * 60 * 24));
                                        if ($days_ago == 0)
                                            echo 'Today';
                                        elseif ($days_ago == 1)
                                            echo 'Yesterday';
                                        elseif ($days_ago < 7)
                                            echo $days_ago . ' days ago';
                                        else
                                            echo 'Updated';
                                    } else {
                                        echo 'Initial';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Meal Planner Tab (progress) -->
                    <div class="tab-content <?php echo $active_tab === 'progress' ? 'active' : ''; ?>" id="progress">
                        <div class="section-header">
                            <h2>Food Exchange List</h2>
                            <div class="section-actions">
                                <button class="btn btn-primary" id="saveMealPlan">
                                    <i class="fas fa-save"></i> Save Meal Plan
                                </button>
                                <span class="info-btn" title="FNRI Food Exchange List System">
                                    <i class="fas fa-info"></i>
                                </span>
                            </div>
                        </div>

                        <!-- FNRI Food Exchange List markup -->
                        <div class="fel-container">
                            <!-- Vegetable selections -->
                            <div class="fel-group" id="vegetable-group">
                                <label class="fel-label">Vegetables</label>
                                <div class="fel-items" id="vegetable-items">
                                    <!-- Dynamic content for vegetable items -->
                                </div>
                            </div>

                            <!-- Fruit selections -->
                            <div class="fel-group" id="fruit-group">
                                <label class="fel-label">Fruits</label>
                                <div class="fel-items" id="fruit-items">
                                    <!-- Dynamic content for fruit items -->
                                </div>
                            </div>

                            <!-- Milk selections -->
                            <div class="fel-group" id="milk-group">
                                <label class="fel-label">Milk and Dairy</label>
                                <div class="fel-items" id="milk-items">
                                    <!-- Dynamic content for milk items -->
                                </div>
                            </div>

                            <!-- Rice selections -->
                            <div class="fel-group" id="rice-group">
                                <label class="fel-label">Rice and Grains</label>
                                <div class="fel-items" id="rice-items">
                                    <!-- Dynamic content for rice items -->
                                </div>
                            </div>

                            <!-- Meat selections -->
                            <div class="fel-group" id="meat-group">
                                <label class="fel-label">Meat and Protein</label>
                                <div class="fel-items" id="meat-items">
                                    <!-- Dynamic content for meat items -->
                                </div>
                            </div>

                            <!-- Fats and Oils selections -->
                            <div class="fel-group" id="fat-group">
                                <label class="fel-label">Fats and Oils</label>
                                <div class="fel-items" id="fat-items">
                                    <!-- Dynamic content for fat items -->
                                </div>
                            </div>
                        </div>

                        <!-- Selected items summary -->
                        <div class="selected-items-container">
                            <h3>Selected Food Items</h3>
                            <div class="selected-items" id="selectedItemsList">
                                <!-- Dynamic content for selected items -->
                            </div>

                            <div class="nutrition-summary">
                                <div class="summary-item">
                                    <span>Total Energy:</span>
                                    <span id="total-energy">0 kcal</span>
                                </div>
                                <div class="summary-item">
                                    <span>Total Protein:</span>
                                    <span id="total-protein">0 g</span>
                                </div>
                                <div class="summary-item">
                                    <span>Total Fat:</span>
                                    <span id="total-fat">0 g</span>
                                </div>
                                <div class="summary-item">
                                    <span>Total Carbs:</span>
                                    <span id="total-cho">0 g</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Consume Meal Modal -->
            <div id="consumeModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000; align-items:center; justify-content:center;">
                <div style="background:white; padding:30px; border-radius:24px; width:400px; max-width:90%; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
                    <h3 style="margin-bottom:5px; font-family:'Outfit', sans-serif;">Confirm Consumption</h3>
                    <p style="color:#64748b; font-size:0.9rem; margin-bottom:20px;">When did you consume <strong id="consumeFoodName" style="color:#1e293b;"></strong>?</p>
                    
                    <form id="consumeForm">
                        <input type="hidden" id="consumeTrackingId" name="tracking_id">
                        
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:24px;">
                            <label style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; text-align: center; cursor: pointer; transition: all 0.2s; background: #f8fafc;">
                                <input type="radio" name="meal_type" value="Breakfast" style="display:none;" required onchange="updateRadioStyling(this)">
                                <i class="fas fa-coffee" style="display:block; font-size: 1.5rem; color: #6366f1; margin-bottom: 8px;"></i>
                                <span style="font-size:0.85rem; font-weight:600; color:#475569;">Breakfast</span>
                            </label>
                            <label style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; text-align: center; cursor: pointer; transition: all 0.2s; background: #f8fafc;">
                                <input type="radio" name="meal_type" value="AM Snack" style="display:none;" required onchange="updateRadioStyling(this)">
                                <i class="fas fa-apple-alt" style="display:block; font-size: 1.5rem; color: #10b981; margin-bottom: 8px;"></i>
                                <span style="font-size:0.85rem; font-weight:600; color:#475569;">AM Snack</span>
                            </label>
                            <label style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; text-align: center; cursor: pointer; transition: all 0.2s; background: #f8fafc;">
                                <input type="radio" name="meal_type" value="Lunch" style="display:none;" required onchange="updateRadioStyling(this)">
                                <i class="fas fa-utensils" style="display:block; font-size: 1.5rem; color: #f59e0b; margin-bottom: 8px;"></i>
                                <span style="font-size:0.85rem; font-weight:600; color:#475569;">Lunch</span>
                            </label>
                            <label style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; text-align: center; cursor: pointer; transition: all 0.2s; background: #f8fafc;">
                                <input type="radio" name="meal_type" value="PM Snack" style="display:none;" required onchange="updateRadioStyling(this)">
                                <i class="fas fa-cookie" style="display:block; font-size: 1.5rem; color: #ec4899; margin-bottom: 8px;"></i>
                                <span style="font-size:0.85rem; font-weight:600; color:#475569;">PM Snack</span>
                            </label>
                            <label style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; text-align: center; cursor: pointer; transition: all 0.2s; background: #f8fafc;">
                                <input type="radio" name="meal_type" value="Dinner" style="display:none;" required onchange="updateRadioStyling(this)">
                                <i class="fas fa-moon" style="display:block; font-size: 1.5rem; color: #8b5cf6; margin-bottom: 8px;"></i>
                                <span style="font-size:0.85rem; font-weight:600; color:#475569;">Dinner</span>
                            </label>
                            <label style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; text-align: center; cursor: pointer; transition: all 0.2s; background: #f8fafc;">
                                <input type="radio" name="meal_type" value="Snack" style="display:none;" required onchange="updateRadioStyling(this)">
                                <i class="fas fa-utensins" style="display:block; font-size: 1.5rem; color: #94a3b8; margin-bottom: 8px;"></i>
                                <span style="font-size:0.85rem; font-weight:600; color:#475569;">Other</span>
                            </label>
                        </div>
                        
                        <div style="display:flex; justify-content:flex-end; gap:12px;">
                            <button type="button" onclick="closeConsumeModal()" style="padding:10px 20px; border-radius:12px; background:white; color:#64748b; border: 1.5px solid #e2e8f0; font-weight:600; cursor:pointer;">Cancel</button>
                            <button type="submit" style="padding:10px 20px; border-radius:12px; background:#10b981; color:white; border:none; font-weight:700; display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <i class="fas fa-share-square"></i> Confirm to Diary
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                function showConsumeModal(id, name) {
                    document.getElementById('consumeTrackingId').value = id;
                    document.getElementById('consumeFoodName').textContent = name;
                    document.getElementById('consumeModal').style.display = 'flex';
                    // Reset radios
                    const form = document.getElementById('consumeForm');
                    form.reset();
                    updateRadioStyling(null);
                }

                function closeConsumeModal() {
                    document.getElementById('consumeModal').style.display = 'none';
                }

                function updateRadioStyling(selectedInput) {
                    const labels = document.querySelectorAll('#consumeForm label');
                    labels.forEach(l => {
                        l.style.borderColor = '#e2e8f0';
                        l.style.background = '#f8fafc';
                        l.style.boxShadow = 'none';
                    });
                    if (selectedInput) {
                        const lbl = selectedInput.closest('label');
                        lbl.style.borderColor = '#10b981';
                        lbl.style.background = '#ecfdf5';
                        lbl.style.boxShadow = '0 4px 10px rgba(16, 185, 129, 0.1)';
                    }
                }

                document.getElementById('consumeForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    
                    const btn = this.querySelector('button[type="submit"]');
                    const originalHTML = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Transferring...';
                    btn.disabled = true;

                    fetch(BASE_URL + 'api/consume_meal.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if(data.success) {
                            closeConsumeModal();
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                            btn.innerHTML = originalHTML;
                            btn.disabled = false;
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Network Error');
                        btn.innerHTML = originalHTML;
                        btn.disabled = false;
                    });
                });

                // FNRI FEL Data and client-side functions (Restored Logic)
                let selectedItems = [];

                function addSelectedItem(group, name, data) {
                    const item = {
                        group,
                        name,
                        data,
                        id: Date.now() + Math.random()
                    };
                    selectedItems.push(item);
                    updateSelectedItemsList();
                    updateNutritionSummary();
                }

                function removeSelectedItem(id) {
                    selectedItems = selectedItems.filter(item => item.id !== id);
                    updateSelectedItemsList();
                    updateNutritionSummary();
                }

                function updateSelectedItemsList() {
                    const container = document.getElementById('selectedItemsList');
                    if (!container) return;
                    if (selectedItems.length === 0) {
                        container.innerHTML = '<div class="no-selection" style="padding:20px; text-align:center; color:#64748b;">No food items selected yet</div>';
                        return;
                    }
                    container.innerHTML = selectedItems.map(item => `
                    <div class="selected-item" style="display:flex; justify-content:space-between; padding:12px; border-bottom:1px solid #e2e8f0;">
                        <div>
                            <div style="font-weight:600; color:#1e293b;">${item.name} <span style="font-size:0.75rem; color:#64748b; font-weight:normal;">(${item.group})</span></div>
                            <div style="font-size:0.8rem; color:#64748b;">
                                ${item.data.exchange} Ex. | ${item.data.energy} kcal | P:${item.data.protein}g F:${item.data.fat}g C:${item.data.cho}g
                            </div>
                        </div>
                        <button onclick="removeSelectedItem(${item.id})" style="background:none; border:none; color:#f43f5e; cursor:pointer;"><i class="fas fa-times"></i></button>
                    </div>
                `).join('');
                }

                function updateNutritionSummary() {
                    const totalEnergy = selectedItems.reduce((sum, item) => sum + parseInt(item.data.energy || 0), 0);
                    const totalProtein = selectedItems.reduce((sum, item) => sum + parseInt(item.data.protein || 0), 0);
                    const totalFat = selectedItems.reduce((sum, item) => sum + parseInt(item.data.fat || 0), 0);
                    const totalCHO = selectedItems.reduce((sum, item) => sum + parseInt(item.data.cho || 0), 0);

                    if (document.getElementById('total-energy')) document.getElementById('total-energy').innerHTML = totalEnergy + '<small style="font-size: 0.6em; margin-left: 2px;">kcal</small>';
                    if (document.getElementById('total-protein')) document.getElementById('total-protein').innerHTML = totalProtein + '<small style="font-size: 0.6em; margin-left: 2px;">g</small>';
                    if (document.getElementById('total-fat')) document.getElementById('total-fat').innerHTML = totalFat + '<small style="font-size: 0.6em; margin-left: 2px;">g</small>';
                    if (document.getElementById('total-cho')) document.getElementById('total-cho').innerHTML = totalCHO + '<small style="font-size: 0.6em; margin-left: 2px;">g</small>';
                }

                function editProfile() { alert('Profile editing feature would open a form here.'); }
                function addFoodEntry() { alert('Food entry feature would open a form here.'); }
                function editFoodEntry(id) { alert('Editing food entry ID: ' + id); }
                function deleteFoodEntry(id) {
                    if (!confirm('Are you sure you want to delete this food entry?')) return;
                    fetch(BASE_URL + 'handlers/delete_food_entry.php?id=' + encodeURIComponent(id))
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) location.reload();
                            else alert('Error deleting entry: ' + (data.message || 'Unknown'));
                        })
                        .catch(() => alert('Error deleting entry.'));
                }
                function updateBodyStats() { alert('Body stats update feature would open a form here.'); }

                // Save active tab to session via AJAX
                function saveActiveTab(tabId) {
                    fetch(BASE_URL + 'handlers/save_active_tab.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ tab: tabId })
                    }).catch(err => console.error('Error saving active tab:', err));
                }

                // DOMContentLoaded - attach event handlers safely
                document.addEventListener('DOMContentLoaded', function () {
                    const tabs = document.querySelectorAll('.tab');
                    const tabContents = document.querySelectorAll('.tab-content');

                    // Initially hide all panels and show only the active one
                    tabContents.forEach(c => { c.style.display = 'none'; c.classList.remove('active'); });
                    const initActive = document.querySelector('.tab.active');
                    if (initActive) {
                        const initId = initActive.getAttribute('data-tab');
                        const initPanel = document.getElementById(initId);
                        if (initPanel) { initPanel.style.display = 'block'; initPanel.classList.add('active'); }
                    } else if (tabContents.length > 0) {
                        // fallback: show first tab
                        tabContents[0].style.display = 'block';
                        tabContents[0].classList.add('active');
                        if (tabs[0]) tabs[0].classList.add('active');
                    }

                    tabs.forEach(tab => {
                        tab.addEventListener('click', () => {
                            const tabId = tab.getAttribute('data-tab');
                            // Hide all
                            tabs.forEach(t => t.classList.remove('active'));
                            tabContents.forEach(c => { c.classList.remove('active'); c.style.display = 'none'; });
                            // Show selected
                            tab.classList.add('active');
                            const content = document.getElementById(tabId);
                            if (content) { content.classList.add('active'); content.style.display = 'block'; }
                            saveActiveTab(tabId);
                        });
                    });

                    // Attach saveMealPlan handler if element exists
                    const saveMealPlanBtn = document.getElementById('saveMealPlan');
                    if (saveMealPlanBtn) {
                        saveMealPlanBtn.addEventListener('click', function () {
                            if (selectedItems.length === 0) {
                                alert('Please select some food items before saving.');
                                return;
                            }

                            const mealPlanData = {
                                user_id: <?php echo (int) $user_id; ?>,
                                items: selectedItems,
                                total_energy: document.getElementById('total-energy')?.textContent || '0',
                                total_protein: document.getElementById('total-protein')?.textContent || '0',
                                total_fat: document.getElementById('total-fat')?.textContent || '0',
                                total_cho: document.getElementById('total-cho')?.textContent || '0'
                            };

                            fetch(BASE_URL + 'handlers/save_meal_plan.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(mealPlanData)
                            })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        alert(data.message || 'Saved');
                                        setTimeout(() => location.reload(), 800);
                                    } else {
                                        alert('Error saving meal plan: ' + (data.message || 'Unknown'));
                                    }
                                })
                                .catch(() => alert('Error saving meal plan.'));
                        });
                    }

                });
            </script>
        </div>
    </main>
</div>
</body>

</html>
