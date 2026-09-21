<?php
$files = [
    'admin-user-management.php',
    'admin-staff-management.php',
    'admin-client-management.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "File $file not found\n";
        continue;
    }
    $content = file_get_contents($file);
    // Use regex to remove any include of logout-modal.php, accounting for different quoting and spaces
    $newContent = preg_replace('/<\?php\s+include\s+[\'"]includes\/logout-modal\.php[\'"];\s+\?>\s*/i', '', $content);
    
    if ($content !== $newContent) {
        file_put_contents($file, $newContent);
        echo "Fixed $file\n";
    } else {
        echo "No changes needed for $file (string not found)\n";
    }
}
?>
