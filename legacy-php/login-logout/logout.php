<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

if (isset($_SESSION['user_id'])) {
    try {
        require_once '../database.php';
        $db = new Database();
        $pdo = $db->getConnection();
        $stmt = $pdo->prepare("UPDATE users SET online_status = 0 WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {
        error_log('NutriDeq logout error: ' . $e->getMessage());
    }
}
session_unset();
session_destroy();
header("Location: NutriDeqN-Login.php");
exit();
?>