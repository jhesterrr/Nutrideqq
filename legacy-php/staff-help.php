<?php
$session_path = 'c:\xampp\htdocs\nutrideq\api\sessions';
if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
session_save_path($session_path);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
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

        .messaging-hub {
            display: grid;
            grid-template-columns: 380px 1fr;
            height: calc(100vh - 100px);
            gap: 24px;
            margin: 20px;
            position: relative;
            z-index: 10;
        }

        /* Thread List Pane */
        .thread-pane {
            display: flex;
            flex-direction: column;
            background: var(--glass-bg) !important;
            backdrop-filter: blur(20px);
            border-radius: 32px;
            overflow: hidden;
            border: 1px solid var(--glass-border) !important;
            box-shadow: var(--glass-shadow);
        }

        .thread-header {
            padding: 32px 24px;
            border-bottom: 1px solid var(--border-color);
            background: rgba(255, 255, 255, 0.05) !important;
        }

        .new-inquiry-btn {
            margin-top: 16px;
            width: 100%;
            background: #10b981;
            color: #fff;
            border: none;
            padding: 12px 20px;
            border-radius: 14px;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            letter-spacing: 0.01em;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .new-inquiry-btn:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }

        .thread-list {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            scroll-behavior: smooth;
        }

        .thread-list::-webkit-scrollbar {
            width: 4px;
        }

        .thread-list::-webkit-scrollbar-thumb {
            background: rgba(5, 150, 105, 0.2);
            border-radius: 4px;
        }

        .thread-card {
            padding: 20px;
            border-radius: 20px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            border: 1px solid transparent;
            display: flex;
            align-items: center;
            gap: 16px;
            background: transparent !important;
        }

        .thread-card:hover {
            background: rgba(255, 255, 255, 0.4) !important;
            transform: translateX(5px);
        }

        .thread-card.active {
            background: rgba(255, 255, 255, 0.7) !important;
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.1);
            border-color: transparent !important;
        }

        .thread-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: #1e293b;
            flex-shrink: 0;
            font-size: 1.2rem;
            border: 2px solid #ffffff;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        /* Chat Pane */
        .chat-pane {
            background: rgba(255, 255, 255, 0.1) !important;
            backdrop-filter: blur(20px);
            border-radius: 32px;
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(255, 255, 255, 0.4) !important;
            box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            position: relative;
        }

        .chat-header {
            padding: 24px 32px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.3) !important;
            backdrop-filter: blur(10px);
            z-index: 10;
        }

        .chat-messages {
            flex: 1;
            padding: 40px;
            overflow-y: auto;
            background: transparent !important;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* Chat Bubbles */
        .msg-row {
            display: flex;
            width: 100%;
            margin-bottom: 8px;
        }

        .msg-row.me {
            justify-content: flex-end;
        }

        .msg-row.them {
            justify-content: flex-start;
        }

        .msg-bubble {
            max-width: 70%;
            padding: 16px 24px;
            border-radius: 24px;
            font-size: 0.98rem;
            line-height: 1.6;
            position: relative;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s;
            border: 1px solid rgba(255, 255, 255, 0.4);
        }

        .msg-row.me .msg-bubble {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-bottom-right-radius: 4px;
            box-shadow: 0 15px 30px rgba(16, 185, 129, 0.2);
        }

        .msg-row.them .msg-bubble {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(5px);
            color: #1e293b;
            border-bottom-left-radius: 4px;
        }

        .msg-meta {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 6px;
            font-weight: 500;
        }

        .msg-row.me .msg-meta {
            text-align: right;
            color: rgba(255, 255, 255, 0.6);
        }

        .chat-input-area {
            padding: 24px 32px;
            background: rgba(255, 255, 255, 0.2) !important;
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .chat-input-pill {
            background: #f1f5f9;
            border-radius: 16px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1.5px solid transparent;
            transition: all 0.2s;
        }

        .chat-input-pill:focus-within {
            background: white;
            border-color: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
        }

        .chat-input {
            flex: 1;
            background: transparent;
            border: none;
            padding: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            outline: none;
            resize: none;
            max-height: 120px;
        }

        .chat-send-btn {
            width: 44px;
            height: 44px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .chat-send-btn:hover {
            background: #059669;
            transform: scale(1.05);
        }

        /* Status Badges */
        .chat-status {
            padding: 2px 14px;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 24px;
        }

        .status-open {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .status-closed {
            background: rgba(148, 163, 184, 0.1);
            color: #64748b;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        /* Modal Styles matching the premium look */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(8px);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-body {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 32px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            width: 100%;
            max-width: 480px;
            padding: 32px;
            box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
        }

        .modal-body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #10b981, #059669);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .modal-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.3rem;
            color: #1e293b;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close-btn {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.04);
            border: none;
            font-size: 1.2rem;
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
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            margin-bottom: 8px;
        }

        .modal-input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            font-family: 'Outfit', sans-serif;
            font-size: 0.95rem;
            color: #1e293b;
            background: #f8fafc;
            outline: none;
            transition: all 0.25s;
        }

        .modal-input:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
            background: #ffffff;
        }

        .modal-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .admin-select-list {
            max-height: 140px;
            overflow-y: auto;
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            padding: 12px 16px;
            background: #f8fafc;
        }

        .admin-select-list::-webkit-scrollbar {
            width: 4px;
        }

        .admin-select-list::-webkit-scrollbar-thumb {
            background: rgba(16, 185, 129, 0.2);
            border-radius: 4px;
        }

        .admin-check-label {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            color: #1e293b;
            cursor: pointer;
        }

        .admin-check-label:last-child {
            margin-bottom: 0;
        }

        .admin-check-label input[type="checkbox"] {
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
            padding: 16px;
            border-radius: 16px;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 8px;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }

        .modal-submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(16, 185, 129, 0.4);
        }

        @media screen and (max-width: 992px) {
            .messaging-hub {
                grid-template-columns: 1fr;
                margin: 0;
                height: calc(100vh - 60px);
                padding: 10px;
                gap: 0;
            }

            .thread-pane {
                <?php if ($selected_thread_id)
                    echo 'display: none;'; ?>
                border-radius: 20px;
            }

            .chat-pane {
                <?php if (!$selected_thread_id)
                    echo 'display: none;'; ?>
                border-radius: 20px;
            }

            .mobile-nav-header {
                display: flex;
                background: linear-gradient(135deg, #1e293b, #0f172a);
                padding: 14px 20px;
                position: fixed;
                top: 0;
                width: 100%;
                z-index: 9000;
                justify-content: space-between;
                align-items: center;
            }

            .main-content {
                margin-top: 60px;
                height: calc(100vh - 60px);
            }

            body {
                padding-top: 60px;
            }
        }
    </style>
    <!-- Critical Missing Stylesheets for Sidebar & Mobile Engine -->
    <link rel="stylesheet" href="css/responsive.css">
    <link rel="stylesheet" href="css/desktop-style.css" media="all and (min-width: 1025px)">
    <link rel="stylesheet" href="css/mobile-style.css" media="all and (max-width: 1024px)">
    <link rel="stylesheet" href="css/premium-revamp.css">
</head>

<body>
    <div class="mobile-nav-header">
        <button onclick="toggleSidebar()"
            style="background:none;border:none;font-size:1.5rem;color:white;cursor:pointer;padding:4px;">
            <i class="fas fa-bars"></i>
        </button>
        <div
            style="font-weight:800;color:white;font-family:'Outfit',sans-serif;font-size:1.1rem;display:flex;align-items:center;gap:8px;">
            <i class="fas fa-headset" style="color:#10b981;"></i> Support Hub
        </div>
        <button onclick="openModal()"
            style="background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);border-radius:10px;color:white;padding:6px 12px;font-family:'Outfit',sans-serif;font-weight:700;font-size:0.75rem;">
            New
        </button>
    </div>

    <!-- Sidebar Overlay (mobile) -->
    <div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:8500; backdrop-filter:blur(5px);">
    </div>

    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content dash-premium">
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

            <div class="messaging-hub">
                <!-- Thread Pane -->
                <div class="thread-pane stagger d-1">
                    <div class="thread-header">
                        <h2
                            style="font-family:'Outfit',sans-serif; font-weight: 800; margin:0; font-size:1.4rem; color:#1e293b;">
                            Clinical Support</h2>
                        <button class="new-inquiry-btn" onclick="openModal()">
                            <i class="fas fa-plus-circle"></i> New Inquiry
                        </button>
                    </div>

                    <div class="thread-list">
                        <?php if (empty($threads)): ?>
                            <div style="text-align:center; color:#94a3b8; padding: 40px 20px; font-weight:500;">
                                <i class="fas fa-comments"
                                    style="font-size: 2.5rem; color:#10b981; opacity:0.5; margin-bottom: 12px;"></i>
                                <p>No consultations yet.<br>Start a new inquiry to connect.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($threads as $thread): ?>
                                <div class="thread-card <?= ($selected_thread_id == $thread['id']) ? 'active' : '' ?>"
                                    onclick="window.location.href='?thread_id=<?= $thread['id'] ?>'">
                                    <div class="thread-avatar" style="font-size: 1rem;"><i class="fas fa-hashtag"
                                            style="opacity: 0.5;"></i></div>
                                    <div style="flex:1;">
                                        <div style="font-weight:700; color:#1e293b; font-size: 0.95rem;">
                                            <?= htmlspecialchars($thread['title']) ?></div>
                                        <div style="font-size:0.75rem; color:#64748b; margin-top:4px; font-weight: 500;">
                                            <i class="far fa-clock" style="margin-right: 4px;"></i>
                                            <?= date('M j, g:i A', strtotime($thread['last_message_at'])) ?>
                                        </div>
                                    </div>
                                    <?php if ($thread['status'] == 'open'): ?>
                                        <div
                                            style="width:10px; height:10px; border-radius:50%; background:#10b981; box-shadow: 0 0 10px rgba(16, 185, 129, 0.4); border: 2px solid #ffffff;">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chat Area -->
                <div class="chat-pane stagger d-2">
                    <?php if ($selected_thread): ?>
                        <div class="chat-header">
                            <div>
                                <h3
                                    style="margin:0; font-size:1.25rem; color:#1e293b; font-family:'Outfit',sans-serif; font-weight: 800;">
                                    <?php if (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/Mobile|Android|iPhone/i', $_SERVER['HTTP_USER_AGENT'])): ?>
                                        <button onclick="window.location.href='staff-help.php'"
                                            style="background:none; border:none; color:#10b981; font-size:1.2rem; margin-right:10px; cursor:pointer;"><i
                                                class="fas fa-arrow-left"></i></button>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($selected_thread['title']) ?>
                                </h3>
                                <div style="display:flex; align-items:center; gap:12px; margin-top:6px;">
                                    <span
                                        class="chat-status <?= $selected_thread['status'] == 'open' ? 'status-open' : 'status-closed' ?>">
                                        <?= ucfirst($selected_thread['status']) ?>
                                    </span>
                                    <span style="font-size:0.75rem; color:#64748b; font-weight: 600;">Thread ID:
                                        #<?= $selected_thread_id ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="chat-messages" id="chatMessages">
                            <?php foreach ($thread_messages as $msg):
                                $isMe = ($msg['sender_id'] == $user_id); ?>
                                <div class="msg-row <?= $isMe ? 'me' : 'them' ?>">
                                    <div class="msg-bubble">
                                        <?php if (!$isMe): ?>
                                            <div
                                                style="font-size:0.75rem; font-weight:700; color:#10b981; margin-bottom:4px; display:flex; align-items:center; gap:6px;">
                                                <i class="fas fa-user-shield"></i> <?= htmlspecialchars($msg['sender_name']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                                        <div class="msg-meta"><?= date('g:i A', strtotime($msg['created_at'])) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="chat-input-area">
                            <?php if ($selected_thread['status'] == 'open'): ?>
                                <form id="messageForm">
                                    <div class="chat-input-pill">
                                        <button type="button"
                                            style="background:none; border:none; color:#64748b; cursor:pointer; padding:8px;"
                                            id="attachBtn">
                                            <i class="fas fa-paperclip"></i>
                                        </button>
                                        <input type="file" id="fileInput" style="display:none;" accept=".pdf,.png,.jpg,.jpeg">
                                        <textarea class="chat-input" id="messageInput" placeholder="Write clinical inquiry..."
                                            rows="1"></textarea>
                                        <button type="submit" class="chat-send-btn">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div style="text-align:center; color:#94a3b8; padding:10px; font-weight:600;">
                                    This support case has been resolved.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Empty State -->
                        <div
                            style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; background: transparent;">
                            <div
                                style="width:140px; height:140px; background: rgba(255,255,255,0.4); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.6); border-radius:40px; display:flex; align-items:center; justify-content:center; margin-bottom:32px; box-shadow: 0 40px 80px -20px rgba(0,0,0,0.1); transform: rotate(-5deg);">
                                <i class="fas fa-comment-medical fa-4x" style="color:#10b981; opacity:0.8;"></i>
                            </div>
                            <h3
                                style="font-family:'Outfit',sans-serif; color:#1e293b; margin:0; font-size:1.8rem; font-weight: 800;">
                                NutriDeq Support Center</h3>
                            <p style="color:#64748b; font-weight: 600; margin-top:12px; font-size: 1.05rem;">Select a
                                consultation thread or start a new inquiry.</p>
                        </div>
                    <?php endif; ?>
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
        function toggleSidebar() {
            const sidebar = document.getElementById('mainSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar) sidebar.classList.toggle('active');
            if (overlay) {
                overlay.style.display = overlay.style.display === 'block' ? 'none' : 'block';
            }
        }

        function openModal() {
            document.getElementById('threadModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeModal() {
            document.getElementById('threadModal').classList.remove('active');
            document.body.style.overflow = '';
        }

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

            const textarea = document.getElementById('messageInput');
            if (textarea) {
                textarea.addEventListener('input', function () {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
            }
        });
    </script>
</body>

</html>