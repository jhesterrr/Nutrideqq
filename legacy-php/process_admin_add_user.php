<?php
$session_path = 'c:\xampp\htdocs\nutrideq\api\sessions';
if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
session_save_path($session_path);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'database.php';

// Check if user is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    header("Location: login-logout/NutriDeqN-Login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    $origin = isset($_POST['origin']) ? $_POST['origin'] : 'admin-user-management.php';

    $errors = [];

    // Validation
    if (empty($name) || strlen($name) < 2) {
        $errors[] = "Name must be at least 2 characters long";
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }

    // No complexity restrictions; admin decides the password content

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    if (empty($errors)) {
        try {
            $database = new Database();
            $conn = $database->getConnection();

            // Check if email already exists
            $check_sql = "SELECT id FROM users WHERE email = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->execute([$email]);

            if ($check_stmt->rowCount() > 0) {
                $errors[] = "Email already exists";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert new user
                $insert_sql = "INSERT INTO users (name, email, password, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, 'active', NOW(), NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
                
                if ($insert_stmt->execute([$name, $email, $hashed_password, $role])) {
                    if ($role === 'regular') {
                        try {
                            $client_insert = $conn->prepare("INSERT INTO clients (user_id, staff_id, name, email, phone, address, city, state, zip_code, age, gender, waist_circumference, hip_circumference, status, created_at, updated_at) VALUES (?, NULL, ?, ?, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', NOW(), NOW())");
                            $client_insert->execute([$conn->lastInsertId(), $name, $email]);
                        } catch (PDOException $e) {}
                    }
                    $_SESSION['success'] = "User added successfully!";
                    header("Location: " . ($origin === 'admin-staff-management.php' || $role === 'staff' ? 'admin-staff-management.php' : 'admin-user-management.php'));
                    exit();
                } else {
                    $errors[] = "Error adding user. Please try again.";
                }
            }

        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }

    // If there are errors, redirect back with error messages
    if (!empty($errors)) {
        $_SESSION['add_user_errors'] = $errors;
        $_SESSION['add_user_data'] = [
            'name' => $name,
            'email' => $email,
            'role' => $role
        ];
        header("Location: " . ($origin === 'admin-staff-management.php' || $role === 'staff' ? 'admin-staff-management.php' : 'admin-user-management.php'));
        exit();
    }
} else {
    header("Location: admin-user-management.php");
    exit();
}
?>
