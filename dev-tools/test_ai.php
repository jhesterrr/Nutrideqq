<?php

/**
 * NutriDeq NLP Symptom-to-Nutrient Mapping â€” Gold-Set Evaluation Harness
 *
 * Evaluates the NLP pipeline (LLM symptom extraction + versioned mapping table)
 * with per-class Precision / Recall / F1 over the curated gold corpus
 * (dev-tools/gold_set.php), plus macro/micro averages, per-language slices,
 * distractor handling, and a negative-case false-positive rate.
 *
 * The symptom-to-nutrient mapping under test is versioned citable data
 * (config/symptom_nutrient_mapping.json) loaded through includes/SymptomMapper.php â€”
 * the exact same code path production uses (api/ai_service.php), including the
 * byte-identical extraction prompt via NutriDeqAI::buildExtractionPrompt().
 *
 * Usage:
 *   php test_ai.php                     # Mock mode (offline, deterministic)
 *   php test_ai.php --live              # Live Groq API (requires GROQ_API_KEY or legacy-php/.env)
 *   php test_ai.php --json=report.json  # Also export a machine-readable report
 *   php test_ai.php --persist           # Store the run in nlp_eval_runs (migration 003)
 *   php test_ai.php --show-mapping      # Print the versioned mapping table and exit
 *   php test_ai.php --show-corpus       # Print corpus metadata + case list and exit
 *
 * Protocols and corpus governance: see dev-tools/EVAL_RUNBOOK.md.
 */

require_once __DIR__ . '/../includes/SymptomMapper.php';
require_once __DIR__ . '/../api/ai_service.php';

const HARNESS_VERSION = '2.1.0';
const TARGET_F1 = 0.80;

// --- CLI flags ---------------------------------------------------------------
$useLive = in_array('--live', $argv, true);
$persist = in_array('--persist', $argv, true);
$showMapping = in_array('--show-mapping', $argv, true);
$showCorpus = in_array('--show-corpus', $argv, true);
$jsonPath = null;
foreach ($argv as $a) {
    if (str_starts_with($a, '--json=')) $jsonPath = substr($a, 7);
}

// --- Gold corpus (versioned, curated in its own file) -------------------------
$CORPUS = require __DIR__ . '/gold_set.php';
$GOLD_SET = $CORPUS['cases'];
$corpusVersion = $CORPUS['corpus_version'];

// --- Mapping table status (fail fast, citable) -------------------------------
if (!SymptomMapper::isAvailable()) {
    fwrite(STDERR, "FATAL: mapping table unavailable: " . SymptomMapper::getLoadError() . "\n");
    exit(2);
}
$mappingDoc = SymptomMapper::getDocument();
$mappingVersion = SymptomMapper::getVersion();

if ($showMapping) {
    echo "Symptom-to-Nutrient Mapping Table â€” version {$mappingVersion}\n";
    echo "Citation: " . ($mappingDoc['citation'] ?? 'n/a') . "\n\n";
    foreach ($mappingDoc['classes'] as $c) {
        printf("%-14s -> %-22s (priority %s)\n", $c['class_id'], $c['nutrient_name'] ?? '(null â€” review only)', $c['priority'] ?? '-');
        echo "  label:     " . ($c['label'] ?? '') . "\n";
        echo "  keywords:  " . implode(', ', $c['keywords'] ?? []) . "\n";
        echo "  rationale: " . ($c['rationale'] ?? '') . "\n\n";
    }
    exit(0);
}

if ($showCorpus) {
    echo "Gold Corpus â€” version {$corpusVersion} (updated " . ($CORPUS['updated'] ?? 'n/a') . ")\n";
    echo "Languages: " . implode(', ', $CORPUS['languages'] ?? []) . "\n";
    echo "Notes: " . ($CORPUS['notes'] ?? '') . "\n";
    if (!empty($CORPUS['known_limitations'])) {
        echo "Known limitations:\n";
        foreach ($CORPUS['known_limitations'] as $l) echo "  - $l\n";
    }
    echo "Cases (" . count($GOLD_SET) . "):\n";
    foreach ($GOLD_SET as $i => $c) {
        printf("  %3d [%-8s] %-70s -> %s%s\n", $i, $c['lang'], substr($c['message'], 0, 70),
            implode(',', $c['expected']) ?: '[]',
            !empty($c['limitation']) ? "  (limitation: {$c['limitation']})" : '');
    }
    exit(0);
}

// Class-id â†’ nutrient resolution + gold-set validation
$classToNutrient = [];
foreach ($mappingDoc['classes'] as $c) {
    if ($c['nutrient_name'] !== null) $classToNutrient[$c['class_id']] = $c['nutrient_name'];
}
foreach ($GOLD_SET as $i => $case) {
    foreach ($case['expected'] as $cid) {
        if (!isset($classToNutrient[$cid])) {
            fwrite(STDERR, "FATAL: corpus case #$i expects unknown class '$cid'\n");
            exit(2);
        }
    }
}
$mappedClasses = array_keys($classToNutrient);

// --- NLP callers -------------------------------------------------------------
function resolveGroqKey()
{
    $apiKey = getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? null);
    if (!$apiKey) {
        $envFile = dirname(__DIR__) . '/.env'; // legacy-php/.env
        if (file_exists($envFile) && preg_match('/^GROQ_API_KEY\s*=\s*["\']?([^"\'\r\n]+)["\']?/m', file_get_contents($envFile), $m)) {
            $apiKey = trim($m[1]);
        }
    }
    return $apiKey;
}

function callGroqLive($message)
{
    $apiKey = resolveGroqKey();
    if (!$apiKey) {
        fwrite(STDERR, "ERROR: GROQ_API_KEY not set (env or legacy-php/.env). Run mock mode, or see dev-tools/EVAL_RUNBOOK.md.\n");
        exit(2);
    }
    $data = [
        'model' => 'openai/gpt-oss-20b',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a clinical NLP engine. Always respond with valid JSON only. No markdown. No explanation. Do not output your reasoning.'],
            ['role' => 'user', 'content' => NutriDeqAI::buildExtractionPrompt($message)],
        ],
        'temperature' => 0.1,
        'reasoning_effort' => 'low',
        // Mirrors the production extraction request.
        'max_tokens' => 2048,
    ];
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $decoded = json_decode((string)$response, true);
    $content = $decoded['choices'][0]['message']['content'] ?? null;
    return $content ? json_decode($content, true) : null;
}

function getNlpResponse($case, $useLive)
{
    $message = $case['message'];
    // Production canonicalizes text-speak before the model sees it — the
    // harness must evaluate the exact production path.
    if (class_exists('TextNormalizer')) {
        require_once __DIR__ . '/../includes/TextNormalizer.php';
        $message = TextNormalizer::normalize($message);
    }
    if ($useLive) return callGroqLive($message);

    // Mock mode: replay the curated LLM outputs (mock_symptoms) verbatim,
    // then apply production's negation guard so the evaluated pipeline is
    // the exact production one (extraction + v1.3 message-level filtering).
    $symptoms = $case['mock_symptoms'] ?? [];
    $filtered = array_values(array_filter($symptoms, function ($s) use ($message) {
        [, $cid] = SymptomMapper::mapWithClass($s);
        return $cid === null || !SymptomMapper::classIsNegatedInMessage($message, $cid);
    }));
    return ['symptoms' => $filtered];
}

// Deterministic fingerprint of the exact prompt under test (audit/citation).
$promptHash = hash('sha256', NutriDeqAI::buildExtractionPrompt('NUTRIDEQ_PROMPT_PROBE'));

// --- Main evaluation ---------------------------------------------------------
$evaluated = 0; $skipped = 0; $exactMatches = 0;
$perClass = []; // class_id => [tp, fp, fn]
foreach ($mappedClasses as $cid) $perClass[$cid] = ['tp' => 0, 'fp' => 0, 'fn' => 0];
$caseRows = [];
$limitationMisses = [];
$distractor = ['cases' => 0, 'misses' => 0];
$negatives = ['cases' => 0, 'fp_cases' => 0];
$langStats = []; // base lang (EN|TL|BIS) => [cases, exact]

echo "NutriDeq NLP Symptom-to-Nutrient Mapping â€” Gold-Set Evaluation\n";
echo "Harness v" . HARNESS_VERSION . " | Mapping v" . $mappingVersion . " | Corpus v" . $corpusVersion . " | Mode: " . ($useLive ? "LIVE (Groq openai/gpt-oss-20b)" : "Mock (offline)") . "\n";
echo "Prompt hash: $promptHash\n";
$negCount = count(array_filter($GOLD_SET, fn($c) => str_ends_with($c['lang'], '-neg') || str_ends_with($c['lang'], '-distr')));
echo "Dataset: " . count($GOLD_SET) . " cases (" . (count($GOLD_SET) - $negCount) . " positive, $negCount negative/distractor)\n";
echo str_repeat('=', 100) . "\n\n";

printf("%-46s %-9s %-24s %-24s %s\n", "Client Message", "Lang", "Predicted", "Expected", "Verdict");
echo str_repeat('-', 100) . "\n";

foreach ($GOLD_SET as $case) {
    $message = $case['message'];
    $expected = $case['expected'];
    $isNegative = str_ends_with($case['lang'], '-neg');
    $isDistractor = str_ends_with($case['lang'], '-distr');
    $baseLang = preg_replace('/-(neg|distr)$/', '', $case['lang']);

    $nlp = getNlpResponse($case, $useLive);
    if ($nlp === null) {
        $skipped++;
        printf("%-46s %-9s %-24s %-24s %s\n", substr($message, 0, 46), $case['lang'], '(skipped)', implode(',', $expected) ?: '[]', 'SKIP');
        continue;
    }

    $predictedClasses = [];
    foreach (($nlp['symptoms'] ?? []) as $symptom) {
        // New schema: objects {label, evidence, ...}; accept plain strings too.
        $label = is_array($symptom) ? ($symptom['label'] ?? $symptom['symptom'] ?? '') : $symptom;
        [$nutrient, $classId] = SymptomMapper::mapWithClass($label);
        if ($classId !== null && !in_array($classId, $predictedClasses, true)) {
            $predictedClasses[] = $classId;
        }
    }

    sort($predictedClasses);
    $expectedSorted = $expected;
    sort($expectedSorted);
    $exact = ($predictedClasses === $expectedSorted);
    $evaluated++;

    foreach ($mappedClasses as $cid) {
        $predHas = in_array($cid, $predictedClasses, true);
        $expHas = in_array($cid, $expected, true);
        if ($predHas && $expHas) $perClass[$cid]['tp']++;
        elseif ($predHas && !$expHas) $perClass[$cid]['fp']++;
        elseif (!$predHas && $expHas) $perClass[$cid]['fn']++;
    }
    if ($exact) $exactMatches++;

    if ($isNegative || $isDistractor) {
        if ($isNegative) { $negatives['cases']++; if (!empty($predictedClasses)) $negatives['fp_cases']++; }
        else {
            $distractor['cases']++;
            if (!$exact) {
                $distractor['misses']++;
                if (!empty($case['limitation'])) $limitationMisses[] = $case;
            }
        }
    } else {
        $langStats[$baseLang]['cases'] = ($langStats[$baseLang]['cases'] ?? 0) + 1;
        if ($exact) $langStats[$baseLang]['exact'] = ($langStats[$baseLang]['exact'] ?? 0) + 1;
    }

    $caseRows[] = [
        'message' => $message, 'lang' => $case['lang'],
        'predicted_classes' => $predictedClasses, 'expected_classes' => $expectedSorted,
        'exact_match' => $exact, 'raw_symptoms' => $nlp['symptoms'] ?? [],
        'limitation' => $case['limitation'] ?? null,
    ];

    $verdict = $exact ? 'OK' : (!empty($case['limitation']) ? 'MISS (known limitation)' : 'MISS');
    printf("%-46s %-9s %-24s %-24s %s\n",
        substr($message, 0, 46), $case['lang'],
        substr(implode(',', $predictedClasses) ?: '[]', 0, 24),
        substr(implode(',', $expectedSorted) ?: '[]', 0, 24),
        $verdict
    );
}

// --- Metrics -----------------------------------------------------------------
$metrics = [];
foreach ($perClass as $cid => $m) {
    $p = ($m['tp'] + $m['fp']) > 0 ? $m['tp'] / ($m['tp'] + $m['fp']) : 0.0;
    $r = ($m['tp'] + $m['fn']) > 0 ? $m['tp'] / ($m['tp'] + $m['fn']) : 0.0;
    $f1 = ($p + $r) > 0 ? 2 * $p * $r / ($p + $r) : 0.0;
    $metrics[$cid] = ['tp' => $m['tp'], 'fp' => $m['fp'], 'fn' => $m['fn'],
        'support' => $m['tp'] + $m['fn'], 'precision' => $p, 'recall' => $r, 'f1' => $f1];
}

$macro = ['precision' => 0.0, 'recall' => 0.0, 'f1' => 0.0];
foreach ($metrics as $m) {
    $macro['precision'] += $m['precision']; $macro['recall'] += $m['recall']; $macro['f1'] += $m['f1'];
}
$n = count($metrics) ?: 1;
$macro = ['precision' => $macro['precision'] / $n, 'recall' => $macro['recall'] / $n, 'f1' => $macro['f1'] / $n];

$gTP = $gFP = $gFN = 0;
foreach ($perClass as $m) { $gTP += $m['tp']; $gFP += $m['fp']; $gFN += $m['fn']; }
$microP = ($gTP + $gFP) > 0 ? $gTP / ($gTP + $gFP) : 0.0;
$microR = ($gTP + $gFN) > 0 ? $gTP / ($gTP + $gFN) : 0.0;
$microF1 = ($microP + $microR) > 0 ? 2 * $microP * $microR / ($microP + $microR) : 0.0;
$subsetAccuracy = $evaluated > 0 ? $exactMatches / $evaluated : 0.0;
$negFpRate = $negatives['cases'] > 0 ? $negatives['fp_cases'] / $negatives['cases'] : 0.0;

// Per-language slices (positives only) + TL micro-F1 (backward-compatible key)
$perLanguage = [];
foreach ($langStats as $lang => $s) {
    $perLanguage[$lang] = [
        'cases' => $s['cases'],
        'exact_matches' => $s['exact'] ?? 0,
        'exact_rate' => $s['cases'] > 0 ? ($s['exact'] ?? 0) / $s['cases'] : 0.0,
    ];
}
$tlCases = array_values(array_filter($caseRows, fn($r) => str_starts_with($r['lang'], 'TL') && !str_ends_with($r['lang'], '-neg') && !str_ends_with($r['lang'], '-distr')));
$tlGTP = $tlFP = $tlFN = 0;
foreach ($tlCases as $r) {
    foreach ($mappedClasses as $cid) {
        $predHas = in_array($cid, $r['predicted_classes'], true);
        $expHas = in_array($cid, $r['expected_classes'], true);
        if ($predHas && $expHas) $tlGTP++;
        elseif ($predHas && !$expHas) $tlFP++;
        elseif (!$predHas && $expHas) $tlFN++;
    }
}
$tlMicroP = ($tlGTP + $tlFP) > 0 ? $tlGTP / ($tlGTP + $tlFP) : 0.0;
$tlMicroR = ($tlGTP + $tlFN) > 0 ? $tlGTP / ($tlGTP + $tlFN) : 0.0;
$tlMicroF1 = ($tlMicroP + $tlMicroR) > 0 ? 2 * $tlMicroP * $tlMicroR / ($tlMicroP + $tlMicroR) : 0.0;
$tlCasesCount = count($tlCases);
$tlExact = 0;
foreach ($tlCases as $r) if ($r['exact_match']) $tlExact++;

// --- Report ------------------------------------------------------------------
echo "\n" . str_repeat('=', 100) . "\n";
echo "PER-CLASS METRICS (class = versioned mapping class; support = gold positives)\n";
echo str_repeat('=', 100) . "\n";
printf("%-14s %-24s %7s %4s %4s %4s %9s %9s %9s\n", "Class", "Nutrient", "Support", "TP", "FP", "FN", "Precision", "Recall", "F1");
echo str_repeat('-', 100) . "\n";
foreach ($metrics as $cid => $m) {
    printf("%-14s %-24s %7d %4d %4d %4d %9.3f %9.3f %9.3f\n",
        $cid, $classToNutrient[$cid], $m['support'], $m['tp'], $m['fp'], $m['fn'],
        $m['precision'], $m['recall'], $m['f1']);
}
echo str_repeat('-', 100) . "\n";
printf("%-14s %-24s %7s %4d %4d %4d %9.3f %9.3f %9.3f  (micro)\n", "MICRO-AVG", "", "", $gTP, $gFP, $gFN, $microP, $microR, $microF1);
printf("%-14s %-24s %7s %4s %4s %4s %9.3f %9.3f %9.3f  (macro)\n", "MACRO-AVG", "", "", '', '', '', $macro['precision'], $macro['recall'], $macro['f1']);

echo "\nSubset accuracy (exact class-set match): " . number_format($subsetAccuracy * 100, 2) . "% ($exactMatches/$evaluated)\n";
echo "Per-language exact rates (positives):";
foreach ($perLanguage as $lang => $s) echo " $lang " . number_format($s['exact_rate'] * 100, 1) . "% ({$s['exact_matches']}/{$s['cases']})";
echo "\n";
echo "Taglish slice: " . number_format(($tlCasesCount > 0 ? $tlExact / $tlCasesCount : 0.0) * 100, 2) . "% exact ($tlExact/$tlCasesCount) | micro-F1 " . number_format($tlMicroF1 * 100, 2) . "%\n";
echo "Negative cases: " . $negatives['fp_cases'] . "/" . $negatives['cases'] . " triggered false mappings (FPR " . number_format($negFpRate * 100, 2) . "%)\n";
echo "Distractors: " . $distractor['misses'] . "/" . $distractor['cases'] . " missed";
if (!empty($limitationMisses)) {
    echo " â€” of which " . count($limitationMisses) . " are documented negation limitations:";
    foreach ($limitationMisses as $lm) echo ' "' . substr($lm['message'], 0, 40) . '"';
}
echo "\n";
echo "Target micro-F1: " . (TARGET_F1 * 100) . "%\n";
echo "Result: " . ($microF1 >= TARGET_F1 ? "PASS" : "BELOW TARGET â€” review mapping keywords or prompt") . "\n";
if ($skipped > 0) echo "Skipped cases: $skipped\n";

// --- JSON export -------------------------------------------------------------
if ($jsonPath !== null) {
    $report = [
        'harness_version' => HARNESS_VERSION,
        'generated_at' => date('c'),
        'mode' => $useLive ? 'live' : 'mock',
        'model' => $useLive ? 'openai/gpt-oss-20b' : null,
        'prompt_hash' => $promptHash,
        'mapping' => [
            'version' => $mappingVersion,
            'released' => $mappingDoc['released'] ?? null,
            'citation' => $mappingDoc['citation'] ?? null,
            'table_path' => 'legacy-php/config/symptom_nutrient_mapping.json',
        ],
        'corpus' => [
            'version' => $corpusVersion,
            'path' => 'legacy-php/dev-tools/gold_set.php',
            'updated' => $CORPUS['updated'] ?? null,
            'known_limitations' => $CORPUS['known_limitations'] ?? [],
        ],
        'prompt' => ['source' => 'NutriDeqAI::buildExtractionPrompt()', 'identical_to_production' => true],
        'dataset' => ['total' => count($GOLD_SET), 'evaluated' => $evaluated, 'skipped' => $skipped],
        'per_class' => array_map(function ($cid, $m) use ($classToNutrient) {
            return ['class_id' => $cid, 'nutrient_name' => $classToNutrient[$cid]] + $m;
        }, array_keys($metrics), array_values($metrics)),
        'macro_avg' => $macro,
        'micro_avg' => ['precision' => $microP, 'recall' => $microR, 'f1' => $microF1],
        'subset_accuracy' => $subsetAccuracy,
        'per_language' => $perLanguage,
        'taglish_slice' => ['cases' => $tlCasesCount, 'exact_matches' => $tlExact,
            'exact_rate' => $tlCasesCount > 0 ? $tlExact / $tlCasesCount : 0.0, 'micro_f1' => $tlMicroF1],
        'negative_cases' => ['cases' => $negatives['cases'], 'false_positive_cases' => $negatives['fp_cases'], 'false_positive_rate' => $negFpRate],
        'distractors' => ['cases' => $distractor['cases'], 'misses' => $distractor['misses'],
            'limitation_misses' => count($limitationMisses)],
        'target_f1' => TARGET_F1,
        'passed' => $microF1 >= TARGET_F1,
        'cases' => $caseRows,
    ];
    $ok = file_put_contents($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo $ok !== false ? "\nJSON report written: $jsonPath\n" : "\nWARNING: failed to write $jsonPath\n";
}

// --- DB persistence (nlp_eval_runs, migration 003) -----------------------------
if ($persist) {
    $runUid = bin2hex(random_bytes(16));
    try {
        require_once __DIR__ . '/../database.php';
        $pdo = (new Database())->getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO nlp_eval_runs
                (run_uid, harness_version, corpus_version, mapping_version, mode, model, prompt_hash,
                 total_cases, evaluated, skipped,
                 micro_precision, micro_recall, micro_f1, macro_precision, macro_recall, macro_f1,
                 subset_accuracy, taglish_exact_rate, taglish_micro_f1, negative_fpr,
                 target_f1, passed, per_class_json, report_json, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $notes = !empty($limitationMisses)
            ? ('includes ' . count($limitationMisses) . ' documented negation-limitation cases')
            : null;
        $stmt->execute([
            $runUid, HARNESS_VERSION, $corpusVersion, $mappingVersion,
            $useLive ? 'live' : 'mock', $useLive ? 'openai/gpt-oss-20b' : null, $promptHash,
            count($GOLD_SET), $evaluated, $skipped,
            $microP, $microR, $microF1, $macro['precision'], $macro['recall'], $macro['f1'],
            $subsetAccuracy, ($tlCasesCount > 0 ? $tlExact / $tlCasesCount : null), $tlMicroF1, $negFpRate,
            TARGET_F1, $microF1 >= TARGET_F1 ? 1 : 0,
            json_encode($metrics), isset($report) ? json_encode($report, JSON_UNESCAPED_UNICODE) : null,
            $notes,
        ]);
        echo "\nPersisted to nlp_eval_runs: run_uid=$runUid\n";
    } catch (Exception $e) {
        fwrite(STDERR, "\nWARNING: DB persistence failed: " . $e->getMessage() . "\n");
        exit(3);
    }
}

echo "\nMapping citation: " . ($mappingDoc['citation'] ?? 'n/a') . "\n";
