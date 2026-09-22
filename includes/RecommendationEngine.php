<?php
// includes/RecommendationEngine.php
//
// Auditable, rule-based recommendation engine.
//
// Design goals (in order):
//  1. Deterministic — the same input always produces the same output.
//  2. Auditable — every emitted recommendation carries the rule ID that fired,
//     the standard value it was evaluated against, the observed intake, and the
//     exact threshold arithmetic. A reviewer can recompute any result by hand.
//  3. Transparent — rules are declared data (RULES), not buried logic.
//
// Rules are evaluated against the PDRI 2015 (FNRI-DOST) per-demographic
// standards resolved via NutrientStandards. Severity tiers:
//   info    — observation only, no action implied
//   low     — minor gap (>=80% of target)
//   moderate — clear gap (<80% of target)
//   high    — severe gap (<40% of target) or UL exceeded
// Output is a data payload only; UI wording stays in the presentation layer.

require_once __DIR__ . '/NutrientStandards.php';

class RecommendationEngine
{
    /**
     * Rule registry. Each rule declares its nutrient and the bands it maps to
     * recommendations. Bands are evaluated top-down (first match wins):
     *   max_pct — fires when percent-of-reference is BELOW the value (deficit)
     *   min_pct — fires when percent-of-reference is AT/ABOVE the value (excess)
     */
    public const RULES = [
        [
            'id' => 'R-DEF-IRON',
            'nutrient' => 'Iron',
            'bands' => [
                ['max_pct' => 40,  'severity' => 'high',     'rec' => 'CONSULT-IRON'],
                ['max_pct' => 80,  'severity' => 'moderate', 'rec' => 'FOOD-IRON'],
            ],
        ],
        [
            'id' => 'R-DEF-CALCIUM',
            'nutrient' => 'Calcium',
            'bands' => [
                ['max_pct' => 40,  'severity' => 'high',     'rec' => 'CONSULT-CALCIUM'],
                ['max_pct' => 80,  'severity' => 'moderate', 'rec' => 'FOOD-CALCIUM'],
            ],
        ],
        [
            'id' => 'R-DEF-VITC',
            'nutrient' => 'Vitamin C',
            'bands' => [
                ['max_pct' => 40,  'severity' => 'high',     'rec' => 'CONSULT-VITC'],
                ['max_pct' => 80,  'severity' => 'moderate', 'rec' => 'FOOD-VITC'],
            ],
        ],
        [
            'id' => 'R-DEF-POTASSIUM',
            'nutrient' => 'Potassium, K',
            'bands' => [
                ['max_pct' => 40,  'severity' => 'high',     'rec' => 'CONSULT-POTASSIUM'],
                ['max_pct' => 80,  'severity' => 'moderate', 'rec' => 'FOOD-POTASSIUM'],
            ],
        ],
        [
            'id' => 'R-DEF-FIBER',
            'nutrient' => 'Fiber, total dietary',
            'bands' => [
                ['max_pct' => 40,  'severity' => 'high',     'rec' => 'FOOD-FIBER'],
                ['max_pct' => 80,  'severity' => 'moderate', 'rec' => 'FOOD-FIBER'],
            ],
        ],
        [
            'id' => 'R-EXC-SODIUM',
            'nutrient' => 'Sodium',
            'bands' => [
                ['min_pct' => 200, 'severity' => 'high',     'rec' => 'LIMIT-SODIUM'],
                ['min_pct' => 120, 'severity' => 'moderate', 'rec' => 'LIMIT-SODIUM'],
            ],
        ],
        [
            'id' => 'R-EXC-IRON-UL',
            'nutrient' => 'Iron',
            'requires_ul' => true,
            'bands' => [
                ['min_pct' => 100, 'severity' => 'high',     'rec' => 'CONSULT-UL-EXCEEDED'],
            ],
        ],
        [
            'id' => 'R-EXC-CALCIUM-UL',
            'nutrient' => 'Calcium',
            'requires_ul' => true,
            'bands' => [
                ['min_pct' => 100, 'severity' => 'high',     'rec' => 'CONSULT-UL-EXCEEDED'],
            ],
        ],
        [
            'id' => 'R-EXC-VITC-UL',
            'nutrient' => 'Vitamin C',
            'requires_ul' => true,
            'bands' => [
                ['min_pct' => 100, 'severity' => 'high',     'rec' => 'CONSULT-UL-EXCEEDED'],
            ],
        ],
    ];

    /**
     * Recommendation catalog: stable keys -> presentation text. Text is kept out
     * of the rules so wording can change without touching rule logic.
     */
    public const RECOMMENDATIONS = [
        'CONSULT-IRON' => [
            'text' => 'Iron intake is well below the PDRI recommendation for your age and sex. Please consult your dietitian — iron needs in this range usually require targeted food or supplement guidance.',
            'action' => 'consult_dietitian',
        ],
        'FOOD-IRON' => [
            'text' => 'Add iron-rich foods: lean beef, liver, spinach, mongo (mung beans), and iron-fortified rice.',
            'action' => 'food_based',
        ],
        'CONSULT-CALCIUM' => [
            'text' => 'Calcium intake is well below the PDRI recommendation. Please consult your dietitian for a calcium plan.',
            'action' => 'consult_dietitian',
        ],
        'FOOD-CALCIUM' => [
            'text' => 'Add calcium-rich foods: milk, dilis (anchovies), sardines with bones, cheese, and fortified soy milk.',
            'action' => 'food_based',
        ],
        'CONSULT-VITC' => [
            'text' => 'Vitamin C intake is well below the PDRI recommendation. Please consult your dietitian.',
            'action' => 'consult_dietitian',
        ],
        'FOOD-VITC' => [
            'text' => 'Add vitamin C-rich foods: calamansi, oranges, papaya, guava, bell peppers, and kamote tops.',
            'action' => 'food_based',
        ],
        'CONSULT-POTASSIUM' => [
            'text' => 'Potassium intake is well below the PDRI adequate intake. Please consult your dietitian.',
            'action' => 'consult_dietitian',
        ],
        'FOOD-POTASSIUM' => [
            'text' => 'Add potassium-rich foods: bananas, kamote (sweet potato), avocado, coconut water, and squash.',
            'action' => 'food_based',
        ],
        'FOOD-FIBER' => [
            'text' => 'Increase dietary fiber: whole grains, oats, vegetables, fruits with skin, and legumes. Add gradually with plenty of water.',
            'action' => 'food_based',
        ],
        'LIMIT-SODIUM' => [
            'text' => 'Sodium intake exceeds the WHO/PDRI guidance. Reduce salty snacks, processed meats, instant noodles, and season with calamansi and herbs instead of salt/fish sauce.',
            'action' => 'limit_intake',
        ],
        'CONSULT-UL-EXCEEDED' => [
            'text' => 'Average intake exceeds the PDRI Tolerable Upper Intake Level for this nutrient. Please consult your dietitian before continuing any supplements or fortified foods.',
            'action' => 'consult_dietitian',
        ],
    ];

    /** Intake history window (days) — matches NutrientAnalyzer. */
    private const WINDOW_DAYS = 14;

    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Evaluate all rules for a user over the last N days.
     * Returns a fully auditable payload; never throws (returns error payload).
     */
    public function evaluateForUser($userId)
    {
        $userId = (int)$userId;
        try {
            $age = NutrientStandards::resolveUserAge($this->pdo, $userId);
            $sex = NutrientStandards::resolveUserSex($this->pdo, $userId);

            // 14-day average intake per tracked nutrient.
            $intakes = $this->getAverages($userId, NutrientStandards::TRACKED);

            $fired = [];
            $audits = [];

            foreach (self::RULES as $rule) {
                $nutrient = trim($rule['nutrient']);
                $avg = $intakes[$nutrient] ?? null;
                if ($avg === null) continue;

                $std = NutrientStandards::get($nutrient, $age, $sex);
                $target = (float)$std['target'];
                $ul = $std['ul_value'];

                if (!empty($rule['requires_ul'])) {
                    // UL rules only fire when a UL is established.
                    if ($ul === null) continue;
                    $pct = $ul > 0 ? ($avg / $ul) * 100 : 0.0;
                    if ($avg <= $ul) continue;
                } else {
                    if ($target <= 0) continue;
                    $pct = ($avg / $target) * 100;
                }

                foreach ($rule['bands'] as $band) {
                    $matched = false;
                    if (isset($band['min_pct'])) {
                        $matched = ($pct >= $band['min_pct']);
                    } else {
                        $matched = ($pct < $band['max_pct']);
                    }
                    if ($matched) {
                        $fired[] = $this->buildRecommendation($rule, $band, $nutrient, $avg, $target, $ul, $std, $pct, $age, $sex);
                        break; // first (most severe) matching band only
                    }
                }
            }

            $summary = $this->summarize($fired, $age, $sex);

            return [
                'success' => true,
                'user_id' => $userId,
                'window_days' => self::WINDOW_DAYS,
                'demographics' => [
                    'age' => $age,
                    'sex' => $sex,
                    'bracket' => NutrientStandards::bracketLabel($age, $sex),
                ],
                'standards' => [
                    'framework' => 'PDRI 2015 (FNRI-DOST)',
                    'resolved_from' => $this->standardsSources($age, $sex),
                ],
                'intakes_avg_daily' => $intakes,
                'recommendations' => $fired,
                'summary' => $summary,
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Evaluation error: ' . $e->getMessage()];
        }
    }

    // ── internals ────────────────────────────────────────────────────────────

    /** Provenance of every tracked nutrient's standard (for the audit payload). */
    private function standardsSources($age, $sex)
    {
        $sources = [];
        foreach (NutrientStandards::TRACKED as $n) {
            $sources[$n] = NutrientStandards::get($n, $age, $sex)['source'];
        }
        return $sources;
    }

    /** Average daily intake per nutrient over the window (mirrors NutrientAnalyzer SQL). */
    private function getAverages($userId, array $nutrients)
    {
        $ph = implode(',', array_fill(0, count($nutrients), '?'));
        $sql = "
            SELECT fn.nutrient_name, SUM(fn.value * (fl.serving_size / 100)) as total
            FROM food_logs fl
            JOIN fct_food_items ffi ON fl.food_id = ffi.food_id
            JOIN fct_nutrients fn ON ffi.id = fn.food_item_id
            WHERE fl.user_id = ?
              AND fn.nutrient_name IN ($ph)
              AND fl.log_date >= DATE_SUB(CURDATE(), INTERVAL " . self::WINDOW_DAYS . " DAY)
            GROUP BY fn.nutrient_name
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute(array_merge([$userId], $nutrients));
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($nutrients as $n) $out[$n] = 0.0;
        foreach ($rows as $r) {
            if (array_key_exists($r['nutrient_name'], $out)) {
                $out[$r['nutrient_name']] = round((float)$r['total'] / self::WINDOW_DAYS, 2);
            }
        }
        return $out;
    }

    /** One fired recommendation with its full audit trail. */
    private function buildRecommendation($rule, $band, $nutrient, $avg, $target, $ul, $std, $pct, $age, $sex)
    {
        $recKey = $band['rec'];
        $catalog = self::RECOMMENDATIONS[$recKey] ?? ['text' => '', 'action' => 'none'];
        $refValue = !empty($rule['requires_ul']) ? $ul : $target;
        $refLabel = !empty($rule['requires_ul']) ? 'UL' : ($std['source_type'] === 'AI' ? 'AI' : 'RNI');

        return [
            'rule_id' => $rule['id'],
            'nutrient' => $nutrient,
            'severity' => $band['severity'],
            'recommendation_key' => $recKey,
            'text' => $catalog['text'],
            'action' => $catalog['action'],
            'audit' => [
                'observed_avg_daily' => $avg,
                'reference_type' => $refLabel,
                'reference_value' => $refValue,
                'unit' => $std['unit'],
                'percent_of_reference' => round($pct, 1),
                'band_field' => isset($band['min_pct']) ? 'min_pct' : 'max_pct',
                'band_threshold' => isset($band['min_pct'])
                    ? ($band['min_pct'] >= 900000 ? null : $band['min_pct'])
                    : (is_infinite($band['max_pct']) ? null : $band['max_pct']),
                'upper_limit' => $ul,
                'standard_source' => $std['source'],
                'source_note' => $std['source_note'],
                'demographics' => [
                    'age' => $age,
                    'sex' => $sex,
                    'bracket' => NutrientStandards::bracketLabel($age, $sex),
                ],
                'expression' => sprintf(
                    '%.2f %s/%s vs %s %s = %.1f%% -> %s %s',
                    $avg, $std['unit'], self::WINDOW_DAYS,
                    $refValue, $std['unit'], $pct,
                    isset($band['min_pct']) ? '>=' : '<',
                    isset($band['min_pct'])
                        ? ($band['min_pct'] >= 900000 ? 'inf' : $band['min_pct'])
                        : (is_infinite($band['max_pct']) ? 'inf' : $band['max_pct'])
                ),
            ],
        ];
    }

    private function summarize($fired, $age, $sex)
    {
        $counts = ['high' => 0, 'moderate' => 0, 'low' => 0, 'info' => 0];
        $ulExceeded = [];
        foreach ($fired as $r) {
            if (isset($counts[$r['severity']])) $counts[$r['severity']]++;
            if ($r['recommendation_key'] === 'CONSULT-UL-EXCEEDED') $ulExceeded[] = $r['nutrient'];
        }
        $status = 'balanced';
        if ($counts['high'] > 0) $status = 'attention_needed';
        elseif ($counts['moderate'] > 0) $status = 'minor_gaps';

        return [
            'status' => $status,
            'severity_counts' => $counts,
            'rules_fired' => count($fired),
            'upper_limit_exceeded' => $ulExceeded,
        ];
    }
}
