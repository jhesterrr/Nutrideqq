<?php
require_once 'database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "<h2>NutriDeq Production Migration</h2>";

    // 1. Update food_logs
    $sql1 = "ALTER TABLE food_logs MODIFY COLUMN meal_type ENUM('Breakfast', 'AM Snack', 'Lunch', 'PM Snack', 'Dinner', 'Snack') NOT NULL";
    $db->exec($sql1);
    echo "✅ Successfully updated 'food_logs' table.<br>";

    // 2. Update food_tracking
    $sql2 = "ALTER TABLE food_tracking MODIFY COLUMN meal_type ENUM('Breakfast', 'AM Snack', 'Lunch', 'PM Snack', 'Dinner', 'Snack') NOT NULL";
    $db->exec($sql2);
    echo "✅ Successfully updated 'food_tracking' table.<br>";

    echo "<br><b>Migration complete!</b><br>";
    echo "<span style='color:red;'>IMPORTANT: Please delete this file (run_migration.php) from your repository immediately for security.</span>";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
    // Add sodium and sugar to food_logs
    try {
        ->exec('ALTER TABLE food_logs ADD COLUMN sodium DECIMAL(10,2) DEFAULT 0 AFTER fat');
        ->exec('ALTER TABLE food_logs ADD COLUMN sugar DECIMAL(10,2) DEFAULT 0 AFTER sodium');
        echo '? Successfully added sodium and sugar to food_logs.<br>';
    } catch (Exception ) { echo '?? Sodium/Sugar columns might already exist.<br>'; }
