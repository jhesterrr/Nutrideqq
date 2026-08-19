<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

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