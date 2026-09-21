<?php
$start = microtime(true);
require_once 'database.php';
$req_time = microtime(true) - $start;

$conn_start = microtime(true);
$db = new Database();
$conn = $db->getConnection();
$conn_time = microtime(true) - $conn_start;

$query_start = microtime(true);
$tables = [];
if ($conn) {
    try {
        $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $tables_err = $e->getMessage();
    }
}
$query_time = microtime(true) - $query_start;

$total_time = microtime(true) - $start;

$out = "Diagnostic Time: " . date('Y-m-d H:i:s') . "\n";
$out .= "Timing metrics (seconds):\n";
$out .= "Include database.php: " . number_format($req_time, 4) . "s\n";
$out .= "Database Connection: " . number_format($conn_time, 4) . "s\n";
$out .= "SHOW TABLES Query: " . number_format($query_time, 4) . "s\n";
$out .= "Total Execution: " . number_format($total_time, 4) . "s\n\n";
$out .= "Tables: " . implode(", ", $tables) . "\n";

file_put_contents('db_check_result.txt', $out);
echo "Diagnostic complete. Timings: Conn=" . number_format($conn_time, 4) . "s, Query=" . number_format($query_time, 4) . "s. Total=" . number_format($total_time, 4) . "s.";
?>