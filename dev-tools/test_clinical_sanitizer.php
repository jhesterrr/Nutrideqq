<?php
/**
 * dev-tools/test_clinical_sanitizer.php
 *
 * Lightweight harness for includes/ClinicalReportSanitizer.php.
 *
 * Run:
 *   C:\xampp\php\php.exe dev-tools\test_clinical_sanitizer.php
 */

require_once __DIR__ . '/../includes/ClinicalReportSanitizer.php';

$pass = 0;
$fail = 0;

function check($name, $condition)
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "[PASS] $name\n";
    } else {
        $fail++;
        echo "[FAIL] $name\n";
    }
}

function sanitize($html)
{
    return ClinicalReportSanitizer::sanitize($html);
}

echo "ClinicalReportSanitizer test suite\n";
echo str_repeat('-', 62) . "\n";

// 1 ── Full document shell with a global <style> must be flattened.
$input = "<!DOCTYPE html><html><head><style>body{background:#0f172a;color:#cbd5e1}</style></head>"
       . "<body><div class='clinical-section'><h3>Assessment</h3><p>BMI is elevated.</p></div></body></html>";
$out = sanitize($input);
check('1a strips <style> block', stripos($out, '<style') === false);
check('1b strips html/head/body tags', !preg_match('/<\/?(?:html|head|body)\b/i', $out));
check('1c keeps readable content', strpos($out, 'BMI is elevated.') !== false);
check('1d keeps semantic classes', strpos($out, 'clinical-section') !== false);
check('1e guarantees host wrapper', strpos($out, 'clinical-report-wrap') !== false);

// 2 ── Glassmorphic inline styles: colors/overlays dropped, layout kept.
$input = '<div class="clinical-section" style="background: rgba(15,23,42,0.8); color:#94a3b8; opacity:.45; '
       . 'display:grid; grid-template-columns:1fr 1fr; padding:12px; position:fixed; top:0;">X</div>';
$out = sanitize($input);
check('2a drops color declarations', stripos($out, 'color:') === false);
check('2b drops background declarations', stripos($out, 'background') === false);
check('2c drops opacity', stripos($out, 'opacity') === false);
check('2d drops position:fixed', stripos($out, 'fixed') === false);
check('2e keeps layout styles', strpos($out, 'display: grid') !== false && strpos($out, 'grid-template-columns: 1fr 1fr') !== false);
check('2f keeps class attribute', strpos($out, 'clinical-section') !== false);

// 3 ── Scripts, event handlers and javascript: URLs.
$input = '<div onclick="evil()" onmouseover=evil()><a href="javascript:evil()">click</a>'
       . '<script>alert(1)</script><span>Safe text</span></div>';
$out = sanitize($input);
check('3a strips <script>', stripos($out, '<script') === false);
check('3b strips quoted onclick', stripos($out, 'onclick') === false);
check('3c strips unquoted onmouseover', stripos($out, 'onmouseover') === false);
check('3d strips javascript: URL', stripos($out, 'javascript:') === false);
check('3e keeps safe text', strpos($out, 'Safe text') !== false);

// 4 ── Plain text / markdown response becomes readable HTML.
$input = "## Nutrient Targets\n\n- Iron rich foods\n- Calcium sources\n\nUse **malunggay** and *kangkong* daily.";
$out = sanitize($input);
check('4a markdown heading converted', strpos($out, '<h4>Nutrient Targets</h4>') !== false);
check('4b bullet became list item', strpos($out, '<li>Iron rich foods</li>') !== false && strpos($out, '<ul>') !== false);
check('4c bold converted', strpos($out, '<strong>malunggay</strong>') !== false);
check('4d italic converted', strpos($out, '<em>kangkong</em>') !== false);
check('4e no raw asterisks left', strpos($out, '**') === false);

// 5 ── Escaping of plain-text fragments (no HTML present).
$out = sanitize('Keep intake under 5 < 6 g of salt & 2 g sodium');
check('5a escapes ampersand', strpos($out, '&amp;') !== false);
check('5b escapes angle bracket', strpos($out, '&lt;') !== false);

// 6 ── Host classes for pills survive, their colors do not.
$out = sanitize("<span class='nutrient-pill' style='color:#fff;background:#000;border-radius:20px'>Vitamin C</span>");
check('6a keeps nutrient-pill class', strpos($out, 'nutrient-pill') !== false);
check('6b strips pill colors', stripos($out, '#fff') === false && stripos($out, '#000') === false);
check('6c keeps harmless layout style', strpos($out, 'border-radius: 20px') !== false);

// 7 ── Already wrapped reports are not double wrapped.
$out = sanitize("<div class='clinical-report-wrap'><p>Done</p></div>");
check('7a single wrapper kept', substr_count($out, 'clinical-report-wrap') === 1);

// 8 ── Empty / unusable payloads.
check('8a empty string', sanitize('') === '');
check('8b null', sanitize(null) === '');
check('8c style-only payload', sanitize('<style>body{color:red}</style>') === '');
check('8d whitespace only', sanitize("   \n  ") === '');

// 9 ── Legacy <font> tags and presentational attributes.
$out = sanitize('<font color="#ff0000">Danger</font><table bgcolor="#ffffff"><tr><td>Cell</td></tr></table>');
check('9a unwraps <font> but keeps text', strpos($out, 'Danger') !== false && stripos($out, '<font') === false);
check('9b strips bgcolor', stripos($out, 'bgcolor') === false);
check('9c keeps table content', strpos($out, 'Cell') !== false);

// 10 ── Realistic "glassmorphic" model output becomes a readable fragment.
$input = "<html><head><style>body{background:#0b1220;color:#94a3b8}</style></head><body>\n"
       . "<div class='clinical-report-wrap' style='background: rgba(15,23,42,0.7); color:#cbd5e1; opacity:0.5;'>\n"
       . "<div class='clinical-section' style='background:#111827;color:#e5e7eb;backdrop-filter: blur(8px);display:grid;gap:10px'>\n"
       . "<h3 style='color:#f8fafc'><i class='fas fa-chart-line'></i> Clinical Assessment</h3>"
       . "<p style='color:#94a3b8'>BMI 27.4 kg/m² — obese class I (WHO Asian cutoffs).</p>"
       . "<div class='nutrient-pill-container'><span class='nutrient-pill' style='background:#0f172a;color:#e2e8f0'>Vitamin C</span></div>"
       . "</div></div></body></html>";
$out = sanitize($input);
check('10a no <style>/<html> left', stripos($out, '<style') === false && stripos($out, '<html') === false);
check('10b no inline color left', stripos($out, 'color') === false);
check('10c keeps clinical content', strpos($out, 'BMI 27.4 kg/m²') !== false);
check('10d keeps pill badge markup', strpos($out, "class='nutrient-pill'") !== false);
check('10e keeps layout style', strpos($out, 'display: grid') !== false);
check('10f single host wrapper', substr_count($out, 'clinical-report-wrap') === 1);

// 11 ── Rules-engine fallback fragment: class-based risk coding survives sanitizing.
$input = "<div class='clinical-report-wrap'>"
       . "<div class='clinical-section'><h3><i class='fas fa-chart-line'></i> Clinical Assessment &amp; Anthropometry</h3>"
       . "<div class='clinical-stat-row'><div class='stat-box'><div class='label'>Calculated BMI</div>"
       . "<div class='value is-high'>27.4 kg/m² (Obese)</div></div></div>"
       . "<p class='clinical-note'><b>Clinical Interpretation:</b> Significant cardiovascular risk.</p></div>"
       . "<div class='clinical-section'><div class='clinical-split'>"
       . "<div class='clinical-panel is-prioritize'><div class='clinical-panel-title'><i class='fas fa-check-circle'></i> Prioritize (DOST-FCT)</div>"
       . "<ul class='clinical-list'><li>Malunggay (leaves)</li></ul></div>"
       . "<div class='clinical-panel is-restrict'><div class='clinical-panel-title'><i class='fas fa-ban'></i> Restrict / Moderate</div>"
       . "<ul class='clinical-list'><li>High-sodium condiments</li></ul></div>"
       . "</div></div></div>";
$out = sanitize($input);
check('11a keeps risk modifier class', strpos($out, 'value is-high') !== false);
check('11b keeps panel modifier classes',
    strpos($out, 'clinical-panel is-prioritize') !== false && strpos($out, 'clinical-panel is-restrict') !== false);
check('11c keeps fallback content',
    strpos($out, 'Restrict / Moderate') !== false && strpos($out, 'Malunggay (leaves)') !== false);
check('11d no inline color survives', stripos($out, 'color:') === false);
check('11e keeps layout primitives',
    strpos($out, 'clinical-split') !== false && strpos($out, 'clinical-list') !== false);
check('11f single host wrapper', substr_count($out, 'clinical-report-wrap') === 1);


echo str_repeat('-', 62) . "\n";
echo "Passed: $pass   Failed: $fail\n";
exit($fail > 0 ? 1 : 0);
