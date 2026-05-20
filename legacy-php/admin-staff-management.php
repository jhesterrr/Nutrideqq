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

$admin_id = $_SESSION['user_id'];
$deleted_by = 'admin';
$deleted_by_id = $admin_id;

$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];
$user_role = $_SESSION['user_role'];

// Include navigation helper
require_once 'navigation.php';

// Get navigation links for current page
$nav_links_array = getNavigationLinks($user_role, 'admin-staff-management.php');

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

$staff_members = [];
$deleted_staff = [];
$stats = [
    'total_staff' => 0,
    'active_staff' => 0,
    'deleted_staff' => 0
];
$error = '';

try {
    $purge_stmt = $conn->prepare("DELETE FROM deleted_users WHERE deleted_at <= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $purge_stmt->execute();
} catch (PDOException $e) {
}

// Handle form actions for inline operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete_staff':
                $user_id = $_POST['staff_id'];

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
                        $_SESSION['success'] = "Staff member moved to delete history successfully";
                    } else {
                        $error = "Staff member not found";
                    }

                    header("Location: admin-staff-management.php");
                    exit();
                } catch (PDOException $e) {
                    $conn->rollBack();
                    $error = "Database error: " . $e->getMessage();
                }
                break;

            case 'restore_staff':
                $deleted_user_id = $_POST['deleted_staff_id'];

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
                            $_SESSION['error'] = "A staff member with this email already exists. Cannot restore.";
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
                            $_SESSION['success'] = "Staff member restored successfully";
                        }
                    } else {
                        $_SESSION['error'] = "Staff member not found in delete history";
                    }

                    header("Location: admin-staff-management.php?tab=delete_history");
                    exit();
                } catch (PDOException $e) {
                    $conn->rollBack();
                    $_SESSION['error'] = "Database error: " . $e->getMessage();
                    header("Location: admin-staff-management.php?tab=delete_history");
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

            case 'toggle_status':
                $user_id = $_POST['staff_id'];
                $stmt = $conn->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?");
                $stmt->execute([$user_id]);
                $_SESSION['success'] = "Staff status updated successfully";
                header("Location: admin-staff-management.php");
                exit();
                break;

            case 'edit_staff':
                $user_id = $_POST['staff_id'];
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                $role = 'staff';
                $status = 'active'; // Default for staff edit
                $new_password = $_POST['password'] ?? '';

                try {
                    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
                    $check_stmt->execute([$email, $user_id]);
                    if ($check_stmt->fetch()) {
                        $_SESSION['error'] = "Email already exists";
                        header("Location: admin-staff-management.php");
                        exit();
                    }

                    if (!empty($new_password)) {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, status = ?, password = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$name, $email, $role, $status, $hashed_password, $user_id]);
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, status = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$name, $email, $role, $status, $user_id]);
                    }

                    $_SESSION['success'] = "Staff member updated successfully";
                    header("Location: admin-staff-management.php");
                    exit();
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Database error: " . $e->getMessage();
                    header("Location: admin-staff-management.php");
                    exit();
                }
                break;
            case 'add_staff':
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                $password = $_POST['password'];
                $confirm_password = $_POST['confirm_password'];

                if ($password !== $confirm_password) {
                    $_SESSION['error'] = "Passwords do not match";
                    header("Location: admin-staff-management.php");
                    exit();
                }

                try {
                    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                    $check_stmt->execute([$email]);
                    if ($check_stmt->fetch()) {
                        $_SESSION['error'] = "Email already exists";
                        header("Location: admin-staff-management.php");
                        exit();
                    }

                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, status, created_at, updated_at) VALUES (?, ?, ?, 'staff', 'active', NOW(), NOW())");
                    $stmt->execute([$name, $email, $hashed_password]);

                    $_SESSION['success'] = "Staff member added successfully";
                    header("Location: admin-staff-management.php");
                    exit();
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Database error: " . $e->getMessage();
                    header("Location: admin-staff-management.php");
                    exit();
                }
                break;
            case 'permanent_delete_staff':
                $deleted_user_id = $_POST['deleted_staff_id'];
                try {
                    $stmt = $conn->prepare("SELECT deleted_at FROM deleted_users WHERE id = ?");
                    $stmt->execute([$deleted_user_id]);
                    $row = $stmt->fetch();
                    if (!$row) {
                        $_SESSION['error'] = "Staff member not found in delete history";
                        header("Location: admin-staff-management.php?tab=delete_history");
                        exit();
                    }
                    $deleted_at = strtotime($row['deleted_at']);
                    $diff_days = floor((time() - $deleted_at) / 86400);
                    if ($diff_days < 10) {
                        $_SESSION['error'] = "Permanent delete allowed only after 10 days";
                        header("Location: admin-staff-management.php?tab=delete_history");
                        exit();
                    }
                    $del = $conn->prepare("DELETE FROM deleted_users WHERE id = ?");
                    $del->execute([$deleted_user_id]);
                    $_SESSION['success'] = "Staff member permanently deleted";
                    header("Location: admin-staff-management.php?tab=delete_history");
                    exit();
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Database error: " . $e->getMessage();
                    header("Location: admin-staff-management.php?tab=delete_history");
                    exit();
                }
                break;
        }
    }
}

// Handle GET parameters for tabs
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'active_users';

try {
    // Get staff statistics
    $stats_sql = "SELECT 
        COUNT(*) as total_staff,
        SUM(status = 'active') as active_staff
        FROM users WHERE role = 'staff'";
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->execute();
    $stats_data = $stats_stmt->fetch();
    $stats['total_staff'] = $stats_data['total_staff'] ?? 0;
    $stats['active_staff'] = $stats_data['active_staff'] ?? 0;

    // Get deleted staff count
    $deleted_stats_stmt = $conn->prepare("SELECT COUNT(*) as deleted_count FROM deleted_users WHERE role = 'staff'");
    $deleted_stats_stmt->execute();
    $deleted_stats = $deleted_stats_stmt->fetch();
    $stats['deleted_staff'] = $deleted_stats['deleted_count'] ?? 0;

    // Get all active staff
    $users_sql = "SELECT * FROM users WHERE role = 'staff' ORDER BY created_at DESC";
    $users_stmt = $conn->prepare($users_sql);
    $users_stmt->execute();
    $staff_members = $users_stmt->fetchAll();

    // Get all deleted staff
    $deleted_users_sql = "SELECT * FROM deleted_users WHERE role = 'staff' ORDER BY deleted_at DESC";
    $deleted_users_stmt = $conn->prepare($deleted_users_sql);
    $deleted_users_stmt->execute();
    $deleted_staff = $deleted_users_stmt->fetchAll();

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
    <title>NutriDeq - Staff Management</title>
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

        <!-- Hero -->
        <div class="mgmt-hero">
            <div class="mgmt-hero-left">
                <div class="mgmt-hero-icon"><i class="fas fa-user-md"></i></div>
                <h1 class="mgmt-hero-title">Staff Management</h1>
                <p class="mgmt-hero-subtitle">Manage NutriDeq clinical staff, their accounts, and permissions</p>
            </div>
            <div class="mgmt-hero-right">
                <button class="btn-premium-add" id="addStaffBtn"><i class="fas fa-user-plus"></i> Add Staff Member</button>
            </div>
        </div>

        <!-- Stats -->
        <div class="mgmt-stats">
            <div class="mgmt-stat stat-total"><div class="mgmt-stat-icon"><i class="fas fa-user-shield"></i></div><div class="mgmt-stat-info"><h4><?php echo $stats['total_staff']??0; ?></h4><p>Total Staff</p></div></div>
            <div class="mgmt-stat stat-active"><div class="mgmt-stat-icon"><i class="fas fa-check-circle"></i></div><div class="mgmt-stat-info"><h4><?php echo $stats['active_staff']??0; ?></h4><p>Active</p></div></div>
            <div class="mgmt-stat stat-deleted"><div class="mgmt-stat-icon"><i class="fas fa-user-times"></i></div><div class="mgmt-stat-info"><h4><?php echo $stats['deleted_staff']??0; ?></h4><p>Deleted</p></div></div>
        </div>

        <!-- Tabs -->
        <div class="mgmt-tabs">
            <button class="mgmt-tab-btn <?php echo $current_tab==='active_staff'?'active':''; ?>" onclick="window.location='?tab=active_staff'"><i class="fas fa-user-shield"></i> Active Staff</button>
            <button class="mgmt-tab-btn <?php echo $current_tab==='delete_history'?'active':''; ?>" onclick="window.location='?tab=delete_history'"><i class="fas fa-history"></i> Delete History</button>
        </div>

        <!-- Active Staff -->
        <div class="mgmt-tab-panel <?php echo $current_tab==='active_staff'?'active':''; ?>">
            <div class="mgmt-toolbar">
                <div class="mgmt-section-label"><i class="fas fa-user-shield"></i> Staff Accounts</div>
                <div class="mgmt-search"><i class="fas fa-search"></i><input type="text" placeholder="Search staff..." oninput="filterCards(this.value,'staffGrid')"></div>
            </div>
            <?php if(empty($staff_members)): ?>
            <div class="mgmt-empty"><div class="mgmt-empty-icon"><i class="fas fa-user-shield"></i></div><h3>No staff members</h3><p>Add your first staff member to get started.</p></div>
            <?php else: ?>
            <div class="dashboard-grid" id="staffGrid">
                <?php foreach($staff_members as $staff): ?>
                <div class="profile-card role-staff status-<?php echo $staff['status']; ?>" data-search="<?php echo strtolower($staff['name'].' '.$staff['email']); ?>">
                    <div class="profile-card-accent"></div>
                    <div class="profile-card-body">
                        <div class="profile-avatar role-staff"><?php echo getInitials($staff['name']); ?><span class="status-dot <?php echo $staff['status']==='active'?'active-dot':''; ?>"></span></div>
                        <div class="profile-name"><?php echo htmlspecialchars($staff['name']); ?></div>
                        <div class="profile-id">#STF-<?php echo str_pad($staff['id'],3,'0',STR_PAD_LEFT); ?></div>
                        <div class="profile-email"><i class="fas fa-envelope"></i><?php echo htmlspecialchars($staff['email']); ?></div>
                        <div class="profile-badges">
                            <span class="profile-badge badge-role-staff"><i class="fas fa-user-shield"></i> Staff</span>
                            <span class="profile-badge badge-<?php echo $staff['status']; ?>"><i class="fas fa-circle"></i> <?php echo ucfirst($staff['status']); ?></span>
                        </div>
                        <div class="profile-meta"><i class="fas fa-calendar-alt"></i> Joined <?php echo date('M j, Y',strtotime($staff['created_at'])); ?></div>
                    </div>
                    <div class="profile-card-footer">
                        <form method="POST" class="inline-form" style="flex:1;"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>"><button type="submit" class="card-action toggle" style="width:100%;display:flex;justify-content:center;align-items:center;gap:6px;font-size:0.8rem;padding:8px 14px;border-radius:10px;" title="Toggle Status"><i class="fas fa-power-off"></i> <?php echo $staff['status']==='active'?'Deactivate':'Activate'; ?></button></form>
                        <button type="button" class="card-action edit edit-staff-btn" data-staff-id="<?php echo $staff['id']; ?>" data-name="<?php echo htmlspecialchars($staff['name']); ?>" data-email="<?php echo htmlspecialchars($staff['email']); ?>" data-status="<?php echo $staff['status']; ?>" title="Edit"><i class="fas fa-pen"></i></button>
                        <form method="POST" class="inline-form" onsubmit="return confirm('Delete this staff member?')"><input type="hidden" name="action" value="delete_staff"><input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>"><button type="submit" class="card-action delete" title="Delete"><i class="fas fa-trash"></i></button></form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Delete History -->
        <div class="mgmt-tab-panel <?php echo $current_tab==='delete_history'?'active':''; ?>">
            <div class="mgmt-section-label" style="margin-bottom:20px;"><i class="fas fa-history"></i> Deleted Staff Archive</div>
            <?php if(empty($deleted_staff)): ?>
            <div class="mgmt-empty"><div class="mgmt-empty-icon"><i class="fas fa-history"></i></div><h3>No deleted staff</h3><p>Deleted staff will appear here.</p></div>
            <?php else: ?>
            <div class="deleted-grid">
                <?php foreach($deleted_staff as $ds): $age=floor((time()-strtotime($ds['deleted_at']))/86400); $canPerm=$age>=10; ?>
                <div class="deleted-card">
                    <div class="deleted-avatar"><?php echo getInitials($ds['name']); ?></div>
                    <div class="deleted-info">
                        <div class="deleted-name"><?php echo htmlspecialchars($ds['name']); ?></div>
                        <div class="deleted-meta">
                            <span><?php echo htmlspecialchars($ds['email']); ?></span>
                            <div class="deleted-by-chip"><i class="fas fa-user-times"></i><?php echo htmlspecialchars($ds['deleted_by_name']??'Admin'); ?></div>
                            <span><?php echo date('M j, Y',strtotime($ds['deleted_at'])); ?></span>
                        </div>
                    </div>
                    <div class="deleted-actions">
                        <form method="POST" class="inline-form" onsubmit="return confirm('Restore?')"><input type="hidden" name="action" value="restore_staff"><input type="hidden" name="deleted_staff_id" value="<?php echo $ds['id']; ?>"><button type="submit" class="card-action restore" title="Restore"><i class="fas fa-undo"></i></button></form>
                        <form method="POST" class="inline-form" onsubmit="return confirm('Permanently delete? Cannot be undone.')"><input type="hidden" name="action" value="permanent_delete_staff"><input type="hidden" name="deleted_staff_id" value="<?php echo $ds['id']; ?>"><button type="submit" class="card-action delete perm-delete-btn" data-deleted-at="<?php echo $ds['deleted_at']; ?>" data-min-days="10" <?php echo $canPerm?'':'disabled'; ?> title="Permanent Delete"><i class="fas fa-skull"></i></button></form>
                        <span class="countdown-chip perm-countdown" data-deleted-at="<?php echo $ds['deleted_at']; ?>" data-min-days="10"></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
    </main>
</div>

<!-- Add Staff Modal -->
<div class="premium-modal-overlay" id="addStaffModal">
    <div class="premium-modal">
        <div class="premium-modal-title"><i class="fas fa-user-plus"></i> Add Staff Member</div>
        <form method="POST">
            <input type="hidden" name="action" value="add_staff">
            <div class="premium-form-group"><label>Full Name</label><input type="text" name="name" required placeholder="Enter full name"></div>
            <div class="premium-form-group"><label>Email Address</label><input type="email" name="email" required placeholder="Enter email"></div>
            <div class="premium-form-group"><label>Password</label><input type="password" name="password" required placeholder="Enter password"></div>
            <div class="premium-form-group"><label>Confirm Password</label><input type="password" name="confirm_password" required placeholder="Confirm password"></div>
            <div class="premium-modal-actions">
                <button type="button" class="btn-modal-cancel" onclick="document.getElementById('addStaffModal').classList.remove('active');document.body.style.overflow='auto'">Cancel</button>
                <button type="submit" class="btn-modal-submit">Add Staff</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Staff Modal -->
<div class="premium-modal-overlay" id="editStaffModal">
    <div class="premium-modal">
        <div class="premium-modal-title"><i class="fas fa-user-edit"></i> Edit Staff</div>
        <form method="POST" id="editStaffForm">
            <input type="hidden" name="action" value="edit_staff">
            <input type="hidden" name="staff_id" id="edit_staff_id">
            <div class="premium-form-group"><label>Full Name</label><input type="text" id="edit_staff_name" name="name" required></div>
            <div class="premium-form-group"><label>Email</label><input type="email" id="edit_staff_email" name="email" required></div>
            <div class="premium-form-group"><label>New Password <span style="color:#9ca3af;font-weight:400;text-transform:none;">(optional)</span></label><input type="password" name="password" placeholder="Leave blank to keep current"></div>
            <div class="premium-modal-actions">
                <button type="button" class="btn-modal-cancel" onclick="document.getElementById('editStaffModal').classList.remove('active');document.body.style.overflow='auto'">Cancel</button>
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
    document.getElementById('addStaffBtn')?.addEventListener('click', ()=>{ document.getElementById('addStaffModal').classList.add('active'); document.body.style.overflow='hidden'; });
    document.querySelectorAll('.edit-staff-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_staff_id').value=this.dataset.staffId;
            document.getElementById('edit_staff_name').value=this.dataset.name;
            document.getElementById('edit_staff_email').value=this.dataset.email;
            document.getElementById('editStaffModal').classList.add('active');
            document.body.style.overflow='hidden';
        });
    });
    ['addStaffModal','editStaffModal'].forEach(id => {
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

