<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$contact_id = intval($_POST['contact_id'] ?? $_GET['contact_id'] ?? 0);
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'regular';

try {
    $database = new Database();
    $pdo = $database->getConnection();

    if ($action === 'set_typing') {
        $is_typing = intval($_POST['is_typing'] ?? 0);
        $expires = date('Y-m-d H:i:s', strtotime('+10 seconds'));

        // Store typing status in a simple table or use sessions
        // For simplicity, we'll use a file-based approach since there's no typing table
        // A production system would use Redis or a database table
        $cache_file = sys_get_temp_dir() . "/nutrideq_typing_" . $contact_id . "_" . $user_id . ".txt";
        file_put_contents($cache_file, json_encode([
            'is_typing' => $is_typing,
            'user_id' => $user_id,
            'role' => $user_role,
            'expires' => $expires
        ]));

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'check_typing') {
        // Check if the contact is typing to the current user
        $cache_file = sys_get_temp_dir() . "/nutrideq_typing_" . $user_id . "_" . $contact_id . ".txt";
        if (file_exists($cache_file)) {
            $data = json_decode(file_get_contents($cache_file), true);
            if ($data && strtotime($data['expires']) > time()) {
                // Check if it's a different user
                if ($data['user_id'] != $user_id || $data['role'] != $user_role) {
                    echo json_encode(['success' => true, 'is_typing' => (bool)$data['is_typing'], 'user_id' => $data['user_id']]);
                    exit;
                }
            } else {
                // Expired, clean up
                unlink($cache_file);
            }
        }
        echo json_encode(['success' => true, 'is_typing' => false]);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
