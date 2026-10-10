<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

require_once '../database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $tracking_id = $_POST['tracking_id'] ?? null;
    $meal_type = $_POST['meal_type'] ?? null; // Breakfast, Lunch, Dinner, Snack

    $allowed_meal_types = ['Breakfast', 'AM Snack', 'Lunch', 'PM Snack', 'Dinner', 'Snack'];

    if (!$tracking_id || !in_array($meal_type, $allowed_meal_types, true)) {
        echo json_encode(['success' => false, 'message' => 'Missing required data']);
        exit();
    }

    try {
        $database = new Database();
        $pdo = $database->getConnection();

        // Check if the tracking entry exists and belongs to the user
        $stmt = $pdo->prepare("SELECT * FROM food_tracking WHERE id = ? AND user_id = ?");
        $stmt->execute([$tracking_id, $user_id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entry) {
            echo json_encode(['success' => false, 'message' => 'Food entry not found']);
            exit();
        }

        // Technically, a regular user might only have a client_user_id link if we're deeply strict, 
        // but since `user_health_tracker.php` ensures the user is seeing their records, 
        // we'll proceed with moving it to the logs.
        $target_user_id = $user_id;

        // Start Transaction
        $pdo->beginTransaction();

        // 1. Insert into food_logs
        $insertStmt = $pdo->prepare("
            INSERT INTO food_logs (user_id, food_name, calories, protein, carbs, fat, meal_type, log_date, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), NOW())
        ");
        
        $insertStmt->execute([
            $target_user_id,
            $entry['food_name'],
            $entry['calories'],
            $entry['protein'],
            $entry['carbs'],
            $entry['fat'],
            $meal_type
        ]);

        // 2. Delete from food_tracking
        $deleteStmt = $pdo->prepare("DELETE FROM food_tracking WHERE id = ?");
        $deleteStmt->execute([$tracking_id]);

        $pdo->commit();

        echo json_encode([
            'success' => true, 
            'message' => 'Meal successfully consumed and added to diary!',
            'transferred_item' => [
                'name' => $entry['food_name'],
                'meal_type' => $meal_type,
                'calories' => $entry['calories']
            ]
        ]);

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Consume meal error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error preventing transfer.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
