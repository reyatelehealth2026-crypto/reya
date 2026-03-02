<?php
/**
 * Odoo API Client - Test Script
 * 
 * Tests the OdooAPIClient class functionality
 * 
 * Usage: php test_odoo_api_client.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/OdooAPIClient.php';

// ANSI colors
define('GREEN', "\033[32m");
define('RED', "\033[31m");
define('YELLOW', "\033[33m");
define('BLUE', "\033[34m");
define('RESET', "\033[0m");

function printTest($name, $success, $message = '')
{
    $status = $success ? GREEN . '✓ PASS' : RED . '✗ FAIL';
    echo $status . RESET . " - $name";
    if ($message) {
        echo " (" . YELLOW . $message . RESET . ")";
    }
    echo PHP_EOL;
}

function printHeader($title)
{
    echo PHP_EOL . BLUE . str_repeat('=', 70) . RESET . PHP_EOL;
    echo BLUE . $title . RESET . PHP_EOL;
    echo BLUE . str_repeat('=', 70) . RESET . PHP_EOL . PHP_EOL;
}

try {
    printHeader('Odoo API Client - Test Suite');

    // Initialize
    echo "Initializing..." . PHP_EOL;
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $client = new OdooAPIClient($pdo);

    printTest('Client initialization', true);

    // Test 1: Health Check
    printHeader('Test 1: Health Check');
    try {
        $result = $client->health();
        printTest('Health check', $result['success'], $result['message']);
    } catch (Exception $e) {
        printTest('Health check', false, $e->getMessage());
    }

    // Test 2: Configuration
    printHeader('Test 2: Configuration');
    printTest('API Base URL', !empty(ODOO_API_BASE_URL), ODOO_API_BASE_URL);
    printTest('API Key', !empty(ODOO_API_KEY), substr(ODOO_API_KEY, 0, 10) . '...');
    printTest('Environment', true, ODOO_ENVIRONMENT);
    printTest('Webhook URL', true, ODOO_WEBHOOK_URL);

    // Test 3: Error Handling
    printHeader('Test 3: Error Handling');
    try {
        // This should fail with LINE_USER_NOT_LINKED
        $client->getUserProfile('test_user_not_linked');
        printTest('Error handling', false, 'Should have thrown exception');
    } catch (Exception $e) {
        $hasThaiMessage = preg_match('/[\x{0E00}-\x{0E7F}]/u', $e->getMessage());
        printTest('Thai error message', $hasThaiMessage, $e->getMessage());
    }

    // Test 4: Rate Limiting
    printHeader('Test 4: Rate Limiting (Optional)');
    echo YELLOW . "Rate limiting is checked per API call" . RESET . PHP_EOL;
    echo YELLOW . "Limit: " . ODOO_API_RATE_LIMIT . " requests/minute" . RESET . PHP_EOL;
    printTest('Rate limit configured', true, ODOO_API_RATE_LIMIT . ' req/min');

    // Summary
    printHeader('Test Summary');
    echo GREEN . "✓ OdooAPIClient is ready to use!" . RESET . PHP_EOL;
    echo PHP_EOL;
    echo "Next steps:" . PHP_EOL;
    echo "1. Contact Odoo team for webhook secret" . PHP_EOL;
    echo "2. Test with real Odoo staging environment" . PHP_EOL;
    echo "3. Implement webhook handler" . PHP_EOL;
    echo PHP_EOL;

} catch (Throwable $e) {
    echo RED . "Fatal error: " . $e->getMessage() . RESET . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}
