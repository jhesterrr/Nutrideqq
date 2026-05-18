<?php
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
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    header("Location: login-logout/NutriDeqN-Login.php");
    exit();
}

$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];
$user_role = $_SESSION['user_role'];
$admin_id = $_SESSION['user_id'];

require_once 'navigation.php';
$nav_links_array = getNavigationLinks($user_role, 'admin-delete-history.php');

function getInitials($name)
{
    $names = explode(' ', $name);
    $initials = '';
    foreach ($names as $n) {
        $initials .= strtoupper($n[0]);
    }
    return substr($initials, 0, 2);
}
$user_initials = getInitials($user_name);

require_once 'database.php';
require_once 'database_helper.php';
$database = new Database();
$conn = $database->getConnection();
$dbHelper = new DatabaseHelper($conn);

$error = '';
$success = '';
$active_tab = $_GET['tab'] ?? 'users';

// Handle restore actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'restore_user':
                    $deleted_user_id = $_POST['deleted_user_id'];
                    $new_user_id = $dbHelper->restoreUser($deleted_user_id, $admin_id);
                    $success = "User restored successfully with new ID: #$new_user_id";
                    break;

                case 'restore_client':
                    $deleted_client_id = $_POST['deleted_client_id'];
                    $new_client_id = $dbHelper->restoreClient($deleted_client_id, $admin_id);
                    $success = "Client restored successfully with new ID: #$new_client_id";
                    break;

                case 'permanently_delete_user':
                    $deleted_user_id = $_POST['deleted_user_id'];
                    $dbHelper->permanentlyDeleteUser($deleted_user_id);
                    $success = "User permanently deleted from history";
                    break;

                case 'permanently_delete_client':
                    $deleted_client_id = $_POST['deleted_client_id'];
                    $check = $conn->prepare("SELECT deleted_at FROM deleted_clients WHERE id = ?");
                    $check->execute([$deleted_client_id]);
                    $row = $check->fetch();
                    if (!$row || empty($row['deleted_at'])) {
                        $error = "Permanent delete unavailable: missing deletion date";
                        break;
                    }
                    $deleted_ts = strtotime($row['deleted_at']);
                    $diff_days = floor((time() - $deleted_ts) / 86400);
                    if ($diff_days < 10) {
                        $error = "Permanent delete allowed only after 10 days";
                        break;
                    }
                    $dbHelper->permanentlyDeleteClient($deleted_client_id);
                    $success = "Client permanently deleted from history";
                    break;
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get deleted records
$deleted_users = $dbHelper->getDeletedUsers();
$deleted_clients = $dbHelper->getDeletedClients();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="scripts/theme-toggle.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriDeq - Delete History</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="css/logout-modal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/responsive.css">
    <!-- Platform Specific Styles -->
    <link rel="stylesheet" href="css/desktop-style.css" media="all and (min-width: 1025px)">
    <link rel="stylesheet" href="css/mobile-style.css" media="all and (max-width: 1024px)">
    <script src="scripts/dashboard.js" defer></script>
    <style>
        .tab-navigation {
            display: flex;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
        }

        .tab-btn {
            padding: 1rem 2rem;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            font-size: 1rem;
            color: var(--gray);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .tab-btn:hover {
            color: var(--primary-color);
        }

        .tab-btn.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            font-weight: 600;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .restore-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
        }

        .permanent-delete-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-deleted {
            background-color: #ffcc00;
            color: #856404;
        }

        .status-restored {
            background-color: #28a745;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--light-gray);
        }

        .delete-info {
            display: flex;
            gap: 10px;
            font-size: 0.85rem;
            color: var(--gray);
        }

        .deleted-by {
            color: var(--primary-color);
        }

        .restored-by {
            color: #28a745;
        }
    </style>
    <link rel="stylesheet" href="css/premium-revamp.css">
</head>

<body>
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">

            <!-- Main Content -->
            <div class="main-content">
                <div class="header">
                    <div class="page-title">
                        <h1>Delete History</h1>
                        <p>View and restore deleted users and clients</p>
                    </div>
                </div>

                <!-- Messages -->
                <?php if (!empty($success)): ?>
                    <div class="success-message">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="error-message">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Tab Navigation -->
                <div class="tab-navigation">
                    <button class="tab-btn <?php echo $active_tab === 'users' ? 'active' : ''; ?>"
                        onclick="switchTab('users')">
                        <i class="fas fa-users"></i> Deleted Users
                        <span class="badge"><?php echo count($deleted_users); ?></span>
                    </button>
                    <button class="tab-btn <?php echo $active_tab === 'clients' ? 'active' : ''; ?>"
                        onclick="switchTab('clients')">
                        <i class="fas fa-user-injured"></i> Deleted Clients
                        <span class="badge"><?php echo count($deleted_clients); ?></span>
                    </button>
                </div>

                <!-- Deleted Users Tab -->
                <div id="users-tab" class="tab-content <?php echo $active_tab === 'users' ? 'active' : ''; ?>">
                    <div class="management-section">
                        <div class="section-header">
                            <h2><i class="fas fa-user-slash"></i> Deleted User Accounts</h2>
                        </div>

                        <?php if (empty($deleted_users)): ?>
                            <div class="empty-state">
                                <i class="fas fa-trash-alt"></i>
                                <h3>No deleted users found</h3>
                                <p>Deleted user accounts will appear here</p>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="user-table">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Deleted Info</th>
                                            <th>Restored Info</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($deleted_users as $user): ?>
                                            <tr>
                                                <td>
                                                    <div class="user-info-cell">
                                                        <div class="user-avatar-small">
                                                            <?php echo getInitials($user['name']); ?>
                                                        </div>
                                                        <div class="user-details">
                                                            <div class="user-name">
                                                                <?php echo htmlspecialchars($user['name']); ?>
                                                            </div>
                                                            <div class="user-email">
                                                                <?php echo htmlspecialchars($user['email']); ?>
                                                            </div>
                                                            <div class="user-id">Original ID:
                                                                #<?php echo $user['original_id']; ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($user['is_restored']): ?>
                                                        <span class="status-badge status-restored">Restored</span>
                                                    <?php else: ?>
                                                        <span class="status-badge status-deleted">Deleted</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="delete-info">
                                                        <div>
                                                            <strong>By:</strong>
                                                            <span
                                                                class="deleted-by"><?php echo $user['deleted_by_name'] ?? 'System'; ?></span>
                                                        </div>
                                                        <div>
                                                            <strong>On:</strong>
                                                            <?php echo date('M j, Y H:i', strtotime($user['deleted_at'])); ?>
                                                        </div>
                                                    </div>
                                                    <?php if ($user['deletion_reason']): ?>
                                                        <div><strong>Reason:</strong>
                                                            <?php echo htmlspecialchars($user['deletion_reason']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($user['is_restored']): ?>
                                                        <div class="delete-info">
                                                            <div>
                                                                <strong>By:</strong>
                                                                <span
                                                                    class="restored-by"><?php echo $user['restored_by_name'] ?? 'System'; ?></span>
                                                            </div>
                                                            <div>
                                                                <strong>On:</strong>
                                                                <?php echo date('M j, Y H:i', strtotime($user['restored_at'])); ?>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <em>Not restored</em>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!$user['is_restored']): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="restore_user">
                                                            <input type="hidden" name="deleted_user_id"
                                                                value="<?php echo $user['id']; ?>">
                                                            <button type="submit" class="restore-btn"
                                                                onclick="return confirm('Restore this user?')">
                                                                <i class="fas fa-undo"></i> Restore
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="permanently_delete_user">
                                                        <input type="hidden" name="deleted_user_id"
                                                            value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="permanent-delete-btn"
                                                            onclick="return confirm('Permanently delete this record? This cannot be undone.')">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Deleted Clients Tab -->
                <div id="clients-tab" class="tab-content <?php echo $active_tab === 'clients' ? 'active' : ''; ?>">
                    <div class="management-section">
                        <div class="section-header">
                            <h2><i class="fas fa-user-injured"></i> Deleted Client Accounts</h2>
                        </div>

                        <?php if (empty($deleted_clients)): ?>
                            <div class="empty-state">
                                <i class="fas fa-trash-alt"></i>
                                <h3>No deleted clients found</h3>
                                <p>Deleted client accounts will appear here</p>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="user-table">
                                    <thead>
                                        <tr>
                                            <th>Client</th>
                                            <th>Contact</th>
                                            <th>Staff</th>
                                            <th>Deleted Info</th>
                                            <th>Restored Info</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($deleted_clients as $client): ?>
                                            <tr>
                                                <td>
                                                    <div class="user-info-cell">
                                                        <div class="user-avatar-small">
                                                            <?php echo getInitials($client['name']); ?>
                                                        </div>
                                                        <div class="user-details">
                                                            <div class="user-name">
                                                                <?php echo htmlspecialchars($client['name']); ?>
                                                            </div>
                                                            <div class="user-email">
                                                                <?php echo htmlspecialchars($client['email']); ?>
                                                            </div>
                                                            <div class="user-id">Original ID:
                                                                #<?php echo $client['original_id']; ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div><?php echo $client['phone'] ?: 'No phone'; ?></div>
                                                    <div style="font-size: 0.8rem;">Age: <?php echo $client['age'] ?? 'N/A'; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($client['staff_name']): ?>
                                                        <div><?php echo htmlspecialchars($client['staff_name']); ?></div>
                                                    <?php else: ?>
                                                        <em>No staff assigned</em>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="delete-info">
                                                        <div>
                                                            <strong>By:</strong>
                                                            <span
                                                                class="deleted-by"><?php echo $client['deleted_by_name'] ?? 'System'; ?></span>
                                                        </div>
                                                        <div>
                                                            <strong>On:</strong>
                                                            <?php echo date('M j, Y H:i', strtotime($client['deleted_at'])); ?>
                                                        </div>
                                                    </div>
                                                    <?php if ($client['deletion_reason']): ?>
                                                        <div><strong>Reason:</strong>
                                                            <?php echo htmlspecialchars($client['deletion_reason']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($client['is_restored']): ?>
                                                        <div class="delete-info">
                                                            <div>
                                                                <strong>By:</strong>
                                                                <span
                                                                    class="restored-by"><?php echo $client['restored_by_name'] ?? 'System'; ?></span>
                                                            </div>
                                                            <div>
                                                                <strong>On:</strong>
                                                                <?php echo date('M j, Y H:i', strtotime($client['restored_at'])); ?>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <em>Not restored</em>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!$client['is_restored']): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="restore_client">
                                                            <input type="hidden" name="deleted_client_id"
                                                                value="<?php echo $client['id']; ?>">
                                                            <button type="submit" class="restore-btn"
                                                                onclick="return confirm('Restore this client?')">
                                                                <i class="fas fa-undo"></i> Restore
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <?php $deleted_ts = !empty($client['deleted_at']) ? strtotime($client['deleted_at']) : null;
                                                    $age_days = $deleted_ts ? floor((time() - $deleted_ts) / 86400) : 0;
                                                    $can_perm_delete = $deleted_ts && $age_days >= 10; ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="permanently_delete_client">
                                                        <input type="hidden" name="deleted_client_id"
                                                            value="<?php echo $client['id']; ?>">
                                                        <button type="submit" class="permanent-delete-btn"
                                                            data-deleted-at="<?php echo $client['deleted_at']; ?>"
                                                            data-deleted-at-ts="<?php echo $deleted_ts ? $deleted_ts : ''; ?>"
                                                            data-min-days="10" <?php echo $can_perm_delete ? '' : 'disabled'; ?>
                                                            onclick="return confirm('Permanently delete this record? This cannot be undone.')">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                        <span class="perm-countdown"
                                                            data-deleted-at="<?php echo $client['deleted_at']; ?>"
                                                            data-deleted-at-ts="<?php echo $deleted_ts ? $deleted_ts : ''; ?>"
                                                            data-min-days="10" style="margin-left:8px;color:#888"></span>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>



            <script>
                function switchTab(tabName) {
                    // Update URL without page reload
                    window.history.pushState({}, '', '?tab=' + tabName);

                    // Update active tab button
                    document.querySelectorAll('.tab-btn').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    event.target.classList.add('active');

                    // Show selected tab content
                    document.querySelectorAll('.tab-content').forEach(content => {
                        content.classList.remove('active');
                    });
                    document.getElementById(tabName + '-tab').classList.add('active');
                }

                document.addEventListener('DOMContentLoaded', function () {


                    function formatTimeLeft(seconds) {
                        const d = Math.floor(seconds / 86400);
                        seconds -= d * 86400;
                        const h = Math.floor(seconds / 3600);
                        seconds -= h * 3600;
                        const m = Math.floor(seconds / 60);
                        const s = Math.floor(seconds - m * 60);
                        return `${d}d ${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
                    }
                    function setupPermDeleteCountdown() {
                        const buttons = document.querySelectorAll('.permanent-delete-btn');
                        buttons.forEach(btn => {
                            const tsAttr = btn.getAttribute('data-deleted-at-ts');
                            const deletedAtStr = btn.getAttribute('data-deleted-at');
                            const minDays = parseInt(btn.getAttribute('data-min-days') || '10', 10);
                            const countdownSpan = btn.parentElement.querySelector('.perm-countdown');
                            if (!countdownSpan) return;
                            let deletedMs = null;
                            if (tsAttr && /^\d+$/.test(tsAttr)) { deletedMs = parseInt(tsAttr, 10) * 1000; }
                            else if (deletedAtStr) { deletedMs = Date.parse(deletedAtStr.replace(' ', 'T')); }
                            if (!deletedMs || Number.isNaN(deletedMs)) return;
                            function update() {
                                const elapsed = Date.now() - deletedMs;
                                const minMs = minDays * 86400 * 1000;
                                const leftMs = Math.max(0, minMs - elapsed);
                                const leftSec = Math.floor(leftMs / 1000);
                                if (leftMs <= 0) { btn.removeAttribute('disabled'); countdownSpan.textContent = ''; return true; }
                                else { btn.setAttribute('disabled', 'disabled'); countdownSpan.textContent = `Available in ${formatTimeLeft(leftSec)}`; return false; }
                            }
                            update();
                            const iv = setInterval(() => { if (update()) clearInterval(iv); }, 1000);
                        });
                    }
                    setupPermDeleteCountdown();
                });
            </script>
        </div>
    </div>
</body>

</html>
