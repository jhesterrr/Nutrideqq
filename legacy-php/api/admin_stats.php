<?php
// api/admin_stats.php
// Suppress PHP errors from corrupting JSON output (errors still go to error_log)
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start(); // Buffer everything as a final safety net
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
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
session_write_close(); // Release session file lock immediately to prevent page load blocking

$staff_id = $_REQUEST['staff_id'] ?? 'all';
$database = new Database();
$pdo = $database->getConnection();

try {
    $response = [
        'success' => true,
        'health_trends' => [],
        'workload' => ['total_clients' => 0, 'max_capacity' => 0],
        'alerts' => [],
        'recent_activity' => [],
        'staff_influence' => [],
        'efficiency' => ['percentage' => 0, 'today' => 0, 'avg' => 0]
    ];

    // Build Staff Filter Clause
    $where_clause = "";
    $params = [];
    if ($staff_id !== 'all') {
        $where_clause = " WHERE staff_id = ? ";
        $params[] = $staff_id;
    }

    // 1. Health Trends (Last 7 Days) - Optimized to run 2 grouped queries instead of 14 nested loop queries
    try {
        $dates = [];
        for ($i = 6; $i >= 0; $i--) {
            $dates[] = date('Y-m-d', strtotime("-$i days"));
        }
        $start_date = $dates[0];
        $end_date = $dates[count($dates) - 1];

        // 1a. Query average calories per client per day in the range
        $cal_query = "
            SELECT 
                ft.tracking_date,
                AVG(ft.cal_sum) as avg_cal
            FROM (
                SELECT ft.user_id, ft.tracking_date, SUM(ft.calories) as cal_sum
                FROM food_tracking ft
                JOIN clients c ON ft.user_id = c.user_id
                WHERE ft.tracking_date BETWEEN ? AND ?
                " . ($staff_id !== 'all' ? "AND c.staff_id = ?" : "") . "
                GROUP BY ft.user_id, ft.tracking_date
            ) ft
            GROUP BY ft.tracking_date";
            
        $stmt_cal = $pdo->prepare($cal_query);
        $cal_params = [$start_date, $end_date];
        if ($staff_id !== 'all') {
            $cal_params = [$start_date, $end_date, $staff_id];
        }
        $stmt_cal->execute($cal_params);
        $cal_results = $stmt_cal->fetchAll(PDO::FETCH_KEY_PAIR);

        // 1b. Query average hydration per client per day in the range
        $water_query = "
            SELECT 
                ht.tracking_date,
                AVG(ht.water_sum) as avg_water
            FROM (
                SELECT ht.user_id, ht.tracking_date, SUM(ht.glasses) as water_sum
                FROM hydration_tracking ht
                JOIN clients c ON ht.user_id = c.user_id
                WHERE ht.tracking_date BETWEEN ? AND ?
                " . ($staff_id !== 'all' ? "AND c.staff_id = ?" : "") . "
                GROUP BY ht.user_id, ht.tracking_date
            ) ht
            GROUP BY ht.tracking_date";
            
        $stmt_water = $pdo->prepare($water_query);
        $water_params = [$start_date, $end_date];
        if ($staff_id !== 'all') {
            $water_params = [$start_date, $end_date, $staff_id];
        }
        $stmt_water->execute($water_params);
        $water_results = $stmt_water->fetchAll(PDO::FETCH_KEY_PAIR);

        // Map to response structure
        $health_data = ['labels' => [], 'calories' => [], 'hydration' => []];
        foreach ($dates as $date) {
            $health_data['labels'][] = date('M d', strtotime($date));
            $health_data['calories'][] = round($cal_results[$date] ?? 0, 0);
            $health_data['hydration'][] = round($water_results[$date] ?? 0, 1);
        }
        $response['health_trends'] = $health_data;
    } catch (Exception $e) {
        $response['health_trends'] = ['labels' => [], 'calories' => [], 'hydration' => []];
    }

    // 2. Workload
    try {
        if ($staff_id === 'all') {
            $stmt = $pdo->query("SELECT COUNT(*) FROM clients WHERE status='active'");
            $response['workload']['total_clients'] = (int) $stmt->fetchColumn();
            $stmt = $pdo->query("SELECT COUNT(*) * 50 FROM users WHERE role='staff' AND status='active'");
            $response['workload']['max_capacity'] = (int) $stmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE staff_id = ? AND status='active'");
            $stmt->execute([$staff_id]);
            $response['workload']['total_clients'] = (int) $stmt->fetchColumn();
            $response['workload']['max_capacity'] = 50;
        }
    } catch (Exception $e) {
        $response['workload'] = ['total_clients' => 0, 'max_capacity' => 0];
    }

    // 3. Alerts (No logs in 48 hours)
    try {
        $alert_query = "
            SELECT c.name, MAX(ft.tracking_date) as last_log
            FROM clients c
            LEFT JOIN food_tracking ft ON c.user_id = ft.user_id
            " . ($staff_id !== 'all' ? "WHERE c.staff_id = ?" : "") . "
            GROUP BY c.id
            HAVING last_log IS NULL OR last_log < DATE_SUB(CURDATE(), INTERVAL 2 DAY)
            LIMIT 5";
        $stmt = $pdo->prepare($alert_query);
        if ($staff_id !== 'all') $stmt->execute([$staff_id]);
        else $stmt->execute();
        $response['alerts'] = $stmt->fetchAll();
    } catch (Exception $e) {
        $response['alerts'] = [];
    }

    // 4. System Efficiency
    try {
        $efficiency_query = "
            SELECT 
                (SELECT COUNT(*) FROM food_tracking WHERE tracking_date = CURDATE()) as today,
                (SELECT COUNT(*) / 7 FROM food_tracking WHERE tracking_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) as weekly_avg
        ";
        $eff_stmt = $pdo->query($efficiency_query);
        $eff_row = $eff_stmt->fetch();
        $today_count = (int)($eff_row['today'] ?? 0);
        $avg_count = (float)($eff_row['weekly_avg'] ?? 0) ?: 1;
        $efficiency_pct = min(100, round(($today_count / $avg_count) * 100));
        $response['efficiency'] = [
            'percentage' => $efficiency_pct,
            'today' => $today_count,
            'avg' => round($avg_count, 1)
        ];
    } catch (Exception $e) {
        $response['efficiency'] = ['percentage' => 0, 'today' => 0, 'avg' => 0];
    }

    // 5. Recent System Activity
    try {
        $activity_sql = "
            SELECT * FROM (
                (SELECT 'user' as type, name as title, 'New User Registered' as description, created_at, 'success' as status FROM users ORDER BY created_at DESC LIMIT 5)
                UNION ALL
                (SELECT 'food' as type, food_name as title, CONCAT('Food logged: ', calories, ' kcal') as description, created_at, 'info' as status FROM food_tracking ORDER BY created_at DESC LIMIT 5)
                UNION ALL
                (SELECT 'message' as type, 'User Update' as title, 'Profile changes detected' as description, updated_at as created_at, 'warning' as status FROM users WHERE updated_at > created_at ORDER BY updated_at DESC LIMIT 5)
            ) combined_activity
            ORDER BY created_at DESC
            LIMIT 10
        ";
        $act_stmt = $pdo->query($activity_sql);
        $response['recent_activity'] = $act_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $response['recent_activity'] = [];
    }

    // 6. Staff Influence Score - Optimized to run 1 query instead of O(Staff) loop queries
    try {
        $staff_query = "
            SELECT 
                u.name,
                (SELECT COUNT(*) FROM wellness_messages wm WHERE wm.sender_id = u.id) as msgs,
                (SELECT COUNT(*) FROM clients c WHERE c.staff_id = u.id) as clients
            FROM users u
            WHERE u.role = 'staff' AND u.status = 'active'";
        $staff_stmt = $pdo->query($staff_query);
        $all_staff = $staff_stmt->fetchAll();
        foreach ($all_staff as $staff) {
            $msgs = (int)($staff['msgs'] ?? 0);
            $clients = (int)($staff['clients'] ?? 0) ?: 1;
            $score = round(($msgs / $clients) * 10, 1);
            $response['staff_influence'][] = [
                'name' => $staff['name'],
                'score' => $score,
                'label' => $score > 5 ? 'High Influence' : ($score > 2 ? 'Active' : 'Developing')
            ];
        }
    } catch (Exception $e) {
        $response['staff_influence'] = [];
    }

    ob_end_clean(); // Discard any buffered warnings before sending JSON
    echo json_encode($response);

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


