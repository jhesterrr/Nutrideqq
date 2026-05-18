<?php
require_once 'database.php';
header('Content-Type: text/html; charset=utf-8');
echo "<h1>NutriDeq MASTER CLOUD REPAIR 🛠️</h1>";

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Function to safely add columns
    function addColumn($pdo, $table, $column, $definition) {
        try {
            $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
            echo "🔧 Added column: <b>$column</b> to <b>$table</b><br>";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "✅ Column <b>$column</b> already exists in <b>$table</b><br>";
            } else {
                echo "❌ Error adding $column to $table: " . $e->getMessage() . "<br>";
            }
        }
    }

    // 1. Core Tables Creation
    $pdo->exec("CREATE TABLE IF NOT EXISTS internal_threads (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, created_by INT NOT NULL, participants JSON NOT NULL, status ENUM('open', 'closed', 'archived') DEFAULT 'open', last_message_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;");
    $pdo->exec("CREATE TABLE IF NOT EXISTS internal_thread_messages (id INT AUTO_INCREMENT PRIMARY KEY, thread_id INT NOT NULL, sender_id INT NOT NULL, sender_role VARCHAR(50) NOT NULL, message TEXT, attachment_path VARCHAR(255) NULL, message_type ENUM('text', 'image', 'file') DEFAULT 'text', read_by JSON NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;");
    $pdo->exec("CREATE TABLE IF NOT EXISTS conversations (id INT AUTO_INCREMENT PRIMARY KEY, client_id INT NOT NULL, dietitian_id INT NOT NULL, status ENUM('open', 'closed', 'archived') DEFAULT 'open', last_message_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;");
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_requests (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token VARCHAR(255) NOT NULL, status ENUM('pending', 'cancelled', 'used') DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_user_id (user_id), INDEX idx_token (token)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "✅ <b>password_reset_requests</b> table ensured.<br>";
    
    // 2. Ensure critical columns exist
    addColumn($pdo, 'internal_threads', 'updated_at', 'DATETIME NULL AFTER last_message_at');
    addColumn($pdo, 'internal_thread_messages', 'attachment_path', 'VARCHAR(255) NULL AFTER message');
    addColumn($pdo, 'internal_thread_messages', 'message_type', "ENUM('text', 'image', 'file') DEFAULT 'text' AFTER attachment_path");
    addColumn($pdo, 'wellness_messages', 'attachment_path', 'VARCHAR(255) NULL AFTER content');
    addColumn($pdo, 'wellness_messages', 'message_type', "ENUM('text', 'image', 'file') DEFAULT 'text' AFTER attachment_path");

    echo "<h2>🎉 CLOUD SYNC COMPLETE!</h2>";
    echo "<p>Your database is now 100% compatible with the Render/Aiven environment.</p>";

} catch (Exception $e) {
    echo "<h1>❌ Master Repair Failed</h1>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
