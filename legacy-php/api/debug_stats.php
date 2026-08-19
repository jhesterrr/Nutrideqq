<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

header('Content-Type: application/json');

$info = [
    'session_logged_in' => $_SESSION['logged_in'] ?? 'NOT SET',
    'session_role' => $_SESSION['user_role'] ?? 'NOT SET',
    'session_id' => session_id(),
    'php_version' => PHP_VERSION,
    'db_test' => 'pending'
];

try {
    require_once __DIR__ . '/../database.php';
    $db = new Database();
    $pdo = $db->getConnection();
    $info['db_test'] = 'connected';
    
    // Check tables
    $tables = ['users','clients','food_tracking','hydration_tracking','wellness_messages'];
    foreach ($tables as $t) {
        $r = $pdo->query("SHOW TABLES LIKE '$t'");
        $info['table_' . $t] = $r->rowCount() > 0 ? 'EXISTS' : 'MISSING';
    }
} catch (Exception $e) {
    $info['db_test'] = 'FAILED: ' . $e->getMessage();
}

echo json_encode($info, JSON_PRETTY_PRINT);
