<?php
/**
 * includes/ClinicalReportSanitizer.php
 *
 * Sanitizes AI-generated clinical guidance before it is injected into the DOM
 * (or stored as a clinical report).
 *
 * Why this exists:
 *  - The Groq model is asked for semantic HTML, but it frequently returns a
 *    whole document (<html><head><style>...</style></head><body>...) or
 *    "glassmorphic" inline styles (translucent dark backgrounds, light grey
 *    text, position:fixed overlays). Because the result is injected through
 *    innerHTML, <style> blocks apply GLOBALLY to the page and inline
 *    light-on-light colors make the report look greyed-out and unreadable.
 *  - Policy: keep semantic structure, host-provided classes and layout styles;
 *    drop scripts, stylesheets, event handlers, colors, overlays and every
 *    other visual hazard. The host stylesheet owns the look (light theme).
 */
class ClinicalReportSanitizer
{
    /**
     * Inline style properties that can hide text, tint backgrounds or float
     * content over the rest of the UI.
     */
    private const STYLE_DENY = [
        'color',
        'background',
        'background-color',
        'background-image',
        'background-clip',
        '-webkit-background-clip',
        '-webkit-text-fill-color',
        '-webkit-text-stroke',
        'opacity',
        'filter',
        'backdrop-filter',
        'text-shadow',
        'box-shadow',
        'visibility',
        'mix-blend-mode',
        'background-blend-mode',
    ];

    /** Tags that mark a fragment as real HTML (used for the plain-text path). */
    private const HTML_HINT = '/<\/?[a-z][a-z0-9-]*(\s|\/|>)/i';

    /**
     * @param string|null $html Raw model output (may be a full HTML document).
     * @return string Safe light-theme HTML fragment, or '' when nothing usable remains.
     */
    public static function sanitize($html): string
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }

        // 0. Markdown fences the model adds even when told not to.
        $html = preg_replace('/^\s*```(?:html)?\s*/i', '', $html);
        $html = preg_replace('/\s*```\s*$/', '', $html);

        // 1. Remove document chrome, active content and embedded media.
        $html = preg_replace('/<!DOCTYPE[^>]*>/i', '', $html);
        $html = preg_replace('/<!--.*?-->/s', '', $html);
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<head\b[^>]*>.*?<\/head>/is', '', $html);
        $html = preg_replace('/<title\b[^>]*>.*?<\/title>/is', '', $html);
        $html = preg_replace('/<svg\b[^>]*\/\s*>/i', '', $html);
        $html = preg_replace('/<(svg|iframe|object|embed|noscript)\b[^>]*>.*?<\/\1>/is', '', $html);
        $html = preg_replace('/<(?:link|meta|base)\b[^>]*>/i', '', $html);
        // Unwrap wrappers but KEEP their inner content.
        $html = preg_replace('/<\/?(?:html|head|body|form)\b[^>]*>/i', '', $html);

        // 2. Drop event handlers and javascript: URLs.
        $html = preg_replace('/\s+on[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace(
            '/\s+(?:href|src)\s*=\s*(?:"\s*javascript:[^"]*"|\'\s*javascript:[^\']*\'|\s*javascript:[^\s>]+)/i',
            '',
            $html
        );

        // 3. Legacy presentational attributes (<font>, bgcolor, text= colors).
        $html = preg_replace('/<\s*\/?\s*font\b[^>]*>/i', '', $html);
        $html = preg_replace('/\s+(?:bgcolor|text|color)\s*=\s*(?:"[^"]*"|\'[^\']*\')/i', '', $html);

        // 3.5 Stray closing-tag fragments (e.g. "</" with no tag name) leak
        //     through as literal text in the browser; valid close tags always
        //     have a letter right after "</", so strip the rest.
        $html = preg_replace('/<\/\s*(?![a-zA-Z])/', '', $html);

        // 4. Filter inline style attributes: keep layout, drop visual hazards.
        $html = preg_replace_callback('/\sstyle\s*=\s*"([^"]*)"/i', static function (array $m): string {
            return self::filterStyleAttr($m);
        }, $html);
        $html = preg_replace_callback('/\sstyle\s*=\s*\'([^\']*)\'/i', static function (array $m): string {
            return self::filterStyleAttr($m);
        }, $html);

        $html = trim($html);

        // 5. Plain text / markdown instead of HTML -> escape then structure it.
        if (!preg_match(self::HTML_HINT, $html)) {
            $html = self::plainTextToHtml($html);
        }

        // 6. Guarantee the host wrapper so report spacing always applies.
        if (stripos($html, 'clinical-report-wrap') === false) {
            $html = "<div class='clinical-report-wrap'>" . $html . "</div>";
        }

        // 7. Nothing readable left? Let the caller use its fallback.
        if (trim(strip_tags($html)) === '' && stripos($html, '<img') === false) {
            return '';
        }

        return $html;
    }

    /**
     * preg_replace_callback() helper for inline style attributes.
     */
    private static function filterStyleAttr(array $m): string
    {
        $kept = self::filterStyle($m[1]);
        return $kept === '' ? '' : ' style="' . $kept . '"';
    }

    /**
     * Keep layout/typography declarations, drop anything visual or hostile.
     */
    public static function filterStyle(string $style): string
    {
        $kept = [];
        foreach (explode(';', $style) as $declaration) {
            $declaration = trim($declaration);
            if ($declaration === '') {
                continue;
            }
            $colon = strpos($declaration, ':');
            if ($colon === false) {
                continue;
            }
            $property = strtolower(trim(substr($declaration, 0, $colon)));
            $value = trim(substr($declaration, $colon + 1));
            if ($property === '' || $value === '') {
                continue;
            }
            if (in_array($property, self::STYLE_DENY, true)) {
                continue;
            }
            if ($property === 'position' && preg_match('/fixed|sticky/i', $value)) {
                continue;
            }
            if (stripos($value, 'url(') !== false) {
                continue;
            }
            // The value is re-emitted inside a double-quoted attribute.
            $value = str_replace(['"', "'", '<', '>'], '', $value);
            $kept[] = $property . ': ' . $value;
        }
        return implode('; ', $kept);
    }

    /**
     * Escape non-HTML model output and give it basic, readable structure.
     */
    private static function plainTextToHtml(string $text): string
    {
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Headings (# ... ######) become h4 headings.
        $text = preg_replace('/^\s*#{1,6}\s+(.+)$/m', '<h4>$1</h4>', $text);

        // Bullets become list items, consecutive ones grouped in a <ul>.
        $text = preg_replace('/^\s*[-*+]\s+(.+)$/m', '<li>$1</li>', $text);
        $text = preg_replace_callback('/(?:<li>[^\n]*<\/li>\s*)+/', static function (array $m): string {
            return "<ul>\n" . $m[0] . "</ul>\n";
        }, $text);

        // Bold / italic emphasis.
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
        $text = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $text);

        $blocks = preg_split('/\r?\n\s*\r?\n/', $text);
        $out = [];
        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }
            if (preg_match('/^<(h[1-6]|ul|ol|li|p|div|table|blockquote|section|hr)\b/i', $block)) {
                $out[] = $block;
            } else {
                $out[] = '<p>' . preg_replace('/\r?\n/', '<br>', $block) . '</p>';
            }
        }
        return implode("\n", $out);
    }
}
