<?php
// Threshold-gated personalized nutrition guidance.
// Deterministic nutrient rules remain the source of truth; NLP symptoms and
// patient context are used to prioritize and explain the returned guidance.
require_once dirname(__DIR__) . '/session_bootstrap.php';
require_once dirname(__DIR__) . '/includes/RecommendationEngine.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0 || !user_can_access_client($userId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }

    $pdo = (new Database())->getConnection();
    $profileStmt = $pdo->prepare("SELECT c.name, c.health_conditions, c.dietary_restrictions, c.goals FROM clients c WHERE c.user_id = ? LIMIT 1");
    $profileStmt->execute([$userId]);
    $profile = $profileStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $counts = [];
    foreach ([
        'food_logs' => "SELECT COUNT(*) FROM food_logs WHERE user_id = ? AND log_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)",
        'bmi_records' => "SELECT COUNT(*) FROM client_bmi_history WHERE user_id = ?",
        'chat_messages' => "SELECT COUNT(*) FROM wellness_messages wm JOIN conversations cv ON cv.id = wm.conversation_id JOIN clients c ON c.id = cv.client_id WHERE c.user_id = ?"
    ] as $key => $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $counts[$key] = (int)$stmt->fetchColumn();
    }
    $dataPoints = $counts['food_logs'] + min($counts['bmi_records'], 3) + min($counts['chat_messages'], 5);
    $minimum = 5;
    if ($dataPoints < $minimum) {
        echo json_encode([
            'success' => true,
            'ready' => false,
            'data_points' => $dataPoints,
            'minimum_data_points' => $minimum,
            'message' => 'Keep logging meals, progress, and symptoms so personalized guidance can be generated reliably.'
        ]);
        exit;
    }

    $activeStmt = $pdo->prepare("SELECT symptom_keyword, mapped_nutrient, MAX(created_at) AS last_seen FROM ai_extracted_symptoms WHERE user_id = ? AND status = 'active' GROUP BY symptom_keyword, mapped_nutrient ORDER BY last_seen DESC LIMIT 20");
    $activeStmt->execute([$userId]);
    $symptoms = $activeStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $appointmentStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments a JOIN clients c ON c.id = a.client_id WHERE c.user_id = ? AND a.status IN ('completed', 'attended') AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)");
    $appointmentStmt->execute([$userId]);
    $completedAppointments = (int)$appointmentStmt->fetchColumn();

    $rules = (new RecommendationEngine($pdo))->evaluateForUser($userId);
    $eat = [];
    $avoid = [];
    foreach (($rules['recommendations'] ?? []) as $recommendation) {
        if ($recommendation['action'] === 'food_based') $eat[] = $recommendation['text'];
        if ($recommendation['action'] === 'limit_intake') $avoid[] = $recommendation['text'];
    }

    $restrictions = trim((string)($profile['dietary_restrictions'] ?? ''));
    $conditions = trim((string)($profile['health_conditions'] ?? ''));
    $contextNotes = [];
    if ($restrictions !== '') $contextNotes[] = 'Respect recorded dietary restrictions: ' . $restrictions . '.';
    if ($conditions !== '') $contextNotes[] = 'Review recommendations against recorded conditions: ' . $conditions . '.';
    if ($symptoms) {
        $labels = array_values(array_unique(array_map(static function ($row) { return $row['symptom_keyword']; }, $symptoms)));
        $contextNotes[] = 'Discuss active reported symptoms with the dietitian: ' . implode(', ', $labels) . '.';
    }
    if ($completedAppointments === 0) $contextNotes[] = 'No completed follow-up was found in the last 90 days; consider scheduling one.';

    echo json_encode([
        'success' => true,
        'ready' => true,
        'data_points' => $dataPoints,
        'minimum_data_points' => $minimum,
        'profile' => ['goals' => $profile['goals'] ?? '', 'restrictions_present' => $restrictions !== '', 'conditions_present' => $conditions !== ''],
        'recommendations' => [
            'eat_more' => array_values(array_unique($eat)),
            'limit_or_avoid' => array_values(array_unique($avoid)),
            'context' => $contextNotes
        ],
        'audit' => [
            'window_days' => 14,
            'food_logs' => $counts['food_logs'],
            'bmi_records' => $counts['bmi_records'],
            'chat_messages' => $counts['chat_messages'],
            'active_symptoms' => count($symptoms),
            'completed_appointments_90d' => $completedAppointments,
            'nutrient_rules' => $rules['summary'] ?? []
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to generate personalized guidance.']);
}
