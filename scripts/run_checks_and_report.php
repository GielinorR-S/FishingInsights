<?php
/**
 * Run Checks and Generate Report
 * PHP 7.3.33 compatible
 * 
 * Runs contract verification and smoke tests, then generates LAST_RUN_REPORT.md
 * with git information and test outputs.
 */

$repoRoot = __DIR__ . '/..';
$reportPath = $repoRoot . '/docs/analysis/LAST_RUN_REPORT.md';
$verifyContractScript = __DIR__ . '/verify_contract.php';
$smokeTestScript = __DIR__ . '/smoke_test.php';

// Change to repo root for git commands
chdir($repoRoot);

// Get git information
function getGitBranch() {
    $output = shell_exec('git branch --show-current 2>&1');
    return trim($output);
}

function getGitHead() {
    $output = shell_exec('git rev-parse HEAD 2>&1');
    return trim($output);
}

function getGitShortHead() {
    $output = shell_exec('git rev-parse --short HEAD 2>&1');
    return trim($output);
}

function getGitStatus() {
    $output = shell_exec('git status --porcelain 2>&1');
    return trim($output);
}

function isGitClean() {
    $status = getGitStatus();
    return empty($status);
}

function getLastCommits($count = 5) {
    $output = [];
    $exitCode = 0;
    exec("git log -n {$count} --pretty=format:\"%h - %s\" 2>&1", $output, $exitCode);
    if ($exitCode !== 0) {
        return [];
    }
    return array_filter($output);
}

function getChangedFiles() {
    $status = getGitStatus();
    if (empty($status)) {
        return [];
    }
    
    $lines = explode("\n", trim($status));
    $files = [];
    foreach ($lines as $line) {
        if (preg_match('/^([AMDRC\?]{2})\s+(.+)$/', $line, $matches)) {
            $statusCode = $matches[1];
            $file = $matches[2];
            $files[] = [
                'status' => $statusCode,
                'file' => $file
            ];
        }
    }
    return $files;
}

function runScript($scriptPath) {
    // Use exec to capture both output and exit code
    $output = [];
    $exitCode = 0;
    $command = 'php ' . escapeshellarg($scriptPath) . ' 2>&1';
    exec($command, $output, $exitCode);
    
    $outputString = implode("\n", $output);
    
    // Determine success: exit code 0 = success, non-zero = failure
    $success = ($exitCode === 0);
    
    // Additional check: look for explicit failure patterns in output
    if ($success && !empty($outputString)) {
        // Even if exit code is 0, check for failure indicators
        if (preg_match('/Summary:.*\d+.*failed/i', $outputString) ||
            preg_match('/All contract checks passed!/i', $outputString) === 0 && 
            preg_match('/All tests passed!/i', $outputString) === 0 &&
            preg_match('/FAIL|Error|failed|ERROR/i', $outputString)) {
            // If we see failure patterns but no success message, it might have failed
            // But trust exit code as primary indicator
        }
    }
    
    return [
        'output' => $outputString,
        'success' => $success,
        'exitCode' => $exitCode
    ];
}

// Get git information
$branch = getGitBranch();
$head = getGitHead();
$shortHead = getGitShortHead();
$isClean = isGitClean();
$commits = getLastCommits(5);
$changedFiles = getChangedFiles();

// Run tests
echo "Running contract verification...\n";
$contractResult = runScript($verifyContractScript);

echo "Running smoke tests...\n";
$smokeResult = runScript($smokeTestScript);

// Determine overall success
$allPassed = $contractResult['success'] && $smokeResult['success'];

// Generate report
$timestamp = date('Y-m-d H:i:s');
$dateOnly = date('Y-m-d');

$report = "# Last Run Report\n\n";
$report .= "**Generated:** {$dateOnly}  \n";
$report .= "**Generated At:** {$timestamp}  \n";
$report .= "**Status:** " . ($allPassed ? "✅ All checks passed" : "⚠️ Some checks failed") . "\n\n";
$report .= "---\n\n";

// Repo State
$report .= "## 1. Repo State\n\n";
$report .= "### Current Branch\n\n";
$report .= "```\n";
$report .= $branch . "\n";
$report .= "```\n\n";

$report .= "### Latest Commit Hash\n\n";
$report .= "```\n";
$report .= $head . "\n";
$report .= "```\n\n";

$report .= "### Short Hash\n\n";
$report .= "```\n";
$report .= $shortHead . "\n";
$report .= "```\n\n";

$report .= "### Working Directory Status\n\n";
if ($isClean) {
    $report .= "✅ **Clean** - No uncommitted changes\n\n";
} else {
    $report .= "⚠️ **Dirty** - Uncommitted changes detected\n\n";
    if (!empty($changedFiles)) {
        $report .= "**Changed/Untracked Files:**\n\n";
        foreach ($changedFiles as $file) {
            $status = $file['status'];
            $filename = $file['file'];
            $report .= "- `{$status}` `{$filename}`\n";
        }
        $report .= "\n";
    }
}

$report .= "### Last 5 Commits\n\n";
if (!empty($commits)) {
    foreach ($commits as $commit) {
        $report .= "- `{$commit}`\n";
    }
} else {
    $report .= "No commits found\n";
}
$report .= "\n";

// Test Results
$report .= "## 2. Test Results\n\n";

$report .= "### Contract Verification\n\n";
$report .= "**Status:** " . ($contractResult['success'] ? "✅ PASS" : "❌ FAIL") . "\n";
if (isset($contractResult['exitCode'])) {
    $report .= "**Exit Code:** " . $contractResult['exitCode'] . "\n";
}
$report .= "\n";
$report .= "**Output:**\n\n";
$report .= "```\n";
$report .= $contractResult['output'];
$report .= "\n```\n\n";

$report .= "### Smoke Tests\n\n";
$report .= "**Status:** " . ($smokeResult['success'] ? "✅ PASS" : "❌ FAIL") . "\n";
if (isset($smokeResult['exitCode'])) {
    $report .= "**Exit Code:** " . $smokeResult['exitCode'] . "\n";
}
$report .= "\n";
$report .= "**Output:**\n\n";
$report .= "```\n";
$report .= $smokeResult['output'];
$report .= "\n```\n\n";

// Summary
$report .= "## 3. Summary\n\n";
$report .= "**Overall Status:** " . ($allPassed ? "✅ All checks passed" : "⚠️ Some checks failed") . "\n\n";
$report .= "**Test Results:**\n";
$report .= "- Contract Verification: " . ($contractResult['success'] ? "✅ PASS" : "❌ FAIL") . "\n";
$report .= "- Smoke Tests: " . ($smokeResult['success'] ? "✅ PASS" : "❌ FAIL") . "\n\n";

if (!$allPassed) {
    $report .= "**⚠️ Warning:** Some tests failed. Review the output above for details.\n\n";
}

$report .= "---\n\n";
$report .= "**Report Generated By:** `scripts/run_checks_and_report.php`\n";
$report .= "**To Regenerate:** Run `php scripts/run_checks_and_report.php`\n";

// Write report
if (file_put_contents($reportPath, $report) === false) {
    echo "ERROR: Failed to write report to {$reportPath}\n";
    exit(1);
}

echo "\n";
echo "Report generated: {$reportPath}\n";
echo "Status: " . ($allPassed ? "✅ All checks passed" : "⚠️ Some checks failed") . "\n";

// Exit with appropriate code
exit($allPassed ? 0 : 1);

