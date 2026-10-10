<?php
// tests/test_text_normalizer.php — quick correctness checks for TextNormalizer.
// Run: C:\xampp\php\php.exe tests\test_text_normalizer.php

require_once __DIR__ . '/../includes/TextNormalizer.php';

$cases = [
    // [input, expected_substring] — canonical words must appear after normalize
    ['mskit chan q',                 'masakit tiyan ko'],
    ['mskt tyan q grbe',             'masakit tiyan ko grabe'],
    ['ang skt ng ulo q',             'ang sakit ng ulo ko'],
    ['pgd n q',                      'pagod na ko'],
    ['pgd n q tlga',                 'pagod na ko talaga'],
    ['wla lkas aq',                  'walang lakas ako'],
    ['hlo nman q',                   'hilo naman ko'],
    ['nhhlo q kpg tmtayo',           null], // fuzzy: nahihilo (loose) — must not crash
    ['plkt n nman bnti q',           null], // pulikat fuzzy
    ['sska n q kpg kain q',          null], // susuka fuzzy
    ['I feel fatigued and dizzy',    'feel fatigued and dizzy'], // English passes through (lowercased)
    ['im feeling better now my fatigue is gone', 'im feeling better now my fatigue is gone'],
];

$pass = 0; $fail = 0;
foreach ($cases as [$input, $expect]) {
    $out = TextNormalizer::normalize($input);
    if ($expect === null) {
        echo "  ~  ", str_pad($input, 42), " => ", $out, PHP_EOL;
        continue;
    }
    if (strpos($out, $expect) !== false) {
        echo "  ✔  ", str_pad($input, 42), " => ", $out, PHP_EOL;
        $pass++;
    } else {
        echo "  ✘  ", str_pad($input, 42), " => ", $out, "   (expected: {$expect})", PHP_EOL;
        $fail++;
    }
}
echo PHP_EOL, "PASS: {$pass}  FAIL: {$fail}", PHP_EOL;
exit($fail === 0 ? 0 : 1);
