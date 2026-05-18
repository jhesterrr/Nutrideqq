<?php
require_once 'database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "CREATE TABLE IF NOT EXISTS food_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    food_id VARCHAR(50),
    food_name VARCHAR(255) NOT NULL,
    calories DECIMAL(10,2) DEFAULT 0,
    protein DECIMAL(10,2) DEFAULT 0,
    carbs DECIMAL(10,2) DEFAULT 0,
    fat DECIMAL(10,2) DEFAULT 0,
    serving_size DECIMAL(10,2) DEFAULT 100,
    meal_type ENUM('Breakfast', 'AM Snack', 'Lunch', 'PM Snack', 'Dinner', 'Snack') NOT NULL,
    log_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (log_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

try {
    $conn->exec($sql);
    echo "Table 'food_logs' created successfully!";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>
