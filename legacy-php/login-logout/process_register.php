<?php
// process_register.php
$session_path = 'c:\xampp\htdocs\nutrideq\api\sessions';
if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
session_save_path($session_path);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// require the Database class from project root
require_once __DIR__ . '/../database.php';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Initialize errors array
    $errors = [];

    // Validation
    if (empty($name) || strlen($name) < 2) {
        $errors[] = "Name must be at least 2 characters long";
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }

    if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $password)) {
        $errors[] = "Password must contain both letters and numbers";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    // If no validation errors, proceed with registration
    if (empty($errors)) {
        try {
            $database = new Database();
            $conn = $database->getConnection();

            // Check if email already exists
            $check_sql = "SELECT id FROM users WHERE email = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->execute([$email]);

            if ($check_stmt->rowCount() > 0) {
                $errors[] = "Email already registered";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Generate Recovery Key: ND-XXXX-XXXX
                $recovery_key = 'ND-' . strtoupper(bin2hex(random_bytes(2))) . '-' . strtoupper(bin2hex(random_bytes(2)));

                // Insert user into database - automatically set as 'user' role
                $insert_sql = "INSERT INTO users (name, email, password, role, status, recovery_key, created_at, updated_at) VALUES (?, ?, ?, 'regular', 'active', ?, NOW(), NOW())";
                $insert_stmt = $conn->prepare($insert_sql);

                if ($insert_stmt->execute([$name, $email, $hashed_password, $recovery_key])) {
                    $user_id = $conn->lastInsertId();
                    
                    // Store recovery key in session to show once on registration success
                    $_SESSION['new_recovery_key'] = $recovery_key;

                    // If a clients row already exists for this email (imported/unlinked), attach it to the new user
                    $attach_stmt = $conn->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
                    $attach_stmt->execute([$email]);
                    $existing_client = $attach_stmt->fetch(PDO::FETCH_ASSOC);

                    if ($existing_client) {
                        // update existing client to link user_id (preserve staff assignment if present)
                        $update_client = $conn->prepare("UPDATE clients SET user_id = ?, updated_at = NOW() WHERE id = ?");
                        $update_client->execute([$user_id, $existing_client['id']]);
                    } else {
                        // create a new client record and leave staff_id NULL (unassigned)
                        $client_sql = "INSERT INTO clients (user_id, name, email, staff_id, status, created_at, updated_at) VALUES (?, ?, ?, NULL, 'active', NOW(), NOW())";
                        $client_stmt = $conn->prepare($client_sql);
                        $client_stmt->execute([$user_id, $name, $email]);
                    }

                    // Log user in
                    $_SESSION['user_id'] = (int)$user_id;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_role'] = 'user';
                    $_SESSION['role'] = 'user';
                    $_SESSION['logged_in'] = true;

                    // Redirect to dashboard
                    header("Location: ../dashboard.php");
                    exit();
                } else {
                    $errors[] = "Error creating account. Please try again.";
                }
            }

        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }

    // If there are errors, redirect back with error messages
    if (!empty($errors)) {
        $error_string = urlencode(implode(". ", $errors));
        $redirect_url = "NutriDeqN-Signup.php?error=" . $error_string . "&name=" . urlencode($name) . "&email=" . urlencode($email);
        header("Location: " . $redirect_url);
        exit();
    }
} else {
    // If not POST request, redirect to register page
    header("Location: NutriDeqN-Signup.php");
    exit();
}
?>
