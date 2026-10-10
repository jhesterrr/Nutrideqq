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
$nav_links_array = getNavigationLinks($user_role, 'admin-staff-management.php');

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
    verify_csrf();
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
$current_tab = isset($_GET['tab']) ? preg_replace('/[^a-z0-9_-]/i', '', $_GET['tab']) : 'active_staff';
if (!in_array($current_tab, ['active_staff', 'delete_history'], true)) {
    $current_tab = 'active_staff';
}

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
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
    <style>
        /* ───────────────────────────────────────────────
           Admin Staff Management — Distinctive UI
           File-folder aesthetic: left color rail, layered
           paper depth, semantic button tint in base state.
           ─────────────────────────────────────────────── */

        @media (max-width: 768px) {
            .mgmt-hero {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 16px !important;
            }
            .mgmt-hero-right {
                width: 100% !important;
            }
            .mgmt-hero-right .btn-premium-add {
                width: 100% !important;
                justify-content: center !important;
                margin: 0 !important;
            }
            .profile-card-footer {
                flex-wrap: wrap !important;
                gap: 10px !important;
            }
            .profile-card-footer .inline-form:first-child {
                flex: 1 1 100% !important;
                order: -1;
            }
            .profile-card-footer .inline-form:first-child .card-action.toggle {
                width: 100% !important;
                justify-content: center !important;
            }
        }
        @media (max-width: 480px) {
            .profile-card-footer {
                justify-content: space-between !important;
            }
            .profile-card-footer .card-action:not(.toggle) {
                width: 44px !important;
                height: 44px !important;
                padding: 0 !important;
                justify-content: center !important;
            }
        }

        /* ── Card shell: file-folder rail + paper depth ── */
        #staffGrid .profile-card {
            background: #ffffff;
            border-radius: 20px 22px 22px 20px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow:
                0 1px 0 rgba(255,255,255,0.9) inset,
                0 0 0 1px rgba(148, 163, 184, 0.08),
                0 10px 24px -14px rgba(15, 23, 42, 0.18),
                0 4px 8px -6px rgba(15, 23, 42, 0.08);
            transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
            padding-left: 0;
            overflow: hidden;
            display: grid;
            grid-template-columns: 5px 1fr;
            grid-template-rows: 1fr auto;
            grid-template-areas:
                "rail body"
                "rail footer";
        }
        #staffGrid .profile-card:hover {
            transform: translateY(-3px);
            border-color: rgba(2, 132, 199, 0.28);
            box-shadow:
                0 1px 0 rgba(255,255,255,0.9) inset,
                0 0 0 1px rgba(2, 132, 199, 0.18),
                0 22px 40px -22px rgba(15, 23, 42, 0.28),
                0 8px 14px -8px rgba(15, 23, 42, 0.12);
        }

        /* The colored file-folder rail on the LEFT */
        #staffGrid .profile-card-accent {
            grid-area: rail;
            width: 100%;
            height: 100%;
            min-height: 100%;
            border-radius: 16px 0 0 16px;
        }
        #staffGrid .profile-card.role-staff .profile-card-accent {
            background: linear-gradient(180deg, #0284c7 0%, #38bdf8 55%, #7dd3fc 100%);
        }
        #staffGrid .profile-card.status-inactive .profile-card-accent {
            background: linear-gradient(180deg, #64748b 0%, #94a3b8 60%, #cbd5e1 100%);
        }

        /* Kill the old generic blob glow — it makes every page look templated */
        #staffGrid .profile-card::before { display: none; }

        /* ── Card body ── */
        #staffGrid .profile-card-body {
            grid-area: body;
            padding: 22px 22px 18px 20px;
            background:
                radial-gradient(120% 70% at 100% -10%, rgba(56,189,248,0.06) 0%, transparent 60%),
                #ffffff;
        }
        #staffGrid .profile-card.status-inactive .profile-card-body {
            background:
                radial-gradient(120% 70% at 100% -10%, rgba(148,163,184,0.08) 0%, transparent 60%),
                #fcfcfd;
        }

        /* Avatar: shrink slightly, stronger inner shadow so initials pop */
        #staffGrid .profile-avatar {
            width: 58px; height: 58px;
            border-radius: 18px;
            font-size: 1.28rem;
            margin-bottom: 12px;
            box-shadow:
                0 1px 0 rgba(255,255,255,0.35) inset,
                0 -1px 0 rgba(0,0,0,0.12) inset,
                0 6px 14px -8px rgba(2,132,199,0.45);
        }
        #staffGrid .profile-avatar .status-dot {
            bottom: -2px; right: -2px;
            width: 14px; height: 14px;
            border-width: 2.5px;
        }

        /* Name & ID hierarchy — tighten tracking, distinguish semantically */
        #staffGrid .profile-name {
            font-size: 1.12rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: #0f172a;
            margin-bottom: 3px;
        }
        #staffGrid .profile-id {
            font-family: 'Inter', monospace;
            font-variant-numeric: tabular-nums;
            font-size: 0.72rem;
            color: #94a3b8;
            font-weight: 500;
            letter-spacing: 0.04em;
            margin-bottom: 10px;
        }
        #staffGrid .profile-email {
            font-size: 0.84rem;
            color: #475569;
            font-weight: 500;
            margin-bottom: 14px;
        }
        #staffGrid .profile-email i { color: #0ea5e9; }

        /* Badges: sentence case, soft rounded rect, semi-bold (no uppercase/tracking!) */
        #staffGrid .profile-badges {
            gap: 8px;
            margin-bottom: 14px;
        }
        #staffGrid .profile-badge {
            padding: 5px 11px;
            border-radius: 8px;
            font-size: 0.74rem;
            font-weight: 600;
            text-transform: none;
            letter-spacing: 0;
            border: 1px solid transparent;
        }
        #staffGrid .badge-role-staff {
            background: rgba(14, 165, 233, 0.08);
            color: #0369a1;
            border-color: rgba(14, 165, 233, 0.18);
        }
        #staffGrid .badge-active {
            background: rgba(16, 185, 129, 0.09);
            color: #047857;
            border-color: rgba(16, 185, 129, 0.2);
        }
        #staffGrid .badge-inactive {
            background: rgba(148, 163, 184, 0.12);
            color: #475569;
            border-color: rgba(148, 163, 184, 0.2);
        }

        #staffGrid .profile-meta {
            font-size: 0.78rem;
            color: #64748b;
            font-weight: 500;
        }
        #staffGrid .profile-meta i { color: #94a3b8; font-size: 0.72rem; }

        /* ── Footer & action buttons ── */
        #staffGrid .profile-card-footer {
            grid-area: footer;
            display: flex;
            gap: 10px;
            padding: 14px 22px 20px 20px;
            border-top: 1px solid rgba(148,163,184,0.12);
            align-items: center;
            background: linear-gradient(180deg, rgba(248,250,252,0.0) 0%, rgba(248,250,252,0.8) 100%);
        }

        /* Inline forms: the FIRST form is the flex item (holds the wide toggle);
           the rest (delete form) use display:contents so their button flows directly. */
        #staffGrid .profile-card-footer .inline-form:first-child {
            flex: 1 1 auto;
            display: block;
        }
        #staffGrid .profile-card-footer .inline-form:not(:first-child) {
            display: contents;
        }
        /* The primary toggle button must fill its wrapping form.
           Explicit width:100% overrides any inherited icon-only sizing from shared css. */
        #staffGrid .profile-card-footer .inline-form:first-child > .card-action.toggle {
            width: 100%;
        }

        /* ALL action buttons: semantic color in BASE state (not only on hover).
           Users shouldn't have to GUESS what each button does. */
        #staffGrid .card-action {
            height: 40px;
            border-radius: 11px;
            border: 1.5px solid transparent;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            font-size: 0.82rem;
            font-weight: 600;
            transition: transform 0.18s ease, box-shadow 0.18s ease,
                        background 0.18s ease, border-color 0.18s ease, color 0.18s ease;
            outline: none;
            text-decoration: none;
        }
        #staffGrid .card-action:focus-visible {
            box-shadow: 0 0 0 3px rgba(14,165,233,0.25);
        }

        /* PRIMARY: Toggle Status — wide, filled, dominant (matches role rail color) */
        #staffGrid .card-action.toggle {
            width: auto;
            min-width: 0;
            padding: 0 14px;
            color: #ffffff;
            background: linear-gradient(180deg, #0284c7 0%, #0369a1 100%);
            border-color: #0369a1;
            box-shadow:
                0 1px 0 rgba(255,255,255,0.2) inset,
                0 -1px 0 rgba(0,0,0,0.14) inset,
                0 6px 14px -8px rgba(2,132,199,0.55);
            flex: 1 1 auto;
            max-width: 100%;
            white-space: nowrap;
            overflow: visible;
            text-overflow: clip;
            justify-content: center;
            font-size: 0.84rem;
            letter-spacing: 0.01em;
        }
        #staffGrid .card-action.toggle i {
            margin-right: 0;
        }
        #staffGrid .card-action.toggle:hover {
            transform: translateY(-1.5px);
            background: linear-gradient(180deg, #0ea5e9 0%, #0284c7 100%);
            border-color: #0284c7;
            box-shadow:
                0 1px 0 rgba(255,255,255,0.22) inset,
                0 -1px 0 rgba(0,0,0,0.14) inset,
                0 10px 20px -9px rgba(2,132,199,0.65);
            color: #ffffff;
        }
        #staffGrid .card-action.toggle:active {
            transform: translateY(0);
        }
        #staffGrid .profile-card.status-inactive .card-action.toggle {
            background: linear-gradient(180deg, #10b981 0%, #059669 100%);
            border-color: #059669;
            box-shadow:
                0 1px 0 rgba(255,255,255,0.2) inset,
                0 -1px 0 rgba(0,0,0,0.14) inset,
                0 6px 14px -8px rgba(16,185,129,0.55);
        }
        #staffGrid .profile-card.status-inactive .card-action.toggle:hover {
            background: linear-gradient(180deg, #34d399 0%, #10b981 100%);
            border-color: #10b981;
        }

        /* SECONDARY: Edit — blue-tinted chip (always visible, not only on hover) */
        #staffGrid .card-action.edit {
            width: 40px;
            color: #2563eb;
            background: #eff6ff;
            border-color: #bfdbfe;
        }
        #staffGrid .card-action.edit:hover {
            background: #dbeafe;
            border-color: #60a5fa;
            color: #1d4ed8;
            transform: translateY(-1.5px);
            box-shadow: 0 6px 12px -6px rgba(37,99,235,0.4);
        }

        /* DESTRUCTIVE: Delete — rose-tinted, visually set back, clearly destructive */
        #staffGrid .card-action.delete {
            width: 40px;
            color: #e11d48;
            background: #fff1f2;
            border-color: #fecdd3;
        }
        #staffGrid .card-action.delete:hover {
            background: #ffe4e6;
            border-color: #fda4af;
            color: #be123c;
            transform: translateY(-1.5px);
            box-shadow: 0 6px 12px -6px rgba(225,29,72,0.4);
        }

        /* ── Delete-history cards: same file-folder treatment, muted palette ── */
        .deleted-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 16px;
        }
        .deleted-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 18px 18px 18px 22px;
            border: 1px solid rgba(148,163,184,0.18);
            border-left: 5px solid #94a3b8;
            box-shadow:
                0 10px 22px -16px rgba(15,23,42,0.18),
                0 3px 7px -5px rgba(15,23,42,0.08);
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 16px;
            align-items: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .deleted-card:hover {
            transform: translateY(-2px);
            border-left-color: #64748b;
            box-shadow:
                0 16px 30px -18px rgba(15,23,42,0.26),
                0 6px 12px -8px rgba(15,23,42,0.12);
        }
        .deleted-avatar {
            width: 48px; height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, #64748b, #94a3b8);
            color: #ffffff;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            box-shadow:
                0 1px 0 rgba(255,255,255,0.3) inset,
                0 -1px 0 rgba(0,0,0,0.1) inset;
        }
        .deleted-name {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            color: #0f172a;
            letter-spacing: -0.01em;
            margin-bottom: 4px;
        }
        .deleted-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            font-size: 0.76rem;
            color: #64748b;
            font-weight: 500;
        }
        .deleted-by-chip {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 9px;
            background: rgba(148,163,184,0.12);
            border-radius: 999px;
            color: #475569;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .deleted-by-chip i { color: #94a3b8; font-size: 0.68rem; }
        .deleted-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .deleted-actions .card-action.restore {
            width: 38px; height: 38px;
            color: #047857;
            background: #ecfdf5;
            border-color: #a7f3d0;
        }
        .deleted-actions .card-action.restore:hover {
            background: #d1fae5;
            border-color: #6ee7b7;
            color: #065f46;
            transform: translateY(-1.5px);
            box-shadow: 0 6px 12px -6px rgba(16,185,129,0.38);
        }
        .deleted-actions .card-action.delete {
            width: 38px; height: 38px;
            color: #9f1239;
            background: #fff1f2;
            border-color: #fecdd3;
        }
        .deleted-actions .card-action.delete:hover:not([disabled]) {
            background: #ffe4e6;
            border-color: #fda4af;
            color: #881337;
            transform: translateY(-1.5px);
            box-shadow: 0 6px 12px -6px rgba(225,29,72,0.38);
        }
        .deleted-actions .card-action.delete[disabled] {
            opacity: 0.55;
            cursor: not-allowed;
        }
        .countdown-chip {
            font-family: 'Inter', monospace;
            font-variant-numeric: tabular-nums;
            font-size: 0.68rem;
            color: #94a3b8;
            font-weight: 600;
            padding: 4px 9px;
            background: rgba(148,163,184,0.1);
            border-radius: 8px;
            white-space: nowrap;
        }

        @media (max-width: 640px) {
            .deleted-card {
                grid-template-columns: auto 1fr;
                grid-template-rows: auto auto;
            }
            .deleted-actions {
                grid-column: 1 / -1;
                justify-content: flex-start;
                padding-top: 4px;
                border-top: 1px dashed rgba(148,163,184,0.2);
                padding-top: 12px;
            }
        }
    </style>
    <script src="scripts/csrf-fetch.js"></script>
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
                         <form method="POST" class="inline-form">
                    <?php echo csrf_field(); ?><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">                    <button type="submit" class="card-action toggle" title="Toggle Status"><i class="fas fa-power-off"></i> <?php echo $staff['status']==='active'?'Deactivate':'Activate'; ?></button></form>
                        <button type="button" class="card-action edit edit-staff-btn" data-staff-id="<?php echo $staff['id']; ?>" data-name="<?php echo htmlspecialchars($staff['name']); ?>" data-email="<?php echo htmlspecialchars($staff['email']); ?>" data-status="<?php echo $staff['status']; ?>" title="Edit"><i class="fas fa-pen"></i></button>
                        <form method="POST" class="inline-form" onsubmit="return confirm('Delete this staff member?')">
                    <?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_staff"><input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>"><button type="submit" class="card-action delete" title="Delete"><i class="fas fa-trash"></i></button></form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Delete History -->
        <div class="mgmt-tab-panel <?php echo $current_tab==='delete_history'?'active':''; ?>">
            <div class="mgmt-section-label mgmt-section-label--mb"><i class="fas fa-history"></i> Deleted Staff Archive</div>
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
                        <form method="POST" class="inline-form" onsubmit="return confirm('Restore?')">
                    <?php echo csrf_field(); ?><input type="hidden" name="action" value="restore_staff"><input type="hidden" name="deleted_staff_id" value="<?php echo $ds['id']; ?>"><button type="submit" class="card-action restore" title="Restore"><i class="fas fa-undo"></i></button></form>
                        <form method="POST" class="inline-form" onsubmit="return confirm('Permanently delete? Cannot be undone.')">
                    <?php echo csrf_field(); ?><input type="hidden" name="action" value="permanent_delete_staff"><input type="hidden" name="deleted_staff_id" value="<?php echo $ds['id']; ?>"><button type="submit" class="card-action delete perm-delete-btn" data-deleted-at="<?php echo $ds['deleted_at']; ?>" data-min-days="10" <?php echo $canPerm?'':'disabled'; ?> title="Permanent Delete"><i class="fas fa-skull"></i></button></form>
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
                    <?php echo csrf_field(); ?>
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
                    <?php echo csrf_field(); ?>
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
});
</script>
</body>
</html>


