<?php
/**
 * includes/SymptomLifecycle.php
 *
 * Real-time symptom lifecycle for the Wellness Insights card.
 *
 * Every client chat message runs through two passes:
 *   1. DETECT  — new symptoms get inserted as active rows (existing behavior,
 *                done by NutriDeqAI::analyzeMessage via the Groq NLP engine).
 *   2. RESOLVE — open symptoms are closed when the client reports feeling
 *                better ("my fatigue is gone", "hindi na ako pagod").
 *
 * The result: the Wellness Insights card mirrors what the client is saying
 * RIGHT NOW, in both the client and dietitian views, updated in real time.
 *
 * Design notes:
 *  - Rows are never deleted; resolution is a status change so the full
 *    clinical trail (which message reported it, which message resolved it)
 *    stays intact and auditable.
 *  - Resolution uses a bilingual keyword engine (EN/Tagalog/Taglish) instead
 *    of a second Groq round-trip: it is instant (same request as the send),
 *    free, deterministic and testable. The Groq NLP still powers DETECTION.
 *  - All insights are EDUCATIONAL ONLY and never constitute a diagnosis.
 */

if (defined('NUTRIDEQ_SYMPTOM_LIFECYCLE')) {
    return;
}
define('NUTRIDEQ_SYMPTOM_LIFECYCLE', true);

require_once __DIR__ . '/SymptomMapper.php';

class SymptomLifecycle
{
    /**
     * Recovery markers. A symptom resolves only when the message BOTH carries
     * one of these AND names the symptom (reference-scoped), so a vague
     * "I feel good today" never clears an unrelated open symptom.
     * Bilingual EN / Tagalog / Taglish, case-insensitive substring match.
     */
    private const RESOLVE_POSITIVES = [
        'feeling better', 'feel better', 'feels better', 'felt better',
        'feeling good', 'feel good', 'feeling great', 'feel great',
        'much better', 'a lot better', 'so much better', 'way better',
        'all better', 'all good now', 'okay na ako', 'ok na ako',
        'gumaan na', 'lumakas na ako', 'bumuti na', 'maganda na ang pakiramdam',
        'maayos na ang pakiramdam', 'ayos na ako', 'wala na ang',
        'wala na yung', 'wala na ung', 'salamat, wala na', 'gone away',
        'is gone', 'are gone', 'gone now', 'went away',
        'not anymore', 'no more', 'no longer', 'hindi na ako', 'hindi na masakit',
        'hindi na ganun', 'di na ako', 'di na masakit', 'nawala na ang',
        'nawala na yung', 'hindi na siya', 'resolved', 'recovered from',
        'no longer have', 'do not have', "don't have", 'dint have',
        'curado na', 'lumipas na',
    ];

    /**
     * Persistence markers — when one sits NEAR the named symptom the client is
     * saying it is STILL there, so that symptom stays unresolved. Positional
     * (window) rather than global: an unrelated "still" no longer blocks
     * resolution of a symptom the client just said was gone.
     */
    private const PERSISTENCE_RE = '/\b(?:still|pa|pa\s+rin|pa\s+din|hindi\s+pa|di\s+pa|lalo|worse|worsening|sumasakit\s+pa|tuloy\s+pa|ganun\s+pa|ganon\s+pa|pumapasok\s+pa)\b/u';

    /** Negation cue that, immediately before a recovery marker, cancels it. */
    private const NEGATED_POSITIVE_RE = '/\b(?:not|no|never|hindi|di|nde)\s+$/u';

    /**
     * Statuses the LLM may return that mean "this symptom is no longer active".
     * Conservative: a symptom only closes on a CLEAR improvement/absence, and
     * only above the confidence floor (see resolutionsToApply).
     */
    private const LLM_RESOLVE_STATUSES = ['resolved', 'improved', 'better', 'gone'];

    /** Minimum model confidence before an LLM resolution is trusted. */
    private const LLM_RESOLVE_MIN_CONFIDENCE = 0.6;

    /**
     * Run the full lifecycle for one client message.
     * Called from handlers/send_message_ajax.php right after NutriDeqAI
     * detection. Failures are swallowed into the error log — the chat
     * message send must never break because of insight bookkeeping.
     *
     * @param int    $userId      The client's users.id (NLP subject)
     * @param int    $messageId   wellness_messages.id of this message
     * @param string $messageText Raw chat text (already trimmed)
     * @return void
     */
    public static function process($userId, $messageId, $messageText)
    {
        try {
            // Text-speak first: "wla n q skt chan q" must resolve like
            // "wala na q sakit tiyan ko". The NLP adjusts to the user.
            if (class_exists('TextNormalizer')) {
                $messageText = TextNormalizer::normalize($messageText);
            }
            $text = mb_strtolower(trim((string)$messageText));
            if ($text === '' || (int)$userId <= 0) {
                return;
            }
            self::resolveMentionedSymptoms($userId, (int)$messageId, $text);
        } catch (Throwable $e) {
            error_log('SymptomLifecycle process failed: ' . $e->getMessage());
        }
    }

    /**
     * Pass 2 — RESOLVE: close active symptoms the message reports as gone.
     * Reference-scoped and conservative: recovery language alone resolves
     * nothing; the message must also NAME the symptom (by any keyword of its
     * clinical class, bilingual).
     */
    private static function resolveMentionedSymptoms($userId, $messageId, $text)
    {
        if (!self::hasRecoveryMarker($text)) {
            return;
        }

        $pdo = (new Database())->getConnection();

        $active = $pdo->prepare(
            "SELECT id, symptom_keyword, mapped_nutrient FROM ai_extracted_symptoms
             WHERE user_id = ? AND status = 'active'"
        );
        $active->execute([$userId]);
        $rows = $active->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            return;
        }

        $ids = self::resolveTargets($text, $rows);
        if (empty($ids)) {
            return; // recovery language but nothing specific named — stay conservative
        }

        $upd = $pdo->prepare(
            "UPDATE ai_extracted_symptoms
             SET status = 'resolved', resolved_at = NOW(), resolved_message_id = ?
             WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
               AND status = 'active'"
        );
        $upd->execute(array_merge([$messageId], $ids));
    }

    /**
     * PURE resolver (no DB, unit-testable) — which of the given active
     * symptoms does this message report as gone?
     *
     * A symptom resolves when the text (already normalized) BOTH carries a
     * recovery marker AND names that symptom (the stored keyword, its
     * canonical label, or ANY keyword of the same clinical class — so "pagod"
     * resolves an English "Fatigue" row), and no persistence marker ("still",
     * "pa rin", "di pa") sits next to that mention.
     *
     * @param string $text   Normalized, lowercased client message
     * @param array  $active Rows: {id, symptom_keyword, mapped_nutrient?}
     * @return int[] ids to resolve
     */
    public static function resolveTargets(string $text, array $active): array
    {
        $text = mb_strtolower(trim($text));
        if ($text === '' || !self::hasRecoveryMarker($text)) {
            return [];
        }

        $targets = [];
        foreach ($active as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) continue;
            foreach (self::termsForRow((string)($row['symptom_keyword'] ?? '')) as $term) {
                if (mb_strlen($term) < 3) continue;
                $pattern = '/\b' . preg_quote($term, '/') . '\b/u';
                if (!preg_match_all($pattern, $text, $m, PREG_OFFSET_CAPTURE)) continue;
                foreach ($m[0] as [$mText, $bytePos]) {
                    // Byte -> character offset so the persistence window is correct
                    // even after multi-byte characters (emoji, accented text).
                    $charPos = mb_strlen(substr($text, 0, $bytePos), 'UTF-8');
                    if (!self::hasPersistenceNear($text, $charPos, mb_strlen($mText))) {
                        $targets[] = $id;
                        continue 2; // this symptom is named and not persisted — done
                    }
                }
            }
        }
        return array_values(array_unique($targets));
    }

    /** True when the text carries a recovery marker that is not itself negated. */
    private static function hasRecoveryMarker(string $text): bool
    {
        foreach (self::RESOLVE_POSITIVES as $marker) {
            $offset = 0;
            while (($pos = mb_strpos($text, $marker, $offset)) !== false) {
                $before = mb_substr($text, max(0, $pos - 12), min(12, $pos));
                if (!preg_match(self::NEGATED_POSITIVE_RE, $before)) {
                    return true;
                }
                $offset = $pos + 1;
            }
        }
        return false;
    }

    /** True when a persistence marker sits within a short window of a mention. */
    private static function hasPersistenceNear(string $text, int $pos, int $len): bool
    {
        $start = max(0, $pos - 24);
        $end = min(mb_strlen($text), $pos + $len + 24);
        $window = mb_substr($text, $start, $end - $start);
        return (bool)preg_match(self::PERSISTENCE_RE, $window);
    }

    /**
     * All lowercased terms that identify a stored symptom: its own keyword,
     * its canonical label, and every keyword of its clinical class (EN/TL/BIS).
     * For SC-NO-MAP / unmatched rows only the specific keyword is used.
     */
    private static function termsForRow(string $keyword): array
    {
        $keyword = mb_strtolower(trim($keyword));
        $terms = $keyword !== '' ? [$keyword] : [];
        $classId = SymptomMapper::canonicalize($keyword)['class_id'] ?? null;
        if ($classId !== null && $classId !== 'SC-NO-MAP') {
            $doc = SymptomMapper::getDocument();
            if (is_array($doc)) {
                foreach ($doc['classes'] as $c) {
                    if (($c['class_id'] ?? null) !== $classId) continue;
                    if (!empty($c['canonical_label'])) $terms[] = mb_strtolower($c['canonical_label']);
                    foreach ((array)($c['keywords'] ?? []) as $kw) {
                        $kw = mb_strtolower(trim((string)$kw));
                        if ($kw !== '') $terms[] = $kw;
                    }
                    break;
                }
            }
        }
        return array_values(array_unique($terms));
    }

    /**
     * Distinct canonical labels of the client's ACTIVE symptoms, for passing
     * into the LLM prompt as open-symptom context. Read-only, no side effects.
     */
    public static function activeLabels($pdo, $userId, int $limit = 20): array
    {
        $out = self::getActiveInsights($pdo, $userId, $limit);
        return array_values(array_filter(array_map(
            static fn($r) => trim((string)($r['symptom_keyword'] ?? '')),
            $out['symptoms'] ?? []
        )));
    }

    /**
     * PURE filter (unit-testable) — which symptoms the model says are gone, at
     * or above the confidence floor. Conservative: only clear 'resolved'/
     * 'improved' statuses count; 'persisting'/'unmentioned'/low-confidence are
     * ignored so a vague or uncertain judgement never closes a symptom.
     *
     * @param array $resolutions Model entries {symptom, status, confidence, ...}
     * @return string[] distinct symptom labels to resolve
     */
    public static function resolutionsToApply(array $resolutions, float $minConfidence = self::LLM_RESOLVE_MIN_CONFIDENCE): array
    {
        $labels = [];
        foreach ($resolutions as $r) {
            if (!is_array($r)) continue;
            $status = strtolower(trim((string)($r['status'] ?? '')));
            $label  = trim((string)($r['symptom'] ?? $r['label'] ?? ''));
            $conf   = isset($r['confidence']) ? (float)$r['confidence'] : 1.0;
            if ($label === '' || $conf < $minConfidence) continue;
            if (!in_array($status, self::LLM_RESOLVE_STATUSES, true)) continue;
            $labels[] = $label;
        }
        return array_values(array_unique($labels));
    }

    /**
     * Apply the LLM's resolution decisions. Maps each reported label to its
     * clinical class and closes EVERY active row of that class, so a Tagalog
     * label ("pagod") closes an English-labelled row ("Fatigue") and vice
     * versa. Returns the number of rows closed (0 when nothing qualifies).
     */
    public static function applyResolutions($pdo, $userId, $messageId, array $resolutions): int
    {
        $labels = self::resolutionsToApply($resolutions);
        if (empty($labels)) return 0;

        $stmt = $pdo->prepare(
            "SELECT id, symptom_keyword FROM ai_extracted_symptoms
             WHERE user_id = ? AND status = 'active'"
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$rows) return 0;

        $wantClasses = [];
        foreach ($labels as $lbl) {
            $wantClasses[SymptomMapper::canonicalKey(SymptomMapper::canonicalize($lbl))] = true;
        }

        $ids = [];
        foreach ($rows as $row) {
            $key = SymptomMapper::canonicalKey(SymptomMapper::canonicalize((string)$row['symptom_keyword']));
            if (isset($wantClasses[$key])) $ids[] = (int)$row['id'];
        }
        if (empty($ids)) return 0;

        $upd = $pdo->prepare(
            "UPDATE ai_extracted_symptoms
             SET status = 'resolved', resolved_at = NOW(), resolved_message_id = ?
             WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
               AND status = 'active'"
        );
        $upd->execute(array_merge([$messageId], $ids));
        return count($ids);
    }

    /**
     * Live card payload — aggregate of ACTIVE symptoms for the Wellness
     * Insights card, consumed by both client and dietitian pages.
     *
     * @param PDO  $pdo    Open connection
     * @param int  $userId Client's users.id
     * @param int  $limit  Max active symptoms returned (default 5)
     * @return array ['symptoms' => [[keyword, nutrient, last_seen, first_seen]], 'active_count' => int]
     */
    public static function getActiveInsights($pdo, $userId, $limit = 20)
    {
        $out = ['symptoms' => [], 'active_count' => 0];
        try {
            $stmt = $pdo->prepare(
                "SELECT symptom_keyword, mapped_nutrient, created_at
                 FROM ai_extracted_symptoms
                 WHERE user_id = ? AND status = 'active'
                 ORDER BY created_at DESC"
            );
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Collapse synonyms into ONE insight per clinical class, both for
            // new canonical rows and for legacy rows written before v1.5.0.
            // Rows are newest-first, so the first hit wins the label/nutrient
            // and last_seen; occurrences and first_seen are aggregated across
            // every row of the same class (e.g. "fatigue" + "tired" = Fatigue
            // ×2, last seen now).
            $merged = [];
            foreach ($rows as $row) {
                $canon = SymptomMapper::canonicalize((string)$row['symptom_keyword']);
                $key = SymptomMapper::canonicalKey($canon);
                $when = $row['created_at'];
                if (!isset($merged[$key])) {
                    $merged[$key] = [
                        'symptom_keyword' => $canon['label'],
                        'nutrient' => $canon['nutrient'] ?: ($row['mapped_nutrient'] ?: null),
                        'last_seen' => $when,
                        'first_seen' => $when,
                        'occurrences' => 1,
                    ];
                    continue;
                }
                $merged[$key]['occurrences']++;
                if ($when > $merged[$key]['last_seen']) $merged[$key]['last_seen'] = $when;
                if ($when < $merged[$key]['first_seen']) $merged[$key]['first_seen'] = $when;
                if (empty($merged[$key]['nutrient']) && !empty($row['mapped_nutrient'])) {
                    $merged[$key]['nutrient'] = $row['mapped_nutrient'];
                }
            }

            // Preserve newest-first ordering after the merge.
            usort($merged, static fn($a, $b) => strcmp((string)$b['last_seen'], (string)$a['last_seen']));
            $out['active_count'] = count($merged);
            $out['symptoms'] = array_slice(array_values($merged), 0, max(1, (int)$limit));
        } catch (Throwable $e) {
            error_log('SymptomLifecycle getActiveInsights failed: ' . $e->getMessage());
        }
        return $out;
    }
}
