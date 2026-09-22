<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

require_once '../database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_role'] !== 'regular') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

verify_csrf();

$data = json_decode(file_get_contents('php://input'), true);
$message_id = $data['message_id'] ?? 0;

$database = new Database();
$pdo = $database->getConnection();

try {
    $stmt = $pdo->prepare("UPDATE messages SET read_status = 1 WHERE id = ?");
    $stmt->execute([$message_id]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>