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

$database = new Database();
$pdo = $database->getConnection();

$user_id = $_SESSION['user_id'] ?? 0;
$type = $_GET['type'] ?? 'staff';
$staff_id = $_GET['staff_id'] ?? $user_id;

header('Content-Type: application/json');

try {
    if ($type === 'staff') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as unread_count
            FROM internal_thread_messages m
            JOIN internal_threads t ON m.thread_id = t.id
            WHERE JSON_CONTAINS(t.participants, ?) 
            AND m.sender_id != ?
            AND (m.read_by IS NULL OR JSON_CONTAINS(m.read_by, ?, '$') = 0)
        ");
        $staff_json = json_encode($staff_id);
        $stmt->execute([$staff_json, $staff_id, $staff_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as unread_count
            FROM internal_thread_messages m
            JOIN internal_threads t ON m.thread_id = t.id
            WHERE JSON_CONTAINS(t.participants, ?) 
            AND m.sender_id != ?
            AND (m.read_by IS NULL OR JSON_CONTAINS(m.read_by, ?, '$') = 0)
        ");
        $admin_json = json_encode($user_id);
        $stmt->execute([$admin_json, $user_id, $user_id]);
    }
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['unread_count' => $result['unread_count'] ?? 0]);
} catch (Exception $e) {
    echo json_encode(['unread_count' => 0, 'error' => $e->getMessage()]);
}
?>