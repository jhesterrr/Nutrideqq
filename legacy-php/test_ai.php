<?php
require_once 'database.php';
require_once 'api/ai_service.php';

echo "<h2>🧪 NutriDeq AI Engine Test</h2>";
echo "<hr>";

// Step 1: Test the raw Gemini API connection
echo "<h3>Step 1: Testing Gemini API connection...</h3>";
$url = 'https://api.groq.com/openai/v1/chat/completions';
$groqKey = getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? 'YOUR_GROQ_API_KEY');
$data = [
    "model"    => "llama-3.1-8b-instant",
    "messages" => [["role" => "user", "content" => "Reply with just the word: CONNECTED"]]
];
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $groqKey]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $decoded = json_decode($response, true);
    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? 'No text';
    echo "✅ <strong>API Connected!</strong> Gemini replied: <em>" . htmlspecialchars($text) . "</em><br><br>";
} else {
    echo "❌ <strong>API Failed! HTTP $httpCode</strong><br>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    exit;
}

// Step 2: Test full NLP analysis
echo "<h3>Step 2: Testing NLP Symptom & Burnout extraction...</h3>";
$ai = new NutriDeqAI();
$testMessage = "I am feeling extremely tired all the time, I have bad muscle cramps in my legs, and I am honestly so stressed out and exhausted that I want to quit this diet.";
// Use user_id=1 and message_id=0 as a test (won't break anything)
$result = $ai->analyzeMessage(1, 0, $testMessage);

if ($result) {
    echo "✅ <strong>NLP Engine Works!</strong><br>";
    echo "📊 Sentiment Score: <strong>" . $result['sentiment_score'] . "/10</strong><br>";
    echo "🔥 Burnout Flag: <strong>" . ($result['burnout_flag'] ? 'YES - HIGH BURNOUT' : 'No') . "</strong><br>";
    echo "🩺 Symptoms Found: <strong>" . implode(', ', $result['symptoms']) . "</strong><br>";
} else {
    echo "❌ <strong>NLP Engine Failed.</strong> Check api/ai_error.log for details.<br>";
    if (file_exists(__DIR__ . '/api/ai_error.log')) {
        echo "<pre>" . htmlspecialchars(file_get_contents(__DIR__ . '/api/ai_error.log')) . "</pre>";
    }
}

// Step 3: Check what's in the DB
echo "<h3>Step 3: Checking what was saved to the database...</h3>";
try {
    $database = new Database();
    $pdo = $database->getConnection();
    $logs = $pdo->query("SELECT * FROM ai_burnout_logs ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $symps = $pdo->query("SELECT * FROM ai_extracted_symptoms ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    echo "📋 Burnout Logs in DB: <strong>" . count($logs) . "</strong><br>";
    echo "🩺 Symptoms in DB: <strong>" . count($symps) . "</strong><br>";
    if (!empty($symps)) {
        echo "<ul>";
        foreach ($symps as $s) {
            echo "<li>{$s['symptom_keyword']} → <em>{$s['mapped_nutrient']}</em></li>";
        }
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "❌ DB Error: " . $e->getMessage();
}

echo "<hr><h3>✅ Test Complete!</h3>";
?>
