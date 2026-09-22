<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

header('Content-Type: application/json');

require_once __DIR__ . '/nutrient_analyzer.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_role = strtolower($_SESSION['role'] ?? $_SESSION['user_role'] ?? '');
$allowed_roles = ['regular', 'user', 'staff', 'admin'];
if (!in_array($user_role, $allowed_roles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden: Insufficient role. Detected: ' . $user_role]);
    exit;
}

try {
    $target_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (int)($_SESSION['user_id'] ?? 0);

    // Admin: any client. The client themself: own data. Staff: only assigned clients.
    if (!user_can_access_client($target_user_id)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => "Forbidden: You do not have access to this client's data."]);
        exit;
    }

    $analyzer = new NutrientAnalyzer();
    $thresholds = [
        'Iron',
        'Potassium, K',
        'Calcium',
        'Sodium',
        'Fiber, total dietary',
        'Vitamin C'
    ];

    $results = [];
    foreach ($thresholds as $nutrientName) {
        $status = $analyzer->getNutrientDebtStatus($target_user_id, $nutrientName);
        if ($status !== null) {
            $results[] = $status;
        } else {
            $results[] = [
                'nutrient' => $nutrientName,
                'avg_daily' => 0,
                'threshold' => 0,
                'is_debt' => false,
                'note' => 'Insufficient data'
            ];
        }
    }

    $debt_count = 0;
    foreach ($results as $r) {
        if (!empty($r['is_debt'])) $debt_count++;
    }

    echo json_encode([
        'success' => true,
        'user_id' => $target_user_id,
        'nutrients' => $results,
        'summary' => [
            'total_tracked' => count($results),
            'debt_count' => $debt_count,
            'status' => $debt_count === 0 ? 'balanced' : ($debt_count >= 3 ? 'critical' : 'warning')
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
