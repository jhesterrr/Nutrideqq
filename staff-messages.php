<?php
require_once __DIR__ . '/session_bootstrap.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login-logout/NutriDeqN-Login.php");
    exit();
}
if (($_SESSION['user_role'] ?? '') !== 'staff') {
    header('Location: dashboard.php');
    exit();
}
require_once 'navigation.php';
require_once 'database.php';
require_once __DIR__ . '/includes/ai_symptom_kb.php';

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

// Fetch Clients with Last Message (uses conversations + wellness_messages tables)
$clients = [];
try {
    $dt_check     = $is_admin ? "" : "AND conv.dietitian_id = ?";
    $client_check = $is_admin ? "" : "WHERE c.staff_id = ?";

    $sql = "
        SELECT
            c.id, c.user_id, c.name, c.email, s.name as staff_name,
            conv.id as conversation_id,
            (SELECT content FROM wellness_messages wm
             WHERE wm.conversation_id = conv.id
             ORDER BY wm.created_at DESC LIMIT 1) as last_message,
            (SELECT message_type FROM wellness_messages wm2
             WHERE wm2.conversation_id = conv.id
             ORDER BY wm2.created_at DESC LIMIT 1) as last_msg_type,
            (SELECT COUNT(*) FROM wellness_messages wm3
             WHERE wm3.conversation_id = conv.id
             AND wm3.is_read = 0 AND wm3.sender_type = 'client') as unread_count,
            (SELECT created_at FROM wellness_messages wm4
             WHERE wm4.conversation_id = conv.id
             ORDER BY wm4.created_at DESC LIMIT 1) as last_msg_time
        FROM clients c
        LEFT JOIN users s ON c.staff_id = s.id
        LEFT JOIN conversations conv ON conv.client_id = c.id $dt_check
        $client_check
        ORDER BY c.name
    ";
    $stmt = $pdo->prepare($sql);
    $params = [];
    if (!$is_admin) {
        $params = [$staff_id, $staff_id];
    }
    $stmt->execute($params);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('NutriDeq error (staff-messages clients): ' . $e->getMessage());
    $clients = [];
}

// Fallback sample client data for demonstration when database returns no results
if (empty($clients)) {
    $clients = [
        [
            'id' => 1,
            'user_id' => 101,
            'name' => 'John Michael Santos',
            'email' => 'john.santos@example.com',
            'staff_name' => 'Dr. Maria Santos',
            'last_message' => 'Thank you for the meal plan, I feel much better now!',
            'last_msg_type' => 'text',
            'unread_count' => 2,
            'last_msg_time' => date('Y-m-d H:i:s', strtotime('-2 hours'))
        ],
        [
            'id' => 2,
            'user_id' => 102,
            'name' => 'Maria Garcia',
            'email' => 'maria.garcia@example.com',
            'staff_name' => 'Dr. Maria Santos',
            'last_message' => 'I have been following the diet plan for a week now.',
            'last_msg_type' => 'text',
            'unread_count' => 0,
            'last_msg_time' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ],
        [
            'id' => 3,
            'user_id' => 103,
            'name' => 'James Rodriguez',
            'email' => 'james.rodriguez@example.com',
            'staff_name' => 'Dr. Maria Santos',
            'last_message' => 'Can we adjust my macro goals?',
            'last_msg_type' => 'text',
            'unread_count' => 1,
            'last_msg_time' => date('Y-m-d H:i:s', strtotime('-3 hours'))
        ],
        [
            'id' => 4,
            'user_id' => 104,
            'name' => 'Anna Reyes',
            'email' => 'anna.reyes@example.com',
            'staff_name' => 'Dr. Maria Santos',
            'last_message' => '📷 Progress photo',
            'last_msg_type' => 'image',
            'unread_count' => 0,
            'last_msg_time' => date('Y-m-d H:i:s', strtotime('-5 days'))
        ],
        [
            'id' => 5,
            'user_id' => 105,
            'name' => 'Carlos Mendoza',
            'email' => 'carlos.mendoza@example.com',
            'staff_name' => 'Dr. Maria Santos',
            'last_message' => 'I am experiencing some fatigue lately.',
            'last_msg_type' => 'text',
            'unread_count' => 3,
            'last_msg_time' => date('Y-m-d H:i:s', strtotime('-30 minutes'))
        ]
    ];
}

$selected_client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : null;
$selected_client    = null;
if ($selected_client_id) {
    foreach ($clients as $c) {
        if ($c['id'] == $selected_client_id) { $selected_client = $c; break; }
    }
}

// Optional: select the first client only when ?demo=1 is explicitly passed
if (!$selected_client && !empty($clients) && isset($_GET['demo'])) {
    $selected_client = $clients[0];
    $selected_client_id = $selected_client['id'];
}

// Fetch AI Insights for the Selected Client
// Uses the SAME shared payload builder as the live client renderer, so the
// dietitian's server-rendered card matches the post-poll JS render exactly
// (active symptoms only, canonical labels, one row per clinical class).
$ai_data = ['score' => 1, 'flag' => false, 'symptoms' => []];
$insights = ['symptoms' => [], 'active_count' => 0];
if ($selected_client) {
    try {
        $stmt = $pdo->prepare("SELECT sentiment_score, burnout_flag FROM ai_burnout_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$selected_client['user_id']]);
        if ($row = $stmt->fetch()) {
            $ai_data['score'] = $row['sentiment_score'];
            $ai_data['flag']  = (bool)$row['burnout_flag'];
        }

        require_once __DIR__ . '/includes/SymptomLifecycle.php';
        require_once __DIR__ . '/handlers/insights_payload.php';
        $insights = nd_build_insights_payload($pdo, (int)$selected_client['user_id']) ?: ['symptoms' => [], 'active_count' => 0];
    } catch (Throwable $e) {
        error_log('NutriDeq (staff-messages insights): ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="scripts/theme-toggle.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Wellness Inbox | NutriDeq</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/components.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/modern-messages.css?v=116">
    <link rel="stylesheet" href="css/premium-revamp.css">
    <script src="scripts/csrf-fetch.js"></script>
</head>
<body data-ci-view="staff">
    <div class="nd-app">
        <?php include 'includes/sidebar.php'; ?>
        <main class="nd-main">
            <div class="mesh-gradient-container dashboard-mesh">
                <div class="mesh-blob blob-1"></div>
                <div class="mesh-blob blob-2"></div>
                <div class="mesh-blob blob-3"></div>
            </div>
            <div class="glass-noise"></div>
            <div class="spotlight" id="spotlight"></div>

            <div class="messaging-wrapper <?= $selected_client_id ? 'view-chat' : 'view-list' ?>" id="messagingWrapper">
                <!-- ── Inbox Sidebar ── -->
                <div class="msg-sidebar">
                    <div class="msg-sidebar-header">
                        <div class="msg-sidebar-header-top">
                            <div>
                                <h2><i class="fas fa-envelope-open-text"></i> Wellness Inbox</h2>
                                <div class="msg-sidebar-sub"><?= count($clients) ?> client<?= count($clients) !== 1 ? 's' : '' ?> assigned</div>
                            </div>
                        </div>

                        <div class="msg-search">
                            <i class="fas fa-search msg-search-icon"></i>
                            <input type="text" id="clientSearch" placeholder="Search clients by name..." oninput="filterClients(this.value)">
                        </div>

                        <div class="msg-tabs">
                            <button class="msg-tab active" data-filter="all" onclick="filterTab(this)">All</button>
                            <button class="msg-tab" data-filter="unread" onclick="filterTab(this)">Unread</button>
                            <button class="msg-tab" data-filter="archive" onclick="filterTab(this)">Archive</button>
                        </div>
                    </div>

                    <div class="contact-list" id="contactList">
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
                                    $previewTxt = mb_strimwidth($raw, 0, 32, '…');
                                }
                                $timeLabel = '';
                                if (!empty($client['last_msg_time'])) {
                                    $timeLabel = date('g:ia', strtotime($client['last_msg_time']));
                                }
                            ?>
                                <div class="contact-item <?= $isActive ? 'active' : '' ?>" data-unread="<?= $unread ?>" data-name="<?= htmlspecialchars(strtolower($client['name'])) ?>" onclick="openChat(<?= $client['id'] ?>)" role="button" tabindex="0">
                                    <div class="contact-avatar">
                                        <?= getInitials($client['name']) ?>
                                    </div>
                                    <div class="contact-info">
                                        <div class="contact-name"><?= htmlspecialchars($client['name']) ?></div>
                                        <div class="contact-preview"><?= htmlspecialchars($previewTxt) ?></div>
                                        <?php if (!empty($client['staff_name'])): ?>
                                            <div class="contact-dietitian">Assigned to <?= htmlspecialchars($client['staff_name']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="contact-meta">
                                        <span class="contact-time"><?= htmlspecialchars($timeLabel) ?></span>
                                        <?php if ($unread > 0): ?>
                                            <span class="unread-badge"><?= $unread ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="disclaimer-banner-v2">
                        <div class="db-icon">
                            <i class="fas fa-shield-halved"></i>
                        </div>
                        <div class="db-content">
                            <div class="db-label">NON-THERAPEUTIC NOTICE</div>
                            <div class="db-text">AI-generated sentiment scores and symptom markers are for educational insight only — not a clinical diagnosis. Always confirm all findings with a qualified dietitian.</div>
                        </div>
                    </div>
                </div>

                <!-- ── Chat Container ── -->
                <div class="msg-container">
                    <?php if ($selected_client): ?>
                        <!-- Chat Header -->
                        <div class="chat-header">
                            <div class="chat-header-user">
                                <button class="btn-dash-action" id="backToInbox" onclick="handleBackToInbox()" title="Back to inbox">
                                    <i class="fas fa-arrow-left"></i>
                                </button>
                                <div class="client-chat-avatar">
                                    <?= getInitials($selected_client['name']) ?>
                                </div>
                                <div class="chat-header-details">
                                    <div class="header-name"><?= htmlspecialchars($selected_client['name']) ?></div>
                                    <div class="header-id">ID: #<?= str_pad($selected_client['id'], 6, '0', STR_PAD_LEFT) ?></div>
                                    <?php if (!empty($selected_client['email'])): ?>
                                        <div class="header-email"><?= htmlspecialchars($selected_client['email']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="chat-header-actions"></div>
                        </div>

                        <!-- Clinical Indicators — Collapsible + Descriptive -->
                        <?php if (!empty($insights['symptoms'])): ?>
                        <?php
                            // Rendered from the shared payload: canonical labels,
                            // one row per clinical class, KB already resolved.
                            $total_syms = (int)($insights['active_count'] ?: count($insights['symptoms']));
                        ?>
                        <div class="clinical-indicators-card ci-collapsed" id="clinicalIndicatorsCard" role="region" aria-label="Clinical indicators">
                            <div class="ci-card-header">
                                <div class="ci-card-title">
                                    <div class="ci-icon-badge">
                                        <i class="fas fa-dna" aria-hidden="true"></i>
                                    </div>
                                    <div class="ci-title-text">
                                        <h3>Wellness Insights</h3>
                                        <p>AI-detected patterns with dietitian-friendly explanations</p>
                                    </div>
                                </div>
                                <div class="ci-header-actions">
                                    <div class="ci-badge-count" title="<?= $total_syms ?> detected">
                                        <span class="ci-count-num"><?= $total_syms ?></span>
                                        <span class="ci-count-label">flags</span>
                                    </div>
                                    <button type="button" class="ci-toggle-btn" id="ciToggleBtn" aria-expanded="false" aria-controls="ciCardBody" onclick="toggleClinicalIndicators()" title="Expand for details">
                                        <i class="fas fa-chevron-down"></i>
                                        <span class="ci-toggle-label">Expand</span>
                                    </button>
                                </div>
                            </div>
                            <div class="ci-card-body" id="ciCardBody">
                                <?php foreach($insights['symptoms'] as $it): ?>
                                    <div class="ci-indicator-card ci-indicator-rich"
                                         title="Detected: <?= htmlspecialchars($it['last_seen']) ?><?= ($it['occurrences'] ?? 1) > 1 ? ' · mentioned ' . (int)$it['occurrences'] . '×' : '' ?>">
                                        <div class="ci-indicator-top">
                                            <div class="ci-symptom-row">
                                                <span class="ci-symptom-icon"><?= $it['icon'] ?></span>
                                                <div class="ci-symptom-info">
                                                    <span class="ci-symptom-name"><?= htmlspecialchars($it['keyword']) ?></span>
                                                    <span class="ci-symptom-cat"><?= htmlspecialchars($it['category']) ?></span>
                                                </div>
                                            </div>
                                            <?php if (!empty($it['nutrients'])): ?>
                                                <div class="ci-arrow-divider">
                                                    <i class="fas fa-arrow-right"></i>
                                                </div>
                                                <div class="ci-nutrient-row">
                                                    <?php foreach ($it['nutrients'] as $nt): ?>
                                                        <span class="ci-nutrient-tag">
                                                            <span class="ci-nutrient-icon"><?= $nt['icon'] ?></span>
                                                            <span class="ci-nutrient-name"><?= htmlspecialchars($nt['name']) ?></span>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ci-indicator-short">
                                            <i class="fas fa-info-circle ci-detail-icon"></i>
                                            <span><?= htmlspecialchars($it['short_desc']) ?></span>
                                        </div>
                                        <details class="ci-details">
                                            <summary class="ci-details-summary">
                                                <span>Why this matters &amp; what to do</span>
                                                <i class="fas fa-angle-down"></i>
                                            </summary>
                                            <div class="ci-details-body">
                                                <div class="ci-section">
                                                    <div class="ci-section-label"><i class="fas fa-book-open"></i> What it could mean</div>
                                                    <div class="ci-section-text"><?= htmlspecialchars($it['full_desc']) ?></div>
                                                </div>
                                                <div class="ci-section">
                                                    <div class="ci-section-label"><i class="fas fa-seedling"></i> Simple steps to try</div>
                                                    <ul class="ci-action-list">
                                                        <?php foreach($it['recommended'] as $rec): ?>
                                                            <li><span class="ci-bullet">✓</span><?= htmlspecialchars($rec) ?></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                                <div class="ci-section ci-cta">
                                                    <div class="ci-section-label"><i class="fas fa-user-md"></i> When to discuss with a dietitian</div>
                                                    <div class="ci-section-text"><?= htmlspecialchars($it['when_to_ask']) ?></div>
                                                </div>
                                            </div>
                                        </details>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <!-- Collapsed state summary bar (shown when minimized) -->
                            <div class="ci-collapsed-summary" id="ciCollapsedSummary" onclick="toggleClinicalIndicators()" role="button" tabindex="0" title="Click to expand">
                                <div class="ci-summary-text">
                                    <i class="fas fa-lightbulb"></i>
                                    <span><?= $total_syms ?> wellness <?= $total_syms === 1 ? 'insight' : 'insights' ?> — click Expand for details</span>
                                </div>
                                <div class="ci-summary-chips">
                                    <?php foreach(array_slice($insights['symptoms'], 0, 5) as $it): ?>
                                        <span class="ci-summary-chip">
                                            <span><?= $it['icon'] ?></span>
                                            <span><?= htmlspecialchars($it['keyword']) ?></span>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if ($total_syms > 5): ?>
                                        <span class="ci-summary-chip ci-summary-more">+<?= $total_syms - 5 ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Messages -->
                        <div class="chat-messages" id="chatMessages">
                            <div class="chat-loading-state">
                                <div class="chat-spinner"></div>
                                <p>Loading conversation…</p>
                            </div>
                        </div>

                        <!-- Input / Read-only -->
                        <?php if (!$is_admin): ?>
                            <div class="chat-input-area">
                                <div class="typing-indicator" id="typingIndicator">
                                    <span class="typing-dots">
                                        <span></span><span></span><span></span>
                                    </span>
                                    <span>Client is typing…</span>
                                </div>
                                <form id="messageForm">
                                <input type="file" id="fileInput" name="attachment" style="display:none;">
                                <div class="input-pill-container">
                                    <button type="button" class="icon-btn" id="attachBtn" title="Attach file">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <textarea class="chat-input" placeholder="Type a message…" rows="1"></textarea>
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
                                <i class="fas fa-user-shield"></i>
                                <div class="admin-readonly-bar-content">
                                    <h4 class="admin-readonly-bar-title">
                                        Read-Only View &mdash; Admin Access
                                        <span class="admin-badge">Admin</span>
                                    </h4>
                                    <p class="admin-readonly-bar-sub">You are viewing this conversation as an administrator. Message sending is restricted to clinical staff. Use the Resolve/Status controls on the header to manage conversation state.</p>
                                </div>
                                <div class="admin-readonly-bar-lock">
                                    <i class="fas fa-lock"></i>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="chat-empty-state">
                            <div class="chat-empty-icon">
                                <i class="fas fa-comment-medical"></i>
                            </div>
                            <div class="chat-empty-title">Wellness Inbox</div>
                            <p class="chat-empty-sub">Select a client from the list to view their conversation and wellness messages.</p>
                            <div class="chat-empty-hint">
                                <i class="fas fa-lightbulb"></i>
                                <span>Use the <strong>search bar</strong> above to quickly find a client by name.</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($selected_client_id): ?>
                <script src="scripts/wellness-insights.js?v=105"></script>
                <script src="scripts/chat-controller.js?v=103"></script>
                <script>
                    (function() {
                        const initChat = () => {
                            console.log("Initializing Chat System...");
                            const chat = new ChatController(<?= $staff_id ?>, '<?= $_SESSION['user_role'] ?>', <?= $selected_client_id ?>);
                            window.currentChat = chat;
                        };
                        if (document.readyState === 'complete' || document.readyState === 'interactive') {
                            initChat();
                        } else {
                            document.addEventListener('DOMContentLoaded', initChat);
                        }
                    })();
                </script>
            <?php endif; ?>

            <script>
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

    <script>
        // Mobile single-pane navigation
        function openChat(clientId) {
            window.location.href = '?client_id=' + clientId;
        }

        function hideChat() {
            const wrapper = document.getElementById('messagingWrapper');
            if (wrapper && window.innerWidth <= 850) {
                wrapper.classList.remove('view-chat');
                wrapper.classList.add('view-list');
                window.history.pushState({}, '', window.location.pathname);
            }
        }

        function handleBackToInbox() {
            if (window.innerWidth <= 850) {
                hideChat();
                setTimeout(() => {
                    window.location.href = 'staff-messages.php';
                }, 250);
            } else {
                window.location.href = 'staff-messages.php';
            }
        }

        // Handle browser back button on mobile
        window.addEventListener('popstate', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const clientId = urlParams.get('client_id');
            const wrapper = document.getElementById('messagingWrapper');
            if (wrapper && window.innerWidth <= 850) {
                if (clientId) {
                    wrapper.classList.remove('view-list');
                    wrapper.classList.add('view-chat');
                } else {
                    wrapper.classList.remove('view-chat');
                    wrapper.classList.add('view-list');
                }
            }
        });

        // Client search filter
        function filterClients(term) {
            term = term.toLowerCase();
            const items = document.querySelectorAll('.contact-item');
            items.forEach(item => {
                const name = item.getAttribute('data-name') || '';
                if (name.includes(term)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Tab filter (visual only — extend as needed)
        function filterTab(btn) {
            document.querySelectorAll('.msg-tab').forEach(t => t.classList.remove('active'));
            btn.classList.add('active');
        }

        // ── Collapse/Expand Clinical Indicators Card (fluid) ──
        // Collapse fades the body out BEFORE the class swap so the summary
        // bar's entrance animation can play; expand relies on the CSS
        // entrance animations that start when the body becomes rendered.
        function toggleClinicalIndicators() {
            const card   = document.getElementById('clinicalIndicatorsCard');
            const body   = document.getElementById('ciCardBody');
            const sum    = document.getElementById('ciCollapsedSummary');
            const btn    = document.getElementById('ciToggleBtn');
            if (!card) return;

            const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const isCollapsing = card.classList.contains('ci-expanded');
            const icon   = btn ? btn.querySelector('i') : null;
            const label  = btn ? btn.querySelector('.ci-toggle-label') : null;

            const swap = () => {
                if (isCollapsing) {
                    card.classList.remove('ci-expanded');
                    card.classList.add('ci-collapsed');
                    if (btn) {
                        btn.setAttribute('aria-expanded', 'false');
                        btn.title = 'Expand for details';
                        btn.classList.remove('is-open'); // chevron rotates back
                    }
                    if (icon) icon.className = 'fas fa-chevron-down';
                    if (label) label.textContent = 'Expand';
                } else {
                    card.classList.remove('ci-collapsed');
                    card.classList.add('ci-expanded');
                    if (btn) {
                        btn.setAttribute('aria-expanded', 'true');
                        btn.title = 'Minimize to focus on chat';
                        btn.classList.add('is-open'); // chevron rotates 180°
                    }
                    if (icon) icon.className = 'fas fa-chevron-up';
                    if (label) label.textContent = 'Collapse';
                }
                if (body) body.classList.remove('ci-body-exit');
            };

            if (isCollapsing && !reduced && body) {
                body.classList.remove('ci-body-exit');
                void body.offsetWidth;
                body.classList.add('ci-body-exit');
                setTimeout(swap, 170); // matches .ci-body-exit duration
            } else {
                swap();
            }
        }

        // ── Shared Sidebar Toggle (mobile / tablet ≤1024px) ──
        // NOTE: sidebar.php already wires up #ndMobileNavToggle. Do NOT re-attach handlers here.
        // (Duplicate handlers would toggle the sidebar twice, cancelling the click.)
    </script>
</body>
</html>