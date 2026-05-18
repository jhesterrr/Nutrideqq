<?php
$session_path = 'c:\xampp\htdocs\nutrideq\api\sessions';
if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
session_save_path($session_path);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Master Anti-Cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    header("Location: login-logout/NutriDeqN-Login.php");
    exit();
}

require_once 'navigation.php';
require_once 'database.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
$is_admin = true;

function getInitials($name) {
    if (!$name) return '?';
    $p = explode(' ', $name); $i = '';
    foreach ($p as $x) if ($x !== '') $i .= strtoupper($x[0]);
    return substr($i, 0, 2);
}
$user_initials = getInitials($user_name);
$nav_links = getNavigationLinks($user_role, 'admin-internal-messages.php');

$database = new Database(); $pdo = $database->getConnection();
$status_filter = $_GET['status'] ?? 'all';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tid = $_POST['thread_id'] ?? null;
    if (isset($_POST['update_status'])) { $pdo->prepare("UPDATE internal_threads SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$_POST['status'] ?? 'open', $tid]); header("Location: admin-internal-messages.php?thread_id=$tid&status=$status_filter"); exit(); }
}

$where = $status_filter !== 'all' ? "status = ?" : "1=1";
$params = $status_filter !== 'all' ? [$status_filter] : [];
$stmt = $pdo->prepare("SELECT * FROM internal_threads WHERE $where ORDER BY last_message_at DESC");
$stmt->execute($params);
$threads = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selected_thread_id = isset($_GET['thread_id']) ? intval($_GET['thread_id']) : null;
$selected_thread = null;
$thread_messages = [];
if ($selected_thread_id) {
    $stmt = $pdo->prepare("SELECT * FROM internal_threads WHERE id = ?");
    $stmt->execute([$selected_thread_id]);
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
    <title>Clinical Command | NutriDeq</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard-premium.css">
    <link rel="stylesheet" href="css/interactive-animations.css">
    <link rel="stylesheet" href="css/mobile-style.css">
    <style>
        .dash-premium { background: transparent !important; }
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
            background: rgba(255,255,255,0.05) !important;
        }

        .thread-search {
            margin-top: 16px;
            position: relative;
        }

        .thread-search i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
        }

        .thread-search input {
            width: 100%;
            padding: 14px 16px 14px 44px;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            background: rgba(255,255,255,0.3);
            font-family: 'Outfit', sans-serif;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .thread-search input:focus { background: #ffffff; box-shadow: 0 10px 30px -10px rgba(5, 150, 105, 0.1); border-color: var(--primary); }

        .thread-list {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
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

        .thread-card:hover { background: rgba(255, 255, 255, 0.4) !important; transform: translateX(5px); }
        .thread-card.active { background: rgba(255,255,255,0.7) !important; box-shadow: 0 10px 30px -10px rgba(0,0,0,0.1); border-color: transparent !important; }

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
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }

        /* Chat Pane */
        .chat-pane {
            background: rgba(255, 255, 255, 0.1) !important;
            backdrop-filter: blur(20px);
            border-radius: 32px;
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(255,255,255,0.4) !important;
            box-shadow: 0 40px 100px -20px rgba(0,0,0,0.1);
            overflow: hidden;
            position: relative;
        }

        .chat-header {
            padding: 24px 32px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255,255,255,0.3) !important;
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
        .msg-row { display: flex; width: 100%; margin-bottom: 8px; }
        .msg-row.me { justify-content: flex-end; }
        .msg-row.them { justify-content: flex-start; }

        .msg-bubble {
            max-width: 70%;
            padding: 16px 24px;
            border-radius: 24px;
            font-size: 0.98rem;
            line-height: 1.6;
            position: relative;
            box-shadow: 0 10px 20px rgba(0,0,0,0.03);
            transition: transform 0.2s;
            border: 1px solid rgba(255,255,255,0.4);
        }
        .msg-row.me .msg-bubble {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-bottom-right-radius: 4px;
            box-shadow: 0 15px 30px rgba(16, 185, 129, 0.2);
        }
        .msg-row.them .msg-bubble {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(5px);
            color: #1e293b;
            border-bottom-left-radius: 4px;
        }

        .msg-meta { font-size: 0.75rem; color: #64748b; margin-top: 6px; font-weight: 500; }
        .msg-row.me .msg-meta { text-align: right; color: rgba(255,255,255,0.6); }

        .chat-input-area {
            padding: 24px 32px;
            background: rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(0,0,0,0.05);
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
        .chat-input-pill:focus-within { background: white; border-color: #10b981; box-shadow: 0 0 0 4px rgba(16,185,129,0.1); }

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
        .chat-send-btn:hover { background: #059669; transform: scale(1.05); }

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
        .status-open { background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid rgba(16,185,129,0.2); }
        .status-resolved { background: rgba(148,163,184,0.1); color: #64748b; border: 1px solid rgba(148,163,184,0.2); }

        /* Premium Resolve Button */
        .btn-resolve-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 50px;
            color: #475569;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            font-family: 'Outfit', sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .btn-resolve-pill:hover {
            background: #1e293b;
            color: white;
            border-color: #1e293b;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(30, 41, 59, 0.15);
        }

        .btn-resolve-pill i {
            font-size: 0.9rem;
            color: #10b981;
            transition: all 0.3s ease;
        }

        .btn-resolve-pill:hover i {
            color: #34d399;
            transform: scale(1.1);
        }

        @media (max-width: 1024px) {
            .mobile-back-btn { display: flex !important; margin-left: -8px; }
            .messaging-hub { grid-template-columns: 1fr; margin: 10px; height: calc(100vh - 150px); padding: 0; gap: 0; }
            .thread-pane { <?php if($selected_thread_id) echo 'display: none;'; ?> border-radius: 24px; }
            .chat-pane { <?php if(!$selected_thread_id) echo 'display: none;'; ?> border-radius: 24px; border: none !important; }
            .chat-messages { padding: 20px; }
            .chat-header { padding: 16px 20px; }
            .chat-input-area { padding: 16px 20px; }
            .floating-pill-nav { width: 95%; overflow-x: auto; }
        }
    </style>
    <link rel="stylesheet" href="css/premium-revamp.css">
    <link rel="stylesheet" href="css/layout.css">
</head>
<body>
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content dash-premium">
            <!-- Modern Mesh Background Elements -->
            <div class="mesh-gradient-container dashboard-mesh">
                <div class="mesh-blob blob-1"></div>
                <div class="mesh-blob blob-2"></div>
                <div class="mesh-blob blob-3"></div>
            </div>

            <!-- Nutri-Glass Noise Texture -->
            <div class="glass-noise"></div>

            <!-- Spotlight & Custom Cursor -->
            <div class="spotlight" id="spotlight"></div>
            <div id="organicCursor"></div>
            <div class="glow-aura" id="cursorAura"></div>

            <!-- Floating Navigation Pills -->
            <div style="display: flex; justify-content: center; margin-bottom: 24px; position: relative; z-index: 20;">
                <div class="floating-pill-nav" style="background: rgba(255,255,255,0.4); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.6); padding: 6px; border-radius: 50px; display: flex; gap: 8px; box-shadow: 0 10px 30px -10px rgba(0,0,0,0.1);">
                    <a href="?status=all" class="pill-nav-item <?= $status_filter === 'all' ? 'active' : '' ?>" style="padding: 10px 24px; border-radius: 50px; font-weight: 700; font-size: 0.85rem; text-decoration: none; transition: all 0.3s; color: <?= $status_filter === 'all' ? '#ffffff' : '#64748b' ?>; background: <?= $status_filter === 'all' ? '#10b981' : 'transparent' ?>; box-shadow: <?= $status_filter === 'all' ? '0 4px 12px rgba(16, 185, 129, 0.3)' : 'none' ?>;">All</a>
                    <a href="?status=open" class="pill-nav-item <?= $status_filter === 'open' ? 'active' : '' ?>" style="padding: 10px 24px; border-radius: 50px; font-weight: 700; font-size: 0.85rem; text-decoration: none; transition: all 0.3s; color: <?= $status_filter === 'open' ? '#ffffff' : '#64748b' ?>; background: <?= $status_filter === 'open' ? '#10b981' : 'transparent' ?>; box-shadow: <?= $status_filter === 'open' ? '0 4px 12px rgba(16, 185, 129, 0.3)' : 'none' ?>;">Open</a>
                    <a href="?status=closed" class="pill-nav-item <?= $status_filter === 'closed' ? 'active' : '' ?>" style="padding: 10px 24px; border-radius: 50px; font-weight: 700; font-size: 0.85rem; text-decoration: none; transition: all 0.3s; color: <?= $status_filter === 'closed' ? '#ffffff' : '#64748b' ?>; background: <?= $status_filter === 'closed' ? '#10b981' : 'transparent' ?>; box-shadow: <?= $status_filter === 'closed' ? '0 4px 12px rgba(16, 185, 129, 0.3)' : 'none' ?>;">Closed</a>
                </div>
            </div>

            <div class="messaging-hub">
                <!-- Search & Thread Sidebar -->
                <div class="thread-pane stagger d-1">
                    <div class="thread-header">
                        <h2 style="font-family:'Outfit',sans-serif; font-weight: 800; margin:0; font-size:1.4rem; color:#1e293b;">Internal Comms</h2>
                        <div class="thread-search">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Search conversations...">
                        </div>
                    </div>

                    <div class="thread-list">
                        <?php foreach ($threads as $index => $thread): ?>
                            <div class="thread-card <?= ($selected_thread_id == $thread['id'])?'active':'' ?>" 
                                 onclick="window.location.href='?thread_id=<?= $thread['id'] ?>&status=<?= $status_filter ?>'">
                                <div class="thread-avatar">
                                    <i class="fas fa-hashtag" style="font-size: 0.9rem; opacity: 0.5;"></i>
                                </div>
                                <div style="flex:1;">
                                    <div style="font-weight:700; color:#1e293b; font-size: 0.95rem;"><?= htmlspecialchars($thread['title']) ?></div>
                                    <div style="font-size:0.75rem; color:#64748b; margin-top:4px; font-weight: 500;">
                                        <i class="far fa-clock" style="margin-right: 4px;"></i> <?= date('M j, g:i A', strtotime($thread['last_message_at'])) ?>
                                    </div>
                                </div>
                                <?php if($thread['status'] == 'open'): ?>
                                    <div style="width:10px; height:10px; border-radius:50%; background:#10b981; box-shadow: 0 0 10px rgba(16, 185, 129, 0.4); border: 2px solid #ffffff;"></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Chat Area -->
                <div class="chat-pane stagger d-2">
                    <?php if ($selected_thread): ?>
                        <div class="chat-header">
                            <div style="display: flex; align-items: center; gap: 16px;">
                                <a href="admin-internal-messages.php?status=<?= $status_filter ?>" class="mobile-back-btn" style="display: none; width: 36px; height: 36px; background: rgba(0,0,0,0.04); border-radius: 50%; align-items: center; justify-content: center; color: #64748b; text-decoration: none; transition: 0.2s;">
                                    <i class="fas fa-chevron-left" style="font-size: 1rem;"></i>
                                </a>
                                <div>
                                    <h3 style="margin:0; font-size:1.25rem; color:#1e293b; font-family:'Outfit',sans-serif; font-weight: 800;"><?= htmlspecialchars($selected_thread['title']) ?></h3>
                                    <div style="display:flex; align-items:center; gap:12px; margin-top:6px;">
                                    <span class="chat-status <?= $selected_thread['status']=='open'?'status-open':'status-resolved' ?>" style="font-weight: 800; text-transform: uppercase; font-size: 0.65rem; padding: 4px 10px; border-radius: 20px;">
                                        <?= ucfirst($selected_thread['status']) ?>
                                    </span>
                                    <span style="font-size:0.75rem; color:#64748b; font-weight: 600;">Thread ID: #<?= $selected_thread_id ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php if($selected_thread['status'] == 'open'): ?>
                                <form method="POST">
                                    <input type="hidden" name="thread_id" value="<?= $selected_thread_id ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <button type="submit" name="status" value="resolved" class="btn-resolve-pill" style="border-radius: 50px; font-weight: 800; font-size: 0.8rem;">
                                        <i class="fas fa-check-circle"></i> Resolve Case
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <div class="chat-messages" id="chatMessages">
                            <?php foreach ($thread_messages as $index => $msg): 
                                $isMe = ($msg['sender_id'] == $user_id); ?>
                                <div class="msg-row <?= $isMe ? 'me' : 'them' ?>">
                                    <div class="msg-bubble">
                                        <?php if(!$isMe): ?>
                                            <div style="font-size:0.75rem; font-weight:700; color:#10b981; margin-bottom:4px; display:flex; align-items:center; gap:6px;">
                                                <i class="fas fa-user-md"></i> <?= htmlspecialchars($msg['sender_name']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                                        <div class="msg-meta"><?= date('g:i A', strtotime($msg['created_at'])) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="chat-input-area">
                            <?php if($selected_thread['status'] == 'open'): ?>
                                <form id="messageForm">
                                    <div class="chat-input-pill">
                                        <button type="button" style="background:none; border:none; color:#64748b; cursor:pointer; padding:8px;" id="attachBtn">
                                            <i class="fas fa-paperclip"></i>
                                        </button>
                                        <input type="file" id="fileInput" style="display:none;" accept=".pdf,.png,.jpg,.jpeg">
                                        <textarea class="chat-input" id="messageInput" placeholder="Write clinical response..." rows="1"></textarea>
                                        <button type="submit" class="chat-send-btn">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div style="text-align:center; color:#94a3b8; padding:10px; font-weight:500;">
                                    This case has been resolved and is now closed.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Empty State -->
                        <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; background: transparent;">
                            <div style="width:140px; height:140px; background: rgba(255,255,255,0.4); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.6); border-radius:40px; display:flex; align-items:center; justify-content:center; margin-bottom:32px; box-shadow: 0 40px 80px -20px rgba(0,0,0,0.1); transform: rotate(-5deg);">
                                <i class="fas fa-shield-halved fa-4x" style="color:#10b981; opacity:0.8;"></i>
                            </div>
                            <h3 style="font-family:'Outfit',sans-serif; color:#1e293b; margin:0; font-size:1.8rem; font-weight: 800;">Internal Terminal</h3>
                            <p style="color:#64748b; font-weight: 600; margin-top:12px; font-size: 1.05rem;">Select an active thread to begin oversight.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <script src="scripts/internal-chat-controller.js"></script>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const el = document.getElementById('chatMessages'); 
                    if(el) el.scrollTop = el.scrollHeight;
                    
                    <?php if ($selected_thread_id): ?>
                    new ChatController(<?= $user_id ?>, 'admin', <?= $selected_thread_id ?>);
                    <?php endif; ?>

                    // Auto-resize textarea
                    const textarea = document.getElementById('messageInput');
                    if(textarea) {
                        textarea.addEventListener('input', function() {
                            this.style.height = 'auto';
                            this.style.height = (this.scrollHeight) + 'px';
                        });
                    }
                });
            </script>
        </main>
    </div>
</body>
</html>
