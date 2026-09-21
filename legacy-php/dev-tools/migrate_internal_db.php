<?php
require_once 'database.php';
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Internal Thread Messages
    $check = $pdo->query("SHOW COLUMNS FROM internal_thread_messages LIKE 'attachment_path'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE internal_thread_messages ADD COLUMN attachment_path VARCHAR(255) NULL AFTER message");
        $pdo->exec("ALTER TABLE internal_thread_messages ADD COLUMN message_type ENUM('text', 'image', 'file') DEFAULT 'text' AFTER attachment_path");
        echo "<h1>✅ Internal Messaging Updated!</h1>";
    } else {
        echo "<h1>ℹ️ Internal Messaging Already Ready</h1>";
    }
    
    echo "<br><a href='staff-help.php'>Return to Help Center</a>";
    
} catch (Exception $e) {
    echo "<h1>❌ Migration Error</h1><p>" . $e->getMessage() . "</p>";
}
