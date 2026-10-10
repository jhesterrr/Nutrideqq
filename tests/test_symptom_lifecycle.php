<?php
// tests/test_symptom_lifecycle.php
//
// Offline unit checks for the two deterministic cores of the wellness
// insights pipeline:
//   1. SymptomMapper::canonicalize()/canonicalKey()  — synonym collapse
//   2. SymptomLifecycle::resolveTargets()            — reference-scoped,
//      persistence-aware resolution (NO database required)
//
// Run: C:\xampp\php\php.exe tests\test_symptom_lifecycle.php

require_once __DIR__ . '/../includes/SymptomLifecycle.php';

$pass = 0; $fail = 0; $failures = [];

function check($label, $expected, $actual, &$pass, &$fail, &$failures)
{
    if ($expected === $actual) {
        $pass++;
        echo "  ✔  {$label}\n";
    } else {
        $fail++;
        $failures[] = "$label  expected=[" . json_encode($expected) . "] got=[" . json_encode($actual) . "]";
        echo "  ✘  {$label}  expected=" . json_encode($expected) . "  got=" . json_encode($actual) . "\n";
    }
}

// ── 1. Canonicalization: synonyms collapse to one label + key ──────────────
echo "Canonicalization\n";
$groups = [
    'SC-FATIGUE' => ['fatigue', 'tired', 'exhausted', 'pagod', 'kapoy', 'walang lakas'],
    'SC-CRAMP'   => ['muscle cramps', 'cramp', 'spasm', 'pulikat', 'kalampos'],
    'SC-DIZZY'   => ['dizziness', 'dizzy', 'lightheaded', 'hilo', 'lula'],
    'SC-HEADACHE'=> ['headache', 'migraine', 'sakit ng ulo'],
    'SC-BLOAT'   => ['bloating', 'masakit tiyan', 'kabag'],
];
foreach ($groups as $classId => $synonyms) {
    $keys = [];
    foreach ($synonyms as $s) {
        $canon = SymptomMapper::canonicalize($s);
        $keys[] = SymptomMapper::canonicalKey($canon);
    }
    check("$classId collapses {".implode(', ', $synonyms)."}", array_fill(0, count($synonyms), $classId), $keys, $pass, $fail, $failures);
    // Labels must be identical too (not just the key).
    $labels = array_map(fn($s) => SymptomMapper::canonicalize($s)['label'], $synonyms);
    check("$classId yields one label", 1, count(array_unique($labels)), $pass, $fail, $failures);
}

// Distinct review symptoms must NOT collapse into one another.
$fever = SymptomMapper::canonicalKey(SymptomMapper::canonicalize('fever'));
$cough = SymptomMapper::canonicalKey(SymptomMapper::canonicalize('cough'));
check('fever and cough stay distinct', true, $fever !== $cough, $pass, $fail, $failures);

// ── 2. Resolution: reference-scoped + persistence-aware ────────────────────
echo "\nResolution (resolveTargets)\n";
$active = [
    ['id' => 1, 'symptom_keyword' => 'Fatigue'],
    ['id' => 2, 'symptom_keyword' => 'Headache'],
    ['id' => 3, 'symptom_keyword' => 'Bloating'],
];

$cases = [
    // [message, expected ids]
    ['im feeling better now my fatigue is gone', [1]],
    ['my headache is gone now', [2]],
    ['hindi na ako pagod', [1]],                       // Tagalog resolves English key via class
    ['wala na ang sakit ng ulo ko', [2]],             // class keyword match (sakit ng ulo)
    ['my bloating resolved, salamat', [3]],
    // Conservative: recovery language but no symptom named clears nothing.
    ['i feel good today', []],
    ['so much better now, thank you doc', []],
    // Persistence next to the symptom keeps it open.
    ['i feel better but my headache is still here', []],
    ['still have my headache', []],
    ['hindi pa rin nawawala ang sakit ng ulo ko', []],
    // Negated recovery marker must not resolve.
    ['i am not feeling better, my fatigue is worse', []],
    // One symptom resolves, an unrelated one stays.
    ['my headache is gone now but my stomach is still bloated', [2]],
];

foreach ($cases as [$msg, $expected]) {
    $got = SymptomLifecycle::resolveTargets($msg, $active);
    check($msg, $expected, $got, $pass, $fail, $failures);
}

// ── 3. Read-time dedup merge (getActiveInsights) over an in-memory DB ──────
// Guards the case that matters most in production: LEGACY synonym rows
// written before v1.5.0 must collapse at read time so the card never shows
// "Fatigue" next to "Tired" next to "Exhausted".
echo "\nRead-time dedup (getActiveInsights)\n";
if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $pdo = new PDO('sqlite::memory:');
    $pdo->exec("CREATE TABLE ai_extracted_symptoms (id INTEGER PRIMARY KEY, user_id INT, symptom_keyword TEXT, mapped_nutrient TEXT, status TEXT, created_at TEXT)");
    $ins = $pdo->prepare('INSERT INTO ai_extracted_symptoms (user_id,symptom_keyword,mapped_nutrient,status,created_at) VALUES (?,?,?,?,?)');
    foreach ([
        ['fatigue',   'Iron',    'active',   '2026-10-01 08:00:00'],
        ['tired',     'Iron',    'active',   '2026-10-02 09:00:00'],
        ['exhausted', 'Iron',    'active',   '2026-10-03 10:00:00'],
        ['Headache',  'Calcium', 'active',   '2026-10-02 11:00:00'],
        ['fever',     null,      'active',   '2026-10-04 12:00:00'],
        ['pagod',     'Iron',    'resolved', '2026-10-05 13:00:00'], // must be excluded
    ] as [$k, $n, $s, $c]) {
        $ins->execute([1, $k, $n, $s, $c]);
    }

    $out = SymptomLifecycle::getActiveInsights($pdo, 1, 20);
    check('3 distinct clinical classes from 4 active rows', 3, $out['active_count'], $pass, $fail, $failures);
    $byKw = [];
    foreach ($out['symptoms'] as $r) $byKw[$r['symptom_keyword']] = $r;
    check('fatigue/tired/exhausted collapse -> one Fatigue, occ=3', [3, 'Iron'],
        [(int)($byKw['Fatigue']['occurrences'] ?? 0), $byKw['Fatigue']['nutrient'] ?? null], $pass, $fail, $failures);
    check('review symptom Fever stays distinct', true, isset($byKw['Fever']), $pass, $fail, $failures);
    // Occ=3 (not 4) proves the resolved 'pagod' row was excluded from the merge.
    check('resolved row excluded (Fatigue occ stays 3)', 3, (int)($byKw['Fatigue']['occurrences'] ?? 0), $pass, $fail, $failures);
} else {
    echo "  ~  skipped (pdo_sqlite not available)\n";
}

// ── 4. LLM resolution filter (conservative) ────────────────────────────────
echo "\nLLM resolution filter (resolutionsToApply)\n";
$modelRes = [
    ['symptom' => 'Headache',  'status' => 'resolved',    'confidence' => 0.9],
    ['symptom' => 'Fatigue',   'status' => 'improved',    'confidence' => 0.7],
    ['symptom' => 'Bloating',  'status' => 'persisting',  'confidence' => 0.9],
    ['symptom' => 'Nausea',    'status' => 'unmentioned', 'confidence' => 1.0],
    ['symptom' => 'Dizziness', 'status' => 'resolved',    'confidence' => 0.3], // below floor
    ['symptom' => '',          'status' => 'resolved',    'confidence' => 1.0], // no label
];
$applied = SymptomLifecycle::resolutionsToApply($modelRes);
check('only clear, confident resolutions apply', ['Headache', 'Fatigue'], $applied, $pass, $fail, $failures);
check('persisting/unmentioned/low-confidence ignored', false,
    in_array('Bloating', $applied, true) || in_array('Nausea', $applied, true) || in_array('Dizziness', $applied, true),
    $pass, $fail, $failures);

// ── 5. Prompt wiring: open-symptom context + resolution schema ─────────────
echo "\nExtraction prompt building\n";
require_once __DIR__ . '/../api/ai_service.php';
$p1 = NutriDeqAI::buildExtractionPrompt('hindi na ako pagod', ['Fatigue', 'Headache']);
check('prompt embeds the open symptoms', true,
    strpos($p1, 'OPEN SYMPTOMS ON RECORD') !== false && strpos($p1, '- Fatigue') !== false, $pass, $fail, $failures);
check('prompt asks for a resolutions schema', true, strpos($p1, '"resolutions"') !== false, $pass, $fail, $failures);
$p2 = NutriDeqAI::buildExtractionPrompt('hello there');
check('no open-symptom block when none active', false, strpos($p2, 'OPEN SYMPTOMS ON RECORD') !== false, $pass, $fail, $failures);

echo "\nPASS: {$pass}  FAIL: {$fail}\n";
if ($fail) {
    echo "\nFailures:\n";
    foreach ($failures as $f) echo "  - $f\n";
}
exit($fail === 0 ? 0 : 1);
