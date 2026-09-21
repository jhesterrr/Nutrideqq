<?php
// includes/SymptomMapper.php
//
// Loads the versioned symptom-to-nutrient mapping (config/symptom_nutrient_mapping.json)
// and maps LLM-extracted symptom text to nutrient keys.
//
// Design notes:
//  * The mapping is DATA, not code. This class only implements the matching
//    algorithm, so the table can be versioned, diffed, cited in the research
//    paper, and updated without touching application logic.
//  * Matching is deterministic: classes in array order, first keyword hit wins.
//    Keyword order inside a class does not matter.
//  * Backward compatible: mapsSymptomToNutrient() preserves the original
//    ai_service.php chain semantics exactly (same order, same substring rules)
//    for the English keywords that were previously hardcoded.

require_once __DIR__ . '/../database.php';

class SymptomMapper
{
    /** @var array|null cached parsed mapping document */
    private static $doc = null;
    /** @var string|null last error while loading */
    private static $loadError = null;

    private function __construct() {}

    /** Load (once) and validate the mapping document. */
    private static function doc()
    {
        if (self::$doc !== null) return self::$doc;
        self::$doc = false; // guard against loops

        $path = __DIR__ . '/../config/symptom_nutrient_mapping.json';
        if (!is_file($path)) {
            self::$loadError = 'Mapping table not found: ' . $path;
            return false;
        }
        $parsed = json_decode(file_get_contents($path), true);
        if (!is_array($parsed) || empty($parsed['classes']) || !is_array($parsed['classes'])) {
            self::$loadError = 'Mapping table invalid (missing classes array)';
            return false;
        }
        foreach ($parsed['classes'] as $i => $c) {
            if (empty($c['class_id'])) {
                self::$loadError = "Mapping table invalid: class #$i missing class_id";
                return false;
            }
            if (!array_key_exists('nutrient_name', $c)) {
                self::$loadError = "Mapping table invalid: {$c['class_id']} missing nutrient_name";
                return false;
            }
        }
        self::$doc = $parsed;
        return self::$doc;
    }

    /** True when the mapping table loaded and validated. */
    public static function isAvailable()
    {
        return self::doc() !== false;
    }

    /** Load error, or null. */
    public static function getLoadError()
    {
        return self::$loadError;
    }

    /** Mapping version string, or null when unavailable. */
    public static function getVersion()
    {
        $doc = self::doc();
        return $doc !== false ? ($doc['version'] ?? null) : null;
    }

    /** Full mapping document (for audit output). */
    public static function getDocument()
    {
        $doc = self::doc();
        return $doc !== false ? $doc : null;
    }

    /** Provenance summary for API audit blocks and evaluation reports. */
    public static function auditInfo()
    {
        $doc = self::doc();
        if ($doc === false) {
            return ['available' => false, 'error' => self::$loadError];
        }
        return [
            'available' => true,
            'version' => $doc['version'] ?? null,
            'released' => $doc['released'] ?? null,
            'citation' => $doc['citation'] ?? null,
            'class_count' => count($doc['classes']),
        ];
    }

    /**
     * Map one extracted symptom string to a nutrient name (or null when it
     * matches no class). Deterministic first-match over the class order.
     */
    public static function map($symptomText)
    {
        $doc = self::doc();
        if ($doc === false) return null;

        $lower = mb_strtolower(trim((string)$symptomText), 'UTF-8');
        if ($lower === '') return null;

        foreach ($doc['classes'] as $class) {
            if (empty($class['keywords']) || !is_array($class['keywords'])) continue;
            foreach ($class['keywords'] as $kw) {
                if ($kw !== '' && str_contains($lower, mb_strtolower($kw, 'UTF-8'))) {
                    return $class['nutrient_name']; // may be null by design (explicit no-map)
                }
            }
        }
        return null;
    }

    /** class_id that matched, or null. (Used by the harness for per-class P/R.) */
    public static function mapWithClass($symptomText)
    {
        $doc = self::doc();
        if ($doc === false) return [null, null];

        $lower = mb_strtolower(trim((string)$symptomText), 'UTF-8');
        if ($lower === '') return [null, null];

        foreach ($doc['classes'] as $class) {
            if (empty($class['keywords']) || !is_array($class['keywords'])) continue;
            foreach ($class['keywords'] as $kw) {
                if ($kw !== '' && str_contains($lower, mb_strtolower($kw, 'UTF-8'))) {
                    return [$class['nutrient_name'], $class['class_id']];
                }
            }
        }
        return [null, null];
    }
}
