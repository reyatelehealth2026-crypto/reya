<?php
/**
 * Test inbox-groups.php JSON response
 * Direct test without going through Next.js
 */

// Simulate request
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'get_groups';
$_GET['line_account_id'] = '3';

// Set headers to simulate Next.js request
$_SERVER['HTTP_X_ADMIN_ID'] = '1';
$_SERVER['HTTP_X_LINE_ACCOUNT_ID'] = '3';

echo "=== Testing inbox-groups.php ===\n";
echo "Method: GET\n";
echo "Action: get_groups\n";
echo "Line Account ID: 3\n\n";

echo "=== Response ===\n";

// Capture output
ob_start();
include __DIR__ . '/api/inbox-groups.php';
$output = ob_get_clean();

echo $output . "\n\n";

// Validate JSON
$json = json_decode($output, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "✓ Valid JSON response\n";
    echo "Success: " . ($json['success'] ? 'true' : 'false') . "\n";
    
    if (isset($json['data'])) {
        echo "Groups count: " . count($json['data']['groups'] ?? []) . "\n";
    }
    
    if (isset($json['error'])) {
        echo "Error: " . $json['error'] . "\n";
    }
} else {
    echo "✗ Invalid JSON: " . json_last_error_msg() . "\n";
    echo "Output length: " . strlen($output) . " bytes\n";
    echo "First 500 chars:\n" . substr($output, 0, 500) . "\n";
}
