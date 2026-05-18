<?php
$session_path = 'c:\xampp\htdocs\nutrideq\api\sessions';
if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
session_save_path($session_path);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

require_once '../database.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$client_id = $_GET['client_id'] ?? $_POST['client_id'] ?? null;
if (!$client_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Client ID is required']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Fetch client
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();

    if (!$client) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Client not found']);
        exit();
    }

    // Prepare prompt
    $name = htmlspecialchars($client['name']);
    $gender = htmlspecialchars($client['gender'] ?? 'N/A');
    $age = htmlspecialchars($client['age'] ?? 'N/A');
    $weight = htmlspecialchars($client['weight'] ?? 'N/A');
    $height = htmlspecialchars($client['height'] ?? 'N/A');
    $waist = htmlspecialchars($client['waist_circumference'] ?? 'N/A');
    $hip = htmlspecialchars($client['hip_circumference'] ?? 'N/A');
    $conditions = htmlspecialchars($client['health_conditions'] ?? 'None');
    $restrictions = htmlspecialchars($client['dietary_restrictions'] ?? 'None');
    $goals = htmlspecialchars($client['goals'] ?? 'General Health');
    $notes = htmlspecialchars($client['notes'] ?? 'None');

    // Call Groq API
    $apiKey = getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? 'YOUR_GROQ_API_KEY');
    $apiUrl = 'https://api.groq.com/openai/v1/chat/completions';
    $model = 'llama-3.1-8b-instant';

    $prompt = "You are the NutriDeq Clinical Nutrition AI. Generate a professional, highly detailed, visually striking clinical nutritional recommendation report for a registered dietician to use when creating a custom meal plan for this client.

Client Information:
- Name: $name
- Age: $age years
- Gender: $gender
- Weight: $weight kg
- Height: $height cm
- Waist Circumference: $waist cm
- Hip Circumference: $hip cm
- Health Conditions: $conditions
- Dietary Restrictions: $restrictions
- Goals: $goals
- Dietician Notes: $notes

Format the response in clean, semantic, modern HTML. Do not output any Markdown tags like ```html ... ``` or conversational greetings/closing remarks. Output ONLY the raw HTML string containing these sections wrapped in a cohesive layout:
1. <div class='clinical-section'><h3><i class='fas fa-chart-line'></i> Clinical Assessment</h3>...
   Calculate the BMI and waist-to-hip ratio if height/weight/waist/hip are available, and give clinical interpretation of metabolic risk.
2. <div class='clinical-section'><h3><i class='fas fa-clipboard-list'></i> Critical Nutrient Targets</h3>...
   State exactly what nutrients the user needs based on their health conditions, goals, and restrictions. Highlight them in beautiful pill badges (e.g., <span class='nutrient-pill'>Vitamin C</span>).
3. <div class='clinical-section'><h3><i class='fas fa-utensils'></i> Suggested FCT Food Strategies</h3>...
   Specific official DOST-FCT food categories (Vegetables, Fruits, Grains, Lean Proteins, Fats) that are highly recommended, and foods to avoid.
4. <div class='clinical-section'><h3><i class='fas fa-heartbeat'></i> Lifestyle & Meal Timing Guidance</h3>...
   Actionable dietician tips on structuring breakfast, lunch, snacks, and dinner for optimal metabolic rate and recovery.

Ensure the design looks like a premium, clinical, glassmorphic card interface, using sleek semantic wrappers, strong bold indicators, and a clean professional palette. Keep the HTML clean and self-contained.";

    $data = [
        "model" => $model,
        "messages" => [
            ["role" => "system", "content" => "You are a clinical NLP engine. Always respond with raw semantic HTML only. No Markdown wrapper. No explanation outside the HTML."],
            ["role" => "user", "content" => $prompt]
        ],
        "temperature" => 0.2,
        "max_tokens" => 2048
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("cURL Error: " . $curlError);
    }

    if ($httpCode === 200 && $response) {
        $responseData = json_decode($response, true);
        $aiOutput = $responseData['choices'][0]['message']['content'] ?? '';
        
        // Strip markdown code fences if LLM outputted them despite instructions
        $aiOutput = preg_replace('/^```html\s*/i', '', $aiOutput);
        $aiOutput = preg_replace('/```$/', '', $aiOutput);
        $aiOutput = trim($aiOutput);

        echo json_encode([
            'success' => true,
            'recommendation' => $aiOutput
        ]);
    } else {
        throw new Exception("Groq API returned HTTP $httpCode: $response");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
