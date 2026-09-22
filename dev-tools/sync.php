<?php
// sync.php - Universal Database Sync Tool
header('Content-Type: text/html');
require_once 'database.php';

function columnExists($pdo, $table, $column) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) { return false; }
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    echo "<h1>🏥 NutriDeq Clinical Sync v1.0.2</h1>";

    $tables = [
        'wellness_messages' => ['attachment_path' => "VARCHAR(255) NULL AFTER content", 'message_type' => "ENUM('text', 'image', 'file') DEFAULT 'text' AFTER attachment_path"],
        'internal_thread_messages' => ['attachment_path' => "VARCHAR(255) NULL AFTER message", 'message_type' => "ENUM('text', 'image', 'file') DEFAULT 'text' AFTER attachment_path"]
    ];

    foreach ($tables as $table => $cols) {
        foreach ($cols as $col => $definition) {
            if (!columnExists($pdo, $table, $col)) {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $definition");
                echo "<p>✅ Added <strong>$col</strong> to <strong>$table</strong>.</p>";
            } else {
                echo "<p>ℹ️ <strong>$table.$col</strong> already exists.</p>";
            }
        }
    }

    echo "<h3>🎉 Cloud Synchronization Complete!</h3>";
    echo "<a href='dashboard.php' style='padding:10px 20px; background:#10B981; color:white; border-radius:8px; text-decoration:none;'>Enter Elite Portal</a>";

} catch (Exception $e) {
    echo "<h1>❌ Sync Error</h1><p>" . $e->getMessage() . "</p>";
}
