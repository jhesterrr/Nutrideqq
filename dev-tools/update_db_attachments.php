<?php
// update_db_attachments.php
require_once 'database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h1>Updating Database for Attachments...</h1>";

    // Add columns if they don't exist
    $sql = "
    ALTER TABLE wellness_messages 
    ADD COLUMN attachment_path VARCHAR(255) NULL AFTER message_type,
    ADD COLUMN file_name VARCHAR(255) NULL AFTER attachment_path;
    ";

    try {
        $pdo->exec($sql);
        echo "<p>✅ Added 'attachment_path' and 'file_name' columns.</p>";
    } catch (PDOException $e) {
        // Ignore if exists (error code 42S21 in MySQL, but generic catch is safer for simple scripts)
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
             echo "<p>ℹ️ Columns already exist.</p>";
        } else {
             echo "<p>⚠️ Note: " . $e->getMessage() . "</p>";
        }
    }
    
    // Create uploads directory
    if (!file_exists('uploads')) {
        mkdir('uploads', 0777, true);
        echo "<p>✅ Created 'uploads' directory.</p>";
    } else {
        echo "<p>ℹ️ 'uploads' directory exists.</p>";
    }

    echo "<hr><h3>Update Complete.</h3>";
    echo "<a href='staff-messages.php'>Return to Messaging</a>";

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
