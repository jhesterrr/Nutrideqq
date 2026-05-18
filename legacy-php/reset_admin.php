<?php
require_once 'database.php';
$db = (new Database())->getConnection();
$hashed_password = password_hash('nutrideq123', PASSWORD_DEFAULT);
$db->query("UPDATE users SET password = '$hashed_password' WHERE email = 'admin@nutrideq.com'");
echo "Admin password updated to 'nutrideq123'\n";
