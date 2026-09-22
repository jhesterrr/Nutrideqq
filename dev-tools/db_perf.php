<?php
$start = microtime(true);
require_once 'database.php';
$req_time = microtime(true) - $start;

$conn_start = microtime(true);
$db = new Database();
$conn = $db->getConnection();
$conn_time = microtime(true) - $conn_start;

$query_start = microtime(true);
$stmt = $conn->query("SELECT 1");
$stmt->execute();
$query_time = microtime(true) - $query_start;

$total_time = microtime(true) - $start;

echo "Timing metrics (seconds):\n";
echo "Include database.php: " . number_format($req_time, 4) . "s\n";
echo "Database Connection: " . number_format($conn_time, 4) . "s\n";
echo "SELECT 1 Query: " . number_format($query_time, 4) . "s\n";
echo "Total Execution: " . number_format($total_time, 4) . "s\n";
?>
