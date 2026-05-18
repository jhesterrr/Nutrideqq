<?php
$session_path = 'c:\xampp\htdocs\nutrideq\api\sessions';
if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
session_save_path($session_path);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    header("Location: login-logout/NutriDeqN-Login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'database.php';
    
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    // New fields for waist and hip circumference
    $waist_circumference = isset($_POST['waist_circumference']) ? floatval($_POST['waist_circumference']) : null;
    $hip_circumference = isset($_POST['hip_circumference']) ? floatval($_POST['hip_circumference']) : null;
    
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = "Email already exists";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->execute([$name, $email, $hashed_password, $role]);
            
            // If the user is a client (regular user), also add to clients table with waist/hip measurements
            if ($role === 'regular') {
                $user_id = $pdo->lastInsertId();
                
                // Insert into clients table with waist and hip measurements
                $stmt = $pdo->prepare("INSERT INTO clients (id, staff_id, name, email, waist_circumference, hip_circumference, status) VALUES (?, NULL, ?, ?, ?, ?, 'active')");
                $stmt->execute([$user_id, $name, $email, $waist_circumference, $hip_circumference]);
                
                // Also insert initial health metrics if measurements are provided
                if ($waist_circumference || $hip_circumference) {
                    $stmt = $pdo->prepare("INSERT INTO health_metrics (user_id, waist_circumference, hip_circumference, measurement_date) VALUES (?, ?, ?, CURDATE())");
                    $stmt->execute([$user_id, $waist_circumference, $hip_circumference]);
                }
            }
            
            $_SESSION['success'] = "User added successfully";
        }
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: admin-user-management.php");
    exit();
}
?>
