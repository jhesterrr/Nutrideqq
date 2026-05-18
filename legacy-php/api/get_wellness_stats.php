<?php
ini_set('display_errors', 0);
// api/get_wellness_stats.php
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
$user_role = $_SESSION['user_role'] ?? 'regular';

$database = new Database();
$pdo = $database->getConnection();

$unread_count = 0;

try {
    if ($user_role === 'staff') {
        // Count unread messages from clients in wellness_messages
        // Where sender_type = 'client' and read_at IS NULL
        // And client is assigned to this staff
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM wellness_messages wm
            JOIN conversations c ON wm.conversation_id = c.id
            WHERE c.dietitian_id = ? 
            AND wm.sender_type = 'client' 
            AND wm.read_at IS NULL
        ");
        $stmt->execute([$user_id]);
        $unread_count = $stmt->fetchColumn();
    } else {
        // Client: Count unread messages from staff/ai
        // Where sender_type != 'client'
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM wellness_messages wm
            JOIN conversations c ON wm.conversation_id = c.id
            WHERE c.client_id = (SELECT id FROM clients WHERE user_id = ? LIMIT 1)
            AND wm.sender_type != 'client'
            AND wm.read_at IS NULL
        ");
        // Fallback for email matching if needed, but user_id is standard
        $stmt->execute([$user_id]);
        $unread_count = $stmt->fetchColumn();
    }
} catch (Exception $e) {
    // 0 on error
}

echo json_encode(['success' => true, 'unread_count' => (int)$unread_count]);
?>
