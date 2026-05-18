<?php
$session_path = 'c:\xampp\htdocs\nutrideq\api\sessions';
if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
session_save_path($session_path);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../database.php';

header('Content-Type: application/json');

// Check if user is logged in and is staff
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_role'] !== 'staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Get the POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        exit();
    }

    $client_id = $input['client_id'] ?? null;
    $staff_id = $input['staff_id'] ?? null;
    $items = $input['items'] ?? [];
    $total_energy = $input['total_energy'] ?? 0;
    $total_protein = $input['total_protein'] ?? 0;
    $total_fat = $input['total_fat'] ?? 0;
    $total_cho = $input['total_cho'] ?? 0;

    if (!$client_id) {
        echo json_encode(['success' => false, 'message' => 'Client ID is required']);
        exit();
    }

    // Verify the client belongs to the staff member
    $check_client = $pdo->prepare("SELECT * FROM clients WHERE id = ? AND staff_id = ?");
    $check_client->execute([$client_id, $staff_id]);
    $client = $check_client->fetch();

    if (!$client) {
        echo json_encode(['success' => false, 'message' => 'Client not found or access denied']);
        exit();
    }

    // Get or create user_id for the client
    $user_query = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $user_query->execute([$client['email']]);
    $user_data = $user_query->fetch();
    
    $client_user_id = null;
    
    if ($user_data) {
        $client_user_id = $user_data['id'];
    } else {
        // Create a user account for the client
        $create_user_stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, role, status) 
            VALUES (?, ?, ?, 'regular', 'active')
        ");
        
        // Generate a random password (clients will need to reset it)
        $random_password = bin2hex(random_bytes(8));
        $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);
        
        $create_user_stmt->execute([
            $client['name'],
            $client['email'],
            $hashed_password
        ]);
        
        $client_user_id = $pdo->lastInsertId();
        
        // Update the client record with the new user_id
        $update_client_stmt = $pdo->prepare("UPDATE clients SET user_id = ? WHERE id = ?");
        $update_client_stmt->execute([$client_user_id, $client_id]);
    }

    // Start transaction
    $pdo->beginTransaction();

    // 1. Save the meal plan to meal_plans table
    $meal_plan_stmt = $pdo->prepare("
        INSERT INTO meal_plans (client_id, staff_id, plan_name, total_calories, total_protein, total_carbs, total_fat, plan_data, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");

    $plan_name = "Meal Plan for " . $client['name'] . " - " . date('Y-m-d H:i:s');
    $plan_data = json_encode([
        'items' => $items,
        'created_at' => date('Y-m-d H:i:s'),
        'totals' => [
            'energy' => $total_energy,
            'protein' => $total_protein,
            'fat' => $total_fat,
            'carbs' => $total_cho
        ]
    ]);

    $meal_plan_stmt->execute([
        $client_id,
        $staff_id,
        $plan_name,
        $total_energy,
        $total_protein,
        $total_cho, // carbs
        $total_fat,
        $plan_data
    ]);

    $meal_plan_id = $pdo->lastInsertId();

    // 2. Save individual items to meal_plan_items table
    $meal_plan_item_stmt = $pdo->prepare("
        INSERT INTO meal_plan_items (meal_plan_id, food_name, food_group, exchanges, weight, calories, protein, carbs, fat, meal_type) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    // 3. ALSO save to food_tracking table so it appears in the Food Tracker
    $food_tracking_stmt = $pdo->prepare("
        INSERT INTO food_tracking (user_id, food_name, calories, protein, carbs, fat, meal_type, tracking_date) 
        VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())
    ");

    foreach ($items as $item) {
        $meal_type = isset($item['meal_type']) && !empty($item['meal_type']) ? strtolower($item['meal_type']) : 'custom';
        
        // Save to meal_plan_items
        $meal_plan_item_stmt->execute([
            $meal_plan_id,
            $item['name'],
            $item['group'],
            $item['data']['exchange'] ?? '1',
            $item['data']['weight'] ?? '',
            $item['data']['energy'] ?? 0,
            $item['data']['protein'] ?? 0,
            $item['data']['cho'] ?? 0,
            $item['data']['fat'] ?? 0,
            $meal_type
        ]);

        // ALSO save to food_tracking
        $food_tracking_stmt->execute([
            $client_user_id,
            $item['name'],
            $item['data']['energy'] ?? 0,
            $item['data']['protein'] ?? 0,
            $item['data']['cho'] ?? 0,
            $item['data']['fat'] ?? 0,
            $meal_type
        ]);
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Meal plan saved successfully and added to food tracker!',
        'meal_plan_id' => $meal_plan_id
    ]);

} catch (PDOException $e) {
    // Rollback transaction on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Meal plan save error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Meal plan save error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>