<?php
// get_fct_foods.php
$session_path = 'c:\xampp\htdocs\nutrideq\api\sessions';
if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
session_save_path($session_path);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

require_once '../database.php';
require_once '../api/fct_helper.php';

// Allow staff/admin only
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['user_role'] !== 'staff' && $_SESSION['user_role'] !== 'admin')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $fctHelper = new FCTHelper();
    $allItems = $fctHelper->getAllItems();

    // Map DB categories cleanly to the Meal Planner form groups
    $groupedData = [
        'vegetable' => [],
        'fruit' => [],
        'milk' => [],
        'rice' => [],
        'meat' => [],
        'fat' => []
    ];

    foreach ($allItems as $item) {
        $cat = strtolower(trim($item['category']));
        
        $targetGroup = null;
        if (strpos($cat, 'vegetable') !== false) {
            $targetGroup = 'vegetable';
        } elseif (strpos($cat, 'fruit') !== false) {
            $targetGroup = 'fruit';
        } elseif (strpos($cat, 'milk') !== false) {
            $targetGroup = 'milk';
        } elseif (strpos($cat, 'rice') !== false || strpos($cat, 'bread') !== false || strpos($cat, 'cereal') !== false) {
            $targetGroup = 'rice';
        } elseif (strpos($cat, 'meat') !== false || strpos($cat, 'poultry') !== false || strpos($cat, 'fish') !== false || strpos($cat, 'egg') !== false) {
            $targetGroup = 'meat';
        } elseif (strpos($cat, 'fat') !== false || strpos($cat, 'oil') !== false) {
            $targetGroup = 'fat';
        }

        if ($targetGroup) {
            $groupedData[$targetGroup][] = [
                'id' => $item['id'],
                'food_id' => $item['food_id'],
                'food_name' => $item['food_name'],
                'exchange' => 1, // Defaulting to 1 exchange for mapping simplicity
                'serving_size' => '100', // Defaulting to 100g if missing, but usually the DB has it implicitly or the user types it
                'energy' => $item['calories'],
                'protein' => $item['protein'],
                'fat' => $item['fat'],
                'carbohydrates' => $item['carbs']
            ];
        }
    }

    echo json_encode(['success' => true, 'data' => $groupedData]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
