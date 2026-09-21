<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

header('Content-Type: application/json');

require_once '../database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$log_date = date('Y-m-d');
$action = $_REQUEST['action'] ?? 'get';

// Mutating requests sent as POST must carry the CSRF token.
// NOTE: the dashboard currently also triggers add/sub via GET links
// (scripts/dashboard-store.js); until that moves to POST, GET mutations
// remain allowed for compatibility.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT INTO hydration_tracking (user_id, glasses, tracking_date) VALUES (?, 1, ?) 
                               ON DUPLICATE KEY UPDATE glasses = glasses + 1");
        $stmt->execute([$user_id, $log_date]);
    } elseif ($action === 'remove') {
        $stmt = $pdo->prepare("UPDATE hydration_tracking SET glasses = GREATEST(0, glasses - 1) WHERE user_id = ? AND tracking_date = ?");
        $stmt->execute([$user_id, $log_date]);
    }

    // Always return updated count
    $stmt = $pdo->prepare("SELECT glasses FROM hydration_tracking WHERE user_id = ? AND tracking_date = ?");
    $stmt->execute([$user_id, $log_date]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $glasses = (int)($row ? $row['glasses'] : 0);

    echo json_encode(['success' => true, 'glasses' => $glasses, 'target' => 8]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
