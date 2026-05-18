<?php
require_once 'database.php';
$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    file_put_contents('db_check_result.txt', "Database connection failed.");
    die("Connection failed.");
}

$out = "Database Debug Info - Time: " . date('Y-m-d H:i:s') . "\n";
try {
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $out .= "Tables in database: " . implode(", ", $tables) . "\n\n";

    $targetTables = ['fct_food_items', 'fct_nutrients'];
    foreach ($targetTables as $table) {
        if (in_array($table, $tables)) {
            $out .= "Structure of table: $table\n";
            $cols = $conn->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $c) {
                $out .= "  - " . $c['Field'] . " | " . $c['Type'] . " | Null: " . $c['Null'] . " | Key: " . $c['Key'] . "\n";
            }

            // Check for data
            $count = $conn->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            $out .= "  Total Rows: $count\n\n";
        } else {
            $out .= "Table $table NOT FOUND\n\n";
        }
    }
} catch (Exception $e) {
    $out .= "ERROR: " . $e->getMessage() . "\n";
}

file_put_contents('db_check_result.txt', $out);
echo "Diagnostic complete. Result saved to db_check_result.txt. Please inform the AI assistant.";
?>