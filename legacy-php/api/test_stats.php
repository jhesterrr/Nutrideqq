<?php
$session_path = 'c:\xampp\htdocs\nutrideq\api\sessions';
if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
session_save_path($session_path);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$_SESSION['logged_in'] = true;
$_SESSION['user_role'] = 'admin';

// Output buffering to capture everything
ob_start();
include 'admin_stats.php';
$output = ob_get_clean();

// Force dump it
header('Content-Type: text/plain');
echo "=== RAW OUTPUT START ===\n";
echo $output;
echo "\n=== RAW OUTPUT END ===";
