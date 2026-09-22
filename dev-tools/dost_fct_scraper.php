<?php
require_once 'database.php';
$db = new Database();$conn = $db->getConnection();

$html = file_get_contents('dost_search.html');
if (!$html) die("Error loading dost_search.html\n");

// Mapping table for nutrient name to standard Nutrideq names
$name_mapping = [
    'Energy, calculated' => 'Energy',
    'Total Fat' => 'Fat',
    'Carbohydrate, total' => 'Carbohydrate',
    'Ash, total' => 'Ash',
    'Calcium, Ca' => 'Calcium',
    'Phosphorus, P' => 'Phosphorus',
    'Iron, Fe' => 'Iron',
    'Sodium, Na' => 'Sodium',
    'Retinol, Vitamin A' => 'Vitamin A',
    'Thiamin, Vitamin B1' => 'Thiamin (B1)',
    'Riboflavin, Vitamin B2' => 'Riboflavin (B2)',
    'Ascorbic Acid, Vitamin C' => 'Vitamin C',
    'Fatty acids, saturated, total' => 'SFA',
    'Fatty acids, monounsaturated, total' => 'MUFA',
    'Fatty acids, polyunsaturated, total' => 'PUFA',
    'Carbohydrate, available' => 'Carbohydrate, available', // Keep if needed
    'beta-Carotene' => 'beta-Carotene',
    'Retinol Activity Equivalent, RAE' => 'Retinol Activity Equivalent, RAE'
];

echo "Starting import for all 1,542 FCT items...\n";
$start_time = microtime(true);

// Extract all modals using string search for speed
$count = 0;
$offset = 0;
while (($pos = strpos($html, '<div class="modal fade"', $offset)) !== false) {
    // Find Food ID. The id of the modal is like id="A001_data"
    if (preg_match('/id="([^"]+)_data"/', substr($html, $pos, 100), $id_match)) {
        $food_code = $id_match[1]; // e.g. A001
        
        // Find end of this modal
        $end_pos = strpos($html, '<div class="modal fade"', $pos + 50);
        if ($end_pos === false) $end_pos = strlen($html);
        $modal_html = substr($html, $pos, $end_pos - $pos);
        
        // Get DB ID for this food
        $stmt = $conn->prepare("SELECT id FROM fct_food_items WHERE food_id = ?");
        $stmt->execute([$food_code]);
        $food_db_id = $stmt->fetchColumn();
        
        if ($food_db_id) {
            // Found food in DB, delete old nutrients
            $del = $conn->prepare("DELETE FROM fct_nutrients WHERE food_item_id = ?");
            $del->execute([$food_db_id]);
            
            // Extract nutrients from modal
            $pattern = '/<div class="col-md-9">(.*?)<\/div>\s*<div class="col-md-3"[^>]*><strong>\s*(.*?)\s*<\/strong><\/div>/is';
            preg_match_all($pattern, $modal_html, $matches, PREG_SET_ORDER);
            
            $ins = $conn->prepare("INSERT INTO fct_nutrients (food_item_id, nutrient_name, value, unit) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value), unit = VALUES(unit)");
            foreach($matches as $m) {
                $raw_name = strip_tags(trim($m[1]));
                
                // Some fields don't have numerical values, like trace
                $raw_val = trim($m[2]);
                if (!is_numeric($raw_val)) {
                    if (strtolower($raw_val) === 'trace') $raw_val = 0;
                    else $raw_val = 0; // fallback
                } else {
                    $raw_val = (float)$raw_val;
                }
                
                $unit = '';
                $name = $raw_name;
                if (preg_match('/^(.*?)\s*\((.*?)\)$/', $raw_name, $parts)) {
                    $name = trim($parts[1]);
                    $unit = trim($parts[2]);
                }
                
                // Map name if exists
                if (isset($name_mapping[$name])) {
                    $name = $name_mapping[$name];
                }
                
                $ins->execute([$food_db_id, $name, $raw_val, $unit]);
            }
            $count++;
            if ($count % 100 == 0) {
                echo "Processed $count items...\n";
            }
        }
    }
    $offset = $pos + 50;
}

$end_time = microtime(true);
echo "Import complete! Processed $count items in " . round($end_time - $start_time, 2) . " seconds.\n";
