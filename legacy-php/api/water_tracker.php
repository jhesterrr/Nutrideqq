<?php
ini_set('display_errors', 0);
// api/water_tracker.php - Real-time daily hydration tracking
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
$log_date = date('Y-m-d');
$action = $_REQUEST['action'] ?? 'get';

try {
    $database = new Database();
    $pdo = $database->getConnection();

    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT INTO hydration_tracking (user_id, glasses, tracking_date) VALUES (?, 1, ?) 
                               ON DUPLICATE KEY UPDATE glasses = glasses + 1");
        $stmt->execute([$user_id, $log_date]);
    } elseif ($action === 'remove') {
        $stmt = $pdo->prepare("UPDATE hydration_tracking SET glasses = GREATEST(0, glasses - 1) WHERE user_id = ? AND tracking_date = ?");
        $stmt->execute([$user_id, $log_date]);
    }

    // Always return updated count
    $stmt = $pdo->prepare("SELECT glasses FROM hydration_tracking WHERE user_id = ? AND tracking_date = ?");
    $stmt->execute([$user_id, $log_date]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $glasses = (int)($row ? $row['glasses'] : 0);

    echo json_encode(['success' => true, 'glasses' => $glasses, 'target' => 8]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
