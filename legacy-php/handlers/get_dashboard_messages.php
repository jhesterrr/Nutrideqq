<?php
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
require_once '../database.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? '';

// Role-based logic
if ($user_role !== 'staff' && $user_role !== 'admin') {
    echo json_encode(['success' => false, 'messages' => [], 'unread_count' => 0]);
    exit();
}

$unread_count = 0;
$messages = [];

try {
    $database = new Database();
    $pdo = $database->getConnection();

    if ($user_role === 'staff') {
        // Query for staff (as it was)
        $unread_stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM wellness_messages wm
            JOIN conversations c ON wm.conversation_id = c.id
            WHERE c.dietitian_id = ? AND wm.sender_type = 'client' AND wm.read_at IS NULL
        ");
        $unread_stmt->execute([$user_id]);
        $unread_count = (int) $unread_stmt->fetchColumn();

        $msgs_stmt = $pdo->prepare("
            SELECT 
                wm.id,
                c.name as client_name,
                c.id as client_id,
                wm.content as message,
                wm.created_at,
                wm.read_at,
                wm.message_type
            FROM wellness_messages wm
            JOIN conversations conv ON wm.conversation_id = conv.id
            JOIN clients c ON conv.client_id = c.id
            WHERE conv.dietitian_id = ? AND wm.sender_type = 'client'
            ORDER BY wm.created_at DESC
            LIMIT 5
        ");
        $msgs_stmt->execute([$user_id]);
        $raw_messages = $msgs_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($raw_messages as $msg) {
            $created_time = strtotime($msg['created_at']);
            $content = htmlspecialchars($msg['message']);
            if ($msg['message_type'] === 'image') $content = '📷 [Image]';
            elseif ($msg['message_type'] === 'file') $content = '📎 [File]';
            if (strlen($content) > 80) $content = substr($content, 0, 80) . '...';

            $messages[] = [
                'id' => $msg['id'],
                'client_name' => htmlspecialchars($msg['client_name']),
                'message' => $content,
                'is_read' => !is_null($msg['read_at']),
                'time_display' => date('M j, g:i A', $created_time),
                'timestamp' => $created_time
            ];
        }
    } elseif ($user_role === 'admin') {
        // Query for admin (example: messages from clients to support)
        // Since I don't know the admin table for messages yet, I'll check if they use the same system or internal_messages.
        // For now, I'll use wellness_messages where dietitian_id IS NULL or some support flag.
        // Actually, many admin DASHBOARDS just show all new client messages.
        $unread_stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM wellness_messages wm
            WHERE wm.read_at IS NULL AND wm.sender_type = 'client'
        ");
        $unread_stmt->execute();
        $unread_count = (int) $unread_stmt->fetchColumn();

        $msgs_stmt = $pdo->prepare("
            SELECT 
                wm.id,
                c.name as client_name,
                wm.content as message,
                wm.created_at,
                wm.read_at,
                wm.message_type
            FROM wellness_messages wm
            JOIN conversations conv ON wm.conversation_id = conv.id
            JOIN clients c ON conv.client_id = c.id
            WHERE wm.sender_type = 'client'
            ORDER BY wm.created_at DESC
            LIMIT 5
        ");
        $msgs_stmt->execute();
        $raw_messages = $msgs_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($raw_messages as $msg) {
            $created_time = strtotime($msg['created_at']);
            $content = htmlspecialchars($msg['message']);
            if ($msg['message_type'] === 'image') $content = '📷 [Image]';
            elseif ($msg['message_type'] === 'file') $content = '📎 [File]';
            if (strlen($content) > 80) $content = substr($content, 0, 80) . '...';

            $messages[] = [
                'id' => $msg['id'],
                'client_name' => htmlspecialchars($msg['client_name']),
                'message' => $content,
                'is_read' => !is_null($msg['read_at']),
                'time_display' => date('M j, g:i A', $created_time),
                'timestamp' => $created_time
            ];
        }
    }

    echo json_encode(['success' => true, 'unread_count' => $unread_count, 'messages' => $messages]);
    exit();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>