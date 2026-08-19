<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_role'] !== 'staff') {
    exit('Unauthorized');
}

require_once '../database.php';
$database = new Database();
$pdo = $database->getConnection();

$client_id = $_GET['client_id'] ?? 0;
$staff_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT c.*, u.email, u.created_at as user_joined,
               (SELECT COUNT(*) FROM messages WHERE client_id = c.id AND sender_type = 'client') as total_messages,
               (SELECT MAX(created_at) FROM messages WHERE client_id = c.id) as last_activity
        FROM clients c
        JOIN users u ON c.user_id = u.id
        WHERE c.id = ? AND c.staff_id = ?
    ");
    $stmt->execute([$client_id, $staff_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($client) {
        echo '<div class="client-info">';
        echo '<div class="info-row">';
        echo '<div class="info-label">Name:</div>';
        echo '<div class="info-value">' . htmlspecialchars($client['name']) . '</div>';
        echo '</div>';
        
        echo '<div class="info-row">';
        echo '<div class="info-label">Email:</div>';
        echo '<div class="info-value">' . htmlspecialchars($client['email']) . '</div>';
        echo '</div>';
        
        echo '<div class="info-row">';
        echo '<div class="info-label">Total Messages:</div>';
        echo '<div class="info-value">' . $client['total_messages'] . '</div>';
        echo '</div>';
        
        echo '<div class="info-row">';
        echo '<div class="info-label">Last Activity:</div>';
        echo '<div class="info-value">' . ($client['last_activity'] ? date('M j, Y g:i A', strtotime($client['last_activity'])) : 'Never') . '</div>';
        echo '</div>';
        
        echo '<div class="info-row">';
        echo '<div class="info-label">Member Since:</div>';
        echo '<div class="info-value">' . date('M j, Y', strtotime($client['user_joined'])) . '</div>';
        echo '</div>';
        
        echo '<div class="modal-actions" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">';
        echo '<button class="btn btn-primary" onclick="location.href=\'user-management-staff.php?view=' . $client_id . '\'">';
        echo '<i class="fas fa-user-edit"></i> View Full Profile';
        echo '</button>';
        echo '</div>';
        
        echo '</div>';
        
        echo '<style>
            .client-info { padding: 20px; }
            .info-row { display: flex; margin-bottom: 15px; }
            .info-label { font-weight: 600; color: #333; width: 150px; }
            .info-value { color: #666; }
        </style>';
    } else {
        echo '<div class="modal-error">Client not found or not assigned to you.</div>';
    }
} catch (Exception $e) {
    echo '<div class="modal-error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>