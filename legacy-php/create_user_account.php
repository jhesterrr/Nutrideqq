<?php
$session_path = 'c:\xampp\htdocs\nutrideq\api\sessions';
if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
session_save_path($session_path);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_role'] !== 'staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $input = json_decode(file_get_contents('php://input'), true);
    $client_id = $input['client_id'] ?? null;
    $staff_id = $_SESSION['user_id'];

    if (!$client_id) {
        echo json_encode(['success' => false, 'message' => 'Client ID is required']);
        exit();
    }

    // Get client data
    $client_stmt = $conn->prepare("SELECT * FROM clients WHERE id = ? AND staff_id = ?");
    $client_stmt->execute([$client_id, $staff_id]);
    $client = $client_stmt->fetch();

    if (!$client) {
        echo json_encode(['success' => false, 'message' => 'Client not found']);
        exit();
    }

    // Check if user already exists
    $user_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $user_stmt->execute([$client['email']]);
    if ($user_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'User account already exists for this email']);
        exit();
    }

    // Create user account
    $default_password = "nutrideq123";
    $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
    
    $create_user_stmt = $conn->prepare("
        INSERT INTO users (name, email, password, role, status) 
        VALUES (?, ?, ?, 'regular', 'active')
    ");
    
    $create_user_stmt->execute([
        $client['name'],
        $client['email'],
        $hashed_password
    ]);
    
    $user_id = $conn->lastInsertId();

    // Update client with user_id
    $update_client_stmt = $conn->prepare("UPDATE clients SET user_id = ? WHERE id = ?");
    $update_client_stmt->execute([$user_id, $client_id]);

    echo json_encode(['success' => true, 'message' => 'User account created successfully']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>