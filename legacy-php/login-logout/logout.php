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
if (isset($_SESSION['user_id'])) {
    try {
        require_once '../database.php';
        $db = new Database();
        $pdo = $db->getConnection();
        $stmt = $pdo->prepare("UPDATE users SET online_status = 0 WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {}
}
session_unset();
session_destroy();
header("Location: NutriDeqN-Login.php");
exit();
?>