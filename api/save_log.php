<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

require_once 'fct_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Session missing. Please refresh and log in again.']);
    exit();
}

verify_csrf();

$user_role = strtolower($_SESSION['role'] ?? $_SESSION['user_role'] ?? '');
if ($user_role !== 'user' && $user_role !== 'regular') {
    echo json_encode(['success' => false, 'message' => 'Forbidden: Only users can log food to the diary. Detected role: ' . $user_role]);
    exit();
}

$user_id = $_SESSION['user_id'];
$meal_type = $_POST['meal_type'] ?? '';
$batch_data = $_POST['batch_data'] ?? null;

if (!$meal_type || (!$batch_data && empty($_POST['food_item_ids']))) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    AuditLogger::init($conn);
    $fct = new FCTHelper();
    
    $conn->beginTransaction();

    $sql = "INSERT INTO food_logs (user_id, food_id, food_name, calories, protein, carbs, fat, serving_size, serving_unit, display_size, meal_type, log_date) 
            VALUES (:user_id, :food_id, :food_name, :calories, :protein, :carbs, :fat, :serving_size, :unit, :display, :meal_type, :log_date)";
    $stmt = $conn->prepare($sql);

    // Parse items to process
    $items_to_process = [];
    if ($batch_data) {
        $items_to_process = json_decode($batch_data, true);
    } else {
        // Fallback for legacy calls
        $ids = $_POST['food_item_ids'] ?? [$_POST['food_item_id']];
        foreach ($ids as $id) {
            $items_to_process[] = ['id' => $id, 'grams' => $_POST['serving_size'] ?? 100, 'display_size' => ($_POST['serving_size'] ?? 100) . ' g'];
        }
    }

    foreach ($items_to_process as $item_data) {
        $id = $item_data['id'];
        $grams = $item_data['grams'];
        $display = $item_data['display_size'] ?? ($grams . ' g');

        $details = $fct->getDetails($id);
        if (!$details) continue;

        $food = $details['item'];
        $nutrients = $details['nutrients'];

        $getNutrientVal = function($nutrients, $name) {
            foreach ($nutrients as $n) {
                if (stripos($n['nutrient_name'], $name) !== false) {
                    return (float)$n['value'];
                }
            }
            return 0;
        };

        $base_calories = $getNutrientVal($nutrients, 'Energy');
        $base_protein = $getNutrientVal($nutrients, 'Protein');
        $base_carbs = $getNutrientVal($nutrients, 'Carbohydrate');
        $base_fat = $getNutrientVal($nutrients, 'Fat');

        $ratio = (float)$grams / 100;

        $stmt->execute([
            ':user_id' => $user_id,
            ':food_id' => $food['food_id'],
            ':food_name' => class_exists('Encryption') ? Encryption::encryptIfExists($food['food_name']) : $food['food_name'],
            ':calories' => $base_calories * $ratio,
            ':protein' => $base_protein * $ratio,
            ':carbs' => $base_carbs * $ratio,
            ':fat' => $base_fat * $ratio,
            ':serving_size' => $grams,
            ':unit' => 'g',
            ':display' => $display,
            ':meal_type' => $meal_type,
            ':log_date' => date('Y-m-d')
        ]);
    }

    $conn->commit();
    AuditLogger::log('food_log_create', 'food_logs', $user_id, $user_id);
    echo json_encode(['success' => true, 'message' => 'Food(s) logged successfully']);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
