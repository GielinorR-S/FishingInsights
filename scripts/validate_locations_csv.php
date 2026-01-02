<?php
/**
 * Locations CSV Validation Script
 * PHP 7.3.33 compatible
 * 
 * Validates data/locations.csv for:
 * - Required columns
 * - VIC bounding box sanity
 * - Consistent region enum
 * - Duplicate detection
 */

// Define required columns and their expected types/ranges
$requiredColumns = [
    'name' => ['type' => 'string', 'min_length' => 1],
    'lat' => ['type' => 'float', 'min' => -39.2, 'max' => -33.9], // VIC approximate bounds
    'lng' => ['type' => 'float', 'min' => 140.9, 'max' => 150.0], // VIC approximate bounds
    'state' => ['type' => 'string', 'pattern' => '/^VIC$/i'], // Must be VIC
    'region' => ['type' => 'string', 'enum' => [
        'Melbourne/Port Phillip',
        'Mornington Peninsula',
        'Western Port',
        'Geelong/Bellarine',
        'Great Ocean Road',
        'Gippsland',
        'South West',
        'Inland'
    ]],
    'type' => ['type' => 'string', 'min_length' => 1],
    'access' => ['type' => 'string', 'min_length' => 1],
    'notes' => ['type' => 'string'],
    'safety' => ['type' => 'string']
];

// Valid regions (case-insensitive matching)
$validRegions = [
    'Melbourne/Port Phillip',
    'Mornington Peninsula',
    'Western Port',
    'Geelong/Bellarine',
    'Great Ocean Road',
    'Gippsland',
    'South West',
    'Inland'
];

$csvFile = __DIR__ . '/../data/locations.csv';
$errors = [];
$warnings = [];
$validRows = 0;
$processedRows = 0;
$uniqueLocations = []; // To check for duplicates using unique key

echo "FishingInsights Locations CSV Validation\n";
echo "==========================================\n\n";

if (!file_exists($csvFile)) {
    echo "ERROR: CSV file not found at: $csvFile\n";
    exit(1);
}

$handle = fopen($csvFile, 'r');
if ($handle === false) {
    echo "ERROR: Failed to open CSV file: $csvFile\n";
    exit(1);
}

$header = fgetcsv($handle);
if ($header === false) {
    $errors[] = "CSV is empty or cannot read header.";
} else {
    // Clean header (remove BOM, trim whitespace)
    $header = array_map('trim', $header);
    // Remove UTF-8 BOM if present
    if (!empty($header[0]) && substr($header[0], 0, 3) === "\xEF\xBB\xBF") {
        $header[0] = substr($header[0], 3);
    }
    
    // Test 1: Required columns
    echo "Test 1: Required columns...\n";
    $missingColumns = array_diff(array_keys($requiredColumns), $header);
    if (!empty($missingColumns)) {
        $errors[] = "Missing required columns: " . implode(', ', $missingColumns);
        echo "  FAIL: Missing columns\n";
    } else {
        echo "  PASS: All required columns present\n";
    }
}

echo "\nTest 2: Row validation...\n";
$rowNum = 1; // Start after header
while (($data = fgetcsv($handle)) !== false) {
    $rowNum++;
    $processedRows++;
    
    if (count($data) !== count($header)) {
        $warnings[] = "Row $rowNum: Incomplete row (expected " . count($header) . " columns, got " . count($data) . ")";
        continue;
    }

    $rowData = array_combine($header, $data);
    
    // Skip if array_combine failed (empty row or malformed)
    if ($rowData === false || !isset($rowData['name'])) {
        $warnings[] = "Row $rowNum: Skipped (malformed or empty)";
        continue;
    }
    
    $rowErrors = [];

    // Generate unique key for duplicate detection (normalized name + region + rounded coords)
    $normalizedName = strtolower(trim($rowData['name']));
    $normalizedName = preg_replace('/\s+/', ' ', $normalizedName);
    $normalizedRegion = strtolower(trim($rowData['region']));
    $roundedLat = round((float)$rowData['lat'], 4);
    $roundedLng = round((float)$rowData['lng'], 4);
    $uniqueKey = $normalizedName . '|' . $normalizedRegion . '|' . $roundedLat . '|' . $roundedLng;
    
    if (isset($uniqueLocations[$uniqueKey])) {
        $rowErrors[] = "Duplicate location (matches row " . $uniqueLocations[$uniqueKey] . ")";
    } else {
        $uniqueLocations[$uniqueKey] = $rowNum;
    }

    foreach ($requiredColumns as $colName => $rules) {
        $value = isset($rowData[$colName]) ? trim($rowData[$colName]) : '';

        if (isset($rules['min_length']) && strlen($value) < $rules['min_length']) {
            $rowErrors[] = "$colName: Value too short (min " . $rules['min_length'] . ")";
        }

        switch ($rules['type']) {
            case 'string':
                if (isset($rules['pattern']) && !preg_match($rules['pattern'], $value)) {
                    $rowErrors[] = "$colName: Does not match pattern (expected: " . $rules['pattern'] . ")";
                }
                if (isset($rules['enum'])) {
                    $valueLower = strtolower($value);
                    $matched = false;
                    foreach ($rules['enum'] as $enumValue) {
                        if (strtolower($enumValue) === $valueLower) {
                            $matched = true;
                            break;
                        }
                    }
                    if (!$matched) {
                        $rowErrors[] = "$colName: Invalid region '$value' (must be one of: " . implode(', ', $rules['enum']) . ")";
                    }
                }
                break;
            case 'float':
                if (!is_numeric($value)) {
                    $rowErrors[] = "$colName: Not a valid number";
                } else {
                    $floatVal = (float)$value;
                    if (isset($rules['min']) && $floatVal < $rules['min']) {
                        $rowErrors[] = "$colName: Value " . $floatVal . " is below min " . $rules['min'];
                    }
                    if (isset($rules['max']) && $floatVal > $rules['max']) {
                        $rowErrors[] = "$colName: Value " . $floatVal . " is above max " . $rules['max'];
                    }
                }
                break;
        }
    }

    if (!empty($rowErrors)) {
        $errors[] = "Row $rowNum: " . implode('; ', $rowErrors);
    } else {
        $validRows++;
    }
}

fclose($handle);

echo "\n==========================================\n";
echo "Summary: $validRows passed, " . count($errors) . " failed\n";
echo "Total rows processed: $processedRows\n\n";

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
    echo "✅ All validation checks passed!\n";
    if (!empty($warnings)) {
        echo "Note: Some warnings were found (see above).\n";
    }
    exit(0);
}

