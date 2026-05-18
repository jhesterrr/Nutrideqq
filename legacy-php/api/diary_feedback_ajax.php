<?php
ini_set('display_errors', 0);
$session_path = 'c:\xampp\htdocs\nutrideq\api\sessions';
if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
session_save_path($session_path);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'fetch') {
    $user_id = intval($_GET['user_id'] ?? $_GET['client_user_id'] ?? 0);
    $log_date = $_GET['log_date'];

    try {
        $sql = "SELECT df.*, u.name as staff_name 
                FROM diary_feedback df
                JOIN users u ON df.staff_id = u.id
                WHERE df.user_id = ? AND df.log_date = ? 
                ORDER BY df.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id, $log_date]);
        $feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'feedback' => $feedback]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} elseif ($action === 'save') {
    $user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
    if ($user_role !== 'staff' && $user_role !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit();
    }

    $user_id = intval($_POST['user_id'] ?? $_POST['client_user_id'] ?? 0);
    $log_date = $_POST['log_date'];
    $content = trim($_POST['content']);
    $staff_id = $_SESSION['user_id'];

    if (empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Content cannot be empty']);
        exit();
    }

    try {
        $sql = "INSERT INTO diary_feedback (user_id, staff_id, log_date, content) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id, $staff_id, $log_date, $content]);

        $new_id = $conn->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Feedback saved',
            'feedback' => [
                'id' => $new_id,
                'content' => $content,
                'staff_name' => $_SESSION['user_name'] ?? 'Staff',
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
