<?php
ini_set('display_errors', 0);
if (strpos(strtolower(PHP_OS), 'win') !== false) {
    $session_path = 'c:\\xampp\\htdocs\\nutrideq\\api\\sessions';
    if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
    session_save_path($session_path);
} else {
    $session_path = '/tmp/sessions';
    if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
    session_save_path($session_path);
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once '../database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['user_email'] ?? '';
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

$weight = isset($data['weight']) ? (float)$data['weight'] : null;
$height = isset($data['height']) ? (float)$data['height'] : null;

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Create BMI History table if it doesn't exist
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS client_bmi_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            weight DECIMAL(5,2),
            height DECIMAL(5,2),
            bmi DECIMAL(5,1),
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $e) {}

    // Update Clients table
    $upd = $pdo->prepare("UPDATE clients SET weight = ?, height = ?, updated_at = NOW() WHERE user_id = ?");
    $upd->execute([$weight, $height, $user_id]);
    
    if ($upd->rowCount() === 0 && !empty($user_email)) {
        $upd = $pdo->prepare("UPDATE clients SET weight = ?, height = ?, updated_at = NOW(), user_id = ? WHERE email = ? AND (user_id IS NULL OR user_id = 0)");
        $upd->execute([$weight, $height, $user_id, $user_email]);
        
        if ($upd->rowCount() === 0) {
            $user_name = $_SESSION['user_name'] ?? 'User';
            $ins = $pdo->prepare("INSERT INTO clients (user_id, name, email, weight, height, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())");
            $ins->execute([$user_id, $user_name, $user_email, $weight, $height]);
        }
    }

    $bmi = 0;
    $bmi_status = 'Unknown';
    if ($weight > 0 && $height > 0) {
        $height_m = $height / 100;
        $bmi = round($weight / ($height_m * $height_m), 1);
        
        $hist = $pdo->prepare("INSERT INTO client_bmi_history (user_id, weight, height, bmi) VALUES (?, ?, ?, ?)");
        $hist->execute([$user_id, $weight, $height, $bmi]);

        if ($bmi < 18.5) $bmi_status = 'Underweight';
        elseif ($bmi < 25) $bmi_status = 'Normal';
        elseif ($bmi < 30) $bmi_status = 'Overweight';
        else $bmi_status = 'Obese';
    }

    // Get updated history
    $hist_stmt = $pdo->prepare("SELECT bmi, DATE_FORMAT(recorded_at, '%b %d') as date_label FROM client_bmi_history WHERE user_id = ? ORDER BY recorded_at ASC LIMIT 15");
    $hist_stmt->execute([$user_id]);
    $history = $hist_stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'new_bmi' => [
            'value' => $bmi,
            'status' => $bmi_status,
            'weight' => $weight,
            'height' => $height
        ],
        'updated_history' => $history
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
