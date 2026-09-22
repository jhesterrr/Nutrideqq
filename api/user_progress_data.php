<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

header('Content-Type: application/json');

require_once '../database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$database = new Database();
$pdo = $database->getConnection();

try {
    // 1. Calculate Today's Macros
    $macro_query = $pdo->prepare("
        SELECT 
            SUM(protein) as total_p,
            SUM(carbs) as total_c,
            SUM(fats) as total_f,
            SUM(calories) as total_cal
        FROM food_tracking 
        WHERE user_id = ? AND tracking_date = CURDATE()
    ");
    $macro_query->execute([$user_id]);
    $today = $macro_query->fetch(PDO::FETCH_ASSOC);

    // 2. Get User Goals (from clients table or defaults)
    $goal_query = $pdo->prepare("SELECT target_calories, target_p, target_c, target_f FROM clients WHERE user_id = ? LIMIT 1");
    $goal_query->execute([$user_id]);
    $goals = $goal_query->fetch(PDO::FETCH_ASSOC);

    // Use defaults if goals not set
    $target_cal = $goals['target_calories'] ?? 2000;
    $target_p = $goals['target_p'] ?? 150;
    $target_c = $goals['target_c'] ?? 250;
    $target_f = $goals['target_f'] ?? 70;

    // 3. Get 7-Day Trend
    $trend_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $t_query = $pdo->prepare("SELECT SUM(calories) as cal FROM food_tracking WHERE user_id = ? AND tracking_date = ?");
        $t_query->execute([$user_id, $date]);
        $val = $t_query->fetchColumn();
        $trend_data['labels'][] = date('D', strtotime($date));
        $trend_data['calories'][] = (int)($val ?? 0);
    }

    echo json_encode([
        'success' => true,
        'macros' => [
            'protein' => [
                'current' => (float)($today['total_p'] ?? 0),
                'target' => (float)$target_p,
                'pct' => min(100, round((($today['total_p'] ?? 0) / $target_p) * 100))
            ],
            'carbs' => [
                'current' => (float)($today['total_c'] ?? 0),
                'target' => (float)$target_c,
                'pct' => min(100, round((($today['total_c'] ?? 0) / $target_c) * 100))
            ],
            'fats' => [
                'current' => (float)($today['total_f'] ?? 0),
                'target' => (float)$target_f,
                'pct' => min(100, round((($today['total_f'] ?? 0) / $target_f) * 100))
            ],
            'calories' => [
                'current' => (int)($today['total_cal'] ?? 0),
                'target' => (int)$target_cal,
                'pct' => min(100, round((($today['total_cal'] ?? 0) / $target_cal) * 100))
            ]
        ],
        'trends' => $trend_data
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
