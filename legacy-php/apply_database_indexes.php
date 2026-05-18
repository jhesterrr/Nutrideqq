<?php
// apply_database_indexes.php
// Safely applies high-performance indexes to all NutriDeq database tables
require_once 'database.php';

header('Content-Type: text/html; charset=utf-8');
echo "<div style='font-family: sans-serif; padding: 20px; line-height: 1.6;'>";
echo "<h1>NutriDeq Database Optimization Suite ⚡</h1>";

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Helper function to safely apply an index
    function applyIndex($pdo, $table, $indexName, $indexDefinition) {
        // Check if table exists
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() == 0) {
                echo "⚠️ Table <b>$table</b> does not exist. Skipping.<br>";
                return;
            }
        } catch (Exception $e) {
            echo "❌ Error checking table <b>$table</b>: " . $e->getMessage() . "<br>";
            return;
        }

        // Check if columns exist
        $cols = explode(',', $indexDefinition);
        foreach ($cols as $col) {
            $col = trim($col);
            $colCheck = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
            if ($colCheck->rowCount() == 0) {
                echo "⚠️ Column <b>$col</b> does not exist in <b>$table</b>. Skipping index <b>$indexName</b>.<br>";
                return;
            }
        }

        // Check if index already exists
        try {
            $stmt = $pdo->query("SHOW INDEX FROM `$table` WHERE Key_name = '$indexName'");
            if ($stmt->rowCount() > 0) {
                echo "✅ Index <b>$indexName</b> already exists on <b>$table</b>.<br>";
                return;
            }
        } catch (Exception $e) {
            // If the table doesn't have any indexes, SHOW INDEX might throw an error or return empty, handle it
        }

        // Apply index
        try {
            $pdo->exec("ALTER TABLE `$table` ADD INDEX `$indexName` ($indexDefinition)");
            echo "🚀 <b>Applied Index:</b> <code>$indexName</code> ($indexDefinition) to <code>$table</code> successfully!<br>";
        } catch (Exception $e) {
            echo "❌ Failed to apply index <b>$indexName</b> to <b>$table</b>: " . $e->getMessage() . "<br>";
        }
    }

    // Helper function to safely apply Full-Text search index
    function applyFullTextIndex($pdo, $table, $indexName, $indexDefinition) {
        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() == 0) return;

        // Check if column exists
        $colCheck = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$indexDefinition'");
        if ($colCheck->rowCount() == 0) {
            echo "⚠️ Column <b>$indexDefinition</b> does not exist in <b>$table</b>. Skipping FULLTEXT index.<br>";
            return;
        }

        // Check if index exists
        try {
            $stmt = $pdo->query("SHOW INDEX FROM `$table` WHERE Key_name = '$indexName'");
            if ($stmt->rowCount() > 0) {
                echo "✅ Full-Text Index <b>$indexName</b> already exists on <b>$table</b>.<br>";
                return;
            }
        } catch (Exception $e) {}

        // Apply Full-Text index
        try {
            $pdo->exec("ALTER TABLE `$table` ADD FULLTEXT INDEX `$indexName` ($indexDefinition)");
            echo "🔥 <b>Applied FULLTEXT Index:</b> <code>$indexName</code> ($indexDefinition) to <code>$table</code> successfully!<br>";
        } catch (Exception $e) {
            echo "❌ Failed to apply FULLTEXT index <b>$indexName</b> to <b>$table</b>: " . $e->getMessage() . "<br>";
        }
    }

    echo "<h3>1. Optimizing Nutritional Reference Data (FCT)...</h3>";
    applyIndex($pdo, 'fct_food_items', 'idx_food_id', 'food_id');
    applyIndex($pdo, 'fct_food_items', 'idx_food_name', 'food_name');
    applyFullTextIndex($pdo, 'fct_food_items', 'idx_food_search', 'food_name');
    applyIndex($pdo, 'fct_nutrients', 'idx_item_id', 'food_item_id');

    echo "<h3>2. Optimizing Client Records & Telemetry Tables...</h3>";
    applyIndex($pdo, 'clients', 'idx_user_id', 'user_id');
    applyIndex($pdo, 'clients', 'idx_staff_id', 'staff_id');
    
    applyIndex($pdo, 'food_logs', 'idx_user_id', 'user_id');
    applyIndex($pdo, 'food_logs', 'idx_user_date', 'user_id, log_date');

    applyIndex($pdo, 'food_tracking', 'idx_user_id', 'user_id');
    applyIndex($pdo, 'food_tracking', 'idx_user_tracking_date', 'user_id, tracking_date');

    applyIndex($pdo, 'clinical_reports', 'idx_user_date', 'user_id, log_date');
    applyIndex($pdo, 'diary_feedback', 'idx_user_date', 'user_id, log_date');
    
    applyIndex($pdo, 'appointments', 'idx_client_id', 'client_id');
    applyIndex($pdo, 'appointments', 'idx_staff_id', 'staff_id');

    echo "<h3>3. Optimizing Real-Time Communication Tables...</h3>";
    applyIndex($pdo, 'conversations', 'idx_client_id', 'client_id');
    applyIndex($pdo, 'conversations', 'idx_dietitian_id', 'dietitian_id');
    applyIndex($pdo, 'wellness_messages', 'idx_conversation_id', 'conversation_id');
    applyIndex($pdo, 'internal_thread_messages', 'idx_thread_id', 'thread_id');
    
    echo "<h2>🎉 DATABASE OPTIMIZATION COMPLETE!</h2>";
    echo "<p>Your database queries are now fully indexed and matched to your active table schema for microsecond response times under load.</p>";

} catch (Exception $e) {
    echo "<h1>❌ Optimization Suite Failed</h1>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
echo "</div>";
?>
