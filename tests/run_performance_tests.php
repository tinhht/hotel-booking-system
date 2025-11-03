<?php

/**
 * Performance Test Suite Runner
 * Runs all performance optimization validation tests
 */

echo "=============================================================\n";
echo "Hotel Booking System - Performance Optimization Test Suite\n";
echo "=============================================================\n\n";

$testFiles = [
    __DIR__ . '/Performance/PerformanceValidationTest.php',
    __DIR__ . '/Performance/RepositoryOptimizationTest.php',
];

$allPassed = true;

foreach ($testFiles as $testFile) {
    echo "Running: " . basename($testFile) . "\n";
    echo str_repeat("-", 60) . "\n";
    
    ob_start();
    include $testFile;
    $output = ob_get_clean();
    
    echo $output;
    
    // Check if test failed
    if (strpos($output, '✗') !== false) {
        $allPassed = false;
    }
    
    echo "\n";
}

echo "=============================================================\n";
if ($allPassed) {
    echo "✓ All performance optimization tests passed!\n";
    exit(0);
} else {
    echo "✗ Some tests failed!\n";
    exit(1);
}
