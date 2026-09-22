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
        // Get recent messages
        $stmt = $pdo->prepare("
            SELECT m.*, u.name as staff_name 
            FROM messages m 
            LEFT JOIN users u ON m.staff_id = u.id 
            WHERE m.client_id = ? 
            ORDER BY m.created_at DESC 
            LIMIT 3
        ");
        $stmt->execute([$client['id']]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'messages' => $messages
        ]);
    } else {
        echo json_encode(['success' => true, 'messages' => []]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error', 'messages' => []]);
}
?>