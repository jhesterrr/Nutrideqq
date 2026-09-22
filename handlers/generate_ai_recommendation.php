<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

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

    // Look for GROQ_API_KEY in environment or optional .env file
    $apiKey = getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? null);
    if (!$apiKey && file_exists(dirname(__DIR__) . '/.env')) {
        $envFile = file_get_contents(dirname(__DIR__) . '/.env');
        if (preg_match('/^GROQ_API_KEY\s*=\s*["\']?([^"\'\r\n]+)["\']?/m', $envFile, $matches)) {
            $apiKey = trim($matches[1]);
        }
    }

    $aiOutput = null;

    if ($apiKey) {
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        // Ensure SSL connection compatibility on local Windows/XAMPP
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $responseData = json_decode($response, true);
            $rawOutput = $responseData['choices'][0]['message']['content'] ?? '';
            $rawOutput = preg_replace('/^```html\s*/i', '', $rawOutput);
            $rawOutput = preg_replace('/```$/', '', $rawOutput);
            $aiOutput = trim($rawOutput);
        }
    }

    // High-fidelity Clinical Rules Engine Fallback (Guarantees zero downtime / works without cloud API)
    if (empty($aiOutput)) {
        // Calculate Biomarkers
        $w = floatval($client['weight'] ?? 0);
        $h = floatval($client['height'] ?? 0);
        $wc = floatval($client['waist_circumference'] ?? 0);
        $hc = floatval($client['hip_circumference'] ?? 0);

        $bmi = ($h > 0 && $w > 0) ? round($w / (($h / 100) ** 2), 1) : null;
        $whr = ($wc > 0 && $hc > 0) ? round($wc / $hc, 2) : null;

        $bmiCategory = 'Normal';
        $bmiColor = '#10b981';
        $bmiRisk = 'Optimal metabolic baseline. Maintain current lean tissue equilibrium.';
        if ($bmi) {
            if ($bmi < 18.5) {
                $bmiCategory = 'Underweight';
                $bmiColor = '#3b82f6';
                $bmiRisk = 'Risk of nutritional depletion and compromised lean muscle mass.';
            } elseif ($bmi >= 23 && $bmi < 25) { // WHO Asian criteria
                $bmiCategory = 'Overweight (Pre-Obese)';
                $bmiColor = '#f59e0b';
                $bmiRisk = 'Elevated visceral adipose risk. Asian cutoffs indicate mild insulin sensitivity concern.';
            } elseif ($bmi >= 25) {
                $bmiCategory = 'Obese';
                $bmiColor = '#ef4444';
                $bmiRisk = 'Significant cardiovascular and metabolic syndrome risk profile requiring targeted dietary deficit.';
            }
        }

        // Caloric Target Estimation (Harris-Benedict baseline)
        $a = intval($client['age'] ?? 30);
        $g = strtolower($client['gender'] ?? 'male');
        if ($w > 0 && $h > 0) {
            if ($g === 'female' || $g === 'f') {
                $bmr = 655.1 + (9.563 * $w) + (1.850 * $h) - (4.676 * $a);
            } else {
                $bmr = 66.5 + (13.75 * $w) + (5.003 * $h) - (6.755 * $a);
            }
            $tdee = round($bmr * 1.35); // Light activity
        } else {
            $tdee = 1800;
        }

        // Target Nutrients based on condition
        $nutrients = ['Dietary Fiber', 'Lean Protein', 'Potassium, K', 'Hydration Electrolytes', 'Vitamin C', 'Magnesium'];
        if (stripos($conditions, 'hypertension') !== false || stripos($conditions, 'blood pressure') !== false) {
            $nutrients[] = 'Low Sodium (<2g)';
            $nutrients[] = 'Omega-3 EPA/DHA';
        }
        if (stripos($conditions, 'diabetes') !== false || stripos($conditions, 'sugar') !== false) {
            $nutrients[] = 'Low Glycemic Index CHO';
            $nutrients[] = 'Chromium';
        }
        if (stripos($conditions, 'anemia') !== false) {
            $nutrients[] = 'Heme Iron';
            $nutrients[] = 'Folate (B9)';
        }

        $nutrientPills = implode(' ', array_map(function($n) {
            return "<span class='nutrient-pill'>$n</span>";
        }, $nutrients));

        $bmiText = $bmi ? "{$bmi} kg/m² ({$bmiCategory})" : "Pending Measurement";
        $whrText = $whr ? "{$whr}" : "Pending Measurement";

        $aiOutput = "
        <div class='clinical-report-wrap'>
            <div class='clinical-section'>
                <h3><i class='fas fa-chart-line'></i> Clinical Assessment & Anthropometry</h3>
                <div class='clinical-stat-row'>
                    <div class='stat-box'>
                        <div class='label'>Calculated BMI</div>
                        <div class='value' style='color: {$bmiColor};'>{$bmiText}</div>
                    </div>
                    <div class='stat-box'>
                        <div class='label'>Waist-to-Hip Ratio</div>
                        <div class='value'>{$whrText}</div>
                    </div>
                    <div class='stat-box'>
                        <div class='label'>Target Caloric Baseline</div>
                        <div class='value' style='color: #059669;'>{$tdee} kcal/day</div>
                    </div>
                </div>
                <p style='margin-top: 10px; font-size: 0.85rem; color: #475569;'>
                    <b>Clinical Interpretation:</b> {$bmiRisk} Client goals focused on <em>{$goals}</em> with restrictions noted: <em>{$restrictions}</em>.
                </p>
            </div>

            <div class='clinical-section'>
                <h3><i class='fas fa-clipboard-list'></i> Critical Nutrient Targets</h3>
                <p style='font-size: 0.82rem; color: #64748b; margin-bottom: 10px;'>Recommended micronutrient targets prioritized to address biomarker profile:</p>
                <div class='nutrient-pill-container'>
                    {$nutrientPills}
                </div>
            </div>

            <div class='clinical-section'>
                <h3><i class='fas fa-utensils'></i> Suggested DOST-FCT Food Strategies</h3>
                <div style='display: grid; grid-template-columns: 1fr 1fr; gap: 12px;'>
                    <div style='background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 12px;'>
                        <div style='font-weight: 700; color: #166534; font-size: 0.85rem; margin-bottom: 6px;'><i class='fas fa-check-circle'></i> Prioritize (DOST-FCT)</div>
                        <ul style='margin: 0; padding-left: 18px; font-size: 0.8rem; color: #1e293b; line-height: 1.6;'>
                            <li><b>Vegetables:</b> Malunggay (leaves), Kangkong, Saluyot, Talbos ng Kamote</li>
                            <li><b>Lean Proteins:</b> Bangus (milkfish), Galunggong, Tokwa (tofu), Skinless Chicken Breast</li>
                            <li><b>Fruits:</b> Papaya, Banana (Latundan/Saba), Calamansi, Guava</li>
                            <li><b>Complex CHO:</b> Brown Rice, Mais grits, Oats, Root crops</li>
                        </ul>
                    </div>
                    <div style='background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 12px;'>
                        <div style='font-weight: 700; color: #991b1b; font-size: 0.85rem; margin-bottom: 6px;'><i class='fas fa-ban'></i> Restrict / Moderate</div>
                        <ul style='margin: 0; padding-left: 18px; font-size: 0.8rem; color: #1e293b; line-height: 1.6;'>
                            <li>Ultra-processed canned goods with excessive sodium nitrites</li>
                            <li>Deep-fried saturated oils and trans-fat street foods</li>
                            <li>Refined sugar sweetened beverages and condensed dairy drinks</li>
                            <li>High-sodium condiments (patis, bagoong) without measured dilution</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class='clinical-section'>
                <h3><i class='fas fa-heartbeat'></i> Lifestyle & Meal Timing Guidance</h3>
                <div style='font-size: 0.82rem; color: #334155; line-height: 1.6;'>
                    <p style='margin: 0 0 6px 0;'><b>• Breakfast (7:00 AM - 8:30 AM):</b> High fiber + lean protein to mitigate mid-morning glycemic spikes.</p>
                    <p style='margin: 0 0 6px 0;'><b>• Lunch (12:00 PM - 1:30 PM):</b> 50% plate vegetable volume, 25% complex whole grains, 25% protein exchange.</p>
                    <p style='margin: 0 0 6px 0;'><b>• Mid-Afternoon Fuel (3:30 PM - 4:00 PM):</b> Hydration (calamansi water) + low-GI fruit with handful of peanuts.</p>
                    <p style='margin: 0;'><b>• Dinner (6:30 PM - 7:30 PM):</b> Lighter digestive load at least 2.5 hours before sleep to support metabolic recovery.</p>
                </div>
            </div>
        </div>
        ";
    }

    echo json_encode([
        'success' => true,
        'recommendation' => $aiOutput
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
