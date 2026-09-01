<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

require_once '../database.php';

// Check staff/admin login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['user_role'] !== 'staff' && $_SESSION['user_role'] !== 'admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$user_id = $_POST['user_id'] ?? null;
$log_date = $_POST['log_date'] ?? null;
$patient_name = $_POST['patient_name'] ?? '';
$dietician_name = $_POST['dietician_name'] ?? '';
$report_content = $_POST['report_content'] ?? '';
$staff_id = $_SESSION['user_id'];

if (!$user_id || !$log_date || !$report_content) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    AuditLogger::init($conn);

    // Create table if not exists
    $createTableSql = "CREATE TABLE IF NOT EXISTS clinical_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        staff_id INT NOT NULL,
        log_date DATE NOT NULL,
        dietician_name VARCHAR(255),
        patient_name VARCHAR(255),
        report_id VARCHAR(50),
        report_content LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (staff_id),
        INDEX (log_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $conn->exec($createTableSql);

    // Generate a unique report ID if it doesn't exist for this log
    $patientName = class_exists('Encryption') ? Encryption::encryptIfExists($patient_name) : $patient_name;
    $dieticianName = class_exists('Encryption') ? Encryption::encryptIfExists($dietician_name) : $dietician_name;
    $encryptedContent = class_exists('Encryption') ? Encryption::encryptIfExists($report_content) : $report_content;

    // Generate a unique report ID if it doesn't exist for this log
    $report_id = 'REP-' . strtoupper(bin2hex(random_bytes(4)));

    // Check if a report already exists for this user and date
    $checkSql = "SELECT id FROM clinical_reports WHERE user_id = ? AND log_date = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([$user_id, $log_date]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        $sql = "UPDATE clinical_reports SET 
                staff_id = ?, 
                dietician_name = ?, 
                patient_name = ?, 
                report_content = ?,
                created_at = CURRENT_TIMESTAMP
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$staff_id, $dieticianName, $patientName, $encryptedContent, $existing['id']]);
    } else {
        $sql = "INSERT INTO clinical_reports (user_id, staff_id, log_date, dietician_name, patient_name, report_id, report_content) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id, $staff_id, $log_date, $dieticianName, $patientName, $report_id, $encryptedContent]);
    }

    AuditLogger::log('clinical_report_save', 'clinical_reports', $report_id, $user_id, $_SESSION['user_role'] ?? 'staff');

    echo json_encode(['success' => true, 'message' => 'Report saved successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
