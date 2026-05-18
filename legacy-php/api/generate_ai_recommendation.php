<?php
$session_path = 'c:\xampp\htdocs\nutrideq\api\sessions';
if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
session_save_path($session_path);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../database.php';
require_once 'ai_service.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'staff') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_GET['user_id'] ?? null;
$staff_id = $_SESSION['user_id'];

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Missing User ID']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

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
    $stmt = $pdo->prepare("SELECT DISTINCT symptom_keyword, mapped_nutrient FROM ai_extracted_symptoms WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $symptoms = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    $stmt = $pdo->prepare("INSERT INTO ai_recommendations (user_id, staff_id, recommendation_text, data_points_used, status) VALUES (?, ?, ?, ?, 'draft')");
    $stmt->execute([$user_id, $staff_id, $recommendation, $data_points]);
    $rec_id = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'recommendation' => $recommendation,
        'recommendation_id' => $rec_id
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
