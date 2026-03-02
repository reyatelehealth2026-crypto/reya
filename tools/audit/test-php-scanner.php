<?php
/**
 * Test script for PhpEndpointScanner
 * 
 * This script tests the PhpEndpointScanner to verify it can:
 * - Scan PHP API files (inbox.php, inbox-v2.php)
 * - Extract action handlers from switch/case statements
 * - Detect HTTP methods
 * - Detect authentication
 * - Detect LINE account filtering
 * - Extract request parameters from $_GET, $_POST, php://input
 * - Extract response JSON structures
 * - Extract database tables and service classes
 */

require_once __DIR__ . '/bootstrap.php';

use Tools\Audit\Analyzers\PhpEndpointScanner;

echo "=== PhpEndpointScanner Test ===\n\n";

// Get the base path (re-ya directory)
$basePath = dirname(__DIR__, 2);

echo "PHP Backend Path: {$basePath}\n";
echo "API Path: {$basePath}/api\n\n";

// Create scanner instance
$scanner = new PhpEndpointScanner($basePath);

echo "Scanner: {$scanner->getName()} v{$scanner->getVersion()}\n\n";

// Validate
echo "Validating scanner...\n";
if (!$scanner->validate()) {
    echo "❌ Validation failed:\n";
    foreach ($scanner->getValidationErrors() as $error) {
        echo "  - {$error}\n";
    }
    exit(1);
}
echo "✓ Validation passed\n\n";

// Run analysis
echo "Running analysis...\n";
$result = $scanner->analyze();

if (!$result['success']) {
    echo "❌ Analysis failed:\n";
    foreach ($result['errors'] as $error) {
        echo "  - {$error}\n";
    }
    exit(1);
}

echo "✓ Analysis completed\n\n";

// Display statistics
$stats = $result['statistics'];
echo "=== Scan Statistics ===\n";
echo "Files scanned: {$stats['files_scanned']}\n";
echo "Endpoints found: {$stats['endpoints_found']}\n";
echo "Errors: {$stats['errors']}\n";
echo "Skipped files: {$stats['skipped_files']}\n\n";

// Display sample endpoints
echo "=== Sample Endpoints ===\n";
$endpoints = $result['endpoints'];
$sampleCount = min(10, count($endpoints));

for ($i = 0; $i < $sampleCount; $i++) {
    $endpoint = $endpoints[$i];
    echo "\n" . ($i + 1) . ". {$endpoint['file']} - {$endpoint['action']}\n";
    echo "   Method: {$endpoint['method']}\n";
    echo "   Authentication: " . ($endpoint['authentication'] ? 'Yes' : 'No') . "\n";
    echo "   LINE Account Filtering: " . ($endpoint['lineAccountFiltering'] ? 'Yes' : 'No') . "\n";
    
    if (!empty($endpoint['requestParams'])) {
        echo "   Request Params: " . implode(', ', array_slice($endpoint['requestParams'], 0, 5));
        if (count($endpoint['requestParams']) > 5) {
            echo " (+" . (count($endpoint['requestParams']) - 5) . " more)";
        }
        echo "\n";
    }
    
    if (!empty($endpoint['serviceClasses'])) {
        echo "   Service Classes: " . implode(', ', $endpoint['serviceClasses']) . "\n";
    }
    
    if (!empty($endpoint['databaseTables'])) {
        echo "   Database Tables: " . implode(', ', array_slice($endpoint['databaseTables'], 0, 3));
        if (count($endpoint['databaseTables']) > 3) {
            echo " (+" . (count($endpoint['databaseTables']) - 3) . " more)";
        }
        echo "\n";
    }
    
    if (!empty($endpoint['responseFormat'])) {
        $successCount = count($endpoint['responseFormat']['success_responses'] ?? []);
        $errorCount = count($endpoint['responseFormat']['error_responses'] ?? []);
        echo "   Response Format: {$successCount} success, {$errorCount} error\n";
    }
}

// Group endpoints by file
echo "\n\n=== Endpoints by File ===\n";
$byFile = [];
foreach ($endpoints as $endpoint) {
    $file = $endpoint['file'];
    if (!isset($byFile[$file])) {
        $byFile[$file] = [];
    }
    $byFile[$file][] = $endpoint['action'];
}

foreach ($byFile as $file => $actions) {
    echo "\n{$file}: " . count($actions) . " actions\n";
    // Show first 5 actions
    $sampleActions = array_slice($actions, 0, 5);
    foreach ($sampleActions as $action) {
        echo "  - {$action}\n";
    }
    if (count($actions) > 5) {
        echo "  ... and " . (count($actions) - 5) . " more\n";
    }
}

// Summary
echo "\n=== Summary ===\n";
echo "Total files scanned: " . count($byFile) . "\n";
echo "Total endpoints: " . count($endpoints) . "\n";

$authCount = count(array_filter($endpoints, fn($e) => $e['authentication']));
$lineFilterCount = count(array_filter($endpoints, fn($e) => $e['lineAccountFiltering']));
$withParams = count(array_filter($endpoints, fn($e) => !empty($e['requestParams'])));
$withServices = count(array_filter($endpoints, fn($e) => !empty($e['serviceClasses'])));

echo "Endpoints with authentication: {$authCount}\n";
echo "Endpoints with LINE account filtering: {$lineFilterCount}\n";
echo "Endpoints with request parameters: {$withParams}\n";
echo "Endpoints using service classes: {$withServices}\n";

echo "\n✅ Test completed successfully!\n";
