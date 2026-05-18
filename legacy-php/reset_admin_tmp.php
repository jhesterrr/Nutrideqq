<?php
require_once 'database.php';
$database = new Database();
$db = $database->getConnection();

$password = 'admin123';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $db->prepare("UPDATE users SET password = ? WHERE email = 'admin@nutrideq.com'");
if ($stmt->execute([$hashed_password])) {
    echo "Password for admin@nutrideq.com updated to 'admin123'";
} else {
    echo "Failed to update password";
}
?>
