<?php
$file = 'dashboard.php';
$lines = file($file);
$start = 454; // 0-indexed line 455
$end = 553;   // 0-indexed line 554
$total_deleted = 0;

for ($i = $start; $i <= $end; $i++) {
    unset($lines[$i]);
    $total_deleted++;
}

file_put_contents($file, implode('', $lines));
echo "Deleted $total_deleted lines from $file\n";
?>
