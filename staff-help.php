<?php
require_once __DIR__ . '/session_bootstrap.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['user_role'] !== 'staff' && $_SESSION['user_role'] !== 'admin')) {
    header("Location: login-logout/NutriDeqN-Login.php");
    exit();
}

require_once 'navigation.php';
require_once 'database.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
$is_admin = ($user_role === 'admin');

function getInitials($name)
{
    if (!$name)
        return '?';
    $p = explode(' ', $name);
    $i = '';
    foreach ($p as $x)
        if ($x !== '')
            $i .= strtoupper($x[0]);
    return substr($i, 0, 2);
}
$user_initials = getInitials($user_name);
$nav_links = getNavigationLinks($user_role, 'staff-help.php');

$database = new Database();
$pdo = $database->getConnection();

// Handle Thread Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_thread'])) {
    $thread_title = trim($_POST['thread_title'] ?? '');
    $initial_message = trim($_POST['initial_message'] ?? '');
    $selected_admins = $_POST['admins'] ?? [];
    if (!empty($thread_title) && !empty($initial_message) && !empty($selected_admins)) {
        try {
            $pdo->beginTransaction();
            $thread_uuid = bin2hex(random_bytes(16));
            $participants = array_merge([$user_id], array_map('intval', $selected_admins));
            $pdo->prepare("INSERT INTO internal_threads (thread_uuid, title, created_by, participants, status, last_message_at, created_at, updated_at) VALUES (?, ?, ?, ?, 'open', NOW(), NOW(), NOW())")->execute([$thread_uuid, $thread_title, $user_id, json_encode($participants)]);
            $new_id = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO internal_thread_messages (thread_id, sender_id, sender_role, message, read_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())")->execute([$new_id, $user_id, $user_role, $initial_message, json_encode([$user_id])]);
            $pdo->commit();
            header("Location: staff-help.php?thread_id=$new_id");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}

// Fetch Threads
$where = $is_admin ? "1=1" : "(JSON_CONTAINS(participants, ?, '$') OR created_by = ?)";
$stmt = $pdo->prepare("SELECT * FROM internal_threads WHERE $where ORDER BY last_message_at DESC");
$is_admin ? $stmt->execute() : $stmt->execute([json_encode($user_id), $user_id]);
$threads = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT id, name FROM users WHERE role = 'admin' AND status = 'active'");
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selected_thread_id = isset($_GET['thread_id']) ? intval($_GET['thread_id']) : null;
$selected_thread = null;
$thread_messages = [];
if ($selected_thread_id) {
    $stmt = $pdo->prepare($is_admin ? "SELECT * FROM internal_threads WHERE id = ?" : "SELECT * FROM internal_threads WHERE id = ? AND (JSON_CONTAINS(participants, ?, '$') OR created_by = ?)");
    $is_admin ? $stmt->execute([$selected_thread_id]) : $stmt->execute([$selected_thread_id, json_encode($user_id), $user_id]);
    $selected_thread = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($selected_thread) {
        $stmt = $pdo->prepare("SELECT m.*, u.name as sender_name FROM internal_thread_messages m LEFT JOIN users u ON m.sender_id = u.id WHERE m.thread_id = ? ORDER BY m.created_at ASC");
        $stmt->execute([$selected_thread_id]);
        $thread_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="scripts/theme-toggle.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Clinical Support Hub | NutriDeq</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard-premium.css">
    <link rel="stylesheet" href="css/interactive-animations.css">
    <style>
        .dash-premium {
            background: transparent !important;
        }

        /* ════════════════════════════════════════════════
           SHARED HELP HUB LAYOUT — FULLY RESPONSIVE
           ════════════════════════════════════════════════ */
        .help-hub-wrapper {
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 10;
            padding: 20px;
            min-height: calc(100vh - 80px);
        }

        .help-hub-grid {
            display: grid;
            grid-template-columns: 380px 1fr;
            height: calc(100vh - 120px);
            min-height: 600px;
            gap: 20px;
            width: 100%;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* ═══════ THREAD LIST PANE (DESKTOP) ═══════ */
        .thread-pane {
            display: flex;
            flex-direction: column;
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.05), 0 5px 15px rgba(0, 0, 0, 0.03);
        }

        .thread-header {
            padding: 24px 20px;
            border-bottom: 1px solid #F3F4F6;
            background: rgba(255, 255, 255, 0.5);
        }

        .thread-header h2 {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            margin: 0 0 2px;
            font-size: 1.2rem;
            color: #0F172A;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .thread-header h2 i {
            color: #10b981;
        }

        .thread-subtitle {
            font-size: 0.8rem;
            color: #6B7280;
            font-weight: 500;
            margin-bottom: 14px;
        }

        .new-inquiry-btn {
            width: 100%;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #fff;
            border: none;
            padding: 12px 18px;
            border-radius: 14px;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            letter-spacing: 0.01em;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
        }

        .new-inquiry-btn:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.4);
        }

        .new-inquiry-btn:active {
            transform: translateY(0);
        }

        .thread-list {
            flex: 1;
            overflow-y: auto;
            padding: 12px 10px 16px;
            scroll-behavior: smooth;
        }

        .thread-list::-webkit-scrollbar {
            width: 5px;
        }

        .thread-list::-webkit-scrollbar-track {
            background: transparent;
        }

        .thread-list::-webkit-scrollbar-thumb {
            background: rgba(5, 150, 105, 0.2);
            border-radius: 4px;
        }

        .thread-list::-webkit-scrollbar-thumb:hover {
            background: rgba(5, 150, 105, 0.35);
        }

        .thread-card {
            padding: 14px;
            border-radius: 16px;
            margin-bottom: 6px;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.25, 0.8, 0.25, 1);
            border: 1px solid transparent;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            background: transparent;
            position: relative;
        }

        .thread-card:hover {
            background: rgba(243, 244, 246, 0.8);
            transform: translateX(3px);
        }

        .thread-card.active {
            background: #ECFDF5;
            border-color: #A7F3D0;
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.12);
        }

        .thread-card.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 60%;
            background: #10b981;
            border-radius: 0 3px 3px 0;
        }

        .thread-avatar {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.12) 0%, rgba(59, 130, 246, 0.1) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: #059669;
            flex-shrink: 0;
            font-size: 1rem;
            border: 1.5px solid rgba(16, 185, 129, 0.18);
        }

        .thread-card-content {
            flex: 1;
            min-width: 0;
        }

        .thread-card-title {
            font-weight: 700;
            color: #0F172A;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.3;
        }

        .thread-card-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 4px;
            font-size: 0.72rem;
            color: #64748b;
            font-weight: 500;
        }

        .thread-card-meta i {
            font-size: 0.65rem;
        }

        .thread-status-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .thread-status-dot.open {
            background: #10b981;
            box-shadow: 0 0 0 2px #ECFDF5, 0 0 8px rgba(16, 185, 129, 0.5);
        }

        .thread-status-dot.closed {
            background: #94a3b8;
        }

        .empty-threads {
            text-align: center;
            color: #94a3b8;
            padding: 50px 20px;
            font-weight: 500;
        }

        .empty-threads i {
            font-size: 2.5rem;
            color: #10b981;
            opacity: 0.5;
            margin-bottom: 12px;
        }

        /* ═══════ CHAT PANE — IMPROVED PC DESIGN ═══════ */
        .chat-pane {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-radius: 24px;
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(255, 255, 255, 0.75);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.07), 0 10px 20px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            position: relative;
        }

        .chat-pane-header {
            padding: 18px 28px;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
            position: relative;
            z-index: 10;
            flex-wrap: wrap;
            gap: 12px;
        }

        .chat-pane-header-left {
            display: flex;
            align-items: center;
            gap: 14px;
            flex: 1;
            min-width: 0;
        }

        .mobile-back-btn {
            display: none;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(5, 150, 105, 0.08);
            color: #059669;
            border: 1px solid rgba(5, 150, 105, 0.15);
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .mobile-back-btn:hover {
            background: rgba(5, 150, 105, 0.14);
        }

        .chat-title-wrap {
            min-width: 0;
        }

        .chat-pane-title {
            margin: 0;
            font-size: 1.1rem;
            color: #0F172A;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-pane-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 4px;
            flex-wrap: wrap;
        }

        .chat-status {
            padding: 2px 12px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 22px;
        }

        .status-open {
            background: rgba(16, 185, 129, 0.12);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.25);
        }

        .status-closed {
            background: rgba(148, 163, 184, 0.12);
            color: #64748b;
            border: 1px solid rgba(148, 163, 184, 0.25);
        }

        .chat-thread-id {
            font-size: 0.72rem;
            color: #64748b;
            font-weight: 600;
        }

        /* ═══════ CHAT MESSAGES — ENHANCED PC ═══════ */
        .chat-messages {
            flex: 1;
            padding: 28px 32px;
            overflow-y: auto;
            background:
                radial-gradient(ellipse at top left, rgba(59, 130, 246, 0.02) 0%, transparent 50%),
                radial-gradient(ellipse at bottom right, rgba(16, 185, 129, 0.02) 0%, transparent 50%);
            display: flex;
            flex-direction: column;
            gap: 14px;
            scroll-behavior: smooth;
        }

        .chat-messages::-webkit-scrollbar {
            width: 5px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: transparent;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: #E5E7EB;
            border-radius: 4px;
        }

        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: #D1D5DB;
        }

        .msg-row {
            display: flex;
            width: 100%;
            margin-bottom: 0;
        }

        .msg-row.me {
            justify-content: flex-end;
        }

        .msg-row.them {
            justify-content: flex-start;
        }

        .msg-bubble {
            max-width: 72%;
            padding: 14px 20px;
            border-radius: 20px;
            font-size: 0.95rem;
            line-height: 1.6;
            position: relative;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .msg-bubble:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.07);
        }

        .msg-row.me .msg-bubble {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-bottom-right-radius: 5px;
            box-shadow: 0 5px 20px rgba(16, 185, 129, 0.22);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .msg-row.them .msg-bubble {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(5px);
            color: #1e293b;
            border-bottom-left-radius: 5px;
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        .msg-sender-label {
            font-size: 0.72rem;
            font-weight: 700;
            color: #059669;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 6px;
            padding-bottom: 4px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
        }

        .msg-sender-label i {
            font-size: 0.65rem;
        }

        .msg-row.me .msg-sender-label {
            color: rgba(255, 255, 255, 0.85);
            border-bottom-color: rgba(255, 255, 255, 0.12);
        }

        .msg-meta {
            font-size: 0.68rem;
            color: #64748b;
            margin-top: 6px;
            font-weight: 600;
            text-align: right;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 4px;
        }

        .msg-row.me .msg-meta {
            color: rgba(255, 255, 255, 0.7);
        }

        .msg-img {
            max-width: 100%;
            max-height: 260px;
            border-radius: 14px;
            margin: 8px 0 4px;
            display: block;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.1);
            cursor: zoom-in;
        }

        .msg-file-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 12px;
            margin: 6px 0 4px;
            font-weight: 600;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .msg-row.me .msg-file-link {
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.2);
        }

        .msg-row.them .msg-file-link {
            color: #059669;
        }

        /* ═══════ INPUT AREA — ENHANCED PC ═══════ */
        .chat-input-section {
            padding: 20px 28px 24px;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
            z-index: 10;
        }

        .chat-closed-banner {
            text-align: center;
            color: #64748b;
            padding: 12px;
            font-weight: 600;
            font-size: 0.85rem;
            background: rgba(148, 163, 184, 0.08);
            border-radius: 12px;
            border: 1px dashed rgba(148, 163, 184, 0.3);
        }

        .typing-indicator-help {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 4px 10px;
            font-size: 0.78rem;
            color: #6B7280;
            font-weight: 500;
            opacity: 0;
            transform: translateY(4px);
            transition: all 0.2s ease;
            pointer-events: none;
            min-height: 22px;
        }

        .typing-indicator-help.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .typing-dots-help {
            display: flex;
            gap: 3px;
        }

        .typing-dots-help span {
            width: 5px;
            height: 5px;
            background: #9CA3AF;
            border-radius: 50%;
            animation: typingBounceHelp 1.4s infinite ease-in-out both;
        }

        .typing-dots-help span:nth-child(1) { animation-delay: -0.32s; }
        .typing-dots-help span:nth-child(2) { animation-delay: -0.16s; }

        @keyframes typingBounceHelp {
            0%, 80%, 100% { transform: scale(0.6); opacity: 0.5; }
            40% { transform: scale(1); opacity: 1; }
        }

        .input-pill {
            background: #F8FAFC;
            border: 1.5px solid #E2E8F0;
            border-radius: 20px;
            padding: 8px 10px 8px 16px;
            display: flex;
            align-items: flex-end;
            gap: 8px;
            transition: all 0.25s ease;
        }

        .input-pill:focus-within {
            background: #fff;
            border-color: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.12), 0 4px 16px rgba(16, 185, 129, 0.08);
            transform: translateY(-1px);
        }

        .input-attach-btn {
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 8px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            flex-shrink: 0;
            margin-bottom: 3px;
        }

        .input-attach-btn:hover {
            background: rgba(16, 185, 129, 0.08);
            color: #059669;
        }

        .chat-input-help {
            flex: 1;
            background: transparent;
            border: none;
            padding: 10px 6px;
            font-family: inherit;
            font-size: 0.95rem;
            color: #1E293B;
            outline: none;
            resize: none;
            max-height: 140px;
            line-height: 1.5;
        }

        .chat-input-help::placeholder {
            color: #94a3b8;
        }

        .chat-send-btn {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            margin-bottom: 2px;
        }

        .chat-send-btn:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: scale(1.06);
            box-shadow: 0 6px 18px rgba(16, 185, 129, 0.4);
        }

        .chat-send-btn:active {
            transform: scale(0.98);
        }

        /* ═══════ EMPTY CHAT STATE ═══════ */
        .empty-chat {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 24px;
            text-align: center;
        }

        .empty-chat-icon {
            width: 120px;
            height: 120px;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.7);
            border-radius: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 28px;
            box-shadow: 0 20px 60px -15px rgba(0, 0, 0, 0.1);
            transform: rotate(-5deg);
        }

        .empty-chat-icon i {
            font-size: 3rem;
            color: #10b981;
            opacity: 0.85;
        }

        .empty-chat h3 {
            font-family: 'Outfit', sans-serif;
            color: #0F172A;
            margin: 0 0 8px;
            font-size: 1.5rem;
            font-weight: 800;
        }

        .empty-chat p {
            color: #64748B;
            font-weight: 600;
            margin: 0;
            font-size: 0.95rem;
            max-width: 360px;
            line-height: 1.6;
        }

        /* ═══════ MODAL ═══════ */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-body {
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(20px);
            border-radius: 28px;
            border: 1px solid rgba(255, 255, 255, 0.6);
            width: 100%;
            max-width: 480px;
            padding: 30px;
            box-shadow: 0 50px 120px -25px rgba(0, 0, 0, 0.25);
            position: relative;
            overflow: hidden;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #10b981, #3B82F6);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }

        .modal-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.25rem;
            color: #0F172A;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-title i {
            color: #10b981;
        }

        .modal-close-btn {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.04);
            border: none;
            font-size: 1.3rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            transition: all 0.2s;
        }

        .modal-close-btn:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .modal-field {
            margin-bottom: 20px;
        }

        .modal-label {
            display: block;
            font-weight: 700;
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            margin-bottom: 8px;
        }

        .modal-input {
            width: 100%;
            padding: 13px 16px;
            border: 1px solid #CBD5E1;
            border-radius: 14px;
            font-family: 'Outfit', sans-serif;
            font-size: 0.92rem;
            color: #0F172A;
            background: #F8FAFC;
            outline: none;
            transition: all 0.25s;
            box-sizing: border-box;
        }

        .modal-input:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.12);
            background: #fff;
        }

        .modal-textarea {
            resize: vertical;
            min-height: 110px;
            line-height: 1.5;
        }

        .admin-select-wrap {
            max-height: 150px;
            overflow-y: auto;
            border: 1px solid #CBD5E1;
            border-radius: 14px;
            padding: 10px 14px;
            background: #F8FAFC;
        }

        .admin-select-wrap::-webkit-scrollbar {
            width: 4px;
        }

        .admin-select-wrap::-webkit-scrollbar-thumb {
            background: rgba(16, 185, 129, 0.2);
            border-radius: 4px;
        }

        .admin-check-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 4px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #1E293B;
            cursor: pointer;
            border-radius: 8px;
            transition: background 0.15s ease;
        }

        .admin-check-item:hover {
            background: rgba(16, 185, 129, 0.06);
        }

        .admin-check-item + .admin-check-item {
            border-top: 1px solid rgba(203, 213, 225, 0.5);
        }

        .admin-check-item input[type="checkbox"] {
            accent-color: #10b981;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .modal-submit-btn {
            width: 100%;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 14px;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 0.98rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 6px;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }

        .modal-submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(16, 185, 129, 0.4);
        }

        /* ════════════════════════════════════════════════
           RESPONSIVE BREAKPOINTS — TABLET & MOBILE
           Aligned to nd-app / nd-mobile-header from sidebar.php
           to match staff-messages.php visual language.
           ════════════════════════════════════════════════ */

        /* Desktop (>= 1025px) — ensure dash-premium main accounts for fixed sidebar */
        @media (min-width: 1025px) {
            main.nd-main.dash-premium {
                margin-left: 280px !important;
                width: calc(100% - 280px) !important;
                padding: 32px !important;
                transition: margin-left 0.4s cubic-bezier(0.2, 0.8, 0.2, 1), width 0.4s cubic-bezier(0.2, 0.8, 0.2, 1);
            }
            body.sidebar-collapsed main.nd-main.dash-premium {
                margin-left: 88px !important;
                width: calc(100% - 88px) !important;
            }
        }

        /* Tablet (<= 1024px) */
        @media (max-width: 1024px) {
            main.nd-main.dash-premium {
                padding-top: 72px !important; /* Reserve space for fixed nd-mobile-header */
                padding-left: 16px !important;
                padding-right: 16px !important;
                min-height: 100dvh !important;
                box-sizing: border-box !important;
                margin-left: 0 !important;
                width: 100% !important;
            }

            .help-hub-wrapper {
                padding: 16px;
            }

            .help-hub-grid {
                grid-template-columns: 320px 1fr;
                height: calc(100vh - 110px);
                min-height: 520px;
                gap: 16px;
            }

            .chat-pane-header {
                padding: 16px 22px;
            }

            .chat-messages {
                padding: 22px 24px;
            }

            .msg-bubble {
                max-width: 80%;
                padding: 12px 18px;
                font-size: 0.92rem;
            }

            .chat-input-section {
                padding: 16px 24px 20px;
            }
        }

        /* Mobile & Small Tablet (<= 850px) — SINGLE PANE MODE */
        @media (max-width: 850px) {
            .mesh-gradient-container.dashboard-mesh,
            .spotlight,
            #organicCursor,
            #cursorAura {
                display: none !important;
            }

            body {
                padding-top: 0 !important;
                margin: 0;
                overflow-x: hidden;
            }

            .nd-app {
                min-height: 100dvh;
            }

            .nd-main.dash-premium {
                padding: 0 !important;
                padding-top: 72px !important; /* Reserve space for fixed nd-mobile-header */
                padding-left: 0 !important;
                padding-right: 0 !important;
                padding-bottom: 0 !important;
                margin: 0 !important;
                min-height: 100dvh !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }

            .help-hub-wrapper {
                padding: 0 !important;
                margin: 0 !important;
                min-height: calc(100dvh - 72px);
                position: relative;
            }

            .help-hub-grid {
                display: flex !important;
                flex-direction: column !important;
                height: calc(100dvh - 72px) !important;
                min-height: calc(100dvh - 72px) !important;
                max-height: calc(100dvh - 72px) !important;
                gap: 0 !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                position: relative !important;
                overflow: hidden !important;
            }

            .thread-pane,
            .chat-pane {
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
                width: 100% !important;
                height: 100% !important;
                border-radius: 0 !important;
                transition: transform 0.28s cubic-bezier(0.25, 0.8, 0.25, 1), opacity 0.2s ease;
            }

            /* LIST VIEW: show thread list, hide chat */
            .help-hub-grid.view-list .thread-pane {
                transform: translateX(0);
                opacity: 1;
                z-index: 10;
            }

            .help-hub-grid.view-list .chat-pane {
                transform: translateX(100%);
                opacity: 0;
                z-index: 1;
                pointer-events: none;
            }

            /* CHAT VIEW: show chat, hide thread list */
            .help-hub-grid.view-chat .thread-pane {
                transform: translateX(-100%);
                opacity: 0;
                z-index: 1;
                pointer-events: none;
            }

            .help-hub-grid.view-chat .chat-pane {
                transform: translateX(0);
                opacity: 1;
                z-index: 10;
            }

            /* Thread Pane Mobile Tweaks */
            .thread-header {
                padding: 18px 16px 14px;
            }

            .thread-header h2 {
                font-size: 1.08rem;
            }

            .thread-subtitle {
                font-size: 0.76rem;
                margin-bottom: 12px;
            }

            .new-inquiry-btn {
                padding: 11px 16px;
                font-size: 0.88rem;
                border-radius: 12px;
            }

            .thread-list {
                padding: 10px 10px 20px;
            }

            .thread-card {
                padding: 13px 12px;
                border-radius: 14px;
                gap: 10px;
            }

            .thread-avatar {
                width: 42px;
                height: 42px;
                border-radius: 11px;
                font-size: 0.95rem;
            }

            .thread-card-title {
                font-size: 0.88rem;
            }

            .thread-card-meta {
                font-size: 0.7rem;
                gap: 6px;
            }

            /* Chat Pane Mobile Tweaks */
            .chat-pane-header {
                padding: 12px 14px !important;
                gap: 10px;
                background: rgba(255, 255, 255, 0.95) !important;
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                position: sticky;
                top: 0;
                z-index: 50;
            }

            .chat-pane-header-left {
                gap: 10px;
            }

            .mobile-back-btn {
                display: flex !important;
            }

            .chat-pane-title {
                font-size: 0.95rem !important;
            }

            .chat-pane-meta {
                margin-top: 3px;
                gap: 8px;
            }

            .chat-status {
                padding: 2px 10px;
                font-size: 0.65rem;
                height: 20px;
            }

            .chat-thread-id {
                font-size: 0.68rem;
            }

            .chat-messages {
                padding: 14px 12px !important;
                gap: 10px;
            }

            .msg-bubble {
                max-width: 88% !important;
                padding: 10px 15px !important;
                border-radius: 18px !important;
                font-size: 0.88rem !important;
                line-height: 1.5 !important;
            }

            .msg-row.me .msg-bubble {
                border-bottom-right-radius: 5px !important;
            }

            .msg-row.them .msg-bubble {
                border-bottom-left-radius: 5px !important;
            }

            .msg-sender-label {
                font-size: 0.7rem;
                margin-bottom: 4px;
            }

            .msg-meta {
                font-size: 0.65rem;
                margin-top: 4px;
            }

            .msg-img {
                max-height: 180px;
                border-radius: 10px;
            }

            .chat-input-section {
                padding: 10px 12px calc(10px + env(safe-area-inset-bottom, 0px)) !important;
                background: rgba(255, 255, 255, 0.95) !important;
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                position: sticky;
                bottom: 0;
                z-index: 50;
            }

            .chat-closed-banner {
                padding: 10px;
                font-size: 0.8rem;
                border-radius: 10px;
            }

            .input-pill {
                padding: 6px 8px 6px 12px;
                border-radius: 18px;
                gap: 4px;
            }

            .input-attach-btn {
                padding: 6px;
                border-radius: 8px;
                margin-bottom: 2px;
            }

            .chat-input-help {
                padding: 9px 4px;
                font-size: 0.9rem;
                max-height: 120px;
            }

            .chat-send-btn {
                width: 40px;
                height: 40px;
                border-radius: 12px;
                box-shadow: 0 3px 10px rgba(16, 185, 129, 0.28);
            }

            .empty-chat {
                padding: 30px 20px;
            }

            .empty-chat-icon {
                width: 90px;
                height: 90px;
                border-radius: 24px;
                margin-bottom: 22px;
            }

            .empty-chat-icon i {
                font-size: 2.2rem;
            }

            .empty-chat h3 {
                font-size: 1.2rem;
            }

            .empty-chat p {
                font-size: 0.88rem;
            }

            /* Modal Mobile */
            .modal-overlay {
                padding: 0;
                align-items: flex-end;
            }

            .modal-body {
                max-width: 100%;
                border-radius: 24px 24px 0 0;
                padding: 22px 20px 28px;
                max-height: 88vh;
                animation: slideUpModal 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            }

            @keyframes slideUpModal {
                from { transform: translateY(30px); opacity: 0.5; }
                to   { transform: translateY(0); opacity: 1; }
            }

            .modal-header {
                margin-bottom: 18px;
            }

            .modal-title {
                font-size: 1.1rem;
            }

            .modal-field {
                margin-bottom: 16px;
            }

            .modal-input {
                padding: 12px 14px;
                font-size: 0.9rem;
                border-radius: 12px;
            }

            .modal-submit-btn {
                padding: 14px;
                font-size: 0.92rem;
                border-radius: 12px;
            }
        }

        /* Small Phones (<= 480px) */
        @media (max-width: 480px) {
            .thread-header {
                padding: 15px 14px 12px;
            }

            .thread-header h2 {
                font-size: 1rem;
            }

            .new-inquiry-btn {
                padding: 10px 14px;
                font-size: 0.85rem;
            }

            .thread-card {
                padding: 11px 10px;
            }

            .thread-avatar {
                width: 40px;
                height: 40px;
                border-radius: 10px;
            }

            .chat-pane-header {
                padding: 10px 12px !important;
            }

            .chat-pane-title {
                font-size: 0.9rem !important;
            }

            .chat-thread-id {
                display: none;
            }

            .chat-messages {
                padding: 12px 10px !important;
                gap: 8px;
            }

            .msg-bubble {
                max-width: 92% !important;
                padding: 9px 13px !important;
                font-size: 0.86rem !important;
            }

            .chat-input-section {
                padding: 8px 10px calc(8px + env(safe-area-inset-bottom, 0px)) !important;
            }

            .input-pill {
                padding: 5px 6px 5px 10px;
                border-radius: 16px;
            }

            .chat-send-btn {
                width: 38px;
                height: 38px;
                border-radius: 11px;
            }

            .empty-chat-icon {
                width: 76px;
                height: 76px;
                border-radius: 20px;
                margin-bottom: 18px;
            }

            .empty-chat-icon i {
                font-size: 1.8rem;
            }

            .empty-chat h3 {
                font-size: 1.05rem;
            }

            .empty-chat p {
                font-size: 0.84rem;
            }
        }
    </style>
    <!-- Critical Missing Stylesheets for Sidebar & Mobile Engine -->
    <link rel="stylesheet" href="css/responsive.css">
    <link rel="stylesheet" href="css/desktop-style.css" media="all and (min-width: 1025px)">
    <link rel="stylesheet" href="css/mobile-style.css" media="all and (max-width: 1024px)">
    <link rel="stylesheet" href="css/premium-revamp.css">
    <script src="scripts/csrf-fetch.js"></script>
</head>

<body>
    <div class="nd-app">
        <?php include 'includes/sidebar.php'; ?>

        <main class="nd-main dash-premium">
            <!-- Modern Mesh Background Elements -->
            <div class="mesh-gradient-container dashboard-mesh">
                <div class="mesh-blob blob-1"></div>
                <div class="mesh-blob blob-2"></div>
                <div class="mesh-blob blob-3"></div>
            </div>

            <!-- Spotlight & Custom Cursor -->
            <div class="spotlight" id="spotlight"></div>
            <div id="organicCursor"></div>
            <div class="glow-aura" id="cursorAura"></div>

            <div class="help-hub-wrapper">
                <div class="help-hub-grid <?= $selected_thread_id ? 'view-chat' : 'view-list' ?>" id="helpHubGrid">
                    <!-- Thread Pane -->
                    <div class="thread-pane stagger d-1">
                        <div class="thread-header">
                            <h2>Clinical Support</h2>
                            <button class="new-inquiry-btn" onclick="openModal()">
                                <i class="fas fa-plus-circle"></i> New Inquiry
                            </button>
                        </div>

                        <div class="thread-list">
                            <?php if (empty($threads)): ?>
                                <div class="empty-threads">
                                    <i class="fas fa-comments"></i>
                                    <p>No consultations yet.<br>Start a new inquiry to connect.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($threads as $thread): ?>
                                    <div class="thread-card <?= ($selected_thread_id == $thread['id']) ? 'active' : '' ?>"
                                        onclick="openThreadView(<?= $thread['id'] ?>)" data-thread-id="<?= $thread['id'] ?>">
                                        <div class="thread-avatar">
                                            <i class="fas fa-hashtag"></i>
                                        </div>
                                        <div class="thread-card-content">
                                            <div class="thread-card-title">
                                                <?= htmlspecialchars($thread['title']) ?>
                                            </div>
                                            <div class="thread-card-meta">
                                                <i class="far fa-clock"></i>
                                                <?= date('M j, g:i A', strtotime($thread['last_message_at'])) ?>
                                            </div>
                                        </div>
                                        <?php if ($thread['status'] == 'open'): ?>
                                            <div class="thread-status-dot" title="Open"></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Chat Pane -->
                    <div class="chat-pane stagger d-2">
                        <?php if ($selected_thread): ?>
                            <div class="chat-pane-header">
                                <button class="mobile-back-btn" onclick="closeThreadView()" aria-label="Back">
                                    <i class="fas fa-arrow-left"></i>
                                </button>
                                <div class="cph-info">
                                    <h3><?= htmlspecialchars($selected_thread['title']) ?></h3>
                                    <div class="cph-meta">
                                        <span class="chat-status <?= $selected_thread['status'] == 'open' ? 'status-open' : 'status-closed' ?>">
                                            <?= ucfirst($selected_thread['status']) ?>
                                        </span>
                                        <span class="cph-id">Thread #<?= $selected_thread_id ?></span>
                                    </div>
                                </div>
                            </div>

                            <?php if ($selected_thread['status'] != 'open'): ?>
                                <div class="chat-closed-banner">
                                    <i class="fas fa-check-circle"></i>
                                    This support case has been resolved.
                                </div>
                            <?php endif; ?>

                            <div class="chat-messages" id="chatMessages">
                                <?php foreach ($thread_messages as $msg):
                                    $isMe = ($msg['sender_id'] == $user_id); ?>
                                    <div class="msg-row <?= $isMe ? 'me' : 'them' ?>" id="msg-<?= $msg['id'] ?>">
                                        <div class="msg-bubble">
                                            <?php if (!$isMe): ?>
                                                <div class="msg-sender-label">
                                                    <i class="fas fa-user-shield"></i>
                                                    <?= htmlspecialchars($msg['sender_name'] ?? 'Admin') ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($msg['message_type']) && $msg['message_type'] === 'image' && !empty($msg['attachment_path'])): ?>
                                                <img src="<?= htmlspecialchars($msg['attachment_path']) ?>"
                                                    class="msg-img chat-img-zoomable" alt="Attachment">
                                            <?php elseif (!empty($msg['message_type']) && $msg['message_type'] === 'file' && !empty($msg['attachment_path'])): ?>
                                                <a href="<?= htmlspecialchars($msg['attachment_path']) ?>" target="_blank" class="msg-file-link">
                                                    <i class="fas fa-file-pdf"></i> View clinical report
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!empty($msg['message'])): ?>
                                                <div class="msg-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                                            <?php endif; ?>
                                            <div class="msg-meta"><?= date('g:i A', strtotime($msg['created_at'])) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="chat-input-section">
                                <div class="typing-indicator-help" id="typingIndicator" style="display:none;">
                                    <span></span><span></span><span></span>
                                    <div class="ti-label">Admin is typing…</div>
                                </div>
                                <?php if ($selected_thread['status'] == 'open'): ?>
                                    <form id="messageForm">
                                        <div class="input-pill">
                                            <button type="button" class="input-attach-btn" id="attachBtn" aria-label="Attach file">
                                                <i class="fas fa-paperclip"></i>
                                            </button>
                                            <input type="file" id="fileInput" style="display:none;" accept=".pdf,.png,.jpg,.jpeg">
                                            <textarea class="chat-input-help" id="messageInput" placeholder="Write clinical inquiry..."
                                                rows="1"></textarea>
                                            <button type="submit" class="chat-send-btn" aria-label="Send">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- Empty Chat State -->
                            <div class="empty-chat">
                                <div class="ec-visual">
                                    <i class="fas fa-comment-medical"></i>
                                </div>
                                <h3>NutriDeq Support Center</h3>
                                <p>Select a consultation thread or start a new inquiry.</p>
                                <button class="new-inquiry-btn ec-cta" onclick="openModal()">
                                    <i class="fas fa-plus-circle"></i> Start New Inquiry
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ═══ NEW INQUIRY MODAL ═══ -->
            <div id="threadModal" class="modal-overlay">
                <div class="modal-body">
                    <div class="modal-header">
                        <h3 class="modal-title"><i class="fas fa-plus-circle" style="color:#10b981;"></i> New Inquiry
                        </h3>
                        <button class="modal-close-btn" onclick="closeModal()">&times;</button>
                    </div>
                    <form method="POST">
                        <div class="modal-field">
                            <label class="modal-label">Inquiry Subject</label>
                            <input type="text" name="thread_title" class="modal-input"
                                placeholder="e.g. Client dietary plan clarification" required>
                        </div>
                        <div class="modal-field">
                            <label class="modal-label">Assign Admin(s)</label>
                            <div class="admin-select-list">
                                <?php if (empty($admins)): ?>
                                    <p style="font-size:0.95rem;color:#64748b;margin:0;">No active admins found.</p>
                                <?php else: ?>
                                    <?php foreach ($admins as $admin): ?>
                                        <label class="admin-check-label">
                                            <input type="checkbox" name="admins[]" value="<?= $admin['id'] ?>">
                                            <?= htmlspecialchars($admin['name']) ?>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="modal-field">
                            <label class="modal-label">Initial Message</label>
                            <textarea name="initial_message" class="modal-input modal-textarea"
                                placeholder="Describe your inquiry in detail..." required></textarea>
                        </div>
                        <button type="submit" name="create_thread" class="modal-submit-btn">
                            <i class="fas fa-rocket"></i> Submit Inquiry
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script src="scripts/internal-chat-controller.js?v=110"></script>
    <script>
        function openModal() {
            document.getElementById('threadModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeModal() {
            document.getElementById('threadModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function openThreadView(id) {
            const grid = document.getElementById('helpHubGrid');
            if (window.innerWidth <= 850) {
                if (grid) {
                    grid.classList.remove('view-list');
                    grid.classList.add('view-chat');
                }
                const state = { view: 'chat', thread_id: id };
                const url = '?thread_id=' + id;
                history.pushState(state, '', url);
                setTimeout(() => { window.location.href = url; }, 280);
            } else {
                window.location.href = '?thread_id=' + id;
            }
        }

        function closeThreadView() {
            const grid = document.getElementById('helpHubGrid');
            if (grid) {
                grid.classList.remove('view-chat');
                grid.classList.add('view-list');
            }
            const state = { view: 'list' };
            history.pushState(state, '', 'staff-help.php');
            setTimeout(() => { window.location.href = 'staff-help.php'; }, 280);
        }

        window.addEventListener('popstate', function (e) {
            const grid = document.getElementById('helpHubGrid');
            if (!grid) return;
            if (e.state && e.state.view === 'list') {
                grid.classList.remove('view-chat');
                grid.classList.add('view-list');
            } else if (e.state && e.state.view === 'chat') {
                grid.classList.remove('view-list');
                grid.classList.add('view-chat');
            } else if (location.search.indexOf('thread_id=') !== -1) {
                grid.classList.remove('view-list');
                grid.classList.add('view-chat');
            } else {
                grid.classList.remove('view-chat');
                grid.classList.add('view-list');
            }
        });

        document.getElementById('threadModal').addEventListener('click', function (e) {
            if (e.target === this) closeModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });

        document.addEventListener('DOMContentLoaded', () => {
            const el = document.getElementById('chatMessages');
            if (el) el.scrollTop = el.scrollHeight;

            <?php if ($selected_thread_id): ?>
                new ChatController(<?= $user_id ?>, 'staff', <?= $selected_thread_id ?>);
            <?php endif; ?>

            const attachBtn = document.getElementById('attachBtn');
            const fileInput = document.getElementById('fileInput');
            if (attachBtn && fileInput) {
                attachBtn.addEventListener('click', () => fileInput.click());
            }

            const textarea = document.getElementById('messageInput');
            if (textarea) {
                textarea.addEventListener('input', function () {
                    this.style.height = 'auto';
                    const capped = Math.min(this.scrollHeight, 140);
                    this.style.height = capped + 'px';
                    this.style.overflowY = (this.scrollHeight > 140) ? 'auto' : 'hidden';
                });
                textarea.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        const form = document.getElementById('messageForm');
                        if (form) form.requestSubmit();
                    }
                });
            }
        });

        // ── Shared Sidebar Toggle (mobile / tablet ≤1024px) ──
        // NOTE: sidebar.php already wires up #ndMobileNavToggle. Do NOT re-attach handlers here.
        // (Duplicate handlers would toggle the sidebar twice, cancelling the click.)
    </script>
</body>

</html>
