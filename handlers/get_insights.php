<?php
// handlers/get_insights.php — live Wellness Insights payload.
// Returns the ACTIVE symptoms for a client so the Wellness Insights card can
// update in real time (polled by chat-controller.js on both user and staff
// message pages) without a page reload.
//
// Auth: session required.
//   - client role : only their OWN insights (forced server-side)
//   - staff role  : only clients with an existing conversation with them
//                   (same participant check as handlers/get_messages.php)
//   - admin role  : read-only, any client (matches get_messages.php policy)

require_once dirname(__DIR__) . '/session_bootstrap.php';
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/includes/SymptomLifecycle.php';
require_once dirname(__DIR__) . '/includes/ai_symptom_kb.php';
require_once __DIR__ . '/insights_payload.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['user_role'] ?? 'regular';
$contact_id = isset($_GET['contact_id']) ? intval($_GET['contact_id']) : 0;

if ($contact_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'contact_id required']);
    exit;
}

try {
    $pdo = (new Database())->getConnection();

    // Resolve which users.id owns the client this conversation is about —
    // mirroring get_messages.php's participant resolution.
    if ($user_role === 'staff') {
        // Staff may only read clients who actually have a conversation with them.
        $stmt = $pdo->prepare(
            "SELECT c.user_id FROM clients c
             JOIN conversations conv ON conv.client_id = c.id
             WHERE c.id = ? AND conv.dietitian_id = ?
             LIMIT 1"
        );
        $stmt->execute([$contact_id, $user_id]);
        $subject = $stmt->fetchColumn();
    } elseif ($user_role === 'admin') {
        $stmt = $pdo->prepare("SELECT user_id FROM clients WHERE id = ? LIMIT 1");
        $stmt->execute([$contact_id]);
        $subject = $stmt->fetchColumn();
    } else {
        // Client: always themselves — the contact_id is ignored.
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE user_id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $subject = $user_id;
    }

    if ($subject === false || !$subject) {
        echo json_encode(['success' => true, 'symptoms' => [], 'active_count' => 0]);
        exit;
    }

    $items = nd_build_insights_payload($pdo, (int)$subject, 5);

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(array_merge(['success' => true], $items));
} catch (Throwable $e) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load insights']);
}
