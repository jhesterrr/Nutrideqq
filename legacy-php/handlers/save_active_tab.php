<?php
$session_path = 'c:\xampp\htdocs\nutrideq\api\sessions';
if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
session_save_path($session_path);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../database.php';

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['tab'])) {
        $_SESSION['active_health_tracker_tab'] = $data['tab'];
        
        echo json_encode(['success' => true, 'message' => 'Tab saved']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No tab specified']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>