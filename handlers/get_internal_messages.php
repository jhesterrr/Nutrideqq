<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

header('Content-Type: application/json');

require_once '../database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'regular';
$thread_id = isset($_GET['thread_id']) ? intval($_GET['thread_id']) : 0;

if ($thread_id <= 0) {
    echo json_encode(['success' => false, 'messages' => [], 'error' => 'Invalid thread ID']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Verify access
    if ($user_role === 'admin') {
        $stmt = $pdo->prepare("SELECT t.* FROM internal_threads t WHERE t.id = ?");
        $stmt->execute([$thread_id]);
    } else {
        // Use JSON_SEARCH for string IDs or numeric cast for robust matching on Cloud DBs
        $stmt = $pdo->prepare("
            SELECT t.* FROM internal_threads t 
            WHERE t.id = ? 
            AND (
                JSON_SEARCH(t.participants, 'one', ?) IS NOT NULL 
                OR t.created_by = ?
            )
        ");
        $stmt->execute([$thread_id, (string)$user_id, $user_id]);
    }
    $thread = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$thread) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }

    // Get messages
    $stmt = $pdo->prepare("
        SELECT m.id, m.thread_id, m.sender_id, m.sender_role as msg_role, m.message, m.attachment_path, m.message_type, m.read_by, m.created_at,
               u.name as sender_name,
               u.role as sender_role
        FROM internal_thread_messages m
        LEFT JOIN users u ON m.sender_id = u.id
        WHERE m.thread_id = ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$thread_id]);
    $raw_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark messages as read (if not sent by me)
    try {
        $update = $pdo->prepare("
            UPDATE internal_thread_messages 
            SET read_by = JSON_ARRAY_APPEND(COALESCE(read_by, '[]'), '$', ?)
            WHERE thread_id = ? AND sender_id != ? 
            AND (read_by IS NULL OR JSON_CONTAINS(COALESCE(read_by, '[]'), ?, '$') = 0)
        ");
        $update->execute([$user_id, $thread_id, $user_id, $user_id]);
    } catch (Exception $e) {
        // Log error but don't fail request
        error_log("Error updating read status: " . $e->getMessage());
    }

    $messages = [];
    foreach ($raw_messages as $msg) {
        $is_me = ($msg['sender_id'] == $user_id);
        
        $messages[] = [
            'id' => $msg['id'],
            'message' => $msg['message'],
            'sender_id' => $msg['sender_id'],
            'sender_role' => $msg['sender_role'],
            'sender_name' => $msg['sender_name'],
            'attachment_path' => $msg['attachment_path'],
            'message_type' => $msg['message_type'],
            'type' => $is_me ? 'sent' : 'received',
            'created_at' => $msg['created_at'],
            'pretty_time' => date('g:i A', strtotime($msg['created_at'])),
            'read_status' => 1 // Simplified for now
        ];
    }

    echo json_encode(['success' => true, 'messages' => $messages, 'thread_status' => $thread['status'], 'thread_title' => $thread['title']]);

} catch (Throwable $e) {
    // Stop returning 500 so we can read the message in the console
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}
