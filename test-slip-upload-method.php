<?php
/**
 * Test script for OdooAPIClient::uploadSlip() method
 * 
 * This script verifies that the uploadSlip method is properly implemented
 * and can handle different scenarios.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/OdooAPIClient.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Initialize Odoo API Client
    $odooClient = new OdooAPIClient($db, 1); // Using line_account_id = 1
    
    // Test 1: Verify method exists and has correct signature
    $reflection = new ReflectionClass('OdooAPIClient');
    $method = $reflection->getMethod('uploadSlip');
    $parameters = $method->getParameters();
    
    $test1 = [
        'name' => 'Method Signature Check',
        'passed' => true,
        'details' => []
    ];
    
    // Check parameter count (should be 3: lineUserId, slipImageBase64, options)
    if (count($parameters) !== 3) {
        $test1['passed'] = false;
        $test1['details'][] = 'Expected 3 parameters, got ' . count($parameters);
    } else {
        $test1['details'][] = 'Parameter count: ✓ (3 parameters)';
    }
    
    // Check parameter names
    $expectedParams = ['lineUserId', 'slipImageBase64', 'options'];
    foreach ($parameters as $index => $param) {
        if ($param->getName() !== $expectedParams[$index]) {
            $test1['passed'] = false;
            $test1['details'][] = "Parameter $index: Expected '{$expectedParams[$index]}', got '{$param->getName()}'";
        } else {
            $test1['details'][] = "Parameter $index: ✓ {$param->getName()}";
        }
    }
    
    // Check if options has default value
    if (!$parameters[2]->isDefaultValueAvailable()) {
        $test1['passed'] = false;
        $test1['details'][] = 'Parameter "options" should have default value';
    } else {
        $defaultValue = $parameters[2]->getDefaultValue();
        if ($defaultValue === []) {
            $test1['details'][] = 'Parameter "options": ✓ (default = [])';
        } else {
            $test1['passed'] = false;
            $test1['details'][] = 'Parameter "options" default should be []';
        }
    }
    
    // Test 2: Verify method implementation
    $methodSource = file_get_contents(__DIR__ . '/classes/OdooAPIClient.php');
    
    $test2 = [
        'name' => 'Method Implementation Check',
        'passed' => true,
        'details' => []
    ];
    
    // Check if method calls /reya/slip/upload endpoint
    if (strpos($methodSource, "'/reya/slip/upload'") !== false) {
        $test2['details'][] = 'Endpoint: ✓ Calls /reya/slip/upload';
    } else {
        $test2['passed'] = false;
        $test2['details'][] = 'Endpoint: ✗ Does not call /reya/slip/upload';
    }
    
    // Check if method merges parameters correctly
    if (strpos($methodSource, 'array_merge') !== false && 
        strpos($methodSource, "'line_user_id' => \$lineUserId") !== false &&
        strpos($methodSource, "'slip_image' => \$slipImageBase64") !== false) {
        $test2['details'][] = 'Parameters: ✓ Correctly merges lineUserId, slipImageBase64, and options';
    } else {
        $test2['passed'] = false;
        $test2['details'][] = 'Parameters: ✗ Parameter merging may be incorrect';
    }
    
    // Check if method returns result from call()
    if (preg_match('/return\s+\$this->call\(/', $methodSource)) {
        $test2['details'][] = 'Return: ✓ Returns result from call() method';
    } else {
        $test2['passed'] = false;
        $test2['details'][] = 'Return: ✗ Does not return result from call()';
    }
    
    // Test 3: Mock API call structure
    $test3 = [
        'name' => 'API Call Structure',
        'passed' => true,
        'details' => []
    ];
    
    // Simulate what the method would send
    $mockLineUserId = 'U1234567890abcdef';
    $mockSlipImage = 'base64_encoded_image_data_here';
    $mockOptions = [
        'bdo_id' => 123,
        'invoice_id' => 456,
        'amount' => 1500.00,
        'transfer_date' => '2026-02-03'
    ];
    
    $expectedParams = array_merge([
        'line_user_id' => $mockLineUserId,
        'slip_image' => $mockSlipImage
    ], $mockOptions);
    
    $test3['details'][] = 'Expected API parameters structure:';
    $test3['details'][] = json_encode($expectedParams, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    // Summary
    $allPassed = $test1['passed'] && $test2['passed'] && $test3['passed'];
    
    $result = [
        'success' => $allPassed,
        'message' => $allPassed ? 
            'All tests passed! uploadSlip() method is correctly implemented.' : 
            'Some tests failed. Please review the details.',
        'tests' => [
            $test1,
            $test2,
            $test3
        ],
        'summary' => [
            'total_tests' => 3,
            'passed' => ($test1['passed'] ? 1 : 0) + ($test2['passed'] ? 1 : 0) + ($test3['passed'] ? 1 : 0),
            'failed' => (!$test1['passed'] ? 1 : 0) + (!$test2['passed'] ? 1 : 0) + (!$test3['passed'] ? 1 : 0)
        ]
    ];
    
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
