<?php
require 'database.php';
$db = new Database();$conn = $db->getConnection();
$stmt = $conn->query('SELECT COUNT(*) FROM fct_food_items');
echo "Total items: " . $stmt->fetchColumn() . "\n";

$stmt2 = $conn->query('SELECT COUNT(*) FROM fct_food_items WHERE category IS NULL OR category = ""');
echo "Items with empty category: " . $stmt2->fetchColumn() . "\n";

$stmt3 = $conn->query('SELECT category, COUNT(*) as c FROM fct_food_items GROUP BY category');
while ($r = $stmt3->fetch(PDO::FETCH_ASSOC)) {
    echo $r['category'] . ": " . $r['c'] . "\n";
}
