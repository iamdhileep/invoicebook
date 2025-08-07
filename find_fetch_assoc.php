<?php
$file = 'HRMS/mobile_pwa_manager.php';
$lines = file($file);

echo "Searching for fetch_assoc() calls in $file:\n\n";

foreach ($lines as $lineNum => $line) {
    if (strpos($line, 'fetch_assoc()') !== false) {
        $actualLineNum = $lineNum + 1;
        echo "Line $actualLineNum: " . trim($line) . "\n";
        
        // Show context around the line
        echo "Context:\n";
        for ($i = max(0, $lineNum - 2); $i <= min(count($lines) - 1, $lineNum + 2); $i++) {
            $contextLineNum = $i + 1;
            $marker = ($i == $lineNum) ? " >> " : "    ";
            echo "$marker$contextLineNum: " . trim($lines[$i]) . "\n";
        }
        echo "\n";
    }
}
?>
