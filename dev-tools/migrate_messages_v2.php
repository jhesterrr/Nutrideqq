<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'database.php';

echo "<h1>🛠️ NutriDeq Messaging v2 Migration</h1>";
echo "<p>Adding Reply & Unsend columns to <code>wellness_messages</code>...</p>";

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Columns to add to wellness_messages
    $migrations = [
        "reply_to_id"  => "ALTER TABLE wellness_messages ADD COLUMN reply_to_id INT NULL DEFAULT NULL AFTER message_type",
        "is_unsent"    => "ALTER TABLE wellness_messages ADD COLUMN is_unsent TINYINT(1) NOT NULL DEFAULT 0 AFTER reply_to_id",
        "is_read"      => "ALTER TABLE wellness_messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER is_unsent",
    ];

    foreach ($migrations as $col => $sql) {
        try {
            $pdo->exec($sql);
            echo "<p>✅ Added column <code>$col</code> to <code>wellness_messages</code>.</p>";
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (
                stripos($msg, 'Duplicate column name') !== false ||
                stripos($msg, 'Column already exists') !== false ||
                stripos($msg, '1060') !== false
            ) {
                echo "<p>ℹ️ Column <code>$col</code> already exists — skipped.</p>";
            } else {
                echo "<p>❌ Failed to add <code>$col</code>: $msg</p>";
            }
        }
    }

    // Add a foreign-key-safe index on reply_to_id for performance (optional, catches failures gracefully)
    try {
        $pdo->exec("ALTER TABLE wellness_messages ADD INDEX idx_reply_to (reply_to_id)");
        echo "<p>✅ Index on <code>reply_to_id</code> created.</p>";
    } catch (PDOException $e) {
        echo "<p>ℹ️ Index already exists or skipped: " . $e->getMessage() . "</p>";
    }

    echo "<hr><h2>🎉 Migration Complete!</h2>";
    echo "<p>The messaging system is now fully set up with Reply & Unsend support.</p>";
    echo "<p><a href='staff-messages.php' style='padding:10px 20px;background:#059669;color:white;border-radius:8px;text-decoration:none;'>→ Go to Messaging</a></p>";

} catch (Exception $e) {
    echo "<h2>💥 Critical Error</h2><pre>" . $e->getMessage() . "</pre>";
}
?>
