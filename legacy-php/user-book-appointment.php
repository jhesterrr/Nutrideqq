<?php
require_once __DIR__ . '/session_bootstrap.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login-logout/NutriDeqN-Login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];
$user_role = $_SESSION['user_role'];

require_once 'navigation.php';
$nav_links_array = getNavigationLinks($user_role, 'user-book-appointment.php');

require_once 'database.php';
$database = new Database();
$pdo = $database->getConnection();

$error = '';
$success = '';
$client = null;
$assigned_staff_id = null;
$staff_list = [];

function getInitials($name)
{
    $p = explode(' ', $name);
    $i = '';
    foreach ($p as $x) {
        if ($x !== '')
            $i .= strtoupper($x[0]);
    }
    return substr($i, 0, 2);
}
$user_initials = getInitials($user_name);

try {
    $client_stmt = $pdo->prepare("SELECT * FROM clients WHERE user_id = ? LIMIT 1");
    $client_stmt->execute([$user_id]);
    $client = $client_stmt->fetch();
    if ($client) {
        $assigned_staff_id = $client['staff_id'] ?? null;
    }
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
}

try {
    $staff_stmt = $pdo->prepare("SELECT id, name FROM users WHERE role='staff' AND status='active' ORDER BY name");
    $staff_stmt->execute();
    $staff_list = $staff_stmt->fetchAll();
} catch (Exception $e) {
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staff_id = intval($_POST['staff_id'] ?? 0);
    $appt_date = trim($_POST['appointment_date'] ?? '');
    $appt_time = trim($_POST['appointment_time'] ?? '');
    $appt_type = trim($_POST['appointment_type'] ?? 'consultation');
    $dt = null;

    if (!$client || !$client['id']) {
        $error = 'Client profile not found.';
    } elseif ($staff_id <= 0) {
        $error = 'Please select a staff member.';
    } elseif ($appt_date === '' || $appt_time === '') {
        $error = 'Please select date and time.';
    } else {
        $dt = date('Y-m-d H:i:s', strtotime($appt_date . ' ' . $appt_time));
        try {
            $conf = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE staff_id = ? AND appointment_date = ? AND status = 'scheduled'");
            $conf->execute([$staff_id, $dt]);
            if ((int) $conf->fetchColumn() > 0) {
                $error = 'Selected time slot is not available. Choose another time.';
            } else {
                $ins = $pdo->prepare("INSERT INTO appointments (client_id, staff_id, appointment_date, type, status, created_at) VALUES (?, ?, ?, ?, 'scheduled', NOW())");
                $ins->execute([$client['id'], $staff_id, $dt, $appt_type]);
                $success = 'Appointment booked successfully.';
            }
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

$upcoming = [];
try {
    if ($client && $client['id']) {
        $stmt = $pdo->prepare("SELECT a.*, u.name as staff_name FROM appointments a LEFT JOIN users u ON a.staff_id = u.id WHERE a.client_id = ? AND a.appointment_date >= CURDATE() AND a.status='scheduled' ORDER BY a.appointment_date ASC");
        $stmt->execute([$client['id']]);
        $upcoming = $stmt->fetchAll();
    }
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="scripts/theme-toggle.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>NutriDeq - Book Appointment</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/logout-modal.css">
    <link rel="stylesheet" href="css/premium-revamp.css">
</head>

<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <div class="page-title">
                <h1>Book Appointment</h1>
                <p>Schedule a session with your dietician</p>
            </div>
            <div class="header-actions">
                <div class="notification-btn"><i class="fas fa-bell"></i>
                    <div class="notification-badge"></div>
                </div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="success-message"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="management-section">
            <div class="section-header">
                <h2><i class="fas fa-calendar-plus"></i> New Appointment</h2>
            </div>
            <form method="POST">
                <div class="form-group"><label for="staff_id">Staff</label><select id="staff_id" name="staff_id"
                        class="form-control" required>
                        <?php foreach ($staff_list as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($assigned_staff_id == $s['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="client-info-grid">
                    <div class="form-group"><label for="appointment_date">Date</label><input type="date"
                            id="appointment_date" name="appointment_date" class="form-control" required></div>
                    <div class="form-group"><label for="appointment_time">Time</label><input type="time"
                            id="appointment_time" name="appointment_time" class="form-control" required></div>
                </div>
                <div class="form-group"><label for="appointment_type">Type</label><select id="appointment_type"
                        name="appointment_type" class="form-control">
                        <option value="consultation">Consultation</option>
                        <option value="follow-up">Follow-up</option>
                        <option value="assessment">Assessment</option>
                    </select></div>
                <div class="form-actions"><button type="submit" class="btn btn-primary"><i
                            class="fas fa-calendar-check"></i> Book</button></div>
            </form>
        </div>

        <div class="management-section">
            <div class="section-header">
                <h2><i class="fas fa-list"></i> Upcoming Appointments</h2>
            </div>
            <div class="table-container">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Type</th>
                            <th>Staff</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming as $a): ?>
                            <tr>
                                <td><?= date('M j, Y', strtotime($a['appointment_date'])) ?></td>
                                <td><?= date('g:i A', strtotime($a['appointment_date'])) ?></td>
                                <td><?= htmlspecialchars($a['type']) ?></td>
                                <td><?= htmlspecialchars($a['staff_name'] ?? 'Unknown') ?></td>
                                <td><span class="appointment-type-badge"><?= htmlspecialchars($a['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($upcoming)): ?>
                            <tr>
                                <td colspan="5">No upcoming appointments.</td>
                            </tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="scripts/dashboard.js"></script>
</body>

</html>

