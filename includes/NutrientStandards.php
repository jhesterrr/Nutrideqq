<?php
// includes/NutrientStandards.php
//
// Per-demographic nutrient reference values (PDRI 2015, FNRI-DOST).
// Primary source: the nutrient_standards table (see migrations/002_nutrient_standards.sql,
// which seeds it from the official FNRI "Philippine Dietary Reference Intakes 2015:
// Summary Tables", Revised September 2018).
// Fallback: if the table is missing or has no row for the requested demographic,
// a static snapshot of the same official values is used so the system never
// silently degrades to a hardcoded single value. Every result carries its own
// provenance ("table" or "static-fallback") so outputs remain auditable.

require_once __DIR__ . '/../database.php';

class NutrientStandards
{
    /** Nutrients this system tracks (names must match fct_nutrients.nutrient_name). */
    public const TRACKED = [
        'Iron',
        'Potassium, K',
        'Calcium',
        'Sodium',
        'Fiber, total dietary',
        'Vitamin C',
    ];

    /** Upper bounds of the static fiber AI ranges (PDRI gives a range, not a point). */
    private const FIBER_AI_UPPER = [
        2   => 7.0,
        5   => 10.0,
        9   => 14.0,
        12  => 17.0,
        15  => 20.0,
        18  => 23.0,
        200 => 25.0,
    ];

    /** Source identifiers returned with every lookup for auditability. */
    public const SOURCE_TABLE  = 'table';
    public const SOURCE_STATIC = 'static-fallback';

    /** @var PDO|null */
    private static $pdo = null;
    /** @var array<string,array>|null cache of static standards */
    private static $static = null;
    /** @var bool|null whether nutrient_standards exists and is populated */
    private static $tableAvailable = null;

    private function __construct() {}

    /**
     * Resolve an int age from a user id. Prefers clients.date_of_birth (computed
     * from today), falls back to clients.age, then users table if it ever gains
     * a birthdate/age column. Returns null when unknown.
     */
    public static function resolveUserAge($pdo, $userId)
    {
        $userId = (int)$userId;
        if ($userId <= 0) return null;

        try {
            $st = $pdo->prepare('SELECT date_of_birth, age FROM clients WHERE user_id = ? LIMIT 1');
            $st->execute([$userId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                if (!empty($row['date_of_birth'])) {
                    $dob = date_create($row['date_of_birth']);
                    if ($dob) {
                        $age = (int)date_diff($dob, date_create('today'))->y;
                        if ($age >= 0 && $age <= 120) return $age;
                    }
                }
                $age = (int)($row['age'] ?? 0);
                if ($age > 0 && $age <= 120) return $age;
            }
        } catch (Exception $e) {
            // fall through
        }

        try {
            foreach (['birthdate', 'birthday', 'birth_date', 'age'] as $col) {
                try {
                    $st = $pdo->prepare("SELECT `$col` FROM users WHERE id = ? LIMIT 1");
                    $st->execute([$userId]);
                    $v = $st->fetchColumn();
                    if ($v === false || $v === null || $v === '') continue;
                    if ($col === 'age') {
                        $age = (int)$v;
                    } else {
                        $dob = date_create((string)$v);
                        if (!$dob) continue;
                        $age = (int)date_diff($dob, date_create('today'))->y;
                    }
                    if ($age >= 0 && $age <= 120) return $age;
                } catch (PDOException $e) {
                    continue; // column does not exist
                }
            }
        } catch (Exception $e) {
            // fall through
        }
        return null;
    }

    /**
     * Resolve sex for a user id. clients.gender is enum('male','female','other');
     * PDRI standards are sex-specific, so anything not male/female (including
     * 'other' and NULL) resolves to null and callers get the male/female union.
     */
    public static function resolveUserSex($pdo, $userId)
    {
        $userId = (int)$userId;
        if ($userId <= 0) return null;
        try {
            $st = $pdo->prepare("SELECT gender FROM clients WHERE user_id = ? LIMIT 1");
            $st->execute([$userId]);
            $g = strtolower(trim((string)$st->fetchColumn()));
            if ($g === 'male' || $g === 'female') return $g;
        } catch (Exception $e) {
            // fall through
        }
        return null;
    }

    /**
     * Fetch standards for one nutrient for a given age/sex.
     * Returns:
     *   target       float   working daily target: RNI if set, else AI upper bound
     *                        (fiber) or AI value
     *   rni_value    float|null
     *   ai_value     float|null
     *   ai_value_upper float|null
     *   ul_value     float|null  tolerable upper intake level (null = none established)
     *   unit         string
     *   source_type  string 'RNI'|'AI'
     *   source_note  string|null
     *   source       string NutrientStandards::SOURCE_* provenance
     *   age_used     int|null
     *   sex_used     string|null
     */
    public static function get($nutrientName, $age = null, $sex = null)
    {
        $sex = ($sex === 'male' || $sex === 'female') ? $sex : null;
        $age = ($age !== null && $age >= 0 && $age <= 120) ? (int)$age : null;

        if (self::$tableAvailable === null) self::detectTable();
        if (self::$tableAvailable) {
            $row = self::getFromTable($nutrientName, $age, $sex);
            if ($row !== null) {
                $row['source'] = self::SOURCE_TABLE;
                return $row;
            }
        }
        $row = self::getFromStatic($nutrientName, $age, $sex);
        $row['source'] = self::SOURCE_STATIC;
        return $row;
    }

    /**
     * Standards for all tracked nutrients at once.
     * $forUser: if provided (with $pdo), age/sex are resolved from the DB.
     */
    public static function getAllForUser($pdo, $userId)
    {
        $age = self::resolveUserAge($pdo, $userId);
        $sex = self::resolveUserSex($pdo, $userId);
        $out = [];
        foreach (self::TRACKED as $n) {
            $out[$n] = self::get($n, $age, $sex);
        }
        return $out;
    }

    /**
     * Testing/ops helper: force the static-fallback path as if the table were
     * unavailable. Static state only — resets on process exit.
     */
    public static function disableTableForTesting()
    {
        self::$tableAvailable = false;
    }

    /** Human-readable bracket label for audit output, e.g. "19-29 y, female". */
    public static function bracketLabel($age, $sex)
    {
        if ($age === null) {
            return 'default (demographics unknown)';
        }
        $label = ($sex === 'female') ? 'female' : (($sex === 'male') ? 'male' : 'sex-union');
        return $age . ' y, ' . $label;
    }

    // ── internals ────────────────────────────────────────────────────────────

    private static function pdo()
    {
        if (self::$pdo === null) {
            self::$pdo = (new Database())->getConnection();
        }
        return self::$pdo;
    }

    private static function detectTable()
    {
        try {
            $n = (int)self::pdo()->query('SELECT COUNT(*) FROM nutrient_standards')->fetchColumn();
            self::$tableAvailable = ($n > 0);
        } catch (Exception $e) {
            self::$tableAvailable = false;
        }
    }

    private static function getFromTable($nutrientName, $age, $sex)
    {
        try {
            if ($age === null) {
                // No age: use the adult 19-29 bracket as the neutral default.
                $sql = "SELECT * FROM nutrient_standards
                        WHERE nutrient_name = ? AND age_min = 19 AND age_max = 29
                          AND sex IN ('male','female')";
                $st = self::pdo()->prepare($sql);
                $st->execute([$nutrientName]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $sql = "SELECT * FROM nutrient_standards
                        WHERE nutrient_name = ? AND ? BETWEEN age_min AND age_max";
                $params = [$nutrientName, $age];
                $sql .= $sex !== null ? " AND sex = ?" : " AND sex IN ('male','female')";
                if ($sex !== null) $params[] = $sex;
                $st = self::pdo()->prepare($sql);
                $st->execute($params);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            }
            if (!$rows) return null;
            return self::normalizeRow($rows[0], $age, $sex);
        } catch (Exception $e) {
            self::$tableAvailable = false; // degrade to static on runtime errors
            return null;
        }
    }

    private static function normalizeRow($row, $age, $sex)
    {
        $rni = $row['rni_value'] !== null ? (float)$row['rni_value'] : null;
        $ai  = $row['ai_value'] !== null ? (float)$row['ai_value'] : null;
        $aiU = $row['ai_value_upper'] !== null ? (float)$row['ai_value_upper'] : null;
        $ul  = $row['ul_value'] !== null ? (float)$row['ul_value'] : null;

        $target = $rni ?? ($aiU ?? $ai) ?? 0.0;

        return [
            'target'        => $target,
            'rni_value'     => $rni,
            'ai_value'      => $ai,
            'ai_value_upper'=> $aiU,
            'ul_value'      => $ul,
            'unit'          => (string)$row['unit'],
            'source_type'   => (string)$row['source_type'],
            'source_note'   => $row['source_note'] ?: null,
            'age_used'      => $age,
            'sex_used'      => $sex,
        ];
    }

    /** Static snapshot of the same official PDRI 2015 values (fallback path). */
    private static function getFromStatic($nutrientName, $age, $sex)
    {
        if (self::$static === null) self::loadStatic();

        if ($age === null) {
            // No age: resolve the adult 19-29 bracket; with no sex given the first
            // row (male) wins — same sex-union default as the table path.
            $bracketAge = 19;
        } else {
            $bracketAge = $age;
        }

        $rows = self::$static[$nutrientName] ?? [];
        $chosen = null;
        foreach ($rows as $r) {
            if ($bracketAge >= $r['age_min'] && $bracketAge <= $r['age_max']) {
                if ($sex !== null && $r['sex'] === $sex) { $chosen = $r; break; }
                if ($chosen === null) $chosen = $r; // first sex match wins; rows are ordered M then F
            }
        }
        if ($chosen === null) {
            // Ultimate fallback: adult bracket of the requested sex (or first).
            foreach ($rows as $r) {
                if ($r['age_min'] === 19) {
                    if ($sex !== null && $r['sex'] === $sex) { $chosen = $r; break; }
                    if ($chosen === null) $chosen = $r;
                }
            }
            $chosen = $chosen ?: (reset($rows) ?: null);
        }
        if ($chosen === null) {
            return [
                'target' => 0.0, 'rni_value' => null, 'ai_value' => null,
                'ai_value_upper' => null, 'ul_value' => null, 'unit' => '',
                'source_type' => 'AI', 'source_note' => 'No standard available for this nutrient',
                'age_used' => $age, 'sex_used' => $sex,
            ];
        }

        $rni = $chosen['rni'] ?? null;
        $ai  = $chosen['ai'] ?? null;
        $aiU = $chosen['ai_upper'] ?? null;
        $target = $rni ?? ($aiU ?? $ai) ?? 0.0;

        return [
            'target'         => $target,
            'rni_value'      => $rni,
            'ai_value'       => $ai,
            'ai_value_upper' => $aiU,
            'ul_value'       => $chosen['ul'] ?? null,
            'unit'           => $chosen['unit'],
            'source_type'    => $rni !== null ? 'RNI' : 'AI',
            'source_note'    => $chosen['note'] ?? null,
            'age_used'       => $age,
            'sex_used'       => $sex,
        ];
    }

    private static function loadStatic()
    {
        // Mirrors migrations/002_nutrient_standards.sql (PDRI 2015, FNRI-DOST).
        $iron = [            [1,2,'male',8],[1,2,'female',8],[3,5,'male',9],[3,5,'female',9],
            [6,9,'male',10],[6,9,'female',9],[10,12,'male',12],[10,12,'female',20],
            [13,15,'male',19],[13,15,'female',28],[16,18,'male',14],[16,18,'female',28],
            [19,29,'male',12],[19,29,'female',28],[30,49,'male',12],[30,49,'female',28],
            [50,59,'male',12],[50,59,'female',10],[60,69,'male',12],[60,69,'female',10],
            [70,200,'male',12],[70,200,'female',10],
        ];
        $calcium = [
            [1,2,'male',500],[1,2,'female',500],[3,5,'male',550],[3,5,'female',550],
            [6,9,'male',700],[6,9,'female',700],[10,12,'male',1000],[10,12,'female',1000],
            [13,15,'male',1000],[13,15,'female',1000],[16,18,'male',1000],[16,18,'female',1000],
            [19,29,'male',750],[19,29,'female',750],[30,49,'male',750],[30,49,'female',750],
            [50,59,'male',750],[50,59,'female',800],[60,69,'male',800],[60,69,'female',800],
            [70,200,'male',800],[70,200,'female',800],
        ];
        $vitc = [
            [1,2,'male',45],[1,2,'female',45],[3,5,'male',45],[3,5,'female',45],
            [6,9,'male',45],[6,9,'female',45],[10,12,'male',45],[10,12,'female',45],
            [13,15,'male',60],[13,15,'female',55],[16,18,'male',70],[16,18,'female',60],
            [19,29,'male',70],[19,29,'female',60],[30,49,'male',70],[30,49,'female',60],
            [50,59,'male',70],[50,59,'female',60],[60,69,'male',70],[60,69,'female',60],
            [70,200,'male',70],[70,200,'female',60],
        ];
        $fiber = [
            [1,2,'male',6],[1,2,'female',6],[3,5,'male',8],[3,5,'female',8],
            [6,9,'male',11],[6,9,'female',11],[10,12,'male',15],[10,12,'female',15],
            [13,15,'male',18],[13,15,'female',18],[16,18,'male',21],[16,18,'female',21],
            [19,29,'male',20],[19,29,'female',20],[30,49,'male',20],[30,49,'female',20],
            [50,59,'male',20],[50,59,'female',20],[60,69,'male',20],[60,69,'female',20],
            [70,200,'male',20],[70,200,'female',20],
        ];
        self::$static = [];
        foreach ([
            'Iron' => [$iron, 'mg'], 'Calcium' => [$calcium, 'mg'], 'Vitamin C' => [$vitc, 'mg'],
        ] as $name => [$rows, $unit]) {
            foreach ($rows as [$min, $max, $sex, $v]) {
                self::$static[$name][] = [
                    'age_min' => $min, 'age_max' => $max, 'sex' => $sex,
                    'rni' => (float)$v, 'unit' => $unit,
                    'note' => ($name === 'Iron' && $sex === 'female' && $min >= 10)
                        ? 'FNRI: cannot be met by usual diet alone' : null,
                ];
            }
        }
        foreach ($fiber as [$min, $max, $sex, $v]) {
            $upper = null;
            foreach (self::FIBER_AI_UPPER as $maxAge => $u) {
                if ($max <= $maxAge) { $upper = $u; break; }
            }
            self::$static['Fiber, total dietary'][] = [
                'age_min' => $min, 'age_max' => $max, 'sex' => $sex,
                'ai' => (float)$v, 'ai_upper' => $upper, 'unit' => 'g', 'note' => null,
            ];
        }
        $adultMax = ['10' => 12, '13' => 15, '16' => 18, '19' => 29, '30' => 49, '50' => 59, '60' => 69, '70' => 200];
        foreach (['Potassium, K' => 2000, 'Sodium' => 500] as $name => $adultVal) {
            $child = [1 => 1000, 3 => 1400, 6 => 1600];
            if ($name === 'Sodium') $child = [1 => 225, 3 => 300, 6 => 400];
            foreach ($child as $min => $v) {
                $max = $min === 1 ? 2 : ($min === 3 ? 5 : 9);
                self::$static[$name][] = [
                    'age_min' => $min, 'age_max' => $max, 'sex' => 'male',
                    'ai' => (float)$v, 'unit' => 'mg', 'note' => null,
                ];
                self::$static[$name][] = [
                    'age_min' => $min, 'age_max' => $max, 'sex' => 'female',
                    'ai' => (float)$v, 'unit' => 'mg', 'note' => null,
                ];
            }
            foreach ($adultMax as $min => $max) {
                $note = ($name === 'Potassium, K' ? 'WHO additional target 3510 mg/day' : 'WHO limit < 2000 mg/day')
                    . ($min < 19 ? ' (scaled by energy for children)' : '');
                self::$static[$name][] = [
                    'age_min' => (int)$min, 'age_max' => $max, 'sex' => 'male',
                    'ai' => (float)$adultVal, 'unit' => 'mg', 'note' => $note,
                ];
                self::$static[$name][] = [
                    'age_min' => (int)$min, 'age_max' => $max, 'sex' => 'female',
                    'ai' => (float)$adultVal, 'unit' => 'mg', 'note' => $note,
                ];
            }
        }
        // Static ULs (same rules as the SQL seed).
        foreach (self::$static['Iron'] as &$r) { $r['ul'] = 45.0; } unset($r);
        foreach (self::$static['Calcium'] as &$r) { $r['ul'] = $r['age_max'] >= 70 ? 2000.0 : 3000.0; } unset($r);
        $vitcUl = function ($min, $max) {
            if ($max <= 2) return 400.0;
            if ($min >= 3 && $max <= 9) return 650.0;
            if ($min >= 10 && $max <= 15) return 1200.0;
            if ($min >= 16 && $max <= 18) return 1800.0;
            return 1000.0; // 19+
        };
        foreach (self::$static['Vitamin C'] as &$r) { $r['ul'] = $vitcUl($r['age_min'], $r['age_max']); } unset($r);
        // Potassium/Sodium/Fiber: no UL established from food sources.
    }
}
