<?php
$session_path = 'c:\xampp\htdocs\nutrideq\api\sessions';
if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
session_save_path($session_path);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
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
$nav_links_array = getNavigationLinks($user_role, 'admin-user-management.php');

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

// Database connection
require_once 'database.php';
$database = new Database();
$conn = $database->getConnection();

$users = [];
$deleted_users = [];
$stats = [
    'total_users' => 0,
    'staff_count' => 0,
    'admin_count' => 0,
    'regular_count' => 0,
    'deleted_users' => 0
];
$error = '';

try {
    $purge_stmt = $conn->prepare("DELETE FROM deleted_users WHERE deleted_at <= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $purge_stmt->execute();
    $purge_clients_stmt = $conn->prepare("DELETE FROM deleted_clients WHERE deleted_at <= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $purge_clients_stmt->execute();
} catch (PDOException $e) {
}

// Handle form actions for inline operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete_user':
                $user_id = $_POST['user_id'];

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

                        // Insert into deleted_users table with proper tracking
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
                            $admin_name, // Admin's actual name
                            $user['name'],
                            $user['email'],
                            $user['password'],
                            $user['role'],
                            $user['status'],
                            $user['created_at'],
                            $user['updated_at']
                        ]);

                        // Unlink linked clients before deleting user
                        $unlink_clients = $conn->prepare("UPDATE clients SET status = 'inactive', user_id = NULL, updated_at = NOW() WHERE user_id = ?");
                        $unlink_clients->execute([$user_id]);
                        // Delete from users table
                        $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                        $delete_stmt->execute([$user_id]);

                        $conn->commit();
                        $_SESSION['success'] = "User moved to delete history successfully";
                    } else {
                        $error = "User not found";
                    }

                    header("Location: admin-user-management.php");
                    exit();
                } catch (PDOException $e) {
                    $conn->rollBack();
                    $error = "Database error: " . $e->getMessage();
                }
                break;

            case 'restore_user':
                $deleted_user_id = $_POST['deleted_user_id'];

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
                            $_SESSION['error'] = "A user with this email already exists. Cannot restore.";
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
                            // Relink clients for regular users by email
                            if ($user['role'] === 'regular') {
                                $relink = $conn->prepare("UPDATE clients SET user_id = ?, status = ?, updated_at = NOW() WHERE email = ?");
                                $relink->execute([$user['original_id'], $user['status'], $user['email']]);
                            }

                            $conn->commit();
                            $_SESSION['success'] = "User restored successfully";
                        }
                    } else {
                        $_SESSION['error'] = "User not found in delete history";
                    }
                } catch (PDOException $e) {
                    $conn->rollBack();
                    $_SESSION['error'] = "Database error: " . $e->getMessage();
                }
                header("Location: admin-user-management.php?tab=delete_history");
                exit();
                break;

            case 'reject_reset':
                try {
                    $request_id = $_POST['request_id'];
                    $stmt = $conn->prepare("UPDATE password_reset_requests SET status = 'cancelled' WHERE id = ?");
                    $stmt->execute([$request_id]);
                    $_SESSION['success'] = "Reset request cancelled.";
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Could not process reset request: " . $e->getMessage();
                }
                header("Location: admin-user-management.php");
                exit();
                break;

            case 'update_role':
                $user_id = $_POST['user_id'];
                $new_role = $_POST['role'];
                $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$new_role, $user_id]);
                $_SESSION['success'] = "User role updated successfully";
                header("Location: admin-user-management.php");
                exit();
                break;

            case 'toggle_status':
                $user_id = $_POST['user_id'];
                $stmt = $conn->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?");
                $stmt->execute([$user_id]);
                $selRole = $conn->prepare("SELECT role, status FROM users WHERE id = ?");
                $selRole->execute([$user_id]);
                $u = $selRole->fetch();
                if ($u && $u['role'] === 'regular') {
                    $updClientStatus = $conn->prepare("UPDATE clients SET status = ? WHERE user_id = ?");
                    $updClientStatus->execute([$u['status'], $user_id]);
                }
                $_SESSION['success'] = "User status updated successfully";
                header("Location: admin-user-management.php");
                exit();
                break;

            case 'edit_user':
                $user_id = $_POST['user_id'];
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                $role = $_POST['role'];
                $status = $_POST['status'];
                $new_password = $_POST['password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';

                try {
                    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
                    $check_stmt->execute([$email, $user_id]);
                    if ($check_stmt->fetch()) {
                        $_SESSION['error'] = "Email already exists";
                        header("Location: admin-user-management.php");
                        exit();
                    }

                    if (!in_array($role, ['regular', 'staff', 'admin'])) {
                        $role = 'regular';
                    }
                    if (!in_array($status, ['active', 'inactive'])) {
                        $status = 'active';
                    }

                    if (!empty($new_password)) {
                        if ($new_password !== $confirm_password) {
                            $_SESSION['error'] = "New password and confirm password do not match";
                            header("Location: admin-user-management.php");
                            exit();
                        }
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, status = ?, password = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$name, $email, $role, $status, $hashed_password, $user_id]);
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, status = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$name, $email, $role, $status, $user_id]);
                    }
                    if ($role === 'regular') {
                        $dupClient = $conn->prepare("SELECT id FROM clients WHERE email = ? AND user_id <> ?");
                        $dupClient->execute([$email, $user_id]);
                        if (!$dupClient->fetch()) {
                            $updClient = $conn->prepare("UPDATE clients SET name = ?, email = ?, updated_at = NOW() WHERE user_id = ?");
                            $updClient->execute([$name, $email, $user_id]);
                        }
                    }

                    $_SESSION['success'] = "User updated successfully";
                    header("Location: admin-user-management.php");
                    exit();
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Database error: " . $e->getMessage();
                    header("Location: admin-user-management.php");
                    exit();
                }
                break;

            case 'permanent_delete':
                $deleted_user_id = $_POST['deleted_user_id'];
                try {
                    $stmt = $conn->prepare("SELECT deleted_at FROM deleted_users WHERE id = ?");
                    $stmt->execute([$deleted_user_id]);
                    $row = $stmt->fetch();
                    if (!$row) {
                        $_SESSION['error'] = "User not found in delete history";
                        header("Location: admin-user-management.php?tab=delete_history");
                        exit();
                    }
                    $deleted_at = strtotime($row['deleted_at']);
                    $diff_days = floor((time() - $deleted_at) / 86400);
                    if ($diff_days < 10) {
                        $_SESSION['error'] = "Permanent delete allowed only after 10 days";
                        header("Location: admin-user-management.php?tab=delete_history");
                        exit();
                    }
                    $del = $conn->prepare("DELETE FROM deleted_users WHERE id = ?");
                    $del->execute([$deleted_user_id]);
                    $_SESSION['success'] = "User permanently deleted";
                    header("Location: admin-user-management.php?tab=delete_history");
                    exit();
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Database error: " . $e->getMessage();
                    header("Location: admin-user-management.php?tab=delete_history");
                    exit();
                }
                break;












        }
    }
}

// Handle GET parameters for tabs
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'active_users';

try {
    // Get user statistics
    $stats_sql = "SELECT 
        COUNT(*) as total_users,
        SUM(role = 'admin') as admin_count,
        SUM(role = 'staff') as staff_count,
        SUM(role = 'regular') as regular_count,
        SUM(status = 'active') as active_users
        FROM users";
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->execute();
    $stats_data = $stats_stmt->fetch();
    $stats['total_users'] = $stats_data['total_users'] ?? 0;
    $stats['staff_count'] = $stats_data['staff_count'] ?? 0;
    $stats['admin_count'] = $stats_data['admin_count'] ?? 0;
    $stats['regular_count'] = $stats_data['regular_count'] ?? 0;

    // Get deleted users count
    $deleted_stats_stmt = $conn->prepare("SELECT COUNT(*) as deleted_count FROM deleted_users");
    $deleted_stats_stmt->execute();
    $deleted_stats = $deleted_stats_stmt->fetch();
    $stats['deleted_users'] = $deleted_stats['deleted_count'] ?? 0;

    // Get all active users
    $users_sql = "SELECT * FROM users ORDER BY created_at DESC";
    $users_stmt = $conn->prepare($users_sql);
    $users_stmt->execute();
    $users = $users_stmt->fetchAll();

    // Get all deleted users
    $deleted_users_sql = "SELECT * FROM deleted_users ORDER BY deleted_at DESC";
    $deleted_users_stmt = $conn->prepare($deleted_users_sql);
    $deleted_users_stmt->execute();
    $deleted_users = $deleted_users_stmt->fetchAll();

    // Get reset requests
    $resets_stmt = $conn->prepare("SELECT prr.id, u.name, u.email, prr.created_at, prr.status FROM password_reset_requests prr JOIN users u ON prr.user_id = u.id WHERE prr.status = 'pending' ORDER BY prr.created_at DESC");
    $resets_stmt->execute();
    $reset_requests = $resets_stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="scripts/theme-toggle.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <!-- Fonts handled by base.css (Phase 2) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/components.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/responsive.css">
    <link rel="stylesheet" href="css/logout-modal.css">
    <link rel="stylesheet" href="css/mobile-style.css">
    <link rel="stylesheet" href="css/premium-management.css">
    <script src="scripts/dashboard.js" defer></script>
    <!-- MOBILE FALLBACK FIXES -->
    <style>
    @media (max-width: 1024px) {
        .main-content, .page-container, main, .dashboard { padding-bottom: 120px !important; margin-bottom: 120px !important; }
        .table-responsive, .table-container, .card-body {
            width: 100% !important;
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch !important;
            display: block !important;
        }
        table { min-width: 700px !important; display: table !important; }
        thead, tbody, tr { width: 100% !important; }
    }
    </style>
    <style>
    @media (max-width: 1024px) {
        .main-content, .page-container, main { padding-bottom: 120px !important; }
        .table-responsive, .table-container, .card-body {
            width: 100vw !important;
            max-width: 100% !important;
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch !important;
            display: block !important;
        }
        table { min-width: 800px !important; display: table !important; }
        .grid, .tab-buttons { display: flex; flex-direction: column; }
    }
    </style>
</head>
<body>
<div class="nd-app">
    <?php include 'includes/sidebar.php'; ?>
    <main class="nd-main">
    <div class="page-container mgmt-page">

        <?php if (isset($_SESSION['success'])): ?>
        <div class="mgmt-alert mgmt-alert-success"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
        <div class="mgmt-alert mgmt-alert-error"><i class="fas fa-exclamation-triangle"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Hero -->
        <div class="mgmt-hero">
            <div class="mgmt-hero-left">
                <div class="mgmt-hero-icon"><i class="fas fa-users-cog"></i></div>
                <h1 class="mgmt-hero-title">User Management</h1>
                <p class="mgmt-hero-subtitle">Manage system users, roles, and permissions across NutriDeq</p>
            </div>
            <div class="mgmt-hero-right">
                <a href="reset_passwords.php" class="btn-premium-add" style="background: linear-gradient(135deg, #1e293b, #475569); box-shadow: 0 4px 14px rgba(30,41,59,0.3); margin-right: 8px;"><i class="fas fa-database"></i> Database Sandbox</a>
                <button class="btn-premium-add" id="addUserBtn"><i class="fas fa-user-plus"></i> Add New User</button>
            </div>
        </div>

        <!-- Stats -->
        <div class="mgmt-stats">
            <div class="mgmt-stat stat-total"><div class="mgmt-stat-icon"><i class="fas fa-users"></i></div><div class="mgmt-stat-info"><h4><?php echo $stats['total_users']??0; ?></h4><p>Total Users</p></div></div>
            <div class="mgmt-stat stat-staff"><div class="mgmt-stat-icon"><i class="fas fa-user-shield"></i></div><div class="mgmt-stat-info"><h4><?php echo $stats['staff_count']??0; ?></h4><p>Staff Members</p></div></div>
            <div class="mgmt-stat stat-admin"><div class="mgmt-stat-icon"><i class="fas fa-user-cog"></i></div><div class="mgmt-stat-info"><h4><?php echo $stats['admin_count']??0; ?></h4><p>Administrators</p></div></div>
            <div class="mgmt-stat stat-active"><div class="mgmt-stat-icon"><i class="fas fa-user-check"></i></div><div class="mgmt-stat-info"><h4><?php echo $stats['regular_count']??0; ?></h4><p>Regular Users</p></div></div>
            <div class="mgmt-stat stat-deleted"><div class="mgmt-stat-icon"><i class="fas fa-user-times"></i></div><div class="mgmt-stat-info"><h4><?php echo $stats['deleted_users']??0; ?></h4><p>Deleted</p></div></div>
        </div>

        <!-- Tabs -->
        <div class="mgmt-tabs">
            <button class="mgmt-tab-btn <?php echo $current_tab==='active_users'?'active':''; ?>" onclick="window.location='?tab=active_users'"><i class="fas fa-users"></i> Active Users</button>
            <button class="mgmt-tab-btn <?php echo $current_tab==='delete_history'?'active':''; ?>" onclick="window.location='?tab=delete_history'"><i class="fas fa-history"></i> Delete History <?php if($stats['deleted_users']>0) echo '<span style="background:#f43f5e;color:white;border-radius:50px;padding:1px 8px;font-size:0.72rem;">'.$stats['deleted_users'].'</span>'; ?></button>
        </div>

        <!-- Active Users -->
        <div class="mgmt-tab-panel <?php echo $current_tab==='active_users'?'active':''; ?>">
            <div class="mgmt-toolbar">
                <div class="mgmt-section-label"><i class="fas fa-users"></i> All User Accounts</div>
                <div class="mgmt-search"><i class="fas fa-search"></i><input type="text" placeholder="Search users..." oninput="filterCards(this.value,'profileGrid')"></div>
            </div>
            <?php if(empty($users)): ?>
            <div class="mgmt-empty"><div class="mgmt-empty-icon"><i class="fas fa-users"></i></div><h3>No users found</h3><p>Click Add New User to get started.</p></div>
            <?php else: ?>
            <div class="profile-grid" id="profileGrid">
                <?php foreach($users as $user): $roleIcon = $user['role']==='admin'?'crown':($user['role']==='staff'?'user-shield':'user'); ?>
                <div class="profile-card role-<?php echo $user['role']; ?> status-<?php echo $user['status']; ?>" data-search="<?php echo strtolower($user['name'].' '.$user['email'].' '.$user['role']); ?>">
                    <div class="profile-card-accent"></div>
                    <div class="profile-card-body">
                        <div class="profile-avatar role-<?php echo $user['role']; ?>"><?php echo getInitials($user['name']); ?><span class="status-dot <?php echo $user['status']==='active'?'active-dot':''; ?>"></span></div>
                        <div class="profile-name"><?php echo htmlspecialchars($user['name']); ?></div>
                        <div class="profile-id">#USR-<?php echo str_pad($user['id'],3,'0',STR_PAD_LEFT); ?></div>
                        <div class="profile-email"><i class="fas fa-envelope"></i><?php echo htmlspecialchars($user['email']); ?></div>
                        <div class="profile-badges">
                            <span class="profile-badge badge-role-<?php echo $user['role']; ?>"><i class="fas fa-<?php echo $roleIcon; ?>"></i> <?php echo ucfirst($user['role']); ?></span>
                            <span class="profile-badge badge-<?php echo $user['status']; ?>"><i class="fas fa-circle"></i> <?php echo ucfirst($user['status']); ?></span>
                        </div>
                        <div class="profile-meta"><i class="fas fa-calendar-alt"></i> Joined <?php echo date('M j, Y',strtotime($user['created_at'])); ?></div>
                    </div>
                    <div class="profile-card-footer">
                        <form method="POST" class="inline-form" style="flex:1;"><input type="hidden" name="action" value="update_role"><input type="hidden" name="user_id" value="<?php echo $user['id']; ?>"><select name="role" onchange="this.form.submit()" class="card-role-select"><option value="regular" <?php echo $user['role']==='regular'?'selected':''; ?>>Regular</option><option value="staff" <?php echo $user['role']==='staff'?'selected':''; ?>>Staff</option><option value="admin" <?php echo $user['role']==='admin'?'selected':''; ?>>Admin</option></select></form>
                        <form method="POST" class="inline-form"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="user_id" value="<?php echo $user['id']; ?>"><button type="submit" class="card-action toggle" title="Toggle Status"><i class="fas fa-power-off"></i></button></form>
                        <button type="button" class="card-action edit edit-btn" data-user-id="<?php echo $user['id']; ?>" data-name="<?php echo htmlspecialchars($user['name']); ?>" data-email="<?php echo htmlspecialchars($user['email']); ?>" data-role="<?php echo $user['role']; ?>" data-status="<?php echo $user['status']; ?>" title="Edit"><i class="fas fa-pen"></i></button>
                        <form method="POST" class="inline-form" onsubmit="return confirm('Move to trash?')"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?php echo $user['id']; ?>"><button type="submit" class="card-action delete" title="Delete"><i class="fas fa-trash"></i></button></form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Delete History -->
        <div class="mgmt-tab-panel <?php echo $current_tab==='delete_history'?'active':''; ?>">
            <div class="mgmt-section-label" style="margin-bottom:20px;"><i class="fas fa-history"></i> Deleted User Archive</div>
            <?php if(empty($deleted_users)): ?>
            <div class="mgmt-empty"><div class="mgmt-empty-icon"><i class="fas fa-history"></i></div><h3>No deleted users</h3><p>Deleted users are kept for 30 days before being purged.</p></div>
            <?php else: ?>
            <div class="deleted-grid">
                <?php foreach($deleted_users as $du): $age=floor((time()-strtotime($du['deleted_at']))/86400); $canPerm=$age>=10; ?>
                <div class="deleted-card">
                    <div class="deleted-avatar"><?php echo getInitials($du['name']); ?></div>
                    <div class="deleted-info">
                        <div class="deleted-name"><?php echo htmlspecialchars($du['name']); ?> <span style="font-size:0.75rem;color:#9ca3af;">#USR-<?php echo str_pad($du['original_id'],3,'0',STR_PAD_LEFT); ?></span></div>
                        <div class="deleted-meta">
                            <span><?php echo htmlspecialchars($du['email']); ?></span>
                            <span class="profile-badge badge-role-<?php echo $du['role']; ?>"><?php echo ucfirst($du['role']); ?></span>
                            <div class="deleted-by-chip"><i class="fas fa-user-times"></i><?php echo htmlspecialchars($du['deleted_by_name']); ?></div>
                            <span><?php echo date('M j, Y',strtotime($du['deleted_at'])); ?></span>
                        </div>
                    </div>
                    <div class="deleted-actions">
                        <form method="POST" class="inline-form" onsubmit="return confirm('Restore this user?')"><input type="hidden" name="action" value="restore_user"><input type="hidden" name="deleted_user_id" value="<?php echo $du['id']; ?>"><button type="submit" class="card-action restore" title="Restore"><i class="fas fa-undo"></i></button></form>
                        <form method="POST" class="inline-form" onsubmit="return confirm('Permanently delete? Cannot be undone.')"><input type="hidden" name="action" value="permanent_delete"><input type="hidden" name="deleted_user_id" value="<?php echo $du['id']; ?>"><button type="submit" class="card-action delete perm-delete-btn" data-deleted-at="<?php echo $du['deleted_at']; ?>" data-min-days="10" <?php echo $canPerm?'':'disabled'; ?> title="Permanent Delete"><i class="fas fa-skull"></i></button></form>
                        <span class="countdown-chip perm-countdown" data-deleted-at="<?php echo $du['deleted_at']; ?>" data-min-days="10"></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
    </main>
</div>

<!-- Add User Modal -->
<div class="premium-modal-overlay" id="addUserModal">
    <div class="premium-modal">
        <div class="premium-modal-title"><i class="fas fa-user-plus"></i> Add New User</div>
        <form method="POST" action="process_admin_add_user.php">
            <input type="hidden" name="origin" value="admin-user-management.php">
            <div class="premium-form-group"><label>Full Name</label><input type="text" name="name" required placeholder="Enter full name"></div>
            <div class="premium-form-group"><label>Email Address</label><input type="email" name="email" required placeholder="Enter email"></div>
            <div class="premium-form-group"><label>Password</label><input type="password" name="password" required placeholder="Enter password"></div>
            <div class="premium-form-group"><label>Confirm Password</label><input type="password" name="confirm_password" required placeholder="Confirm password"></div>
            <div class="premium-form-group"><label>Role</label><select name="role" required><option value="regular">Regular User</option><option value="staff">Staff Member</option><option value="admin">Administrator</option></select></div>
            <div class="premium-modal-actions">
                <button type="button" class="btn-modal-cancel" onclick="document.getElementById('addUserModal').classList.remove('active');document.body.style.overflow='auto'">Cancel</button>
                <button type="submit" class="btn-modal-submit">Add User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div class="premium-modal-overlay" id="editUserModal">
    <div class="premium-modal">
        <div class="premium-modal-title"><i class="fas fa-user-edit"></i> Edit User</div>
        <form method="POST" id="editUserForm">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="premium-form-group"><label>Full Name</label><input type="text" id="edit_name" name="name" required></div>
            <div class="premium-form-group"><label>Email</label><input type="email" id="edit_email" name="email" required></div>
            <div class="premium-form-group"><label>Role</label><select id="edit_role" name="role"><option value="regular">Regular User</option><option value="staff">Staff Member</option><option value="admin">Administrator</option></select></div>
            <div class="premium-form-group"><label>Status</label><select id="edit_status" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
            <div class="premium-form-group"><label>New Password <span style="color:#9ca3af;font-weight:400;text-transform:none;">(optional)</span></label><input type="password" id="edit_password" name="password" placeholder="Leave blank to keep current"></div>
            <div class="premium-form-group"><label>Confirm New Password</label><input type="password" id="edit_confirm_password" name="confirm_password" placeholder="Re-enter new password"></div>
            <div class="premium-modal-actions">
                <button type="button" class="btn-modal-cancel" onclick="document.getElementById('editUserModal').classList.remove('active');document.body.style.overflow='auto'">Cancel</button>
                <button type="submit" class="btn-modal-submit">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function filterCards(term, gridId) {
    term = term.toLowerCase();
    document.querySelectorAll('#'+gridId+' .profile-card').forEach(c => {
        c.style.display = (c.dataset.search||'').includes(term) ? '' : 'none';
    });
}
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('addUserBtn')?.addEventListener('click', ()=>{ document.getElementById('addUserModal').classList.add('active'); document.body.style.overflow='hidden'; });
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_user_id').value=this.dataset.userId;
            document.getElementById('edit_name').value=this.dataset.name;
            document.getElementById('edit_email').value=this.dataset.email;
            document.getElementById('edit_role').value=this.dataset.role||'regular';
            document.getElementById('edit_status').value=this.dataset.status||'active';
            document.getElementById('edit_password').value='';
            document.getElementById('edit_confirm_password').value='';
            document.getElementById('editUserModal').classList.add('active');
            document.body.style.overflow='hidden';
        });
    });
    ['addUserModal','editUserModal'].forEach(id => {
        document.getElementById(id)?.addEventListener('click', e=>{ if(e.target===document.getElementById(id)){document.getElementById(id).classList.remove('active');document.body.style.overflow='auto';} });
    });
    function fmt(s){const d=Math.floor(s/86400);s-=d*86400;const h=Math.floor(s/3600);s-=h*3600;const m=Math.floor(s/60);const sec=Math.floor(s-m*60);return `${d}d ${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(sec).padStart(2,'0')}`;}
    document.querySelectorAll('.perm-delete-btn').forEach(btn=>{
        const dMs=Date.parse((btn.dataset.deletedAt||'').replace(' ','T'));
        const min=parseInt(btn.dataset.minDays||'10',10);
        const chip=btn.closest('.deleted-actions')?.querySelector('.perm-countdown');
        function upd(){const l=Math.max(0,min*86400000-(Date.now()-dMs));if(l<=0){btn.removeAttribute('disabled');if(chip)chip.textContent='';return true;}btn.setAttribute('disabled','disabled');if(chip)chip.textContent='Available in '+fmt(Math.floor(l/1000));return false;}
        upd(); const iv=setInterval(()=>{if(upd())clearInterval(iv);},1000);
    });
    document.getElementById('logoutBtn')?.addEventListener('click',e=>{e.preventDefault();document.getElementById('logoutModal')?.classList.add('active');});
    document.getElementById('cancelLogout')?.addEventListener('click',()=>document.getElementById('logoutModal')?.classList.remove('active'));
    document.getElementById('confirmLogout')?.addEventListener('click',()=>window.location.href='login-logout/logout.php');
    window.addEventListener('click',e=>{if(e.target===document.getElementById('logoutModal'))document.getElementById('logoutModal').classList.remove('active');});
});
</script>
<!-- Global Logout Modal -->
<div class="logout-modal" id="logoutModal" style="z-index: 10001;">
    <div class="logout-modal-content">
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <h3>Are you sure?</h3>
        <p>You will be logged out and redirected to the login page.</p>
        <div class="logout-modal-actions">
            <button class="btn btn-secondary" id="cancelLogout">Cancel</button>
            <button class="btn btn-primary" id="confirmLogout">Yes, Logout</button>
        </div>
    </div>
</div>
</body>
</html>

