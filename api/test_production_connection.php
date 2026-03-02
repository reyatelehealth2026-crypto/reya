<?php
/**
 * Test Odoo Production Connection
 * Run this script to verify connection to Odoo ERP (Production)
 * 
 * Usage: php api/test_production_connection.php
 */

require_once __DIR__ . '/../config/config.php';

// Force Production Config for this test
$baseUrl = 'https://erp.cnyrxapp.com';
$apiKey = '5pG-doAH1EEqHXurM3t466WAgKRYemmK7FANAB74o5A';
$testLineId = 'Utest123456';

echo "=== Odoo Production Connection Test ===\n";
echo "Base URL: $baseUrl\n";
echo "API Key: " . substr($apiKey, 0, 5) . "...\n\n";

// Helper function to make requests
function callOdoo($endpoint, $params, $apiKey = null)
{
    global $baseUrl;

    $url = $baseUrl . $endpoint;
    $body = [
        'jsonrpc' => '2.0',
        'params' => $params
    ];

    $headers = ['Content-Type: application/json'];
    if ($apiKey) {
        $headers[] = 'X-Api-Key: ' . $apiKey;
    }

    echo "Request: $endpoint\n";
    // echo "Body: " . json_encode($body) . "\n";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false // For testing
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "Error: $error\n";
        return null;
    }

    echo "Status: $httpCode\n";
    echo "Response: " . substr($response, 0, 200) . (strlen($response) > 200 ? '...' : '') . "\n";
    echo "----------------------------------------\n";

    return json_decode($response, true);
}

// Test 1: Health Check
echo "\n[Test 1] Health Check (No Auth)\n";
callOdoo('/reya/health', []);

// Test 2: User Link
echo "\n[Test 2] User Link (Customer Code + Phone)\n";
callOdoo('/reya/user/link', [
    'line_user_id' => $testLineId,
    'customer_code' => 'PC200134',
    'phone' => '0849915142'
], $apiKey);

// Test 3: Get Orders
echo "\n[Test 3] Get Orders\n";
callOdoo('/reya/orders', [
    'line_user_id' => $testLineId,
    'limit' => 5
], $apiKey);

echo "\nDone.\n";
