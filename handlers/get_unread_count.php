<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

require_once '../database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_role'] !== 'regular') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$database = new Database();
$pdo = $database->getConnection();

try {
    // Get client ID
    $client_stmt = $pdo->prepare("SELECT id FROM clients WHERE user_id = ? LIMIT 1");
    $client_stmt->execute([$user_id]);
    $client = $client_stmt->fetch();
    
    if ($client) {
        // Get unread messages count
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM messages WHERE client_id = ? AND sender_type = 'staff' AND read_status = 0");
        $stmt->execute([$client['id']]);
        $result = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'count' => $result['count'] ?? 0
        ]);
    } else {
        echo json_encode(['success' => true, 'count' => 0]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error', 'count' => 0]);
}
?>