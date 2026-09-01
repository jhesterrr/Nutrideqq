<?php
$session_path = __DIR__ . '/api/sessions';
if (!is_dir($session_path)) {
    mkdir($session_path, 0777, true);
}
session_save_path($session_path);

// Session timeout configuration (clinical data — shorter timeout for security)
$role = $_SESSION['user_role'] ?? 'regular';
$timeout = ($role === 'admin' || $role === 'staff') ? 1800 : 900; // 30 min staff, 15 min user
ini_set('session.gc_maxlifetime', $timeout);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session activity tracking — auto-expire idle sessions
if (isset($_SESSION['last_activity'])) {
    $elapsed = time() - $_SESSION['last_activity'];
    if ($elapsed > $timeout) {
        session_unset();
        session_destroy();
        if (!headers_sent()) {
            header('Location: login-logout/NutriDeqN-Login.php?timeout=1');
        }
        exit();
    }
}
$_SESSION['last_activity'] = time();

// Initialize encryption if env key is available
$encKey = getenv('NUTRIDEQ_ENC_KEY');
if ($encKey && file_exists(__DIR__ . '/libs/Encryption.php')) {
    require_once __DIR__ . '/libs/Encryption.php';
    Encryption::init();
}

// Initialize audit logger if env key is available
if (file_exists(__DIR__ . '/api/AuditLogger.php')) {
    require_once __DIR__ . '/api/AuditLogger.php';
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

function csrf_token() {
    return $_SESSION['csrf_token'] ?? '';
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf() {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}