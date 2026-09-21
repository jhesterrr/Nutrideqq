<?php
// api/get_recommendations.php
// Auditable rule-based dietary recommendations for a user, evaluated against
// PDRI 2015 (FNRI-DOST) per-demographic standards.
// GET (optionally ?user_id= for admins/staff on assigned clients).
require_once dirname(__DIR__) . '/session_bootstrap.php';
require_once dirname(__DIR__) . '/includes/RecommendationEngine.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $requested_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (int)($_SESSION['user_id'] ?? 0);
    if ($requested_user_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid user id']);
        exit;
    }

    // Admin: any user. The user themself: own data. Staff: only assigned clients.
    if (!user_can_access_client($requested_user_id)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => "Forbidden: You do not have access to this client's data."]);
        exit;
    }

    $engine = new RecommendationEngine((new Database())->getConnection());
    $result = $engine->evaluateForUser($requested_user_id);

    if (empty($result['success'])) {
        http_response_code(500);
        echo json_encode($result);
        exit;
    }

    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
