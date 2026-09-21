<?php
require_once 'database.php';
$database = new Database();
$pdo = $database->getConnection();
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo json_encode($tables);
?>
