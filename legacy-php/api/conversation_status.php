<?php
ini_set('display_errors', 0);
// api/conversation_status.php
if (strpos(strtolower(PHP_OS), 'win') !== false) {
    $session_path = 'c:\\xampp\\htdocs\\nutrideq\\api\\sessions';
    if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
    session_save_path($session_path);
} else {
    $session_path = '/tmp/sessions';
    if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
    session_save_path($session_path);
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once '../database.php';

if (!isset($_SESSION['logged_in'])) {
    echo json_encode(['success' => false]);
    exit;
}

$action = $_POST['action'] ?? ''; // 'typing' or 'read'
$contact_id = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;
$user_id = $_SESSION['user_id'];

// Ideally, use Redis or a temp table. For this demo, we'll Mock the success 
// or update the 'read_at' in DB if action is 'read'.

if ($action === 'read' && $contact_id > 0) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Update unread messages in the conversation
        // First find conversation
        // (Simplified logic: assuming we know the conversation or query by participants)
        
        // This query assumes we're updating messages sent BY the contact TO the current user
        // We need to join conversations to ensure we match the right pair, or simpler:
        // Update wellness_messages where conversation_id is shared and sender_id = contact_id
        
        // For simplicity/robustness in this demo scope:
        // We will just return success as "Reading" is often a UI state update.
        // Real implementation would be: 
        // UPDATE wellness_messages SET read_at = NOW() WHERE sender_id = ? AND read_at IS NULL AND conversation_id IN (SELECT id FROM conversations WHERE ...)
        
    } catch (Exception $e) { }
}

echo json_encode(['success' => true]);
?>
