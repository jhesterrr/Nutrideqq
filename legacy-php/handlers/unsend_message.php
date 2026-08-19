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

if ($user_role === 'admin') {
    echo json_encode(['success' => false, 'error' => 'Admins cannot edit messages']);
    exit;
}

$message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;

if ($message_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid message ID']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Retrieve message details
    $stmt = $pdo->prepare("SELECT * FROM wellness_messages WHERE id = ? LIMIT 1");
    $stmt->execute([$message_id]);
    $message = $stmt->fetch();

    if (!$message) {
        echo json_encode(['success' => false, 'error' => 'Message not found']);
        exit;
    }

    // Check permission: you can only delete your own messages
    $is_owner = false;
    if ($user_role === 'staff' && $message['sender_type'] === 'staff' && intval($message['sender_id']) === intval($user_id)) {
        $is_owner = true;
    } elseif ($user_role !== 'staff' && $user_role !== 'admin') {
        // Client
        $stmtC = $pdo->prepare("SELECT id FROM clients WHERE user_id = ? LIMIT 1");
        $stmtC->execute([$user_id]);
        $c_row = $stmtC->fetch();
        $client_id = $c_row ? intval($c_row['id']) : 0;

        if ($message['sender_type'] === 'client' && intval($message['sender_id']) === $client_id) {
            $is_owner = true;
        }
    }

    if (!$is_owner) {
        echo json_encode(['success' => false, 'error' => 'Permission denied. You can only unsend your own messages.']);
        exit;
    }

    // Update message to is_unsent = 1, erase original content and attachment for privacy
    $upd = $pdo->prepare("UPDATE wellness_messages SET is_unsent = 1, content = 'This message was unsent', attachment_path = NULL, message_type = 'text' WHERE id = ?");
    $upd->execute([$message_id]);

    echo json_encode(['success' => true]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
