<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';
require_once dirname(__DIR__) . '/includes/NutrientStandards.php';

header('Content-Type: application/json');

require_once '../database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_role = strtolower($_SESSION['role'] ?? $_SESSION['user_role'] ?? '');

try {
    $requested_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (int)($_SESSION['user_id'] ?? 0);

    // Admin: any client. The client themself: own data. Staff: only assigned clients.
    if (!user_can_access_client($requested_user_id)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => "Forbidden: You do not have access to this client's data."]);
        exit;
    }

    $database = new Database();
    $pdo = $database->getConnection();

    $date = $_GET['date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    $tracked_nutrients = [
        'Iron',
        'Potassium, K',
        'Calcium',
        'Sodium',
        'Fiber, total dietary',
        'Vitamin C'
    ];

    $macros_stmt = $pdo->prepare("
        SELECT SUM(calories) as calories, SUM(protein) as protein,
               SUM(carbs) as carbs, SUM(fat) as fat
        FROM food_logs
        WHERE user_id = ? AND log_date = ?
    ");
    $macros_stmt->execute([$requested_user_id, $date]);
    $macros = $macros_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $macros_totals = [
        'calories' => round((float)($macros['calories'] ?? 0), 1),
        'protein'  => round((float)($macros['protein'] ?? 0), 1),
        'carbs'    => round((float)($macros['carbs'] ?? 0), 1),
        'fat'      => round((float)($macros['fat'] ?? 0), 1)
    ];

    $placeholders = implode(',', array_fill(0, count($tracked_nutrients), '?'));
    $params = array_merge([$requested_user_id, $date], $tracked_nutrients);

    $sql = "
        SELECT fn.nutrient_name,
               SUM(fn.value * (fl.serving_size / 100)) as total
        FROM food_logs fl
        JOIN fct_food_items ffi ON fl.food_id = ffi.food_id
        JOIN fct_nutrients fn ON ffi.id = fn.food_item_id
        WHERE fl.user_id = ? AND fl.log_date = ?
          AND fn.nutrient_name IN ($placeholders)
        GROUP BY fn.nutrient_name
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $db_rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $micro_totals = [];
    foreach ($tracked_nutrients as $n) {
        $micro_totals[$n] = round((float)($db_rows[$n] ?? 0), 2);
    }

    $grouped = null;
    if (!empty($_GET['grouped'])) {
        $grouped = [];
        $meal_types = ['Breakfast', 'AM Snack', 'Lunch', 'PM Snack', 'Dinner', 'Snack'];
        foreach ($meal_types as $mt) {
            $gparams = array_merge([$requested_user_id, $date, $mt], $tracked_nutrients);
            $gsql = "
                SELECT fn.nutrient_name,
                       SUM(fn.value * (fl.serving_size / 100)) as total,
                       SUM(fl.calories) as calories,
                       SUM(fl.protein) as protein,
                       SUM(fl.carbs) as carbs,
                       SUM(fl.fat) as fat
                FROM food_logs fl
                JOIN fct_food_items ffi ON fl.food_id = ffi.food_id
                JOIN fct_nutrients fn ON ffi.id = fn.food_item_id
                WHERE fl.user_id = ? AND fl.log_date = ? AND fl.meal_type = ?
                  AND fn.nutrient_name IN ($placeholders)
                GROUP BY fn.nutrient_name
            ";
            $gstmt = $pdo->prepare($gsql);
            $gstmt->execute($gparams);
            $grows = $gstmt->fetchAll(PDO::FETCH_ASSOC);

            $meal_micros = [];
            foreach ($tracked_nutrients as $n) $meal_micros[$n] = 0.0;
            $meal_macros = ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0];
            foreach ($grows as $gr) {
                $nn = $gr['nutrient_name'];
                if (isset($meal_micros[$nn])) $meal_micros[$nn] = round((float)$gr['total'], 2);
                $meal_macros['calories'] = round((float)($gr['calories'] ?? $meal_macros['calories']), 1);
                $meal_macros['protein']  = round((float)($gr['protein']  ?? $meal_macros['protein']), 1);
                $meal_macros['carbs']    = round((float)($gr['carbs']    ?? $meal_macros['carbs']), 1);
                $meal_macros['fat']      = round((float)($gr['fat']      ?? $meal_macros['fat']), 1);
            }

            $mstm = $pdo->prepare("
                SELECT SUM(calories) as calories, SUM(protein) as protein,
                       SUM(carbs) as carbs, SUM(fat) as fat
                FROM food_logs WHERE user_id = ? AND log_date = ? AND meal_type = ?
            ");
            $mstm->execute([$requested_user_id, $date, $mt]);
            $mrow = $mstm->fetch(PDO::FETCH_ASSOC);
            if ($mrow) {
                $meal_macros['calories'] = max($meal_macros['calories'], round((float)($mrow['calories'] ?? 0), 1));
                $meal_macros['protein']  = max($meal_macros['protein'],  round((float)($mrow['protein']  ?? 0), 1));
                $meal_macros['carbs']    = max($meal_macros['carbs'],    round((float)($mrow['carbs']    ?? 0), 1));
                $meal_macros['fat']      = max($meal_macros['fat'],      round((float)($mrow['fat']      ?? 0), 1));
            }

            $grouped[$mt] = [
                'micronutrients' => $meal_micros,
                'macros' => $meal_macros
            ];
        }
    }

    // Per-demographic reference values (PDRI 2015, FNRI-DOST) resolved from the
    // nutrient_standards table; falls back to a static snapshot of the same
    // official values when demographics are unknown or the table is unavailable.
    $stdAge = NutrientStandards::resolveUserAge($pdo, $requested_user_id);
    $stdSex = NutrientStandards::resolveUserSex($pdo, $requested_user_id);

    $micro_with_status = [];
    $sources = [];
    foreach ($micro_totals as $name => $val) {
        $std = NutrientStandards::get($name, $stdAge, $stdSex);
        $thresh = (float)$std['target'];
        $ul = $std['ul_value'];
        $pct = $thresh > 0 ? min(100, round(($val / $thresh) * 100, 1)) : 0;
        $micro_with_status[$name] = [
            // Original keys (kept for dashboard-store.js compatibility):
            'total' => $val,
            'threshold' => $thresh,
            'percent_of_daily' => $pct,
            'is_below_threshold' => ($thresh > 0 && $val < $thresh),
            // Standards detail (new, additive):
            'unit' => $std['unit'],
            'rni_value' => $std['rni_value'],
            'ai_value' => $std['ai_value'],
            'ai_value_upper' => $std['ai_value_upper'],
            'upper_limit' => $ul,
            'exceeds_upper_limit' => ($ul !== null && $val > $ul),
            'source_type' => $std['source_type'],
            'source_note' => $std['source_note'],
            'standard_source' => $std['source'],
        ];
        $sources[$name] = $std['source'];
    }

    $payload = [
        'success' => true,
        'user_id' => $requested_user_id,
        'date' => $date,
        'macros' => $macros_totals,
        'micronutrients' => $micro_with_status,
        'micronutrient_raw_totals' => $micro_totals,
        'standards' => [
            'framework' => 'PDRI 2015 (FNRI-DOST)',
            'demographics' => [
                'age' => $stdAge,
                'sex' => $stdSex,
                'bracket' => NutrientStandards::bracketLabel($stdAge, $stdSex),
            ],
            'resolved_from' => $sources,
        ],
    ];
    if ($grouped !== null) {
        $payload['by_meal'] = $grouped;
    }

    echo json_encode($payload);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
