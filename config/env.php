<?php
/**
 * config/env.php — minimal .env loader for shared hosting (e.g. Hostinger),
 * where machine-level environment variables cannot be set from a dashboard.
 *
 * Loads .env into PHP's environment (getenv/$_ENV) with this precedence:
 *   1. real environment variables (Docker, Render, CLI exports) — always win
 *   2. .env.local — machine-local overrides (git-ignored, never deployed)
 *   3. .env — the committed/production template
 * so the same checkout works on localhost (XAMPP) and on Hostinger.
 * Idempotent, dependency-free.
 */
if (!defined('NUTRIDEQ_ENV_LOADED')) {
    define('NUTRIDEQ_ENV_LOADED', true);

    $ndBaseDir = dirname(__DIR__); // repo root / public_html
    $ndMerged = [];
    // Later files override earlier ones; only real env vars are untouchable.
    foreach (['.env', '.env.local'] as $ndName) {
        $ndFile = $ndBaseDir . '/' . $ndName;
        if (!is_file($ndFile) || !is_readable($ndFile)) {
            continue;
        }
        $ndLines = file($ndFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($ndLines as $ndLine) {
            $ndLine = trim($ndLine);
            if ($ndLine === '' || $ndLine[0] === '#' || strpos($ndLine, '=') === false) {
                continue;
            }
            list($ndKey, $ndValue) = explode('=', $ndLine, 2);
            $ndKey = trim($ndKey);
            $ndValue = trim($ndValue);
            $ndLen = strlen($ndValue);
            if ($ndLen >= 2
                && (($ndValue[0] === '"' && $ndValue[$ndLen - 1] === '"')
                    || ($ndValue[0] === "'" && $ndValue[$ndLen - 1] === "'"))) {
                $ndValue = substr($ndValue, 1, -1);
            }
            if ($ndKey !== '') {
                $ndMerged[$ndKey] = $ndValue;
            }
        }
    }
    foreach ($ndMerged as $ndKey => $ndValue) {
        if (getenv($ndKey) !== false || isset($_ENV[$ndKey])) {
            continue; // never override a real environment variable
        }
        $_ENV[$ndKey] = $ndValue;
        putenv("$ndKey=$ndValue");
    }
    unset($ndBaseDir, $ndMerged, $ndName, $ndFile, $ndLines, $ndLine, $ndKey, $ndValue, $ndLen);
}
