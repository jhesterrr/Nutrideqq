<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

header('Content-Type: application/json');

require_once '../database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_role = strtolower($_SESSION['role'] ?? $_SESSION['user_role'] ?? '');
$allowed_roles = ['regular', 'user'];
if (!in_array($user_role, $allowed_roles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden: Only regular users can generate self-recommendations. Detected role: ' . $user_role]);
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user session']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    $stmt = $pdo->prepare("
        SELECT id, name, email, weight, height, age, gender,
               health_conditions, dietary_restrictions, goals,
               goal_calories, goal_protein, goal_carbs, goal_fats,
               waist_circumference, hip_circumference
        FROM clients WHERE user_id = ? LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client && !empty($_SESSION['user_email'])) {
        $stmt = $pdo->prepare("
            SELECT id, name, email, weight, height, age, gender,
                   health_conditions, dietary_restrictions, goals,
                   goal_calories, goal_protein, goal_carbs, goal_fats,
                   waist_circumference, hip_circumference
            FROM clients WHERE email = ? LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_email']]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$client) {
        $client = [
            'id' => null,
            'name' => $_SESSION['user_name'] ?? 'NutriDeq User',
            'email' => $_SESSION['user_email'] ?? '',
            'weight' => null,
            'height' => null,
            'age' => null,
            'gender' => 'N/A',
            'health_conditions' => null,
            'dietary_restrictions' => null,
            'goals' => 'General Health',
            'waist_circumference' => null,
            'hip_circumference' => null
        ];
    }

    $name = htmlspecialchars($client['name'] ?? 'User');
    $gender = htmlspecialchars($client['gender'] ?? 'N/A');
    $age = htmlspecialchars($client['age'] ?? 'N/A');
    $weight = htmlspecialchars($client['weight'] ?? 'N/A');
    $height = htmlspecialchars($client['height'] ?? 'N/A');
    $waist = htmlspecialchars($client['waist_circumference'] ?? 'N/A');
    $hip = htmlspecialchars($client['hip_circumference'] ?? 'N/A');
    $conditions = htmlspecialchars($client['health_conditions'] ?? 'None reported');
    $restrictions = htmlspecialchars($client['dietary_restrictions'] ?? 'None');
    $goals = htmlspecialchars($client['goals'] ?? 'General health and balanced nutrition');

    $stmt = $pdo->prepare("
        SELECT AVG(daily_cal) as avg_cal FROM (
            SELECT SUM(calories) as daily_cal
            FROM food_logs WHERE user_id = ? GROUP BY log_date
            ORDER BY log_date DESC LIMIT 3
        ) as sub
    ");
    $stmt->execute([$user_id]);
    $avg_cal = round((float)($stmt->fetchColumn() ?? 0));

    $stmt = $pdo->prepare("SELECT nutrient_name, mapped_nutrient, MAX(created_at) as max_created
        FROM ai_extracted_symptoms WHERE user_id = ?
        GROUP BY nutrient_name, mapped_nutrient
        ORDER BY max_created DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $symptoms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $symptom_list = '';
    if ($symptoms && count($symptoms) > 0) {
        $symptom_list = implode(", ", array_map(function ($s) {
            return htmlspecialchars($s['nutrient_name'] ?? $s['mapped_nutrient'] ?? 'marker');
        }, $symptoms));
    } else {
        $symptom_list = 'No recent symptom markers detected';
    }

    $apiKey = getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? '');
    $aiOutput = '';

    if (!$apiKey) {
        $aiOutput = "
        <div class='clinical-section' style='padding:20px;background:#f8fafc;border-radius:14px;margin-bottom:16px;'>
            <h3 style='margin:0 0 12px 0;color:#1e293b;font-family:Outfit;'><i class='fas fa-chart-line' style='color:#10b981;margin-right:8px;'></i> Clinical Assessment</h3>
            <p style='margin:0;line-height:1.6;color:#475569;font-size:0.92rem;'>
                <b>AI engine currently offline.</b> Your nutritionist has been notified and will provide a personalized recommendation shortly.
                In the meantime, continue maintaining balanced meals across all six food groups and monitor your daily water intake.
            </p>
        </div>
        <div class='clinical-section' style='padding:20px;background:#ecfdf5;border-radius:14px;margin-bottom:16px;'>
            <h3 style='margin:0 0 12px 0;color:#1e293b;font-family:Outfit;'><i class='fas fa-clipboard-list' style='color:#059669;margin-right:8px;'></i> Critical Nutrient Targets</h3>
            <p style='margin:0;line-height:1.6;color:#475569;font-size:0.92rem;'>
                <span class='nutrient-pill' style='display:inline-block;padding:4px 12px;border-radius:20px;background:#dbeafe;color:#1e40af;font-weight:700;margin:0 6px 6px 0;'>Protein</span>
                <span class='nutrient-pill' style='display:inline-block;padding:4px 12px;border-radius:20px;background:#d1fae5;color:#065f46;font-weight:700;margin:0 6px 6px 0;'>Fiber</span>
                <span class='nutrient-pill' style='display:inline-block;padding:4px 12px;border-radius:20px;background:#fef3c7;color:#92400e;font-weight:700;margin:0 6px 6px 0;'>Iron</span>
                <span class='nutrient-pill' style='display:inline-block;padding:4px 12px;border-radius:20px;background:#fee2e2;color:#991b1b;font-weight:700;margin:0 6px 6px 0;'>Calcium</span>
                <span class='nutrient-pill' style='display:inline-block;padding:4px 12px;border-radius:20px;background:#ede9fe;color:#5b21b6;font-weight:700;margin:0 6px 6px 0;'>Vitamin C</span>
            </p>
        </div>
        <div class='clinical-section' style='padding:20px;background:#eff6ff;border-radius:14px;margin-bottom:16px;'>
            <h3 style='margin:0 0 12px 0;color:#1e293b;font-family:Outfit;'><i class='fas fa-utensils' style='color:#3b82f6;margin-right:8px;'></i> Suggested FCT Food Strategies</h3>
            <p style='margin:0;line-height:1.6;color:#475569;font-size:0.92rem;'>
                Focus on dark leafy greens (Iron), low-fat dairy or fortified plant milk (Calcium), citrus fruits (Vitamin C),
                whole grains and legumes (Fiber), and bananas/sweet potatoes (Potassium). Limit ultra-processed foods (Sodium moderation).
            </p>
        </div>
        <div class='clinical-section' style='padding:20px;background:#fdf4ff;border-radius:14px;'>
            <h3 style='margin:0 0 12px 0;color:#1e293b;font-family:Outfit;'><i class='fas fa-heartbeat' style='color:#a855f7;margin-right:8px;'></i> Lifestyle &amp; Meal Timing Guidance</h3>
            <p style='margin:0;line-height:1.6;color:#475569;font-size:0.92rem;'>
                Distribute protein evenly across Breakfast, Lunch, and Dinner (aim for 20-30g per meal).
                Hydrate with 250ml water upon waking and before each meal. Avoid heavy snacks within 2 hours of bedtime.
                Walk 10-15 minutes after the largest meal of the day to support metabolic regulation.
            </p>
        </div>";
    } else {
        $apiUrl = 'https://api.groq.com/openai/v1/chat/completions';
        $model = 'openai/gpt-oss-20b';

        $prompt = "You are the NutriDeq Clinical Nutrition AI. Generate a professional, highly detailed clinical nutritional recommendation report for a user reviewing their own self-generated wellness recommendation.

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
- Recent Average Daily Intake (last 3 days): $avg_cal kcal/day
- Recent Nutritional Symptom Markers: $symptom_list

Format the response in clean, semantic, modern HTML. Do not output Markdown wrappers or conversational greetings/closing. Output ONLY the raw HTML string containing these sections wrapped in a cohesive layout:
1. <div class='clinical-section'><h3><i class='fas fa-chart-line'></i> Clinical Assessment</h3>... Calculate BMI and waist-to-hip ratio if height/weight/waist/hip are available, and give clinical interpretation.
2. <div class='clinical-section'><h3><i class='fas fa-clipboard-list'></i> Critical Nutrient Targets</h3>... State exactly what nutrients the user needs based on conditions, goals, restrictions. Highlight them in beautiful pill badges: <span class='nutrient-pill'>Nutrient</span>.
3. <div class='clinical-section'><h3><i class='fas fa-utensils'></i> Suggested FCT Food Strategies</h3>... Specific DOST-FCT food categories highly recommended, and foods to avoid.
4. <div class='clinical-section'><h3><i class='fas fa-heartbeat'></i> Lifestyle & Meal Timing Guidance</h3>... Actionable dietician tips for Breakfast, Lunch, Snacks, Dinner.

Design looks like a premium clinical glassmorphic card interface. Use clean professional palette. Keep HTML clean and self-contained. Do NOT include NON-THERAPEUTIC NOTICE inside the HTML output â€” it will be rendered by the UI wrapper.";

        $data = [
            "model" => $model,
            "messages" => [
                ["role" => "system", "content" => "You are a clinical NLP engine. Always respond with raw semantic HTML only. No Markdown wrapper. No explanation outside HTML."],
                ["role" => "user", "content" => $prompt]
            ],
            "temperature" => 0.25,
            "max_tokens" => 2500
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError || $httpCode !== 200 || !$response) {
            $fallbackReason = $curlError ? ("Network issue: " . $curlError) : ("API HTTP $httpCode");
            $aiOutput = "
            <div class='clinical-section' style='padding:20px;background:#fef2f2;border-radius:14px;margin-bottom:16px;'>
                <h3 style='margin:0 0 12px 0;color:#7f1d1d;font-family:Outfit;'><i class='fas fa-triangle-exclamation' style='color:#ef4444;margin-right:8px;'></i> AI Engine Temporarily Unavailable</h3>
                <p style='margin:0;line-height:1.6;color:#991b1b;font-size:0.92rem;'>
                    $fallbackReason. Your recommendation has been queued for manual review by your assigned dietician.
                    Below are evidence-based guidelines while you wait.
                </p>
            </div>
            <div class='clinical-section' style='padding:20px;background:#ecfdf5;border-radius:14px;margin-bottom:16px;'>
                <h3 style='margin:0 0 12px 0;color:#1e293b;font-family:Outfit;'><i class='fas fa-clipboard-list' style='color:#059669;margin-right:8px;'></i> Evidence-Based Nutrient Targets</h3>
                <p style='margin:0;line-height:1.6;color:#475569;font-size:0.92rem;'>
                    <span class='nutrient-pill' style='display:inline-block;padding:4px 12px;border-radius:20px;background:#dbeafe;color:#1e40af;font-weight:700;margin:0 6px 6px 0;'>Protein 1.2-1.6g/kg</span>
                    <span class='nutrient-pill' style='display:inline-block;padding:4px 12px;border-radius:20px;background:#d1fae5;color:#065f46;font-weight:700;margin:0 6px 6px 0;'>Fiber 20-30g/d</span>
                    <span class='nutrient-pill' style='display:inline-block;padding:4px 12px;border-radius:20px;background:#fef3c7;color:#92400e;font-weight:700;margin:0 6px 6px 0;'>Iron 7+ mg/d</span>
                    <span class='nutrient-pill' style='display:inline-block;padding:4px 12px;border-radius:20px;background:#fee2e2;color:#991b1b;font-weight:700;margin:0 6px 6px 0;'>Calcium 500+ mg/d</span>
                    <span class='nutrient-pill' style='display:inline-block;padding:4px 12px;border-radius:20px;background:#ede9fe;color:#5b21b6;font-weight:700;margin:0 6px 6px 0;'>Vitamin C 30+ mg/d</span>
                    <span class='nutrient-pill' style='display:inline-block;padding:4px 12px;border-radius:20px;background:#ccfbf1;color:#134e4a;font-weight:700;margin:0 6px 6px 0;'>Potassium 1500+ mg/d</span>
                </p>
            </div>
            <div class='clinical-section' style='padding:20px;background:#eff6ff;border-radius:14px;margin-bottom:16px;'>
                <h3 style='margin:0 0 12px 0;color:#1e293b;font-family:Outfit;'><i class='fas fa-utensils' style='color:#3b82f6;margin-right:8px;'></i> Suggested FCT Food Strategies</h3>
                <p style='margin:0;line-height:1.6;color:#475569;font-size:0.92rem;'>
                    <b>Vegetables:</b> Malunggay, kangkong, spinach (Iron + Fiber). <b>Fruits:</b> Pomelo, mango, calamansi (Vitamin C + Potassium).
                    <b>Grains:</b> Brown rice, whole wheat pandesal (Fiber + B vitamins). <b>Lean Proteins:</b> Tilapia, chicken breast, mung beans, tofu.
                    <b>Fats:</b> Avocado, nuts in moderation. Limit patis, bagoong, and processed canned goods to moderate sodium.
                </p>
            </div>
            <div class='clinical-section' style='padding:20px;background:#fdf4ff;border-radius:14px;'>
                <h3 style='margin:0 0 12px 0;color:#1e293b;font-family:Outfit;'><i class='fas fa-heartbeat' style='color:#a855f7;margin-right:8px;'></i> Lifestyle &amp; Meal Timing Guidance</h3>
                <p style='margin:0;line-height:1.6;color:#475569;font-size:0.92rem;'>
                    <b>Breakfast (6-8 AM):</b> High-protein + complex carb. Example: Oatmeal with 1 egg + banana.
                    <b>AM Snack (10 AM):</b> 1 fruit + 1 glass water. <b>Lunch (12-1 PM):</b> Largest meal: 1/2 plate veggies, 1/4 protein, 1/4 rice.
                    <b>PM Snack (3-4 PM):</b> Yogurt or nuts. <b>Dinner (6-7 PM):</b> Light protein + veggies, avoid heavy carbs after 8 PM.
                    Post-lunch 10-min walk supports glucose regulation. Sleep 7-9 hours.
                </p>
            </div>";
        } else {
            $responseData = json_decode($response, true);
            $aiOutput = $responseData['choices'][0]['message']['content'] ?? '';
            $aiOutput = preg_replace('/^```html\s*/i', '', $aiOutput);
            $aiOutput = preg_replace('/```$/', '', $aiOutput);
            $aiOutput = trim($aiOutput);
        }
    }

    try {
        $data_points = json_encode([
            'bmi' => null,
            'avg_cal' => $avg_cal,
            'symptoms' => $symptoms,
            'generated_by' => 'user_self_service',
            'generated_at' => date('c')
        ]);

        $staff_id = null;
        if (!empty($client['id'])) {
            $sstmt = $pdo->prepare("SELECT staff_id FROM clients WHERE id = ? LIMIT 1");
            $sstmt->execute([$client['id']]);
            $sid = $sstmt->fetchColumn();
            if ($sid) $staff_id = (int)$sid;
        }

        try {
            $checkTable = $pdo->query("SELECT 1 FROM ai_recommendations LIMIT 1");
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS ai_recommendations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    dietitian_id INT NULL,
                    recommendation_text LONGTEXT,
                    data_points_used LONGTEXT,
                    trigger_source VARCHAR(50) DEFAULT 'symptom_match',
                    status VARCHAR(50) DEFAULT 'pending_review',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX(user_id), INDEX(status), INDEX(trigger_source)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            } else {
                throw $e;
            }
        }

        $istmt = $pdo->prepare("
            INSERT INTO ai_recommendations (user_id, dietitian_id, recommendation_text, data_points_used, trigger_source, status)
            VALUES (?, ?, ?, ?, 'user_self', 'pending_staff_review')
        ");
        $istmt->execute([
            $user_id,
            $staff_id,
            $aiOutput,
            $data_points
        ]);
        $recommendation_id = (int)$pdo->lastInsertId();

        if (class_exists('AuditLogger')) {
            try {
                AuditLogger::init($pdo);
                AuditLogger::log('ai_recommendation_user_self', 'ai_recommendations', (string)$recommendation_id, $user_id, 'regular');
            } catch (Exception $e) {
            }
        }
    } catch (Exception $e) {
        $recommendation_id = 0;
    }

    echo json_encode([
        'success' => true,
        'recommendation' => $aiOutput,
        'recommendation_id' => $recommendation_id,
        'profile_used' => [
            'name' => $name,
            'avg_cal_3d' => $avg_cal
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
