<?php
require_once 'database.php';
$db = new Database();
$conn = $db->getConnection();

echo "Checking fct_food_items table...\n";
try {
    $stmt = $conn->query("DESCRIBE fct_food_items");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "Field: " . $col['Field'] . " | Type: " . $col['Type'] . "\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>