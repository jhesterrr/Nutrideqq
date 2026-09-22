<?php
require_once 'database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Check roles
    $stmt = $conn->query("SELECT DISTINCT role FROM users");
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Current roles in DB: " . implode(", ", $roles) . "\n";

    // Migrate regular to user
    $migrate = $conn->prepare("UPDATE users SET role = 'user' WHERE role = 'regular'");
    $migrate->execute();
    $count = $migrate->rowCount();
    echo "Successfully migrated $count users from 'regular' to 'user'.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
