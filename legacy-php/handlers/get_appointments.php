<?php
require_once '../database.php';
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

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'staff') {
    die('Unauthorized');
}

$database = new Database();
$pdo = $database->getConnection();
$staff_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            c.name as client_name,
            a.appointment_date,
            a.type as appointment_type,
            a.status,
            a.notes
        FROM appointments a
        JOIN clients c ON a.client_id = c.id
        WHERE c.staff_id = ? 
        AND a.appointment_date >= CURDATE()
        AND a.status = 'scheduled'
        ORDER BY a.appointment_date ASC
    ");
    $stmt->execute([$staff_id]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($appointments) > 0) {
        echo '<div class="modal-appointments-list">';
        foreach ($appointments as $appointment) {
            echo '
            <div class="modal-appointment-item">
                <div class="appointment-avatar">
                    ' . strtoupper(substr($appointment['client_name'], 0, 2)) . '
                </div>
                <div class="appointment-details">
                    <div class="appointment-client">' . htmlspecialchars($appointment['client_name']) . '</div>
                    <div class="appointment-time">
                        <i class="fas fa-clock"></i>
                        ' . date('M j, g:i A', strtotime($appointment['appointment_date'])) . '
                    </div>
                    <div class="appointment-type">
                        <span class="appointment-type-badge">' . ucfirst($appointment['appointment_type']) . '</span>
                    </div>
                    ' . ($appointment['notes'] ? '<div class="appointment-notes">' . htmlspecialchars($appointment['notes']) . '</div>' : '') . '
                </div>
            </div>';
        }
        echo '</div>';
    } else {
        echo '<div class="no-data-message">
                <i class="fas fa-calendar-times"></i>
                <h3>No Appointments Found</h3>
                <p>You have no scheduled appointments.</p>
              </div>';
    }
} catch (Exception $e) {
    echo '<div class="modal-error">
            <i class="fas fa-exclamation-triangle"></i>
            <p>Error loading appointments.</p>
          </div>';
}
?>