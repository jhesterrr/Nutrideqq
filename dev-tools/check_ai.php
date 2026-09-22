<?php
require 'database.php';
$pdo = (new Database())->getConnection();
$stmt = $pdo->query('SELECT * FROM ai_burnout_logs');
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Logs:\n"; print_r($logs);
$stmt2 = $pdo->query('SELECT * FROM ai_extracted_symptoms');
$symp = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "Symptoms:\n"; print_r($symp);
?>
