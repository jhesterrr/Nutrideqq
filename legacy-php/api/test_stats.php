<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

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
