$file = 'c:\xampp\htdocs\nutrideq\food-exchange.php'
$content = Get-Content $file -Raw -Encoding UTF8

# Find the start marker (after the new vtabs close div and outer tab-content close div)
$startMarker = '</div><!-- /.fel-vtabs -->'
$startPos = $content.IndexOf($startMarker)
if ($startPos -lt 0) {
    Write-Host "Start marker not found"
    exit 1
}
$afterStart = $startPos + $startMarker.Length

# Find the next </div> after the startMarker (closes #food-exchange-list tab-content)
$tabContentClose = $content.IndexOf('</div>', $afterStart)
$afterTabContentClose = $tabContentClose + 6

# Find the next three large structural closing divs and the <script> tag position
$scriptPos = $content.IndexOf('<script>', $afterStart)
if ($scriptPos -lt 0) {
    Write-Host "Script tag not found"
    exit 1
}

# Build new content: keep everything up to and including </div> after vtabs,
# then skip all remaining old/duplicate content until 3 lines before <script>
$beforeJunk = $content.Substring(0, $afterTabContentClose)
$afterJunk = $content.Substring($scriptPos)

$newContent = $beforeJunk + "`r`n`r`n`r`n        `r`n        " + $afterJunk

if ($newContent -ne $content) {
    [System.IO.File]::WriteAllText($file, $newContent, [System.Text.Encoding]::UTF8)
    Write-Host "Cleaned up OK"
} else {
    Write-Host "No change made"
}
