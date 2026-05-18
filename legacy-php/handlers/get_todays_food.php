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
require_once '../database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_role'] !== 'regular') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$database = new Database();
$pdo = $database->getConnection();

try {
    // Get today's food tracking
    $stmt = $pdo->prepare("
        SELECT 
            'Daily Food Intake' as plan_name,
            CONCAT('Your food intake for ', DATE_FORMAT(NOW(), '%M %d, %Y')) as description,
            SUM(calories) as calories,
            SUM(protein) as protein,
            SUM(carbs) as carbs,
            SUM(fat) as fat,
            MAX(created_at) as created_at,
            'system' as staff_name
        FROM food_tracking 
        WHERE user_id = ? AND tracking_date = CURDATE()
    ");
    $stmt->execute([$user_id]);
    $today_food = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $mealPlans = [];
    $totalCalories = 0;
    
    if ($today_food && $today_food['calories'] !== null) {
        $mealPlans[] = $today_food;
        $totalCalories = $today_food['calories'];
    } else {
        // Get recent food tracking
        $recent_stmt = $pdo->prepare("
            SELECT 
                CONCAT('Food intake - ', DATE_FORMAT(tracking_date, '%M %d')) as plan_name,
                CONCAT('Your food intake from ', DATE_FORMAT(tracking_date, '%M %d')) as description,
                SUM(calories) as calories,
                SUM(protein) as protein,
                SUM(carbs) as carbs,
                SUM(fat) as fat,
                MAX(created_at) as created_at,
                'system' as staff_name
            FROM food_tracking 
            WHERE user_id = ? 
            GROUP BY tracking_date 
            ORDER BY tracking_date DESC 
            LIMIT 3
        ");
        $recent_stmt->execute([$user_id]);
        $recent_food = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($recent_food)) {
            $mealPlans = $recent_food;
            $totalCalories = $recent_food[0]['calories'] ?? 0;
        }
    }
    
    echo json_encode([
        'success' => true,
        'mealPlans' => $mealPlans,
        'totalCalories' => $totalCalories
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error', 'mealPlans' => [], 'totalCalories' => 0]);
}
?>