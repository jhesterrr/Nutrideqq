<?php
header('Content-Type: application/json');
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

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'staff' && $_SESSION['user_role'] !== 'admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$log_id = $data['log_id'] ?? null;
$calories = isset($data['calories']) ? (float)$data['calories'] : null;
$protein = isset($data['protein']) ? (float)$data['protein'] : null;
$carbs = isset($data['carbs']) ? (float)$data['carbs'] : null;
$fat = isset($data['fat']) ? (float)$data['fat'] : null;

if (!$log_id) {
    echo json_encode(['success' => false, 'message' => 'Missing log ID']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $sql = "UPDATE food_logs SET 
            calories = :calories, 
            protein = :protein, 
            carbs = :carbs, 
            fat = :fat, 
            updated_at = NOW() 
            WHERE id = :id";
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        ':calories' => $calories,
        ':protein' => $protein,
        ':carbs' => $carbs,
        ':fat' => $fat,
        ':id' => $log_id
    ]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Macros updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update database']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
