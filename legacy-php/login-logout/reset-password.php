<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

require_once '../database.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = false;
$email = '';

if (empty($token)) {
    header("Location: forgot-password.php");
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Verify token
    $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $resetRequest = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetRequest) {
        $error = "This reset link is invalid or has expired. Please request a new one.";
    } else {
        $email = $resetRequest['email'];
    }

    // Process new password
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
        $password = $_POST['password'];
        $confirm = $_POST['confirm_password'];

        if (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } elseif ($password !== $confirm) {
            $error = "Passwords do not match.";
        } else {
            // Update password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $updateStmt->execute([$hashedPassword, $email]);

            // Delete the used token
            $delStmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $delStmt->execute([$email]);

            $success = true;
        }
    }
} catch (Exception $e) {
    $error = "An error occurred: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <title>Create New Password - NutriDeq</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #10b981;
            --text-dark: #0f172a;
            --bounce: cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        body {
            margin: 0; padding: 0; min-height: var(--app-height-dvh);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }
        .auth-card {
            width: 100%; max-width: 450px; padding: 40px;
            background: white; border-radius: 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            animation: popIn 0.6s var(--bounce);
        }
        @keyframes popIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        
        h1 { font-size: 1.8rem; color: var(--text-dark); text-align: center; margin-bottom: 10px; }
        p { color: #64748b; text-align: center; margin-bottom: 30px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #475569; }
        .form-control {
            width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0;
            border-radius: 16px; box-sizing: border-box; font-size: 1rem;
            transition: all 0.3s;
        }
        .form-control:focus { outline: none; border-color: var(--primary); }
        
        .btn-submit {
            width: 100%; padding: 16px; background: var(--primary); color: white;
            border: none; border-radius: 16px; font-weight: 700; font-size: 1rem;
            cursor: pointer; transition: all 0.3s var(--bounce);
        }
        .btn-submit:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(16, 185, 129, 0.2); }
        
        .alert { padding: 20px; border-radius: 16px; margin-bottom: 25px; font-size: 0.95rem; line-height: 1.5; }
        .alert-success { background: #ecfdf5; border: 1px solid #10b981; color: #065f46; text-align: center; }
        .alert-error { background: #fef2f2; border: 1px solid #ef4444; color: #991b1b; }
        
        .back-link { display: block; text-align: center; margin-top: 25px; color: var(--primary); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="auth-card">
        <h1>New Password</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle" style="display: block; font-size: 2rem; margin-bottom: 10px;"></i>
                Password updated successfully! You can now log in with your new credentials.
            </div>
            <a href="NutriDeqN-Login.php" class="btn-submit" style="display: block; text-align: center; text-decoration: none;">Log In Now</a>
        <?php else: ?>
            <p>Please enter your new secure password below.</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (!$error || strpos($error, 'Passwords') !== false || strpos($error, 'at least 8') !== false): ?>
                <form method="POST">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="password" class="form-control" placeholder="At least 8 characters" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
                    </div>
                    <button type="submit" class="btn-submit">Update Password</button>
                </form>
            <?php else: ?>
                <a href="forgot-password.php" class="back-link">Request a new reset link</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
