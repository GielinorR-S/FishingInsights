<?php
/**
 * Validate Locations CSV
 * PHP 7.3.33 compatible
 * 
 * Validates locations.csv file for required columns, data types, and sanity checks.
 */

$csvFile = __DIR__ . '/../data/locations.csv';

$errors = [];
$warnings = [];
$passed = 0;
$failed = 0;

echo "FishingInsights Locations CSV Validation\n";
echo "==========================================\n\n";

// Check if file exists
if (!file_exists($csvFile)) {
    echo "ERROR: CSV file not found: $csvFile\n";
    exit(1);
}

// Required columns
$requiredColumns = ['name', 'lat', 'lng', 'state', 'region'];
$allColumns = ['name', 'lat', 'lng', 'state', 'region', 'type', 'access', 'notes', 'safety'];

// Valid state codes (Australia)
$validStates = ['VIC', 'NSW', 'QLD', 'SA', 'WA', 'TAS', 'NT', 'ACT'];

// Latitude/longitude ranges for Australia
$latMin = -44.0;
$latMax = -10.0;
$lngMin = 113.0;
$lngMax = 154.0;

// Open CSV file
$handle = fopen($csvFile, 'r');
if ($handle === false) {
    echo "ERROR: Failed to open CSV file\n";
    exit(1);
}

// Read header
$headers = fgetcsv($handle);
if ($headers === false) {
    echo "ERROR: Failed to read CSV header\n";
    fclose($handle);
    exit(1);
}

// Trim headers
$headers = array_map('trim', $headers);

// Check required columns
echo "Test 1: Required columns...\n";
$headerMap = [];
$missingColumns = [];
foreach ($requiredColumns as $col) {
    $idx = array_search($col, $headers);
    if ($idx === false) {
        $missingColumns[] = $col;
        $failed++;
    } else {
        $headerMap[$col] = $idx;
        $passed++;
    }
}

if (!empty($missingColumns)) {
    $errors[] = "Missing required columns: " . implode(', ', $missingColumns);
    echo "  FAIL: Missing columns: " . implode(', ', $missingColumns) . "\n\n";
} else {
    echo "  PASS: All required columns present\n\n";
}

// Map all columns
foreach ($allColumns as $col) {
    $idx = array_search($col, $headers);
    if ($idx !== false) {
        $headerMap[$col] = $idx;
    }
}

// Validate rows
echo "Test 2: Row validation...\n";
$rowNum = 1;
$names = [];
$coordinates = [];

while (($row = fgetcsv($handle)) !== false) {
    $rowNum++;
    
    if (count($row) < count($headers)) {
        $warnings[] = "Row $rowNum: Incomplete row (expected " . count($headers) . " columns, got " . count($row) . ")";
        continue;
    }
    
    // Get values
    $name = isset($headerMap['name']) ? trim($row[$headerMap['name']]) : '';
    $lat = isset($headerMap['lat']) ? trim($row[$headerMap['lat']]) : '';
    $lng = isset($headerMap['lng']) ? trim($row[$headerMap['lng']]) : '';
    $state = isset($headerMap['state']) ? trim($row[$headerMap['state']]) : '';
    $region = isset($headerMap['region']) ? trim($row[$headerMap['region']]) : '';
    
    // Validate name
    if (empty($name)) {
        $errors[] = "Row $rowNum: Name is empty";
        $failed++;
    } else {
        $passed++;
        // Check for duplicates
        $nameKey = strtolower($name);
        if (isset($names[$nameKey])) {
            $warnings[] = "Row $rowNum: Duplicate name '$name' (also at row " . $names[$nameKey] . ")";
        } else {
            $names[$nameKey] = $rowNum;
        }
    }
    
    // Validate latitude
    if (empty($lat)) {
        $errors[] = "Row $rowNum: Latitude is empty";
        $failed++;
    } elseif (!is_numeric($lat)) {
        $errors[] = "Row $rowNum: Latitude is not numeric: $lat";
        $failed++;
    } else {
        $latFloat = (float)$lat;
        if ($latFloat < $latMin || $latFloat > $latMax) {
            $errors[] = "Row $rowNum: Latitude out of range for Australia: $lat (expected $latMin to $latMax)";
            $failed++;
        } else {
            $passed++;
        }
    }
    
    // Validate longitude
    if (empty($lng)) {
        $errors[] = "Row $rowNum: Longitude is empty";
        $failed++;
    } elseif (!is_numeric($lng)) {
        $errors[] = "Row $rowNum: Longitude is not numeric: $lng";
        $failed++;
    } else {
        $lngFloat = (float)$lng;
        if ($lngFloat < $lngMin || $lngFloat > $lngMax) {
            $errors[] = "Row $rowNum: Longitude out of range for Australia: $lng (expected $lngMin to $lngMax)";
            $failed++;
        } else {
            $passed++;
        }
    }
    
    // Check for duplicate coordinates
    $coordKey = "$latFloat|$lngFloat";
    if (isset($coordinates[$coordKey])) {
        $warnings[] = "Row $rowNum: Duplicate coordinates ($lat, $lng) (also at row " . $coordinates[$coordKey] . ")";
    } else {
        $coordinates[$coordKey] = $rowNum;
    }
    
    // Validate state
    if (empty($state)) {
        $errors[] = "Row $rowNum: State is empty";
        $failed++;
    } else {
        $stateUpper = strtoupper($state);
        if (!in_array($stateUpper, $validStates)) {
            $warnings[] = "Row $rowNum: State code '$state' not in standard list (valid: " . implode(', ', $validStates) . ")";
        }
        $passed++;
    }
    
    // Validate region
    if (empty($region)) {
        $errors[] = "Row $rowNum: Region is empty";
        $failed++;
    } else {
        $passed++;
    }
}

fclose($handle);

// Summary
echo "\n==========================================\n";
echo "Summary: $passed passed, $failed failed\n\n";

if (!empty($warnings)) {
    echo "Warnings:\n";
    foreach ($warnings as $warning) {
        echo "  - $warning\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "Errors:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    echo "\n";
    exit(1);
} else {
    echo "All validation checks passed!\n";
    if (!empty($warnings)) {
        echo "Note: Some warnings were found (see above).\n";
    }
    exit(0);
}

