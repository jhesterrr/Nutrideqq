<?php
// Fix for XAMPP Permission Denied session error
$session_path = __DIR__ . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'sessions';
if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
session_save_path($session_path);
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
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['user_role'] !== 'staff' && $_SESSION['user_role'] !== 'admin')) {
    header("Location: login-logout/NutriDeqN-Login.php");
    exit();
}
require_once 'navigation.php';
require_once 'database.php';

$staff_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['user_role'] === 'admin');
$nav_links_array = getNavigationLinks($_SESSION['user_role'], 'staff-messages.php');

$database = new Database();
$pdo = $database->getConnection();

function getInitials($name) {
    $p = explode(' ', $name); $i = '';
    foreach ($p as $x) if ($x !== '') $i .= strtoupper($x[0]);
    return substr($i, 0, 2);
}

// Fetch Clients with Last Message
$clients = [];
try {
    $dt_check    = $is_admin ? "" : "AND dietitian_id = ?";
    $client_check = $is_admin ? "" : "WHERE c.staff_id = ?";

    $sql = "
        SELECT
            c.id, c.user_id, c.name, c.email, s.name as staff_name,
            (SELECT content FROM wellness_messages wm
             WHERE wm.conversation_id = (SELECT id FROM conversations WHERE client_id = c.id $dt_check LIMIT 1)
             ORDER BY wm.created_at DESC LIMIT 1) as last_message,
            (SELECT message_type FROM wellness_messages wm2
             WHERE wm2.conversation_id = (SELECT id FROM conversations WHERE client_id = c.id $dt_check LIMIT 1)
             ORDER BY wm2.created_at DESC LIMIT 1) as last_msg_type,
            (SELECT COUNT(*) FROM wellness_messages wm3
             WHERE wm3.conversation_id = (SELECT id FROM conversations WHERE client_id = c.id $dt_check LIMIT 1)
             AND wm3.is_read = 0 AND wm3.sender_type = 'client') as unread_count
        FROM clients c
        LEFT JOIN users s ON c.staff_id = s.id
        $client_check
        ORDER BY c.name
    ";
    $stmt = $pdo->prepare($sql);
    $params = [];
    if (!$is_admin) {
        $params = [$staff_id, $staff_id, $staff_id, $staff_id];
    }
    $stmt->execute($params);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback: try without unread_count column if it doesn't exist
    try {
        $dt_check     = $is_admin ? "" : "AND dietitian_id = ?";
        $client_check = $is_admin ? "" : "WHERE c.staff_id = ?";
        $sql = "
            SELECT c.id, c.user_id, c.name, c.email, s.name as staff_name,
                (SELECT content FROM wellness_messages wm
                 WHERE wm.conversation_id = (SELECT id FROM conversations WHERE client_id = c.id $dt_check LIMIT 1)
                 ORDER BY wm.created_at DESC LIMIT 1) as last_message,
                (SELECT message_type FROM wellness_messages wm2
                 WHERE wm2.conversation_id = (SELECT id FROM conversations WHERE client_id = c.id $dt_check LIMIT 1)
                 ORDER BY wm2.created_at DESC LIMIT 1) as last_msg_type,
                0 as unread_count
            FROM clients c LEFT JOIN users s ON c.staff_id = s.id
            $client_check ORDER BY c.name
        ";
        $stmt = $pdo->prepare($sql);
        $params = $is_admin ? [] : [$staff_id, $staff_id, $staff_id];
        $stmt->execute($params);
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) {}
}

$selected_client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : null;
$selected_client    = null;
if ($selected_client_id) {
    foreach ($clients as $c) {
        if ($c['id'] == $selected_client_id) { $selected_client = $c; break; }
    }
}

// Fetch AI Insights for the Selected Client
$ai_data = ['score' => 1, 'flag' => false, 'symptoms' => []];
if ($selected_client) {
    // Get Burnout Score
    $stmt = $pdo->prepare("SELECT sentiment_score, burnout_flag FROM ai_burnout_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$selected_client['user_id']]);
    if ($row = $stmt->fetch()) {
        $ai_data['score'] = $row['sentiment_score'];
        $ai_data['flag']  = $row['burnout_flag'];
    }

    // Get Recent Symptoms
    $stmt = $pdo->prepare("SELECT symptom_keyword, mapped_nutrient, MAX(created_at) AS max_created FROM ai_extracted_symptoms WHERE user_id = ? GROUP BY symptom_keyword, mapped_nutrient ORDER BY max_created DESC LIMIT 5");
    $stmt->execute([$selected_client['user_id']]);
    $ai_data['symptoms'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="scripts/theme-toggle.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Wellness Inbox | NutriDeq</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Phase 2 Tokens -->
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/components.css">
    <link rel="stylesheet" href="css/sidebar.css">
    
    <!-- Messaging Specific (Pending full refactor) -->
    <link rel="stylesheet" href="css/modern-messages.css?v=102">
    <style>
        /* ═══════════ WELLNESS INBOX ═══════════ */
        .dash-premium { background: transparent !important; }

        /* ── Wrapper layout ── */
        .messaging-wrapper {
            position: relative;
            z-index: 10;
            background: rgba(255,255,255,0.12) !important;
            backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255,255,255,0.3) !important;
            border-radius: 28px !important;
            overflow: hidden;
            display: grid !important;
            grid-template-columns: 340px 1fr !important;
            height: calc(100vh - 48px) !important;
            margin-top: 14px !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        /* ── Inbox list panel ── */
        .msg-sidebar {
            background: rgba(255,255,255,0.18) !important;
            border-right: 1px solid rgba(5,150,105,0.1) !important;
            width: 100% !important;
            display: flex;
            flex-direction: column;
        }
        .msg-sidebar-header {
            padding: 22px 20px 16px !important;
            background: linear-gradient(135deg, #064e3b 0%, #065f46 100%) !important;
            border-bottom: none !important;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }
        .msg-sidebar-header::before {
            content: '';
            position: absolute;
            top: -30%; left: -10%;
            width: 60%; height: 200%;
            background: radial-gradient(circle, rgba(52,211,153,0.15) 0%, transparent 60%);
            pointer-events: none;
        }
        .msg-sidebar-header h2 {
            font-family: var(--nd-font-heading) !important;
            font-size: 1.05rem !important;
            font-weight: 700 !important;
            color: #fff !important;
            margin: 0 0 4px !important;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .msg-sidebar-header h2 i { font-size: 0.95rem; opacity: 0.8; }
        .msg-sidebar-sub {
            font-size: 0.78rem;
            color: rgba(167,243,208,0.8);
            font-weight: 600;
        }

        /* ── Contact items ── */
        .contact-list { flex: 1; overflow-y: auto; padding: 8px 6px; }
        .contact-list::-webkit-scrollbar { width: 3px; }
        .contact-list::-webkit-scrollbar-thumb { background: rgba(5,150,105,0.2); border-radius: 3px; }

        .contact-item {
            margin: 3px 6px !important;
            padding: 13px 14px !important;
            border-radius: 16px !important;
            background: transparent !important;
            display: flex;
            align-items: center;
            gap: 13px;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.34,1.56,0.64,1);
            border: 1px solid transparent !important;
        }
        .contact-item:hover {
            background: rgba(5,150,105,0.06) !important;
            transform: translateX(4px);
            border-color: rgba(5,150,105,0.1) !important;
        }
        .contact-item.active {
            background: rgba(5,150,105,0.1) !important;
            border-color: rgba(5,150,105,0.2) !important;
            border-left: 3px solid var(--primary, #059669) !important;
            box-shadow: 0 4px 16px rgba(5,150,105,0.1) !important;
        }

        /* ── Avatars ── */
        .contact-avatar.circle-avatar {
            width: 44px !important; height: 44px !important;
            border-radius: 14px !important;
            background: linear-gradient(135deg, rgba(5,150,105,0.15), rgba(5,150,105,0.05)) !important;
            color: var(--primary, #059669) !important;
            font-weight: 800 !important;
            font-size: 1rem !important;
            border: 1.5px solid rgba(5,150,105,0.2) !important;
            box-shadow: none !important;
            text-shadow: none;
            flex-shrink: 0;
        }
        .contact-item.active .contact-avatar.circle-avatar {
            background: rgba(5,150,105,0.2) !important;
        }

        .contact-name { font-weight: 700; font-size: 0.9rem; color: var(--text-primary); }
        .contact-preview { font-size: 0.78rem; color: var(--text-secondary); margin-top: 2px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px; }

        /* Unread badge */
        .unread-badge {
            min-width: 20px; height: 20px;
            background: var(--primary, #059669);
            color: white;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 800;
            display: flex; align-items: center; justify-content: center;
            padding: 0 6px;
            margin-left: auto;
            flex-shrink: 0;
        }

        /* ── Chat container ── */
        .msg-container {
            background: rgba(255,255,255,0.08) !important;
            backdrop-filter: blur(10px);
            border-radius: 0 !important;
            box-shadow: none !important;
            border: none !important;
        }

        /* ── Chat header ── */
        .chat-header {
            background: rgba(255,255,255,0.25) !important;
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(5,150,105,0.1) !important;
            padding: 16px 24px !important;
            min-height: 72px !important;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .chat-header-user { display: flex; align-items: center; gap: 14px; }

        .client-chat-avatar {
            width: 46px; height: 46px;
            border-radius: 14px;
            background: linear-gradient(135deg, #064e3b, #065f46);
            color: #a7f3d0;
            font-weight: 800;
            font-size: 1.1rem;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .header-name {
            font-family: var(--nd-font-heading);
            font-weight: 600 !important;
            font-size: 1.125rem !important;
            color: var(--text-primary) !important;
        }
        .header-id { font-size: 0.75rem; color: var(--text-secondary); font-weight: 600; margin-top: 2px; }

        .context-chips { display: flex; gap: 8px; margin-left: auto; }
        .info-chip {
            padding: 5px 12px !important;
            border-radius: 50px !important;
            font-size: 0.75rem !important;
            font-weight: 700 !important;
            display: flex; align-items: center; gap: 6px;
            border: 1px solid !important;
            transition: all 0.2s !important;
        }
        .chip-goal { background: rgba(99,102,241,0.1) !important; color: #6366f1 !important; border-color: rgba(99,102,241,0.2) !important; }
        .chip-plan { background: rgba(245,158,11,0.1) !important; color: #f59e0b !important; border-color: rgba(245,158,11,0.2) !important; }

        .btn-dash-action {
            background: rgba(255,255,255,0.5) !important;
            border: 1px solid rgba(0,0,0,0.06) !important;
            border-radius: 12px !important;
            color: var(--text-primary) !important;
            width: 38px !important; height: 38px !important;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-dash-action:hover { background: rgba(5,150,105,0.1) !important; color: var(--primary) !important; }

        /* ── Messages ── */
        .chat-messages {
            background: rgba(255,255,255,0.06) !important;
            padding: 24px !important;
        }
        .message-wrapper.received .message-bubble {
            background: #f1f5f9 !important;
            border: 1px solid rgba(0,0,0,0.1) !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05) !important;
            color: #1a1a1a !important;
        }
        .message-wrapper.sent .message-bubble {
            background: #059669 !important;
            color: white !important;
            box-shadow: 0 8px 24px rgba(5, 150, 105, 0.2) !important;
        }

        /* ── Input area ── */
        .chat-input-area {
            background: rgba(255,255,255,0.5) !important;
            backdrop-filter: blur(12px) !important;
            border-top: 1px solid rgba(5,150,105,0.1) !important;
            padding: 16px 24px !important;
        }
        .input-pill-container {
            background: rgba(255,255,255,0.8) !important;
            border: 1.5px solid rgba(5,150,105,0.15) !important;
            border-radius: 20px !important;
        }
        .input-pill-container:focus-within {
            border-color: var(--primary, #059669) !important;
            box-shadow: 0 0 0 3px rgba(5,150,105,0.1), 0 8px 24px rgba(5,150,105,0.12) !important;
        }
        .send-btn {
            background: linear-gradient(135deg, #059669, #047857) !important;
            border-radius: 12px !important;
            width: 40px !important; height: 40px !important;
            box-shadow: 0 4px 12px rgba(5,150,105,0.25) !important;
        }
        .send-btn:hover { transform: scale(1.08) rotate(0deg) !important; box-shadow: 0 6px 18px rgba(5,150,105,0.35) !important; }

        /* ── AI Suggestions ── */
        .ai-suggestion-card {
            background: rgba(238,242,255,0.9) !important;
            color: #4f46e5 !important;
            border-color: #c7d2fe !important;
        }

        /* ── Empty state ── */
        .chat-empty {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            text-align: center;
        }
        .chat-empty-icon {
            width: 80px; height: 80px;
            border-radius: 24px;
            background: rgba(5,150,105,0.08);
            border: 1px solid rgba(5,150,105,0.15);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
        }
        .chat-empty-icon i { font-size: 2rem; color: var(--primary, #059669); }
        .chat-empty-title { font-family: var(--nd-font-heading); font-weight: 700; font-size: 1.1rem; color: var(--text-primary); margin: 0 0 8px; }
        .chat-empty-sub { font-size: 0.85rem; color: var(--text-secondary); margin: 0; }

        /* ── Admin read-only banner ── */
        .admin-readonly-bar {
            padding: 14px 24px;
            background: rgba(248,250,252,0.8);
            border-top: 1px solid rgba(0,0,0,0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 600;
        }
        .admin-readonly-bar i { color: var(--primary, #059669); }

        /* ── No-clients state ── */
        .no-clients-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            text-align: center;
            opacity: 0.6;
        }
        .no-clients-state i { font-size: 2.5rem; color: var(--primary); margin-bottom: 12px; }

        /* ── Mobile overrides ── */
        @media (max-width: 1024px) {
            .messaging-wrapper {
                display: flex !important;
                flex-direction: column !important;
                height: auto !important;
                min-height: calc(100vh - 80px) !important;
                border-radius: 0 !important;
                margin-top: 0 !important;
                border: none !important;
                background: transparent !important;
            }
            .msg-sidebar-header { border-radius: 0 !important; }
            .context-chips .info-chip span { display: none !important; }
            .chat-header { padding: 12px 16px !important; min-height: 60px !important; }
            #backToInbox { display: flex !important; }
        }
    </style>
    <script>
        const BASE_URL = '';
    </script>
    <link rel="stylesheet" href="css/premium-revamp.css">
</head>
<body>
    <div class="nd-app">
        <?php include 'includes/sidebar.php'; ?>
        <main class="nd-main">

            <!-- Ambient Mesh -->
            <div class="mesh-gradient-container dashboard-mesh">
                <div class="mesh-blob blob-1"></div>
                <div class="mesh-blob blob-2"></div>
                <div class="mesh-blob blob-3"></div>
            </div>
            <div class="glass-noise"></div>
            <div class="spotlight" id="spotlight"></div>

            <!-- Messaging Wrapper -->
            <div class="messaging-wrapper <?= $selected_client_id ? 'view-chat' : 'view-list' ?>" id="messagingWrapper">

                <!-- ── Inbox Sidebar ── -->
                <div class="msg-sidebar">
                    <div class="msg-sidebar-header">
                        <h2><i class="fas fa-envelope-open-text"></i> Wellness Inbox</h2>
                        <div class="msg-sidebar-sub"><?= count($clients) ?> client<?= count($clients) !== 1 ? 's' : '' ?> assigned</div>
                        
                        <!-- Client Search Bar -->
                        <div style="margin-top: 14px; position: relative; z-index: 10;">
                            <i class="fas fa-search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.7); font-size: 0.85rem;"></i>
                            <input type="text" id="clientSearch" placeholder="Search clients by name..." style="width: 100%; padding: 10px 14px 10px 38px; border-radius: 20px; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.15); color: white; font-family: 'Outfit'; font-size: 0.85rem; outline: none; transition: all 0.3s;" oninput="filterClients(this.value)" onfocus="this.style.background='rgba(0,0,0,0.3)'; this.style.borderColor='rgba(255,255,255,0.4)';" onblur="this.style.background='rgba(0,0,0,0.2)'; this.style.borderColor='rgba(255,255,255,0.15)';">
                        </div>
                        <script>
                            function filterClients(term) {
                                term = term.toLowerCase();
                                const items = document.querySelectorAll('.contact-item');
                                items.forEach(item => {
                                    const name = item.querySelector('.contact-name').textContent.toLowerCase();
                                    if (name.includes(term)) {
                                        item.style.display = 'flex';
                                    } else {
                                        item.style.display = 'none';
                                    }
                                });
                            }
                        </script>
                    </div>

                    <div class="contact-list">
                        <?php if (empty($clients)): ?>
                            <div class="no-clients-state">
                                <i class="fas fa-users"></i>
                                <p><strong>No clients assigned</strong></p>
                                <p style="font-size:0.82rem;">Clients assigned to you will appear here.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($clients as $client):
                                $isActive   = ($client['id'] == $selected_client_id);
                                $unread     = intval($client['unread_count'] ?? 0);
                                $previewTxt = 'Start a conversation...';
                                if (!empty($client['last_message'])) {
                                    $raw = $client['last_message'];
                                    if ($client['last_msg_type'] === 'image') $raw = '📷 Image';
                                    elseif ($client['last_msg_type'] === 'file') $raw = '📎 File';
                                    $previewTxt = mb_strimwidth($raw, 0, 36, '…');
                                }
                            ?>
                                <div class="contact-item <?= $isActive ? 'active' : '' ?>"
                                     onclick="window.location.href='?client_id=<?= $client['id'] ?>'">
                                    <div class="contact-avatar circle-avatar">
                                        <?= getInitials($client['name']) ?>
                                    </div>
                                    <div class="contact-info" style="flex:1;min-width:0;">
                                        <div class="contact-name"><?= htmlspecialchars($client['name']) ?></div>
                                        <div class="contact-preview"><?= htmlspecialchars($previewTxt) ?></div>
                                    </div>
                                    <?php if ($unread > 0): ?>
                                        <div class="unread-badge"><?= $unread ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ── Chat Container ── -->
                <div class="msg-container">
                    <?php if ($selected_client): ?>

                        <!-- Chat Header -->
                        <div class="chat-header">
                            <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
                                <button class="btn-dash-action" id="backToInbox"
                                        style="display:none;"
                                        onclick="window.location.href='staff-messages.php'">
                                    <i class="fas fa-arrow-left"></i>
                                </button>
                                <div class="chat-header-user">
                                    <div class="client-chat-avatar">
                                        <?= getInitials($selected_client['name']) ?>
                                    </div>
                                    <div>
                                        <div class="header-name"><?= htmlspecialchars($selected_client['name']) ?></div>
                                        <div class="header-id">ID: #<?= str_pad($selected_client['id'], 6, '0', STR_PAD_LEFT) ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="context-chips">
                                <!-- Emotional Resilience Badge -->
                                <?php if ($ai_data['flag']): ?>
                                <div class="info-chip" title="Sentiment Analysis" style="background: rgba(239,68,68,0.1) !important; color: #ef4444 !important; border-color: rgba(239,68,68,0.2) !important;">
                                    <i class="fas fa-fire"></i> <span>LOW RESILIENCE (<?= $ai_data['score'] ?>/10)</span>
                                </div>
                                <?php else: ?>
                                <div class="info-chip" title="Sentiment Analysis" style="background: rgba(16,185,129,0.1) !important; color: #10b981 !important; border-color: rgba(16,185,129,0.2) !important;">
                                    <i class="fas fa-brain"></i> <span>RESILIENCE: <?= $ai_data['score'] ?>/10</span>
                                </div>
                                <?php endif; ?>

                                <div class="info-chip chip-plan">
                                    <i class="fas fa-file-alt"></i> <span>DIET PLAN</span>
                                </div>
                            </div>
                        </div>

                        <!-- Symptom Marker Bar -->
                        <?php if (!empty($ai_data['symptoms'])): ?>
                        <div style="background: rgba(99,102,241,0.05); padding: 10px 24px; border-bottom: 1px solid rgba(99,102,241,0.1); font-size: 0.8rem; display: flex; align-items: center; gap: 12px; color: var(--text-secondary);">
                            <strong style="color: #6366f1; white-space: nowrap;"><i class="fas fa-stethoscope"></i> Clinical Indicators (Draft):</strong>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <?php foreach($ai_data['symptoms'] as $sym): ?>
                                    <span style="background: white; padding: 2px 8px; border-radius: 4px; border: 1px solid rgba(99,102,241,0.2); color: #475569;">
                                        <?= htmlspecialchars(ucfirst($sym['symptom_keyword'])) ?> 
                                        <?= $sym['mapped_nutrient'] ? '<span style="color:#059669; font-weight:600; margin-left:4px;">('.htmlspecialchars($sym['mapped_nutrient']).')</span>' : '' ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Messages -->
                        <div class="chat-messages" id="chatMessages">
                            <div style="text-align:center;padding:40px;color:var(--text-secondary);">
                                <i class="fas fa-spinner fa-spin" style="font-size:1.5rem;margin-bottom:12px;display:block;color:var(--primary);"></i>
                                Loading conversation…
                            </div>
                        </div>

                        <!-- Input / Read-only -->
                        <?php if (!$is_admin): ?>
                            <div class="chat-input-area">
                                <div class="typing-indicator" id="typingIndicator"
                                     style="position:absolute;top:4px;left:40px;font-size:0.75rem;color:var(--text-secondary);opacity:0;transition:opacity 0.2s;">
                                    Client is typing…
                                </div>
                                <form id="messageForm">
                                    <input type="file" id="fileInput" name="attachment" style="display:none;">
                                    <div class="input-pill-container">
                                        <button type="button" class="icon-btn" id="attachBtn" title="Attach file">
                                            <i class="fas fa-plus"></i>
                                        </button>

                                        <textarea class="chat-input" placeholder="Type a message…" rows="1"
                                                  oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px'"></textarea>
                                        <div class="input-actions">
                                            <button type="submit" class="icon-btn send-btn">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="admin-readonly-bar">
                                <i class="fas fa-eye"></i> Read-Only View &mdash; Admin Access
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <!-- Empty state -->
                        <div class="chat-empty">
                            <div class="chat-empty-icon">
                                <i class="fas fa-comment-medical"></i>
                            </div>
                            <div class="chat-empty-title">Wellness Inbox</div>
                            <p class="chat-empty-sub">Select a client from the list to view their conversation and wellness messages.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div><!-- /.messaging-wrapper -->

            <?php if ($selected_client_id): ?>
                <script src="scripts/chat-controller.js?v=102"></script>
                <script>
                    (function() {
                        const initChat = () => {
                            console.log("Initializing Chat System...");
                            const chat = new ChatController(<?= $staff_id ?>, '<?= $_SESSION['user_role'] ?>', <?= $selected_client_id ?>);

                            window.currentChat = chat;
                        };
                        // Start immediately, but if it fails (rare), wait for DOM
                        if (document.readyState === 'complete' || document.readyState === 'interactive') {
                            initChat();
                        } else {
                            document.addEventListener('DOMContentLoaded', initChat);
                        }
                    })();
                </script>
            <?php endif; ?>

            <script>
                // Spotlight tracking
                const spotlight = document.getElementById('spotlight');
                if (spotlight) {
                    document.addEventListener('mousemove', (e) => {
                        spotlight.style.left = e.clientX + 'px';
                        spotlight.style.top  = e.clientY + 'px';
                    });
                }
            </script>

        </main>
    </div>
</body>
</html>
