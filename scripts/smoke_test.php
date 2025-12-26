<?php
/**
 * Backend Smoke Test
 * PHP 7.3.33 compatible
 * 
 * Tests basic backend functionality:
 * - Health endpoint
 * - Forecast endpoint
 */

// Configuration: BASE_URL and BASE_PATH
$baseUrl = getenv('FISHINGINSIGHTS_BASE_URL') ?: 'http://127.0.0.1:8001';
$basePath = getenv('FISHINGINSIGHTS_BASE_PATH') ?: '';

// Auto-detect base path if not set
if (empty($basePath)) {
    // Try health.php at root first
    $healthUrlRoot = $baseUrl . '/health.php';
    $healthResponse = @file_get_contents($healthUrlRoot);
    if ($healthResponse !== false) {
        $basePath = ''; // Root path works
    } else {
        // Try /api path
        $healthUrlApi = $baseUrl . '/api/health.php';
        $healthResponse = @file_get_contents($healthUrlApi);
        if ($healthResponse !== false) {
            $basePath = '/api'; // /api path works
        }
        // If both fail, default to /api (original behavior)
        if ($healthResponse === false) {
            $basePath = '/api';
        }
    }
}

$passed = 0;
$failed = 0;
$errors = [];

echo "FishingInsights Backend Smoke Test\n";
echo "====================================\n";
echo "Base URL: {$baseUrl}\n";
echo "Base Path: " . ($basePath ?: '(root)') . "\n\n";

// Test 1: Health Check
echo "Test 1: Health Check...\n";
$healthUrl = $baseUrl . $basePath . '/health.php';
$healthResponse = @file_get_contents($healthUrl);

if ($healthResponse === false) {
    $failed++;
    $errors[] = "Health check failed: Could not connect to $healthUrl";
    echo "  FAIL: Could not connect\n\n";
} else {
    $healthData = json_decode($healthResponse, true);
    if (!$healthData) {
        $failed++;
        $errors[] = "Health check failed: Invalid JSON response";
        echo "  FAIL: Invalid JSON\n\n";
    } elseif (isset($healthData['status']) && $healthData['status'] === 'ok') {
        $passed++;
        echo "  PASS: status = ok\n";
        
        // Check PDO SQLite
        if (isset($healthData['has_pdo_sqlite']) && $healthData['has_pdo_sqlite'] === true) {
            $passed++;
            echo "  PASS: has_pdo_sqlite = true\n";
        } else {
            $failed++;
            $errors[] = "PDO SQLite extension not available";
            echo "  FAIL: has_pdo_sqlite = false\n";
        }
        
        // Check write capability
        if (isset($healthData['can_write_db']) && $healthData['can_write_db'] === true) {
            $passed++;
            echo "  PASS: can_write_db = true\n";
        } else {
            $failed++;
            $errors[] = "Database is not writable";
            echo "  FAIL: can_write_db = false\n";
        }
    } else {
        $failed++;
        $errors[] = "Health check failed: status != ok";
        echo "  FAIL: status != ok\n";
        if (isset($healthData['message'])) {
            echo "  Error: " . $healthData['message'] . "\n";
        }
    }
    echo "\n";
}

// Test 2: Forecast Endpoint
echo "Test 2: Forecast Endpoint...\n";
$forecastUrl = $baseUrl . $basePath . '/forecast.php?lat=-37.8&lng=144.9&days=7';
$forecastResponse = @file_get_contents($forecastUrl);

if ($forecastResponse === false) {
    $failed++;
    $errors[] = "Forecast check failed: Could not connect to $forecastUrl";
    echo "  FAIL: Could not connect\n\n";
} else {
    $forecastData = json_decode($forecastResponse, true);
    if (!$forecastData) {
        $failed++;
        $errors[] = "Forecast check failed: Invalid JSON response";
        echo "  FAIL: Invalid JSON\n\n";
    } elseif (isset($forecastData['error']) && $forecastData['error'] === false) {
        if (isset($forecastData['data']['forecast']) && is_array($forecastData['data']['forecast'])) {
            $forecastLength = count($forecastData['data']['forecast']);
            if ($forecastLength === 7) {
                $passed++;
                echo "  PASS: forecast length = 7 days\n";
                
                // Check first day has required fields
                if (isset($forecastData['data']['forecast'][0]['score']) && 
                    isset($forecastData['data']['forecast'][0]['date'])) {
                    $passed++;
                    echo "  PASS: forecast structure valid\n";
                    echo "  Sample: Date = " . $forecastData['data']['forecast'][0]['date'] . 
                         ", Score = " . $forecastData['data']['forecast'][0]['score'] . "\n";
                } else {
                    $failed++;
                    $errors[] = "Forecast structure invalid: missing score or date";
                    echo "  FAIL: Forecast structure invalid\n";
                }
            } else {
                $failed++;
                $errors[] = "Forecast length = $forecastLength (expected 7)";
                echo "  FAIL: forecast length = $forecastLength (expected 7)\n";
            }
        } else {
            $failed++;
            $errors[] = "Forecast data missing or invalid";
            echo "  FAIL: Forecast data missing\n";
        }
    } else {
        $failed++;
        $errors[] = "Forecast check failed: error = true";
        echo "  FAIL: error = true\n";
        if (isset($forecastData['message'])) {
            echo "  Error: " . $forecastData['message'] . "\n";
        }
    }
    echo "\n";
}

// Test 3: API Contract Verification
echo "Test 3: API Contract Verification...\n";
$verifyScript = __DIR__ . '/verify_contract.php';
if (!file_exists($verifyScript)) {
    $failed++;
    $errors[] = "Contract verification script not found: $verifyScript";
    echo "  FAIL: Script not found\n\n";
} else {
    $output = [];
    $returnVar = 0;
    exec("php \"$verifyScript\" 2>&1", $output, $returnVar);
    
    if ($returnVar === 0) {
        $passed++;
        echo "  PASS: Contract verification passed\n";
        // Show last few lines of output
        $lastLines = array_slice($output, -3);
        foreach ($lastLines as $line) {
            if (trim($line) !== '') {
                echo "  " . $line . "\n";
            }
        }
    } else {
        $failed++;
        $errors[] = "Contract verification failed (exit code: $returnVar)";
        echo "  FAIL: Contract verification failed\n";
        // Show last few lines of output
        $lastLines = array_slice($output, -5);
        foreach ($lastLines as $line) {
            if (trim($line) !== '') {
                echo "  " . $line . "\n";
            }
        }
    }
    echo "\n";
}

// Summary
echo "====================================\n";
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

