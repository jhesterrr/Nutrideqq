<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

require_once '../database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$client_user_id = $data['client_user_id'] ?? null;
$staff_id = $data['staff_id'] ?? null;

if (!$client_user_id) {
    echo json_encode(['success' => false, 'message' => 'Missing client user ID']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if client exists in clients table
    $stmt = $conn->prepare("SELECT id FROM clients WHERE user_id = ?");
    $stmt->execute([$client_user_id]);
    $client = $stmt->fetch();
    
    if ($client) {
        $update_stmt = $conn->prepare("UPDATE clients SET staff_id = ?, is_assigned = ?, updated_at = NOW() WHERE user_id = ?");
        $is_assigned = empty($staff_id) ? 0 : 1;
        $staff_val = empty($staff_id) ? null : $staff_id;
        $update_stmt->execute([$staff_val, $is_assigned, $client_user_id]);
    } else {
        // Fetch basic info from users table to insert into clients
        $user_stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
        $user_stmt->execute([$client_user_id]);
        $user = $user_stmt->fetch();
        
        if ($user) {
            $insert_stmt = $conn->prepare("INSERT INTO clients (user_id, staff_id, name, email, is_assigned) VALUES (?, ?, ?, ?, ?)");
            $is_assigned = empty($staff_id) ? 0 : 1;
            $staff_val = empty($staff_id) ? null : $staff_id;
            $insert_stmt->execute([$client_user_id, $staff_val, $user['name'], $user['email'], $is_assigned]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit();
        }
    }
    
    // Get staff name to return
    $staff_name = '';
    if (!empty($staff_id)) {
        $sstmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
        $sstmt->execute([$staff_id]);
        $staff = $sstmt->fetch();
        if ($staff) {
            $staff_name = $staff['name'];
        }
    }
    
    echo json_encode(['success' => true, 'staff_name' => $staff_name]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
