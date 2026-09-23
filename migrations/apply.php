<?php
// migrations/apply.php — idempotent migration runner.
// Usage: php migrations/apply.php [filename.sql]   (default: 002_nutrient_standards.sql)
// Each migration registers a guard deciding whether it can be skipped because
// its effects are already present.
require_once __DIR__ . '/../database.php';

/** Skip-guards: return a skip reason string, or null to run the migration. */
$GUARDS = [
    '002_nutrient_standards.sql' => function ($pdo) {
        try {
            $exists = $pdo->query("SHOW TABLES LIKE 'nutrient_standards'")->fetchColumn();
            if ($exists && (int)$pdo->query('SELECT COUNT(*) FROM nutrient_standards')->fetchColumn() > 0) {
                return 'nutrient_standards already populated';
            }
        } catch (Exception $e) { /* run it */ }
        return null;
    },
    '003_nlp_eval_audit.sql' => function ($pdo) {
        try {
            $tableOk = (bool)$pdo->query("SHOW TABLES LIKE 'nlp_eval_runs'")->fetchColumn();
            $col = $pdo->query("SHOW COLUMNS FROM ai_extracted_symptoms LIKE 'mapping_version'")->fetchColumn();
            if ($tableOk && $col !== false) {
                return 'nlp_eval_runs exists and ai_extracted_symptoms already has mapping columns';
            }
        } catch (Exception $e) { /* run it */ }
        return null;
    },
    '004_symptom_lifecycle.sql' => function ($pdo) {
        try {
            $col = $pdo->query("SHOW COLUMNS FROM ai_extracted_symptoms LIKE 'status'")->fetchColumn();
            if ($col !== false) {
                return 'ai_extracted_symptoms already has lifecycle columns';
            }
        } catch (Exception $e) { /* run it */ }
        return null;
    },
];

$migration = $argv[1] ?? '002_nutrient_standards.sql';
$path = __DIR__ . '/' . $migration;
if (!is_file($path)) {
    fwrite(STDERR, "Migration not found: $path\n");
    exit(1);
}

$pdo = (new Database())->getConnection();

if (isset($GUARDS[$migration])) {
    $skipReason = ($GUARDS[$migration])($pdo);
    if ($skipReason !== null) {
        echo "[skip] $migration: $skipReason\n";
        exit(0);
    }
}

// Strip comments (full-line and inline "-- ..."), then split on statement
// terminators. Note: SQL string literals must not contain "--" or ";".
$lines = array_filter(explode("\n", file_get_contents($path)), function ($l) {
    return strpos(ltrim($l), '--') !== 0;
});
$lines = array_map(function ($l) {
    return preg_replace('/\s--.*$/', '', $l);
}, $lines);
$statements = array_filter(array_map('trim', explode(';', implode("\n", $lines))));

foreach ($statements as $i => $st) {
    try {
        $pdo->exec($st);
        echo "[ok] statement " . ($i + 1) . "\n";
    } catch (PDOException $e) {
        fwrite(STDERR, "[fail] " . substr(preg_replace('/\s+/', ' ', $st), 0, 90) . " ... : " . $e->getMessage() . "\n");
        exit(1);
    }
}
echo "[done] applied $migration\n";
