<?php
// tests/test_nlp_end_to_end.php
//
// Offline (mock) + live end-to-end check of the bilingual + text-speak NLP
// pipeline: TextNormalizer -> Groq extraction -> SymptomMapper.
//
// Usage:
//   C:\xampp\php\php.exe tests\test_nlp_end_to_end.php          (offline / mapper-only)
//   C:\xampp\php\php.exe tests\test_nlp_end_to_end.php --live   (real Groq calls)

require_once __DIR__ . '/../includes/TextNormalizer.php';
require_once __DIR__ . '/../api/ai_service.php';
require_once __DIR__ . '/../includes/SymptomMapper.php';

useStrict(false);
$live = in_array('--live', $argv ?? [], true);
$gold = require __DIR__ . '/../dev-tools/gold_set.php';

$pass = 0; $fail = 0; $failures = [];

foreach ($gold['cases'] as $case) {
    $expected = $case['expected'];
    $raw = $case['message'];

    // Production path: normalize FIRST, then extract + map.
    $normalized = TextNormalizer::normalize($raw);

    if ($live) {
        $result = callGroqLive($normalized);
        $symptoms = is_array($result) ? array_values($result['symptoms'] ?? []) : [];
    } else {
        // Offline: run the PRODUCTION message-level matcher over the
        // normalized text. SymptomMapper::matchText() applies negation
        // handling + longest-match precedence + keyword extraction — the
        // same deterministic core production uses for mapping, so this is a
        // valid smoke test of normalization + v1.3 matching.
        $symptoms = [$normalized];
    }

    $got = [];
    if (!$live) {
        foreach (SymptomMapper::matchText($normalized) as $w) {
            if (!in_array($w['class_id'], $got, true)) $got[] = $w['class_id'];
        }
    } else {
        foreach ($symptoms as $s) {
            // New schema: objects {label, evidence, ...}; accept plain strings too.
            $label = is_array($s) ? ($s['label'] ?? $s['symptom'] ?? '') : $s;
            [, $classId] = SymptomMapper::mapWithClass($label);
            if ($classId !== null && !in_array($classId, $got, true)) {
                $got[] = $classId;
            }
        }
    }
    sort($expected); sort($got);

    if ($expected === $got) {
        $pass++;
    } else {
        $fail++;
        $failures[] = [$raw, $normalized, $expected, $got];
    }
}

echo "Mode: ", $live ? "LIVE (Groq)" : "OFFLINE (mock)", PHP_EOL;
echo "PASS: {$pass} / ", $pass + $fail, PHP_EOL;

if ($fail > 0) {
    echo PHP_EOL, "FAILURES ({$fail}):", PHP_EOL;
    foreach (array_slice($failures, 0, 15) as [$raw, $norm, $exp, $got]) {
        echo "  msg:    ", $raw, PHP_EOL;
        echo "  norm:   ", $norm, PHP_EOL;
        echo "  expect: ", implode(',', $exp), "   got: ", implode(',', $got) ?: '(none)', PHP_EOL, PHP_EOL;
    }
}
exit($fail === 0 ? 0 : 1);

// ── helpers ──────────────────────────────────────────────────────────────

function useStrict(bool $b) { /* placeholder for future strict flag */ }

function callGroqLive(string $message): ?array
{
    $apiKey = getenv('GROQ_API_KEY');
    if (!$apiKey) {
        fwrite(STDERR, "GROQ_API_KEY not set; cannot run --live\n");
        exit(2);
    }
    $data = [
        'model' => 'openai/gpt-oss-20b',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a clinical NLP engine. Always respond with valid JSON only. No markdown. No explanation. Do not output your reasoning.'],
            ['role' => 'user', 'content' => NutriDeqAI::buildExtractionPrompt($message)],
        ],
        'temperature' => 0.1,
        'max_tokens' => 1024,
    ];
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    $decoded = json_decode((string)$resp, true);
    $content = $decoded['choices'][0]['message']['content'] ?? null;
    if (!$content) return null;
    $parsed = json_decode($content, true);
    if (!is_array($parsed) && preg_match('/\{.*\}/s', $content, $m)) {
        $parsed = json_decode($m[0], true);
    }
    return is_array($parsed) ? $parsed : null;
}
