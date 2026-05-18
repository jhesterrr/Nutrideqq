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

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$food_name = $_POST['food_name'] ?? '';
$meal_type = $_POST['meal_type'] ?? '';
$log_date = $_POST['log_date'] ?? date('Y-m-d');

// Nutrients can be empty (null) for dietician to fill later
$serving_qty = !empty($_POST['serving_size']) ? (float)$_POST['serving_size'] : 0;
$serving_unit = $_POST['serving_unit'] ?? 'g';
$calories = !empty($_POST['calories']) ? (float)$_POST['calories'] : 0;
$protein = !empty($_POST['protein']) ? (float)$_POST['protein'] : 0;
$carbs = !empty($_POST['carbs']) ? (float)$_POST['carbs'] : 0;
$fat = !empty($_POST['fat']) ? (float)$_POST['fat'] : 0;

$multipliers = ['g'=>1, 'ml'=>1, 'cup'=>240, 'slice'=>35, 'piece'=>50, 'tbsp'=>15];
$total_grams = $serving_qty * ($multipliers[$serving_unit] ?? 1);
$display_size = $serving_qty . ' ' . $serving_unit;

if (empty($food_name) || empty($meal_type)) {
    echo json_encode(['success' => false, 'message' => 'Meal name and type are required']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Auto-migration: Ensure columns exist
    $columns = $conn->query("SHOW COLUMNS FROM food_logs")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('serving_unit', $columns)) {
        $conn->exec("ALTER TABLE food_logs ADD COLUMN serving_unit VARCHAR(50) DEFAULT 'g' AFTER serving_size");
    }
    if (!in_array('display_size', $columns)) {
        $conn->exec("ALTER TABLE food_logs ADD COLUMN display_size VARCHAR(100) AFTER serving_unit");
    }

    $sql = "INSERT INTO food_logs (user_id, food_id, food_name, calories, protein, carbs, fat, serving_size, serving_unit, display_size, meal_type, log_date) 
            VALUES (:user_id, NULL, :food_name, :calories, :protein, :carbs, :fat, :serving_size, :unit, :display, :meal_type, :log_date)";
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        ':user_id' => $user_id,
        ':food_name' => $food_name,
        ':calories' => $calories,
        ':protein' => $protein,
        ':carbs' => $carbs,
        ':fat' => $fat,
        ':serving_size' => $total_grams,
        ':unit' => $serving_unit,
        ':display' => $display_size,
        ':meal_type' => $meal_type,
        ':log_date' => $log_date
    ]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Custom meal saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save to database']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
