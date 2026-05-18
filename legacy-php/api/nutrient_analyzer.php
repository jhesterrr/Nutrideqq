<?php
// api/nutrient_analyzer.php
require_once __DIR__ . '/../database.php';

class NutrientAnalyzer {
    private $pdo;
    private $thresholds = [
        'Iron' => 7.0,
        'Potassium, K' => 1500.0,
        'Calcium' => 500.0,
        'Sodium' => 500.0,
        'Fiber, total dietary' => 10.0,
        'Vitamin C' => 30.0
    ];

    public function __construct() {
        $database = new Database();
        $this->pdo = $database->getConnection();
    }

    /**
     * Checks if a user has a "Micronutrient Debt" for a specific nutrient
     * based on their last 14 days of food logs.
     */
    public function getNutrientDebtStatus($userId, $nutrientName) {
        if (!isset($this->thresholds[$nutrientName])) return null;

        try {
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
            $isDebt = ($dailyAvg < $this->thresholds[$nutrientName]);

            return [
                'nutrient' => $nutrientName,
                'avg_daily' => round($dailyAvg, 2),
                'threshold' => $this->thresholds[$nutrientName],
                'is_debt' => $isDebt
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
