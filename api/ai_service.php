<?php
// /api/ai_service.php
// Data source: symptom-to-nutrient mapping is versioned data
// (config/symptom_nutrient_mapping.json, applied by includes/SymptomMapper.php);
// nutrient keys reference the Philippine DOST-FNRI FCT (not USDA).
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/SymptomMapper.php';

class NutriDeqAI {
    private $apiKey;
    private $apiUrl = 'https://api.groq.com/openai/v1/chat/completions';
    private $model  = 'openai/gpt-oss-20b';
    private $pdo;

    public function __construct() {
        $this->apiKey = getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? null);
        if (!$this->apiKey) {
            throw new Exception('GROQ_API_KEY environment variable is not set');
        }
        $database = new Database();
        $this->pdo = $database->getConnection();
    }

    /**
     * Shared extraction prompt â€” the single source of the production prompt.
     * The evaluation harness (dev-tools/test_ai.php --live) reuses this so the
     * evaluated prompt is byte-identical to the one used in production.
     * Taglish-aware: clients may write English, Tagalog, or mixed Taglish;
     * the engine must normalize Tagalog symptom words to English labels.
     */
    public static function buildExtractionPrompt($text) {
        return "You are the NutriDeq Clinical NLP Engine. Analyze the following message from a diet client.
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
- The client may write in English, Tagalog, or Taglish (mixed). Tagalog symptom words include: pagod / kapaguran (tired, fatigued), walang lakas / mahina (weak), hilo / nahihilo (dizzy), sakit ng ulo (headache), pulikat / kalampos (muscle cramps), kabag (bloating, gas), susuka / naiinit (nauseous). Normalize them to their English symptom terms in the output (e.g. \"pagod\" -> \"fatigue\", \"hilo\" -> \"dizziness\"). Output symptom labels in English only.

Client Message: \"$text\"";
    }

    /**
     * The NLP Module: Analyzes chat text for burnout sentiment and physical symptoms.
     */
    public function analyzeMessage($userId, $messageId, $text) {
        $prompt = self::buildExtractionPrompt($text);

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

                    // 2. Save Extracted Symptoms with nutrient mapping from the
                    //    versioned mapping table. Every row records WHICH mapping
                    //    version + class produced the assignment (audit columns,
                    //    migration 003); unmatched symptoms map to NULL and are
                    //    retained for dietitian review.
                    foreach ($symptoms as $symptom) {
                        [$mappedNutrient, $mappingClassId] = SymptomMapper::mapWithClass($symptom);

                        $stmtS = $this->pdo->prepare("INSERT INTO ai_extracted_symptoms (user_id, message_id, symptom_keyword, mapped_nutrient, mapping_version, mapping_class_id) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmtS->execute([$userId, $messageId, $symptom, $mappedNutrient, SymptomMapper::getVersion(), $mappingClassId]);
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

    public function generateResponse($prompt) {
        $data = [
            "model"    => $this->model,
            "messages" => [
                ["role" => "user", "content" => $prompt]
            ],
            "temperature" => 0.2,
            "max_tokens"  => 2048
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

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
            $content = $responseData['choices'][0]['message']['content'] ?? null;
            if ($content) {
                return trim($content);
            }
        }

        file_put_contents(__DIR__ . '/ai_error.log', date('Y-m-d H:i:s') . " HTTP $httpCode: $response\n", FILE_APPEND);
        return false;
    }
}
?>
