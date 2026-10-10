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
//  * v1.3.0 matcher semantics (matchText core, shared by every entry point):
//      - NEGATION AWARE  — a keyword match inside the scope of a negation cue
//        ("no headache", "hindi na ako napagod", "wala nang lagnat") is
//        suppressed, so messages that DENY a symptom never map to it, even
//        when the LLM over-extracts the bare symptom word.
//      - LONGEST MATCH PRECEDENCE — when one keyword occurrence is contained
//        inside a longer keyword from another class ("kabag sa kalamnan" vs
//        "kabag"), the longer (more specific) class wins and the shorter
//        class is not double-fired.
//      - Keyword occurrences that ARE a negation cue ("walang lakas") win
//        over the cue itself, so genuine keywords are never self-suppressed.
//  * Backward compatible: mapsSymptomToNutrient() preserves the original
//    ai_service.php chain semantics exactly (same order, same substring rules)
//    for the English keywords that were previously hardcoded.

require_once __DIR__ . '/../database.php';

class SymptomMapper
{
    /** Negation cues (EN + TL). Short tagalog "di" only matches the bare word. */
    private const NEGATION_RE = '/\b(?:no|not|never|without|none|nope|don\'?t|doesn\'?t|didn\'?t|won\'?t|can\'?t|cannot|hindi|hnd|nde|di|wala|walang)\b/u';

    /** Contrast / boundary cues that END a negation scope. */
    private const CONTRAST_RE = '/\b(?:but|however|though|although|pero|ngunit)\b|[.,;!?]/u';

    /** How many characters after a negation cue remain negated (no contrast cue). */
    private const NEGATION_SCOPE_CHARS = 40;

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
     * matches no class). Deterministic first-match over the class order,
     * negation-aware and longest-match-aware (see matchText).
     */
    public static function map($symptomText)
    {
        [$nutrient] = self::mapWithClass($symptomText);
        return $nutrient;
    }

    /**
     * [nutrient, class_id] of the best match for one symptom string, or
     * [null, null]. "Best" = first winning class in class-array order after
     * negation suppression and longest-match resolution.
     * (Used by production mapping and both evaluation harnesses for P/R.)
     */
    public static function mapWithClass($symptomText)
    {
        $winners = self::matchText((string)$symptomText);
        if (empty($winners)) return [null, null];
        return [$winners[0]['nutrient'], $winners[0]['class_id']]; // may be null by design (explicit no-map)
    }

    /**
     * Canonicalize ONE extracted symptom string into a single stable record
     * {label, class_id, nutrient}. Every synonym of a clinical class collapses
     * to that class's canonical_label, so the insights card shows ONE row per
     * clinical pattern instead of one row per synonym:
     *   "fatigue" / "tired" / "exhausted"  -> {label: "Fatigue", SC-FATIGUE, Iron}
     *   "pulikat"  / "cramp" / "spasm"      -> {label: "Muscle cramps", SC-CRAMP, Potassium, K}
     *   "fever"                            -> {label: "Fever", SC-NO-MAP, null}
     *   unmatched free text                -> {label: Title-cased raw, null, null}
     * The label is what gets stored as symptom_keyword, so canonicalization at
     * insertion time makes the DB self-deduplicating going forward.
     */
    public static function canonicalize($symptomText): array
    {
        $winners = self::matchText((string)$symptomText);
        if (empty($winners)) {
            return ['label' => self::title((string)$symptomText), 'class_id' => null, 'nutrient' => null];
        }
        $w = $winners[0];
        return ['label' => self::labelForMatch($w), 'class_id' => $w['class_id'], 'nutrient' => $w['nutrient']];
    }

    /**
     * Stable dedupe key for a canonicalize() record. Clinical classes collapse
     * by class_id; SC-NO-MAP and unmatched text stay distinct per phrase so
     * different review symptoms never hide each other.
     */
    public static function canonicalKey(array $canon): string
    {
        $classId = $canon['class_id'] ?? null;
        if ($classId === null) {
            return 'RAW|' . mb_strtolower((string)($canon['label'] ?? ''));
        }
        if ($classId === 'SC-NO-MAP') {
            return 'SC-NO-MAP|' . mb_strtolower((string)($canon['label'] ?? ''));
        }
        return (string)$classId;
    }

    /** Canonical display label for one winning keyword match. */
    private static function labelForMatch(array $match): string
    {
        if ($match['class_id'] !== 'SC-NO-MAP') {
            $doc = self::doc();
            if ($doc !== false) {
                foreach ($doc['classes'] as $c) {
                    if ($c['class_id'] === $match['class_id'] && !empty($c['canonical_label'])) {
                        return $c['canonical_label'];
                    }
                }
            }
        }
        // SC-NO-MAP (or a class without a canonical_label): keep the specific
        // matched keyword so distinct review symptoms stay legible.
        return self::title((string)$match['keyword']);
    }

    /** Title-case the first character, preserving the rest. */
    private static function title(string $s): string
    {
        $s = trim($s);
        if ($s === '') return $s;
        return mb_strtoupper(mb_substr($s, 0, 1), 'UTF-8') . mb_substr($s, 1, null, 'UTF-8');
    }

    /**
     * Resolve EVERY matched class in a free text (message-level matching).
     * Returns winning matches deduplicated by class_id, ordered by class-array
     * order. This is the entry point for message-level offline evaluation and
     * any caller that needs the full class set rather than the first hit.
     *
     * @return array[] each: {class_id, nutrient, keyword, start, end}
     */
    public static function matchText($text): array
    {
        $doc = self::doc();
        if ($doc === false) return [];

        $lower = mb_strtolower(trim((string)$text), 'UTF-8');
        if ($lower === '') return [];

        $matches = self::collectKeywordMatches($lower, $doc);
        if (empty($matches)) return [];

        $matches = self::dropNegated($lower, $matches);
        if (empty($matches)) return [];

        $winners = self::resolveContainment($matches);

        // Dedupe by class_id, preserving class-array order.
        $order = [];
        foreach ($doc['classes'] as $i => $c) $order[$c['class_id']] = $i;
        usort($winners, fn($a, $b) => $order[$a['class_id']] <=> $order[$b['class_id']]);
        $seen = [];
        $out = [];
        foreach ($winners as $w) {
            // Unmapped review symptoms still need to stack independently;
            // otherwise one SC-NO-MAP keyword would hide every other symptom
            // in the same message. Clinical classes remain deduplicated.
            $dedupeKey = $w['class_id'] === 'SC-NO-MAP'
                ? $w['class_id'] . '|' . $w['keyword']
                : $w['class_id'];
            if (isset($seen[$dedupeKey])) continue;
            $seen[$dedupeKey] = true;
            $out[] = $w;
        }
        return $out;
    }

    /**
     * Production safety net against LLM over-extraction: true when EVERY
     * occurrence of $classId's keywords in the client's message sits inside a
     * negation scope ("no headache", "hindi na ako napagod"). When the class
     * is not mentioned at all, returns false — the LLM's paraphrase stands.
     *
     * Callers pass the SAME normalized text the extraction model saw.
     */
    public static function classIsNegatedInMessage(string $messageText, string $classId): bool
    {
        $doc = self::doc();
        if ($doc === false) return false;

        $lower = mb_strtolower(trim($messageText), 'UTF-8');
        if ($lower === '') return false;

        $all = self::collectKeywordMatches($lower, $doc);
        $classMentioned = false;
        foreach ($all as $m) {
            if ($m['class_id'] === $classId) { $classMentioned = true; break; }
        }
        if (!$classMentioned) return false;

        $survivors = self::dropNegated($lower, $all);
        foreach ($survivors as $m) {
            if ($m['class_id'] === $classId) return false; // at least one survives
        }
        return true; // every occurrence is negated
    }

    /** All keyword occurrences in $lower as byte-span matches. */
    private static function collectKeywordMatches(string $lower, array $doc): array
    {
        $matches = [];
        foreach ($doc['classes'] as $class) {
            if (empty($class['keywords']) || !is_array($class['keywords'])) continue;
            foreach ($class['keywords'] as $kw) {
                $kw = mb_strtolower(trim((string)$kw), 'UTF-8');
                if ($kw === '') continue;
                $offset = 0;
                // Byte-level strpos is safe on valid UTF-8: ASCII keyword bytes
                // can never match mid-codepoint (continuation bytes are >= 0x80).
                while (($pos = strpos($lower, $kw, $offset)) !== false) {
                    $matches[] = [
                        'class_id' => $class['class_id'],
                        'nutrient' => $class['nutrient_name'] ?? null,
                        'keyword'  => $kw,
                        'start'    => $pos,
                        'end'      => $pos + strlen($kw),
                    ];
                    $offset = $pos + 1;
                }
            }
        }
        return $matches;
    }

    /** Remove matches that fall inside a negation scope. */
    private static function dropNegated(string $lower, array $matches): array
    {
        $scopes = [];
        if (preg_match_all(self::NEGATION_RE, $lower, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as [$cue, $cueStart]) {
                $cueEnd = $cueStart + strlen($cue);
                // A cue INSIDE a genuine keyword ("walang" in "walang lakas")
                // must not suppress that keyword — the keyword wins.
                $insideKeyword = false;
                foreach ($matches as $km) {
                    if ($cueStart < $km['end'] && $km['start'] < $cueEnd) {
                        $insideKeyword = true;
                        break;
                    }
                }
                if ($insideKeyword) continue;

                $scopeEnd = $cueEnd + self::NEGATION_SCOPE_CHARS;
                if (preg_match(self::CONTRAST_RE, $lower, $cm, PREG_OFFSET_CAPTURE, $cueEnd)) {
                    $scopeEnd = min($scopeEnd, (int)$cm[0][1]); // contrast ends the scope
                }
                $scopes[] = ['start' => $cueStart, 'end' => $scopeEnd];
            }
        }
        if (empty($scopes)) return $matches;

        return array_values(array_filter($matches, function ($km) use ($scopes) {
            foreach ($scopes as $s) {
                if ($km['start'] < $s['end'] && $s['start'] < $km['end']) return false; // negated
            }
            return true;
        }));
    }

    /**
     * Longest-match precedence: drop any match whose span is contained in a
     * longer match from a DIFFERENT class ("kabag" ⊂ "kabag sa kalamnan").
     * Returns survivors sorted longest-keyword-first.
     */
    private static function resolveContainment(array $matches): array
    {
        usort($matches, function ($a, $b) {
            $lenB = $b['end'] - $b['start'];
            $lenA = $a['end'] - $a['start'];
            return $lenB <=> $lenA;
        });
        $winners = [];
        foreach ($matches as $km) {
            $contained = false;
            foreach ($winners as $w) {
                if ($w['class_id'] !== $km['class_id']
                    && $km['start'] >= $w['start'] && $km['end'] <= $w['end']) {
                    $contained = true;
                    break;
                }
            }
            if (!$contained) $winners[] = $km;
        }
        return $winners;
    }
}
