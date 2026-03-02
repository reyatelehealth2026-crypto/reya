<?php
/**
 * Test script for NextJsEndpointScanner
 * 
 * This script tests the NextJsEndpointScanner to verify it can:
 * - Scan the Next.js inbox API directory
 * - Extract HTTP methods
 * - Detect authentication
 * - Detect LINE account filtering
 * - Extract request parameters
 * - Extract database tables
 */

require_once __DIR__ . '/bootstrap.php';

use Tools\Audit\Analyzers\NextJsEndpointScanner;

echo "=== NextJsEndpointScanner Test ===\n\n";

// Determine the path to the Next.js codebase
$nextjsPath = __DIR__ . '/../../../inboxreya/inbox';

echo "Next.js Path: {$nextjsPath}\n";
echo "API Path: {$nextjsPath}/src/app/api/inbox\n\n";

// Create scanner instance
$scanner = new NextJsEndpointScanner($nextjsPath);

echo "Scanner: {$scanner->getName()} v{$scanner->getVersion()}\n\n";

// Validate scanner
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
$sampleCount = min(5, count($endpoints));

for ($i = 0; $i < $sampleCount; $i++) {
    $endpoint = $endpoints[$i];
    echo "\n" . ($i + 1) . ". {$endpoint['method']} {$endpoint['path']}\n";
    echo "   File: " . basename(dirname($endpoint['file'])) . "/" . basename($endpoint['file']) . "\n";
    echo "   Authentication: " . ($endpoint['authentication'] ? 'Yes' : 'No') . "\n";
    echo "   LINE Account Filtering: " . ($endpoint['lineAccountFiltering'] ? 'Yes' : 'No') . "\n";
    
    if (!empty($endpoint['requestParams'])) {
        echo "   Request Params: " . implode(', ', $endpoint['requestParams']) . "\n";
    }
    
    if (!empty($endpoint['databaseTables'])) {
        echo "   Database Tables: " . implode(', ', $endpoint['databaseTables']) . "\n";
    }
    
    if (!empty($endpoint['phpBridgeCalls'])) {
        echo "   PHP Bridge Calls: " . count($endpoint['phpBridgeCalls']) . "\n";
    }
}

// Group endpoints by path
echo "\n\n=== Endpoints by Path ===\n";
$byPath = [];
foreach ($endpoints as $endpoint) {
    $path = $endpoint['path'];
    if (!isset($byPath[$path])) {
        $byPath[$path] = [];
    }
    $byPath[$path][] = $endpoint['method'];
}

ksort($byPath);
foreach ($byPath as $path => $methods) {
    echo "{$path}: " . implode(', ', $methods) . "\n";
}

// Summary
echo "\n=== Summary ===\n";
echo "Total unique paths: " . count($byPath) . "\n";
echo "Total endpoints: " . count($endpoints) . "\n";

$authCount = count(array_filter($endpoints, fn($e) => $e['authentication']));
$lineFilterCount = count(array_filter($endpoints, fn($e) => $e['lineAccountFiltering']));
$phpBridgeCount = count(array_filter($endpoints, fn($e) => !empty($e['phpBridgeCalls'])));

echo "Endpoints with authentication: {$authCount}\n";
echo "Endpoints with LINE account filtering: {$lineFilterCount}\n";
echo "Endpoints with PHP bridge calls: {$phpBridgeCount}\n";

echo "\n✅ Test completed successfully!\n";

