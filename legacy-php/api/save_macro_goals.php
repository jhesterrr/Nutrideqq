<?php
ini_set('display_errors', 0);
$session_path = 'c:\xampp\htdocs\nutrideq\api\sessions';
if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
session_save_path($session_path);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

require_once '../database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Ensure the goal columns exist in clients table
    try {
        $pdo->exec("ALTER TABLE clients 
            ADD COLUMN IF NOT EXISTS goal_calories INT DEFAULT 2000,
            ADD COLUMN IF NOT EXISTS goal_protein INT DEFAULT 150,
            ADD COLUMN IF NOT EXISTS goal_carbs INT DEFAULT 200,
            ADD COLUMN IF NOT EXISTS goal_fats INT DEFAULT 65");
    } catch (Exception $e) {
        // Ignore if exists or error
    }

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
