<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

require_once '../database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'total_unread' => 0]);
    exit();
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'total_unread' => 0]);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
header('Content-Type: application/json');

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (isset($_POST['thread_id'])) {
        $thread_id = (int)($_POST['thread_id'] ?? 0);
        $last_id = (int)($_POST['last_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT m.*, u.name as sender_name, u.role as sender_role FROM internal_thread_messages m LEFT JOIN users u ON m.sender_id = u.id WHERE m.thread_id = ? AND m.id > ? ORDER BY m.created_at ASC");
        $stmt->execute([$thread_id, $last_id]);
        $new_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($new_messages as $msg) {
    if ((int)$msg['sender_id'] != $user_id) {
        try {
            $read_by = json_decode($msg['read_by'] ?? '[]', true);
            if (!is_array($read_by)) {
                $read_by = [];
            }
            
            // Ensure all values are integers
            $read_by = array_map('intval', $read_by);
            
            if (!in_array($user_id, $read_by, true)) {
                $read_by[] = $user_id;
                // Remove duplicates and re-index
                $read_by = array_unique($read_by);
                $read_by = array_values($read_by);
                
                $update = $pdo->prepare("
                    UPDATE internal_thread_messages 
                    SET read_by = ?
                    WHERE id = ?
                ");
                $update->execute([json_encode($read_by), $msg['id']]);
            }
        } catch (Exception $e) {
            error_log("Error marking message as read: " . $e->getMessage());
        }
    }
}
        echo json_encode(['success' => true, 'new_messages' => $new_messages, 'count' => count($new_messages)]);
    } else {
        $query = "SELECT COUNT(*) as total_unread FROM internal_thread_messages m INNER JOIN internal_threads t ON m.thread_id = t.id WHERE JSON_CONTAINS(t.participants, ?) AND m.sender_id != ? AND (m.read_by IS NULL OR JSON_CONTAINS(m.read_by, ?, '$') = 0) AND t.status != 'archived'";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            json_encode($user_id),
            $user_id,
            json_encode($user_id)
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_unread = (int)($result['total_unread'] ?? 0);
        echo json_encode(['success' => true, 'total_unread' => $total_unread]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'total_unread' => 0]);
}
?>
