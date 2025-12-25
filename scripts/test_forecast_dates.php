<?php
/**
 * Test Forecast Date Range
 * PHP 7.3.33 compatible
 * 
 * Tests that forecast dates start correctly:
 * - Default (no start param) should start at today in Australia/Melbourne
 * - Explicit start date should be used exactly
 */

$apiBase = 'http://127.0.0.1:8001';
$passed = 0;
$failed = 0;
$errors = [];

echo "FishingInsights Forecast Date Range Test\n";
echo "==========================================\n\n";

// Get today in Australia/Melbourne timezone
$tz = new DateTimeZone('Australia/Melbourne');
$today = new DateTime('now', $tz);
$todayStr = $today->format('Y-m-d');

echo "Today in Australia/Melbourne: $todayStr\n\n";

// Test 1: Default start (should be today)
echo "Test 1: Default start date (no start param)...\n";
$url1 = $apiBase . '/api/forecast.php?lat=-37.8&lng=144.9&days=7';
$response1 = @file_get_contents($url1);

if ($response1 === false) {
    $failed++;
    $errors[] = "Test 1 failed: Could not connect to $url1";
    echo "  FAIL: Could not connect\n\n";
} else {
    $data1 = json_decode($response1, true);
    if (!$data1 || !isset($data1['data']['forecast']) || empty($data1['data']['forecast'])) {
        $failed++;
        $errors[] = "Test 1 failed: Invalid response structure";
        echo "  FAIL: Invalid response\n\n";
    } else {
        $firstDate = $data1['data']['forecast'][0]['date'] ?? null;
        if ($firstDate === $todayStr) {
            $passed++;
            echo "  PASS: First date = $firstDate (matches today)\n";
        } else {
            $failed++;
            $errors[] = "Test 1 failed: First date = $firstDate (expected $todayStr)";
            echo "  FAIL: First date = $firstDate (expected $todayStr)\n";
        }
        
        // Check we got 7 days
        $dayCount = count($data1['data']['forecast']);
        if ($dayCount === 7) {
            $passed++;
            echo "  PASS: Forecast length = 7 days\n";
        } else {
            $failed++;
            $errors[] = "Test 1 failed: Forecast length = $dayCount (expected 7)";
            echo "  FAIL: Forecast length = $dayCount (expected 7)\n";
        }
    }
    echo "\n";
}

// Test 2: Explicit start date
$testDate = '2025-12-26';
echo "Test 2: Explicit start date ($testDate)...\n";
$url2 = $apiBase . '/api/forecast.php?lat=-37.8&lng=144.9&days=7&start=' . urlencode($testDate);
$response2 = @file_get_contents($url2);

if ($response2 === false) {
    $failed++;
    $errors[] = "Test 2 failed: Could not connect to $url2";
    echo "  FAIL: Could not connect\n\n";
} else {
    $data2 = json_decode($response2, true);
    if (!$data2 || !isset($data2['data']['forecast']) || empty($data2['data']['forecast'])) {
        $failed++;
        $errors[] = "Test 2 failed: Invalid response structure";
        echo "  FAIL: Invalid response\n\n";
    } else {
        $firstDate = $data2['data']['forecast'][0]['date'] ?? null;
        if ($firstDate === $testDate) {
            $passed++;
            echo "  PASS: First date = $firstDate (matches requested date)\n";
        } else {
            $failed++;
            $errors[] = "Test 2 failed: First date = $firstDate (expected $testDate)";
            echo "  FAIL: First date = $firstDate (expected $testDate)\n";
        }
        
        // Check dates are sequential
        $allSequential = true;
        for ($i = 0; $i < count($data2['data']['forecast']); $i++) {
            $expectedDate = date('Y-m-d', strtotime($testDate . " +$i days"));
            $actualDate = $data2['data']['forecast'][$i]['date'] ?? null;
            if ($actualDate !== $expectedDate) {
                $allSequential = false;
                $errors[] = "Test 2 failed: Day $i date = $actualDate (expected $expectedDate)";
                break;
            }
        }
        if ($allSequential) {
            $passed++;
            echo "  PASS: All dates are sequential\n";
        } else {
            $failed++;
            echo "  FAIL: Dates are not sequential\n";
        }
    }
    echo "\n";
}

// Summary
echo "==========================================\n";
echo "Summary: $passed passed, $failed failed\n\n";

if ($failed > 0) {
    echo "Errors:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    echo "\n";
    exit(1);
} else {
    echo "All tests passed!\n";
    exit(0);
}

