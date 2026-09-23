<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once dirname(__DIR__) . '/session_bootstrap.php';

header('Content-Type: application/json');

require_once '../database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'regular';

if ($user_role === 'admin') {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Admins are read-only']);
    exit;
}

$recipient_id = isset($_POST['recipient_id']) ? intval($_POST['recipient_id']) : 0;
$message_text = isset($_POST['message']) ? trim($_POST['message']) : '';
$reply_to_id = isset($_POST['reply_to_id']) ? intval($_POST['reply_to_id']) : null;
if ($reply_to_id <= 0) $reply_to_id = null;

// Validation: Message can be empty IF an attachment exists
if ($recipient_id <= 0 || (empty($message_text) && !isset($_FILES['attachment']))) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    try {
        AuditLogger::init($pdo);
    } catch (Throwable $e) {
        error_log("AuditLogger init skipped: " . $e->getMessage());
    }

    // 1. Handle Attachment Upload
    $attachment_path = null;
    $msg_type = 'text';

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'png', 'jpg', 'jpeg'];

        if (in_array($ext, $allowed)) {
            $upload_dir = '../uploads/reports/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $filename = uniqid('report_', true) . '.' . $ext;
            $target = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $target)) {
                $attachment_path = 'uploads/reports/' . $filename;
                $msg_type = ($ext === 'pdf') ? 'file' : 'image';
            }
        }
    }

    // 2. Participant Resolution
    $client_id = 0;
    $dietitian_id = 0;
    $sender_type = '';

    if ($user_role === 'staff') {
        $sender_type = 'staff';
        $dietitian_id = $user_id;
        $client_id = $recipient_id;
    } else {
        $sender_type = 'client';
        $dietitian_id = $recipient_id;
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE user_id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $client_id = $row['id'] ?? 0;
    }

    // 3. Conversation Logic
    $stmt = $pdo->prepare("SELECT id FROM conversations WHERE client_id = ? AND dietitian_id = ? LIMIT 1");
    $stmt->execute([$client_id, $dietitian_id]);
    $conv = $stmt->fetch();
    $conv_id = 0;

    if ($conv) {
        $conv_id = $conv['id'];
        $pdo->prepare("UPDATE conversations SET last_message_at = NOW(), status='open' WHERE id=?")->execute([$conv_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO conversations (client_id, dietitian_id, status, last_message_at) VALUES (?, ?, 'open', NOW())");
        $stmt->execute([$client_id, $dietitian_id]);
        $conv_id = $pdo->lastInsertId();
    }

    // 4. Insert Message
    // IMPORTANT: If sender is client, sender_id MUST be $client_id (from clients table)
    // If sender is staff, sender_id is $user_id (from users table)
    $final_sender_id = ($sender_type === 'client') ? $client_id : $user_id;

    $encryptedContent = class_exists('Encryption') ? Encryption::encryptIfExists($message_text) : $message_text;

    $ins_msg = $pdo->prepare("INSERT INTO wellness_messages (conversation_id, sender_type, sender_id, content, attachment_path, message_type, reply_to_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $ins_msg->execute([$conv_id, $sender_type, $final_sender_id, $encryptedContent, $attachment_path, $msg_type, $reply_to_id]);
    $new_msg_id = $pdo->lastInsertId();
    
    try {
        AuditLogger::log('message_send', 'wellness_messages', (string)$new_msg_id, $user_id, $user_role);
    } catch (Throwable $e) {
        error_log("AuditLogger log skipped: " . $e->getMessage());
    }

    // Fetch reply parent details if applicable
    $reply_message = null;
    $reply_sender_name = null;
    if ($reply_to_id) {
        $p_stmt = $pdo->prepare("
            SELECT wm.content,
            CASE 
                WHEN wm.sender_type = 'client' THEN c.name
                WHEN wm.sender_type = 'staff' THEN u.name
                WHEN wm.sender_type = 'ai' THEN 'NutriDeq AI'
                ELSE 'Unknown'
            END as sender_name
            FROM wellness_messages wm
            LEFT JOIN clients c ON wm.sender_type = 'client' AND wm.sender_id = c.id
            LEFT JOIN users u ON wm.sender_type = 'staff' AND wm.sender_id = u.id
            WHERE wm.id = ?
        ");
        $p_stmt->execute([$reply_to_id]);
        $parent = $p_stmt->fetch();
        if ($parent) {
            $reply_message = $parent['content'];
            $reply_sender_name = $parent['sender_name'];
            if (strtolower($reply_sender_name) === 'users' || strtolower($reply_sender_name) === 'admin') {
                $reply_sender_name = 'Dietician Staff';
            }
        }
    }

    // --- 5. NLP AI Integration (Passive Monitoring - Non-blocking) ---
    $nlp_result = null;
    if ($sender_type === 'client' && !empty($message_text)) {
        try {
            if (file_exists('../api/ai_service.php')) {
                require_once '../api/ai_service.php';
                if (class_exists('NutriDeqAI')) {
                    $ai = new NutriDeqAI();
                    $nlp_result = $ai->analyzeMessage($user_id, $new_msg_id, $message_text);
                }
            }
        } catch (Throwable $aiEx) {
            error_log("NutriDeq AI passive analysis skipped: " . $aiEx->getMessage());
        }

        // --- 5b. Symptom lifecycle (real-time resolution) ---
        // The Wellness Insights card must mirror what the client is saying
        // RIGHT NOW: new symptoms were just detected above; messages like
        // "my fatigue is gone" / "hindi na ako pagod" resolve open ones.
        try {
            require_once '../includes/SymptomLifecycle.php';
            SymptomLifecycle::process($user_id, (int)$new_msg_id, $message_text);
        } catch (Throwable $lifeEx) {
            error_log("SymptomLifecycle skipped: " . $lifeEx->getMessage());
        }
    }

    // --- 5c. Live insights payload for the Wellness Insights card ---
    $live_insights = null;
    if ($sender_type === 'client') {
        try {
            require_once '../includes/SymptomLifecycle.php';
            require_once '../includes/ai_symptom_kb.php';
            require_once __DIR__ . '/insights_payload.php';
            $live_insights = nd_build_insights_payload($pdo, (int)$user_id);
        } catch (Throwable $insEx) {
            error_log("Live insights payload skipped: " . $insEx->getMessage());
        }
    }

    // Clear any accidental output before sending JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');

    echo json_encode([
        'success' => true,
        'message' => [
            'id' => $new_msg_id,
            'message' => $message_text,
            'sender_type' => $sender_type,
            'attachment_path' => $attachment_path,
            'message_type' => $msg_type,
            'file_name' => 'NutriDeq-Clinical-Report.pdf',
            'pretty_time' => date('g:i A'),
            'type' => 'sent',
            'is_unsent' => false,
            'reply_to_id' => $reply_to_id,
            'reply_message' => $reply_message,
            'reply_sender_name' => $reply_sender_name
        ],
        'insights' => $live_insights,
        'nlp' => is_array($nlp_result) ? [
            'sentiment_score' => (int)($nlp_result['sentiment_score'] ?? 0),
            'symptoms' => array_values($nlp_result['symptoms'] ?? [])
        ] : null
    ]);

} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}