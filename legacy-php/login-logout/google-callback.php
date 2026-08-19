<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

require_once __DIR__ . '/../database.php';

// Load Composer autoloader
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} else {
    die("Composer dependencies not installed. Please run 'composer install'.");
}

use Google\Client as GoogleClient;
use Google\Service\Oauth2 as GoogleServiceOauth2;

// Get credentials from environment variables (Railway)
$client_id = getenv('GOOGLE_CLIENT_ID');
$client_secret = getenv('GOOGLE_CLIENT_SECRET');
$redirect_uri = 'https://' . $_SERVER['HTTP_HOST'] . '/login-logout/google-callback.php';

if (!$client_id || !$client_secret) {
    die("Google API credentials not configured in environment variables.");
}

$client = new GoogleClient();
$client->setClientId($client_id);
$client->setClientSecret($client_secret);
$client->setRedirectUri($redirect_uri);
$client->addScope("email");
$client->addScope("profile");

if (isset($_GET['code'])) {
    try {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        
        if (isset($token['error'])) {
            throw new Exception("Error fetching access token: " . $token['error_description']);
        }
        
        $client->setAccessToken($token['access_token']);

        // Get user info from Google
        $google_oauth = new GoogleServiceOauth2($client);
        $google_account_info = $google_oauth->userinfo->get();
        
        $email = strtolower($google_account_info->email);
        $name = $google_account_info->name;
        $google_id = $google_account_info->id;
        $picture = $google_account_info->picture;

        // GMAIL ONLY RESTRICTION
        if (!preg_match('/@gmail\.com$/i', $email)) {
            $_SESSION['error'] = "Only Gmail accounts are allowed to sign in.";
            header("Location: NutriDeqN-Login.php");
            exit();
        }

        $database = new Database();
        $conn = $database->getConnection();

        // Check if user already exists
        $stmt = $conn->prepare("SELECT id, name, email, role, status, has_password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // User exists, log them in
            if ($user['status'] !== 'active') {
                $_SESSION['error'] = "Your account has been deactivated.";
                header("Location: NutriDeqN-Login.php");
                exit();
            }

            $user_id = $user['id'];
            $role = ($user['role'] === 'regular') ? 'user' : $user['role'];
            $has_password = $user['has_password'];
            
            // Update last login
            $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $update_stmt->execute([$user_id]);

        } else {
            // New user, create account
            // We use a random password since they login via Google
            $random_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $has_password = 0; // NEW GOOGLE USER MUST CREATE A PASSWORD
            
            // Fix: Use 'regular' role instead of 'user' to match DB ENUM
            $insert_sql = "INSERT INTO users (name, email, password, role, status, is_verified, has_password, created_at, updated_at) VALUES (?, ?, ?, 'regular', 'active', 1, 0, NOW(), NOW())";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->execute([$name, $email, $random_password]);
            
            $user_id = $conn->lastInsertId();
            $role = 'user'; // Still treat as 'user' in session for app logic

            // Link to clients table (same logic as process_register.php)
            $attach_stmt = $conn->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
            $attach_stmt->execute([$email]);
            $existing_client = $attach_stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing_client) {
                $update_client = $conn->prepare("UPDATE clients SET user_id = ?, updated_at = NOW() WHERE id = ?");
                $update_client->execute([$user_id, $existing_client['id']]);
            } else {
                $client_sql = "INSERT INTO clients (user_id, name, email, staff_id, status, created_at, updated_at) VALUES (?, ?, ?, NULL, 'active', NOW(), NOW())";
                $client_stmt = $conn->prepare($client_sql);
                $client_stmt->execute([$user_id, $name, $email]);
            }
        }

        // Set session variables
        $_SESSION['user_id'] = (int)$user_id;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = $role;
        $_SESSION['role'] = $role;
        $_SESSION['logged_in'] = true;
        $_SESSION['google_login'] = true;
        $_SESSION['has_password'] = (int)$has_password;

        header("Location: ../dashboard.php");
        exit();

    } catch (Exception $e) {
        $_SESSION['error'] = "Google Login Failed: " . $e->getMessage();
        header("Location: NutriDeqN-Login.php");
        exit();
    }
} else {
    // Redirect to Google login page
    $auth_url = $client->createAuthUrl();
    header("Location: " . $auth_url);
    exit();
}
