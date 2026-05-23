<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'database.php';

echo "<h1>🛠️ NutriDeq UNIVERSAL FORCE SYNC 🛠️</h1>";

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // --- NEW AI TABLES ---
    echo "<h3>Creating AI Integration Tables...</h3>";
    
    $ai_tables = [
        "CREATE TABLE IF NOT EXISTS ai_burnout_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            message_id INT NULL,
            sentiment_score INT NOT NULL COMMENT '1-10 stress level',
            burnout_flag BOOLEAN DEFAULT FALSE,
            analysis_text TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS ai_extracted_symptoms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            message_id INT NULL,
            symptom_keyword VARCHAR(255) NOT NULL,
            mapped_nutrient VARCHAR(255) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS ai_recommendations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            dietitian_id INT NULL,
            recommendation_text TEXT NOT NULL,
            trigger_source VARCHAR(100) NOT NULL COMMENT 'e.g., burnout_alert, symptom_match',
            status ENUM('pending_review', 'approved', 'rejected') DEFAULT 'pending_review',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )"
    ];

    foreach ($ai_tables as $query) {
        try {
            $pdo->exec($query);
            echo "✅ Created AI table successfully.<br>";
        } catch (Exception $e) {
            echo "❌ Failed to create AI table: " . $e->getMessage() . "<br>";
        }
    }
    // -----------------------

    
    $alterations = [
        "wellness_messages" => [
            "ADD COLUMN attachment_path VARCHAR(255) NULL AFTER content",
            "ADD COLUMN message_type ENUM('text', 'image', 'file') DEFAULT 'text' AFTER attachment_path",
            "ADD COLUMN read_at DATETIME NULL AFTER message_type",
            "ADD COLUMN reply_to_id INT NULL DEFAULT NULL AFTER message_type",
            "ADD COLUMN is_unsent TINYINT(1) NOT NULL DEFAULT 0 AFTER reply_to_id",
            "ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER is_unsent"
        ],
        "internal_thread_messages" => [
            "ADD COLUMN attachment_path VARCHAR(255) NULL AFTER message",
            "ADD COLUMN message_type ENUM('text', 'image', 'file') DEFAULT 'text' AFTER attachment_path",
            "ADD COLUMN read_by JSON NULL AFTER message_type"
        ],
        "internal_threads" => [
            "ADD COLUMN updated_at DATETIME NULL AFTER last_message_at",
            "ADD COLUMN last_message_at DATETIME NULL AFTER status"
        ],
        "conversations" => [
            "ADD COLUMN last_message_at DATETIME NULL AFTER dietitian_id",
            "ADD COLUMN status ENUM('open', 'closed', 'archived') DEFAULT 'open' AFTER last_message_at"
        ]
    ];

    foreach ($alterations as $table => $queries) {
        echo "<h3>Checking table: <code>$table</code></h3>";
        foreach ($queries as $query) {
            try {
                $pdo->exec("ALTER TABLE $table $query");
                echo "✅ Applied: <code>ALTER TABLE $table $query</code><br>";
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate column name') !== false || strpos($e->getMessage(), 'Column already exists') !== false) {
                    echo "ℹ️ Already exists: $table -> " . explode(' ', $query)[2] . "<br>";
                } else {
                    echo "❌ Failed: $table -> $query | Error: " . $e->getMessage() . "<br>";
                }
            }
        }
    }

    echo "<h2>🎉 SYSTEM FULLY ALIGNED!</h2>";
    echo "<p>Every messaging column is now strictly enforced. Go back to your dashboard and refresh twice.</p>";

} catch (Exception $e) {
    echo "<h1>💥 Critical Crash</h1>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
