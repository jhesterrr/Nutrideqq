<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

require_once '../database.php';
require_once 'ai_service.php';
require_once '../includes/SymptomMapper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'staff') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

verify_csrf();

$user_id = $_GET['user_id'] ?? null;
$staff_id = $_SESSION['user_id'];

// Staff may only generate recommendations for clients assigned to them
if (!user_can_access_client($user_id)) {
    echo json_encode(['success' => false, 'error' => 'Forbidden: client not assigned to you']);
    exit();
}

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Missing User ID']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    AuditLogger::init($pdo);

    // 1. GATHER DATA POINTS
    
    // User Basic Info & BMI
    $stmt = $pdo->prepare("SELECT name, weight, height FROM clients WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $bmi = 0;
    if ($user && $user['height'] > 0) {
        $bmi = round($user['weight'] / (($user['height']/100) ** 2), 1);
    }

    // Recent Symptoms (AI Extracted)
    $stmt = $pdo->prepare("SELECT symptom_keyword, mapped_nutrient, MAX(created_at) AS max_created FROM ai_extracted_symptoms WHERE user_id = ? GROUP BY symptom_keyword, mapped_nutrient ORDER BY max_created DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $symptoms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mapping provenance for the symptoms feeding this report: which mapping
    // table version + class produced each stored nutrient assignment.
    $nlpAudit = [
        'mapping_current' => SymptomMapper::auditInfo(),
        'symptoms' => [],
        'versions_used' => [],
    ];
    if (!empty($symptoms)) {
        $kwPh = implode(',', array_fill(0, count($symptoms), '?'));
        $vstmt = $pdo->prepare("
            SELECT symptom_keyword, mapping_version, mapping_class_id, COUNT(*) AS hits
            FROM ai_extracted_symptoms
            WHERE user_id = ? AND symptom_keyword IN ($kwPh)
            GROUP BY symptom_keyword, mapping_version, mapping_class_id
            ORDER BY symptom_keyword
        ");
        $vstmt->execute(array_merge([$user_id], array_column($symptoms, 'symptom_keyword')));
        $prov = $vstmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($prov as $p) {
            $nlpAudit['symptoms'][$p['symptom_keyword']][] = [
                'version' => $p['mapping_version'],
                'class_id' => $p['mapping_class_id'],
                'hits' => (int)$p['hits'],
            ];
            if ($p['mapping_version'] !== null && !in_array($p['mapping_version'], $nlpAudit['versions_used'], true)) {
                $nlpAudit['versions_used'][] = $p['mapping_version'];
            }
        }
    }

    // Recent Calorie intake (Last 3 days average)
    $stmt = $pdo->prepare("SELECT AVG(daily_cal) as avg_cal FROM (SELECT SUM(calories) as daily_cal FROM food_logs WHERE user_id = ? GROUP BY log_date ORDER BY log_date DESC LIMIT 3) as sub");
    $stmt->execute([$user_id]);
    $avg_cal = round($stmt->fetchColumn() ?? 0);

    // 2. CONSTRUCT PROMPT FOR AI
    $symptom_list = implode(", ", array_map(function($s) { return $s['symptom_keyword'] . " (" . $s['mapped_nutrient'] . ")"; }, $symptoms));
    
    $prompt = "As a Clinical Nutritionist AI, generate a brief, professional recommendation for a client with the following profile:
    - Name: {$user['name']}
    - BMI: {$bmi}
    - Recent Avg Intake: {$avg_cal} kcal/day
    - Detected Symptoms: {$symptom_list}
    
    Provide 3 specific dietary adjustments and 1 lifestyle tip. Keep it concise, clinical, and actionable. Format as a short paragraph.";

    // 3. CALL AI SERVICE
    $ai = new NutriDeqAI();
    $recommendation = $ai->generateResponse($prompt);

    // 4. SAVE AS DRAFT
    $data_points = json_encode([
        'bmi' => $bmi,
        'avg_cal' => $avg_cal,
        'symptoms' => $symptoms
    ]);

    $stmt = $pdo->prepare("INSERT INTO ai_recommendations (user_id, dietitian_id, recommendation_text, data_points_used, trigger_source, status) VALUES (?, ?, ?, ?, 'symptom_match', 'pending_review')");
    $stmt->execute([$user_id, $staff_id, $recommendation, $data_points]);
    $rec_id = $pdo->lastInsertId();

    AuditLogger::log('ai_recommendation_generate', 'ai_recommendations', (string)$rec_id, $user_id, 'staff');

    echo json_encode([
        'success' => true,
        'recommendation' => $recommendation,
        'recommendation_id' => $rec_id,
        'nlp_mapping_audit' => $nlpAudit
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
