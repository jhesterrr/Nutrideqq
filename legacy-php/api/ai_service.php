<?php
// /api/ai_service.php
require_once __DIR__ . '/../database.php';

class NutriDeqAI {
    private $apiKey;
    private $apiUrl = 'https://api.groq.com/openai/v1/chat/completions';
    private $model  = 'llama-3.1-8b-instant';
    private $pdo;

    public function __construct() {
        $this->apiKey = getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? 'YOUR_GROQ_API_KEY');
        $database = new Database();
        $this->pdo = $database->getConnection();
    }

    /**
     * The NLP Module: Analyzes chat text for burnout sentiment and physical symptoms.
     */
    public function analyzeMessage($userId, $messageId, $text) {
        $prompt = "You are the NutriDeq Clinical NLP Engine. Analyze the following message from a diet client.
Return ONLY a valid JSON object with no markdown formatting, no explanation, no extra text.
Format:
{
  \"sentiment_score\": 5,
  \"burnout_flag\": false,
  \"symptoms\": [\"symptom1\", \"symptom2\"]
}
Rules:
- sentiment_score: 1-10 (1=motivated/happy, 10=extremely stressed/exhausted/quitting)
- burnout_flag: true if sentiment_score >= 8
- symptoms: array of physical symptoms mentioned (e.g. fatigue, cramps, headaches, nausea, dizziness). Empty array [] if none.

Client Message: \"$text\"";

        $data = [
            "model"    => $this->model,
            "messages" => [
                ["role" => "system", "content" => "You are a clinical NLP engine. Always respond with valid JSON only. No markdown. No explanation."],
                ["role" => "user",   "content" => $prompt]
            ],
            "temperature"     => 0.1,
            "max_tokens"      => 256,
            "response_format" => ["type" => "json_object"]
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response  = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError) {
            file_put_contents(__DIR__ . '/ai_error.log', date('Y-m-d H:i:s') . " cURL Error: $curlError\n", FILE_APPEND);
            return false;
        }

        if ($httpCode === 200 && $response) {
            $responseData = json_decode($response, true);
            $aiOutput = $responseData['choices'][0]['message']['content'] ?? null;

            if ($aiOutput) {
                $result = json_decode($aiOutput, true);

                if ($result) {
                    $score = intval($result['sentiment_score'] ?? 1);
                    $flag  = ($result['burnout_flag'] ?? false) ? 1 : 0;
                    $symptoms = $result['symptoms'] ?? [];

                    // 1. Save Burnout Log
                    $stmt = $this->pdo->prepare("INSERT INTO ai_burnout_logs (user_id, message_id, sentiment_score, burnout_flag, analysis_text) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$userId, $messageId, $score, $flag, $text]);

                    // 2. Save Extracted Symptoms with nutrient mapping
                    foreach ($symptoms as $symptom) {
                        $mappedNutrient = null;
                        $lower = strtolower(trim($symptom));
                        // Mapping to exact keys in fct_nutrients table
                        if (str_contains($lower, 'cramp'))                        $mappedNutrient = 'Potassium, K';
                        elseif (str_contains($lower, 'fatigue') || str_contains($lower, 'tired') || str_contains($lower, 'exhausted')) $mappedNutrient = 'Iron';
                        elseif (str_contains($lower, 'dizz'))                     $mappedNutrient = 'Sodium';
                        elseif (str_contains($lower, 'headache'))                 $mappedNutrient = 'Calcium'; // Proxy for missing Magnesium
                        elseif (str_contains($lower, 'nausea'))                   $mappedNutrient = 'Vitamin C'; // General wellness proxy
                        elseif (str_contains($lower, 'weak'))                     $mappedNutrient = 'Iron';
                        elseif (str_contains($lower, 'bloat'))                    $mappedNutrient = 'Fiber, total dietary';

                        $stmtS = $this->pdo->prepare("INSERT INTO ai_extracted_symptoms (user_id, message_id, symptom_keyword, mapped_nutrient) VALUES (?, ?, ?, ?)");
                        $stmtS->execute([$userId, $messageId, $symptom, $mappedNutrient]);
                    }

                    return $result;
                } else {
                    file_put_contents(__DIR__ . '/ai_error.log', date('Y-m-d H:i:s') . " JSON decode failed: $aiOutput\n", FILE_APPEND);
                }
            } else {
                file_put_contents(__DIR__ . '/ai_error.log', date('Y-m-d H:i:s') . " No content in response: $response\n", FILE_APPEND);
            }
        } else {
            file_put_contents(__DIR__ . '/ai_error.log', date('Y-m-d H:i:s') . " HTTP $httpCode: $response\n", FILE_APPEND);
        }

        return false;
    }
}
?>
