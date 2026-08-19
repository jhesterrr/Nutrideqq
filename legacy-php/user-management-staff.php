<?php
require_once __DIR__ . '/session_bootstrap.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['user_role'] !== 'staff' && $_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'user')) {
    header("Location: login-logout/NutriDeqN-Login.php");
    exit();
}
$staff_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['user_role'] === 'admin');
$is_user = ($_SESSION['user_role'] === 'user');
$deleted_by = $is_admin ? 'admin' : ($is_user ? 'user' : 'staff');
$deleted_by_id = $staff_id;

$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];
$user_role = $_SESSION['user_role'];

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
    'new_this_month' => 0,
    'deleted_clients' => 0
];
$error = '';
$success = '';

// Handle form actions for client management
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_client':
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone'] ?? '');
                $age = $_POST['age'] ?? null;
                $gender = $_POST['gender'] ?? '';
                $weight = $_POST['weight'] ?? null;
                $height = $_POST['height'] ?? null;
                $health_conditions = trim($_POST['health_conditions'] ?? '');
                $dietary_restrictions = trim($_POST['dietary_restrictions'] ?? '');
                $goals = trim($_POST['goals'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $city = trim($_POST['city'] ?? '');
                $state = trim($_POST['state'] ?? '');
                $zip_code = trim($_POST['zip_code'] ?? '');
                $waist_circumference = $_POST['waist_circumference'] ?? null;
                $hip_circumference = $_POST['hip_circumference'] ?? null;
                $account_password = $_POST['account_password'] ?? '';
                $account_confirm_password = $_POST['account_confirm_password'] ?? '';
                $desired_password = '';
                if (!empty($account_password)) {
                    if ($account_password !== $account_confirm_password) {
                        $error = "Account password and confirm password do not match";
                        break;
                    }
                    $desired_password = $account_password;
                }

                if (empty($name) || empty($email)) {
                    $error = "Name and email are required";
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = "Invalid email format";
                } else {
                    $check_stmt = $conn->prepare("SELECT id FROM clients WHERE email = ?");
                    $check_stmt->execute([$email]);
                    if ($check_stmt->fetch()) {
                        $error = "Client with this email already exists";
                    } else {
                        try {
                            $conn->beginTransaction();

                            $insert_stmt = $conn->prepare("
                                INSERT INTO clients (user_id, name, email, phone, address, city, state, zip_code, age, gender, weight, height, waist_circumference, hip_circumference, health_conditions, dietary_restrictions, goals, status, staff_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)
                            ");

                            if (
                                $insert_stmt->execute([
                                    null,
                                    $name,
                                    $email,
                                    $phone,
                                    $address,
                                    $city,
                                    $state,
                                    $zip_code,
                                    $age,
                                    $gender,
                                    $weight,
                                    $height,
                                    $waist_circumference,
                                    $hip_circumference,
                                    $health_conditions,
                                    $dietary_restrictions,
                                    $goals,
                                    $staff_id
                                ])
                            ) {
                                $client_id_new = $conn->lastInsertId();
                                $user_check = $conn->prepare('SELECT id FROM users WHERE email = ?');
                                $user_check->execute([$email]);
                                $existing_user = $user_check->fetch();
                                $linked_user_id = null;
                                if ($existing_user && isset($existing_user['id'])) {
                                    $linked_user_id = (int) $existing_user['id'];
                                    if (!empty($desired_password)) {
                                        $hashed = password_hash($desired_password, PASSWORD_DEFAULT);
                                        $upd_user = $conn->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');
                                        $upd_user->execute([$hashed, $linked_user_id]);
                                    }
                                } else {
                                    $to_hash = !empty($desired_password) ? $desired_password : bin2hex(random_bytes(8));
                                    $hashed = password_hash($to_hash, PASSWORD_DEFAULT);
                                    $ins_user = $conn->prepare('INSERT INTO users (name, email, password, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
                                    $ins_user->execute([$name, $email, $hashed, 'regular', 'active']);
                                    $linked_user_id = (int) $conn->lastInsertId();
                                }
                                if (!empty($linked_user_id)) {
                                    $upd = $conn->prepare('UPDATE clients SET user_id = ? WHERE id = ?');
                                    $upd->execute([$linked_user_id, $client_id_new]);
                                }
                                $conn->commit();
                                $_SESSION['success'] = "Client added successfully";
                                header("Location: user-management-staff.php");
                                exit();
                            } else {
                                $conn->rollBack();
                                $error = "Failed to add client";
                            }
                        } catch (PDOException $e) {
                            $conn->rollBack();
                            $error = "Database error: " . $e->getMessage();
                        }
                    }
                }
                break;

            case 'update_client':
                $client_id = $_POST['client_id'];
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $age = $_POST['age'] ?? null;
                $weight = $_POST['weight'] ?? null;
                $height = $_POST['height'] ?? null;
                $waist_circumference = $_POST['waist_circumference'] ?? null;
                $hip_circumference = $_POST['hip_circumference'] ?? null;
                $address = trim($_POST['address'] ?? '');
                $city = trim($_POST['city'] ?? '');
                $state = trim($_POST['state'] ?? '');
                $zip_code = trim($_POST['zip_code'] ?? '');
                $health_conditions = trim($_POST['health_conditions'] ?? '');
                $dietary_restrictions = trim($_POST['dietary_restrictions'] ?? '');
                $goals = trim($_POST['goals'] ?? '');
                $notes = trim($_POST['notes'] ?? '');

                try {
                    if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $error = "Please provide valid name and email";
                        break;
                    }
                    $dupe = $conn->prepare("SELECT id FROM clients WHERE email = ? AND id != ?");
                    $dupe->execute([$email, $client_id]);
                    if ($dupe->fetch()) {
                        $error = "Client with this email already exists";
                        break;
                    }
                    $update_sql = "UPDATE clients SET name = ?, email = ?, phone = ?, age = ?, weight = ?, height = ?, waist_circumference = ?, hip_circumference = ?, address = ?, city = ?, state = ?, zip_code = ?, health_conditions = ?, dietary_restrictions = ?, goals = ?, notes = ?, updated_at = NOW() WHERE id = ?";
                    $update_params = [
                        $name,
                        $email,
                        $phone,
                        $age,
                        $weight,
                        $height,
                        $waist_circumference,
                        $hip_circumference,
                        $address,
                        $city,
                        $state,
                        $zip_code,
                        $health_conditions,
                        $dietary_restrictions,
                        $goals,
                        $notes,
                        $client_id
                    ];

                    if (!$is_admin) {
                        $update_sql .= " AND staff_id = ?";
                        $update_params[] = $staff_id;
                    }

                    $update_stmt = $conn->prepare($update_sql);

                    if ($update_stmt->execute($update_params)) {
                        $link_check = $conn->prepare('SELECT user_id FROM clients WHERE id = ?');
                        $link_check->execute([$client_id]);
                        $row = $link_check->fetch();
                        $current_user_id = $row['user_id'] ?? null;
                        $user_check = $conn->prepare('SELECT id FROM users WHERE email = ?');
                        $user_check->execute([$email]);
                        $existing_user = $user_check->fetch();
                        $target_user_id = null;
                        if ($existing_user && isset($existing_user['id'])) {
                            $target_user_id = (int) $existing_user['id'];
                            $dup = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
                            $dup->execute([$email, $target_user_id]);
                            if (!$dup->fetch()) {
                                $upd_user = $conn->prepare('UPDATE users SET name = ?, email = ?, updated_at = NOW() WHERE id = ?');
                                $upd_user->execute([$name, $email, $target_user_id]);
                            }
                        } else {
                            $tmpPassword = bin2hex(random_bytes(8));
                            $hashed = password_hash($tmpPassword, PASSWORD_DEFAULT);
                            $ins_user = $conn->prepare('INSERT INTO users (name, email, password, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
                            $ins_user->execute([$name, $email, $hashed, 'regular', 'active']);
                            $target_user_id = (int) $conn->lastInsertId();
                        }
                        if (empty($current_user_id) || $current_user_id != $target_user_id) {
                            $upd = $conn->prepare('UPDATE clients SET user_id = ? WHERE id = ?');
                            $upd->execute([$target_user_id, $client_id]);
                        }
                        $_SESSION['success'] = "Client updated successfully";
                        header("Location: user-management-staff.php");
                        exit();
                    } else {
                        $error = "Failed to update client";
                    }
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
                break;

            case 'toggle_client_status':
                $client_id = $_POST['client_id'];
                try {
                    $toggle_sql = "UPDATE clients SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?";
                    $toggle_params = [$client_id];
                    if (!$is_admin) {
                        $toggle_sql .= " AND staff_id = ?";
                        $toggle_params[] = $staff_id;
                    }
                    $stmt = $conn->prepare($toggle_sql);
                    $stmt->execute($toggle_params);

                    $sel_sql = "SELECT status, user_id FROM clients WHERE id = ?";
                    $sel_params = [$client_id];
                    if (!$is_admin) {
                        $sel_sql .= " AND staff_id = ?";
                        $sel_params[] = $staff_id;
                    }
                    $sel = $conn->prepare($sel_sql);
                    $sel->execute($sel_params);
                    $c = $sel->fetch();
                    if ($c && !empty($c['user_id'])) {
                        $updUserStatus = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
                        $updUserStatus->execute([$c['status'], $c['user_id']]);
                    }
                    $_SESSION['success'] = "Client status updated successfully";
                    header("Location: user-management-staff.php");
                    exit();
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
                break;

            case 'delete_client':
                $client_id = $_POST['client_id'];

                try {
                    $conn->beginTransaction();

                    // Get client data before deletion
                    $sel_sql = "SELECT * FROM clients WHERE id = ?";
                    $sel_params = [$client_id];
                    if (!$is_admin) {
                        $sel_sql .= " AND staff_id = ?";
                        $sel_params[] = $staff_id;
                    }
                    $select_stmt = $conn->prepare($sel_sql);
                    $select_stmt->execute($sel_params);
                    $client = $select_stmt->fetch();

                    if ($client) {
                        // Insert into deleted_clients table with correct column names
                        $insert_stmt = $conn->prepare("\n    INSERT INTO deleted_clients (\n        original_id, user_id, staff_id, deleted_by, \n        deleted_by_user_id, deleted_by_name,\n        name, email, phone, address, city, state, zip_code,\n        age, gender, weight, height, waist_circumference,\n        hip_circumference, health_conditions, dietary_restrictions,\n        goals, notes, status, created_at, updated_at, deleted_at\n    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())\n            ");

                        $insert_stmt->execute([
                            $client['id'],
                            $client['user_id'],
                            $client['staff_id'],
                            $deleted_by,
                            $deleted_by_id,
                            $user_name,
                            $client['name'],
                            $client['email'],
                            $client['phone'],
                            $client['address'],
                            $client['city'],
                            $client['state'],
                            $client['zip_code'],
                            $client['age'],
                            $client['gender'],
                            $client['weight'],
                            $client['height'],
                            $client['waist_circumference'],
                            $client['hip_circumference'],
                            $client['health_conditions'],
                            $client['dietary_restrictions'],
                            $client['goals'],
                            $client['notes'],
                            $client['status'],
                            $client['created_at'],
                            $client['updated_at']
                        ]);

                        if (!empty($client['user_id'])) {
                            try {
                                $updUser = $conn->prepare("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id = ?");
                                $updUser->execute([$client['user_id']]);
                            } catch (PDOException $e) {
                            }
                        }
                        $del_sql = "UPDATE clients SET status = 'deleted', updated_at = NOW() WHERE id = ?";
                        $del_params = [$client_id];
                        if (!$is_admin) {
                            $del_sql .= " AND staff_id = ?";
                            $del_params[] = $staff_id;
                        }
                        $delete_stmt = $conn->prepare($del_sql);
                        $delete_stmt->execute($del_params);

                        $conn->commit();
                        $_SESSION['success'] = "Client moved to delete history successfully";
                    } else {
                        $error = "Client not found or you don't have permission to delete this client";
                    }

                    header("Location: user-management-staff.php");
                    exit();
                } catch (PDOException $e) {
                    $conn->rollBack();
                    $error = "Database error: " . $e->getMessage();
                }
                break;

            case 'restore_client':
                $deleted_client_id = $_POST['deleted_client_id'];

                try {
                    $conn->beginTransaction();

                    // Get deleted client data for clients belonging to this staff
                    $sel_sql = "SELECT * FROM deleted_clients WHERE id = ?";
                    $sel_params = [$deleted_client_id];
                    if (!$is_admin) {
                        $sel_sql .= " AND staff_id = ?";
                        $sel_params[] = $staff_id;
                    }
                    $select_stmt = $conn->prepare($sel_sql);
                    $select_stmt->execute($sel_params);
                    $client = $select_stmt->fetch();

                    if ($client) {
                        // If a client row with same original_id exists, update it; else insert
                        $exists_stmt = $conn->prepare("SELECT id FROM clients WHERE id = ?");
                        $exists_stmt->execute([$client['original_id']]);
                        $exists = $exists_stmt->fetch();

                        if ($exists) {
                            // Update existing row back to original data
                            $update_stmt = $conn->prepare("
                                UPDATE clients SET
                                    user_id = ?, staff_id = ?, name = ?, email = ?, phone = ?, address = ?, city = ?, state = ?, zip_code = ?,
                                    age = ?, gender = ?, weight = ?, height = ?, waist_circumference = ?, hip_circumference = ?,
                                    health_conditions = ?, dietary_restrictions = ?, goals = ?, notes = ?, status = ?,
                                    updated_at = NOW(), restored_at = NOW()
                                WHERE id = ?
                            ");
                            $update_stmt->execute([
                                $client['user_id'],
                                $client['staff_id'],
                                $client['name'],
                                $client['email'],
                                $client['phone'],
                                $client['address'],
                                $client['city'],
                                $client['state'],
                                $client['zip_code'],
                                $client['age'],
                                $client['gender'],
                                $client['weight'],
                                $client['height'],
                                $client['waist_circumference'],
                                $client['hip_circumference'],
                                $client['health_conditions'],
                                $client['dietary_restrictions'],
                                $client['goals'],
                                $client['notes'],
                                $client['status'],
                                $client['original_id']
                            ]);
                        } else {
                            // Check duplicate email in other records (exclude this original_id)
                            $check_stmt = $conn->prepare("SELECT id FROM clients WHERE email = ? AND id != ?");
                            $check_stmt->execute([$client['email'], $client['original_id']]);
                            if ($check_stmt->fetch()) {
                                $conn->rollBack();
                                $error = "A client with this email already exists. Cannot restore.";
                                break;
                            }
                            // Insert back into clients table
                            $insert_stmt = $conn->prepare("
                                INSERT INTO clients (
                                    id, user_id, staff_id, name, email, phone, address, city, state, zip_code,
                                    age, gender, weight, height, waist_circumference, hip_circumference,
                                    health_conditions, dietary_restrictions, goals, notes, status,
                                    created_at, updated_at, restored_at
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                            ");
                            $insert_stmt->execute([
                                $client['original_id'],
                                $client['user_id'],
                                $client['staff_id'],
                                $client['name'],
                                $client['email'],
                                $client['phone'],
                                $client['address'],
                                $client['city'],
                                $client['state'],
                                $client['zip_code'],
                                $client['age'],
                                $client['gender'],
                                $client['weight'],
                                $client['height'],
                                $client['waist_circumference'],
                                $client['hip_circumference'],
                                $client['health_conditions'],
                                $client['dietary_restrictions'],
                                $client['goals'],
                                $client['notes'],
                                $client['status'],
                                $client['created_at'],
                                $client['updated_at']
                            ]);
                        }

                        // Remove from deleted_clients table
                        $delete_stmt = $conn->prepare("DELETE FROM deleted_clients WHERE id = ?");
                        $delete_stmt->execute([$deleted_client_id]);

                        $conn->commit();
                        $_SESSION['success'] = "Client restored successfully";
                        if (!empty($client['user_id'])) {
                            try {
                                $updUser = $conn->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
                                $updUser->execute([$client['status'], $client['user_id']]);
                            } catch (PDOException $e) {
                            }
                        }
                    } else {
                        $error = "Client not found in delete history";
                    }

                    header("Location: user-management-staff.php?tab=delete_history");
                    exit();
                } catch (PDOException $e) {
                    $conn->rollBack();
                    $error = "Database error: " . $e->getMessage();
                }
                break;
        }
    }
}

// Handle GET parameters for tabs
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'active_clients';

try {
    // Get client statistics for the logged-in staff member
    $stats_sql = "SELECT 
        COUNT(*) as total_clients,
        SUM(status = 'active') as active_clients,
        SUM(created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as new_this_month
        FROM clients";
    $stats_params = [];
    if (!$is_admin) {
        $stats_sql .= " WHERE staff_id = ?";
        $stats_params[] = $staff_id;
    }
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->execute($stats_params);
    $stats_data = $stats_stmt->fetch();
    $stats['total_clients'] = $stats_data['total_clients'] ?? 0;
    $stats['active_clients'] = $stats_data['active_clients'] ?? 0;
    $stats['new_this_month'] = $stats_data['new_this_month'] ?? 0;

    // Get deleted clients count for this staff
    $del_stats_sql = "SELECT COUNT(*) as deleted_count FROM deleted_clients";
    $del_stats_params = [];
    if (!$is_admin) {
        $del_stats_sql .= " WHERE staff_id = ?";
        $del_stats_params[] = $staff_id;
    }
    $deleted_stats_stmt = $conn->prepare($del_stats_sql);
    $deleted_stats_stmt->execute($del_stats_params);
    $deleted_stats = $deleted_stats_stmt->fetch();
    $stats['deleted_clients'] = $deleted_stats['deleted_count'] ?? 0;

    // Get active clients assigned to this staff
    $clients_sql = "SELECT * FROM clients WHERE ";
    $clients_params = [];
    if ($is_admin) {
        $clients_sql .= "id NOT IN (SELECT original_id FROM deleted_clients)";
    } else {
        $clients_sql .= "staff_id = ? AND id NOT IN (SELECT original_id FROM deleted_clients)";
        $clients_params[] = $staff_id;
    }
    $clients_sql .= " ORDER BY created_at DESC";
    $clients_stmt = $conn->prepare($clients_sql);
    $clients_stmt->execute($clients_params);
    $clients = $clients_stmt->fetchAll();

    // Get deleted clients deleted by this staff
    $deleted_clients_sql = "SELECT * FROM deleted_clients";
    $deleted_clients_params = [];
    if (!$is_admin) {
        $deleted_clients_sql .= " WHERE staff_id = ?";
        $deleted_clients_params[] = $staff_id;
    }
    $deleted_clients_sql .= " ORDER BY deleted_at DESC";
    $deleted_clients_stmt = $conn->prepare($deleted_clients_sql);
    $deleted_clients_stmt->execute($deleted_clients_params);
    $deleted_clients = $deleted_clients_stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    // For debugging
    error_log("Database error in user-management-staff.php: " . $e->getMessage());
}
require_once 'navigation.php';
$nav_links_array = getNavigationLinks($user_role, 'user-management-staff.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="scripts/theme-toggle.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <title>NutriDeq – Client Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/responsive.css">
    <link rel="stylesheet" href="css/logout-modal.css">
    <!-- Platform Specific Styles -->
    <link rel="stylesheet" href="css/desktop-style.css" media="all and (min-width: 1025px)">
    <link rel="stylesheet" href="css/mobile-style.css" media="all and (max-width: 1024px)">
    <link rel="stylesheet" href="css/premium-management.css">
    <script src="scripts/dashboard.js" defer></script>
    <style>
        /* Client-specific card colours: clients = emerald */
        .profile-card.role-client .profile-card-accent { background: linear-gradient(90deg,#064e3b,#34d399); }
        .profile-avatar.role-client { background: linear-gradient(135deg,#064e3b,#10b981); }
        .profile-card.role-client::before { background: radial-gradient(circle,rgba(16,185,129,0.08) 0%,transparent 70%); }
        /* BMI badges */
        .bmi-pill { padding: 2px 9px; border-radius: 50px; font-size: 0.68rem; font-weight: 700; font-family:'Inter',sans-serif; }
        .bmi-normal      { background:rgba(16,185,129,0.12); color:#059669; }
        .bmi-underweight { background:rgba(99,102,241,0.12); color:#6366f1; }
        .bmi-overweight  { background:rgba(245,158,11,0.12); color:#d97706; }
        .bmi-obese       { background:rgba(244,63,94,0.12);  color:#f43f5e; }
        /* Restore button */
        .card-action.restore-client { }
        .card-action.restore-client:hover { background:#f0fdf4; border-color:#10b981; color:#10b981; }
        /* Status chip inside card */
        .status-chip { padding:4px 11px; border-radius:50px; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; font-family:'Inter',sans-serif; }
        .status-chip.active   { background:rgba(16,185,129,0.12); color:#059669; }
        .status-chip.inactive { background:rgba(107,114,128,0.1); color:#6b7280; }
        .status-chip.deleted  { background:rgba(244,63,94,0.1);  color:#f43f5e; }
        /* Deleted card — rose accent */
        .deleted-card { border-left-color: #f43f5e !important; }
        /* Client metric in card */
        .client-metric-row { display:flex; gap:12px; margin-bottom:12px; flex-wrap:wrap; }
        .client-metric { display:flex; align-items:center; gap:5px; font-size:0.78rem; color:#6b7280; }
        .client-metric i { color:#10b981; font-size:0.72rem; }
    </style>
    <style>
        /* ═══ NutriDeq Premium Modal System ═══ */

        /* Backdrop */
        .premium-modal-overlay { display:none; position:fixed; inset:0; background:rgba(3,26,18,0.55); backdrop-filter:blur(8px); z-index:9999; align-items:center; justify-content:center; padding:16px; }
        .premium-modal-overlay.active { display:flex; }

        /* Modal shell */
        .ndq-modal { background:#fff; border-radius:24px; box-shadow:0 32px 80px rgba(0,0,0,0.22),0 8px 24px rgba(0,0,0,0.1); width:100%; max-height:92vh; height:92vh; display:flex; flex-direction:column; animation:ndqModalIn 0.35s cubic-bezier(0.34,1.56,0.64,1) both; }
        @keyframes ndqModalIn { from { opacity:0; transform:scale(0.88) translateY(24px); } to { opacity:1; transform:scale(1) translateY(0); } }

        /* Header */
        .ndq-modal-header { display:flex; align-items:center; gap:16px; padding:24px 28px 20px; background:linear-gradient(135deg,#064e3b 0%,#065f46 50%,#047857 100%); flex-shrink:0; position:relative; overflow:hidden; }
        .ndq-modal-header::before { content:''; position:absolute; top:-60px; right:-60px; width:200px; height:200px; background:radial-gradient(circle,rgba(167,243,208,0.2) 0%,transparent 70%); border-radius:50%; pointer-events:none; }
        .ndq-modal-header::after  { content:''; position:absolute; bottom:-40px; left:20%; width:150px; height:150px; background:radial-gradient(circle,rgba(52,211,153,0.15) 0%,transparent 70%); border-radius:50%; pointer-events:none; }
        .ndq-modal-header-icon { width:52px; height:52px; border-radius:16px; background:rgba(255,255,255,0.15); backdrop-filter:blur(8px); border:1px solid rgba(255,255,255,0.25); display:flex; align-items:center; justify-content:center; font-size:1.3rem; color:#a7f3d0; flex-shrink:0; position:relative; z-index:1; }
        .ndq-modal-title { font-family:'Outfit',sans-serif; font-size:1.3rem; font-weight:800; color:#fff; line-height:1.2; position:relative; z-index:1; }
        .ndq-modal-subtitle { font-size:0.8rem; color:rgba(167,243,208,0.85); font-weight:500; margin-top:2px; position:relative; z-index:1; }
        .ndq-modal-close { margin-left:auto; width:36px; height:36px; border-radius:10px; border:1px solid rgba(255,255,255,0.2); background:rgba(255,255,255,0.1); color:rgba(255,255,255,0.7); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:0.9rem; transition:all 0.2s; flex-shrink:0; position:relative; z-index:1; }
        .ndq-modal-close:hover { background:rgba(255,255,255,0.2); color:#fff; transform:rotate(90deg); }

        /* Scrollable body */
        .ndq-modal-body {
            overflow-y: auto;
            flex: 1 1 0;
            min-height: 0;
            padding: 24px 28px 8px;
            scroll-behavior: smooth;
            /* Firefox themed scrollbar */
            scrollbar-width: thin;
            scrollbar-color: rgba(5,150,105,0.35) transparent;
        }
        /* WebKit: Chrome / Safari / Edge */
        .ndq-modal-body::-webkit-scrollbar              { width: 5px; }
        .ndq-modal-body::-webkit-scrollbar-track        { background: transparent; margin: 8px 0; }
        .ndq-modal-body::-webkit-scrollbar-thumb        { background: rgba(5,150,105,0.3); border-radius: 99px; }
        .ndq-modal-body::-webkit-scrollbar-thumb:hover  { background: rgba(5,150,105,0.55); }

        /* Section headers */
        .ndq-section { margin-bottom:24px; }
        .ndq-section-label { display:flex; align-items:center; gap:8px; font-family:'Outfit',sans-serif; font-size:0.75rem; font-weight:800; text-transform:uppercase; letter-spacing:0.08em; color:#059669; margin-bottom:14px; padding-bottom:8px; border-bottom:1.5px solid rgba(5,150,105,0.12); }
        .ndq-section-label i { width:24px; height:24px; background:rgba(5,150,105,0.1); border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:0.72rem; color:#059669; }

        /* Field grid */
        .ndq-field-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px 16px; }
        .ndq-field { display:flex; flex-direction:column; gap:5px; }
        .ndq-field.full { grid-column:span 2; }
        .ndq-field label { font-size:0.75rem; font-weight:700; color:#374151; font-family:'Inter',sans-serif; text-transform:uppercase; letter-spacing:0.04em; }
        .ndq-field label .req { color:#f43f5e; margin-left:2px; }

        /* Input wrapper with icon */
        .ndq-input-wrap { position:relative; display:flex; align-items:center; }
        .ndq-input-wrap > i:first-child { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:#9ca3af; font-size:0.8rem; pointer-events:none; z-index:1; transition:color 0.2s; }
        .ndq-input-wrap input,
        .ndq-input-wrap select { width:100%; padding:11px 14px 11px 38px; border:1.5px solid #e5e7eb; border-radius:11px; font-size:0.88rem; font-family:'Inter',sans-serif; color:#111827; background:#f9fafb; outline:none; transition:all 0.2s; }
        .ndq-input-wrap input:focus,
        .ndq-input-wrap select:focus { border-color:#059669; background:#fff; box-shadow:0 0 0 3px rgba(5,150,105,0.1); }
        .ndq-input-wrap:focus-within > i:first-child { color:#059669; }

        /* Unit badge */
        .ndq-unit { position:absolute; right:12px; top:50%; transform:translateY(-50%); font-size:0.72rem; font-weight:700; color:#9ca3af; background:#f3f4f6; padding:2px 7px; border-radius:6px; pointer-events:none; }

        /* Textarea variant */
        .ndq-textarea-wrap { align-items:flex-start; }
        .ndq-textarea-wrap > i:first-child { top:13px; transform:none; }
        .ndq-textarea-wrap textarea { width:100%; padding:11px 14px 11px 38px; border:1.5px solid #e5e7eb; border-radius:11px; font-size:0.88rem; font-family:'Inter',sans-serif; color:#111827; background:#f9fafb; outline:none; transition:all 0.2s; resize:vertical; min-height:64px; }
        .ndq-textarea-wrap textarea:focus { border-color:#059669; background:#fff; box-shadow:0 0 0 3px rgba(5,150,105,0.1); }

        /* Sticky footer */
        .ndq-modal-footer { display:flex; align-items:center; justify-content:flex-end; gap:10px; padding:16px 28px 22px; border-top:1.5px solid #f3f4f6; flex-shrink:0; background:#fff; border-radius:0 0 24px 24px; }
        .ndq-btn-cancel { padding:11px 20px; border:1.5px solid #e5e7eb; border-radius:12px; background:transparent; color:#6b7280; font-size:0.85rem; font-weight:600; cursor:pointer; transition:all 0.2s; font-family:'Inter',sans-serif; display:flex; align-items:center; gap:7px; }
        .ndq-btn-cancel:hover { background:#f3f4f6; color:#374151; border-color:#d1d5db; }
        .ndq-btn-submit { padding:11px 24px; border:none; border-radius:12px; background:linear-gradient(135deg,#059669,#047857); color:#fff; font-size:0.85rem; font-weight:700; cursor:pointer; transition:all 0.2s; font-family:'Inter',sans-serif; display:flex; align-items:center; gap:7px; box-shadow:0 4px 14px rgba(5,150,105,0.3); }
        .ndq-btn-submit:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(5,150,105,0.38); }

        /* Responsive */
        @media (max-width:600px) {
            .ndq-modal-header { padding:18px 20px 16px; }
            .ndq-modal-body  { padding:18px 20px 8px; }
            .ndq-modal-footer { padding:14px 20px 18px; }
            .ndq-field-grid { grid-template-columns:1fr; }
            .ndq-field.full { grid-column:span 1; }
        }
    </style>
</head>
<body>
<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
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
                <div class="mgmt-hero-icon"><i class="fas fa-user-injured"></i></div>
                <h1 class="mgmt-hero-title">Client Management</h1>
                <p class="mgmt-hero-subtitle">Manage your clients and their nutritional profiles</p>
            </div>
            <div class="mgmt-hero-right">
                <button class="btn-premium-add" id="addClientBtn"><i class="fas fa-user-plus"></i> Add New Client</button>
            </div>
        </div>

        <!-- Stats -->
        <div class="mgmt-stats">
            <div class="mgmt-stat stat-clients"><div class="mgmt-stat-icon"><i class="fas fa-user-friends"></i></div><div class="mgmt-stat-info"><h4><?php echo $stats['total_clients']; ?></h4><p>Total Clients</p></div></div>
            <div class="mgmt-stat stat-active"><div class="mgmt-stat-icon"><i class="fas fa-heartbeat"></i></div><div class="mgmt-stat-info"><h4><?php echo $stats['active_clients']; ?></h4><p>Active Clients</p></div></div>
            <div class="mgmt-stat stat-staff"><div class="mgmt-stat-icon"><i class="fas fa-chart-line"></i></div><div class="mgmt-stat-info"><h4><?php echo $stats['new_this_month']; ?></h4><p>New This Month</p></div></div>
            <div class="mgmt-stat stat-deleted"><div class="mgmt-stat-icon"><i class="fas fa-archive"></i></div><div class="mgmt-stat-info"><h4><?php echo $stats['deleted_clients']; ?></h4><p>Archived</p></div></div>
        </div>

        <!-- Tabs -->
        <div class="mgmt-tabs">
            <button class="mgmt-tab-btn <?php echo $current_tab==='active_clients'?'active':''; ?>" onclick="window.location='?tab=active_clients'"><i class="fas fa-users"></i> Active Clients</button>
            <button class="mgmt-tab-btn <?php echo $current_tab==='delete_history'?'active':''; ?>" onclick="window.location='?tab=delete_history'"><i class="fas fa-history"></i> Archive <?php if($stats['deleted_clients']>0) echo '<span style="background:#f43f5e;color:white;border-radius:50px;padding:1px 8px;font-size:0.72rem;">'.$stats['deleted_clients'].'</span>'; ?></button>
        </div>

        <!-- ── Active Clients ── -->
        <div class="mgmt-tab-panel <?php echo $current_tab==='active_clients'?'active':''; ?>">
            <div class="mgmt-toolbar">
                <div class="mgmt-section-label"><i class="fas fa-users"></i> My Clients</div>
                <div class="mgmt-search"><i class="fas fa-search"></i><input type="text" placeholder="Search clients..." oninput="filterCards(this.value,'clientGrid')"></div>
            </div>
            <?php if(empty($clients)): ?>
            <div class="mgmt-empty"><div class="mgmt-empty-icon"><i class="fas fa-user-friends"></i></div><h3>No clients yet</h3><p>Click Add New Client to get started.</p></div>
            <?php else: ?>
            <div class="profile-grid" id="clientGrid">
                <?php foreach($clients as $client):
                    $bmi = ($client['height']>0) ? $client['weight']/(($client['height']/100)*($client['height']/100)) : 0;
                    $bmiCat = $bmi<=0?'':($bmi<18.5?'underweight':($bmi<25?'normal':($bmi<30?'overweight':'obese')));
                    $statusClass = in_array($client['status'],['active','inactive','deleted']) ? $client['status'] : 'inactive';
                ?>
                <div class="profile-card role-client status-<?php echo $statusClass; ?>"
                     data-search="<?php echo strtolower($client['name'].' '.$client['email'].' '.($client['status']??'')); ?>">
                    <div class="profile-card-accent"></div>
                    <div class="profile-card-body">
                        <div class="profile-avatar role-client">
                            <?php echo getInitials($client['name']); ?>
                            <span class="status-dot <?php echo $client['status']==='active'?'active-dot':''; ?>"></span>
                        </div>
                        <div class="profile-name"><?php echo htmlspecialchars($client['name']); ?></div>
                        <div class="profile-id">#CLT-<?php echo str_pad($client['id'],4,'0',STR_PAD_LEFT); ?></div>
                        <div class="profile-email"><i class="fas fa-envelope"></i><?php echo htmlspecialchars($client['email']); ?></div>
                        <div class="client-metric-row">
                            <?php if($client['age']): ?><div class="client-metric"><i class="fas fa-birthday-cake"></i><?php echo $client['age']; ?>y</div><?php endif; ?>
                            <?php if($client['gender']): ?><div class="client-metric"><i class="fas fa-venus-mars"></i><?php echo ucfirst($client['gender']); ?></div><?php endif; ?>
                            <?php if($bmi>0): ?><div class="client-metric"><i class="fas fa-weight"></i><?php echo number_format($bmi,1); ?> BMI</div><?php endif; ?>
                        </div>
                        <div class="profile-badges">
                            <span class="profile-badge badge-active"><i class="fas fa-user-injured"></i> Client</span>
                            <span class="status-chip <?php echo $statusClass; ?>"><?php echo ucfirst($statusClass); ?></span>
                            <?php if($bmi>0): ?><span class="bmi-pill bmi-<?php echo $bmiCat; ?>"><?php echo ucfirst($bmiCat); ?></span><?php endif; ?>
                        </div>
                        <?php if($client['goals']): ?>
                        <div class="profile-meta"><i class="fas fa-bullseye"></i><?php echo htmlspecialchars(mb_strimwidth($client['goals'],0,40,'…')); ?></div>
                        <?php else: ?>
                        <div class="profile-meta"><i class="fas fa-calendar-alt"></i> Added <?php echo date('M j, Y',strtotime($client['created_at'])); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="profile-card-footer">
                        <!-- Status toggle -->
                        <form method="POST" class="inline-form" style="flex:1;">
                            <input type="hidden" name="action" value="toggle_client_status">
                            <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                            <button type="submit" class="card-status-toggle <?php echo $statusClass; ?>" title="Toggle Status">
                                <?php echo ucfirst($statusClass); ?>
                            </button>
                        </form>
                        <!-- Edit -->
                        <button type="button" class="card-action edit edit-client-btn"
                            data-client='<?php echo htmlspecialchars(json_encode($client),ENT_QUOTES); ?>'
                            title="Edit Client"><i class="fas fa-pen"></i></button>
                        <!-- View Health Plan -->
                        <button type="button" class="card-action"
                            onclick="window.location.href='anthropometric-information.php?client_id=<?php echo $client['id']; ?>&tab=food-tracker'"
                            title="View Health Plan"
                            style="border-color:#6366f1;color:#6366f1;"
                            onmouseover="this.style.background='#eef2ff'"
                            onmouseout="this.style.background=''">
                            <i class="fas fa-file-medical"></i>
                        </button>
                        <!-- Archive -->
                        <form method="POST" class="inline-form" onsubmit="return confirm('Archive this client? They can be restored from the Archive tab.')">
                            <input type="hidden" name="action" value="delete_client">
                            <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                            <button type="submit" class="card-action delete" title="Archive Client"><i class="fas fa-archive"></i></button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── Archive / Delete History ── -->
        <div class="mgmt-tab-panel <?php echo $current_tab==='delete_history'?'active':''; ?>">
            <div class="mgmt-section-label"><i class="fas fa-history"></i> Archived Clients</div>
            <?php if(empty($deleted_clients)): ?>
            <div class="mgmt-empty"><div class="mgmt-empty-icon"><i class="fas fa-history"></i></div><h3>No archived clients</h3><p>Archived clients will appear here and can be restored.</p></div>
            <?php else: ?>
            <div class="deleted-grid">
                <?php foreach($deleted_clients as $dc): ?>
                <div class="deleted-card">
                    <div class="deleted-avatar"><?php echo getInitials($dc['name']); ?></div>
                    <div class="deleted-info">
                        <div class="deleted-name"><?php echo htmlspecialchars($dc['name']); ?> <span style="font-size:0.75rem;color:#9ca3af;">#CLT-<?php echo str_pad($dc['original_id'],4,'0',STR_PAD_LEFT); ?></span></div>
                        <div class="deleted-meta">
                            <span><?php echo htmlspecialchars($dc['email']); ?></span>
                            <?php if($dc['age']): ?><span><i class="fas fa-birthday-cake" style="color:#d1d5db;"></i> <?php echo $dc['age']; ?>y</span><?php endif; ?>
                            <div class="deleted-by-chip"><i class="fas fa-user-times"></i><?php echo htmlspecialchars($dc['deleted_by_name']??ucfirst($dc['deleted_by']??'Staff')); ?></div>
                            <span><?php echo date('M j, Y',strtotime($dc['deleted_at'])); ?></span>
                        </div>
                    </div>
                    <div class="deleted-actions">
                        <form method="POST" class="inline-form" onsubmit="return confirm('Restore this client?')">
                            <input type="hidden" name="action" value="restore_client">
                            <input type="hidden" name="deleted_client_id" value="<?php echo $dc['id']; ?>">
                            <button type="submit" class="card-action restore restore-client" title="Restore"><i class="fas fa-undo"></i></button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
    </main>
</div>

<!-- ── Add Client Modal ── -->
<div class="premium-modal-overlay" id="addClientModal">
    <div class="premium-modal ndq-modal" style="max-width:640px;padding:0;">

        <!-- Modal Header -->
        <div class="ndq-modal-header">
            <div class="ndq-modal-header-icon"><i class="fas fa-user-plus"></i></div>
            <div>
                <div class="ndq-modal-title">Add New Client</div>
                <div class="ndq-modal-subtitle">Fill in the client details below</div>
            </div>
            <button class="ndq-modal-close" onclick="document.getElementById('addClientModal').classList.remove('active');document.body.style.overflow='auto'"><i class="fas fa-times"></i></button>
        </div>

        <form method="POST" id="addClientForm">
            <input type="hidden" name="action" value="add_client">

            <div class="ndq-modal-body">

                <!-- Section: Basic Info -->
                <div class="ndq-section">
                    <div class="ndq-section-label"><i class="fas fa-id-card"></i> Basic Information</div>
                    <div class="ndq-field-grid">
                        <div class="ndq-field full">
                            <label>Full Name <span class="req">*</span></label>
                            <div class="ndq-input-wrap"><i class="fas fa-user"></i><input type="text" name="name" required placeholder="Enter full name"></div>
                        </div>
                        <div class="ndq-field full">
                            <label>Email Address <span class="req">*</span></label>
                            <div class="ndq-input-wrap"><i class="fas fa-envelope"></i><input type="email" name="email" required placeholder="client@example.com"></div>
                        </div>
                        <div class="ndq-field">
                            <label>Account Password <span style="color:#9ca3af;font-weight:400;font-size:0.75rem;">(optional)</span></label>
                            <div class="ndq-input-wrap"><i class="fas fa-lock"></i><input type="password" name="account_password" id="account_password" placeholder="Set initial password"></div>
                        </div>
                        <div class="ndq-field">
                            <label>Confirm Password</label>
                            <div class="ndq-input-wrap"><i class="fas fa-lock"></i><input type="password" name="account_confirm_password" id="account_confirm_password" placeholder="Re-enter password"></div>
                        </div>
                        <div class="ndq-field">
                            <label>Phone</label>
                            <div class="ndq-input-wrap"><i class="fas fa-phone"></i><input type="tel" name="phone" placeholder="+63 987-654-2100"></div>
                        </div>
                        <div class="ndq-field">
                            <label>Age</label>
                            <div class="ndq-input-wrap"><i class="fas fa-birthday-cake"></i><input type="number" name="age" min="1" max="100" placeholder="Age in years"></div>
                        </div>
                        <div class="ndq-field full">
                            <label>Gender</label>
                            <div class="ndq-input-wrap"><i class="fas fa-venus-mars"></i>
                                <select name="gender">
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section: Physical Metrics -->
                <div class="ndq-section">
                    <div class="ndq-section-label"><i class="fas fa-weight"></i> Physical Metrics</div>
                    <div class="ndq-field-grid">
                        <div class="ndq-field">
                            <label>Weight</label>
                            <div class="ndq-input-wrap"><i class="fas fa-weight-hanging"></i><input type="number" name="weight" step="any" min="1" placeholder="kg"><span class="ndq-unit">kg</span></div>
                        </div>
                        <div class="ndq-field">
                            <label>Height</label>
                            <div class="ndq-input-wrap"><i class="fas fa-ruler-vertical"></i><input type="number" name="height" step="any" min="1" placeholder="cm"><span class="ndq-unit">cm</span></div>
                        </div>
                        <div class="ndq-field">
                            <label>Waist Circumference</label>
                            <div class="ndq-input-wrap"><i class="fas fa-circle-notch"></i><input type="number" name="waist_circumference" step="any" min="0" placeholder="cm"><span class="ndq-unit">cm</span></div>
                        </div>
                        <div class="ndq-field">
                            <label>Hip Circumference</label>
                            <div class="ndq-input-wrap"><i class="fas fa-circle-notch"></i><input type="number" name="hip_circumference" step="any" min="0" placeholder="cm"><span class="ndq-unit">cm</span></div>
                        </div>
                    </div>
                </div>

                <!-- Section: Address -->
                <div class="ndq-section">
                    <div class="ndq-section-label"><i class="fas fa-map-marker-alt"></i> Address</div>
                    <div class="ndq-field-grid">
                        <div class="ndq-field full">
                            <label>Street Address</label>
                            <div class="ndq-input-wrap"><i class="fas fa-road"></i><input type="text" name="address" placeholder="123 Main Street"></div>
                        </div>
                        <div class="ndq-field">
                            <label>City</label>
                            <div class="ndq-input-wrap"><i class="fas fa-city"></i><input type="text" name="city" placeholder="City"></div>
                        </div>
                        <div class="ndq-field">
                            <label>Province / State</label>
                            <div class="ndq-input-wrap"><i class="fas fa-map"></i><input type="text" name="state" placeholder="Province"></div>
                        </div>
                    </div>
                </div>

                <!-- Section: Health Profile -->
                <div class="ndq-section">
                    <div class="ndq-section-label"><i class="fas fa-heartbeat"></i> Health Profile</div>
                    <div class="ndq-field-grid">
                        <div class="ndq-field full">
                            <label>Health Conditions</label>
                            <div class="ndq-input-wrap ndq-textarea-wrap"><i class="fas fa-notes-medical"></i><textarea name="health_conditions" rows="2" placeholder="e.g. Diabetes, Hypertension, Thyroid issues…"></textarea></div>
                        </div>
                        <div class="ndq-field full">
                            <label>Dietary Restrictions</label>
                            <div class="ndq-input-wrap ndq-textarea-wrap"><i class="fas fa-ban"></i><textarea name="dietary_restrictions" rows="2" placeholder="e.g. Vegetarian, Gluten-free, Lactose intolerant…"></textarea></div>
                        </div>
                        <div class="ndq-field full">
                            <label>Health / Nutrition Goals</label>
                            <div class="ndq-input-wrap ndq-textarea-wrap"><i class="fas fa-bullseye"></i><textarea name="goals" rows="2" placeholder="e.g. Weight loss, Muscle gain, Better digestion…"></textarea></div>
                        </div>
                    </div>
                </div>

            </div><!-- /.ndq-modal-body -->

            <div class="ndq-modal-footer">
                <button type="button" class="ndq-btn-cancel" onclick="document.getElementById('addClientModal').classList.remove('active');document.body.style.overflow='auto'"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="ndq-btn-submit"><i class="fas fa-user-plus"></i> Add Client</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Edit Client Modal ── -->
<div class="premium-modal-overlay" id="editClientModal">
    <div class="premium-modal ndq-modal" style="max-width:640px;padding:0;">

        <div class="ndq-modal-header">
            <div class="ndq-modal-header-icon"><i class="fas fa-user-edit"></i></div>
            <div>
                <div class="ndq-modal-title">Edit Client</div>
                <div class="ndq-modal-subtitle">Update the client's information</div>
            </div>
            <button class="ndq-modal-close" onclick="document.getElementById('editClientModal').classList.remove('active');document.body.style.overflow='auto'"><i class="fas fa-times"></i></button>
        </div>

        <form method="POST" id="editClientForm">
            <input type="hidden" name="action" value="update_client">
            <input type="hidden" name="client_id" id="edit_client_id">

            <div class="ndq-modal-body">

                <div class="ndq-section">
                    <div class="ndq-section-label"><i class="fas fa-id-card"></i> Basic Information</div>
                    <div class="ndq-field-grid">
                        <div class="ndq-field">
                            <label>Full Name <span class="req">*</span></label>
                            <div class="ndq-input-wrap"><i class="fas fa-user"></i><input type="text" id="edit_name" name="name" required></div>
                        </div>
                        <div class="ndq-field">
                            <label>Email <span class="req">*</span></label>
                            <div class="ndq-input-wrap"><i class="fas fa-envelope"></i><input type="email" id="edit_email" name="email" required></div>
                        </div>
                        <div class="ndq-field">
                            <label>Phone</label>
                            <div class="ndq-input-wrap"><i class="fas fa-phone"></i><input type="tel" id="edit_phone" name="phone"></div>
                        </div>
                        <div class="ndq-field">
                            <label>Age</label>
                            <div class="ndq-input-wrap"><i class="fas fa-birthday-cake"></i><input type="number" id="edit_age" name="age" min="1" max="120"></div>
                        </div>
                    </div>
                </div>

                <div class="ndq-section">
                    <div class="ndq-section-label"><i class="fas fa-weight"></i> Physical Metrics</div>
                    <div class="ndq-field-grid">
                        <div class="ndq-field">
                            <label>Weight</label>
                            <div class="ndq-input-wrap"><i class="fas fa-weight-hanging"></i><input type="number" id="edit_weight" name="weight" step="any" min="1"><span class="ndq-unit">kg</span></div>
                        </div>
                        <div class="ndq-field">
                            <label>Height</label>
                            <div class="ndq-input-wrap"><i class="fas fa-ruler-vertical"></i><input type="number" id="edit_height" name="height" step="any" min="1"><span class="ndq-unit">cm</span></div>
                        </div>
                        <div class="ndq-field">
                            <label>Waist</label>
                            <div class="ndq-input-wrap"><i class="fas fa-circle-notch"></i><input type="number" id="edit_waist_circumference" name="waist_circumference" step="any" min="0"><span class="ndq-unit">cm</span></div>
                        </div>
                        <div class="ndq-field">
                            <label>Hip</label>
                            <div class="ndq-input-wrap"><i class="fas fa-circle-notch"></i><input type="number" id="edit_hip_circumference" name="hip_circumference" step="any" min="0"><span class="ndq-unit">cm</span></div>
                        </div>
                    </div>
                </div>

                <div class="ndq-section">
                    <div class="ndq-section-label"><i class="fas fa-map-marker-alt"></i> Address</div>
                    <div class="ndq-field-grid">
                        <div class="ndq-field full">
                            <label>Street Address</label>
                            <div class="ndq-input-wrap"><i class="fas fa-road"></i><input type="text" id="edit_address" name="address"></div>
                        </div>
                        <div class="ndq-field">
                            <label>City</label>
                            <div class="ndq-input-wrap"><i class="fas fa-city"></i><input type="text" id="edit_city" name="city"></div>
                        </div>
                        <div class="ndq-field">
                            <label>Province / State</label>
                            <div class="ndq-input-wrap"><i class="fas fa-map"></i><input type="text" id="edit_state" name="state"></div>
                        </div>
                    </div>
                </div>

                <div class="ndq-section">
                    <div class="ndq-section-label"><i class="fas fa-heartbeat"></i> Health Profile</div>
                    <div class="ndq-field-grid">
                        <div class="ndq-field full">
                            <label>Health Conditions</label>
                            <div class="ndq-input-wrap ndq-textarea-wrap"><i class="fas fa-notes-medical"></i><textarea id="edit_health_conditions" name="health_conditions" rows="2" placeholder="e.g. Diabetes, Hypertension…"></textarea></div>
                        </div>
                        <div class="ndq-field full">
                            <label>Dietary Restrictions</label>
                            <div class="ndq-input-wrap ndq-textarea-wrap"><i class="fas fa-ban"></i><textarea id="edit_dietary_restrictions" name="dietary_restrictions" rows="2" placeholder="e.g. Gluten-free, Lactose intolerant…"></textarea></div>
                        </div>
                        <div class="ndq-field full">
                            <label>Goals</label>
                            <div class="ndq-input-wrap ndq-textarea-wrap"><i class="fas fa-bullseye"></i><textarea id="edit_goals" name="goals" rows="2" placeholder="e.g. Weight loss, Muscle gain…"></textarea></div>
                        </div>
                        <div class="ndq-field full">
                            <label>Dietician Notes</label>
                            <div class="ndq-input-wrap ndq-textarea-wrap"><i class="fas fa-sticky-note"></i><textarea id="edit_notes" name="notes" rows="3" placeholder="Progress notes, recommendations…"></textarea></div>
                        </div>
                    </div>
                </div>

            </div><!-- /.ndq-modal-body -->

            <div class="ndq-modal-footer">
                <button type="button" class="ndq-btn-cancel" onclick="document.getElementById('editClientModal').classList.remove('active');document.body.style.overflow='auto'"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="ndq-btn-submit"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
const BASE_URL = '<?= rtrim(dirname($_SERVER["PHP_SELF"]), "/") ?>/';

function filterCards(term, gridId) {
    term = term.toLowerCase();
    document.querySelectorAll('#'+gridId+' .profile-card').forEach(c => {
        c.style.display = (c.dataset.search||'').includes(term) ? '' : 'none';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Open Add modal
    document.getElementById('addClientBtn')?.addEventListener('click', () => {
        document.getElementById('addClientModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    });

    // Open Edit modal
    document.querySelectorAll('.edit-client-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const c = JSON.parse(this.dataset.client || '{}');
            document.getElementById('edit_client_id').value       = c.id || '';
            document.getElementById('edit_name').value            = c.name || '';
            document.getElementById('edit_email').value           = c.email || '';
            document.getElementById('edit_phone').value           = c.phone || '';
            document.getElementById('edit_age').value             = c.age || '';
            document.getElementById('edit_weight').value          = c.weight || '';
            document.getElementById('edit_height').value          = c.height || '';
            document.getElementById('edit_waist_circumference').value = c.waist_circumference || '';
            document.getElementById('edit_hip_circumference').value   = c.hip_circumference  || '';
            document.getElementById('edit_address').value         = c.address || '';
            document.getElementById('edit_city').value            = c.city || '';
            document.getElementById('edit_state').value           = c.state || '';
            document.getElementById('edit_health_conditions').value   = c.health_conditions || '';
            document.getElementById('edit_dietary_restrictions').value= c.dietary_restrictions || '';
            document.getElementById('edit_goals').value           = c.goals || '';
            document.getElementById('edit_notes').value           = c.notes || '';
            document.getElementById('editClientModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    });

    // Close modals on backdrop click
    ['addClientModal','editClientModal'].forEach(id => {
        document.getElementById(id)?.addEventListener('click', e => {
            if(e.target === document.getElementById(id)) {
                document.getElementById(id).classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        });
    });
});
</script>
</body>
</html>