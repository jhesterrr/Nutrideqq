<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

header('Content-Type: application/json');

require_once '../database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

verify_csrf();

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    $stmt = $pdo->prepare("UPDATE clients SET 
        goal_calories = ?, goal_protein = ?, goal_carbs = ?, goal_fats = ? 
        WHERE user_id = ?");
    
    $stmt->execute([
        (int)$data['calories'],
        (int)$data['protein'],
        (int)$data['carbs'],
        (int)$data['fats'],
        $user_id
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
