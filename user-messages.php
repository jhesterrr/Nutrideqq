<?php
require_once __DIR__ . '/session_bootstrap.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['user_role'] !== 'user' && $_SESSION['user_role'] !== 'regular')) {
    header("Location: login-logout/NutriDeqN-Login.php");
    exit();
}

require_once 'navigation.php';
require_once 'database.php';
require_once __DIR__ . '/includes/ai_symptom_kb.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
$nav_links_array = getNavigationLinks($user_role, 'user-messages.php');

$database = new Database();
$pdo = $database->getConnection();

function getInitials($name)
{
    $p = explode(' ', $name);
    $i = '';
    foreach ($p as $x) {
        if ($x !== '')
            $i .= strtoupper($x[0]);
    }
    return substr($i, 0, 2);
}

// Debugging (Commented out)
/*
echo "<!-- Debug info: \n";
echo "User ID: " . var_export($user_id, true) . "\n";
echo "User Email: " . var_export($_SESSION['user_email'] ?? 'not set', true) . "\n";
try {
    $debug_stmt = $pdo->prepare("SELECT * FROM clients WHERE user_id = ? OR email = ?");
    $debug_stmt->execute([$user_id, $_SESSION['user_email'] ?? '']);
    $debug_clients = $debug_stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Client Query Result: " . var_export($debug_clients, true) . "\n";
} catch (Exception $e) {
    echo "Debug Query Error: " . $e->getMessage() . "\n";
}
echo "-->";
*/

// Linked Staff Logic
$client_id = null;
$assigned_staff_id = null;
$staff_members = [];

try {
    // 1. Efficient lookup using JOIN to get staff details immediately
    $stmt = $pdo->prepare("
        SELECT c.id as client_id, c.staff_id, u.name as staff_name, u.email as staff_email
        FROM clients c
        JOIN users u ON c.staff_id = u.id
        WHERE (c.user_id = :uid OR c.email = :email)
        AND u.role = 'staff'
        LIMIT 1
    ");
    $stmt->execute([
        ':uid' => $user_id,
        ':email' => $_SESSION['user_email'] ?? ''
    ]);
    $client_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($client_info) {
        $client_id = $client_info['client_id'];
        $assigned_staff_id = $client_info['staff_id'];
        $staff_members[] = [
            'id' => $client_info['staff_id'],
            'name' => $client_info['staff_name'],
            'email' => $client_info['staff_email']
        ];
    }
} catch (Exception $e) {
    // Log error if needed: error_log($e->getMessage());
}

$selected_staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : ($assigned_staff_id ?? null);
$selected_staff = null;

if ($selected_staff_id) {
    foreach ($staff_members as $s) {
        if ($s['id'] == $selected_staff_id) {
            $selected_staff = $s;
            break;
        }
    }
}

// ── Fetch AI Insights for the Logged-In User ──
$ai_data = ['score' => 1, 'flag' => false, 'symptoms' => []];
if (isset($user_id)) {
    try {
        $stmt = $pdo->prepare("SELECT sentiment_score, burnout_flag FROM ai_burnout_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$user_id]);
        if ($row = $stmt->fetch()) {
            $ai_data['score'] = $row['sentiment_score'];
            $ai_data['flag']  = (bool)$row['burnout_flag'];
        }

        $stmt = $pdo->prepare("SELECT symptom_keyword, mapped_nutrient, MAX(created_at) AS max_created FROM ai_extracted_symptoms WHERE user_id = ? GROUP BY symptom_keyword, mapped_nutrient ORDER BY max_created DESC LIMIT 5");
        $stmt->execute([$user_id]);
        $ai_data['symptoms'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log('NutriDeq (user-messages AI fetch): ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="scripts/theme-toggle.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>My Wellness Chat | NutriDeq</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/modern-messages.css?v=110">
    <link rel="stylesheet" href="css/responsive.css">
    <link rel="stylesheet" href="css/logout-modal.css">
    <!-- Platform Specific Styles -->
    <link rel="stylesheet" href="css/desktop-style.css" media="all and (min-width: 1025px)">
    <link rel="stylesheet" href="css/mobile-style.css" media="all and (max-width: 1024px)">
    <style>
        @media screen and (max-width: 768px) {
            .messaging-wrapper {
                height: calc(100vh - 70px) !important;
                margin: 0 !important;
                border-radius: 0 !important;
            }
            .msg-sidebar {
                width: 100% !important;
                max-width: 100% !important;
            }
            .chat-header-user {
                max-width: 70%;
            }
            .header-name {
                font-size: 0.9rem !important;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            #backToInbox {
                display: inline-flex !important;
            }
        }
        /* Hide back button on desktop */
        @media screen and (min-width: 769px) {
            #backToInbox { display: none !important; }
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
    </style>
    <script src="scripts/dashboard.js" defer></script>
    <link rel="stylesheet" href="css/premium-revamp.css">
    <script src="scripts/csrf-fetch.js"></script>
</head>

<body>
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">

            <div class="messaging-wrapper <?= $selected_staff_id ? 'view-chat' : 'view-list' ?>" id="messagingWrapper">
                <div class="msg-sidebar">
                    <div class="msg-sidebar-header">
                        <h2>Care Team</h2>
                    </div>

                    <div class="contact-list">
                        <?php if (empty($staff_members)): ?>
                            <div style="padding:24px; text-align:center; color:var(--text-tertiary);">
                                <p>No dietitian assigned yet. <br>Please contact support.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($staff_members as $staff): ?>
                                <?php $isActive = ($selected_staff_id == $staff['id']); ?>
                                <div class="contact-item <?= $isActive ? 'active' : '' ?>"
                                    onclick="window.location.href='?staff_id=<?= $staff['id'] ?>'">
                                    <div class="contact-avatar"
                                        style="background: var(--accent-light); color: var(--primary-green);">
                                        <?= getInitials($staff['name']) ?>
                                    </div>
                                    <div class="contact-info">
                                        <div class="contact-name"><?= htmlspecialchars($staff['name']) ?> -
                                            <?= getUserRoleText('staff') ?>
                                        </div>
                                        <div class="contact-preview" style="margin-top:4px;">Your Dedicated Dietitian</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="msg-container">
                    <?php if ($selected_staff): ?>
                        <div class="chat-header">
                            <div class="chat-header-user">
                                <button class="icon-btn" id="backToInbox"
                                    style="<?= $selected_staff_id ? 'display: inline-flex;' : 'display: none;' ?> margin-right: 8px; color: var(--primary);"
                                    onclick="window.location.href='user-messages.php'"
                                    title="Back to inbox">
                                    <i class="fas fa-arrow-left"></i>
                                </button>
                                <div class="contact-avatar"
                                    style="background: var(--accent-light); color: var(--primary-green); width:40px; height:40px; font-size:0.9rem; flex-shrink:0;">
                                    <?= getInitials($selected_staff['name']) ?>
                                </div>
                                <div style="min-width:0;">
                                    <div class="header-name"><?= htmlspecialchars($selected_staff['name']) ?> -
                                        <?= getUserRoleText('staff') ?>
                                    </div>
                                    <div class="header-status">
                                        <span class="status-dot online"></span> Online
                                    </div>
                                </div>
                            </div>
                            <!-- Context Chips (Visual Only) -->
                            <div class="context-chips">
                                <div class="info-chip"><i class="fas fa-bullseye"></i> <span>Goal: Wellness</span></div>
                                <div class="info-chip"><i class="fas fa-file-invoice"></i> <span>Plan: Active</span></div>
                            </div>
                        </div>

                        <!-- User's Wellness Insights (Education Only) -->
                        <?php if (!empty($ai_data['symptoms'])): ?>
                        <?php
                            $total_syms = count($ai_data['symptoms']);
                            $nutrientIcons = [
                                'iron' => '🩸', 'Iron' => '🩸',
                                'b12' => '💉', 'B12' => '💉', 'B-12' => '💉',
                                'potassium' => '🍌', 'Potassium' => '🍌',
                                'magnesium' => '🔮', 'Magnesium' => '🔮',
                                'calcium' => '🥛', 'Calcium' => '🥛',
                                'vitamin d' => '☀️', 'Vitamin D' => '☀️',
                                'zinc' => '🔩', 'Zinc' => '🔩',
                                'vitamin c' => '🍊', 'Vitamin C' => '🍊',
                            ];
                            $prepped = [];
                            foreach($ai_data['symptoms'] as $sym) {
                                $kb = nd_lookup_symptom($sym['symptom_keyword']);
                                $nutrients = explode(',', $sym['mapped_nutrient'] ?? '');
                                $nutrientTags = [];
                                foreach ($nutrients as $n) {
                                    $n = trim($n);
                                    if (empty($n)) continue;
                                    $icon = $nutrientIcons[$n] ?? '⚛️';
                                    $nutrientTags[] = ['icon' => $icon, 'name' => htmlspecialchars($n)];
                                }
                                $prepped[] = [
                                    'sym' => $sym,
                                    'kb'  => $kb,
                                    'nutrientTags' => $nutrientTags,
                                ];
                            }
                        ?>
                        <div class="clinical-indicators-card ci-user ci-collapsed" id="userIndicatorsCard" role="region" aria-label="My wellness insights">
                            <div class="ci-card-header ci-user-header">
                                <div class="ci-card-title">
                                    <div class="ci-icon-badge">
                                        <i class="fas fa-spa" aria-hidden="true"></i>
                                    </div>
                                    <div class="ci-title-text">
                                        <h3>My Wellness Insights</h3>
                                        <p>Personal patterns from your records — educational only</p>
                                    </div>
                                </div>
                                <div class="ci-header-actions">
                                    <div class="ci-badge-count ci-user-badge" title="<?= $total_syms ?> insights">
                                        <span class="ci-count-num"><?= $total_syms ?></span>
                                        <span class="ci-count-label">insights</span>
                                    </div>
                                    <button type="button" class="ci-toggle-btn ci-user-toggle" id="userCiToggleBtn" aria-expanded="false" aria-controls="userCiCardBody" onclick="toggleUserIndicators()" title="Expand">
                                        <i class="fas fa-chevron-down"></i>
                                        <span class="ci-toggle-label">Expand</span>
                                    </button>
                                </div>
                            </div>
                            <div class="ci-card-body" id="userCiCardBody">
                                <?php foreach($prepped as $p):
                                    $sym = $p['sym']; $kb = $p['kb']; $nutrientTags = $p['nutrientTags'];
                                ?>
                                    <div class="ci-indicator-card ci-indicator-rich" 
                                         title="Seen: <?= htmlspecialchars($sym['max_created']) ?>">
                                        <div class="ci-indicator-top">
                                            <div class="ci-symptom-row">
                                                <span class="ci-symptom-icon"><?= $kb['icon'] ?></span>
                                                <div class="ci-symptom-info">
                                                    <span class="ci-symptom-name"><?= htmlspecialchars(ucfirst($sym['symptom_keyword'])) ?></span>
                                                    <span class="ci-symptom-cat"><?= htmlspecialchars($kb['category']) ?></span>
                                                </div>
                                            </div>
                                            <?php if (!empty($nutrientTags)): ?>
                                                <div class="ci-arrow-divider">
                                                    <i class="fas fa-arrow-right"></i>
                                                </div>
                                                <div class="ci-nutrient-row">
                                                    <?php foreach ($nutrientTags as $nt): ?>
                                                        <span class="ci-nutrient-tag">
                                                            <span class="ci-nutrient-icon"><?= $nt['icon'] ?></span>
                                                            <span class="ci-nutrient-name"><?= $nt['name'] ?></span>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ci-indicator-short">
                                            <i class="fas fa-info-circle ci-detail-icon"></i>
                                            <span><?= htmlspecialchars($kb['short_desc']) ?></span>
                                        </div>
                                        <details class="ci-details">
                                            <summary class="ci-details-summary">
                                                <span>Learn more &amp; simple steps</span>
                                                <i class="fas fa-angle-down"></i>
                                            </summary>
                                            <div class="ci-details-body">
                                                <div class="ci-section">
                                                    <div class="ci-section-label"><i class="fas fa-book-open"></i> What this could mean</div>
                                                    <div class="ci-section-text"><?= htmlspecialchars($kb['full_desc']) ?></div>
                                                </div>
                                                <div class="ci-section">
                                                    <div class="ci-section-label"><i class="fas fa-seedling"></i> Simple steps to try at home</div>
                                                    <ul class="ci-action-list">
                                                        <?php foreach($kb['recommended'] as $rec): ?>
                                                            <li><span class="ci-bullet">✓</span><?= htmlspecialchars($rec) ?></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                                <div class="ci-section ci-cta">
                                                    <div class="ci-section-label"><i class="fas fa-user-md"></i> When to talk to your dietitian</div>
                                                    <div class="ci-section-text"><?= htmlspecialchars($kb['when_to_ask']) ?></div>
                                                </div>
                                            </div>
                                        </details>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="ci-collapsed-summary ci-user-summary" id="userCiCollapsedSummary" onclick="toggleUserIndicators()" role="button" tabindex="0" title="Click to expand">
                                <div class="ci-summary-text">
                                    <i class="fas fa-lightbulb"></i>
                                    <span><?= $total_syms ?> personal wellness <?= $total_syms === 1 ? 'insight' : 'insights' ?> — Expand for guidance</span>
                                </div>
                                <div class="ci-summary-chips">
                                    <?php foreach(array_slice($prepped, 0, 5) as $p): ?>
                                        <span class="ci-summary-chip">
                                            <span><?= $p['kb']['icon'] ?></span>
                                            <span><?= htmlspecialchars(ucfirst($p['sym']['symptom_keyword'])) ?></span>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if ($total_syms > 5): ?>
                                        <span class="ci-summary-chip ci-summary-more">+<?= $total_syms - 5 ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <!-- Non-therapeutic reminder (user view) -->
                        <div class="user-insight-disclaimer">
                            <i class="fas fa-shield-halved"></i>
                            <div><b>NOT MEDICAL ADVICE.</b> These AI-generated insights are for awareness &amp; education only. Always confirm with your dietitian before making changes.</div>
                        </div>
                        <?php endif; ?>

                        <div class="chat-messages" id="chatMessages">
                            <div style="text-align:center; margin-top:50px; color:var(--text-tertiary);">
                                <i class="fas fa-spinner fa-spin fa-2x"></i><br><br>Loading conversation...
                            </div>
                        </div>

                        <div class="chat-input-area">


                            <div class="typing-indicator" id="typingIndicator"
                                style="position:absolute; top:-20px; left:40px; font-size:0.75rem; color:var(--text-secondary); opacity:0; transition:opacity 0.2s;">
                                Dietitian is typing...
                            </div>

                            <form id="messageForm">
                                <input type="file" id="fileInput" name="attachment" style="display: none;">

                                <!-- PILL CONTAINER -->
                                <div class="input-pill-container">
                                    <!-- LEFT ICONS -->
                                    <button type="button" class="icon-btn" id="attachBtn" title="Add File">
                                        <i class="fas fa-plus"></i>
                                    </button>


                                    <!-- INPUT (Flex Grow) -->
                                    <textarea class="chat-input" placeholder="Message your dietitian..." rows="1"
                                        oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px'"></textarea>

                                    <!-- RIGHT ACTIONS (Margin Left Auto) -->
                                    <div class="input-actions">
                                        <button type="submit" class="icon-btn send-btn"><i
                                                class="fas fa-paper-plane"></i></button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <div
                            style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:var(--text-tertiary); opacity:0.6;">
                            <i class="fas fa-user-md fa-4x" style="margin-bottom:20px; opacity:0.3;"></i>
                            <h3 style="font-size:1.1rem; font-weight:500;">Select your Dietitian</h3>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($selected_staff_id): ?>
                <script>const BASE_URL = '';</script>
                <script src="scripts/chat-controller.js?v=102"></script>
                <script>
                    (function() {
                        const initChat = () => {
                            console.log("Initializing User Chat...");
                            const chat = new ChatController(
                                <?= $user_id ?>,
                                '<?= $_SESSION['user_role'] ?>',
                                <?= $selected_staff_id ?>
                            );

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
        </div>
    </div>

    <script>
        // ── Collapse/Expand User Wellness Insights Card ──
        function toggleUserIndicators() {
            const card   = document.getElementById('userIndicatorsCard');
            const btn    = document.getElementById('userCiToggleBtn');
            if (!card) return;

            const isCollapsing = card.classList.contains('ci-expanded');
            const icon   = btn ? btn.querySelector('i') : null;
            const label  = btn ? btn.querySelector('.ci-toggle-label') : null;

            if (isCollapsing) {
                card.classList.remove('ci-expanded');
                card.classList.add('ci-collapsed');
                if (btn) {
                    btn.setAttribute('aria-expanded', 'false');
                    btn.title = 'Expand for details';
                }
                if (icon) icon.className = 'fas fa-chevron-down';
                if (label) label.textContent = 'Expand';
            } else {
                card.classList.remove('ci-collapsed');
                card.classList.add('ci-expanded');
                if (btn) {
                    btn.setAttribute('aria-expanded', 'true');
                    btn.title = 'Minimize to focus on chat';
                }
                if (icon) icon.className = 'fas fa-chevron-up';
                if (label) label.textContent = 'Collapse';
            }
        }
    </script>
</body>

</html>

