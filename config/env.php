<?php
/**
 * config/env.php — minimal .env loader for shared hosting (e.g. Hostinger),
 * where machine-level environment variables cannot be set from a dashboard.
 *
 * Loads legacy-php/.env into PHP's environment (getenv/$_ENV) ONLY for keys
 * that are not already defined, so real environment variables (Docker, Render,
 * CLI exports) always take precedence. Idempotent, dependency-free.
 */
if (!defined('NUTRIDEQ_ENV_LOADED')) {
    define('NUTRIDEQ_ENV_LOADED', true);

    $ndEnvFile = dirname(__DIR__) . '/.env'; // legacy-php/.env
    if (is_file($ndEnvFile) && is_readable($ndEnvFile)) {
        $ndLines = file($ndEnvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
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
            if ($ndKey === '' || getenv($ndKey) !== false || isset($_ENV[$ndKey])) {
                continue; // never override an already-defined variable
            }
            $_ENV[$ndKey] = $ndValue;
            putenv("$ndKey=$ndValue");
        }
    }
    unset($ndEnvFile, $ndLines, $ndLine, $ndKey, $ndValue, $ndLen);
}
