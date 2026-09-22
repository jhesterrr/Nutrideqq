<?php
// setup_wellness_db.php
require_once 'database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h1>Initializing Wellness Messaging Database...</h1>";

    // 1. Conversations Table
    $sql_conv = "CREATE TABLE IF NOT EXISTS conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        dietitian_id INT NOT NULL,
        status ENUM('open', 'awaiting', 'resolved') DEFAULT 'open',
        last_message_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_thread (client_id, dietitian_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql_conv);
    echo "<p>✅ Table 'conversations' check/create: Done.</p>";

    // 2. Messages Table (wellness_messages to avoid conflict)
    $sql_msg = "CREATE TABLE IF NOT EXISTS wellness_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        sender_type ENUM('client', 'staff', 'ai') NOT NULL,
        sender_id INT NOT NULL,
        content TEXT NOT NULL,
        message_type ENUM('text', 'image', 'file') DEFAULT 'text',
        read_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_conversation (conversation_id),
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql_msg);
    echo "<p>✅ Table 'wellness_messages' check/create: Done.</p>";

    echo "<hr><h3>Database Setup Complete.</h3>";
    echo "<a href='staff-messages.php'>Go to Messaging</a>";

} catch (Exception $e) {
    die("Error setting up database: " . $e->getMessage());
}
?>
