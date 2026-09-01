<?php

/**
 * NutriDeq NLP Symptom-to-Nutrient Mapping Evaluation Framework
 * 
 * Evaluates the accuracy of the NLP-based symptom extraction and nutrient mapping
 * using a confusion matrix (Precision, Recall, F1-score, Accuracy).
 *
 * Usage:
 *   php test_ai.php              # Run with mock API responses
 *   php test_ai.php --live       # Run with live Groq API (requires GROQ_API_KEY)
 */

// --- Test Dataset (Ground Truth) ---
// Each entry: client message → expected nutrients that should be mapped
$TEST_DATASET = [
    // Iron deficiency indicators
    ['message' => 'I constantly feel exhausted and weak', 'expected' => ['Iron']],
    ['message' => 'I get tired easily after minimal exertion', 'expected' => ['Iron']],
    ['message' => 'I feel fatigued all the time, even after rest', 'expected' => ['Iron']],
    ['message' => 'My energy levels are very low lately', 'expected' => ['Iron']],

    // Potassium deficiency indicators
    ['message' => 'I experience muscle cramps in my legs at night', 'expected' => ['Potassium, K']],
    ['message' => 'My calves keep cramping, especially after exercise', 'expected' => ['Potassium, K']],
    ['message' => 'I have muscle spasms that come and go', 'expected' => ['Potassium, K']],

    // Sodium deficiency indicators
    ['message' => 'I feel dizzy when I stand up quickly', 'expected' => ['Sodium']],
    ['message' => 'I get lightheaded spells throughout the day', 'expected' => ['Sodium']],

    // Calcium deficiency indicators
    ['message' => 'I have frequent headaches that won\'t go away', 'expected' => ['Calcium']],
    ['message' => 'I get throbbing headaches every morning', 'expected' => ['Calcium']],

    // Vitamin C deficiency indicators
    ['message' => 'I feel nauseous after meals', 'expected' => ['Vitamin C']],
    ['message' => 'I have been feeling queasy and nauseous', 'expected' => ['Vitamin C']],

    // Fiber deficiency indicators
    ['message' => 'I feel bloated and gassy after eating', 'expected' => ['Fiber, total dietary']],
    ['message' => 'My stomach feels swollen and bloated', 'expected' => ['Fiber, total dietary']],

    // Combined symptom cases
    ['message' => 'I feel exhausted AND get muscle cramps at night', 'expected' => ['Iron', 'Potassium, K']],
    ['message' => 'I am dizzy and fatigued', 'expected' => ['Sodium', 'Iron']],

    // Negative cases (no nutrient deficiency symptoms)
    ['message' => 'I had a great workout today', 'expected' => []],
    ['message' => 'I enjoyed my lunch and feel satisfied', 'expected' => []],
    ['message' => 'The weather is nice today', 'expected' => []],
];

// --- Mock API Responses (for offline testing) ---
// Maps a client message to the NLIP response the LLM would produce
$MOCK_RESPONSES = [
    'I constantly feel exhausted and weak' => ['sentiment_score' => 4, 'burnout_flag' => false, 'symptoms' => ['fatigue', 'weakness']],
    'I get tired easily after minimal exertion' => ['sentiment_score' => 3, 'burnout_flag' => false, 'symptoms' => ['tiredness']],
    'I feel fatigued all the time, even after rest' => ['sentiment_score' => 5, 'burnout_flag' => false, 'symptoms' => ['fatigue']],
    'My energy levels are very low lately' => ['sentiment_score' => 6, 'burnout_flag' => false, 'symptoms' => ['fatigue']],
    'I experience muscle cramps in my legs at night' => ['sentiment_score' => 3, 'burnout_flag' => false, 'symptoms' => ['muscle cramps']],
    'My calves keep cramping, especially after exercise' => ['sentiment_score' => 4, 'burnout_flag' => false, 'symptoms' => ['calf cramps']],
    'I have muscle spasms that come and go' => ['sentiment_score' => 3, 'burnout_flag' => false, 'symptoms' => ['muscle spasms']],
    'I feel dizzy when I stand up quickly' => ['sentiment_score' => 4, 'burnout_flag' => false, 'symptoms' => ['dizziness']],
    'I get lightheaded spells throughout the day' => ['sentiment_score' => 5, 'burnout_flag' => false, 'symptoms' => ['lightheadedness']],
    'I have frequent headaches that won\'t go away' => ['sentiment_score' => 6, 'burnout_flag' => false, 'symptoms' => ['headache']],
    'I get throbbing headaches every morning' => ['sentiment_score' => 5, 'burnout_flag' => false, 'symptoms' => ['headache']],
    'I feel nauseous after meals' => ['sentiment_score' => 4, 'burnout_flag' => false, 'symptoms' => ['nausea']],
    'I have been feeling queasy and nauseous' => ['sentiment_score' => 4, 'burnout_flag' => false, 'symptoms' => ['nausea']],
    'I feel bloated and gassy after eating' => ['sentiment_score' => 4, 'burnout_flag' => false, 'symptoms' => ['bloating']],
    'My stomach feels swollen and bloated' => ['sentiment_score' => 3, 'burnout_flag' => false, 'symptoms' => ['bloating']],
    'I feel exhausted AND get muscle cramps at night' => ['sentiment_score' => 7, 'burnout_flag' => false, 'symptoms' => ['fatigue', 'muscle cramps']],
    'I am dizzy and fatigued' => ['sentiment_score' => 6, 'burnout_flag' => false, 'symptoms' => ['dizziness', 'fatigue']],
    'I had a great workout today' => ['sentiment_score' => 1, 'burnout_flag' => false, 'symptoms' => []],
    'I enjoyed my lunch and feel satisfied' => ['sentiment_score' => 1, 'burnout_flag' => false, 'symptoms' => []],
    'The weather is nice today' => ['sentiment_score' => 1, 'burnout_flag' => false, 'symptoms' => []],
];

// --- Symptom-to-Nutrient Mapping (mirrors ai_service.php lines 87-101) ---
function mapSymptomToNutrient(string $symptom): ?string {
    $lower = strtolower(trim($symptom));
    if (str_contains($lower, 'cramp') || str_contains($lower, 'spasm')) return 'Potassium, K';
    if (str_contains($lower, 'fatigue') || str_contains($lower, 'tired') || str_contains($lower, 'exhausted')) return 'Iron';
    if (str_contains($lower, 'dizz'))                        return 'Sodium';
    if (str_contains($lower, 'headache'))                     return 'Calcium';
    if (str_contains($lower, 'nausea'))                       return 'Vitamin C';
    if (str_contains($lower, 'weak'))                         return 'Iron';
    if (str_contains($lower, 'bloat'))                        return 'Fiber, total dietary';
    return null;
}

// --- NLP Analysis Simulation ---
function getNlpResponse(string $message, bool $useLiveApi = false): ?array {
    global $MOCK_RESPONSES;

    if ($useLiveApi) {
        $apiKey = getenv('GROQ_API_KEY');
        if (!$apiKey) {
            echo "ERROR: GROQ_API_KEY not set. Use mock mode.\n";
            return null;
        }
        $prompt = 'You are the NutriDeq Clinical NLP Engine. Analyze: "' . $message . '" Return JSON: {"sentiment_score": 5, "burnout_flag": false, "symptoms": ["symptom1"]}';
        $data = [
            "model" => "llama-3.1-8b-instant",
            "messages" => [["role" => "user", "content" => $prompt]],
            "temperature" => 0.1,
            "max_tokens" => 256,
            "response_format" => ["type" => "json_object"]
        ];
        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        curl_close($ch);
        $decoded = json_decode($response, true);
        $content = $decoded['choices'][0]['message']['content'] ?? null;
        return $content ? json_decode($content, true) : null;
    }

    return $MOCK_RESPONSES[$message] ?? null;
}

// --- Main Evaluation ---
$useLiveApi = (in_array('--live', $argv));
echo "NutriDeq NLP Symptom-to-Nutrient Mapping — Evaluation Report\n";
echo "Mode: " . ($useLiveApi ? "LIVE API" : "Mock (offline)") . "\n";
echo str_repeat('=', 72) . "\n\n";

// Collect all unique nutrients for confusion matrix
$allNutrients = ['Iron', 'Potassium, K', 'Sodium', 'Calcium', 'Vitamin C', 'Fiber, total dietary'];

$results = []; // [nutrient => [TP, TN, FP, FN]]
foreach ($allNutrients as $n) {
    $results[$n] = ['TP' => 0, 'TN' => 0, 'FP' => 0, 'FN' => 0];
}

$totalTests = count($TEST_DATASET);
$correctPredictions = 0;

printf("%-45s | %-25s | %s\n", "Client Message", "Predicted Nutrients", "Expected");
echo str_repeat('-', 95) . "\n";

foreach ($TEST_DATASET as $test) {
    $message = $test['message'];
    $expected = $test['expected'];

    // Get NLP response (symptoms extracted by LLM)
    $nlpResult = getNlpResponse($message, $useLiveApi);
    if ($nlpResult === null) {
        echo "SKIP: Could not get NLP response for: $message\n";
        continue;
    }

    // Map extracted symptoms to nutrients
    $predicted = [];
    foreach ($nlpResult['symptoms'] as $symptom) {
        $nutrient = mapSymptomToNutrient($symptom);
        if ($nutrient !== null) {
            $predicted[] = $nutrient;
        }
    }
    $predicted = array_unique($predicted);

    // Compute confusion matrix per nutrient
    foreach ($allNutrients as $nutrient) {
        $predictedHas = in_array($nutrient, $predicted);
        $expectedHas = in_array($nutrient, $expected);

        if ($predictedHas && $expectedHas)    $results[$nutrient]['TP']++;
        elseif (!$predictedHas && !$expectedHas) $results[$nutrient]['TN']++;
        elseif ($predictedHas && !$expectedHas)  $results[$nutrient]['FP']++;
        else                                    $results[$nutrient]['FN']++;
    }

    // Check overall correctness
    $isCorrect = (count(array_diff($predicted, $expected)) === 0) && (count(array_diff($expected, $predicted)) === 0);
    if ($isCorrect) $correctPredictions++;

    printf("%-45s | %-25s | %s\n",
        substr($message, 0, 45),
        substr(implode(', ', $predicted) ?: 'none', 0, 25),
        implode(', ', $expected) ?: 'none'
    );
}

// --- Aggregate Metrics ---
echo "\n" . str_repeat('=', 72) . "\n";
echo "CONFUSION MATRIX & PERFORMANCE METRICS\n";
echo str_repeat('=', 72) . "\n\n";

printf("%-22s | %5s | %5s | %5s | %5s | %-8s | %-8s | %-8s | %-8s\n",
    "Nutrient", "TP", "TN", "FP", "FN", "Precision", "Recall", "F1-Score", "Accuracy");
echo str_repeat('-', 100) . "\n";

$overallTP = 0; $overallFP = 0; $overallTN = 0; $overallFN = 0;

foreach ($allNutrients as $nutrient) {
    $m = $results[$nutrient];
    $precision = ($m['TP'] + $m['FP']) > 0 ? $m['TP'] / ($m['TP'] + $m['FP']) : 0;
    $recall = ($m['TP'] + $m['FN']) > 0 ? $m['TP'] / ($m['TP'] + $m['FN']) : 0;
    $f1 = ($precision + $recall) > 0 ? 2 * ($precision * $recall) / ($precision + $recall) : 0;
    $accuracy = $totalTests > 0 ? ($m['TP'] + $m['TN']) / $totalTests : 0;

    $overallTP += $m['TP']; $overallFP += $m['FP']; $overallTN += $m['TN']; $overallFN += $m['FN'];

    printf("%-22s | %5d | %5d | %5d | %5d | %-8.4f | %-8.4f | %-8.4f | %-8.4f\n",
        substr($nutrient, 0, 22),
        $m['TP'], $m['TN'], $m['FP'], $m['FN'],
        $precision, $recall, $f1, $accuracy
    );
}

echo str_repeat('-', 100) . "\n";

// Overall metrics
$overallPrecision = ($overallTP + $overallFP) > 0 ? $overallTP / ($overallTP + $overallFP) : 0;
$overallRecall = ($overallTP + $overallFN) > 0 ? $overallTP / ($overallTP + $overallFN) : 0;
$overallF1 = ($overallPrecision + $overallRecall) > 0 ? 2 * ($overallPrecision * $overallRecall) / ($overallPrecision + $overallRecall) : 0;
$overallAccuracy = ($overallTP + $overallTN + $overallFP + $overallFN) > 0 ? ($overallTP + $overallTN) / ($overallTP + $overallTN + $overallFP + $overallFN) : 0;

printf("%-22s | %5d | %5d | %5d | %5d | %-8.4f | %-8.4f | %-8.4f | %-8.4f\n",
    "OVERALL", $overallTP, $overallTN, $overallFP, $overallFN,
    $overallPrecision, $overallRecall, $overallF1, $overallAccuracy
);

echo "\n";
echo "Overall Accuracy: " . number_format($overallAccuracy * 100, 2) . "%\n";
echo "Overall F1-Score: " . number_format($overallF1 * 100, 2) . "%\n";
echo "Target F1-Score:  80.00%\n";
echo "Result: " . ($overallF1 >= 0.80 ? "PASS ✓" : "BELOW TARGET — review symptom mapping rules") . "\n";

echo "\nNote: F1-score for individual nutrients may vary. The overall F1-score is the micro-average across all nutrient categories.\n";
echo "In live mode (--live), actual Groq API calls are made. In mock mode, pre-annotated responses are used.\n";
