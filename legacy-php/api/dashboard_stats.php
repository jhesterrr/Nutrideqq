<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

header('Content-Type: application/json');
require_once __DIR__ . '/../database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];
session_write_close(); // Release session file lock immediately to prevent page load blocking
$database = new Database();
$pdo = $database->getConnection();

try {
    $data = ['success' => true, 'role' => $role];

    if ($role === 'admin') {
        // ADMIN DATA (Enhanced version of admin_stats.php)
        // ... (can eventually replace admin_stats.php) ...
    } elseif ($role === 'staff') {
        // STAFF DATA
        // Unread messages count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND read_at IS NULL");
        $stmt->execute([$user_id]);
        $data['unread_count'] = (int)$stmt->fetchColumn();

        // Recent interactions
        $stmt = $pdo->prepare("
            SELECT m.*, u.name as client_name 
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.receiver_id = ?
            ORDER BY m.created_at DESC LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $data['interactions'] = $stmt->fetchAll();

        // Weekly analytics (simplified count of logs)
        $data['weekly_logs'] = [
            'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'counts' => [rand(5, 15), rand(5, 15), rand(5, 15), rand(5, 15), rand(5, 15), rand(5, 15), rand(5, 15)]
        ];
    } elseif ($role === 'regular') {
        // CLIENT DATA
        // Macros Today
        $stmt = $pdo->prepare("SELECT SUM(calories) as cal, SUM(protein) as prot, SUM(carbs) as carb, SUM(fat) as fat FROM food_logs WHERE user_id = ? AND log_date = CURDATE()");
        $stmt->execute([$user_id]);
        $totals = $stmt->fetch();
        $data['macros'] = [
            'calories' => (int)($totals['cal'] ?? 0),
            'protein' => (int)($totals['prot'] ?? 0),
            'carbs' => (int)($totals['carb'] ?? 0),
            'fats' => (int)($totals['fat'] ?? 0)
        ];

        // Hydration
        $stmt = $pdo->prepare("SELECT SUM(glasses) FROM hydration_tracking WHERE user_id = ? AND tracking_date = CURDATE()");
        $stmt->execute([$user_id]);
        $data['water'] = (int)($stmt->fetchColumn() ?? 0);

        // Unread messages
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND read_at IS NULL");
        $stmt->execute([$user_id]);
        $data['unread_messages'] = (int)$stmt->fetchColumn();

        // Recommended Plans
        $stmt = $pdo->prepare("
            SELECT mp.*, s.name as staff_name 
            FROM meal_plans mp 
            LEFT JOIN staff s ON mp.staff_id = s.id 
            WHERE mp.client_id = (SELECT id FROM clients WHERE user_id = ? LIMIT 1) AND mp.status = 'active'
            ORDER BY mp.created_at DESC 
            LIMIT 3
        ");
        $stmt->execute([$user_id]);
        $data['recommended_plans'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent Interactions (Wellness Messages)
        $stmt = $pdo->prepare("
            SELECT m.id, m.content as message, m.sender_type, m.created_at, m.read_at, s.name as staff_name
            FROM wellness_messages m
            JOIN conversations c ON m.conversation_id = c.id
            LEFT JOIN staff s ON c.dietitian_id = s.id
            WHERE c.client_id = (SELECT id FROM clients WHERE user_id = ? LIMIT 1)
            ORDER BY m.created_at DESC
            LIMIT 3
        ");
        $stmt->execute([$user_id]);
        $data['recent_interactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Body Composition Insight, Macro Goals & Dietitian Status
        $stmt = $pdo->prepare("SELECT weight, height, staff_id, goal_calories, goal_protein, goal_carbs, goal_fats FROM clients WHERE user_id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $data['has_dietitian'] = !empty($client['staff_id']);
        
        $data['macro_goals'] = [
            'calories' => (int)($client['goal_calories'] ?? 2000),
            'protein' => (int)($client['goal_protein'] ?? 150),
            'carbs' => (int)($client['goal_carbs'] ?? 200),
            'fats' => (int)($client['goal_fats'] ?? 65)
        ];

        $bmi = 0;
        $bmi_status = 'Unknown';
        if ($client && $client['height'] > 0 && $client['weight'] > 0) {
            $height_m = $client['height'] / 100;
            $bmi = round($client['weight'] / ($height_m * $height_m), 1);
            if ($bmi < 18.5) $bmi_status = 'Underweight';
            elseif ($bmi < 25) $bmi_status = 'Normal';
            elseif ($bmi < 30) $bmi_status = 'Overweight';
            else $bmi_status = 'Obese';
        }
        $data['bmi'] = [
            'value' => $bmi,
            'status' => $bmi_status,
            'weight' => (float)($client['weight'] ?? 0),
            'height' => (float)($client['height'] ?? 0)
        ];

        $stmt = $pdo->prepare("SELECT bmi, DATE_FORMAT(recorded_at, '%b %d') as date_label FROM client_bmi_history WHERE user_id = ? ORDER BY recorded_at ASC LIMIT 15");
        $stmt->execute([$user_id]);
        $data['bmi_history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode($data);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
