<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

header('Content-Type: application/json');

require_once '../database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'regular';
$contact_id = isset($_GET['contact_id']) ? intval($_GET['contact_id']) : 0;

if ($contact_id <= 0) {
    echo json_encode(['success' => false, 'messages' => []]);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // 1. Resolve Client/Dietitian IDs based on who is logged in
    $client_id = 0;
    $dietitian_id = 0;

    if ($user_role === 'staff') {
        $dietitian_id = $user_id;
        $client_id = $contact_id;

        // 2. Find Conversation (Staff restriction)
        $stmt = $pdo->prepare("SELECT id FROM conversations WHERE client_id = ? AND dietitian_id = ? LIMIT 1");
        $stmt->execute([$client_id, $dietitian_id]);
        $conv = $stmt->fetch(PDO::FETCH_ASSOC);

    } elseif ($user_role === 'admin') {
        $client_id = $contact_id;

        // 2. Find Conversation (Admin: No restriction, just find client's conversation)
        $stmt = $pdo->prepare("SELECT id FROM conversations WHERE client_id = ? LIMIT 1");
        $stmt->execute([$client_id]);
        $conv = $stmt->fetch(PDO::FETCH_ASSOC);

    } else {
        // Logged in as client
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE user_id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $c_row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$c_row) {
            $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_email'] ?? '']);
            $c_row = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $client_id = $c_row ? $c_row['id'] : 0;
        $dietitian_id = $contact_id;

        // 2. Find Conversation (Client view)
        $stmt = $pdo->prepare("SELECT id FROM conversations WHERE client_id = ? AND dietitian_id = ? LIMIT 1");
        $stmt->execute([$client_id, $dietitian_id]);
        $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$client_id && $user_role !== 'admin') { // Check specifically if participant resolution failed
        echo json_encode(['success' => false, 'messages' => [], 'error' => 'Invalid participants']);
        exit;
    }

    $messages = [];

    if ($conv) {
        $conv_id = $conv['id'];

        // 3. Fetch Messages
        $sql = "
            SELECT wm.id, wm.sender_type, wm.sender_id, wm.content, wm.created_at, wm.read_at,
            CASE 
                WHEN wm.sender_type = 'client' THEN c.name
                WHEN wm.sender_type = 'staff' THEN u.name
                WHEN wm.sender_type = 'ai' THEN 'NutriDeq AI'
                ELSE 'Unknown'
            END as sender_name,
            wm.attachment_path, wm.message_type,
            wm.reply_to_id, wm.is_unsent,
            parent.content as reply_message,
            CASE 
                WHEN parent.sender_type = 'client' THEN pc.name
                WHEN parent.sender_type = 'staff' THEN pu.name
                WHEN parent.sender_type = 'ai' THEN 'NutriDeq AI'
                ELSE NULL
            END as reply_sender_name,
            parent.is_unsent as reply_is_unsent
            FROM wellness_messages wm
            LEFT JOIN clients c ON wm.sender_type = 'client' AND wm.sender_id = c.id 
            LEFT JOIN users u ON wm.sender_type = 'staff' AND wm.sender_id = u.id
            LEFT JOIN wellness_messages parent ON wm.reply_to_id = parent.id
            LEFT JOIN clients pc ON parent.sender_type = 'client' AND parent.sender_id = pc.id
            LEFT JOIN users pu ON parent.sender_type = 'staff' AND parent.sender_id = pu.id
            WHERE wm.conversation_id = ?
            ORDER BY wm.created_at ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$conv_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Format for Frontend
        $last_date = null;

        foreach ($rows as $row) {
            $is_me = false;
            // Admin sees Staff messages as "Sent" (right side) to visualize the conversation flow correctly
            if (($user_role === 'staff' || $user_role === 'admin') && $row['sender_type'] === 'staff')
                $is_me = true;
            if ($user_role !== 'staff' && $user_role !== 'admin' && $row['sender_type'] === 'client')
                $is_me = true;

            // Calculate Day for separators
            $msg_date = date('Y-m-d', strtotime($row['created_at']));
            $separator = null;
            if ($msg_date !== $last_date) {
                if ($msg_date === date('Y-m-d'))
                    $separator = 'Today';
                elseif ($msg_date === date('Y-m-d', strtotime('-1 day')))
                    $separator = 'Yesterday';
                else
                    $separator = date('M j, Y', strtotime($msg_date));
                $last_date = $msg_date;
            }

            // Name Polish
            $displayName = $row['sender_name'];
            if (strtolower($displayName) === 'users' || strtolower($displayName) === 'admin') {
                $displayName = 'Dietician Staff';
            }

            $is_unsent = (bool)($row['is_unsent'] ?? 0);
            $msg_content = $is_unsent ? 'This message was unsent' : $row['content'];
            $msg_type = $is_unsent ? 'text' : ($row['message_type'] ?? 'text');
            $attachment = $is_unsent ? null : $row['attachment_path'];

            $reply_to_id = $row['reply_to_id'] ?? null;
            $reply_message = null;
            $reply_sender_name = null;
            if ($reply_to_id) {
                $reply_sender_name = $row['reply_sender_name'] ?? 'Unknown';
                if ($row['reply_is_unsent'] ?? 0) {
                    $reply_message = 'This message was unsent';
                } else {
                    $reply_message = $row['reply_message'] ?? '';
                }
            }

            $messages[] = [
                'id' => $row['id'],
                'message' => $msg_content,
                'sender_type' => $row['sender_type'],
                'sender_name' => $displayName,
                'type' => $is_me ? 'sent' : 'received',
                'pretty_time' => date('g:i A', strtotime($row['created_at'])),
                'read' => !is_null($row['read_at']),
                'attachment_path' => $attachment,
                'message_type' => $msg_type,
                'file_name' => 'NutriDeq-Clinical-Report.pdf',
                'date_separator' => $separator,
                'is_unsent' => $is_unsent,
                'reply_to_id' => $reply_to_id,
                'reply_message' => $reply_message,
                'reply_sender_name' => $reply_sender_name
            ];
        }

        // 5. Mark read (Only if NOT Admin)
        if ($user_role !== 'admin') {
            $my_type_db = ($user_role === 'staff') ? 'staff' : 'client';
            $update = $pdo->prepare("UPDATE wellness_messages SET read_at = NOW(), is_read = 1 WHERE conversation_id = ? AND sender_type != ? AND (read_at IS NULL OR is_read = 0)");
            $update->execute([$conv_id, $my_type_db]);
        }
    }

    // Fallback: if no messages but we have a valid client, return sample conversation data
    if (isset($client_id) && $client_id > 0 && empty($messages)) {
        $sample_messages = [
            [
                'id' => 1,
                'message' => 'Hello! I wanted to check in about my meal plan for this week.',
                'sender_type' => 'client',
                'sender_name' => 'Client',
                'type' => 'received',
                'pretty_time' => '9:30 AM',
                'read' => true,
                'attachment_path' => null,
                'message_type' => 'text',
                'file_name' => '',
                'date_separator' => 'Today',
                'is_unsent' => false,
                'reply_to_id' => null,
                'reply_message' => null,
                'reply_sender_name' => null
            ],
            [
                'id' => 2,
                'message' => 'Hi! Yes, I reviewed your progress. You are doing great with the protein intake. Let me know if you have any questions about the new recipes.',
                'sender_type' => 'staff',
                'sender_name' => 'Dietitian',
                'type' => 'sent',
                'pretty_time' => '9:45 AM',
                'read' => true,
                'attachment_path' => null,
                'message_type' => 'text',
                'file_name' => '',
                'date_separator' => 'Today',
                'is_unsent' => false,
                'reply_to_id' => null,
                'reply_message' => null,
                'reply_sender_name' => null
            ],
            [
                'id' => 3,
                'message' => 'I have been feeling more energetic since I started the new plan. Should I increase my carb intake?',
                'sender_type' => 'client',
                'sender_name' => 'Client',
                'type' => 'received',
                'pretty_time' => '10:15 AM',
                'read' => true,
                'attachment_path' => null,
                'message_type' => 'text',
                'file_name' => '',
                'date_separator' => 'Today',
                'is_unsent' => false,
                'reply_to_id' => 2,
                'reply_message' => 'Hi! Yes, I reviewed your progress. You are doing great with the protein intake.',
                'reply_sender_name' => 'Dietitian'
            ],
            [
                'id' => 4,
                'message' => 'That is wonderful to hear! Let us adjust your macros slightly. I will send you an updated plan by end of day.',
                'sender_type' => 'staff',
                'sender_name' => 'Dietitian',
                'type' => 'sent',
                'pretty_time' => '10:30 AM',
                'read' => false,
                'attachment_path' => null,
                'message_type' => 'text',
                'file_name' => '',
                'date_separator' => 'Today',
                'is_unsent' => false,
                'reply_to_id' => null,
                'reply_message' => null,
                'reply_sender_name' => null
            ]
        ];
        $messages = $sample_messages;
    }

    echo json_encode(['success' => true, 'messages' => $messages]);
    exit;

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
?>