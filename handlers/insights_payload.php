<?php
// handlers/insights_payload.php
// Shared builder for the live Wellness Insights payload used by
// get_messages.php (polling) and send_message_ajax.php (post-send).
// Enriches active symptoms with the knowledge-base entry so the client JS
// can re-render the card exactly like the server-side PHP does.

if (!function_exists('nd_build_insights_payload')) {
    function nd_build_insights_payload($pdo, int $client_user_id, int $limit = 5): ?array
    {
        $live = SymptomLifecycle::getActiveInsights($pdo, $client_user_id, $limit);

        $iconMap = [
            'iron' => '🩸', 'Iron' => '🩸',
            'b12' => '💉', 'B12' => '💉', 'B-12' => '💉',
            'potassium' => '🍌', 'Potassium' => '🍌',
            'magnesium' => '🔮', 'Magnesium' => '🔮',
            'calcium' => '🥛', 'Calcium' => '🥛',
            'vitamin d' => '☀️', 'Vitamin D' => '☀️',
            'zinc' => '🔩', 'Zinc' => '🔩',
            'vitamin c' => '🍊', 'Vitamin C' => '🍊',
        ];

        $items = [];
        foreach ($live['symptoms'] as $row) {
            $kb = nd_lookup_symptom($row['symptom_keyword']);
            $nutrients = [];
            foreach (explode(',', (string)$row['nutrient']) as $n) {
                $n = trim($n);
                if ($n === '') continue;
                $nutrients[] = ['icon' => $iconMap[$n] ?? '⚛️', 'name' => $n];
            }
            $items[] = [
                'keyword'      => $row['symptom_keyword'],
                'icon'         => $kb['icon'],
                'category'     => $kb['category'],
                'short_desc'   => $kb['short_desc'],
                'full_desc'    => $kb['full_desc'],
                'recommended'  => array_values($kb['recommended']),
                'when_to_ask'  => $kb['when_to_ask'],
                'nutrients'    => $nutrients,
                'last_seen'    => $row['last_seen'],
                'first_seen'   => $row['first_seen'],
                'occurrences'  => (int)$row['occurrences'],
            ];
        }

        return ['symptoms' => $items, 'active_count' => $live['active_count']];
    }
}
