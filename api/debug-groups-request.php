<?php
/**
 * Debug script to test inbox-groups.php with detailed logging
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

echo "<h1>Debug: inbox-groups.php Request</h1>";

// Simulate GET request
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'get_groups';
$_GET['line_account_id'] = '3';
$_SERVER['HTTP_X_ADMIN_ID'] = '1';
$_SERVER['HTTP_X_LINE_ACCOUNT_ID'] = '3';

echo "<h2>Request Details</h2>";
echo "<pre>";
echo "Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "Action: " . $_GET['action'] . "\n";
echo "Line Account ID: " . $_GET['line_account_id'] . "\n";
echo "Admin ID Header: " . $_SERVER['HTTP_X_ADMIN_ID'] . "\n";
echo "</pre>";

echo "<h2>Testing Database Connection</h2>";
try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    
    $db = Database::getInstance()->getConnection();
    echo "<p style='color: green;'>✓ Database connected</p>";
    
    // Test query
    $stmt = $db->query("SELECT COUNT(*) as count FROM line_groups");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Groups in database: " . $result['count'] . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    exit;
}

echo "<h2>Testing LineAPI Class</h2>";
try {
    require_once __DIR__ . '/../classes/LineAPI.php';
    require_once __DIR__ . '/../classes/LineAccountManager.php';
    echo "<p style='color: green;'>✓ Classes loaded</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Class loading error: " . $e->getMessage() . "</p>";
    exit;
}

echo "<h2>Calling inbox-groups.php</h2>";
echo "<p>Capturing output...</p>";
echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";

// Capture output
ob_start();
include __DIR__ . '/inbox-groups.php';
$output = ob_get_clean();

echo htmlspecialchars($output);
echo "</pre>";

echo "<h2>JSON Validation</h2>";
$json = json_decode($output, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "<p style='color: green;'>✓ Valid JSON</p>";
    echo "<pre>" . json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
} else {
    echo "<p style='color: red;'>✗ Invalid JSON: " . json_last_error_msg() . "</p>";
    echo "<p>Output length: " . strlen($output) . " bytes</p>";
}
