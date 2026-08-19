<?php
require_once __DIR__ . '/session_bootstrap.php';

require_once 'database.php';

// Security check: Only allow access from localhost (XAMPP local dev environment)
$allowed_ips = ['127.0.0.1', '::1'];
if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips) && $_SERVER['SERVER_NAME'] !== 'localhost' && $_SERVER['SERVER_ADDR'] !== '127.0.0.1') {
    die("Security Error: Developer tool only accessible from localhost local development environment.");
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'reset_password') {
        $user_id = (int)$_POST['user_id'];
        $new_password = $_POST['new_password'];
        $user_email = $_POST['user_email'];

        if (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } else {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt->execute([$hashed_password, $user_id])) {
                    $message = "Password for <strong>" . htmlspecialchars($user_email) . "</strong> has been successfully updated to <strong>" . htmlspecialchars($new_password) . "</strong>!";
                } else {
                    $error = "Failed to update password in the database.";
                }
            } catch (Exception $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'create_user') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'];

        if (empty($name) || empty($email) || empty($password)) {
            $error = "All fields are required to create a new user account.";
        } else {
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $recovery_key = 'ND-' . strtoupper(bin2hex(random_bytes(2))) . '-' . strtoupper(bin2hex(random_bytes(2)));
                $stmt = $db->prepare("INSERT INTO users (name, email, password, role, status, recovery_key) VALUES (?, ?, ?, ?, 'active', ?)");
                if ($stmt->execute([$name, $email, $hashed_password, $role, $recovery_key])) {
                    $message = "User <strong>" . htmlspecialchars($email) . "</strong> successfully created with role <strong>" . htmlspecialchars($role) . "</strong> and password <strong>" . htmlspecialchars($password) . "</strong>!";
                } else {
                    $error = "Failed to create user account.";
                }
            } catch (Exception $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// Fetch all users
try {
    $stmt = $db->query("SELECT id, name, email, role, status, recovery_key, last_login FROM users ORDER BY role ASC, id DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Failed to fetch users: " . $e->getMessage();
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <title>NutriDeq - Developer Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <style>
        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --primary-light: #34d399;
            --bg-dark: #0b1329;
            --card-bg: rgba(17, 24, 39, 0.7);
            --border-glow: rgba(16, 185, 129, 0.25);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --bounce: cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-dark);
            background-image: 
                radial-gradient(at 0% 0%, rgba(16, 185, 129, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(5, 150, 105, 0.1) 0px, transparent 50%),
                radial-gradient(rgba(16, 185, 129, 0.05) 1px, transparent 0);
            background-size: 100% 100%, 100% 100%, 30px 30px;
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            min-height: var(--app-height-dvh);
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            z-index: 10;
        }

        /* Header Style */
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 40px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 20px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .brand-logo {
            width: 50px;
            height: 50px;
            background: #ffffff;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.3);
        }

        .brand-logo img {
            width: 32px;
        }

        .brand-text h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff 0%, var(--primary-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
        }

        .brand-text p {
            font-size: 0.85rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 600;
            margin-top: 2px;
        }

        .security-badge {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 30px;
            padding: 8px 16px;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--primary-light);
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.1);
        }

        /* Notifications */
        .alert {
            padding: 16px 20px;
            border-radius: 16px;
            margin-bottom: 30px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.4s var(--bounce);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: #a7f3d0;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
        }

        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Grid Dashboard */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        @media (max-width: 992px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Cards Style */
        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 28px;
            padding: 30px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .glass-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            opacity: 0.7;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
        }

        .card-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.4rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title i {
            color: var(--primary);
        }

        /* Search input */
        .search-container {
            position: relative;
            margin-bottom: 20px;
        }

        .search-input {
            width: 100%;
            padding: 14px 20px 14px 45px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            color: var(--text-main);
            outline: none;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.3s;
        }

        .search-input:focus {
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 15px var(--border-glow);
        }

        .search-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
            border-radius: 18px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            background: rgba(0, 0, 0, 0.2);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.92rem;
        }

        th {
            background: rgba(255, 255, 255, 0.03);
            padding: 16px 20px;
            font-weight: 700;
            color: var(--text-muted);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 1px;
        }

        td {
            padding: 18px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            vertical-align: middle;
        }

        tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        .user-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .user-name {
            font-weight: 700;
            font-size: 0.95rem;
        }

        .user-email {
            color: var(--text-muted);
            font-size: 0.82rem;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-admin { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; }
        .badge-staff { background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); color: #fde68a; }
        .badge-regular { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #a7f3d0; }

        .badge-active { background: rgba(16, 185, 129, 0.1); color: var(--primary-light); }
        .badge-inactive { background: rgba(255, 255, 255, 0.08); color: var(--text-muted); }

        .recovery-key-cell {
            font-family: monospace;
            color: var(--primary-light);
            font-weight: 600;
            background: rgba(16, 185, 129, 0.08);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.85rem;
        }

        /* Action Buttons */
        .actions-cell {
            display: flex;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            border: none;
            transition: all 0.3s var(--bounce);
            font-family: inherit;
            outline: none;
        }

        .btn-primary {
            background: var(--primary-dark);
            color: white;
            box-shadow: 0 4px 15px rgba(5, 150, 105, 0.3);
        }

        .btn-primary:hover {
            background: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(5, 150, 105, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-main);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .btn-accent {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: var(--primary-light);
        }

        .btn-accent:hover {
            background: var(--primary-dark);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(5, 150, 105, 0.3);
        }

        .btn-sm {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.78rem;
        }

        /* Form Controls */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: var(--text-main);
            font-family: inherit;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 12px var(--border-glow);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 18px;
            padding-right: 40px;
        }

        /* Password modal popup trigger helper */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(11, 19, 41, 0.8);
            backdrop-filter: blur(10px);
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            pointer-events: all;
        }

        .modal-card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            width: 100%;
            max-width: 450px;
            padding: 30px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.5);
            transform: scale(0.9);
            transition: all 0.3s var(--bounce);
        }

        .modal-overlay.active .modal-card {
            transform: scale(1);
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .modal-close {
            color: var(--text-muted);
            cursor: pointer;
            font-size: 1.2rem;
            transition: 0.3s;
        }

        .modal-close:hover {
            color: #ef4444;
        }

        .quick-reset-btn {
            background: rgba(16, 185, 129, 0.1);
            color: var(--primary-light);
            border: 1px dashed rgba(16, 185, 129, 0.4);
            padding: 10px;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            margin-bottom: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            transition: 0.3s;
        }

        .quick-reset-btn:hover {
            background: rgba(16, 185, 129, 0.2);
        }

        footer {
            margin-top: 50px;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        
        footer a {
            color: var(--primary-light);
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="container">
    <header>
        <div class="brand">
            <div class="brand-logo">
                <img src="assets/img/logo.png" alt="NutriDeq Logo" onerror="this.src='https://raw.githubusercontent.com/jhesterrr/nutrideq/main/assets/img/logo.png'">
            </div>
            <div class="brand-text">
                <h1>NutriDeq Portal</h1>
                <p>Developer & Admin Tool</p>
            </div>
        </div>
        <div style="display: flex; gap: 12px; align-items: center;">
            <a href="admin-user-management.php" class="btn btn-secondary btn-sm" style="padding: 8px 16px; border-radius: 30px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 0.8rem; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); color: #f8fafc; cursor: pointer; transition: 0.3s;">
                <i class="fas fa-arrow-left"></i> Return to Admin Panel
            </a>
            <div class="security-badge">
                <i class="fas fa-shield-alt"></i> Local Developer Sandbox
            </div>
        </div>
    </header>

    <!-- Status Messages -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle" style="font-size: 1.2rem;"></i>
            <div><?php echo $message; ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle" style="font-size: 1.2rem;"></i>
            <div><?php echo $error; ?></div>
        </div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <!-- Users Management Card -->
        <div class="glass-card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-users-cog"></i> User Directory Database
                </h2>
                <div class="badge badge-regular"><?php echo count($users); ?> Total Users</div>
            </div>

            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="userSearch" class="search-input" placeholder="Search by name, email, or role...">
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>User Details</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Recovery Key</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody">
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                    No users found in the database.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr class="user-row">
                                    <td>
                                        <div class="user-info">
                                            <span class="user-name"><?php echo htmlspecialchars($user['name']); ?></span>
                                            <span class="user-email"><?php echo htmlspecialchars($user['email']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['role']; ?>">
                                            <?php echo htmlspecialchars($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo ($user['status'] === 'active' ? 'active' : 'inactive'); ?>">
                                            <?php echo htmlspecialchars($user['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($user['recovery_key'])): ?>
                                            <span class="recovery-key-cell"><?php echo htmlspecialchars($user['recovery_key']); ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 0.8rem; font-style: italic;">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-cell">
                                        <button class="btn btn-accent btn-sm" onclick="openResetModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>', '<?php echo $user['role']; ?>')">
                                            <i class="fas fa-key"></i> Reset Pass
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Right Side: Create Quick Sandbox Accounts -->
        <div class="glass-card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-user-plus"></i> Add Account
                </h2>
            </div>
            <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 20px; line-height: 1.5;">
                Create a pre-configured account instantly for debugging, role testing, or onboarding.
            </p>

            <form method="POST">
                <input type="hidden" name="action" value="create_user">
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Dietitian Test" required>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="e.g. test@nutrideq.com" required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="text" name="password" class="form-control" value="nutrideq123" placeholder="Min. 8 characters" required>
                </div>

                <div class="form-group">
                    <label>System Role</label>
                    <select name="role" class="form-control">
                        <option value="regular">Regular Client (regular)</option>
                        <option value="staff">Dietitian Staff (staff)</option>
                        <option value="admin">Administrator (admin)</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                    <i class="fas fa-plus"></i> Create User Account
                </button>
            </form>

            <!-- Quick Sandbox Helper Info -->
            <div style="margin-top: 30px; padding: 20px; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 16px;">
                <h4 style="font-size: 0.9rem; font-weight: 700; color: var(--primary-light); margin-bottom: 10px;">
                    <i class="fas fa-info-circle"></i> Password Guidelines
                </h4>
                <ul style="color: var(--text-muted); font-size: 0.82rem; padding-left: 18px; line-height: 1.6;">
                    <li>Default database passwords use bcrypt hashing (`PASSWORD_DEFAULT`).</li>
                    <li>Always use at least 8 characters.</li>
                    <li>Use the list on the left to see recovery keys to bypass standard recovery procedures.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Modal Reset Password -->
<div class="modal-overlay" id="resetModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem;">
                <i class="fas fa-key" style="color: var(--primary); margin-right: 8px;"></i> Reset Password
            </h3>
            <span class="modal-close" onclick="closeResetModal()">&times;</span>
        </div>
        
        <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 20px;" id="modalUserDesc"></p>

        <div class="quick-reset-btn" id="quickResetOption" onclick="applyQuickReset()">
            <i class="fas fa-bolt"></i> Click to Auto-Fill Default Password: <strong id="quickResetVal">admin123</strong>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="modalUserId">
            <input type="hidden" name="user_email" id="modalUserEmail">

            <div class="form-group">
                <label>New Password</label>
                <input type="text" name="new_password" id="modalNewPassword" class="form-control" placeholder="Enter new secure password" required minlength="8">
            </div>

            <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 25px;">
                <button type="button" class="btn btn-secondary" onclick="closeResetModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Password</button>
            </div>
        </form>
    </div>
</div>

<footer>
    <p>Powered by Antigravity Studio &copy; <?php echo date('Y'); ?>. Return to <a href="login-logout/NutriDeqN-Login.php">System Login Page</a></p>
</footer>

<script>
    // GSAP Page Intro Animation
    document.addEventListener("DOMContentLoaded", () => {
        gsap.from("header", { duration: 0.6, y: -30, opacity: 0, ease: "power2.out" });
        gsap.from(".glass-card", { duration: 0.8, y: 30, opacity: 0, stagger: 0.15, ease: "power3.out" });
    });

    // Real-time table search filtering
    const searchInput = document.getElementById("userSearch");
    searchInput.addEventListener("input", function() {
        const query = this.value.toLowerCase();
        const rows = document.querySelectorAll(".user-row");
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(query)) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });
    });

    // Modal Control Logic
    const resetModal = document.getElementById("resetModal");
    const modalUserId = document.getElementById("modalUserId");
    const modalUserEmail = document.getElementById("modalUserEmail");
    const modalUserDesc = document.getElementById("modalUserDesc");
    const modalNewPassword = document.getElementById("modalNewPassword");
    const quickResetVal = document.getElementById("quickResetVal");
    
    let defaultPassValue = "admin123";

    function openResetModal(userId, email, role) {
        modalUserId.value = userId;
        modalUserEmail.value = email;
        modalUserDesc.innerHTML = `You are updating the password credentials for: <br><strong style="color: white;">${email}</strong> (${role})`;
        
        // Pick appropriate default password
        if (role === 'admin') {
            defaultPassValue = 'admin123';
        } else if (role === 'staff') {
            defaultPassValue = 'staff123';
        } else {
            defaultPassValue = 'nutrideq123';
        }
        
        quickResetVal.textContent = defaultPassValue;
        modalNewPassword.value = "";
        
        resetModal.classList.add("active");
        gsap.from(".modal-card", { duration: 0.4, scale: 0.8, opacity: 0, ease: "back.out(1.7)" });
    }

    function closeResetModal() {
        resetModal.classList.remove("active");
    }

    function applyQuickReset() {
        modalNewPassword.value = defaultPassValue;
        gsap.from(modalNewPassword, { duration: 0.3, y: -5, ease: "power2.out" });
    }

    // Close modal on click outside card
    resetModal.addEventListener("click", function(e) {
        if (e.target === resetModal) {
            closeResetModal();
        }
    });
</script>
</body>
</html>
