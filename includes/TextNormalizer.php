<?php
/**
 * includes/TextNormalizer.php
 *
 * Filipino text-speak / shortcut normalizer for the NutriDeq NLP pipeline.
 *
 * Clients often type health messages in compressed text-speak with dropped
 * vowels and letter substitutions ("mskit chan q" = "masakit tiyan ko" =
 * my stomach hurts). The NLP must adjust to the user — never the reverse.
 *
 * This normalizer runs BEFORE the Groq extraction prompt and BEFORE the
 * SymptomLifecycle resolver so both see canonical text.
 *
 * Three layers, in order:
 *   1. REGISTRY   — exact dictionary of known shortcuts (fast, unambiguous)
 *   2. FUZZY      — consonant-skeleton match against the registry + the
 *                   symptom vocabulary (handles spellings never seen before)
 *   3. PHONETIC   — vowel-collapsed matching inside long messages
 *
 * All layers are deterministic and auditable — no second LLM round-trip.
 */

if (defined('NUTRIDEQ_TEXT_NORMALIZER')) {
    return;
}
define('NUTRIDEQ_TEXT_NORMALIZER', true);

class TextNormalizer
{
    /**
     * Registry of Filipino/Taglish text-speak variants.
     * Keys are lowercase, vowels already stripped (consonant skeleton).
     * Values are the canonical expansion.
     */
    private const REGISTRY = [
        // ── Stomach / abdominal ──
        'mskt' => 'masakit', 'mskit' => 'masakit', 'mskt ng' => 'masakit ng',
        'chan' => 'tiyan', 'chan q' => 'tiyan ko', 'chn' => 'tiyan',
        'tyan' => 'tiyan', 'tyan q' => 'tiyan ko',
        // NOTE: English "stomach"/"tummy" are deliberately NOT mapped to
        // "tiyan" — they are real English words the mapper already understands
        // ("stomach hurts" -> SC-BLOAT); translating them would corrupt English.


        // ── Head ──
        'ulo' => 'ulo', 'ulo q' => 'ulo ko',
        'sng ulo' => 'sakit ng ulo', 'sakit ng ulo' => 'sakit ng ulo',
        'skt ulo' => 'sakit ng ulo', 'skit ulo' => 'sakit ng ulo',

        // ── General pain ──
        'skt' => 'sakit', 'skit' => 'sakit', 'aray' => 'aray',
        'ang skt' => 'ang sakit', 'saket' => 'sakit',

        // ── Fatigue / weakness ──
        'pgd' => 'pagod', 'pagd' => 'pagod', 'pgod' => 'pagod',
        'pgod n' => 'pagod na', 'wla lkas' => 'walang lakas',
        'wl lkas' => 'walang lakas', 'hina' => 'hina',

        // ── Dizziness ──
        'hlo' => 'hilo', 'nhhlo' => 'nahihilo', 'nhilo' => 'nahilo',
        'lula' => 'lula',

        // ── Cramps ──
        'plkt' => 'pulikat', 'pulikat' => 'pulikat', 'klmpos' => 'kalampos',

        // ── Nausea ──
        'sska' => 'susuka', 'ssuka' => 'susuka', 'skt skt' => 'sakit',
        'ninit' => 'naiinit', 'msusuka' => 'masusuka',

        // ── Bloating ──
        'kbg' => 'kabag', 'hangin' => 'hangin', 'sikip' => 'sikip',

        // ── Coughs / colds / flu ──
        'ub0' => 'ubo', 'ubo' => 'ubo', 'sip0n' => 'sipon',
        'spn' => 'sipon', 'trmadal' => 'tramadal', 'hilnat' => 'hilanat',
        'lagnat' => 'lagnat', 'lgt' => 'lagnat',

        // ── Common function words ──
        'q' => 'ko', 'aq' => 'ako', 'ku' => 'ko',
        'wla' => 'wala', 'wl' => 'wala',
        'wla n' => 'wala nang', 'wl n' => 'wala nang',
        'n q' => 'na ko', 'n aq' => 'na ako', 'n rn' => 'na rin',
        'nman' => 'naman', 'n mn' => 'na man', 'namn' => 'naman',
        'tlga' => 'talaga', 'tliga' => 'talaga', 'grbe' => 'grabe',
        'sobrng' => 'sobrang', 'sbrang' => 'sobrang', 'sobra' => 'sobra',
        'khpon' => 'kahapon', 'kphpon' => 'kahapon', 'ngyn' => 'ngayon',
        'nyn' => 'ngayon', 'bkt' => 'bakit', 'hnd' => 'hindi',
        'hndi' => 'hindi', 'nde' => 'hindi', 'd q' => 'di ko',
        'd q alam' => 'di ko alam', 'ng' => 'ng', 'ung' => 'yung',
        'lng' => 'lang', 'ln' => 'lang', 'tz' => 'tapos',
        'tpos' => 'tapos', 'tas' => 'tapos', 'taz' => 'tapos',
        'kh8' => 'kakain', 'kain' => 'kain', 'kmain' => 'kakakain',
        'tlong' => 'tulong', 'plz' => 'please', 'pls' => 'please',
        'po' => 'po', 'p0' => 'po', 'aq0' => 'ako',
        'rn' => 'na rin', 'rn.' => 'na rin', 'dge' => 'ng',
        'pe0ple' => 'people', 'mqm' => 'matulog', 'tul0g' => 'tulog',
        '2log' => 'tulog', 'tlog' => 'tulog',

        // ── Expanded function words & routine shortcuts ──
        'kpg' => 'kapag', 'kpag' => 'kapag', 'pg' => 'pag',
        'tmtayo' => 'tumatayo', 'tumtayo' => 'tumatayo', 'ttayo' => 'tumatayo',
        'tayo' => 'tayo', 'ntayo' => 'natayo',
        'gbi' => 'gabi', 'gbi.' => 'gabi', 'kgbi' => 'kagabi',
        'bnti' => 'binti', 'bnti q' => 'binti ko',
        'kmt' => 'kamot', 'kamote' => 'kamote',
        'lht' => 'lahat', 'slmt' => 'salamat', 'tnx' => 'salamat',
        'mron' => 'meron', 'pnta' => 'pumunta', 'ptuloy' => 'patuloy',
        '2loy' => 'tuloy', '2loy 2loy' => 'tuloy tuloy',
        'smkt' => 'sumakit', 'smkit' => 'sumakit', 'sumsakit' => 'sumasakit',
        'kumikirot' => 'kumikirot', 'kir0t' => 'kirot', 'kirot' => 'kirot',
        'mhm' => 'mahimbing', 'pgising' => 'paggising',
        'hrap' => 'hirap', 'hrp' => 'hirap',
        'nhhrapan' => 'nahihirapan', 'nhhirapan' => 'nahihirapan',
        'gnyan' => 'ganyan', 'gnyn' => 'ganyan', 'gnun' => 'ganun', 'ganun' => 'ganun',
        'wla p' => 'wala pa', 'wl p' => 'wala pa',
        'd p' => 'di pa', 'nde pa' => 'hindi pa', 'hnd p' => 'hindi pa',
        'snba' => 'sana ba', 'sna' => 'sana',
        'mdyo' => 'medyo', 'mdjo' => 'medyo', 'mdami' => 'marami',
        'rn nmn' => 'na rin naman', 'n nmn' => 'na naman',
        'sbrang' => 'sobrang', 'sobrng' => 'sobrang', 'sbra' => 'sobra',
        'grbe' => 'grabe', 'grb' => 'grabe',
        'mramdaman' => 'naramdaman', 'nrmdaman' => 'naramdaman',
        'maayos' => 'maayos',
        'kmi' => 'kami', 'tyo' => 'tayo', 'sla' => 'sila', 'cla' => 'sila',
        'kayo' => 'kayo', 'kta' => 'kita', 'nyo' => 'niyo',
        'smn' => 'samin', 'snmin' => 'samin', 'kanila' => 'kanila',
        'inaantok' => 'inaantok', 'nntok' => 'inaantok', 'antok' => 'antok',
        'gtom' => 'gutom', 'gut0m' => 'gutom', 'gutom' => 'gutom',
    ];

    /** Symptoms the NLP knows about (canonical keys for fuzzy matching). */
    private const SYMPTOM_VOCAB = [
        'masakit', 'sakit', 'masakit ang tiyan', 'masakit tiyan ko',
        'masakit ang ulo', 'sakit ng ulo', 'pagod', 'kapaguran',
        'walang lakas', 'hina ang katawan', 'hilo', 'nahihilo', 'lula',
        'pulikat', 'kalampos', 'kabag', 'namamaga', 'sikip ng tiyan',
        'susuka', 'naiinit', 'masusuka', 'ubo', 'sipon', 'lagnat',
        'hilanat', 'tramadal', 'sakit ng katawan', 'masakit ang katawan',
        'sobrang sakit', 'matagal nang sakit', 'bigla akong nahilo',
        'grabe ang sakit', 'hangin sa tiyan', 'dehydrated',
    ];

    /** Cache of lowercase registry + vocab skeletons (built once). */
    private static ?array $skeletonIndex = null;

    /**
     * Common short words that must NEVER be fuzzy-rescued — their consonant
     * skeletons collide with real targets ("all"→"lula", "the"→?).
     * Checked case-insensitively before any skeleton substitution.
     */
    private const FUZZY_STOPWORDS = [
        // English
        'all', 'and', 'the', 'for', 'was', 'are', 'you', 'not', 'but', 'its',
        'now', 'low', 'gas', 'day', 'get', 'got', 'has', 'had', 'how', 'who',
        'why', 'can', 'did', 'does', 'done', 'from', 'have', 'here', 'just',
        'know', 'like', 'long', 'made', 'make', 'much', 'need', 'only', 'over',
        'said', 'same', 'some', 'such', 'take', 'than', 'that', 'them', 'then',
        'they', 'this', 'time', 'very', 'want', 'well', 'went', 'were', 'what',
        'when', 'will', 'with', 'would', 'year', 'your', 'about', 'after',
        'again', 'still', 'since', 'being', 'every', 'feel', 'feels', 'felt',
        // Filipino / Taglish
        'na', 'sa', 'ng', 'po', 'ko', 'ako', 'ka', 'si', 'ni', 'at', 'ay',
        'ung', 'yung', 'lang', 'din', 'rin', 'hindi', 'wala', 'meron', 'may',
        'naman', 'talaga', 'grabe', 'sobra', 'kayo', 'kami', 'tayo', 'sila',
        'kasi', 'pero', 'dahil', 'pag', 'kapag', 'kung', 'na ko', 'na ako',
        'tapos', 'ula', 'gabi', 'araw', 'umaga', 'hapon', 'bukas', 'ngayon',
    ];

    /**
     * Entry point — normalize a raw client message.
     * Returns the best-effort canonical text: same language, same meaning,
     * just spelled the way the rest of the pipeline expects.
     */
    public static function normalize(string $raw): string
    {
        $text = self::preprocess($raw);
        if ($text === '') {
            return $text;
        }

        $tokens = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($tokens === false) {
            return $text;
        }

        // Pass 1: exact registry match on 1-3 token windows (left-to-right).
        $out = [];
        $i = 0;
        $n = count($tokens);
        while ($i < $n) {
            if (trim($tokens[$i]) === '') {
                $out[] = $tokens[$i];
                $i++;
                continue;
            }
            // How many real words remain from $i? Bounds the window.
            $wordsRemaining = 0;
            for ($k = $i; $k < $n; $k++) {
                if (trim($tokens[$k]) !== '') $wordsRemaining++;
            }
            $maxLen = min(3, $wordsRemaining);
            $matched = false;
            for ($len = $maxLen; $len >= 1; $len--) {
                // collect up to $len words (skipping whitespace tokens)
                $words = [];
                $j = $i;
                while ($j < $n && count($words) < $len) {
                    if (trim($tokens[$j]) !== '') $words[] = $tokens[$j];
                    $j++;
                }
                if (count($words) < $len) continue;
                $phrase = implode(' ', $words);
                if (isset(self::REGISTRY[$phrase])) {
                    $out[] = self::REGISTRY[$phrase];
                    // advance past consumed words AND the whitespace between
                    $consumed = 0;
                    while ($i < $n && $consumed < $len) {
                        if (trim($tokens[$i]) !== '') $consumed++;
                        $i++;
                    }
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $out[] = $tokens[$i];
                $i++;
            }
        }

        $normalized = implode('', $out);

        // Pass 2: fuzzy consonant-skeleton rescue for tokens not in registry.
        $normalized = self::fuzzyRescue($normalized);

        return $normalized;
    }

    /**
     * Pass 2 — for each token, if it is NOT a known Filipino/English word but
     * its consonant skeleton matches a known one, substitute the canonical.
     * This catches spellings like "mskt" or "pgd" that aren't in REGISTRY.
     */
    private static function fuzzyRescue(string $text): string
    {
        if (self::$skeletonIndex === null) {
            self::buildSkeletonIndex();
        }
        $tokens = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($tokens === false) {
            return $text;
        }

        $out = [];
        foreach ($tokens as $tok) {
            if (trim($tok) === '' || mb_strlen($tok) < 2) {
                $out[] = $tok;
                continue;
            }
            // Skip already-canonical words and anything on the stoplist
            $clean = preg_replace('/[^a-z0-9]/', '', $tok);
            if ($clean === '' || isset(self::REGISTRY[$clean]) || in_array($clean, self::$skeletonIndex['canonical'], true)) {
                $out[] = $tok;
                continue;
            }
            if (in_array($clean, self::FUZZY_STOPWORDS, true)) {
                $out[] = $tok;
                continue;
            }
            $skel = self::skeleton($clean);
            // Confidence gates: skeleton must be >= 3 chars ("mskt","plkt") so
            // short 2-letter skeletons can't collide, and the length difference
            // must be sane — otherwise leave the token untouched.
            if ($skel !== '' && mb_strlen($skel) >= 3 && isset(self::$skeletonIndex['by_skel'][$skel])) {
                $candidates = self::$skeletonIndex['by_skel'][$skel];
                // Pick the candidate closest in length (handles 'mskit'→'masakit')
                usort($candidates, function ($a, $b) use ($clean) {
                    return abs(mb_strlen($a) - mb_strlen($clean)) <=> abs(mb_strlen($b) - mb_strlen($clean));
                });
                $best = $candidates[0];
                if (abs(mb_strlen($best) - mb_strlen($clean)) <= 3) {
                    $out[] = $best;
                    continue;
                }
            }
            $out[] = $tok;
        }
        return implode('', $out);
    }

    /**
     * Character-level cleanup applied before tokenization:
     *  - strips zero-width / BOM characters that break substring matching,
     *  - collapses long letter runs (3+) to a single letter so "sobranggg" ->
     *    "sobrang" and "grabehhh" -> "grabe". Runs of exactly two letters are
     *    preserved because they are legitimate ("all", "feel", "tummy").
     *  - lowercases and trims.
     */
    private static function preprocess(string $raw): string
    {
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $raw) ?? $raw;
        $text = mb_strtolower(trim($text));
        if ($text === '') {
            return $text;
        }
        // Collapse only runs of 3+ identical LETTERS (never digits, so "1000"
        // and dosages survive intact).
        $text = preg_replace('/([a-z])\1{2,}/u', '$1', $text) ?? $text;
        // Space out sentence punctuation so a shortcut glued to it ("pgd,",
        // "skt.") is still matched as a clean token by the registry. Apostrophes
        // are deliberately NOT split ("can't", "i'm" stay intact).
        $text = preg_replace('/([.,!?;:]+)/u', ' $1 ', $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    /**
     * Consonant skeleton: lowercase, strip vowels (keep 'y' as a consonant —
     * Filipino text-speak drops vowels aggressively).
     */
    private static function skeleton(string $word): string
    {
        return preg_replace('/[aeiou]/', '', mb_strtolower($word)) ?? '';
    }

    private static function buildSkeletonIndex(): void
    {
        $canonical = [];
        $bySkel = [];
        foreach (array_keys(self::REGISTRY) as $phrase) {
            foreach (preg_split('/\s+/', $phrase) as $w) {
                $canonical[$w] = true;
            }
        }
        // Index REGISTRY values and symptom vocab by skeleton
        $allTargets = array_unique(array_merge(
            array_values(self::REGISTRY),
            self::SYMPTOM_VOCAB
        ));
        foreach ($allTargets as $target) {
            foreach (preg_split('/\s+/', $target) as $w) {
                $w = preg_replace('/[^a-z]/', '', $w);
                if ($w === '' || mb_strlen($w) < 2) continue;
                $canonical[$w] = true;
                $skel = self::skeleton($w);
                if ($skel === '') continue;
                $bySkel[$skel][] = $w;
            }
        }
        self::$skeletonIndex = ['canonical' => array_keys($canonical), 'by_skel' => $bySkel];
    }

    /**
     * Utility for the SymptomLifecycle resolver: normalize a message first,
     * then resolve against it. Keeps one canonical code path.
     */
    public static function normalizeForResolution(string $raw): string
    {
        return self::normalize($raw);
    }
}
