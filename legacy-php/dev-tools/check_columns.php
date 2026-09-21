<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'database.php';

echo "<pre>";
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $stmt = $pdo->query("SHOW COLUMNS FROM wellness_messages");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Columns in wellness_messages:\n";
    print_r($columns);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
echo "</pre>";
