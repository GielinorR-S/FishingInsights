<?php
/**
 * API Contract Verification Script
 * PHP 7.3.33 compatible
 * 
 * Verifies that /api/forecast.php response matches the contract specification.
 */

$apiBase = 'http://127.0.0.1:8001';
$goldenFile = __DIR__ . '/../tests/golden/forecast.sample.json';
$testUrl = $apiBase . '/api/forecast.php?lat=-37.8&lng=144.9&days=7';

$passed = 0;
$failed = 0;
$errors = [];

echo "FishingInsights API Contract Verification\n";
echo "==========================================\n\n";

// Helper to get today's date in Australia/Melbourne
function getTodayInMelbourne() {
    $dt = new DateTime('now', new DateTimeZone('Australia/Melbourne'));
    return $dt->format('Y-m-d');
}

// Helper to check if key exists and is correct type
function checkKey($data, $path, $expectedType, $required = true) {
    global $errors, $failed;
    
    $keys = explode('.', $path);
    $current = $data;
    
    foreach ($keys as $key) {
        if (!isset($current[$key])) {
            if ($required) {
                $errors[] = "Missing required key: $path";
                $failed++;
                return false;
            }
            return true; // Optional key missing is OK
        }
        $current = $current[$key];
    }
    
    $actualType = gettype($current);
    if ($actualType === 'double') {
        $actualType = 'float';
    }
    if ($actualType === 'integer') {
        $actualType = 'int';
    }
    
    $typeMatch = false;
    if ($expectedType === 'array') {
        $typeMatch = is_array($current);
    } elseif ($expectedType === 'string') {
        $typeMatch = is_string($current);
    } elseif ($expectedType === 'number') {
        $typeMatch = is_numeric($current);
    } elseif ($expectedType === 'boolean') {
        $typeMatch = is_bool($current);
    } else {
        $typeMatch = ($actualType === $expectedType);
    }
    
    if (!$typeMatch) {
        $errors[] = "Type mismatch for $path: expected $expectedType, got $actualType";
        $failed++;
        return false;
    }
    
    return true;
}

// Test 1: Load golden sample
echo "Test 1: Load golden sample...\n";
if (!file_exists($goldenFile)) {
    $failed++;
    $errors[] = "Golden sample file not found: $goldenFile";
    echo "  FAIL: File not found\n\n";
} else {
    $goldenContent = file_get_contents($goldenFile);
    // Remove BOM if present
    if (substr($goldenContent, 0, 3) === "\xEF\xBB\xBF") {
        $goldenContent = substr($goldenContent, 3);
    }
    // Trim whitespace
    $goldenContent = trim($goldenContent);
    $golden = json_decode($goldenContent, true);
    if ($golden === null && json_last_error() !== JSON_ERROR_NONE) {
        $failed++;
        $errors[] = "Failed to parse golden sample JSON: " . json_last_error_msg();
        echo "  FAIL: Invalid JSON (" . json_last_error_msg() . ")\n\n";
    } else {
        $passed++;
        echo "  PASS: Golden sample loaded\n\n";
    }
}

// Test 2: Fetch live response
echo "Test 2: Fetch live response...\n";
$response = @file_get_contents($testUrl);
if ($response === false) {
    $failed++;
    $errors[] = "Could not connect to $testUrl";
    echo "  FAIL: Connection failed\n\n";
} else {
    $live = json_decode($response, true);
    if (!$live) {
        $failed++;
        $errors[] = "Failed to parse live response JSON";
        echo "  FAIL: Invalid JSON\n\n";
    } else {
        $passed++;
        echo "  PASS: Live response fetched\n\n";
    }
}

if (!isset($live)) {
    echo "==========================================\n";
    echo "Summary: $passed passed, $failed failed\n\n";
    if ($failed > 0) {
        echo "Errors:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
        echo "\n";
        exit(1);
    }
    exit(0);
}

// Test 3: Check top-level structure
echo "Test 3: Top-level structure...\n";
$topLevelChecks = [
    ['error', 'boolean', true],
    ['data', 'array', true],
];

foreach ($topLevelChecks as $check) {
    if (checkKey($live, $check[0], $check[1], $check[2])) {
        $passed++;
    }
}

if ($live['error'] === true) {
    $failed++;
    $errors[] = "API returned error: " . ($live['message'] ?? 'Unknown error');
    echo "  FAIL: API returned error\n\n";
} else {
    $passed++;
    echo "  PASS: error = false\n";
}

echo "\n";

// Test 4: Check data object structure
echo "Test 4: Data object structure...\n";
$dataChecks = [
    ['data.location', 'array', true],
    ['data.location.lat', 'number', true],
    ['data.location.lng', 'number', true],
    ['data.location.name', 'string', true],
    ['data.timezone', 'string', true],
    ['data.forecast', 'array', true],
    ['data.cached', 'boolean', true],
    ['data.cached_at', 'string', true],
];

foreach ($dataChecks as $check) {
    if (checkKey($live, $check[0], $check[1], $check[2])) {
        $passed++;
    }
}

// Check timezone invariant
if (isset($live['data']['timezone']) && $live['data']['timezone'] === 'Australia/Melbourne') {
    $passed++;
    echo "  PASS: timezone = Australia/Melbourne\n";
} else {
    $failed++;
    $errors[] = "Timezone invariant violated: expected 'Australia/Melbourne', got '" . ($live['data']['timezone'] ?? 'missing') . "'";
    echo "  FAIL: timezone invariant\n";
}

echo "\n";

// Test 5: Check forecast array length
echo "Test 5: Forecast array length...\n";
$requestedDays = 7;
$forecastCount = isset($live['data']['forecast']) ? count($live['data']['forecast']) : 0;

if ($forecastCount === $requestedDays) {
    $passed++;
    echo "  PASS: forecast length = $requestedDays\n\n";
} else {
    $failed++;
    $errors[] = "Forecast length mismatch: expected $requestedDays, got $forecastCount";
    echo "  FAIL: forecast length = $forecastCount (expected $requestedDays)\n\n";
}

// Test 6: Check first date equals Melbourne today
echo "Test 6: First date equals Melbourne today...\n";
$expectedToday = getTodayInMelbourne();
$actualFirstDate = isset($live['data']['forecast'][0]['date']) ? $live['data']['forecast'][0]['date'] : null;

if ($actualFirstDate === $expectedToday) {
    $passed++;
    echo "  PASS: forecast[0].date = $expectedToday (Melbourne today)\n\n";
} else {
    $failed++;
    $errors[] = "First date invariant violated: expected $expectedToday, got " . ($actualFirstDate ?? 'missing');
    echo "  FAIL: forecast[0].date = " . ($actualFirstDate ?? 'missing') . " (expected $expectedToday)\n\n";
}

// Test 7: Check forecast day structure (first day)
echo "Test 7: Forecast day structure (first day)...\n";
if (isset($live['data']['forecast'][0])) {
    $day = $live['data']['forecast'][0];
    
    $dayChecks = [
        ['date', 'string', true],
        ['score', 'number', true],
        ['weather', 'array', true],
        ['sun', 'array', true],
        ['tides', 'array', true],
        ['best_bite_windows', 'array', true],
        ['recommended_species', 'array', true],
        ['gear_suggestions', 'array', true],
        ['reasons', 'array', true],
    ];
    
    foreach ($dayChecks as $check) {
        $key = 'data.forecast.0.' . $check[0];
        if (checkKey($live, $key, $check[1], $check[2])) {
            $passed++;
        }
    }
    
    // Check score range
    $score = isset($day['score']) ? (float)$day['score'] : null;
    if ($score !== null && $score >= 0 && $score <= 100) {
        $passed++;
        echo "  PASS: score in range 0-100 ($score)\n";
    } else {
        $failed++;
        $errors[] = "Score range violated: score = " . ($score ?? 'missing') . " (expected 0-100)";
        echo "  FAIL: score out of range\n";
    }
    
    echo "\n";
} else {
    $failed++;
    $errors[] = "First forecast day missing";
    echo "  FAIL: First forecast day missing\n\n";
}

// Test 8: Check all days have required fields
echo "Test 8: All days have required fields...\n";
$allDaysValid = true;
for ($i = 0; $i < $forecastCount; $i++) {
    if (!isset($live['data']['forecast'][$i]['date']) || 
        !isset($live['data']['forecast'][$i]['score']) ||
        !isset($live['data']['forecast'][$i]['weather']) ||
        !isset($live['data']['forecast'][$i]['sun']) ||
        !isset($live['data']['forecast'][$i]['tides'])) {
        $allDaysValid = false;
        $errors[] = "Day $i missing required fields";
        break;
    }
    
    $dayScore = (float)$live['data']['forecast'][$i]['score'];
    if ($dayScore < 0 || $dayScore > 100) {
        $allDaysValid = false;
        $errors[] = "Day $i score out of range: $dayScore";
        break;
    }
}

if ($allDaysValid) {
    $passed++;
    echo "  PASS: All $forecastCount days have required fields and valid scores\n\n";
} else {
    $failed++;
    echo "  FAIL: Some days missing required fields or invalid scores\n\n";
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
    echo "All contract checks passed!\n";
    exit(0);
}

