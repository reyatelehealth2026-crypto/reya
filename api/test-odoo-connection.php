<?php
/**
 * Test Odoo API Connection
 * Debug script to test the API connection
 */

header('Content-Type: application/json; charset=utf-8');

// Configuration from postman_api_user2.json
$baseUrl = 'https://erp.cnyrxapp.com';
$apiUser = 'webapi_user2@cny.co';
$userToken = '@ewNI*4X*/4t9vgMds2Gzs3j=VG%q%ERYM-1A/utT0#CUZ&UR&$pwvuxj!MNUcruJ@RZ/p7$uN*fdqE6xktQdGxGov%?L0@@CekhyzeROSv2/qmj&%G-vlHq$4V8&AHC/XkxI$Tgkbq3p/6faPvf8wjIP#hfZM7GimVkXbvpvsrfZKOCGk?ldTpL9=5qI-eCVs29xIyr0';

$testResults = [];

// Test 1: Check if cURL is available
$testResults['curl_available'] = function_exists('curl_init');

// Test 2: Check if we can reach the server
$testResults['server_reachable'] = false;
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $baseUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_NOBODY => true
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$curlErrno = curl_errno($ch);
curl_close($ch);

$testResults['server_check'] = [
    'http_code' => $httpCode,
    'curl_error' => $curlError,
    'curl_errno' => $curlErrno,
    'reachable' => $httpCode > 0
];

// Test 3: Test actual API call
$testResults['api_test'] = [];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $baseUrl . '/ineco_gc/get_product',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['PRODUCT_CODE' => '0001']),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Api-User: ' . $apiUser,
        'User-Token: ' . $userToken
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$curlInfo = curl_getinfo($ch);
curl_close($ch);

$testResults['api_test'] = [
    'endpoint' => $baseUrl . '/ineco_gc/get_product',
    'http_code' => $httpCode,
    'curl_error' => $curlError,
    'response_length' => strlen($response),
    'response_preview' => substr($response, 0, 500),
    'total_time' => $curlInfo['total_time'] ?? null,
    'connect_time' => $curlInfo['connect_time'] ?? null,
    'ssl_verify_result' => $curlInfo['ssl_verify_result'] ?? null
];

// Try to decode response
$decoded = json_decode($response, true);
if ($decoded !== null) {
    $testResults['api_test']['response_json'] = $decoded;
}

// Test 4: Check DNS resolution
$testResults['dns_check'] = [];
$host = parse_url($baseUrl, PHP_URL_HOST);
$ip = gethostbyname($host);
$testResults['dns_check'] = [
    'host' => $host,
    'resolved_ip' => $ip,
    'dns_resolved' => $ip !== $host
];

// Summary
$testResults['summary'] = [
    'curl_ok' => $testResults['curl_available'],
    'dns_ok' => $testResults['dns_check']['dns_resolved'],
    'server_ok' => $testResults['server_check']['reachable'],
    'api_ok' => $testResults['api_test']['http_code'] >= 200 && $testResults['api_test']['http_code'] < 300
];

$testResults['overall_status'] =
    $testResults['summary']['curl_ok'] &&
    $testResults['summary']['dns_ok'] &&
    $testResults['summary']['server_ok'] &&
    $testResults['summary']['api_ok'] ? 'SUCCESS' : 'FAILED';

echo json_encode($testResults, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
