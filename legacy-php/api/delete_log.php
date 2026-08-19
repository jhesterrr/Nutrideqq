<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

require_once '../database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? '';
$log_id = $_POST['id'] ?? 0;

if (!$log_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit();
}

// Security: Only 'user' role can delete, and only their own logs
if ($user_role !== 'user') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden: Only users can manage their own logs']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Ensure the log belongs to this user
    $sql = "DELETE FROM food_logs WHERE id = :id AND user_id = :user_id";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([':id' => $log_id, ':user_id' => $user_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Log deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Log not found or access denied']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
