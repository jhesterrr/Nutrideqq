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
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_role'] !== 'staff') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once 'database.php';
$database = new Database();
$pdo = $database->getConnection();

$staff_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $thread_title = trim($_POST['thread_title'] ?? '');
    $initial_message = trim($_POST['initial_message'] ?? '');
    $selected_admins = $_POST['admins'] ?? []; // Array of IDs
    
    // Validate
    if (empty($thread_title) || empty($initial_message) || empty($selected_admins)) {
        echo json_encode(['success' => false, 'error' => 'Please fill all required fields']);
        exit();
    }

    try {
        $pdo->beginTransaction();
        
        $thread_uuid = bin2hex(random_bytes(16));
        $participants = array_merge([$staff_id], array_map('intval', $selected_admins));
        
        // Create Thread
        $stmt = $pdo->prepare(
            "INSERT INTO internal_threads (thread_uuid, title, created_by, participants, status, last_message_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, 'open', NOW(), NOW(), NOW())"
        );
        $stmt->execute([$thread_uuid, $thread_title, $staff_id, json_encode($participants)]);
        $new_thread_id = $pdo->lastInsertId();
        
        // Create Initial Message
        $stmt = $pdo->prepare(
            "INSERT INTO internal_thread_messages (thread_id, sender_id, sender_role, message, read_by, created_at)
             VALUES (?, ?, 'staff', ?, ?, NOW())"
        );
        $stmt->execute([$new_thread_id, $staff_id, $initial_message, json_encode([$staff_id])]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'thread_id' => $new_thread_id,
            'title' => $thread_title
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
