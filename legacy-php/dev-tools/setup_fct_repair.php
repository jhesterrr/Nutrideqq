<?php
require_once 'api/fct_helper.php';
require_once 'database.php';

echo "<div style='font-family: sans-serif; padding: 20px;'>";
echo "<h2>FCT Database Repair Tool</h2>";

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Check fct_food_items table
    $stmt = $conn->query("SHOW TABLES LIKE 'fct_food_items'");
    $recreateFood = false;

    if ($stmt->rowCount() > 0) {
        $stmt = $conn->query("DESCRIBE fct_food_items");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        // If it doesn't have food_name, it's the old/wrong schema
        if (!in_array('food_name', $columns)) {
            echo "<p>⚠️ Incompatible schema detected in <b>fct_food_items</b>.</p>";
            // Check if it's empty before dropping
            $count = $conn->query("SELECT COUNT(*) FROM fct_food_items")->fetchColumn();
            if ($count == 0) {
                echo "<p>🗑️ Table is empty. Dropping and recreating...</p>";
                // Check if fct_nutrients exists before trying to drop it
                $stmtNutrients = $conn->query("SHOW TABLES LIKE 'fct_nutrients'");
                if ($stmtNutrients->rowCount() > 0) {
                    $conn->exec("DROP TABLE fct_nutrients"); // Drop dependent table first
                }
                $conn->exec("DROP TABLE fct_food_items");
                $recreateFood = true;
            } else {
                echo "<p>❌ Table contains data. Manual intervention required to preserve data.</p>";
                exit;
            }
        }
    } else {
        $recreateFood = true;
    }

    if ($recreateFood) {
        echo "<p>🔨 Creating <b>fct_food_items</b> table...</p>";
        $sql = "CREATE TABLE `fct_food_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `food_id` VARCHAR(50) NOT NULL UNIQUE,
            `food_name` VARCHAR(255) NOT NULL,
            `category` VARCHAR(100) DEFAULT 'General',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (`food_name`),
            INDEX (`category`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $conn->exec($sql);
        echo "<p>✅ Table <b>fct_food_items</b> created.</p>";
    }

    // Check fct_nutrients table
    $stmt = $conn->query("SHOW TABLES LIKE 'fct_nutrients'");
    $recreateNutrients = ($stmt->rowCount() == 0 || $recreateFood);

    if ($recreateNutrients && $stmt->rowCount() > 0 && !$recreateFood) {
        // If it exists but food_item_id is missing, it's wrong
        $stmt = $conn->query("DESCRIBE fct_nutrients");
        $nCols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('food_item_id', $nCols)) {
            echo "<p>🗑️ Incompatible <b>fct_nutrients</b> detected. Dropping...</p>";
            $conn->exec("DROP TABLE fct_nutrients");
            $recreateNutrients = true;
        }
    }

    if ($recreateNutrients) {
        echo "<p>🔨 Creating <b>fct_nutrients</b> table...</p>";
        $sql = "CREATE TABLE `fct_nutrients` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `food_item_id` INT NOT NULL,
            `nutrient_name` VARCHAR(100) NOT NULL,
            `value` DECIMAL(10, 4) DEFAULT 0.0000,
            `unit` VARCHAR(20) DEFAULT '',
            FOREIGN KEY (`food_item_id`) REFERENCES `fct_food_items`(`id`) ON DELETE CASCADE,
            UNIQUE KEY `unique_nutrient` (`food_item_id`, `nutrient_name`),
            INDEX (`nutrient_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $conn->exec($sql);
        echo "<p>✅ Table <b>fct_nutrients</b> created.</p>";
    }

    echo "<h3>🎉 Database repair complete!</h3>";
    echo "<p><a href='fct-library.php' style='padding: 10px 20px; background: #2E8B57; color: white; text-decoration: none; border-radius: 5px;'>Back to FCT Library</a></p>";

} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
echo "</div>";
?>