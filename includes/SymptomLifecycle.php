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

class SymptomLifecycle
{
    /**
     * Resolution trigger keywords. A message resolves a symptom when it
     * contains BOTH a positive/absence marker AND (optionally) the symptom
     * name or a pronoun referring to it. Matching is case-insensitive
     * substring based — deliberately simple and auditable.
     * Tagalog/Taglish terms normalized: "hindi na ako pagod" = "no longer tired".
     */
    private const RESOLVE_POSITIVES = [
        'feeling better', 'feel better', 'feels better', 'felt better',
        'feeling good', 'feel good', 'feeling great', 'feel great',
        'much better', 'a lot better', 'so much better', 'way better',
        'all better', 'all good now', 'okay na ako', 'ok na ako',
        'gumaan na', 'lumakas na ako', 'bumuti na', 'maganda na ang pakiramdam',
        'maayos na ang pakiramdam', 'ayos na ako', 'wala na ang',
        'wala na yung', 'wala na ung', 'salamat, wala na', 'gone away',
        'not anymore', 'no more', 'no longer', 'hindi na ako', 'hindi na masakit',
        'hindi na ganun', 'di na ako', 'di na masakit', 'nawala na ang',
        'nawala na yung', 'hindi na siya', 'resolved', 'recovered from',
        'no longer have', 'do not have', "don't have", 'dint have',
        'curado na', 'lumipas na',
    ];

    /** Negation guards — a message containing these stays unresolved. */
    private const NEGATION_MARKERS = [
        'not feeling better', 'does not feel better', "doesn't feel better",
        'still', 'masakit pa rin', 'pumapasok pa rin', 'ganun pa rin',
        'lalo pang sumakit', 'nagiging masama', 'getting worse', 'worse than',
        'hindi pa rin', 'hindi pa ako', 'di pa ako',
    ];

    /** Short fallback pronouns that can stand in for a symptom name. */
    private const REFERRING_TERMS = ['it', 'them', 'iyan', 'yun', 'siya'];

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
     *
     * Rules:
     *  - The message must contain a positive/absence marker.
     *  - The message must NOT contain a negation guard ("still hurting").
     *  - If any symptom keyword (or a matching nutrient) is mentioned, only
     *    those symptoms resolve; otherwise (generic "feeling better today!")
     *    ALL of the user's active symptoms resolve — the user said they
     *    improved, the card should reflect that immediately.
     */
    private static function resolveMentionedSymptoms($userId, $messageId, $text)
    {
        $hasPositive = false;
        foreach (self::RESOLVE_POSITIVES as $marker) {
            if (strpos($text, $marker) !== false) {
                $hasPositive = true;
                break;
            }
        }
        if (!$hasPositive) {
            return;
        }

        foreach (self::NEGATION_MARKERS as $neg) {
            if (strpos($text, $neg) !== false) {
                return; // "still fatigued" — do not resolve anything
            }
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

        // Which symptoms does this message explicitly mention?
        $mentionedIds = [];
        foreach ($rows as $row) {
            $kw = mb_strtolower(trim((string)$row['symptom_keyword']));
            if ($kw !== '' && strpos($text, $kw) !== false) {
                $mentionedIds[] = (int)$row['id'];
                continue;
            }
            // Also accept nutrient references ("iron levels are better now")
            $nutrient = mb_strtolower(trim((string)$row['mapped_nutrient']));
            if ($nutrient !== '' && strpos($text, explode(',', $nutrient)[0]) !== false) {
                $mentionedIds[] = (int)$row['id'];
            }
        }

        // No explicit mention + positive marker => generic improvement, resolve all.
        if (empty($mentionedIds)) {
            foreach (self::REFERRING_TERMS as $term) {
                if (strpos($text, $term) !== false) {
                    break;
                }
            }
            foreach ($rows as $row) {
                $mentionedIds[] = (int)$row['id'];
            }
        }

        $upd = $pdo->prepare(
            "UPDATE ai_extracted_symptoms
             SET status = 'resolved', resolved_at = NOW(), resolved_message_id = ?
             WHERE id IN (" . implode(',', array_fill(0, count($mentionedIds), '?')) . ")
               AND status = 'active'"
        );
        $params = array_merge([$messageId], $mentionedIds);
        $upd->execute($params);
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
    public static function getActiveInsights($pdo, $userId, $limit = 5)
    {
        $out = ['symptoms' => [], 'active_count' => 0];
        try {
            $stmt = $pdo->prepare(
                "SELECT symptom_keyword, MAX(mapped_nutrient) AS nutrient,
                        MAX(created_at) AS last_seen,
                        MIN(created_at) AS first_seen,
                        COUNT(*) AS occurrences
                 FROM ai_extracted_symptoms
                 WHERE user_id = ? AND status = 'active'
                 GROUP BY symptom_keyword
                 ORDER BY last_seen DESC
                 LIMIT " . (int)$limit
            );
            $stmt->execute([$userId]);
            $out['symptoms'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $c = $pdo->prepare(
                "SELECT COUNT(DISTINCT symptom_keyword) AS n
                 FROM ai_extracted_symptoms WHERE user_id = ? AND status = 'active'"
            );
            $c->execute([$userId]);
            $out['active_count'] = (int)($c->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            error_log('SymptomLifecycle getActiveInsights failed: ' . $e->getMessage());
        }
        return $out;
    }
}
