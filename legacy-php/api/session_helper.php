<?php
// Global Session Fix for XAMPP Permission Issues
$session_path = __DIR__ . DIRECTORY_SEPARATOR . 'sessions';
if (!is_dir($session_path)) { 
    mkdir($session_path, 0777, true); 
}
session_save_path($session_path);
if (session_status() === PHP_SESSION_NONE) {
    if (strpos(strtolower(PHP_OS), 'win') !== false) {
    $session_path = 'c:\\xampp\\htdocs\\nutrideq\\api\\sessions';
    if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
    session_save_path($session_path);
} else {
    $session_path = '/tmp/sessions';
    if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
    session_save_path($session_path);
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }
}
