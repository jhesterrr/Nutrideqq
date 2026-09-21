<?php
require_once 'database.php';
try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Check if password_reset_requests table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'password_reset_requests'");
    if ($stmt->rowCount() == 0) {
        // Create the table
        $pdo->exec("
            CREATE TABLE password_reset_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(255) NOT NULL,
                status ENUM('pending', 'cancelled', 'used') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (user_id),
                INDEX (token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "<h1>✅ password_reset_requests table created successfully!</h1>";
    } else {
        echo "<h1>ℹ️ password_reset_requests table already exists!</h1>";
    }

    echo "<br><a href='admin-user-management.php'>Return to User Management</a>";

} catch (Exception $e) {
    echo "<h1>❌ Migration Error</h1><p>" . $e->getMessage() . "</p>";
}
