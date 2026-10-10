<?php
// /api/ai_service.php
// Data source: symptom-to-nutrient mapping is versioned data
// (config/symptom_nutrient_mapping.json, applied by includes/SymptomMapper.php);
// nutrient keys reference the Philippine DOST-FNRI FCT (not USDA).
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/SymptomMapper.php';

class NutriDeqAI {
    private $apiKey;
    private $apiUrl = 'https://api.groq.com/openai/v1/chat/completions';
    private $model;
    private $pdo;

    public function __construct() {
        $this->apiKey = getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? null);
        if (!$this->apiKey) {
            throw new Exception('GROQ_API_KEY environment variable is not set');
        }
        // Model is configurable so a stronger multilingual model can be
        // A/B-tested without a code change (e.g. qwen/qwen3.8-27b, gpt-oss-120b).
        $this->model = getenv('GROQ_MODEL') ?: 'openai/gpt-oss-20b';
        $database = new Database();
        $this->pdo = $database->getConnection();
    }

    /**
     * Shared extraction prompt — the single source of the production prompt.
     * The evaluation harness (dev-tools/test_ai.php --live) reuses this so the
     * evaluated prompt is byte-identical to the one used in production.
     * Bilingual + text-speak aware: clients write English, Tagalog, Taglish
     * AND compressed shortcuts ("mskit chan q" = masakit tiyan ko). The
     * incoming text is already canonicalized by TextNormalizer, but the model
     * must still understand residual shortcuts it sees.
     */
    public static function buildExtractionPrompt($text, array $activeSymptoms = []) {
        // Open-symptom context: only present when the client already has active
        // symptoms, so the model can judge resolution in the SAME call.
        $activeBlock = '';
        if (!empty($activeSymptoms)) {
            $activeBlock = "\n\nOPEN SYMPTOMS ON RECORD FOR THIS CLIENT:\n- " . implode("\n- ", $activeSymptoms)
                . "\nFor EACH open symptom above, decide from THIS message ALONE whether it is now:\n"
                . "  \"resolved\"    = clearly gone or clearly improved (\"hindi na ako pagod\", \"my headache is gone\", \"okay na\")\n"
                . "  \"persisting\"  = still there or worse (\"masakit pa rin\", \"still hurting\", \"getting worse\")\n"
                . "  \"unmentioned\" = this message does not speak about it at all\n"
                . "Return a `resolutions` entry for EVERY open symptom listed. Use \"resolved\" ONLY when the client clearly reports it gone or improved; when unsure, use \"unmentioned\".";
        }
        return "You are the NutriDeq Clinical NLP Engine. Read and UNDERSTAND the WHOLE message — do not rely on spotting particular words. The client may write English, Tagalog, Taglish (mixed), or Filipino text-speak.
Return ONLY a valid JSON object with no markdown formatting, no explanation, no extra text.
Format:
{
  \"sentiment_score\": 5,
  \"burnout_flag\": false,
  \"symptoms\": [
    {\"label\": \"Fatigue\", \"evidence\": \"sobrang pagod\", \"language\": \"tl\", \"confidence\": 0.9}
  ],
  \"resolutions\": [
    {\"symptom\": \"Headache\", \"status\": \"resolved\", \"evidence\": \"wala na sakit ng ulo ko\", \"confidence\": 0.9}
  ]
}
Rules:
- sentiment_score: 1-10 (1=motivated/happy, 10=extremely stressed/exhausted/quitting)
- burnout_flag: true if sentiment_score >= 8
- symptoms: the physical symptoms the client actually reports. Each is an object {label, evidence, language, confidence}. Empty array [] if none.
  * \"label\" = canonical English label (use the preferred list below when it fits; if the symptom is NOT in the list, output a short, clear English noun phrase for it — NEVER drop a real symptom just because it is unlisted).
  * \"evidence\" = the exact words from the message that show the symptom (quote the client's own wording).
  * \"language\" = \"en\", \"tl\", or \"taglish\". \"confidence\" = 0.0-1.0.
- resolutions: one entry {symptom, status, evidence, confidence} per OPEN symptom listed at the end of this prompt (status = resolved | persisting | unmentioned). If no open symptoms are listed, return [].
- CANONICAL LABELS: always output symptom labels in plain English, and reuse ONE fixed label for every synonym of the same symptom. Preferred labels (use these exact spellings): fatigue, muscle cramps, dizziness, headache, nausea, bloating, fever, cough, sore throat, chest pain, back pain, neck pain, joint pain, muscle pain, leg pain, shoulder pain, tooth pain, shortness of breath, palpitations, diarrhea, constipation.
  * Multiple synonyms for ONE symptom = output that label ONCE. E.g. \"sobrang pagod, exhausted, walang lakas\" -> [\"fatigue\"] (not fatigue+tiredness+weakness).
  * \"stomach ache\", \"masakit tiyan\", \"kabag\", \"bloated\" all -> \"bloating\". \"cramp\", \"spasm\", \"pulikat\" all -> \"muscle cramps\". \"hilo\", \"lightheaded\", \"lula\" all -> \"dizziness\". \"sakit ng ulo\", \"migraine\" -> \"headache\". \"susuka\", \"naiinit\", \"queasy\" -> \"nausea\".
- The client may write in English, Tagalog, or Taglish (mixed). Tagalog symptom words include: pagod / kapaguran (tired, fatigued), walang lakas / mahina (weak), hilo / nahihilo (dizzy), sakit ng ulo (headache), pulikat / kalampos (muscle cramps), kabag (bloating, gas), susuka / naiinit (nauseous). Normalize them to the canonical English labels above.
- The message may contain Filipino TEXT-SPEAK: shortened words with dropped vowels, numbers for syllables (b=be/ka, 2=to/na/u, 4=pa/for), and improvised spellings. Examples: \"mskit chan q\" = masakit tiyan ko (stomach ache), \"pgd n q\" = pagod na ko (I'm tired), \"hlo q\" = hilo ko (dizzy), \"2loy 2loy n lagnat q\" = tuloy tuloy na lagnat ko (continuous fever), \"sska q\" = susuka ako (about to vomit), \"wl lkas aq\" = walang lakas ako (no strength). Interpret shortcuts phonetically and extract the underlying symptom. If a shortcut is genuinely ambiguous even phonetically, skip it rather than guessing.
- READ HUMAN PHRASING, NOT JUST KEYWORDS: clients often describe symptoms indirectly or with feeling words. Map the underlying physical symptom:
  * \"I can't get out of bed, everything is heavy\" / \"wala akong gana, sobrang antok\" -> fatigue
  * \"the room keeps spinning\" / \"parang iikot ang paligid\" -> dizziness
  * \"my head is pounding/banging\" / \"parang binibiyak ang ulo ko\" -> headache
  * \"my legs lock up at night\" / \"namamanhid at kumakabig ang binti\" -> muscle cramps
  * \"I feel like throwing up\" / \"parang ayaw ko nang kumain, nasusuka\" -> nausea
  * \"my clothes feel tight after eating\" / \"parang busog na busog ang tiyan\" -> bloating
- Only physical symptoms related to nutrition and wellness. Do NOT extract emotions alone (sad, stressed, tired of work) unless they describe a physical symptom. A client simply saying \"pagod ako sa trabaho\" (tired of work) is NOT fatigue unless they describe bodily tiredness.
$activeBlock

Client Message: \"$text\"";
    }

    /**
     * The NLP Module: Analyzes chat text for burnout sentiment and physical symptoms.
     */
    public function analyzeMessage($userId, $messageId, $text, array $activeSymptoms = []) {
        // Text-speak canonicalization: the NLP adjusts to the user, not the
        // reverse. Shortcuts like "mskit chan q" are expanded before the
        // model sees the message (best-effort, deterministic, auditable).
        if (class_exists('TextNormalizer')) {
            $text = TextNormalizer::normalize($text);
        }

        // Deterministic safety net: the matcher's own hits are canonicalized
        // (fatigue/tired/exhausted -> "Fatigue"; ONE row per clinical class) so
        // the card never shows near-duplicate insights. Reused both when the
        // remote model is slow/empty AND merged into the model's output below.
        $fallbackCanon = self::canonicalizeSymptoms(array_map(
            static function ($match) { return $match['keyword']; },
            SymptomMapper::matchText($text)
        ));
        $saveFallback = function () use ($userId, $messageId, $text, $fallbackCanon) {
            $stmt = $this->pdo->prepare("INSERT INTO ai_burnout_logs (user_id, message_id, sentiment_score, burnout_flag, analysis_text) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $messageId, 1, 0, $text]);
            self::persistSymptoms($this->pdo, $userId, $messageId, $fallbackCanon);
            return ['sentiment_score' => 1, 'burnout_flag' => false, 'symptoms' => self::labelsOf($fallbackCanon)];
        };

        $prompt = self::buildExtractionPrompt($text, $activeSymptoms);

        $data = [
            "model"    => $this->model,
            "messages" => [
                ["role" => "system", "content" => "You are a clinical NLP engine. Always respond with valid JSON only. No markdown. No explanation. Do not output your reasoning."],
                ["role" => "user",   "content" => $prompt]
            ],
            "temperature" => 0.1,
            // Keep the reasoning model responsive for this small extraction
            // task while leaving enough room for its JSON response.
            "reasoning_effort" => "low",
            // Reasoning models (gpt-oss-*) spend tokens thinking before the
            // JSON; a small budget can leave the generation empty and Groq
            // rejects the request. The prompt already demands raw JSON — we
            // parse defensively below.
            "max_tokens"  => 2048
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response  = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError) {
            file_put_contents(__DIR__ . '/ai_error.log', date('Y-m-d H:i:s') . " cURL Error: $curlError\n", FILE_APPEND);
            return $saveFallback();
        }

        if ($httpCode === 200 && $response) {
            $responseData = json_decode($response, true);
            $aiOutput = $responseData['choices'][0]['message']['content'] ?? null;

            if ($aiOutput) {
                $result = json_decode($aiOutput, true);

                // Defensive: reasoning models may wrap the JSON in prose or
                // reasoning fences — extract the first {...} block if needed.
                if (!is_array($result)) {
                    if (preg_match('/\{.*\}/s', $aiOutput, $m)) {
                        $result = json_decode($m[0], true);
                    }
                }

                if (is_array($result)) {
                    $score = intval($result['sentiment_score'] ?? 1);
                    $flag  = ($result['burnout_flag'] ?? false) ? 1 : 0;

                    // Canonicalize + dedupe (model output merged with the
                    // deterministic safety net) so synonyms collapse to one
                    // stable label and one clinical class == one insight.
                    // The model returns {label, evidence, language, confidence}
                    // objects; accept plain strings too for robustness.
                    $modelLabels = array_map(
                        static fn($e) => self::labelFromEntry($e),
                        (array)($result['symptoms'] ?? [])
                    );
                    $symptoms = self::canonicalizeSymptoms(array_merge(
                        $modelLabels,
                        self::labelsOf($fallbackCanon)
                    ));

                    // v1.3 negation guard: if the client's message explicitly
                    // DENIES a symptom the model over-extracted ("no headache",
                    // "hindi na ako napagod", "wala nang lagnat"), the verbatim
                    // message outranks the paraphrase — drop it so nothing is
                    // detected from a denial. (Active symptoms the client says
                    // are gone are CLOSED by the SymptomLifecycle resolver,
                    // which runs on the same message downstream.)
                    $symptoms = array_values(array_filter($symptoms, function ($canon) use ($text) {
                        $cid = $canon['class_id'] ?? null;
                        return $cid === null || !SymptomMapper::classIsNegatedInMessage($text, $cid);
                    }));

                    // 1. Save Burnout Log
                    $stmt = $this->pdo->prepare("INSERT INTO ai_burnout_logs (user_id, message_id, sentiment_score, burnout_flag, analysis_text) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$userId, $messageId, $score, $flag, $text]);

                    // 2. Save Extracted Symptoms with nutrient mapping from the
                    //    versioned mapping table. Every row records WHICH mapping
                    //    version + class produced the assignment (audit columns,
                    //    migration 003); unmatched symptoms map to NULL and are
                    //    retained for dietitian review.
                    self::persistSymptoms($this->pdo, $userId, $messageId, $symptoms);

                    $result['symptoms'] = self::labelsOf($symptoms);
                    $result['resolutions'] = self::normalizeResolutions($result['resolutions'] ?? []);
                    return $result;
                } else {
                    file_put_contents(__DIR__ . '/ai_error.log', date('Y-m-d H:i:s') . " JSON decode failed: $aiOutput\n", FILE_APPEND);
                }
            } else {
                file_put_contents(__DIR__ . '/ai_error.log', date('Y-m-d H:i:s') . " No content in response: $response\n", FILE_APPEND);
            }
        } else {
            file_put_contents(__DIR__ . '/ai_error.log', date('Y-m-d H:i:s') . " HTTP $httpCode: $response\n", FILE_APPEND);
        }

        return $saveFallback();
    }

    /**
     * Canonicalize + dedupe a list of raw symptom strings.
     * Synonyms of one clinical class collapse to a single record, so the card
     * shows ONE insight per clinical pattern. Order is preserved (first hit).
     *
     * @return array<int, array{label:string, class_id:?string, nutrient:?string}>
     */
    private static function canonicalizeSymptoms(array $rawSymptoms): array {
        $out = [];
        $seen = [];
        foreach ($rawSymptoms as $s) {
            $s = trim((string)$s);
            if ($s === '') continue;
            $canon = SymptomMapper::canonicalize($s);
            if ($canon['label'] === '') continue;
            $key = SymptomMapper::canonicalKey($canon);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = $canon;
        }
        return $out;
    }

    /** The display labels of a canonicalized symptom list. */
    private static function labelsOf(array $canon): array {
        return array_map(static fn($c) => $c['label'], $canon);
    }

    /** Pull a label out of a model symptom entry (object or plain string). */
    private static function labelFromEntry($entry): string {
        if (is_array($entry)) {
            return trim((string)($entry['label'] ?? $entry['symptom'] ?? $entry['keyword'] ?? ''));
        }
        return trim((string)$entry);
    }

    /**
     * Normalize the model's `resolutions` array into a clean, typed list.
     * Entries that are malformed or carry no status are dropped.
     *
     * @return array<int, array{symptom:string, status:string, confidence:float, evidence:string}>
     */
    private static function normalizeResolutions($raw): array {
        if (!is_array($raw)) return [];
        $out = [];
        foreach ($raw as $r) {
            if (!is_array($r)) continue;
            $symptom = trim((string)($r['symptom'] ?? $r['label'] ?? ''));
            $status  = strtolower(trim((string)($r['status'] ?? '')));
            if ($symptom === '' || $status === '') continue;
            $out[] = [
                'symptom'    => $symptom,
                'status'     => $status,
                'confidence' => isset($r['confidence']) ? (float)$r['confidence'] : 1.0,
                'evidence'   => (string)($r['evidence'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Persist canonicalized symptoms, one audit-tagged row each. Canonical
     * labels make the table self-deduplicating; the mapping version + class
     * are recorded for provenance (migration 003).
     */
    private static function persistSymptoms($pdo, $userId, $messageId, array $canon): void {
        if (empty($canon)) return;
        $stmtS = $pdo->prepare("INSERT INTO ai_extracted_symptoms (user_id, message_id, symptom_keyword, mapped_nutrient, mapping_version, mapping_class_id) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($canon as $c) {
            $stmtS->execute([$userId, $messageId, $c['label'], $c['nutrient'], SymptomMapper::getVersion(), $c['class_id']]);
        }
    }

    public function generateResponse($prompt) {
        $data = [
            "model"    => $this->model,
            "messages" => [
                ["role" => "user", "content" => $prompt]
            ],
            "temperature" => 0.2,
            "max_tokens"  => 2048
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response  = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError) {
            file_put_contents(__DIR__ . '/ai_error.log', date('Y-m-d H:i:s') . " cURL Error: $curlError\n", FILE_APPEND);
            return false;
        }

        if ($httpCode === 200 && $response) {
            $responseData = json_decode($response, true);
            $content = $responseData['choices'][0]['message']['content'] ?? null;
            if ($content) {
                return trim($content);
            }
        }

        file_put_contents(__DIR__ . '/ai_error.log', date('Y-m-d H:i:s') . " HTTP $httpCode: $response\n", FILE_APPEND);
        return false;
    }
}
?>
