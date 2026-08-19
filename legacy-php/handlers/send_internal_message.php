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

$thread_id = intval($_POST['thread_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

// Only staff and admin can send internal messages
if (!in_array($user_role, ['staff', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$has_attachment = (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK);

if ($thread_id <= 0 || (empty($message) && !$has_attachment)) {
    echo json_encode(['success' => false, 'error' => 'Invalid input - message or attachment required']);
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
        // Robust JSON matching for Cloud DB
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

    if ($thread['status'] !== 'open') {
        echo json_encode(['success' => false, 'error' => 'Thread is closed']);
        exit;
    }

    // 1. Handle Attachment Upload
    $attachment_path = null;
    $msg_type = 'text';

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'png', 'jpg', 'jpeg'];

        if (in_array($ext, $allowed)) {
            $upload_dir = '../uploads/reports/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $filename = uniqid('int_report_', true) . '.' . $ext;
            $target = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $target)) {
                $attachment_path = 'uploads/reports/' . $filename;
                $msg_type = ($ext === 'pdf') ? 'file' : 'image';
            }
        }
    }

    // 2. Insert message
    $stmt = $pdo->prepare("
        INSERT INTO internal_thread_messages (thread_id, sender_id, sender_role, message, attachment_path, message_type, read_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $thread_id, 
        $user_id, 
        $user_role, 
        $message, 
        $attachment_path,
        $msg_type,
        json_encode([$user_id])
    ]);
    
    $message_id = $pdo->lastInsertId();

    // Update thread last message time
    $update = $pdo->prepare("UPDATE internal_threads SET last_message_at = NOW(), updated_at = NOW() WHERE id = ?");
    $update->execute([$thread_id]);

    // Fetch the inserted message to return
    $stmt = $pdo->prepare("
        SELECT m.*, 
               u.name as sender_name,
               u.role as u_role
        FROM internal_thread_messages m
        LEFT JOIN users u ON m.sender_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$message_id]);
    $msg = $stmt->fetch(PDO::FETCH_ASSOC);

    $formatted_message = [
        'id' => $msg['id'],
        'message' => $msg['message'],
        'sender_id' => $msg['sender_id'],
        'sender_role' => $msg['sender_role'],
        'sender_name' => $msg['sender_name'],
        'attachment_path' => $msg['attachment_path'],
        'message_type' => $msg['message_type'],
        'file_name' => 'Support-Internal-Report.pdf',
        'type' => 'sent',
        'created_at' => $msg['created_at'],
        'pretty_time' => date('g:i A', strtotime($msg['created_at'])),
        'read_status' => 1
    ];

    echo json_encode(['success' => true, 'message' => $formatted_message]);

} catch (Throwable $e) {
    // Stop returning 500 for better cloud debugging
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
