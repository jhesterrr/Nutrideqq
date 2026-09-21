<?php
require_once __DIR__ . '/session_bootstrap.php';

require_once 'database.php';
require_once 'navigation.php';
require_once 'api/fct_helper.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: login-logout/NutriDeqN-Login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'user';
$db = new Database();
$conn = $db->getConnection();
$fct = new FCTHelper();

// Fetch the logged-in user's real name for the report
$user_name_stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
$user_name_stmt->execute([$user_id]);
$user_name_row = $user_name_stmt->fetch(PDO::FETCH_ASSOC);
$current_user_name  = $user_name_row['name']  ?? $_SESSION['name'] ?? 'NutriDeq User';
$current_user_email = $user_name_row['email'] ?? '';

// Date selection
$selected_date = $_GET['date'] ?? date('Y-m-d');
$today = date('Y-m-d');

// Fetch logs for the selected date
$sql = "SELECT * FROM food_logs WHERE user_id = :user_id AND log_date = :date ORDER BY created_at ASC";
$stmt = $conn->prepare($sql);
$stmt->execute([':user_id' => $user_id, ':date' => $selected_date]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by meal type
$grouped_logs = [
    'Breakfast' => [],
    'AM Snack' => [],
    'Lunch' => [],
    'PM Snack' => [],
    'Dinner' => [],
    'Snack' => [] // Keep for legacy
];

$totals = [
    'calories' => 0,
    'protein' => 0,
    'carbs' => 0,
    'fat' => 0
];

$TRACKED_MICRO = ['Iron','Potassium, K','Calcium','Sodium','Fiber, total dietary','Vitamin C'];
$MICRO_UNITS = [
    'Iron' => 'mg', 'Potassium, K' => 'mg', 'Calcium' => 'mg',
    'Sodium' => 'mg', 'Fiber, total dietary' => 'g', 'Vitamin C' => 'mg'
];
$MICRO_THRESHOLDS = [
    'Iron' => 7.0, 'Potassium, K' => 1500.0, 'Calcium' => 500.0,
    'Sodium' => 500.0, 'Fiber, total dietary' => 10.0, 'Vitamin C' => 30.0
];
$meal_micros = [];
foreach (array_keys($grouped_logs) as $mt) {
    $meal_micros[$mt] = [];
    foreach ($TRACKED_MICRO as $n) $meal_micros[$mt][$n] = 0.0;
}
$day_micros = [];
foreach ($TRACKED_MICRO as $n) $day_micros[$n] = 0.0;

if (!empty($logs)) {
    $log_ids = array_column($logs, 'id');
    if (!empty($log_ids)) {
        $ph = implode(',', array_fill(0, count($log_ids), '?'));
        $nph = implode(',', array_fill(0, count($TRACKED_MICRO), '?'));
        $params = array_merge($TRACKED_MICRO, $log_ids);
        $sql = "SELECT fl.id as log_id, fl.meal_type, fn.nutrient_name,
                       SUM(fn.value * (fl.serving_size / 100)) as val
                FROM food_logs fl
                JOIN fct_food_items ffi ON fl.food_id = ffi.food_id
                JOIN fct_nutrients fn ON ffi.id = fn.food_item_id
                WHERE fn.nutrient_name IN ($nph) AND fl.id IN ($ph)
                GROUP BY fl.id, fl.meal_type, fn.nutrient_name";
        $stmt2 = $conn->prepare($sql);
        $stmt2->execute($params);
        foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $n = $row['nutrient_name'];
            $mt = $row['meal_type'];
            $v = (float)$row['val'];
            if (isset($meal_micros[$mt]) && isset($meal_micros[$mt][$n])) $meal_micros[$mt][$n] += $v;
            if (isset($day_micros[$n])) $day_micros[$n] += $v;
        }
    }
}

foreach ($logs as $log) {
    if (isset($grouped_logs[$log['meal_type']])) {
        $grouped_logs[$log['meal_type']][] = $log;
    }
    $totals['calories'] += (float)$log['calories'];
    $totals['protein'] += (float)$log['protein'];
    $totals['carbs'] += (float)$log['carbs'];
    $totals['fat'] += (float)$log['fat'];
}

// Fetch clinical report if exists
try {
    $report_stmt = $conn->prepare("SELECT * FROM clinical_reports WHERE user_id = ? AND log_date = ?");
    $report_stmt->execute([$user_id, $selected_date]);
    $saved_report = $report_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
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
        $report_stmt->execute([$user_id, $selected_date]);
        $saved_report = $report_stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        throw $e;
    }
}

$nav_links = getNavigationLinks($user_role, 'user-diary.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <script src="scripts/theme-toggle.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>My Food Diary | NutriDeq</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/responsive.css">
    <!-- Platform Specific Styles -->
    <link rel="stylesheet" href="css/desktop-style.css" media="all and (min-width: 1025px)">
    <link rel="stylesheet" href="css/mobile-style.css" media="all and (max-width: 1024px)">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="stylesheet" href="css/logout-modal.css">
    <script src="scripts/dashboard.js" defer></script>
    <script src="scripts/dashboard-store.js" defer></script>
    <style>
        :root {
            --primary: #10b981;
            --gray: #64748b;
            --dark: #0f172a;
        }
        .diary-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 15px;
        }

        /* Mobile Clinical Report Responsiveness */
        .clinical-report-container {
            width: 100%;
            max-width: 820px;
            margin: 0 auto;
            font-family: 'Inter', sans-serif;
            background: white;
            border-radius: 20px;
            padding: 48px;
            box-sizing: border-box;
            box-shadow: 0 4px 24px rgba(0,0,0,0.05);
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
            #clinicalReportModal .modal-overlay > div {
                width: 100% !important;
                height: 100% !important;
                max-width: 100vw !important;
                max-height: var(--app-height-dvh) !important;
                margin: 0 !important;
                border-radius: 0 !important;
                display: flex !important;
                flex-direction: column !important;
            }
            
            #reportContentArea {
                padding: 10px !important;
                flex: 1 !important;
                overflow-y: auto !important;
                background: #f1f5f9 !important;
            }

            .clinical-report-container {
                padding: 15px !important;
                border-radius: 12px !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                box-shadow: none !important;
            }

            .report-header-grid {
                flex-direction: column !important;
                align-items: center !important;
                text-align: center !important;
                gap: 15px !important;
                padding-bottom: 15px !important;
                margin-bottom: 20px !important;
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

            .macro-summary-grid {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 10px !important;
                margin-bottom: 20px !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }
            .macro-summary-grid > div {
                width: 100% !important;
                margin: 0 !important;
                padding: 15px 10px !important;
                min-width: 0 !important;
                box-sizing: border-box !important;
                background: #f8fafc !important;
                border-radius: 12px !important;
                border: 1px solid #e2e8f0 !important;
            }
            .macro-summary-grid p {
                font-size: 0.7rem !important;
                white-space: normal !important;
                line-height: 1.3 !important;
                margin-bottom: 4px !important;
            }
            .macro-summary-grid p[style*="font-weight: 900"] {
                font-size: 1.1rem !important;
                margin: 4px 0 !important;
            }

            .signature-block-grid {
                grid-template-columns: 1fr !important;
                gap: 25px !important;
                margin-top: 30px !important;
            }

            .table-responsive-report {
                margin: 0 -5px !important;
                width: calc(100% + 10px) !important;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            .table-responsive-report table { min-width: 500px; }

            .modal-footer-report {
                flex-direction: column !important;
                gap: 8px !important;
                padding: 12px !important;
            }
            .modal-footer-report button { 
                width: 100% !important; 
                margin: 0 !important; 
                padding: 12px !important;
                font-size: 0.9rem !important;
            }
        }

        .date-nav {
            display: flex;
            align-items: center;
            gap: 15px;
            background: white;
            padding: 10px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .date-nav h2 { margin: 0; font-size: 1.1rem; min-width: 150px; text-align: center; }
        .date-nav a { color: var(--primary); font-size: 1.2rem; transition: transform 0.2s; }
        .date-nav a:hover { transform: scale(1.1); }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 2rem;
        }
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            text-align: center;
            position: relative;
            overflow: hidden;
            border-bottom: 4px solid var(--primary);
        }
        .summary-card h3 { margin: 0; font-size: 0.9rem; color: var(--gray); font-weight: 500; }
        .summary-card .value { font-size: 1.5rem; font-weight: 700; color: var(--dark); margin: 5px 0; }
        .summary-card .unit { font-size: 0.8rem; color: var(--gray); }

        .meal-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        .meal-header {
            background: #f8f9fa;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
        }
        .meal-header h3 { margin: 0; font-size: 1.1rem; color: var(--primary); }
        .meal-total { font-size: 0.85rem; color: var(--gray); font-weight: 500; }

        .food-item {
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f1f1f1;
            transition: background 0.2s;
        }
        .food-item:last-child { border-bottom: none; }
        .food-item:hover { background: #fafafa; }
        .food-info h4 { margin: 0; font-size: 1rem; color: var(--dark); }
        .food-info p { margin: 5px 0 0; font-size: 0.8rem; color: var(--gray); }
        .food-macros { text-align: right; }
        .food-macros .cals { font-weight: 600; color: var(--dark); display: block; }
        .food-macros .macro-breakdown { font-size: 0.75rem; color: var(--gray); }

        .no-logs {
            padding: 30px;
            text-align: center;
            color: #ccc;
        }
        .no-logs i { font-size: 2.5rem; margin-bottom: 10px; display: block; }

        .progress-container {
            margin-top: 10px;
            height: 8px;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            background: var(--primary);
            width: 0%;
            transition: width 0.5s ease-out;
        }

        /* Feedback Remarks Styling */
        .remarks-section {
            margin-top: 3rem;
            padding: 25px;
            background: #fdfdfd;
            border-radius: 15px;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        }
        .remarks-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            color: var(--primary);
            font-weight: 600;
        }
        .remark-card {
            background: #fff;
            border-left: 4px solid var(--primary);
            padding: 15px 20px;
            margin-bottom: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }
        .remark-meta {
            font-size: 0.75rem;
            color: var(--gray);
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
        }
        .remark-content {
            font-size: 0.95rem;
            color: #444;
            line-height: 1.5;
        }
        .modal-overlay {
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
        }
        
        @media screen and (max-width: 768px) {
            .modal-container {
                width: 95% !important;
                margin: 10px auto !important;
                max-height: 95vh;
            }
            .modal-body {
                padding: 15px !important;
            }
            .fct-table thead {
                display: none;
            }
            .fct-table, .fct-table tbody, .fct-table tr, .fct-table td {
                display: block;
                width: 100%;
            }
            .fct-table tr {
                margin-bottom: 15px;
                border: 1px solid #eee !important;
                border-radius: 12px;
                padding: 10px;
                background: #fff;
                box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            }
            .fct-table td {
                text-align: left !important;
                padding: 8px 5px !important;
                border: none !important;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .fct-table td::before {
                content: attr(data-label);
                font-weight: 600;
                color: var(--gray);
                font-size: 0.85rem;
            }
            .fct-table td:first-child {
                border-bottom: 1px solid #f9f9f9 !important;
                margin-bottom: 5px;
            }
            /* Reset data labels for clear view */
            .fct-table td:nth-child(2) {
                font-size: 1.1rem;
                display: block;
            }
            .fct-table td:nth-child(2)::before {
                display: none;
            }
            
            .modal-footer {
                display: none !important;
            }
            .modal-header {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 15px;
            }
            .modal-header-actions {
                width: 100%;
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            .modal-header-actions .btn {
                width: 100%;
                padding: 12px !important;
            }
            .modal-body {
                padding: 15px !important;
            }
            /* Adjust sticky search for mobile header growth */
            .modal-controls {
                top: -15px !important; 
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
            <div class="page-container">
                <div class="diary-header">
                    <div class="page-title">
                        <h1>My Food Diary</h1>
                        <p>Track your daily nutritional intake</p>
                    </div>

                    <div class="date-nav">
                        <?php 
                        $prev_date = date('Y-m-d', strtotime($selected_date . ' -1 day'));
                        $next_date = date('Y-m-d', strtotime($selected_date . ' +1 day'));
                        $date_label = ($selected_date == $today) ? 'Today' : date('M j, Y', strtotime($selected_date));
                        ?>
                        <a href="?date=<?php echo $prev_date; ?>"><i class="fas fa-chevron-left"></i></a>
                        <h2><?php echo $date_label; ?></h2>
                        <a href="?date=<?php echo $next_date; ?>"><i class="fas fa-chevron-right"></i></a>
                    </div>

                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <?php if ($saved_report): ?>
                            <button class="btn-primary" onclick="viewClinicalReport()" style="background: #10b981; border: none; border-radius: 12px; padding: 10px 20px; font-weight: 600; display: flex; align-items: center; gap: 8px; color: white; cursor: pointer;">
                                <i class="fas fa-file-medical"></i> View Clinical Report
                            </button>
                        <?php endif; ?>
                        <button class="btn-primary" onclick="window.analyzeTodayIntake({outputId:'todayAnalysisResult', date:'<?php echo $selected_date; ?>'})"
                                style="background: #0ea5e9; border: none; border-radius: 12px; padding: 10px 18px; font-weight: 600; display: flex; align-items: center; gap: 8px; color: white; cursor: pointer; transition: all 0.2s;">
                            <i class="fas fa-magnifying-glass-chart"></i> Analyze Food Today
                        </button>
                        <button class="btn-primary" onclick="openFctModal('Breakfast')" style="background: var(--primary); color: white; border: none; border-radius: 12px; padding: 10px 20px; font-weight: 600; cursor: pointer;">
                            <i class="fas fa-plus"></i> Add Log
                        </button>
                    </div>
                </div>

                <div class="summary-cards">
                    <div class="summary-card">
                        <h3>Calories</h3>
                        <div class="value"><?php echo number_format($totals['calories'], 0); ?></div>
                        <div class="unit">kcal</div>
                    </div>
                    <div class="summary-card" style="border-bottom-color: #4a90e2;">
                        <h3>Protein</h3>
                        <div class="value"><?php echo number_format($totals['protein'], 1); ?></div>
                        <div class="unit">g</div>
                    </div>
                    <div class="summary-card" style="border-bottom-color: #f5a623;">
                        <h3>Carbs</h3>
                        <div class="value"><?php echo number_format($totals['carbs'], 1); ?></div>
                        <div class="unit">g</div>
                    </div>
                    <div class="summary-card" style="border-bottom-color: #d0021b;">
                        <h3>Fat</h3>
                        <div class="value"><?php echo number_format($totals['fat'], 1); ?></div>
                        <div class="unit">g</div>
                    </div>
                </div>

                <div id="todayAnalysisResult" style="display:none; margin-bottom:24px;"></div>

                <?php foreach ($grouped_logs as $meal => $items):
                    $mm = $meal_micros[$meal] ?? [];
                    $meal_has_micro = false;
                    foreach ($mm as $v) { if ($v > 0) { $meal_has_micro = true; break; } }
                ?>
                    <section class="meal-section">
                        <div class="meal-header">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <h3><?php echo $meal; ?></h3>
                                <button type="button" class="add-meal-btn" 
                                    style="color: #4a90e2; font-size: 1.3rem; background: none; border: none; padding: 0; cursor: pointer; transition: transform 0.2s;"
                                    onclick="openCustomMealModal('<?php echo $meal; ?>')" title="Add custom meal to <?php echo $meal; ?>"
                                    onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="add-meal-btn" 
                                    style="color: #2D8A56; font-size: 1.3rem; background: none; border: none; padding: 0; cursor: pointer; transition: transform 0.2s;"
                                    onclick="openFctModal('<?php echo $meal; ?>')" title="Add food to <?php echo $meal; ?>"
                                    onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'">
                                    <i class="fas fa-plus-circle"></i>
                                </button>
                            </div>
                            <?php 
                            $meal_cals = array_sum(array_column($items, 'calories'));
                            ?>
                            <div class="meal-total"><?php echo number_format($meal_cals, 0); ?> kcal</div>
                        </div>
                        <div class="meal-content">
                            <?php if (empty($items)): ?>
                                <div class="no-logs">
                                    <i class="fas fa-utensils"></i>
                                    <p>Nothing logged for <?php echo strtolower($meal); ?></p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                    <div class="food-item" style="display: flex; flex-direction: column; align-items: stretch;">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; width: 100%;">
                                            <div class="food-info" style="flex: 1;">
                                                <h4><?php echo htmlspecialchars($item['food_name']); ?></h4>
                                                <p style="margin-bottom: 8px;"><?php echo htmlspecialchars($item['display_size'] ?: number_format($item['serving_size'], 0) . 'g'); ?></p>
                                                
                                                <?php 
                                                $micros = $fct->getMicronutrientsForFood($item['food_id'], $item['serving_size']);
                                                if (!empty($micros)): 
                                                ?>
                                                <div style="font-size: 0.75rem; color: #64748b; margin-top: 6px; line-height: 1.4; display: flex; flex-wrap: wrap; gap: 8px;">
                                                    <?php foreach($micros as $micro): ?>
                                                        <span style="background: rgba(16, 185, 129, 0.1); padding: 2px 8px; border-radius: 12px; color: #059669; font-weight: 600;"><?php echo htmlspecialchars($micro); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <div style="display: flex; flex-direction: column; align-items: flex-end;">
                                                <div class="food-macros" style="text-align: right; margin-bottom: 4px;">
                                                    <span class="cals" style="font-weight: 700; color: #1e293b; font-size: 1.1rem;"><?php echo number_format($item['calories'], 0); ?> kcal</span>
                                                </div>
                                                <div style="display: flex; align-items: center; gap: 12px;">
                                                    <span class="macro-breakdown" style="font-size: 0.85rem; color: #64748b;">
                                                        P: <?php echo number_format($item['protein'], 1); ?>g | 
                                                        C: <?php echo number_format($item['carbs'], 1); ?>g | 
                                                        F: <?php echo number_format($item['fat'], 1); ?>g
                                                    </span>
                                                    <?php if ($user_role === 'user'): ?>
                                                        <button onclick="deleteLog(<?php echo $item['id']; ?>)" class="btn-delete" style="color: #ef4444; background: none; border: none; cursor: pointer; font-size: 1.1rem;">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($items) && $meal_has_micro): ?>
                        <div style="margin-top:10px;">
                            <button type="button" onclick="toggleMealMicro('mm_<?php echo md5($meal); ?>')"
                                    style="width:100%;display:flex;justify-content:space-between;align-items:center;padding:8px 14px;background:rgba(14,165,233,0.06);border:1px solid rgba(14,165,233,0.15);border-radius:10px;font-size:0.8rem;font-weight:600;color:#0369a1;cursor:pointer;">
                                <span><i class="fas fa-flask-vial" style="margin-right:6px;"></i> Show Micronutrient Breakdown (<?php echo count($TRACKED_MICRO); ?> tracked)</span>
                                <i class="fas fa-chevron-down mm_toggle" id="mm_toggle_<?php echo md5($meal); ?>" style="transition:transform .25s ease;"></i>
                            </button>
                            <div id="mm_<?php echo md5($meal); ?>" style="display:none;padding:14px 6px 0 6px;">
                                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
                                <?php foreach ($TRACKED_MICRO as $n):
                                    $v = $mm[$n] ?? 0;
                                    if ($v <= 0) {
                                        echo '<div style="padding:10px;border-radius:10px;background:#f8fafc;border:1px dashed #e2e8f0;opacity:.75;">
                                            <div style="font-size:0.72rem;color:#94a3b8;font-weight:600;">'.htmlspecialchars($n).'</div>
                                            <div style="font-weight:700;color:#94a3b8;margin-top:2px;">0 '.$MICRO_UNITS[$n].'</div>
                                        </div>';
                                        continue;
                                    }
                                    $th = $MICRO_THRESHOLDS[$n] ?? 0;
                                    $pct = $th > 0 ? min(100, round(($v/$th)*100, 0)) : 0;
                                    $below = ($th > 0 && $v < $th);
                                    $col = $below ? ($pct<40?'#ef4444':'#f59e0b') : '#10b981';
                                ?>
                                    <div style="padding:10px 12px;border-radius:10px;background:<?php echo $below ? 'rgba(239,68,68,0.04)' : 'rgba(16,185,129,0.04)'; ?>;border:1px solid <?php echo $below ? 'rgba(239,68,68,0.15)' : 'rgba(16,185,129,0.15)'; ?>;">
                                        <div style="display:flex;justify-content:space-between;align-items:baseline;">
                                            <div style="font-size:0.72rem;color:#475569;font-weight:600;"><?php echo htmlspecialchars($n); ?></div>
                                            <div style="font-size:0.65rem;font-weight:700;color:<?php echo $col; ?>;"><?php echo $pct; ?>%</div>
                                        </div>
                                        <div style="font-weight:700;color:<?php echo $col; ?>;font-size:0.9rem;margin:2px 0 6px 0;">
                                            <?php echo number_format($v, 1); ?> <span style="font-size:0.7rem;font-weight:500;color:#94a3b8;">/ <?php echo $th.$MICRO_UNITS[$n]; ?></span>
                                        </div>
                                        <div style="height:4px;background:#e2e8f0;border-radius:20px;overflow:hidden;">
                                            <div style="width:<?php echo $pct; ?>%;height:100%;background:<?php echo $col; ?>;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>


                <!-- Dietitian's Remarks Section -->
                <?php
                $feedback_sql = "SELECT df.*, u.name as staff_name FROM diary_feedback df JOIN users u ON df.staff_id = u.id WHERE df.user_id = ? AND df.log_date = ? ORDER BY df.created_at DESC";
                $f_stmt = $conn->prepare($feedback_sql);
                $f_stmt->execute([$user_id, $selected_date]);
                $feedbacks = $f_stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <section class="remarks-section">
                    <div class="remarks-header" style="display:flex; justify-content:space-between; align-items:center;">
                        <div style="display:flex; align-items:center; gap:10px; color:var(--primary); font-weight:600;">
                            <i class="fas fa-user-md"></i>
                            <span>Dietitian's Remarks</span>
                        
                    <?php if (empty($feedbacks)): ?>
                        <p style="color: #ccc; font-style: italic; font-size: 0.9rem;">No remarks for this day yet.</p>
                    <?php else: ?>
                        <?php foreach ($feedbacks as $fb): ?>
                            <div class="remark-card">
                                <div class="remark-meta">
                                    <span><strong><?php echo htmlspecialchars($fb['staff_name']); ?></strong></span>
                                    <span><?php echo date('M j, Y g:i A', strtotime($fb['created_at'])); ?></span>
                                </div>
                                <div class="remark-content"><?php echo nl2br(htmlspecialchars($fb['content'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>

    <!-- User Personal Report Modal -->
    <div id="userReportOverlay" style="display:none; position:fixed; top:0; left:0; width:100vw; height: var(--app-height-dvh); background:rgba(15,23,42,0.5); backdrop-filter:blur(8px); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:white; width:90%; max-width:860px; max-height:90vh; border-radius:24px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 30px 60px rgba(0,0,0,0.2);">
            <div style="padding:20px 28px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
                <h3 style="margin:0; font-size:1rem; color:#1e293b;"><i class="fas fa-file-medical" style="color:#10b981; margin-right:8px;"></i>My Personal Health Report</h3>
                <button onclick="toggleUserReport(false)" style="background:none; border:none; font-size:1.3rem; cursor:pointer; color:#94a3b8;"><i class="fas fa-times"></i></button>
            </div>
            <div style="flex:1; overflow-y:auto; padding:32px; background:#f8fafc;">
                <div id="userReportPrintArea" style="max-width:780px; margin:0 auto; background:white; border-radius:18px; padding:40px; box-shadow:0 4px 24px rgba(0,0,0,0.06); font-family:'Inter',sans-serif;">
                    <!-- Header -->
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px; border-bottom:3px solid #10b981; padding-bottom:20px;">
                        <div>
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:4px;">
                                <img src="assets/img/logo.png" style="width:36px; height:36px; border-radius:8px;" alt="NutriDeq">
                                <h2 style="color:#10b981; margin:0; font-size:1.5rem; font-family:'Outfit',sans-serif;">NutriDeq</h2>
                            </div>
                            <p style="color:#64748b; margin:0; font-size:0.8rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Personal Health & Nutrition Report</p>
                        </div>
                        <div style="text-align:right;">
                            <p style="font-weight:700; margin:0; color:#1e293b;"><?php echo date('F j, Y'); ?></p>
                            <p style="color:#94a3b8; margin:4px 0; font-size:0.78rem;"><?php echo date('M j, Y', strtotime($selected_date)); ?> — Daily Review</p>
                        </div>
                    </div>

                    <!-- Patient Info -->
                    <div style="background:#f8fafc; border-radius:12px; padding:16px 20px; margin-bottom:28px; display:flex; gap:40px;">
                        <div>
                            <p style="color:#10b981; font-size:0.68rem; font-weight:700; text-transform:uppercase; margin:0 0 4px;">Patient</p>
                            <p style="font-weight:700; margin:0; color:#1e293b;"><?php echo htmlspecialchars($current_user_name); ?></p>
                            <p style="font-size:0.75rem; color:#64748b; margin:2px 0 0;"><?php echo htmlspecialchars($current_user_email); ?></p>
                        </div>
                        <div>
                            <p style="color:#10b981; font-size:0.68rem; font-weight:700; text-transform:uppercase; margin:0 0 4px;">Review Date</p>
                            <p style="font-weight:700; margin:0; color:#1e293b;"><?php echo date('D, M j, Y', strtotime($selected_date)); ?></p>
                        </div>
                    </div>

                    <!-- Macro Summary Cards -->
                    <h4 style="color:#1e293b; font-size:0.82rem; font-weight:700; margin-bottom:12px; text-transform:uppercase; letter-spacing:0.5px;">📊 Nutritional Summary</h4>
                    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:28px;">
                        <?php
                        $dri = ['calories'=>2000,'protein'=>50,'carbs'=>275,'fat'=>78];
                        $mcol = ['calories'=>'#ef4444','protein'=>'#3b82f6','carbs'=>'#f59e0b','fat'=>'#8b5cf6'];
                        $mlab = ['calories'=>['Energy','kcal'],'protein'=>['Protein','g'],'carbs'=>['Carbs','g'],'fat'=>['Fat','g']];
                        foreach ($mlab as $k=>[$l,$u]):
                            $p = min(100, round($totals[$k]/$dri[$k]*100));
                            $v = $k==='calories' ? number_format($totals[$k],0) : number_format($totals[$k],1);
                            $c = $mcol[$k];
                            $fl = $p>110?'⚠ High':($p<50?'↓ Low':'✓ OK');
                            $fc = $p>110?'#ef4444':($p<50?'#f59e0b':'#10b981');
                        ?>
                        <div style="background:#f8fafc; padding:14px; border-radius:12px; border-top:3px solid <?php echo $c; ?>; text-align:center;">
                            <p style="color:#64748b; font-size:0.65rem; margin:0 0 4px; text-transform:uppercase; font-weight:600;"><?php echo $l; ?></p>
                            <p style="font-weight:900; font-size:1.2rem; margin:0; color:#1e293b;"><?php echo $v; ?><small style="font-size:0.5em; color:#94a3b8; margin-left:2px;"><?php echo $u; ?></small></p>
                            <div style="margin-top:6px; height:4px; background:#e2e8f0; border-radius:4px; overflow:hidden;"><div style="height:100%; width:<?php echo $p; ?>%; background:<?php echo $c; ?>; border-radius:4px;"></div></div>
                            <p style="margin:4px 0 0; font-size:0.62rem; font-weight:700; color:<?php echo $fc; ?>;"><?php echo $fl; ?> (<?php echo $p; ?>% DRI)</p>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Meal breakdown -->
                    <h4 style="color:#1e293b; font-size:0.82rem; font-weight:700; margin-bottom:12px; text-transform:uppercase; letter-spacing:0.5px;">🍽 What I Ate Today</h4>
                    <?php
                    $uicons = ['Breakfast'=>'☀️','AM Snack'=>'🍎','Lunch'=>'🥗','PM Snack'=>'🍪','Dinner'=>'🌙','Snack'=>'🍎'];
                    foreach ($grouped_logs as $meal => $items):
                        $mc = array_sum(array_column($items,'calories'));
                        $mp = array_sum(array_column($items,'protein'));
                        $mca = array_sum(array_column($items,'carbs'));
                        $mf = array_sum(array_column($items,'fat'));
                    ?>
                    <div style="margin-bottom:18px; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;">
                        <div style="background:#f1fdf7; padding:10px 16px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #d1fae5;">
                            <span style="font-weight:700; color:#1e293b; font-size:0.9rem;"><?php echo ($uicons[$meal]??'🍽').' '.$meal; ?></span>
                            <?php if (!empty($items)): ?>
                            <span style="font-size:0.75rem; color:#10b981; font-weight:700; background:white; padding:2px 10px; border-radius:20px; border:1px solid #d1fae5;">
                                <?php echo number_format($mc,0); ?> kcal | P:<?php echo number_format($mp,1); ?>g C:<?php echo number_format($mca,1); ?>g F:<?php echo number_format($mf,1); ?>g
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php if(empty($items)): ?>
                        <div style="padding:14px; text-align:center; color:#cbd5e1; font-size:0.82rem; font-style:italic;">Nothing logged.</div>
                        <?php else: ?>
                        <table style="width:100%; border-collapse:collapse; font-size:0.8rem;">
                            <thead><tr style="background:#f8fafc; color:#64748b; font-weight:600; font-size:0.68rem; text-transform:uppercase;">
                                <th style="padding:7px 14px; text-align:left;">Food</th>
                                <th style="padding:7px; text-align:center;">Serving</th>
                                <th style="padding:7px; text-align:center;">kcal</th>
                                <th style="padding:7px; text-align:center;">Protein</th>
                                <th style="padding:7px; text-align:center;">Carbs</th>
                                <th style="padding:7px; text-align:center;">Fat</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach($items as $it): ?>
                            <tr style="border-top:1px solid #f1f5f9;">
                                <td style="padding:9px 14px; font-weight:600; color:#1e293b;"><?php echo htmlspecialchars($it['food_name']); ?></td>
                                <td style="padding:9px; text-align:center; color:#64748b;"><?php echo htmlspecialchars($it['display_size'] ?: number_format($it['serving_size'], 0) . 'g'); ?></td>
                                <td style="padding:9px; text-align:center; font-weight:700; color:#ef4444;"><?php echo number_format($it['calories'],0); ?></td>
                                <td style="padding:9px; text-align:center; color:#3b82f6;"><?php echo number_format($it['protein'],1); ?>g</td>
                                <td style="padding:9px; text-align:center; color:#f59e0b;"><?php echo number_format($it['carbs'],1); ?>g</td>
                                <td style="padding:9px; text-align:center; color:#8b5cf6;"><?php echo number_format($it['fat'],1); ?>g</td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <!-- Dietitian Remarks inside user report -->
                    <?php if (!empty($feedbacks)): ?>
                    <h4 style="color:#1e293b; font-size:0.82rem; font-weight:700; margin:24px 0 12px; text-transform:uppercase; letter-spacing:0.5px;">📝 Dietitian Notes For You</h4>
                    <?php foreach ($feedbacks as $fb): ?>
                    <div style="background:#f8fafc; border-left:4px solid #10b981; padding:12px 16px; border-radius:10px; margin-bottom:10px;">
                        <p style="font-size:0.72rem; color:#94a3b8; margin:0 0 4px;"><strong style="color:#1e293b;"><?php echo htmlspecialchars($fb['staff_name']); ?></strong> · <?php echo date('M j, Y g:i A', strtotime($fb['created_at'])); ?></p>
                        <p style="color:#1e293b; margin:0; font-size:0.875rem; line-height:1.6;"><?php echo nl2br(htmlspecialchars($fb['content'])); ?></p>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Footer -->
                    <div style="margin-top:32px; padding-top:20px; border-top:1px solid #e2e8f0; text-align:center; color:#cbd5e1; font-size:0.7rem; line-height:1.7;">
                        Personal Health Report — NutriDeq Clinical Platform · Confidential · <?php echo date('F j, Y g:i A'); ?>
                    </div>
                </div>
            </div>
            <div style="padding:16px 28px; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end; gap:12px; background:white;">
                <button onclick="toggleUserReport(false)" style="padding:10px 20px; border-radius:10px; border:1.5px solid #e2e8f0; background:white; cursor:pointer; font-weight:600; color:#475569;">Close</button>
                <button onclick="generateClinicalReport('#userReportPrintArea','My-NutriDeq-Report-<?php echo $selected_date; ?>.pdf')" style="padding:10px 24px; border-radius:10px; background:#10b981; color:white; border:none; font-weight:700; cursor:pointer; box-shadow:0 4px 15px rgba(16,185,129,0.25);">
                    <i class="fas fa-file-medical"></i> Download PDF
                </button>
            </div>
        </div>
    </div>

    <!-- Custom Meal Modal -->
    <div id="customMealModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10001; overflow-y: auto;">
        <div class="modal-container" style="background: white; width: 90%; max-width: 500px; margin: 50px auto; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
            <div class="modal-header" style="background: #f8f9fa; padding: 20px 25px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                <h2 id="customMealTitle" style="margin: 0; color: #4a90e2;">Add Custom Meal</h2>
                <button type="button" onclick="closeCustomMealModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--gray);">&times;</button>
            </div>
            <form id="customMealForm" style="padding: 25px;">
                <input type="hidden" name="meal_type" id="customMealType">
                <input type="hidden" name="log_date" value="<?php echo $selected_date; ?>">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Meal Name</label>
                    <input type="text" name="food_name" class="form-control" placeholder="e.g. Homemade Adobo" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Serving Quantity</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="number" name="serving_size" id="customServingVal" class="form-control" placeholder="0" required style="flex: 2; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                        <select name="serving_unit" id="customServingUnit" class="form-control" style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                            <option value="g">grams</option>
                            <option value="ml">ml</option>
                            <option value="cup">cup(s)</option>
                            <option value="slice">slice(s)</option>
                            <option value="piece">piece(s)</option>
                            <option value="tbsp">tbsp</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">Calories (kcal)</label>
                        <input type="number" step="any" name="calories" class="form-control" placeholder="0" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">Protein (g)</label>
                        <input type="number" step="any" name="protein" class="form-control" placeholder="0" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">Carbs (g)</label>
                        <input type="number" step="any" name="carbs" class="form-control" placeholder="0" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">Fat (g)</label>
                        <input type="number" step="any" name="fat" class="form-control" placeholder="0" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                    </div>
                </div>

                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <button type="button" onclick="closeCustomMealModal()" style="flex: 1; padding: 12px; border-radius: 10px; border: 1px solid #ddd; background: white; cursor: pointer;">Cancel</button>
                    <button type="submit" style="flex: 2; padding: 12px; border-radius: 10px; border: none; background: #4a90e2; color: white; font-weight: 600; cursor: pointer;">Save Meal</button>
                </div>
                <p style="margin-top: 15px; font-size: 0.8rem; color: #666; text-align: center; font-style: italic;">
                    Tip: Macros should be for the <b>entire serving</b> you entered above.
                </p>
            </form>
        </div>
    </div>



    <!-- FCT Library Modal -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function toggleUserReport(show) {
            const overlay = document.getElementById('userReportOverlay');
            overlay.style.display = show ? 'flex' : 'none';
        }
        function generateClinicalReport(targetSelector, filename) {
            const element = document.querySelector(targetSelector);
            if (!element) return alert('Report content not found.');
            const btn = event.currentTarget || document.activeElement;
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
            btn.style.opacity = '0.7';
            btn.style.pointerEvents = 'none';
            html2pdf().set({
                margin: 10,
                filename: filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            }).from(element).save().then(() => {
                btn.innerHTML = '<i class="fas fa-check"></i> Saved!';
                btn.style.backgroundColor = '#059669';
                setTimeout(() => {
                    btn.innerHTML = orig;
                    btn.style.opacity = '1';
                    btn.style.pointerEvents = 'auto';
                    btn.style.backgroundColor = '#10b981';
                }, 3000);
            }).catch(err => {
                console.error(err);
                btn.innerHTML = orig;
                btn.style.opacity = '1';
                btn.style.pointerEvents = 'auto';
            });
        }
    </script>
    <!-- FCT Library Modal -->
    <div id="fctModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; overflow-y: auto;">
        <div class="modal-container" style="background: white; width: 95%; max-width: 1000px; margin: 30px auto; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.2); display: flex; flex-direction: column; max-height: calc(100vh - 60px);">
            <div class="modal-header" style="background: #f8f9fa; padding: 20px 25px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
                <div>
                    <h2 id="fctModalTitle" style="margin: 0; color: var(--primary);">Add Food to Diary</h2>
                    <div id="selectedCount" style="font-size: 0.85rem; color: var(--gray); margin-top: 4px;">0 items selected</div>
                </div>
                <div class="modal-header-actions" style="display: flex; gap: 10px; align-items: center;">
                    <button type="button" class="btn btn-outline" style="background: white; border: 1px solid #ddd; padding: 8px 15px; border-radius: 8px; cursor: pointer;" onclick="closeFctModal()">Cancel</button>
                    <button type="button" id="btnAddSelected" class="btn btn-primary" style="background: var(--primary); color: white; border: none; padding: 8px 20px; border-radius: 8px; cursor: pointer; font-weight: 500;" onclick="submitSelectedFoods()">Add Selected</button>
                    <button type="button" onclick="closeFctModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--gray); margin-left: 5px;">&times;</button>
                </div>
            </div>
            <div class="modal-body" style="padding: 25px; overflow-y: auto; flex-grow: 1;">
                <div class="modal-controls" style="margin-bottom: 20px; display: flex; gap: 15px; flex-wrap: wrap; position: sticky; top: -25px; background: white; padding: 10px 0; z-index: 10; align-items: center;">
                    <div style="flex-grow: 1; position: relative;">
                        <span style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #9ca3af;"><i class="fas fa-search"></i></span>
                        <input type="text" id="fctSearch" placeholder="Search food items..." style="width: 100%; padding: 12px 12px 12px 40px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.95rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                    </div>
                    <button type="button" onclick="openCustomMealModal(currentMealType)" style="background: #f0faf5; color: #10b981; border: 1.5px dashed #10b981; padding: 10px 18px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                        <i class="fas fa-magic"></i> Add Custom Food
                    </button>
                </div>
                <div id="fctTableContainer">
                    <table class="fct-table" style="width: 100%; border-collapse: collapse;">
                        <thead style="position: sticky; top: 0; background: white; z-index: 1;">
                            <tr style="border-bottom: 2px solid #eee;">
                                <th style="padding: 12px; text-align: left; width: 40px;"><input type="checkbox" id="selectAll"></th>
                                <th style="padding: 12px; text-align: left;">Food Item</th>
                                <th style="padding: 12px; text-align: left; width: 220px;">Serving</th>
                                <th style="padding: 12px; text-align: center;">Calories</th>
                                <th style="padding: 12px; text-align: center;">Protein</th>
                                <th style="padding: 12px; text-align: center;">Carbs</th>
                                <th style="padding: 12px; text-align: center;">Fat</th>
                            </tr>
                        </thead>
                        <tbody id="fctTableBody">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Clinical Report Modal -->
    <?php if ($saved_report): ?>
    <div id="clinicalReportModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000; overflow-y:auto; padding: 20px;">
        <div style="background: white; max-width: 900px; margin: 40px auto; border-radius: 24px; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.2);">
            <div style="padding: 20px 32px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
                <h3 style="margin:0; font-family: 'Poppins', sans-serif; color: #1e293b;"><i class="fas fa-file-medical" style="color:#10b981;"></i> Clinical Diagnostic Report</h3>
                <button onclick="closeClinicalReport()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#94a3b8;"><i class="fas fa-times"></i></button>
            </div>
            <div id="reportContentArea" style="padding: 40px; background: #f1f5f9;">
                <div style="background: white; border-radius: 16px; padding: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
                    <?php echo $saved_report['report_content']; ?>
                </div>
            </div>
            <div style="padding: 20px 32px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 12px; background: #f8fafc;" class="modal-footer-report">
                <button onclick="closeClinicalReport()" style="padding: 10px 24px; border-radius: 10px; border: 1px solid #e2e8f0; background: white; cursor: pointer; font-weight: 600;">Close</button>
                <button id="downloadPdfBtn" onclick="generateUserPDF()" style="padding: 10px 24px; border-radius: 10px; background: #10b981; color: white; border: none; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-file-download"></i> Download PDF Report
                </button>
            </div>
        </div>
    </div>
    <script>
        function viewClinicalReport() {
            document.getElementById('clinicalReportModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        function closeClinicalReport() {
            document.getElementById('clinicalReportModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        function generateUserPDF() {
            const element = document.querySelector('#reportContentArea > div');
            if (!element) return alert("Error: Report content not found.");
            
            const btn = document.getElementById('downloadPdfBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
            btn.style.opacity = '0.7';
            btn.style.pointerEvents = 'none';

            const opt = {
                margin:       10,
                filename:     'NutriDeq-Clinical-Report-<?php echo $selected_date; ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().set(opt).from(element).save().then(() => {
                btn.innerHTML = '<i class="fas fa-check"></i> Downloaded';
                btn.style.backgroundColor = '#059669';
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.opacity = '1';
                    btn.style.pointerEvents = 'auto';
                    btn.style.backgroundColor = '#10b981';
                }, 3000);
            }).catch(err => {
                console.error("PDF generation error: ", err);
                btn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Failed';
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.opacity = '1';
                    btn.style.pointerEvents = 'auto';
                }, 3000);
            });
        }
    </script>
    <?php endif; ?>

    <script>
        const currentUserRole = '<?php echo $user_role; ?>';
        let currentMealType = '';
        const allFctData = <?php echo json_encode($fct->getAllItems()); ?>;
        
        function openFctModal(mealType) {
            currentMealType = mealType;
            document.getElementById('fctModalTitle').innerText = 'Add Food to ' + mealType;
            document.getElementById('btnAddSelected').innerText = 'Add Selected to ' + mealType;
            document.getElementById('fctModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
            renderFctTable();
        }

        function closeFctModal() {
            document.getElementById('fctModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Custom Meal Modal Logic
        function openCustomMealModal(mealType) {
            currentMealType = mealType;
            document.getElementById('customMealType').value = mealType;
            document.getElementById('customMealTitle').innerText = 'Add Custom ' + mealType;
            document.getElementById('customMealModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeCustomMealModal() {
            document.getElementById('customMealModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            document.getElementById('customMealForm').reset();
        }

        document.getElementById('customMealForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerText;

            try {
                submitBtn.disabled = true;
                submitBtn.innerText = 'Saving...';

                const response = await fetch('api/save_custom_meal.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.message || 'Error saving custom meal');
                }
            } catch (err) {
                console.error(err);
                alert('Connection error. Please try again.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerText = originalText;
            }
        });

        // Conversion units logic
        const UNIT_MULTIPLIERS = {
            'g': 1,
            'ml': 1,
            'cup': 240,       // Approx average for many foods
            'slice': 35,      // Approx average slice
            'piece': 50,      // Approx average piece
            'tbsp': 15        // Approx 15g
        };

        function renderFctTable(filter = '') {
            const tbody = document.getElementById('fctTableBody');
            tbody.innerHTML = '';
            
            const filtered = allFctData.filter(item => 
                item.food_name.toLowerCase().includes(filter.toLowerCase()) ||
                (item.food_id && item.food_id.toString().includes(filter))
            ).slice(0, 100); 

            filtered.forEach(item => {
                const tr = document.createElement('tr');
                tr.id = `food-row-${item.id}`;
                tr.style.borderBottom = '1px solid #f1f1f1';
                tr.dataset.baseCals = item.calories || 0;
                tr.dataset.baseProtein = item.protein || 0;
                tr.dataset.baseCarbs = item.carbs || 0;
                tr.dataset.baseFat = item.fat || 0;

                tr.innerHTML = `
                    <td style="padding: 12px; text-align: left;"><input type="checkbox" class="food-checkbox" value="${item.id}" style="width: 20px; height: 20px;" onchange="updateSelectedCount()"></td>
                    <td data-label="Food Item" style="padding: 12px; text-align: left;">
                        <span style="font-weight: 500; color: var(--dark);">${item.food_name}</span><br>
                        <small style="color: var(--gray);">${item.category}</small>
                    </td>
                    <td data-label="Serving" style="padding: 12px; text-align: left;">
                        <div style="display: flex; gap: 5px;">
                            <input type="number" class="serving-qty" value="100" min="1" step="any" 
                                style="width: 70px; padding: 6px; border: 1px solid #ddd; border-radius: 6px;" 
                                oninput="calculateRowMacros(${item.id})">
                            <select class="serving-unit" style="padding: 6px; border: 1px solid #ddd; border-radius: 6px;" onchange="calculateRowMacros(${item.id})">
                                <option value="g" selected>g</option>
                                <option value="ml">ml</option>
                                <option value="cup">cup</option>
                                <option value="slice">slice</option>
                                <option value="piece">piece</option>
                                <option value="tbsp">tbsp</option>
                            </select>
                        </div>
                    </td>
                    <td data-label="Calories" class="row-cals" style="padding: 12px; text-align: center; font-weight: 600;">${parseFloat(item.calories || 0).toFixed(0)}</td>
                    <td data-label="Protein" class="row-protein" style="padding: 12px; text-align: center;">${parseFloat(item.protein || 0).toFixed(1)}</td>
                    <td data-label="Carbs" class="row-carbs" style="padding: 12px; text-align: center;">${parseFloat(item.carbs || 0).toFixed(1)}</td>
                    <td data-label="Fat" class="row-fat" style="padding: 12px; text-align: center;">${parseFloat(item.fat || 0).toFixed(1)}</td>
                `;
                tbody.appendChild(tr);
            });

            if (filtered.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="padding: 40px; text-align: center; color: var(--gray);">No food items found matching your search.</td></tr>';
            }
            updateSelectedCount();
        }

        function calculateRowMacros(id) {
            const row = document.getElementById(`food-row-${id}`);
            if (!row) return;

            const qty = parseFloat(row.querySelector('.serving-qty').value) || 0;
            const unit = row.querySelector('.serving-unit').value;
            const multiplier = UNIT_MULTIPLIERS[unit] || 1;
            
            // Core logic: nutrients are per 100g. Total grams = quantity * unit factor.
            const totalGrams = qty * multiplier;
            const ratio = totalGrams / 100;

            const cals = parseFloat(row.dataset.baseCals) * ratio;
            const protein = parseFloat(row.dataset.baseProtein) * ratio;
            const carbs = parseFloat(row.dataset.baseCarbs) * ratio;
            const fat = parseFloat(row.dataset.baseFat) * ratio;

            row.querySelector('.row-cals').innerText = cals.toFixed(0);
            row.querySelector('.row-protein').innerText = protein.toFixed(1);
            row.querySelector('.row-carbs').innerText = carbs.toFixed(1);
            row.querySelector('.row-fat').innerText = fat.toFixed(1);

            // Also check the checkbox if they edit quantity (ux improvement)
            const cb = row.querySelector('.food-checkbox');
            if (qty > 0 && !cb.checked) {
                cb.checked = true;
                updateSelectedCount();
            }
        }

        document.getElementById('fctSearch').addEventListener('input', (e) => {
            renderFctTable(e.target.value);
        });

        document.getElementById('selectAll').addEventListener('change', (e) => {
            document.querySelectorAll('.food-checkbox').forEach(cb => {
                cb.checked = e.target.checked;
            });
            updateSelectedCount();
        });

        function updateSelectedCount() {
            const count = document.querySelectorAll('.food-checkbox:checked').length;
            document.getElementById('selectedCount').innerText = count + ' items selected';
            document.getElementById('btnAddSelected').disabled = count === 0;
            document.getElementById('btnAddSelected').style.opacity = count === 0 ? '0.5' : '1';
        }

        async function submitSelectedFoods() {
            const selectedRows = Array.from(document.querySelectorAll('.food-checkbox:checked')).map(cb => {
                const row = cb.closest('tr');
                const qty = parseFloat(row.querySelector('.serving-qty').value) || 0;
                const unit = row.querySelector('.serving-unit').value;
                const totalGrams = qty * (UNIT_MULTIPLIERS[unit] || 1);
                
                return {
                    id: cb.value,
                    grams: totalGrams,
                    display_size: `${qty} ${unit}`
                };
            });
            
            if (selectedRows.length === 0) return;

            const btn = document.getElementById('btnAddSelected');
            const originalText = btn.innerText;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';

            try {
                // Send as JSON data for better handling of mixed serving sizes
                const payload = new URLSearchParams();
                payload.append('meal_type', currentMealType);
                payload.append('batch_data', JSON.stringify(selectedRows));

                const response = await fetch(BASE_URL + 'api/save_log.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: payload.toString()
                });

                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.message || 'Error saving logs');
                    btn.disabled = false;
                    btn.innerText = originalText;
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An unexpected error occurred');
                btn.disabled = false;
                btn.innerText = originalText;
            }
        }

        async function deleteLog(logId) {
            if (!confirm('Are you sure you want to remove this entry?')) return;
            
            try {
                const response = await fetch(BASE_URL + 'api/delete_log.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${logId}`
                });
                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.message || 'Failed to delete log');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred during deletion');
            }
        }

        function toggleMealMicro(id) {
            const el = document.getElementById(id);
            const tog = document.getElementById('mm_toggle_' + id.replace('mm_', ''));
            if (!el) return;
            const show = el.style.display === 'none' || el.style.display === '';
            el.style.display = show ? 'block' : 'none';
            if (tog) tog.style.transform = show ? 'rotate(180deg)' : 'rotate(0deg)';
        }




        (function () {
            const ps = new URLSearchParams(window.location.search);
            const tab = ps.get('tab');
            if (tab === 'nutrient-analysis') {
                document.addEventListener('DOMContentLoaded', function () {
                    const el = document.getElementById('todayAnalysisResult');
                    if (el) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        if (typeof window.analyzeTodayIntake === 'function') {
                            setTimeout(() => window.analyzeTodayIntake({
                                outputId: 'todayAnalysisResult',
                                date: '<?php echo $selected_date; ?>'
                            }), 100);
                        }
                    }
                });
            }
        })();
    </script>
</body>
</html>


