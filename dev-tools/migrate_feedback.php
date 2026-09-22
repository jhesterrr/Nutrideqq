<?php
require_once 'database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Check if column exists
    $check = $conn->query("SHOW COLUMNS FROM diary_feedback LIKE 'client_user_id'");
    if ($check->rowCount() > 0) {
        $sql = "ALTER TABLE diary_feedback CHANGE client_user_id user_id INT NOT NULL";
        $conn->exec($sql);
        echo "Successfully renamed 'client_user_id' to 'user_id' in 'diary_feedback' table.";
    } else {
        echo "Column 'client_user_id' already renamed or does not exist.";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
