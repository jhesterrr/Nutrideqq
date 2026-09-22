<?php
require_once __DIR__ . '/config/env.php';

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
    // Hardened session cookie: HttpOnly + SameSite
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/; samesite=Lax', '', false, true);
    }
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

// JS-readable mirror of the CSRF token so fetch() can send it as a header.
// Not httpOnly by design; it carries no secret beyond the form token itself.
if (!isset($_COOKIE['nd_csrf']) || !hash_equals($_SESSION['csrf_token'], (string)$_COOKIE['nd_csrf'])) {
    setcookie('nd_csrf', $_SESSION['csrf_token'], [
        'expires' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => false,
        'samesite' => 'Strict',
    ]);
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

function csrf_token() {
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Authorization: may the current session access data belonging to the client
 * identified by $client_user_id?
 * Admin: always. The client themself: yes. Staff: only when assigned
 * (clients.staff_id stores the staff member's user id). Fails closed on
 * database errors.
 */
function user_can_access_client($client_user_id) {
    $client_user_id = (int)$client_user_id;
    if ($client_user_id <= 0) {
        return false;
    }
    $role = $_SESSION['user_role'] ?? '';
    $me = (int)($_SESSION['user_id'] ?? 0);
    if ($role === 'admin') {
        return true;
    }
    if ($me === $client_user_id) {
        return true;
    }
    if ($role !== 'staff') {
        return false;
    }
    try {
        require_once __DIR__ . '/database.php';
        static $pdo_for_authz = null;
        if ($pdo_for_authz === null) {
            $pdo_for_authz = (new Database())->getConnection();
        }
        $stmt = $pdo_for_authz->prepare("SELECT 1 FROM clients WHERE user_id = ? AND staff_id = ? LIMIT 1");
        $stmt->execute([$client_user_id, $me]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf() {
    // Accept the token from (in order): X-CSRF-Token header, form POST, GET,
    // or a JSON body field — covers both classic form posts and fetch()-based
    // JSON endpoints.
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($token === '') {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    }

    if ($token === '' || $token === null) {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $token = $json['csrf_token'] ?? '';
            }
        }
    }

    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', (string)$token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}