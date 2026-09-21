<?php
// api/nutrient_analyzer.php
// Nutrient debt thresholds resolved per-demographic from the FNRI PDRI 2015
// standards (nutrient_standards table; static official snapshot as fallback).
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/NutrientStandards.php';

class NutrientAnalyzer {
    private $pdo;

    public function __construct() {
        $database = new Database();
        $this->pdo = $database->getConnection();
    }

    /**
     * Checks if a user has a "Micronutrient Debt" for a specific nutrient
     * based on their last 14 days of food logs, compared against the
     * PDRI 2015 reference value for the user's age/sex. Also flags when the
     * 14-day average exceeds the PDRI Tolerable Upper Intake Level (UL).
     */
    public function getNutrientDebtStatus($userId, $nutrientName) {
        if (!in_array($nutrientName, NutrientStandards::TRACKED, true)) return null;

        try {
            $age = NutrientStandards::resolveUserAge($this->pdo, $userId);
            $sex = NutrientStandards::resolveUserSex($this->pdo, $userId);
            $std = NutrientStandards::get($nutrientName, $age, $sex);
            $threshold = (float)$std['target'];
            $ul = $std['ul_value'];

            // Get last 14 days intake
            // We join food_logs -> fct_food_items -> fct_nutrients
            $sql = "
                SELECT SUM(fn.value * (fl.serving_size / 100)) as total_intake
                FROM food_logs fl
                JOIN fct_food_items ffi ON fl.food_id = ffi.food_id
                JOIN fct_nutrients fn ON ffi.id = fn.food_item_id
                WHERE fl.user_id = ? 
                AND fn.nutrient_name = ?
                AND fl.log_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId, $nutrientName]);
            $total = (float)$stmt->fetchColumn();
            
            $dailyAvg = $total / 14;
            $isDebt = ($threshold > 0 && $dailyAvg < $threshold);

            return [
                'nutrient' => $nutrientName,
                'avg_daily' => round($dailyAvg, 2),
                // Original key kept for backward compatibility:
                'threshold' => $threshold,
                'is_debt' => $isDebt,
                // Standards detail (new, additive):
                'unit' => $std['unit'],
                'upper_limit' => $ul,
                'exceeds_upper_limit' => ($ul !== null && $dailyAvg > $ul),
                'source_type' => $std['source_type'],
                'source_note' => $std['source_note'],
                'standard_source' => $std['source'],
                'demographics' => [
                    'age' => $age,
                    'sex' => $sex,
                    'bracket' => NutrientStandards::bracketLabel($age, $sex),
                ],
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Analyzes all recently extracted symptoms for a user and returns clinical insights.
     */
    public function analyzeRecentSymptoms($userId) {
        try {
            // Get symptoms from last 7 days
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT symptom_keyword, mapped_nutrient 
                FROM ai_extracted_symptoms 
                WHERE user_id = ? AND mapped_nutrient IS NOT NULL
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute([$userId]);
            $symptoms = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $insights = [];
            foreach ($symptoms as $s) {
                $status = $this->getNutrientDebtStatus($userId, $s['mapped_nutrient']);
                if ($status && $status['is_debt']) {
                    $insights[] = [
                        'symptom' => $s['symptom_keyword'],
                        'nutrient' => $s['mapped_nutrient'],
                        'debt_level' => 'Significant',
                        'data_point' => "Avg {$status['avg_daily']} units vs needed {$status['threshold']}"
                    ];
                }
            }
            return $insights;
        } catch (Exception $e) {
            return [];
        }
    }

    public function getFoodSuggestion($nutrient) {
        $map = [
            'Iron' => 'Spinach, Red Meat, Lentils, Quinoa',
            'Potassium, K' => 'Bananas, Avocado, Sweet Potato, White Beans',
            'Calcium' => 'Yogurt, Sardines, Chia Seeds, Almonds',
            'Sodium' => 'Coconut Water, Celery, Sea Salt (moderation)',
            'Fiber, total dietary' => 'Oats, Raspberries, Chickpeas, Apples',
            'Vitamin C' => 'Oranges, Bell Peppers, Strawberries, Broccoli'
        ];
        return $map[$nutrient] ?? 'Consult your nutritionist';
    }
}
