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
require_once '../database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$entry_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($entry_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid entry ID']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Verify ownership
    $check = $pdo->prepare("SELECT user_id FROM food_tracking WHERE id = ?");
    $check->execute([$entry_id]);
    $row = $check->fetch();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Entry not found']);
        exit();
    }

    if ($row['user_id'] != $user_id && $_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'staff') {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }

    $stmt = $pdo->prepare("DELETE FROM food_tracking WHERE id = ?");
    $stmt->execute([$entry_id]);

    echo json_encode(['success' => true, 'message' => 'Entry deleted']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
