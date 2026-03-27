<?php
$csvPath = __DIR__ . '/public/dataset/sayilgoh_fakt_cv.csv';
$handle = fopen($csvPath, 'r');

// Get header
$header = fgetcsv($handle, 0, ';');
echo "Header columns (" . count($header) . "):\n";
foreach ($header as $i => $col) {
    echo "  [$i] " . trim($col) . "\n";
}

echo "\n\nFirst 3 data rows:\n";
$count = 0;
while (($row = fgetcsv($handle, 0, ';')) !== false && $count < 3) {
    $count++;
    echo "\n--- Row $count (columns: " . count($row) . ") ---\n";
    foreach ($row as $i => $val) {
        echo "  [$i] " . trim($val) . "\n";
    }
}

fclose($handle);
