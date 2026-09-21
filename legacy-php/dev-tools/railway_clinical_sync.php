<?php
// railway_clinical_sync.php - Master Database Sync for Railway.app
require_once 'database.php';
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h1>🏥 NutriDeq Remote Database Sync</h1>";

    // 1. Wellness Messages
    $check1 = $pdo->query("SHOW COLUMNS FROM wellness_messages LIKE 'attachment_path'");
    if ($check1->rowCount() == 0) {
        $pdo->exec("ALTER TABLE wellness_messages ADD COLUMN attachment_path VARCHAR(255) NULL AFTER content, ADD COLUMN message_type ENUM('text', 'image', 'file') DEFAULT 'text' AFTER attachment_path");
        echo "<p>✅ Railway Main Messaging: Updated.</p>";
    } else {
        echo "<p>ℹ️ Railway Main Messaging: Already Ready.</p>";
    }

    // 2. Internal Messages
    $check2 = $pdo->query("SHOW COLUMNS FROM internal_thread_messages LIKE 'attachment_path'");
    if ($check2->rowCount() == 0) {
        $pdo->exec("ALTER TABLE internal_thread_messages ADD COLUMN attachment_path VARCHAR(255) NULL AFTER message, ADD COLUMN message_type ENUM('text', 'image', 'file') DEFAULT 'text' AFTER attachment_path");
        echo "<p>✅ Railway Help Center: Updated.</p>";
    } else {
        echo "<p>ℹ️ Railway Help Center: Already Ready.</p>";
    }
    
    echo "<hr><h3>Database Successfully Mirrored.</h3>";
    echo "<a href='dashboard.php'>Return to Dashboard</a>";
    
} catch (Exception $e) {
    echo "<h1>❌ Sync Error</h1><p>" . $e->getMessage() . "</p>";
}
