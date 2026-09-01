<?php
require_once __DIR__ . '/session_bootstrap.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    header("Location: login-logout/NutriDeqN-Login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$deleted_by = 'admin';
$deleted_by_id = $admin_id;

$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];
$user_role = $_SESSION['user_role'];

// Include navigation helper
require_once 'navigation.php';

// Get navigation links for current page
$nav_links_array = getNavigationLinks($user_role, 'admin-client-management.php');

function getInitials($name)
{
    $names = explode(' ', trim($name ?? ''));
    $initials = '';
    foreach ($names as $n) {
        if ($n !== '') {
            $initials .= strtoupper($n[0]);
        }
    }
    return !empty($initials) ? substr($initials, 0, 2) : 'U';
}
$user_initials = getInitials($user_name);

// Database connection
require_once 'database.php';
$database = new Database();
$conn = $database->getConnection();

$clients = [];
$deleted_clients = [];
$stats = [
    'total_clients' => 0,
    'active_clients' => 0,
    'deleted_clients' => 0
];
$error = '';

try {
    $purge_stmt = $conn->prepare("DELETE FROM deleted_users WHERE deleted_at <= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $purge_stmt->execute();
} catch (PDOException $e) {
}

// Handle form actions for inline operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete_client':
                $user_id = $_POST['client_id'];

                try {
                    $conn->beginTransaction();

                    // Get user data before deletion
                    $select_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                    $select_stmt->execute([$user_id]);
                    $user = $select_stmt->fetch();

                    if ($user) {
                        // Get admin's name for tracking
                        $admin_stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
                        $admin_stmt->execute([$admin_id]);
                        $admin = $admin_stmt->fetch();
                        $admin_name = $admin['name'] ?? 'Admin';

                        // Insert into deleted_users table
                        $insert_stmt = $conn->prepare("
                            INSERT INTO deleted_users (
                                original_id, deleted_by, deleted_by_user_id, deleted_by_name,
                                name, email, password, role, status,
                                created_at, updated_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");

                        $insert_stmt->execute([
                            $user['id'],
                            $deleted_by, // 'admin'
                            $deleted_by_id, // $admin_id
                            $admin_name,
                            $user['name'],
                            $user['email'],
                            $user['password'],
                            $user['role'],
                            $user['status'],
                            $user['created_at'],
                            $user['updated_at']
                        ]);

                        // Delete from users table
                        $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                        $delete_stmt->execute([$user_id]);

                        $conn->commit();
                        $_SESSION['success'] = "Client moved to delete history successfully";
                    } else {
                        $error = "Client not found";
                    }

                    header("Location: admin-client-management.php");
                    exit();
                } catch (PDOException $e) {
                    $conn->rollBack();
                    $error = "Database error: " . $e->getMessage();
                }
                break;

            case 'restore_client':
                $deleted_user_id = $_POST['deleted_client_id'];

                try {
                    $conn->beginTransaction();

                    // Get deleted user data
                    $select_stmt = $conn->prepare("SELECT * FROM deleted_users WHERE id = ?");
                    $select_stmt->execute([$deleted_user_id]);
                    $user = $select_stmt->fetch();

                    if ($user) {
                        // Check if user with same email already exists
                        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                        $check_stmt->execute([$user['email']]);

                        if ($check_stmt->fetch()) {
                            $_SESSION['error'] = "A client with this email already exists. Cannot restore.";
                        } else {
                            // Insert back into users table
                            $insert_stmt = $conn->prepare("
                                INSERT INTO users (
                                    id, name, email, password, role, status,
                                    created_at, updated_at, restored_at
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                            ");

                            $insert_stmt->execute([
                                $user['original_id'],
                                $user['name'],
                                $user['email'],
                                $user['password'],
                                $user['role'],
                                $user['status'],
                                $user['created_at'],
                                $user['updated_at']
                            ]);

                            // Remove from deleted_users table
                            $delete_stmt = $conn->prepare("DELETE FROM deleted_users WHERE id = ?");
                            $delete_stmt->execute([$deleted_user_id]);

                            $conn->commit();
                            $_SESSION['success'] = "Client restored successfully";
                        }
                    } else {
                        $_SESSION['error'] = "Client not found in delete history";
                    }

                    header("Location: admin-client-management.php?tab=delete_history");
                    exit();
                } catch (PDOException $e) {
                    $conn->rollBack();
                    $_SESSION['error'] = "Database error: " . $e->getMessage();
                    header("Location: admin-client-management.php?tab=delete_history");
                    exit();
                }
                break;

            case 'update_role':
                $user_id = $_POST['user_id'];
                $new_role = $_POST['role'];
                $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$new_role, $user_id]);
                $_SESSION['success'] = "User role updated successfully";
                header("Location: admin-staff-management.php");
                exit();
                break;

            case 'toggle_client_status':
                $user_id = $_POST['client_id'];
                $stmt = $conn->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?");
                $stmt->execute([$user_id]);
                $_SESSION['success'] = "Client status updated successfully";
                header("Location: admin-client-management.php");
                exit();
                break;

            case 'edit_user':
                $user_id = $_POST['user_id'];
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                $role = $_POST['role'] ?? 'user';
                $status = $_POST['status'];
                $new_password = $_POST['password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';

                try {
                    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
                    $check_stmt->execute([$email, $user_id]);
                    if ($check_stmt->fetch()) {
                        $_SESSION['error'] = "Email already exists";
                        header("Location: admin-client-management.php");
                        exit();
                    }

                    if (!in_array($role, ['user', 'staff', 'admin'])) {
                        $role = 'user';
                    }
                    if (!in_array($status, ['active', 'inactive'])) {
                        $status = 'active';
                    }

                    if (!empty($new_password)) {
                        if ($new_password !== $confirm_password) {
                            $_SESSION['error'] = "New password and confirm password do not match";
                            header("Location: admin-client-management.php");
                            exit();
                        }
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, status = ?, password = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$name, $email, $role, $status, $hashed_password, $user_id]);
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, status = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$name, $email, $role, $status, $user_id]);
                    }

                    $_SESSION['success'] = "Client updated successfully";
                    header("Location: admin-client-management.php");
                    exit();
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Database error: " . $e->getMessage();
                    header("Location: admin-client-management.php");
                    exit();
                }
                break;
            case 'permanent_delete_client':
                $deleted_user_id = $_POST['deleted_client_id'];
                try {
                    $stmt = $conn->prepare("SELECT deleted_at FROM deleted_users WHERE id = ?");
                    $stmt->execute([$deleted_user_id]);
                    $row = $stmt->fetch();
                    if (!$row) {
                        $_SESSION['error'] = "Client not found in delete history";
                        header("Location: admin-client-management.php?tab=delete_history");
                        exit();
                    }
                    $deleted_at = strtotime($row['deleted_at']);
                    $diff_days = floor((time() - $deleted_at) / 86400);
                    if ($diff_days < 10) {
                        $_SESSION['error'] = "Permanent delete allowed only after 10 days";
                        header("Location: admin-client-management.php?tab=delete_history");
                        exit();
                    }
                    $del = $conn->prepare("DELETE FROM deleted_users WHERE id = ?");
                    $del->execute([$deleted_user_id]);
                    $_SESSION['success'] = "Client permanently deleted";
                    header("Location: admin-client-management.php?tab=delete_history");
                    exit();
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Database error: " . $e->getMessage();
                    header("Location: admin-client-management.php?tab=delete_history");
                    exit();
                }
                break;
        }
    }
}

// Handle GET parameters for tabs
$current_tab = isset($_GET['tab']) ? preg_replace('/[^a-z0-9_-]/i', '', $_GET['tab']) : 'active_clients';
if (!in_array($current_tab, ['active_clients', 'delete_history'], true)) {
    $current_tab = 'active_clients';
}

try {
    // Get client statistics
    $stats_sql = "SELECT 
        COUNT(*) as total_clients,
        SUM(status = 'active') as active_clients
        FROM users WHERE role = 'regular'";
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->execute();
    $stats_data = $stats_stmt->fetch();
    $stats['total_clients'] = $stats_data['total_clients'] ?? 0;
    $stats['active_clients'] = $stats_data['active_clients'] ?? 0;

    // Get deleted clients count
    $deleted_stats_stmt = $conn->prepare("SELECT COUNT(*) as deleted_count FROM deleted_users WHERE role = 'regular'");
    $deleted_stats_stmt->execute();
    $deleted_stats = $deleted_stats_stmt->fetch();
    $stats['deleted_clients'] = $deleted_stats['deleted_count'] ?? 0;

    // Get all staff members for the dropdown
    $staff_sql = "SELECT id, name FROM users WHERE role = 'staff' AND status = 'active' ORDER BY name ASC";
    $staff_stmt = $conn->prepare($staff_sql);
    $staff_stmt->execute();
    $available_staff = $staff_stmt->fetchAll();

    // Get all active clients
    $users_sql = "SELECT u.*, c.staff_id, s.name as staff_name 
                  FROM users u 
                  LEFT JOIN clients c ON u.id = c.user_id 
                  LEFT JOIN users s ON c.staff_id = s.id 
                  WHERE u.role = 'regular' 
                  ORDER BY u.created_at DESC";
    $users_stmt = $conn->prepare($users_sql);
    $users_stmt->execute();
    $clients = $users_stmt->fetchAll();

    // Get all deleted clients
    $deleted_users_sql = "SELECT * FROM deleted_users WHERE role = 'regular' ORDER BY deleted_at DESC";
    $deleted_users_stmt = $conn->prepare($deleted_users_sql);
    $deleted_users_stmt->execute();
    $deleted_clients = $deleted_users_stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="scripts/theme-toggle.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>NutriDeq - Client Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/responsive.css">
    <link rel="stylesheet" href="css/logout-modal.css">
    <link rel="stylesheet" href="css/mobile-style.css">
    <link rel="stylesheet" href="css/premium-management.css">
    <script src="scripts/dashboard.js" defer></script>
    <link rel="stylesheet" href="css/premium-revamp.css">
</head>
<body>
<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
    <div class="page-container mgmt-page">
        <?php if(isset($_SESSION['success'])): ?><div class="mgmt-alert mgmt-alert-success"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
        <?php if(!empty($error)): ?><div class="mgmt-alert mgmt-alert-error"><i class="fas fa-exclamation-triangle"></i><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <div class="mgmt-hero">
            <div class="mgmt-hero-left">
                <div class="mgmt-hero-icon"><i class="fas fa-heartbeat"></i></div>
                <h1 class="mgmt-hero-title">Client Management</h1>
                <p class="mgmt-hero-subtitle">Oversee all registered NutriDeq clients and their health journeys</p>
            </div>
            <div class="mgmt-hero-right">
                <div class="mgmt-search"><i class="fas fa-search"></i><input type="text" placeholder="Search clients..." oninput="filterCards(this.value,'clientGrid')"></div>
            </div>
        </div>
        <div class="mgmt-stats">
            <div class="mgmt-stat stat-clients"><div class="mgmt-stat-icon"><i class="fas fa-users"></i></div><div class="mgmt-stat-info"><h4><?php echo $stats['total_clients']??0; ?></h4><p>Total Clients</p></div></div>
            <div class="mgmt-stat stat-active"><div class="mgmt-stat-icon"><i class="fas fa-heartbeat"></i></div><div class="mgmt-stat-info"><h4><?php echo $stats['active_clients']??0; ?></h4><p>Active</p></div></div>
            <div class="mgmt-stat stat-deleted"><div class="mgmt-stat-icon"><i class="fas fa-user-times"></i></div><div class="mgmt-stat-info"><h4><?php echo $stats['deleted_clients']??0; ?></h4><p>Deleted</p></div></div>
        </div>
        <div class="mgmt-tabs">
            <button class="mgmt-tab-btn <?php echo $current_tab==='active_clients'?'active':''; ?>" onclick="window.location='?tab=active_clients'"><i class="fas fa-users"></i> Active Clients</button>
            <button class="mgmt-tab-btn <?php echo $current_tab==='delete_history'?'active':''; ?>" onclick="window.location='?tab=delete_history'"><i class="fas fa-history"></i> Delete History</button>
        </div>
        <div class="mgmt-tab-panel <?php echo $current_tab==='active_clients'?'active':''; ?>">
            <div class="mgmt-section-label"><i class="fas fa-users"></i> Registered Clients</div>
            <?php if(empty($clients)): ?>
            <div class="mgmt-empty"><div class="mgmt-empty-icon"><i class="fas fa-users"></i></div><h3>No clients</h3><p>Clients appear here after registration.</p></div>
            <?php else: ?>
            <div class="dashboard-grid" id="clientGrid">
                <?php foreach($clients as $client): ?>
                <div class="profile-card role-regular status-<?php echo $client['status']??'active'; ?>" data-search="<?php echo strtolower(($client['name']??'').' '.($client['email']??'')); ?>">
                    <div class="profile-card-accent"></div>
                    <div class="profile-card-body">
                        <div class="profile-avatar role-regular"><?php echo getInitials($client['name']??'?'); ?><span class="status-dot <?php echo ($client['status']??'active')==='active'?'active-dot':''; ?>"></span></div>
                        <div class="profile-name"><?php echo htmlspecialchars($client['name']??'Unknown'); ?></div>
                        <div class="profile-id">#CLT-<?php echo str_pad($client['id'],3,'0',STR_PAD_LEFT); ?></div>
                        <div class="profile-email"><i class="fas fa-envelope"></i><?php echo htmlspecialchars($client['email']??'N/A'); ?></div>
                        <div class="profile-badges">
                            <span class="profile-badge badge-role-regular"><i class="fas fa-user"></i> Client</span>
                            <span class="profile-badge badge-<?php echo $client['status']??'active'; ?>"><i class="fas fa-circle"></i> <?php echo ucfirst($client['status']??'active'); ?></span>
                        </div>
                        <div class="profile-meta staff-assignment-wrapper">
                            <i class="fas fa-user-shield"></i>
                            <select class="staff-assignment-dropdown" data-client-id="<?php echo $client['id']; ?>">
                                <option value="">-- Unassigned --</option>
                                <?php foreach($available_staff as $staff): ?>
                                    <option value="<?php echo $staff['id']; ?>" <?php echo (isset($client['staff_id']) && $client['staff_id'] == $staff['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($staff['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="assign-feedback" id="feedback-<?php echo $client['id']; ?>"></div>
                        </div>
                        <div class="profile-meta"><i class="fas fa-calendar-alt"></i> Joined <?php echo date('M j, Y',strtotime($client['created_at']??'now')); ?></div>
                    </div>
                    <div class="profile-card-footer">
                         <form method="POST" class="inline-form">
                    <?php echo csrf_field(); ?><input type="hidden" name="action" value="toggle_client_status"><input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">                    <button type="submit" class="card-action toggle"><i class="fas fa-power-off"></i><?php echo ($client['status']??'active')==='active'?'Deactivate':'Activate'; ?></button></form>
                        <form method="POST" class="inline-form" onsubmit="return confirm('Delete this client?')">
                    <?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_client"><input type="hidden" name="client_id" value="<?php echo $client['id']; ?>"><button type="submit" class="card-action delete"><i class="fas fa-trash"></i></button></form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="mgmt-tab-panel <?php echo $current_tab==='delete_history'?'active':''; ?>">
            <div class="mgmt-section-label mgmt-section-label--mb"><i class="fas fa-history"></i> Deleted Client Archive</div>
            <?php if(empty($deleted_clients)): ?>
            <div class="mgmt-empty"><div class="mgmt-empty-icon"><i class="fas fa-history"></i></div><h3>No deleted clients</h3></div>
            <?php else: ?>
            <div class="deleted-grid">
                <?php foreach($deleted_clients as $dc): $age=floor((time()-strtotime($dc['deleted_at']))/86400); $canPerm=$age>=10; ?>
                <div class="deleted-card">
                    <div class="deleted-avatar"><?php echo getInitials($dc['name']??'?'); ?></div>
                    <div class="deleted-info">
                        <div class="deleted-name"><?php echo htmlspecialchars($dc['name']??'Unknown'); ?></div>
                        <div class="deleted-meta">
                            <span><?php echo htmlspecialchars($dc['email']??''); ?></span>
                            <div class="deleted-by-chip"><i class="fas fa-user-times"></i><?php echo htmlspecialchars($dc['deleted_by_name']??'Admin'); ?></div>
                            <span><?php echo date('M j, Y',strtotime($dc['deleted_at'])); ?></span>
                        </div>
                    </div>
                    <div class="deleted-actions">
                        <form method="POST" class="inline-form" onsubmit="return confirm('Restore?')">
                    <?php echo csrf_field(); ?><input type="hidden" name="action" value="restore_client"><input type="hidden" name="deleted_client_id" value="<?php echo $dc['id']; ?>"><button type="submit" class="card-action restore"><i class="fas fa-undo"></i></button></form>
                        <form method="POST" class="inline-form" onsubmit="return confirm('Permanently delete?')">
                    <?php echo csrf_field(); ?><input type="hidden" name="action" value="permanent_delete_client"><input type="hidden" name="deleted_client_id" value="<?php echo $dc['id']; ?>"><button type="submit" class="card-action delete perm-delete-btn" data-deleted-at="<?php echo $dc['deleted_at']; ?>" data-min-days="10" <?php echo $canPerm?'':'disabled'; ?>><i class="fas fa-skull"></i></button></form>
                        <span class="countdown-chip perm-countdown" data-deleted-at="<?php echo $dc['deleted_at']; ?>" data-min-days="10"></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    </main>
</div>
<script>
function filterCards(term,gridId){term=term.toLowerCase();document.querySelectorAll('#'+gridId+' .profile-card').forEach(c=>{c.style.display=(c.dataset.search||'').includes(term)?'':'none';});}
document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('.staff-assignment-dropdown').forEach(dropdown => {
        dropdown.addEventListener('change', function() {
            const clientId = this.dataset.clientId;
            const staffId = this.value;
            const feedbackEl = document.getElementById('feedback-' + clientId);
            
            feedbackEl.style.display = 'block';
            feedbackEl.style.color = 'var(--text-color)';
            feedbackEl.textContent = 'Saving...';
            
            fetch('handlers/assign_staff.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    client_user_id: clientId,
                    staff_id: staffId
                })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    feedbackEl.style.color = '#10b981';
                    feedbackEl.textContent = 'Saved!';
                    setTimeout(() => { feedbackEl.style.display = 'none'; }, 2000);
                } else {
                    feedbackEl.style.color = '#ef4444';
                    feedbackEl.textContent = data.message || 'Error saving';
                }
            })
            .catch(err => {
                feedbackEl.style.color = '#ef4444';
                feedbackEl.textContent = 'Network error';
            });
        });
    });

    function fmt(s){const d=Math.floor(s/86400);s-=d*86400;const h=Math.floor(s/3600);s-=h*3600;const m=Math.floor(s/60);const sec=Math.floor(s-m*60);return d+'d '+String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(sec).padStart(2,'0');}
    document.querySelectorAll('.perm-delete-btn').forEach(btn=>{const dMs=Date.parse((btn.dataset.deletedAt||'').replace(' ','T'));const min=parseInt(btn.dataset.minDays||'10',10);const chip=btn.closest('.deleted-actions')?.querySelector('.perm-countdown');function upd(){const l=Math.max(0,min*86400000-(Date.now()-dMs));if(l<=0){btn.removeAttribute('disabled');if(chip)chip.textContent='';return true;}btn.setAttribute('disabled','disabled');if(chip)chip.textContent='Available in '+fmt(Math.floor(l/1000));return false;}upd();const iv=setInterval(()=>{if(upd())clearInterval(iv);},1000);});
});
</script>
</body>
</html>


